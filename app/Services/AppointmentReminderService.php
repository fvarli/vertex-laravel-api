<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentReminder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class AppointmentReminderService
{
    private const DEFAULT_OFFSETS = [1440, 120];

    private const DEFAULT_RETRY = [
        'max_attempts' => 2,
        'backoff_minutes' => [15, 30],
        'escalate_on_exhausted' => true,
    ];

    /**
     * @return list<int>
     */
    public function resolveOffsetsMinutes(?array $workspaceReminderPolicy = null): array
    {
        $offsets = $workspaceReminderPolicy['whatsapp_offsets_minutes'] ?? self::DEFAULT_OFFSETS;
        $normalized = collect($offsets)
            ->map(fn ($value) => (int) $value)
            ->filter(fn (int $value) => $value > 0)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        return $normalized === [] ? self::DEFAULT_OFFSETS : $normalized;
    }

    public function syncForAppointment(Appointment $appointment, ?array $workspaceReminderPolicy = null): void
    {
        $offsets = $this->resolveOffsetsMinutes($workspaceReminderPolicy);
        $retryPolicy = $this->resolveRetryPolicy($workspaceReminderPolicy);

        $now = now()->utc();
        $targetSlots = collect($offsets)->map(function (int $offsetMinutes) use ($appointment, $now, $retryPolicy) {
            $scheduledFor = $appointment->starts_at->copy()->subMinutes($offsetMinutes);
            $isPast = $scheduledFor->lte($now);

            return [
                'scheduled_for' => $scheduledFor->toDateTimeString(),
                'status' => $isPast
                    ? AppointmentReminder::STATUS_MISSED
                    : AppointmentReminder::STATUS_PENDING,
                'offset_minutes' => $offsetMinutes,
                'next_retry_at' => $isPast
                    ? $this->retryAt($now, 0, $retryPolicy)
                    : null,
            ];
        });

        if ($appointment->status === Appointment::STATUS_CANCELLED) {
            $this->cancelPending($appointment);

            return;
        }

        foreach ($targetSlots as $slot) {
            AppointmentReminder::query()->updateOrCreate(
                [
                    'appointment_id' => $appointment->id,
                    'channel' => AppointmentReminder::CHANNEL_WHATSAPP,
                    'scheduled_for' => $slot['scheduled_for'],
                ],
                [
                    'workspace_id' => $appointment->workspace_id,
                    'status' => $slot['status'],
                    'next_retry_at' => $slot['next_retry_at'],
                    'payload' => ['offset_minutes' => $slot['offset_minutes']],
                ]
            );
        }

        AppointmentReminder::query()
            ->where('appointment_id', $appointment->id)
            ->where('channel', AppointmentReminder::CHANNEL_WHATSAPP)
            ->whereIn('status', [
                AppointmentReminder::STATUS_PENDING,
                AppointmentReminder::STATUS_READY,
                AppointmentReminder::STATUS_MISSED,
                AppointmentReminder::STATUS_FAILED,
                AppointmentReminder::STATUS_ESCALATED,
            ])
            ->whereNotIn('scheduled_for', $targetSlots->pluck('scheduled_for')->all())
            ->delete();
    }

    public function cancelPending(Appointment $appointment): void
    {
        AppointmentReminder::query()
            ->where('appointment_id', $appointment->id)
            ->whereIn('status', [
                AppointmentReminder::STATUS_PENDING,
                AppointmentReminder::STATUS_READY,
                AppointmentReminder::STATUS_FAILED,
                AppointmentReminder::STATUS_MISSED,
            ])
            ->update(['status' => AppointmentReminder::STATUS_CANCELLED]);
    }

    public function markMissed(): int
    {
        $now = Carbon::now()->utc();

        $reminders = AppointmentReminder::query()
            ->whereIn('status', [AppointmentReminder::STATUS_PENDING, AppointmentReminder::STATUS_READY])
            ->whereHas('appointment', fn (Builder $q) => $q->where('starts_at', '<=', $now))
            ->get();

        $affected = 0;

        foreach ($reminders as $reminder) {
            if (! $this->canTransition($reminder->status, AppointmentReminder::STATUS_MISSED)) {
                continue;
            }

            $policy = $this->resolveRetryPolicy($reminder->workspace?->reminder_policy);
            $reminder->update([
                'status' => AppointmentReminder::STATUS_MISSED,
                'failure_reason' => 'not_marked_sent_before_session_start',
                'next_retry_at' => $this->retryAt($now, (int) $reminder->attempt_count, $policy),
            ]);
            $affected++;
        }

        return $affected;
    }

    public function prepareReady(): int
    {
        $now = Carbon::now()->utc();
        $affected = 0;

        $reminders = AppointmentReminder::query()
            ->with('workspace')
            ->where('status', AppointmentReminder::STATUS_PENDING)
            ->where('scheduled_for', '<=', $now)
            ->get();

        foreach ($reminders as $reminder) {
            if (! $this->canTransition($reminder->status, AppointmentReminder::STATUS_READY)) {
                continue;
            }

            $policy = is_array($reminder->workspace?->reminder_policy)
                ? $reminder->workspace->reminder_policy
                : [];

            if ($this->isInQuietPeriod($now, $policy)) {
                continue;
            }

            $reminder->update(['status' => AppointmentReminder::STATUS_READY]);
            $affected++;
        }

        return $affected;
    }

    public function retryFailed(): int
    {
        $now = Carbon::now()->utc();
        $affected = 0;

        $reminders = AppointmentReminder::query()
            ->with('workspace')
            ->whereIn('status', [AppointmentReminder::STATUS_FAILED, AppointmentReminder::STATUS_MISSED])
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', $now);
            })
            ->get();

        foreach ($reminders as $reminder) {
            $policy = $this->resolveRetryPolicy($reminder->workspace?->reminder_policy);
            $currentAttempts = (int) $reminder->attempt_count;

            if ($currentAttempts >= (int) $policy['max_attempts']) {
                continue;
            }

            $nextAttempts = $currentAttempts + 1;
            $retryAt = $this->retryAt($now, $currentAttempts, $policy);

            $reminder->update([
                'status' => AppointmentReminder::STATUS_PENDING,
                'attempt_count' => $nextAttempts,
                'last_attempted_at' => $now,
                'next_retry_at' => $retryAt,
            ]);

            $affected++;
        }

        return $affected;
    }

    public function escalateStale(): int
    {
        $affected = 0;
        $now = Carbon::now()->utc();

        $reminders = AppointmentReminder::query()
            ->with('workspace')
            ->whereIn('status', [AppointmentReminder::STATUS_FAILED, AppointmentReminder::STATUS_MISSED])
            ->get();

        foreach ($reminders as $reminder) {
            $policy = $this->resolveRetryPolicy($reminder->workspace?->reminder_policy);
            if (! ((bool) ($policy['escalate_on_exhausted'] ?? true))) {
                continue;
            }

            if ((int) $reminder->attempt_count < (int) $policy['max_attempts']) {
                continue;
            }

            if (! $this->canTransition($reminder->status, AppointmentReminder::STATUS_ESCALATED)) {
                continue;
            }

            $reminder->update([
                'status' => AppointmentReminder::STATUS_ESCALATED,
                'escalated_at' => $now,
            ]);

            $affected++;
        }

        return $affected;
    }

    public function requeue(AppointmentReminder $reminder, ?string $reason = null): AppointmentReminder
    {
        $now = Carbon::now()->utc();

        if (! $this->canTransition($reminder->status, AppointmentReminder::STATUS_PENDING)) {
            return $reminder;
        }

        $reminder->update([
            'status' => AppointmentReminder::STATUS_PENDING,
            'next_retry_at' => null,
            'escalated_at' => null,
            'failure_reason' => $reason,
            'last_attempted_at' => $now,
        ]);

        return $reminder->refresh();
    }

    public function canTransition(string $from, string $to): bool
    {
        if ($from === $to) {
            return true;
        }

        $matrix = [
            AppointmentReminder::STATUS_PENDING => [
                AppointmentReminder::STATUS_READY,
                AppointmentReminder::STATUS_CANCELLED,
                AppointmentReminder::STATUS_MISSED,
                AppointmentReminder::STATUS_FAILED,
            ],
            AppointmentReminder::STATUS_READY => [
                AppointmentReminder::STATUS_SENT,
                AppointmentReminder::STATUS_CANCELLED,
                AppointmentReminder::STATUS_MISSED,
                AppointmentReminder::STATUS_FAILED,
            ],
            AppointmentReminder::STATUS_MISSED => [
                AppointmentReminder::STATUS_PENDING,
                AppointmentReminder::STATUS_CANCELLED,
                AppointmentReminder::STATUS_ESCALATED,
            ],
            AppointmentReminder::STATUS_FAILED => [
                AppointmentReminder::STATUS_PENDING,
                AppointmentReminder::STATUS_CANCELLED,
                AppointmentReminder::STATUS_ESCALATED,
            ],
            AppointmentReminder::STATUS_ESCALATED => [
                AppointmentReminder::STATUS_PENDING,
                AppointmentReminder::STATUS_CANCELLED,
            ],
            AppointmentReminder::STATUS_SENT => [],
            AppointmentReminder::STATUS_CANCELLED => [],
        ];

        return in_array($to, $matrix[$from] ?? [], true);
    }

    /**
     * @return array{max_attempts:int,backoff_minutes:list<int>,escalate_on_exhausted:bool}
     */
    public function resolveRetryPolicy(?array $workspaceReminderPolicy = null): array
    {
        $policy = $workspaceReminderPolicy['retry'] ?? [];
        $maxAttempts = max(1, (int) ($policy['max_attempts'] ?? self::DEFAULT_RETRY['max_attempts']));
        $backoff = collect($policy['backoff_minutes'] ?? self::DEFAULT_RETRY['backoff_minutes'])
            ->map(fn ($item) => max(1, (int) $item))
            ->values()
            ->all();

        if ($backoff === []) {
            $backoff = self::DEFAULT_RETRY['backoff_minutes'];
        }

        return [
            'max_attempts' => $maxAttempts,
            'backoff_minutes' => $backoff,
            'escalate_on_exhausted' => (bool) ($policy['escalate_on_exhausted'] ?? self::DEFAULT_RETRY['escalate_on_exhausted']),
        ];
    }

    public function isInQuietPeriod(Carbon $timestampUtc, array $workspaceReminderPolicy): bool
    {
        $quiet = $workspaceReminderPolicy['quiet_hours'] ?? [];
        $quietEnabled = (bool) ($quiet['enabled'] ?? false);
        $weekendMute = (bool) ($workspaceReminderPolicy['weekend_mute'] ?? false);

        if (! $quietEnabled && ! $weekendMute) {
            return false;
        }

        $timezone = (string) ($quiet['timezone'] ?? 'UTC');
        $local = $timestampUtc->copy()->setTimezone($timezone);

        if ($weekendMute && in_array((int) $local->dayOfWeekIso, [6, 7], true)) {
            return true;
        }

        if (! $quietEnabled) {
            return false;
        }

        $start = (string) ($quiet['start'] ?? '22:00');
        $end = (string) ($quiet['end'] ?? '08:00');
        [$startHour, $startMinute] = array_map('intval', explode(':', $start));
        [$endHour, $endMinute] = array_map('intval', explode(':', $end));

        $minutes = ((int) $local->format('H')) * 60 + ((int) $local->format('i'));
        $startMinutes = $startHour * 60 + $startMinute;
        $endMinutes = $endHour * 60 + $endMinute;

        if ($startMinutes === $endMinutes) {
            return false;
        }

        if ($startMinutes < $endMinutes) {
            return $minutes >= $startMinutes && $minutes < $endMinutes;
        }

        return $minutes >= $startMinutes || $minutes < $endMinutes;
    }

    private function retryAt(Carbon $base, int $attemptCount, array $retryPolicy): Carbon
    {
        $backoff = $retryPolicy['backoff_minutes'];
        $index = min($attemptCount, max(count($backoff) - 1, 0));

        return $base->copy()->addMinutes((int) $backoff[$index]);
    }
}
