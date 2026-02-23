<?php

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Enums\SystemRole;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\WorkspaceApprovalRequestedNotification;
use App\Notifications\WorkspaceApprovedNotification;
use App\Notifications\WorkspaceRejectedNotification;
use Illuminate\Support\Facades\Notification;

class WorkspaceApprovalNotifier
{
    public function notifyApprovalRequested(Workspace $workspace): void
    {
        $owner = $workspace->owner;
        if (! $owner) {
            return;
        }

        $platformAdmins = User::query()
            ->where('system_role', SystemRole::PlatformAdmin->value)
            ->where('is_active', true)
            ->get();

        if ($platformAdmins->isEmpty()) {
            return;
        }

        Notification::send($platformAdmins, new WorkspaceApprovalRequestedNotification($workspace, $owner));
    }

    public function notifyDecision(Workspace $workspace): void
    {
        $owner = $workspace->owner;
        if (! $owner) {
            return;
        }

        if ($workspace->approval_status === ApprovalStatus::Approved->value) {
            $owner->notify(new WorkspaceApprovedNotification($workspace));

            return;
        }

        if ($workspace->approval_status === ApprovalStatus::Rejected->value) {
            $owner->notify(new WorkspaceRejectedNotification($workspace));
        }
    }
}
