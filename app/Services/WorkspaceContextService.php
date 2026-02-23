<?php

namespace App\Services;

use App\Enums\WorkspaceRole;
use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

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
        return $this->getRole($user, $workspaceId) === WorkspaceRole::OwnerAdmin->value;
    }

    /**
     * Assert that the given user is an active member of the workspace.
     *
     * @throws ValidationException
     */
    public function assertTrainerInWorkspace(int $trainerUserId, int $workspaceId): void
    {
        $exists = User::query()
            ->whereKey($trainerUserId)
            ->whereHas('workspaces', fn ($q) => $q
                ->where('workspaces.id', $workspaceId)
                ->where('workspace_user.is_active', true))
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'trainer_user_id' => [__('api.workspace.membership_required')],
            ]);
        }
    }

    /**
     * Assert that the given student belongs to the workspace and return it.
     *
     * @throws ValidationException
     */
    public function assertStudentInWorkspace(int $studentId, int $workspaceId): Student
    {
        $student = Student::query()
            ->whereKey($studentId)
            ->where('workspace_id', $workspaceId)
            ->first();

        if (! $student) {
            throw ValidationException::withMessages([
                'student_id' => [__('api.workspace.membership_required')],
            ]);
        }

        return $student;
    }
}
