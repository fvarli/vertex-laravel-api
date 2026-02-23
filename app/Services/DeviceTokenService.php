<?php

namespace App\Services;

use App\Models\DeviceToken;
use Illuminate\Support\Collection;

class DeviceTokenService
{
    public function listForUser(int $userId): Collection
    {
        return DeviceToken::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get();
    }

    public function register(int $userId, array $data): DeviceToken
    {
        return DeviceToken::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'token' => $data['token'],
            ],
            [
                'platform' => $data['platform'],
                'device_name' => $data['device_name'] ?? null,
                'is_active' => true,
                'last_used_at' => now(),
            ],
        );
    }

    public function delete(DeviceToken $device): void
    {
        $device->delete();
    }
}
