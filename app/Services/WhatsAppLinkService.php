<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\MessageTemplate;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class WhatsAppLinkService
{
    public function build(Student $student, ?Appointment $appointment = null, ?string $template = null): string
    {
        $phone = preg_replace('/[^0-9]/', '', $student->phone ?? '');

        // Try workspace default template first
        if ($template === null || $template === 'default') {
            $dbTemplate = $this->getDefaultTemplate($student->workspace_id);
            if ($dbTemplate && $appointment) {
                $message = $this->renderTemplate($dbTemplate, $student, $appointment);

                return 'https://wa.me/'.$phone.'?text='.rawurlencode($message);
            }
        }

        $message = match ($template) {
            'reminder' => $this->buildReminderMessage($student, $appointment),
            default => "Hi {$student->full_name}, this is your trainer. Please check your weekly plan.",
        };

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($message);
    }

    public function buildWithTemplate(Student $student, Appointment $appointment, MessageTemplate $template): string
    {
        $phone = preg_replace('/[^0-9]/', '', $student->phone ?? '');
        $message = $this->renderTemplate($template, $student, $appointment);

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($message);
    }

    private function getDefaultTemplate(int $workspaceId): ?MessageTemplate
    {
        return MessageTemplate::query()
            ->where('workspace_id', $workspaceId)
            ->where('channel', 'whatsapp')
            ->where('is_default', true)
            ->first();
    }

    private function renderTemplate(MessageTemplate $template, Student $student, Appointment $appointment): string
    {
        return $template->render([
            'student_name' => $student->full_name,
            'appointment_date' => $appointment->starts_at?->format('Y-m-d') ?? '',
            'appointment_time' => $appointment->starts_at?->format('H:i') ?? '',
            'trainer_name' => $appointment->trainer ? trim($appointment->trainer->name.' '.$appointment->trainer->surname) : '',
            'location' => $appointment->location ?? '',
        ]);
    }

    public function bulkLinks(int $workspaceId, ?int $trainerUserId, string $date): array
    {
        $dayStart = CarbonImmutable::parse($date)->startOfDay();
        $dayEnd = CarbonImmutable::parse($date)->endOfDay();

        $appointments = Appointment::query()
            ->with('student')
            ->where('workspace_id', $workspaceId)
            ->whereBetween('starts_at', [$dayStart, $dayEnd])
            ->whereIn('status', [Appointment::STATUS_PLANNED, Appointment::STATUS_DONE])
            ->when($trainerUserId !== null, fn (Builder $q) => $q->where('trainer_user_id', $trainerUserId))
            ->orderBy('starts_at')
            ->get();

        return $appointments->map(function (Appointment $appointment) {
            $student = $appointment->student;

            return [
                'appointment_id' => $appointment->id,
                'student_name' => $student?->full_name,
                'starts_at' => $appointment->starts_at?->toIso8601String(),
                'whatsapp_link' => $student ? $this->build($student, $appointment, 'reminder') : null,
                'whatsapp_status' => $appointment->whatsapp_status,
            ];
        })->all();
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
