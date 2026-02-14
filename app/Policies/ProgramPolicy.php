<?php

namespace App\Policies;

use App\Models\Program;
use App\Models\User;
use App\Services\WorkspaceContextService;

class ProgramPolicy
{
    public function __construct(private readonly WorkspaceContextService $workspaceContextService) {}

    public function view(User $user, Program $program): bool
    {
        return $this->canAccess($user, $program->workspace_id, $program->trainer_user_id);
    }

    public function update(User $user, Program $program): bool
    {
        return $this->canAccess($user, $program->workspace_id, $program->trainer_user_id);
    }

    public function setStatus(User $user, Program $program): bool
    {
        return $this->canAccess($user, $program->workspace_id, $program->trainer_user_id);
    }

    private function canAccess(User $user, int $workspaceId, int $trainerId): bool
    {
        $role = $this->workspaceContextService->getRole($user, $workspaceId);

        if (! $role) {
            return false;
        }

        if ($role === 'owner_admin') {
            return true;
        }

        return $trainerId === $user->id;
    }
}
