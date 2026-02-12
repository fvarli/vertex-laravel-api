<?php

namespace App\Services;

use App\Models\User;

class ProfileService
{
    public function updateProfile(User $user, array $data): User
    {
        $user->update($data);

        return $user->refresh();
    }

    public function changePassword(User $user, string $password): void
    {
        $user->update(['password' => $password]);
    }
}
