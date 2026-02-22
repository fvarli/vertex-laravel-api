<?php

namespace App\Channels;

use App\Models\DeviceToken;
use App\Services\PushNotificationService;
use Illuminate\Notifications\Notification;

class FcmChannel
{
    public function __construct(private readonly PushNotificationService $pushService) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toFcm')) {
            return;
        }

        $message = $notification->toFcm($notifiable);

        if (! is_array($message) || empty($message['title'])) {
            return;
        }

        $this->pushService->sendToUser(
            $notifiable,
            $message['title'],
            $message['body'] ?? '',
            $message['data'] ?? [],
        );
    }

    public static function shouldSend(object $notifiable): bool
    {
        return DeviceToken::query()
            ->where('user_id', $notifiable->id)
            ->where('is_active', true)
            ->exists();
    }
}
