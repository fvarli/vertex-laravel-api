<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

    public function updateAvatar(User $user, UploadedFile $file): User
    {
        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $filename = $user->id . '_' . md5(uniqid()) . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('avatars', $filename, 'public');

        $user->update(['avatar' => $path]);

        return $user->refresh();
    }

    public function deleteAvatar(User $user): void
    {
        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
            $user->update(['avatar' => null]);
        }
    }

    public function deleteAccount(User $user): void
    {
        $user->tokens()->delete();
        $user->delete();
    }
}
