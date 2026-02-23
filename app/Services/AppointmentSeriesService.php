<?php

namespace App\Services;

use App\Exceptions\AppointmentConflictException;
use App\Models\Appointment;
use App\Models\AppointmentSeries;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AppointmentSeriesService
{
    public function __construct(
        private readonly AppointmentService $appointmentService,
        private readonly AppointmentReminderService $appointmentReminderService,
    ) {}

    public function list(int $workspaceId, ?int $trainerUserId, array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $status = (string) ($filters['status'] ?? 'all');

        return AppointmentSeries::query()
            ->where('workspace_id', $workspaceId)
            ->when($trainerUserId, fn ($query) => $query->where('trainer_user_id', $trainerUserId))
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when(isset($filters['trainer_id']), fn ($query) => $query->where('trainer_user_id', (int) $filters['trainer_id']))
            ->when(isset($filters['student_id']), fn ($query) => $query->where('student_id', (int) $filters['student_id']))
            ->when(isset($filters['from']), fn ($query) => $query->whereDate('start_date', '>=', $filters['from']))
            ->when(isset($filters['to']), fn ($query) => $query->whereDate('start_date', '<=', $filters['to']))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * @return array{series: AppointmentSeries, generated_count: int, skipped_conflicts_count: int}
     */
    public function create(int $workspaceId, int $trainerUserId, int $studentId, array $data, ?array $workspaceReminderPolicy = null): array
    {
        return DB::transaction(function () use ($workspaceId, $trainerUserId, $studentId, $data, $workspaceReminderPolicy) {
            $series = AppointmentSeries::query()->create([
                'workspace_id' => $workspaceId,
                'trainer_user_id' => $trainerUserId,
                'student_id' => $studentId,
                'title' => $data['title'] ?? null,
                'location' => $data['location'] ?? null,
                'recurrence_rule' => $data['recurrence_rule'],
                'start_date' => $data['start_date'],
                'starts_at_time' => $data['starts_at_time'],
                'ends_at_time' => $data['ends_at_time'],
                'status' => AppointmentSeries::STATUS_ACTIVE,
            ]);

            [$generated, $skipped] = $this->generateOccurrences(
                series: $series,
                fromDate: Carbon::parse($data['start_date'])->startOfDay(),
                workspaceReminderPolicy: $workspaceReminderPolicy,
            );

            return [
                'series' => $series->refresh()->load(['student', 'trainer']),
                'generated_count' => $generated,
                'skipped_conflicts_count' => $skipped,
            ];
        });
    }

    /**
     * @return array{series: AppointmentSeries, generated_count: int, skipped_conflicts_count: int}
     */
    public function updateSeries(AppointmentSeries $series, array $data, string $editScope, ?array $workspaceReminderPolicy = null): array
    {
        return DB::transaction(function () use ($series, $data, $editScope, $workspaceReminderPolicy) {
            $series->update([
                'title' => array_key_exists('title', $data) ? $data['title'] : $series->title,
                'location' => array_key_exists('location', $data) ? $data['location'] : $series->location,
                'recurrence_rule' => $data['recurrence_rule'] ?? $series->recurrence_rule,
                'start_date' => $data['start_date'] ?? $series->start_date?->toDateString(),
                'starts_at_time' => $data['starts_at_time'] ?? $series->starts_at_time,
                'ends_at_time' => $data['ends_at_time'] ?? $series->ends_at_time,
                'status' => $data['status'] ?? $series->status,
            ]);

            $fromDate = $editScope === 'future'
                ? now()->utc()->startOfDay()
                : Carbon::parse($series->start_date)->startOfDay();

            Appointment::query()
                ->where('series_id', $series->id)
                ->where('starts_at', '>=', $fromDate)
                ->where('status', Appointment::STATUS_PLANNED)
                ->delete();

            [$generated, $skipped] = $this->generateOccurrences(
                series: $series->refresh(),
                fromDate: $fromDate,
                workspaceReminderPolicy: $workspaceReminderPolicy,
            );

            return [
                'series' => $series->refresh()->load(['student', 'trainer']),
                'generated_count' => $generated,
                'skipped_conflicts_count' => $skipped,
            ];
        });
    }

    /**
     * @return array{0:int,1:int}
     */
    public function generateOccurrences(AppointmentSeries $series, Carbon $fromDate, ?array $workspaceReminderPolicy = null): array
    {
        $rule = $series->recurrence_rule ?? [];
        $freq = (string) ($rule['freq'] ?? 'weekly');
        $interval = max(1, (int) ($rule['interval'] ?? 1));
        $maxCount = max(1, min(365, (int) ($rule['count'] ?? 365)));
        $until = isset($rule['until']) ? Carbon::parse($rule['until'])->endOfDay() : null;
        $horizon = now()->utc()->addDays(180)->endOfDay();
        $endBoundary = $until ? $until->min($horizon) : $horizon;

        $dates = $freq === 'monthly'
            ? $this->buildMonthlyDates($series, $interval, $maxCount, $fromDate, $endBoundary)
            : $this->buildWeeklyDates($series, $interval, $maxCount, $fromDate, $endBoundary);

        $generated = 0;
        $skipped = 0;

        foreach ($dates as $date) {
            $startsAt = Carbon::parse($date->toDateString().' '.$series->starts_at_time, 'UTC');
            $endsAt = Carbon::parse($date->toDateString().' '.$series->ends_at_time, 'UTC');

            try {
                $appointment = $this->appointmentService->create(
                    workspaceId: $series->workspace_id,
                    trainerUserId: $series->trainer_user_id,
                    studentId: $series->student_id,
                    data: [
                        'starts_at' => $startsAt->toDateTimeString(),
                        'ends_at' => $endsAt->toDateTimeString(),
                        'location' => $series->location,
                        'notes' => $series->title,
                        'series_id' => $series->id,
                        'series_occurrence_date' => $date->toDateString(),
                    ],
                );

                $this->appointmentReminderService->syncForAppointment($appointment, $workspaceReminderPolicy);
                $generated++;
            } catch (AppointmentConflictException) {
                $skipped++;
            }
        }

        return [$generated, $skipped];
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function buildWeeklyDates(
        AppointmentSeries $series,
        int $interval,
        int $maxCount,
        Carbon $fromDate,
        Carbon $endBoundary
    ): Collection {
        $rule = $series->recurrence_rule ?? [];
        $weekdays = collect($rule['byweekday'] ?? [Carbon::parse($series->start_date)->dayOfWeekIso])
            ->map(fn ($day) => max(1, min(7, (int) $day)))
            ->unique()
            ->sort()
            ->values();

        $cursor = Carbon::parse($series->start_date)->startOfDay();
        $dates = collect();

        while ($cursor->lte($endBoundary) && $dates->count() < $maxCount) {
            foreach ($weekdays as $weekday) {
                $candidate = $cursor->copy()->startOfWeek(Carbon::MONDAY)->addDays($weekday - 1);
                if ($candidate->lt(Carbon::parse($series->start_date)->startOfDay())) {
                    continue;
                }
                if ($candidate->lt($fromDate) || $candidate->gt($endBoundary)) {
                    continue;
                }
                if ($dates->count() >= $maxCount) {
                    break;
                }
                $dates->push($candidate);
            }
            $cursor->addWeeks($interval);
        }

        return $dates->sort()->values();
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function buildMonthlyDates(
        AppointmentSeries $series,
        int $interval,
        int $maxCount,
        Carbon $fromDate,
        Carbon $endBoundary
    ): Collection {
        $anchor = Carbon::parse($series->start_date)->startOfDay();
        $cursor = $anchor->copy();
        $dates = collect();

        while ($cursor->lte($endBoundary) && $dates->count() < $maxCount) {
            if ($cursor->gte($fromDate)) {
                $dates->push($cursor->copy());
            }
            $cursor->addMonthsNoOverflow($interval);
        }

        return $dates;
    }
}
