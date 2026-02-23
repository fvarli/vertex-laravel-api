<?php

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class WorkspaceApprovalService
{
    public function __construct(private readonly WorkspaceApprovalNotifier $workspaceApprovalNotifier) {}

    public function listPending(int $perPage = 15): LengthAwarePaginator
    {
        return Workspace::query()
            ->where('approval_status', ApprovalStatus::Pending->value)
            ->orderBy('approval_requested_at')
            ->paginate($perPage);
    }

    public function approve(Workspace $workspace, User $approver, ?string $note = null): Workspace
    {
        $workspace->update([
            'approval_status' => ApprovalStatus::Approved->value,
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
            'approval_status' => ApprovalStatus::Rejected->value,
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
