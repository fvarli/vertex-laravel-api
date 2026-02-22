<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    public function sendToUser(User $user, string $title, string $body, array $data = []): int
    {
        $tokens = DeviceToken::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('token');

        $sent = 0;
        foreach ($tokens as $token) {
            if ($this->send($token, $title, $body, $data)) {
                $sent++;
            }
        }

        return $sent;
    }

    public function send(string $token, string $title, string $body, array $data = []): bool
    {
        if (! config('fcm.enabled')) {
            Log::debug('FCM disabled, skipping push', compact('token', 'title'));

            return true;
        }

        $serverKey = config('fcm.server_key');
        if (! $serverKey) {
            Log::warning('FCM server key not configured');

            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key='.$serverKey,
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'to' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $data,
            ]);

            if ($response->successful()) {
                return true;
            }

            Log::warning('FCM send failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('FCM send error', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
