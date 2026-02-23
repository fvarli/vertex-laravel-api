<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ReportService
{
    private const CACHE_TTL = 300; // 5 minutes

    public function appointments(int $workspaceId, ?int $trainerId, CarbonImmutable $from, CarbonImmutable $to, string $groupBy): array
    {
        $cacheKey = "report:appointments:{$workspaceId}:{$trainerId}:{$from->toDateString()}:{$to->toDateString()}:{$groupBy}";

        return Cache::remember($cacheKey, self::CACHE_TTL, fn () => $this->buildAppointmentsReport($workspaceId, $trainerId, $from, $to, $groupBy));
    }

    private function buildAppointmentsReport(int $workspaceId, ?int $trainerId, CarbonImmutable $from, CarbonImmutable $to, string $groupBy): array
    {
        $base = Appointment::query()
            ->where('workspace_id', $workspaceId)
            ->when($trainerId !== null, fn (Builder $q) => $q->where('trainer_user_id', $trainerId))
            ->whereBetween('starts_at', [$from, $to]);

        return [
            'filters' => $this->filterPayload($from, $to, $groupBy),
            'totals' => [
                'total' => (clone $base)->count(),
                'planned' => (clone $base)->where('status', Appointment::STATUS_PLANNED)->count(),
                'done' => (clone $base)->where('status', Appointment::STATUS_DONE)->count(),
                'cancelled' => (clone $base)->where('status', Appointment::STATUS_CANCELLED)->count(),
                'no_show' => (clone $base)->where('status', Appointment::STATUS_NO_SHOW)->count(),
            ],
            'buckets' => $this->groupAppointments($base, $groupBy),
        ];
    }

    public function students(int $workspaceId, ?int $trainerId, CarbonImmutable $from, CarbonImmutable $to, string $groupBy): array
    {
        $base = Student::query()
            ->where('workspace_id', $workspaceId)
            ->when($trainerId !== null, fn (Builder $q) => $q->where('trainer_user_id', $trainerId));

        $inRange = (clone $base)->whereBetween('created_at', [$from, $to]);

        return [
            'filters' => $this->filterPayload($from, $to, $groupBy),
            'totals' => [
                'total' => (clone $base)->count(),
                'active' => (clone $base)->where('status', Student::STATUS_ACTIVE)->count(),
                'passive' => (clone $base)->where('status', Student::STATUS_PASSIVE)->count(),
                'new_in_range' => (clone $inRange)->count(),
            ],
            'buckets' => $this->groupStudents($inRange, $groupBy),
        ];
    }

    public function programs(int $workspaceId, ?int $trainerId, CarbonImmutable $from, CarbonImmutable $to, string $groupBy): array
    {
        $base = Program::query()
            ->where('workspace_id', $workspaceId)
            ->when($trainerId !== null, fn (Builder $q) => $q->where('trainer_user_id', $trainerId))
            ->whereBetween('week_start_date', [$from->toDateString(), $to->toDateString()]);

        return [
            'filters' => $this->filterPayload($from, $to, $groupBy),
            'totals' => [
                'total' => (clone $base)->count(),
                'active' => (clone $base)->where('status', Program::STATUS_ACTIVE)->count(),
                'draft' => (clone $base)->where('status', Program::STATUS_DRAFT)->count(),
                'archived' => (clone $base)->where('status', Program::STATUS_ARCHIVED)->count(),
            ],
            'buckets' => $this->groupPrograms($base, $groupBy),
        ];
    }

    public function reminders(int $workspaceId, ?int $trainerId, CarbonImmutable $from, CarbonImmutable $to, string $groupBy): array
    {
        $base = AppointmentReminder::query()
            ->where('workspace_id', $workspaceId)
            ->whereBetween('scheduled_for', [$from, $to])
            ->when($trainerId !== null, fn (Builder $q) => $q->whereHas('appointment', fn (Builder $a) => $a->where('trainer_user_id', $trainerId)));

        $total = (clone $base)->count();
        $sent = (clone $base)->where('status', AppointmentReminder::STATUS_SENT)->count();
        $missed = (clone $base)->where('status', AppointmentReminder::STATUS_MISSED)->count();
        $escalated = (clone $base)->where('status', AppointmentReminder::STATUS_ESCALATED)->count();
        $onTimeSent = (clone $base)
            ->where('status', AppointmentReminder::STATUS_SENT)
            ->whereNotNull('marked_sent_at')
            ->whereColumn('marked_sent_at', '<=', 'scheduled_for')
            ->count();

        return [
            'filters' => $this->filterPayload($from, $to, $groupBy),
            'totals' => [
                'total' => $total,
                'sent' => $sent,
                'pending' => (clone $base)->where('status', AppointmentReminder::STATUS_PENDING)->count(),
                'ready' => (clone $base)->where('status', AppointmentReminder::STATUS_READY)->count(),
                'failed' => (clone $base)->where('status', AppointmentReminder::STATUS_FAILED)->count(),
                'missed' => $missed,
                'escalated' => $escalated,
                'send_rate' => $total > 0 ? round(($sent / $total) * 100, 1) : 0.0,
                'on_time_send_rate' => $sent > 0 ? round(($onTimeSent / $sent) * 100, 1) : 0.0,
                'missed_rate' => $total > 0 ? round(($missed / $total) * 100, 1) : 0.0,
                'escalated_count' => $escalated,
                'avg_attempt_count' => round((float) ((clone $base)->avg('attempt_count') ?? 0), 2),
            ],
            'buckets' => $this->groupReminders($base, $groupBy),
        ];
    }

    public function trainerPerformance(int $workspaceId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $workspace = Workspace::query()->find($workspaceId);
        $trainerIds = $workspace->users()
            ->wherePivot('is_active', true)
            ->wherePivot('role', 'trainer')
            ->get()
            ->pluck('id');

        $trainers = [];

        foreach ($trainerIds as $trainerId) {
            $trainer = User::query()->find($trainerId);

            $totalStudents = Student::query()
                ->where('workspace_id', $workspaceId)
                ->where('trainer_user_id', $trainerId)
                ->count();

            $activeStudents = Student::query()
                ->where('workspace_id', $workspaceId)
                ->where('trainer_user_id', $trainerId)
                ->where('status', Student::STATUS_ACTIVE)
                ->count();

            $appointmentBase = Appointment::query()
                ->where('workspace_id', $workspaceId)
                ->where('trainer_user_id', $trainerId)
                ->whereBetween('starts_at', [$from, $to]);

            $totalAppointments = (clone $appointmentBase)->count();
            $completedAppointments = (clone $appointmentBase)->where('status', Appointment::STATUS_DONE)->count();
            $noShowCount = (clone $appointmentBase)->where('status', Appointment::STATUS_NO_SHOW)->count();
            $cancellationCount = (clone $appointmentBase)->where('status', Appointment::STATUS_CANCELLED)->count();

            $completionRate = $totalAppointments > 0
                ? round(($completedAppointments / $totalAppointments) * 100, 1)
                : 0.0;

            $activePrograms = Program::query()
                ->where('workspace_id', $workspaceId)
                ->where('trainer_user_id', $trainerId)
                ->where('status', Program::STATUS_ACTIVE)
                ->count();

            $reminderBase = AppointmentReminder::query()
                ->where('workspace_id', $workspaceId)
                ->whereBetween('scheduled_for', [$from, $to])
                ->whereHas('appointment', fn (Builder $q) => $q->where('trainer_user_id', $trainerId));

            $remindersSent = (clone $reminderBase)->where('status', AppointmentReminder::STATUS_SENT)->count();
            $remindersTotal = (clone $reminderBase)->count();

            $reminderSuccessRate = $remindersTotal > 0
                ? round(($remindersSent / $remindersTotal) * 100, 1)
                : 0.0;

            $trainers[] = [
                'trainer_id' => $trainerId,
                'trainer_name' => trim($trainer->name.' '.$trainer->surname),
                'total_students' => $totalStudents,
                'active_students' => $activeStudents,
                'total_appointments' => $totalAppointments,
                'completed_appointments' => $completedAppointments,
                'no_show_count' => $noShowCount,
                'cancellation_count' => $cancellationCount,
                'completion_rate' => $completionRate,
                'active_programs' => $activePrograms,
                'reminders_sent' => $remindersSent,
                'reminder_success_rate' => $reminderSuccessRate,
            ];
        }

        return [
            'period' => ['from' => $from->toDateTimeString(), 'to' => $to->toDateTimeString()],
            'trainers' => $trainers,
        ];
    }

    public function studentRetention(int $workspaceId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $base = Student::query()->where('workspace_id', $workspaceId);

        $totalStudents = (clone $base)->count();
        $newStudents = (clone $base)->whereBetween('created_at', [$from, $to])->count();

        $churnedStudents = (clone $base)
            ->where('status', Student::STATUS_PASSIVE)
            ->whereBetween('updated_at', [$from, $to])
            ->count();

        $retentionRate = $totalStudents > 0
            ? round((($totalStudents - $churnedStudents) / $totalStudents) * 100, 1)
            : 0.0;

        $churnRate = $totalStudents > 0
            ? round(($churnedStudents / $totalStudents) * 100, 1)
            : 0.0;

        $activeStudentsWithAppointments = Student::query()
            ->where('workspace_id', $workspaceId)
            ->where('status', Student::STATUS_ACTIVE)
            ->get();

        $thirtyDaysAgo = CarbonImmutable::now()->subDays(30);
        $studentsWithoutAppointment30d = 0;
        $lifetimeDaysTotal = 0;

        foreach ($activeStudentsWithAppointments as $student) {
            $lastAppointment = Appointment::query()
                ->where('student_id', $student->id)
                ->where('workspace_id', $workspaceId)
                ->whereIn('status', [Appointment::STATUS_DONE, Appointment::STATUS_PLANNED])
                ->max('starts_at');

            if (! $lastAppointment || CarbonImmutable::parse($lastAppointment)->lt($thirtyDaysAgo)) {
                $studentsWithoutAppointment30d++;
            }

            $lifetimeDaysTotal += (int) CarbonImmutable::parse($student->created_at)->diffInDays(CarbonImmutable::now());
        }

        $avgLifetimeDays = $activeStudentsWithAppointments->count() > 0
            ? (int) round($lifetimeDaysTotal / $activeStudentsWithAppointments->count())
            : 0;

        return [
            'period' => ['from' => $from->toDateTimeString(), 'to' => $to->toDateTimeString()],
            'total_students' => $totalStudents,
            'new_students' => $newStudents,
            'churned_students' => $churnedStudents,
            'retention_rate' => $retentionRate,
            'churn_rate' => $churnRate,
            'avg_student_lifetime_days' => $avgLifetimeDays,
            'students_without_appointment_30d' => $studentsWithoutAppointment30d,
        ];
    }

    private function filterPayload(CarbonImmutable $from, CarbonImmutable $to, string $groupBy): array
    {
        return [
            'date_from' => $from->toDateTimeString(),
            'date_to' => $to->toDateTimeString(),
            'group_by' => $groupBy,
        ];
    }

    private function groupAppointments(Builder $query, string $groupBy): array
    {
        return $query->get(['starts_at', 'status'])
            ->groupBy(fn (Appointment $appointment) => $this->bucketKey($appointment->starts_at, $groupBy))
            ->map(function (Collection $items, string $bucket) {
                return [
                    'bucket' => $bucket,
                    'total' => $items->count(),
                    'planned' => $items->where('status', Appointment::STATUS_PLANNED)->count(),
                    'done' => $items->where('status', Appointment::STATUS_DONE)->count(),
                    'cancelled' => $items->where('status', Appointment::STATUS_CANCELLED)->count(),
                    'no_show' => $items->where('status', Appointment::STATUS_NO_SHOW)->count(),
                ];
            })
            ->values()
            ->all();
    }

    private function groupStudents(Builder $query, string $groupBy): array
    {
        return $query->get(['created_at', 'status'])
            ->groupBy(fn (Student $student) => $this->bucketKey($student->created_at, $groupBy))
            ->map(function (Collection $items, string $bucket) {
                return [
                    'bucket' => $bucket,
                    'total' => $items->count(),
                    'active' => $items->where('status', Student::STATUS_ACTIVE)->count(),
                    'passive' => $items->where('status', Student::STATUS_PASSIVE)->count(),
                ];
            })
            ->values()
            ->all();
    }

    private function groupPrograms(Builder $query, string $groupBy): array
    {
        return $query->get(['week_start_date', 'status'])
            ->groupBy(fn (Program $program) => $this->bucketKey($program->week_start_date, $groupBy))
            ->map(function (Collection $items, string $bucket) {
                return [
                    'bucket' => $bucket,
                    'total' => $items->count(),
                    'active' => $items->where('status', Program::STATUS_ACTIVE)->count(),
                    'draft' => $items->where('status', Program::STATUS_DRAFT)->count(),
                    'archived' => $items->where('status', Program::STATUS_ARCHIVED)->count(),
                ];
            })
            ->values()
            ->all();
    }

    private function groupReminders(Builder $query, string $groupBy): array
    {
        return $query->get(['scheduled_for', 'status'])
            ->groupBy(fn (AppointmentReminder $reminder) => $this->bucketKey($reminder->scheduled_for, $groupBy))
            ->map(function (Collection $items, string $bucket) {
                return [
                    'bucket' => $bucket,
                    'total' => $items->count(),
                    'sent' => $items->where('status', AppointmentReminder::STATUS_SENT)->count(),
                    'pending' => $items->where('status', AppointmentReminder::STATUS_PENDING)->count(),
                    'ready' => $items->where('status', AppointmentReminder::STATUS_READY)->count(),
                    'failed' => $items->where('status', AppointmentReminder::STATUS_FAILED)->count(),
                    'missed' => $items->where('status', AppointmentReminder::STATUS_MISSED)->count(),
                    'escalated' => $items->where('status', AppointmentReminder::STATUS_ESCALATED)->count(),
                ];
            })
            ->values()
            ->all();
    }

    private function bucketKey($value, string $groupBy): string
    {
        $date = CarbonImmutable::parse($value);

        return match ($groupBy) {
            'month' => $date->format('Y-m'),
            'week' => $date->format('o-\WW'),
            default => $date->format('Y-m-d'),
        };
    }
}
