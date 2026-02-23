<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WorkspaceService
{
    public function __construct(private readonly WorkspaceApprovalNotifier $workspaceApprovalNotifier) {}

    public function listForUser(User $user): Collection
    {
        return $user->workspaces()->orderBy('workspaces.id')->get();
    }

    public function members(Workspace $workspace): Collection
    {
        return $workspace->users()
            ->orderBy('workspace_user.role')
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'surname' => $user->surname,
                'email' => $user->email,
                'role' => $user->pivot->role,
                'is_active' => $user->pivot->is_active,
            ]);
    }

    public function createWorkspace(User $user, string $name): Workspace
    {
        $workspace = DB::transaction(function () use ($user, $name) {
            $workspace = Workspace::query()->create([
                'name' => trim($name),
                'owner_user_id' => $user->id,
                'approval_status' => 'pending',
                'approval_requested_at' => now(),
            ]);

            $workspace->users()->syncWithoutDetaching([
                $user->id => ['role' => 'owner_admin', 'is_active' => true],
            ]);

            if (! $user->active_workspace_id) {
                $user->update(['active_workspace_id' => $workspace->id]);
            }

            return $workspace;
        });

        $workspace->loadMissing('owner');
        $this->workspaceApprovalNotifier->notifyApprovalRequested($workspace);

        return $workspace;
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
