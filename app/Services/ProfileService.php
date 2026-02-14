<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

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

        $currentTokenId = $user->currentAccessToken()?->id;

        if ($currentTokenId) {
            $user->tokens()->whereKeyNot($currentTokenId)->delete();

            return;
        }

        $user->tokens()->delete();
    }

    public function updateAvatar(User $user, UploadedFile $file): User
    {
        $disk = Storage::disk('public');
        $oldAvatar = $user->avatar;
        $path = $file->store('avatars', 'public');

        try {
            $user->update(['avatar' => $path]);
        } catch (Throwable $e) {
            $disk->delete($path);
            throw $e;
        }

        if ($oldAvatar && $oldAvatar !== $path) {
            $disk->delete($oldAvatar);
        }

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
