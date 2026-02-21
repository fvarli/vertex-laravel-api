<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 15), 1), 50);
        $unreadOnly = (bool) $request->boolean('unread_only', false);

        $query = $request->user()
            ->notifications()
            ->orderByDesc('created_at');

        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        $notifications = $query->paginate($perPage);

        return $this->sendResponse(NotificationResource::collection($notifications)->response()->getData(true));
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = $request->user()->unreadNotifications()->count();

        return $this->sendResponse(['count' => $count]);
    }

    public function markRead(Request $request, DatabaseNotification $notification): JsonResponse
    {
        if ($notification->notifiable_id !== $request->user()->id || $notification->notifiable_type !== $request->user()::class) {
            return $this->sendError(__('api.forbidden'), [], 403);
        }

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return $this->sendResponse(new NotificationResource($notification->fresh()), __('api.notifications.marked_read'));
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()
            ->unreadNotifications()
            ->update(['read_at' => now()]);

        return $this->sendResponse([], __('api.notifications.marked_all_read'));
    }
}
