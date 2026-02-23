<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\AppointmentReminder;
use App\Models\User;
use App\Services\WorkspaceContextService;

class AppointmentReminderPolicy
{
    public function __construct(private readonly WorkspaceContextService $workspaceContextService) {}

    public function view(User $user, AppointmentReminder $reminder): bool
    {
        return $this->canAccess($user, $reminder);
    }

    public function update(User $user, AppointmentReminder $reminder): bool
    {
        return $this->canAccess($user, $reminder);
    }

    private function canAccess(User $user, AppointmentReminder $reminder): bool
    {
        $appointment = $reminder->appointment;

        if (! $appointment) {
            return false;
        }

        if ((int) $user->active_workspace_id !== (int) $appointment->workspace_id) {
            return false;
        }

        $role = $this->workspaceContextService->getRole($user, (int) $appointment->workspace_id);

        if (! $role) {
            return false;
        }

        if ($role === WorkspaceRole::OwnerAdmin->value) {
            return true;
        }

        return (int) $appointment->trainer_user_id === (int) $user->id;
    }
}
