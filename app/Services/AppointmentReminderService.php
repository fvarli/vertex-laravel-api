<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentReminder;
use Illuminate\Support\Carbon;

class AppointmentReminderService
{
    /**
     * @return list<int>
     */
    public function resolveOffsetsMinutes(?array $workspaceReminderPolicy = null): array
    {
        $offsets = $workspaceReminderPolicy['whatsapp_offsets_minutes'] ?? [1440, 120];
        $normalized = collect($offsets)
            ->map(fn ($value) => (int) $value)
            ->filter(fn (int $value) => $value > 0)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        return $normalized === [] ? [1440, 120] : $normalized;
    }

    public function syncForAppointment(Appointment $appointment, ?array $workspaceReminderPolicy = null): void
    {
        $offsets = $this->resolveOffsetsMinutes($workspaceReminderPolicy);
        $appointment->loadMissing(['workspace']);

        $now = now()->utc();
        $targetSlots = collect($offsets)->map(function (int $offsetMinutes) use ($appointment, $now) {
            $scheduledFor = $appointment->starts_at->copy()->subMinutes($offsetMinutes);
            $status = $scheduledFor->lte($now)
                ? AppointmentReminder::STATUS_MISSED
                : AppointmentReminder::STATUS_PENDING;

            return [
                'scheduled_for' => $scheduledFor->toDateTimeString(),
                'status' => $status,
                'offset_minutes' => $offsetMinutes,
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
            ])
            ->whereNotIn('scheduled_for', $targetSlots->pluck('scheduled_for')->all())
            ->delete();
    }

    public function cancelPending(Appointment $appointment): void
    {
        AppointmentReminder::query()
            ->where('appointment_id', $appointment->id)
            ->whereIn('status', [AppointmentReminder::STATUS_PENDING, AppointmentReminder::STATUS_READY])
            ->update(['status' => AppointmentReminder::STATUS_CANCELLED]);
    }

    public function markMissed(): int
    {
        return AppointmentReminder::query()
            ->whereIn('status', [AppointmentReminder::STATUS_PENDING, AppointmentReminder::STATUS_READY])
            ->where('scheduled_for', '<=', Carbon::now()->utc())
            ->update(['status' => AppointmentReminder::STATUS_MISSED]);
    }
}
