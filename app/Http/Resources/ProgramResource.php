<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgramResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'student_id' => $this->student_id,
            'trainer_user_id' => $this->trainer_user_id,
            'title' => $this->title,
            'goal' => $this->goal,
            'week_start_date' => $this->week_start_date?->toDateString(),
            'status' => $this->status,
            'items' => ProgramItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
