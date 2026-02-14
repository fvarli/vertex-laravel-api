<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Student;

class WhatsAppLinkService
{
    public function build(Student $student, ?Appointment $appointment = null, ?string $template = null): string
    {
        $phone = preg_replace('/[^0-9]/', '', $student->phone ?? '');

        $message = match ($template) {
            'reminder' => $this->buildReminderMessage($student, $appointment),
            default => "Hi {$student->full_name}, this is your trainer. Please check your weekly plan.",
        };

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($message);
    }

    private function buildReminderMessage(Student $student, ?Appointment $appointment): string
    {
        if (! $appointment) {
            return "Hi {$student->full_name}, reminder for your upcoming training session.";
        }

        $date = $appointment->starts_at?->timezone('UTC')->format('Y-m-d');
        $time = $appointment->starts_at?->timezone('UTC')->format('H:i');

        return "Hi {$student->full_name}, reminder: your session is scheduled on {$date} at {$time} UTC.";
    }
}
