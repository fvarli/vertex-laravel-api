<?php

namespace App\Listeners;

use App\Events\AppointmentCreated;
use App\Models\User;
use App\Notifications\AppointmentCreatedNotification;

class SendAppointmentCreatedNotification
{
    public function handle(AppointmentCreated $event): void
    {
        $appointment = $event->appointment;
        $trainer = User::query()->find($appointment->trainer_user_id);

        if ($trainer) {
            $appointment->loadMissing('student');
            $trainer->notify(new AppointmentCreatedNotification($appointment));
        }
    }
}
