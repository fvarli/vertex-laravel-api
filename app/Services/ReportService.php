<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Program;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportService
{
    public function appointments(int $workspaceId, ?int $trainerId, CarbonImmutable $from, CarbonImmutable $to, string $groupBy): array
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
