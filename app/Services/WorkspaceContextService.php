<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;

class WorkspaceContextService
{
    public function getActiveWorkspace(User $user): Workspace
    {
        $workspaceId = $user->active_workspace_id;

        if (! $workspaceId) {
            throw new AuthorizationException(__('api.workspace.no_active_workspace'));
        }

        $workspace = Workspace::query()->find($workspaceId);

        if (! $workspace) {
            throw new AuthorizationException(__('api.workspace.no_active_workspace'));
        }

        $membership = $user->workspaces()
            ->where('workspaces.id', $workspace->id)
            ->wherePivot('is_active', true)
            ->first();

        if (! $membership) {
            throw new AuthorizationException(__('api.workspace.membership_required'));
        }

        return $workspace;
    }

    public function getRole(User $user, int $workspaceId): ?string
    {
        $workspace = $user->workspaces()
            ->where('workspaces.id', $workspaceId)
            ->wherePivot('is_active', true)
            ->first();

        return $workspace?->pivot?->role;
    }

    public function isOwnerAdmin(User $user, int $workspaceId): bool
    {
        return $this->getRole($user, $workspaceId) === 'owner_admin';
    }
}
