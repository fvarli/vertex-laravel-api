<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkspaceTrainerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'surname' => $this->surname,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_active' => (bool) $this->is_active,
            'membership_is_active' => isset($this->membership_is_active) ? (bool) $this->membership_is_active : null,
            'student_count' => isset($this->student_count) ? (int) $this->student_count : 0,
            'today_appointments' => isset($this->today_appointments) ? (int) $this->today_appointments : 0,
            'upcoming_7d_appointments' => isset($this->upcoming_7d_appointments) ? (int) $this->upcoming_7d_appointments : 0,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
