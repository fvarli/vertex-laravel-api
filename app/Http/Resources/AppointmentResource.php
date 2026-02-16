<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'series_id' => $this->series_id,
            'series_occurrence_date' => $this->series_occurrence_date,
            'is_series_exception' => $this->is_series_exception,
            'series_edit_scope_applied' => $this->series_edit_scope_applied,
            'workspace_id' => $this->workspace_id,
            'trainer_user_id' => $this->trainer_user_id,
            'student_id' => $this->student_id,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'status' => $this->status,
            'whatsapp_status' => $this->whatsapp_status,
            'whatsapp_marked_at' => $this->whatsapp_marked_at,
            'whatsapp_marked_by_user_id' => $this->whatsapp_marked_by_user_id,
            'next_pending_reminder_at' => $this->whenLoaded('reminders', function () {
                return $this->reminders
                    ->whereIn('status', ['pending', 'ready'])
                    ->sortBy('scheduled_for')
                    ->first()?->scheduled_for;
            }),
            'reminder_summary' => $this->whenLoaded('reminders', function () {
                return [
                    'pending' => $this->reminders->whereIn('status', ['pending', 'ready'])->count(),
                    'sent' => $this->reminders->where('status', 'sent')->count(),
                ];
            }),
            'location' => $this->location,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
