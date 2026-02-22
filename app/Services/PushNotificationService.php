<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\User;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    private ?string $accessToken = null;

    private ?float $tokenExpiresAt = null;

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

        $accessToken = $this->getAccessToken();
        if (! $accessToken) {
            return false;
        }

        $projectId = config('fcm.project_id');
        if (! $projectId) {
            Log::warning('FCM project ID not configured');

            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$accessToken,
            ])->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => array_map('strval', $data),
                ],
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

    protected function getAccessToken(): ?string
    {
        if ($this->accessToken && $this->tokenExpiresAt && time() < $this->tokenExpiresAt) {
            return $this->accessToken;
        }

        $credentialsPath = config('fcm.credentials_path');
        if (! $credentialsPath || ! file_exists($credentialsPath)) {
            Log::warning('FCM credentials file not found', ['path' => $credentialsPath]);

            return null;
        }

        try {
            $credentials = new ServiceAccountCredentials(
                'https://www.googleapis.com/auth/firebase.messaging',
                json_decode(file_get_contents($credentialsPath), true)
            );

            $token = $credentials->fetchAuthToken();

            if (empty($token['access_token'])) {
                Log::warning('FCM OAuth2 token response missing access_token');

                return null;
            }

            $this->accessToken = $token['access_token'];
            $this->tokenExpiresAt = time() + ($token['expires_in'] ?? 3500) - 60;

            return $this->accessToken;
        } catch (\Throwable $e) {
            Log::error('FCM OAuth2 token error', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
