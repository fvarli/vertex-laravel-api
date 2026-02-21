<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;

class WorkspaceApprovalService
{
    public function approve(Workspace $workspace, User $approver, ?string $note = null): Workspace
    {
        $workspace->update([
            'approval_status' => 'approved',
            'approved_at' => now(),
            'approved_by_user_id' => $approver->id,
            'approval_note' => $note,
        ]);

        return $workspace->fresh();
    }

    public function reject(Workspace $workspace, User $approver, ?string $note = null): Workspace
    {
        $workspace->update([
            'approval_status' => 'rejected',
            'approved_at' => null,
            'approved_by_user_id' => $approver->id,
            'approval_note' => $note,
        ]);

        return $workspace->fresh();
    }
}
