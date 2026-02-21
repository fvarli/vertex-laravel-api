<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;

class WorkspaceApprovalService
{
    public function __construct(private readonly WorkspaceApprovalNotifier $workspaceApprovalNotifier) {}

    public function approve(Workspace $workspace, User $approver, ?string $note = null): Workspace
    {
        $workspace->update([
            'approval_status' => 'approved',
            'approved_at' => now(),
            'approved_by_user_id' => $approver->id,
            'approval_note' => $note,
        ]);

        $workspace = $workspace->fresh();
        $workspace->loadMissing('owner');
        $this->workspaceApprovalNotifier->notifyDecision($workspace);

        return $workspace;
    }

    public function reject(Workspace $workspace, User $approver, ?string $note = null): Workspace
    {
        $workspace->update([
            'approval_status' => 'rejected',
            'approved_at' => null,
            'approved_by_user_id' => $approver->id,
            'approval_note' => $note,
        ]);

        $workspace = $workspace->fresh();
        $workspace->loadMissing('owner');
        $this->workspaceApprovalNotifier->notifyDecision($workspace);

        return $workspace;
    }
}
