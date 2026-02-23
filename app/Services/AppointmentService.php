<?php

namespace App\Services;

use App\Exceptions\AppointmentConflictException;
use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class AppointmentService
{
    public function __construct(private readonly AppointmentReminderService $appointmentReminderService) {}

    public function list(int $workspaceId, ?int $trainerUserId, array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $search = trim((string) ($filters['search'] ?? ''));
        $from = $filters['from'] ?? $filters['date_from'] ?? null;
        $to = $filters['to'] ?? $filters['date_to'] ?? null;
        $sort = (string) ($filters['sort'] ?? 'starts_at');
        $direction = (string) ($filters['direction'] ?? 'desc');

        return Appointment::query()
            ->with(['student', 'trainer', 'reminders'])
            ->where('workspace_id', $workspaceId)
            ->when($trainerUserId, fn ($q) => $q->where('trainer_user_id', $trainerUserId))
            ->when($from, fn ($q, $fromValue) => $q->where('starts_at', '>=', $fromValue))
            ->when($to, fn ($q, $toValue) => $q->where('starts_at', '<=', $toValue))
            ->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['whatsapp_status']) && $filters['whatsapp_status'] !== 'all', fn ($q) => $q->where('whatsapp_status', $filters['whatsapp_status']))
            ->when(isset($filters['trainer_id']), fn ($q) => $q->where('trainer_user_id', (int) $filters['trainer_id']))
            ->when(isset($filters['student_id']), fn ($q) => $q->where('student_id', (int) $filters['student_id']))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('location', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('student', function ($studentQuery) use ($search) {
                            $studentQuery->where('full_name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy($sort, $direction)
            ->paginate($perPage);
    }

    public function create(int $workspaceId, int $trainerUserId, int $studentId, array $data): Appointment
    {
        $startsAt = Carbon::parse($data['starts_at'])->utc();
        $endsAt = Carbon::parse($data['ends_at'])->utc();

        $this->assertNoConflict($workspaceId, $trainerUserId, $studentId, $startsAt, $endsAt);

        $appointment = Appointment::query()->create([
            'series_id' => $data['series_id'] ?? null,
            'series_occurrence_date' => $data['series_occurrence_date'] ?? null,
            'is_series_exception' => (bool) ($data['is_series_exception'] ?? false),
            'series_edit_scope_applied' => $data['series_edit_scope_applied'] ?? null,
            'workspace_id' => $workspaceId,
            'trainer_user_id' => $trainerUserId,
            'student_id' => $studentId,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => Appointment::STATUS_PLANNED,
            'location' => $data['location'] ?? null,
            'notes' => $data['notes'] ?? null,
        ])->load(['student', 'trainer']);

        $workspaceReminderPolicy = $appointment->workspace?->reminder_policy;
        $this->appointmentReminderService->syncForAppointment($appointment, is_array($workspaceReminderPolicy) ? $workspaceReminderPolicy : null);

        return $appointment;
    }

    public function update(Appointment $appointment, array $data): Appointment
    {
        $startsAt = isset($data['starts_at']) ? Carbon::parse($data['starts_at'])->utc() : $appointment->starts_at;
        $endsAt = isset($data['ends_at']) ? Carbon::parse($data['ends_at'])->utc() : $appointment->ends_at;
        $trainerUserId = $data['trainer_user_id'] ?? $appointment->trainer_user_id;
        $studentId = $data['student_id'] ?? $appointment->student_id;

        $this->assertNoConflict(
            workspaceId: $appointment->workspace_id,
            trainerUserId: $trainerUserId,
            studentId: $studentId,
            startsAt: $startsAt,
            endsAt: $endsAt,
            ignoreAppointmentId: $appointment->id,
        );

        $appointment->update([
            'trainer_user_id' => $trainerUserId,
            'student_id' => $studentId,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'is_series_exception' => array_key_exists('is_series_exception', $data)
                ? (bool) $data['is_series_exception']
                : $appointment->is_series_exception,
            'series_edit_scope_applied' => $data['series_edit_scope_applied'] ?? $appointment->series_edit_scope_applied,
            'location' => array_key_exists('location', $data) ? $data['location'] : $appointment->location,
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $appointment->notes,
        ]);

        $appointment = $appointment->refresh()->load(['student', 'trainer', 'workspace']);
        $workspaceReminderPolicy = $appointment->workspace?->reminder_policy;
        $this->appointmentReminderService->syncForAppointment($appointment, is_array($workspaceReminderPolicy) ? $workspaceReminderPolicy : null);

        return $appointment;
    }

    public function updateStatus(Appointment $appointment, string $status): Appointment
    {
        $this->assertStatusTransitionAllowed($appointment, $status);

        $appointment->update(['status' => $status]);

        $appointment = $appointment->refresh()->load(['student', 'trainer', 'workspace']);
        if ($status === Appointment::STATUS_CANCELLED) {
            $this->appointmentReminderService->cancelPending($appointment);
        } else {
            $workspaceReminderPolicy = $appointment->workspace?->reminder_policy;
            $this->appointmentReminderService->syncForAppointment($appointment, is_array($workspaceReminderPolicy) ? $workspaceReminderPolicy : null);
        }

        return $appointment;
    }

    private function assertStatusTransitionAllowed(Appointment $appointment, string $nextStatus): void
    {
        $currentStatus = (string) $appointment->status;

        if ($currentStatus === $nextStatus) {
            return;
        }

        $allowedTransitions = [
            Appointment::STATUS_PLANNED => [Appointment::STATUS_DONE, Appointment::STATUS_CANCELLED, Appointment::STATUS_NO_SHOW],
            Appointment::STATUS_DONE => [Appointment::STATUS_PLANNED],
            Appointment::STATUS_NO_SHOW => [Appointment::STATUS_PLANNED, Appointment::STATUS_DONE, Appointment::STATUS_CANCELLED],
            Appointment::STATUS_CANCELLED => [Appointment::STATUS_PLANNED],
        ];

        if (! in_array($nextStatus, $allowedTransitions[$currentStatus] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => [__('api.appointment.invalid_status_transition')],
            ]);
        }

        if (
            in_array($nextStatus, [Appointment::STATUS_DONE, Appointment::STATUS_NO_SHOW], true)
            && $appointment->starts_at?->isFuture()
        ) {
            throw ValidationException::withMessages([
                'status' => [__('api.appointment.cannot_complete_future')],
            ]);
        }
    }

    private function assertNoConflict(
        int $workspaceId,
        int $trainerUserId,
        int $studentId,
        Carbon $startsAt,
        Carbon $endsAt,
        ?int $ignoreAppointmentId = null
    ): void {
        $query = Appointment::query()
            ->where('workspace_id', $workspaceId)
            ->whereNotIn('status', [Appointment::STATUS_CANCELLED])
            ->where(function ($scope) use ($trainerUserId, $studentId) {
                $scope->where('trainer_user_id', $trainerUserId)
                    ->orWhere('student_id', $studentId);
            })
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt);

        if ($ignoreAppointmentId) {
            $query->whereKeyNot($ignoreAppointmentId);
        }

        if ($query->exists()) {
            throw new AppointmentConflictException(__('api.appointment.conflict'));
        }
    }
}
