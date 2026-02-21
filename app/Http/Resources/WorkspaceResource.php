<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkspaceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'owner_user_id' => $this->owner_user_id,
            'approval_status' => $this->approval_status,
            'approval_requested_at' => $this->approval_requested_at,
            'approved_at' => $this->approved_at,
            'approved_by_user_id' => $this->approved_by_user_id,
            'approval_note' => $this->approval_note,
            'role' => $this->pivot->role ?? null,
            'is_active_membership' => $this->pivot->is_active ?? null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
