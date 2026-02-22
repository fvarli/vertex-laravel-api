<?php

namespace App\Notifications;

use App\Channels\FcmChannel;
use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AppointmentReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Appointment $appointment,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (FcmChannel::shouldSend($notifiable)) {
            $channels[] = FcmChannel::class;
        }

        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'appointment.reminder',
            'appointment_id' => $this->appointment->id,
            'student_name' => $this->appointment->student?->full_name,
            'starts_at' => $this->appointment->starts_at?->toIso8601String(),
            'location' => $this->appointment->location,
        ];
    }

    public function toFcm(object $notifiable): array
    {
        $studentName = $this->appointment->student?->full_name ?? 'Unknown';
        $time = $this->appointment->starts_at?->format('H:i') ?? '';

        return [
            'title' => 'Appointment reminder',
            'body' => "Upcoming appointment with {$studentName} at {$time}.",
            'data' => [
                'type' => 'appointment.reminder',
                'appointment_id' => (string) $this->appointment->id,
            ],
        ];
    }
}
