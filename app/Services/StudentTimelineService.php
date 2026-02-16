<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Program;
use App\Models\Student;

class StudentTimelineService
{
    public function list(Student $student, int $limit = 30): array
    {
        $programEvents = Program::query()
            ->where('student_id', $student->id)
            ->orderBy('week_start_date', 'desc')
            ->limit($limit)
            ->get()
            ->map(function (Program $program) {
                return [
                    'type' => 'program',
                    'id' => $program->id,
                    'event_at' => $program->week_start_date?->toDateString(),
                    'status' => $program->status,
                    'title' => $program->title,
                    'meta' => [
                        'goal' => $program->goal,
                    ],
                ];
            });

        $appointmentEvents = Appointment::query()
            ->where('student_id', $student->id)
            ->orderBy('starts_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function (Appointment $appointment) {
                return [
                    'type' => 'appointment',
                    'id' => $appointment->id,
                    'event_at' => optional($appointment->starts_at)?->toIso8601String(),
                    'status' => $appointment->status,
                    'title' => $appointment->location ?: 'Session',
                    'meta' => [
                        'ends_at' => optional($appointment->ends_at)?->toIso8601String(),
                        'whatsapp_status' => $appointment->whatsapp_status,
                    ],
                ];
            });

        return $programEvents
            ->concat($appointmentEvents)
            ->sortByDesc('event_at')
            ->take($limit)
            ->values()
            ->all();
    }
}
