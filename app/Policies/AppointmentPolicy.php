<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;
use App\Services\WorkspaceContextService;

class AppointmentPolicy
{
    public function __construct(private readonly WorkspaceContextService $workspaceContextService) {}

    public function view(User $user, Appointment $appointment): bool
    {
        return $this->canAccess($user, $appointment->workspace_id, $appointment->trainer_user_id);
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $this->canAccess($user, $appointment->workspace_id, $appointment->trainer_user_id);
    }

    public function setStatus(User $user, Appointment $appointment): bool
    {
        return $this->canAccess($user, $appointment->workspace_id, $appointment->trainer_user_id);
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
