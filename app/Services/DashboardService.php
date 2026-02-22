<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
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
        $reminderBase = AppointmentReminder::query()
            ->where('workspace_id', $workspaceId)
            ->whereBetween('scheduled_for', [$todayStart, $todayEnd])
            ->when($trainerUserId !== null, fn ($q) => $q->whereHas('appointment', fn ($a) => $a->where('trainer_user_id', $trainerUserId)));

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
            'reminders' => [
                'today_total' => (clone $reminderBase)->count(),
                'today_sent' => (clone $reminderBase)->where('status', AppointmentReminder::STATUS_SENT)->count(),
                'today_missed' => (clone $reminderBase)->where('status', AppointmentReminder::STATUS_MISSED)->count(),
                'today_escalated' => (clone $reminderBase)->where('status', AppointmentReminder::STATUS_ESCALATED)->count(),
            ],
            'whatsapp' => $this->buildWhatsappStats($workspaceId, $trainerUserId, $todayStart, $todayEnd),
            'trends' => $this->buildTrends($workspaceId, $trainerUserId),
            'top_trainers' => $trainerUserId === null
                ? $this->buildTopTrainers($workspaceId)
                : [],
        ];
    }

    private function buildWhatsappStats(int $workspaceId, ?int $trainerUserId, CarbonImmutable $todayStart, CarbonImmutable $todayEnd): array
    {
        $base = Appointment::query()
            ->where('workspace_id', $workspaceId)
            ->whereBetween('starts_at', [$todayStart, $todayEnd])
            ->whereIn('status', [Appointment::STATUS_PLANNED, Appointment::STATUS_DONE])
            ->when($trainerUserId !== null, fn ($q) => $q->where('trainer_user_id', $trainerUserId));

        $total = (clone $base)->count();
        $sent = (clone $base)->where('whatsapp_status', Appointment::WHATSAPP_STATUS_SENT)->count();
        $notSent = $total - $sent;
        $sendRate = $total > 0 ? round(($sent / $total) * 100, 1) : 0;

        return [
            'today_total' => $total,
            'today_sent' => $sent,
            'today_not_sent' => $notSent,
            'send_rate' => $sendRate,
        ];
    }

    private function buildTrends(int $workspaceId, ?int $trainerUserId): array
    {
        $now = CarbonImmutable::now();
        $thisWeekStart = $now->startOfWeek()->startOfDay();
        $thisWeekEnd = $now->endOfWeek()->endOfDay();
        $lastWeekStart = $thisWeekStart->subWeek();
        $lastWeekEnd = $thisWeekEnd->subWeek();
        $monthStart = $now->startOfMonth()->startOfDay();
        $monthEnd = $now->endOfMonth()->endOfDay();

        $baseQuery = fn () => Appointment::query()
            ->where('workspace_id', $workspaceId)
            ->when($trainerUserId !== null, fn ($q) => $q->where('trainer_user_id', $trainerUserId));

        $thisWeekCount = (clone $baseQuery())->whereBetween('starts_at', [$thisWeekStart, $thisWeekEnd])->count();
        $lastWeekCount = (clone $baseQuery())->whereBetween('starts_at', [$lastWeekStart, $lastWeekEnd])->count();

        if ($lastWeekCount > 0) {
            $pctChange = round((($thisWeekCount - $lastWeekCount) / $lastWeekCount) * 100);
            $appointmentsVsLastWeek = ($pctChange >= 0 ? '+' : '').$pctChange.'%';
        } else {
            $appointmentsVsLastWeek = $thisWeekCount > 0 ? '+100%' : '0%';
        }

        $newStudentsThisMonth = Student::query()
            ->where('workspace_id', $workspaceId)
            ->when($trainerUserId !== null, fn ($q) => $q->where('trainer_user_id', $trainerUserId))
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->count();

        $thisWeekDone = (clone $baseQuery())
            ->whereBetween('starts_at', [$thisWeekStart, $thisWeekEnd])
            ->where('status', Appointment::STATUS_DONE)
            ->count();
        $lastWeekDone = (clone $baseQuery())
            ->whereBetween('starts_at', [$lastWeekStart, $lastWeekEnd])
            ->where('status', Appointment::STATUS_DONE)
            ->count();

        $thisWeekTotal = (clone $baseQuery())
            ->whereBetween('starts_at', [$thisWeekStart, $thisWeekEnd])
            ->whereIn('status', [Appointment::STATUS_DONE, Appointment::STATUS_NO_SHOW])
            ->count();
        $lastWeekTotal = (clone $baseQuery())
            ->whereBetween('starts_at', [$lastWeekStart, $lastWeekEnd])
            ->whereIn('status', [Appointment::STATUS_DONE, Appointment::STATUS_NO_SHOW])
            ->count();

        $thisRate = $thisWeekTotal > 0 ? $thisWeekDone / $thisWeekTotal : 0;
        $lastRate = $lastWeekTotal > 0 ? $lastWeekDone / $lastWeekTotal : 0;

        if ($thisRate > $lastRate) {
            $completionRateTrend = 'up';
        } elseif ($thisRate < $lastRate) {
            $completionRateTrend = 'down';
        } else {
            $completionRateTrend = 'stable';
        }

        return [
            'appointments_vs_last_week' => $appointmentsVsLastWeek,
            'new_students_this_month' => $newStudentsThisMonth,
            'completion_rate_trend' => $completionRateTrend,
        ];
    }

    private function buildTopTrainers(int $workspaceId, int $limit = 5): array
    {
        $thisWeekStart = CarbonImmutable::now()->startOfWeek()->startOfDay();
        $thisWeekEnd = CarbonImmutable::now()->endOfWeek()->endOfDay();

        $workspace = Workspace::query()->find($workspaceId);
        if (! $workspace) {
            return [];
        }

        $trainers = $workspace->users()
            ->wherePivot('is_active', true)
            ->wherePivot('role', 'trainer')
            ->get();

        $results = [];
        foreach ($trainers as $trainer) {
            $completedSessions = Appointment::query()
                ->where('workspace_id', $workspaceId)
                ->where('trainer_user_id', $trainer->id)
                ->whereBetween('starts_at', [$thisWeekStart, $thisWeekEnd])
                ->where('status', Appointment::STATUS_DONE)
                ->count();

            $results[] = [
                'name' => trim($trainer->name.' '.$trainer->surname),
                'completed_sessions' => $completedSessions,
            ];
        }

        usort($results, fn ($a, $b) => $b['completed_sessions'] <=> $a['completed_sessions']);

        return array_slice($results, 0, $limit);
    }
}
