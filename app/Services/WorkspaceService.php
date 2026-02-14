<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class WorkspaceService
{
    public function createWorkspace(User $user, string $name): Workspace
    {
        return DB::transaction(function () use ($user, $name) {
            $workspace = Workspace::query()->create([
                'name' => trim($name),
                'owner_user_id' => $user->id,
            ]);

            $workspace->users()->syncWithoutDetaching([
                $user->id => ['role' => 'owner_admin', 'is_active' => true],
            ]);

            if (! $user->active_workspace_id) {
                $user->update(['active_workspace_id' => $workspace->id]);
            }

            return $workspace;
        });
    }

    public function switchWorkspace(User $user, Workspace $workspace): void
    {
        $hasMembership = $user->workspaces()
            ->where('workspaces.id', $workspace->id)
            ->wherePivot('is_active', true)
            ->exists();

        if (! $hasMembership) {
            throw new AuthorizationException(__('api.workspace.membership_required'));
        }

        $user->update(['active_workspace_id' => $workspace->id]);
    }
}
