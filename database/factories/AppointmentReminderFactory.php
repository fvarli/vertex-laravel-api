<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppointmentReminderFactory extends Factory
{
    protected $model = AppointmentReminder::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'appointment_id' => Appointment::factory(),
            'channel' => AppointmentReminder::CHANNEL_WHATSAPP,
            'scheduled_for' => now()->addHours(4),
            'status' => AppointmentReminder::STATUS_PENDING,
            'attempt_count' => 0,
            'opened_at' => null,
            'marked_sent_at' => null,
            'marked_sent_by_user_id' => null,
            'payload' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => AppointmentReminder::STATUS_SENT,
            'marked_sent_at' => now(),
            'marked_sent_by_user_id' => User::factory(),
        ]);
    }
}
