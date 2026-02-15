<?php

namespace App\Services;

use App\Models\User;

class AccessContextService
{
    public function build(User $user): array
    {
        $workspaceRole = null;
        $workspaceId = $user->active_workspace_id;

        if ($workspaceId) {
            $workspaceRole = $user->workspaces()
                ->where('workspaces.id', $workspaceId)
                ->wherePivot('is_active', true)
                ->first()?->pivot?->role;
        }

        return [
            'system_role' => $user->system_role ?? 'workspace_user',
            'active_workspace_role' => $workspaceRole,
            'permissions' => $this->resolvePermissions($user, $workspaceId, $workspaceRole),
        ];
    }

    private function resolvePermissions(User $user, ?int $workspaceId, ?string $workspaceRole): array
    {
        if (($user->system_role ?? null) === 'platform_admin') {
            return ['*'];
        }

        $permissions = collect();

        if ($workspaceId) {
            $permissions = $permissions
                ->merge(
                    $user->roles()
                        ->wherePivot('workspace_id', $workspaceId)
                        ->with('permissions:id,name')
                        ->get()
                        ->flatMap(fn ($role) => $role->permissions->pluck('name'))
                )
                ->merge(
                    $user->permissions()
                        ->wherePivot('workspace_id', $workspaceId)
                        ->pluck('permissions.name')
                );
        }

        // Backward-compatible fallback while transitioning to explicit RBAC assignments.
        if ($permissions->isEmpty() && $workspaceRole) {
            $permissions = $permissions->merge(match ($workspaceRole) {
                'owner_admin' => ['workspace.manage', 'students.manage', 'programs.manage', 'appointments.manage', 'calendar.view'],
                'trainer' => ['students.own', 'programs.own', 'appointments.own', 'calendar.view'],
                default => [],
            });
        }

        return $permissions
            ->filter(fn ($permission) => is_string($permission) && $permission !== '')
            ->unique()
            ->values()
            ->all();
    }
}
