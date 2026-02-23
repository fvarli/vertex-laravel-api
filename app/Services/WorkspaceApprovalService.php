<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class WorkspaceApprovalService
{
    public function __construct(private readonly WorkspaceApprovalNotifier $workspaceApprovalNotifier) {}

    public function listPending(int $perPage = 15): LengthAwarePaginator
    {
        return Workspace::query()
            ->where('approval_status', 'pending')
            ->orderBy('approval_requested_at')
            ->paginate($perPage);
    }

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
