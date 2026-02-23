<?php

namespace App\Services;

use App\Models\Appointment;

class CalendarService
{
    public function availability(int $workspaceId, ?int $trainerUserId, array $filters): array
    {
        $from = $filters['from'] ?? now()->startOfDay()->toDateTimeString();
        $to = $filters['to'] ?? now()->addWeek()->endOfDay()->toDateTimeString();

        $appointments = Appointment::query()
            ->where('workspace_id', $workspaceId)
            ->whereBetween('starts_at', [$from, $to])
            ->when($trainerUserId, fn ($q) => $q->where('trainer_user_id', $trainerUserId))
            ->whereNotIn('status', [Appointment::STATUS_CANCELLED])
            ->orderBy('starts_at')
            ->get(['id', 'trainer_user_id', 'student_id', 'starts_at', 'ends_at', 'status']);

        $days = $appointments
            ->groupBy(fn ($appointment) => $appointment->starts_at->toDateString())
            ->map(fn ($items, $date) => [
                'date' => $date,
                'items' => $items->values(),
            ])
            ->values();

        return [
            'from' => $from,
            'to' => $to,
            'appointments' => $appointments,
            'days' => $days,
        ];
    }
}
