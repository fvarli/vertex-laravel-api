<?php

namespace App\Services;

use App\Exceptions\AppointmentConflictException;
use App\Models\Appointment;
use Carbon\Carbon;

class AppointmentService
{
    public function create(int $workspaceId, int $trainerUserId, int $studentId, array $data): Appointment
    {
        $startsAt = Carbon::parse($data['starts_at'])->utc();
        $endsAt = Carbon::parse($data['ends_at'])->utc();

        $this->assertNoConflict($workspaceId, $trainerUserId, $studentId, $startsAt, $endsAt);

        return Appointment::query()->create([
            'workspace_id' => $workspaceId,
            'trainer_user_id' => $trainerUserId,
            'student_id' => $studentId,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => Appointment::STATUS_PLANNED,
            'location' => $data['location'] ?? null,
            'notes' => $data['notes'] ?? null,
        ])->load(['student', 'trainer']);
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
            'location' => array_key_exists('location', $data) ? $data['location'] : $appointment->location,
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $appointment->notes,
        ]);

        return $appointment->refresh()->load(['student', 'trainer']);
    }

    public function updateStatus(Appointment $appointment, string $status): Appointment
    {
        $appointment->update(['status' => $status]);

        return $appointment->refresh()->load(['student', 'trainer']);
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
