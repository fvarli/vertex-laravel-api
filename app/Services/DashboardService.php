<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Program;
use App\Models\Student;
use Carbon\CarbonImmutable;

class DashboardService
{
    public function summary(int $workspaceId, ?int $trainerUserId = null): array
    {
        $todayStart = CarbonImmutable::now()->startOfDay();
        $todayEnd = CarbonImmutable::now()->endOfDay();
        $nextWeekEnd = CarbonImmutable::now()->addDays(7)->endOfDay();
        $weekStart = CarbonImmutable::now()->startOfWeek()->toDateString();
        $weekEnd = CarbonImmutable::now()->endOfWeek()->toDateString();

        $studentQuery = Student::query()
            ->where('workspace_id', $workspaceId)
            ->when($trainerUserId !== null, fn ($q) => $q->where('trainer_user_id', $trainerUserId));

        $appointmentBase = Appointment::query()
            ->where('workspace_id', $workspaceId)
            ->when($trainerUserId !== null, fn ($q) => $q->where('trainer_user_id', $trainerUserId));

        $programBase = Program::query()
            ->where('workspace_id', $workspaceId)
            ->whereBetween('week_start_date', [$weekStart, $weekEnd])
            ->when($trainerUserId !== null, fn ($q) => $q->where('trainer_user_id', $trainerUserId));

        $todayDoneCount = (clone $appointmentBase)
            ->whereBetween('starts_at', [$todayStart, $todayEnd])
            ->where('status', Appointment::STATUS_DONE)
            ->count();
        $todayNoShowCount = (clone $appointmentBase)
            ->whereBetween('starts_at', [$todayStart, $todayEnd])
            ->where('status', Appointment::STATUS_NO_SHOW)
            ->count();
        $attendanceDenominator = $todayDoneCount + $todayNoShowCount;

        return [
            'date' => $todayStart->toDateString(),
            'students' => [
                'active' => (clone $studentQuery)->where('status', Student::STATUS_ACTIVE)->count(),
                'passive' => (clone $studentQuery)->where('status', Student::STATUS_PASSIVE)->count(),
                'total' => (clone $studentQuery)->count(),
            ],
            'appointments' => [
                'today_total' => (clone $appointmentBase)->whereBetween('starts_at', [$todayStart, $todayEnd])->count(),
                'today_done' => $todayDoneCount,
                'today_no_show' => $todayNoShowCount,
                'today_planned' => (clone $appointmentBase)
                    ->whereBetween('starts_at', [$todayStart, $todayEnd])
                    ->where('status', Appointment::STATUS_PLANNED)
                    ->count(),
                'today_cancelled' => (clone $appointmentBase)
                    ->whereBetween('starts_at', [$todayStart, $todayEnd])
                    ->where('status', Appointment::STATUS_CANCELLED)
                    ->count(),
                'upcoming_7d' => (clone $appointmentBase)
                    ->whereBetween('starts_at', [$todayStart, $nextWeekEnd])
                    ->whereIn('status', [Appointment::STATUS_PLANNED, Appointment::STATUS_DONE])
                    ->count(),
                'today_attendance_rate' => $attendanceDenominator > 0
                    ? round(($todayDoneCount / $attendanceDenominator) * 100, 1)
                    : null,
            ],
            'programs' => [
                'active_this_week' => (clone $programBase)->where('status', Program::STATUS_ACTIVE)->count(),
                'draft_this_week' => (clone $programBase)->where('status', Program::STATUS_DRAFT)->count(),
            ],
        ];
    }
}
