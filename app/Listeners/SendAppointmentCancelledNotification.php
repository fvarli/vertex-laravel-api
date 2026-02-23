<?php

namespace App\Listeners;

use App\Events\AppointmentStatusChanged;
use App\Models\Appointment;
use App\Models\User;
use App\Notifications\AppointmentCancelledNotification;

class SendAppointmentCancelledNotification
{
    public function handle(AppointmentStatusChanged $event): void
    {
        if ($event->newStatus !== Appointment::STATUS_CANCELLED) {
            return;
        }

        $appointment = $event->appointment;
        $trainer = User::query()->find($appointment->trainer_user_id);

        if ($trainer) {
            $appointment->loadMissing('student');
            $trainer->notify(new AppointmentCancelledNotification($appointment));
        }
    }
}
