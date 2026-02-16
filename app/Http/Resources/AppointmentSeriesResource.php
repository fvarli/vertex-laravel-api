<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentSeriesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'trainer_user_id' => $this->trainer_user_id,
            'student_id' => $this->student_id,
            'title' => $this->title,
            'location' => $this->location,
            'recurrence_rule' => $this->recurrence_rule,
            'start_date' => $this->start_date?->toDateString(),
            'starts_at_time' => $this->starts_at_time,
            'ends_at_time' => $this->ends_at_time,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
