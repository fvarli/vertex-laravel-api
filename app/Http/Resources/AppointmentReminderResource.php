<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentReminderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'appointment_id' => $this->appointment_id,
            'channel' => $this->channel,
            'scheduled_for' => $this->scheduled_for,
            'status' => $this->status,
            'attempt_count' => $this->attempt_count,
            'last_attempted_at' => $this->last_attempted_at,
            'next_retry_at' => $this->next_retry_at,
            'escalated_at' => $this->escalated_at,
            'failure_reason' => $this->failure_reason,
            'opened_at' => $this->opened_at,
            'marked_sent_at' => $this->marked_sent_at,
            'marked_sent_by_user_id' => $this->marked_sent_by_user_id,
            'payload' => $this->payload,
            'appointment' => $this->whenLoaded('appointment', fn () => [
                'id' => $this->appointment?->id,
                'student_id' => $this->appointment?->student_id,
                'trainer_user_id' => $this->appointment?->trainer_user_id,
                'starts_at' => $this->appointment?->starts_at,
                'ends_at' => $this->appointment?->ends_at,
                'status' => $this->appointment?->status,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
