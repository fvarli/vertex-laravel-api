<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;
use App\Services\WorkspaceContextService;

class StudentPolicy
{
    public function __construct(private readonly WorkspaceContextService $workspaceContextService) {}

    public function view(User $user, Student $student): bool
    {
        return $this->canAccess($user, $student->workspace_id, $student->trainer_user_id);
    }

    public function update(User $user, Student $student): bool
    {
        return $this->canAccess($user, $student->workspace_id, $student->trainer_user_id);
    }

    public function setStatus(User $user, Student $student): bool
    {
        return $this->canAccess($user, $student->workspace_id, $student->trainer_user_id);
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
