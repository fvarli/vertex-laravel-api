<?php

namespace App\Services;

use App\Exceptions\AppointmentConflictException;
use App\Models\Appointment;
use Carbon\Carbon;

class AppointmentService
{
    public function __construct(private readonly AppointmentReminderService $appointmentReminderService) {}

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
