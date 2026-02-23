<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProfileService
{
    private const AVATAR_MAX_SIZE = 400;

    private const THUMB_SIZE = 64;

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
        $oldThumb = $user->avatar_thumb;

        $path = $file->store('avatars', 'public');

        $resizedPath = $this->resizeImage($disk->path($path), self::AVATAR_MAX_SIZE);
        $thumbPath = $this->createThumbnail($disk->path($path), self::THUMB_SIZE);

        $thumbRelative = $thumbPath ? 'avatars/'.basename($thumbPath) : null;

        try {
            $user->update([
                'avatar' => $path,
                'avatar_thumb' => $thumbRelative,
            ]);
        } catch (Throwable $e) {
            $disk->delete($path);
            if ($thumbRelative) {
                $disk->delete($thumbRelative);
            }
            throw $e;
        }

        if ($oldAvatar && $oldAvatar !== $path) {
            $disk->delete($oldAvatar);
        }
        if ($oldThumb && $oldThumb !== $thumbRelative) {
            $disk->delete($oldThumb);
        }

        return $user->refresh();
    }

    public function deleteAvatar(User $user): void
    {
        $disk = Storage::disk('public');

        if ($user->avatar) {
            $disk->delete($user->avatar);
        }
        if ($user->avatar_thumb) {
            $disk->delete($user->avatar_thumb);
        }

        $user->update(['avatar' => null, 'avatar_thumb' => null]);
    }

    public function deleteAccount(User $user): void
    {
        $user->tokens()->delete();
        $user->delete();
    }

    private function resizeImage(string $absolutePath, int $maxSize): ?string
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        $info = @getimagesize($absolutePath);
        if (! $info) {
            return null;
        }

        [$width, $height] = $info;
        if ($width <= $maxSize && $height <= $maxSize) {
            return $absolutePath;
        }

        $source = $this->createImageFromFile($absolutePath, $info[2]);
        if (! $source) {
            return null;
        }

        $ratio = min($maxSize / $width, $maxSize / $height);
        $newWidth = (int) round($width * $ratio);
        $newHeight = (int) round($height * $ratio);

        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagejpeg($resized, $absolutePath, 85);
        imagedestroy($source);
        imagedestroy($resized);

        return $absolutePath;
    }

    private function createThumbnail(string $absolutePath, int $size): ?string
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        $info = @getimagesize($absolutePath);
        if (! $info) {
            return null;
        }

        [$width, $height] = $info;
        $source = $this->createImageFromFile($absolutePath, $info[2]);
        if (! $source) {
            return null;
        }

        $cropSize = min($width, $height);
        $x = (int) round(($width - $cropSize) / 2);
        $y = (int) round(($height - $cropSize) / 2);

        $thumb = imagecreatetruecolor($size, $size);
        imagecopyresampled($thumb, $source, 0, 0, $x, $y, $size, $size, $cropSize, $cropSize);

        $thumbPath = preg_replace('/(\.[^.]+)$/', '_thumb$1', $absolutePath);
        imagejpeg($thumb, $thumbPath, 80);
        imagedestroy($source);
        imagedestroy($thumb);

        return $thumbPath;
    }

    private function createImageFromFile(string $path, int $type): ?\GdImage
    {
        return match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
            default => null,
        };
    }
}
