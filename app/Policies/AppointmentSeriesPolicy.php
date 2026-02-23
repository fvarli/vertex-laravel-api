<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\AppointmentSeries;
use App\Models\User;
use App\Services\WorkspaceContextService;

class AppointmentSeriesPolicy
{
    public function __construct(private readonly WorkspaceContextService $workspaceContextService) {}

    public function view(User $user, AppointmentSeries $series): bool
    {
        return $this->canAccess($user, $series->workspace_id, $series->trainer_user_id);
    }

    public function update(User $user, AppointmentSeries $series): bool
    {
        return $this->canAccess($user, $series->workspace_id, $series->trainer_user_id);
    }

    public function setStatus(User $user, AppointmentSeries $series): bool
    {
        return $this->canAccess($user, $series->workspace_id, $series->trainer_user_id);
    }

    private function canAccess(User $user, int $workspaceId, int $trainerId): bool
    {
        if ((int) $user->active_workspace_id !== $workspaceId) {
            return false;
        }

        $role = $this->workspaceContextService->getRole($user, $workspaceId);

        if (! $role) {
            return false;
        }

        if ($role === WorkspaceRole::OwnerAdmin->value) {
            return true;
        }

        return $trainerId === $user->id;
    }
}
