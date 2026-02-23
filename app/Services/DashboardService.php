<?php

namespace App\Services;

use App\Enums\WorkspaceRole;
use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function summary(int $workspaceId, ?int $trainerUserId = null): array
    {
        $cacheKey = "dashboard:summary:{$workspaceId}:{$trainerUserId}";

        return Cache::remember($cacheKey, 120, function () use ($workspaceId, $trainerUserId) {
            return $this->buildSummary($workspaceId, $trainerUserId);
        });
    }

    public static function clearCache(int $workspaceId): void
    {
        Cache::forget("dashboard:summary:{$workspaceId}:");

        $cachePrefix = "dashboard:summary:{$workspaceId}:";
        // Individual trainer caches will expire via TTL
        // For immediate invalidation on data changes, clear known keys
        Cache::forget($cachePrefix);
    }

    private function buildSummary(int $workspaceId, ?int $trainerUserId = null): array
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

        return User::query()
            ->select([
                'users.id',
                'users.name',
                'users.surname',
                DB::raw('COUNT(appointments.id) as completed_sessions'),
            ])
            ->join('workspace_user', function ($join) use ($workspaceId) {
                $join->on('workspace_user.user_id', '=', 'users.id')
                    ->where('workspace_user.workspace_id', $workspaceId)
                    ->where('workspace_user.is_active', true)
                    ->where('workspace_user.role', WorkspaceRole::Trainer->value);
            })
            ->leftJoin('appointments', function ($join) use ($workspaceId, $thisWeekStart, $thisWeekEnd) {
                $join->on('appointments.trainer_user_id', '=', 'users.id')
                    ->where('appointments.workspace_id', $workspaceId)
                    ->where('appointments.status', Appointment::STATUS_DONE)
                    ->whereBetween('appointments.starts_at', [$thisWeekStart, $thisWeekEnd]);
            })
            ->groupBy('users.id', 'users.name', 'users.surname')
            ->orderByDesc('completed_sessions')
            ->limit($limit)
            ->get()
            ->map(fn ($trainer) => [
                'name' => trim($trainer->name.' '.$trainer->surname),
                'completed_sessions' => (int) $trainer->completed_sessions,
            ])
            ->all();
    }
}
