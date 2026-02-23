<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\NotificationResource;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends BaseController
{
    public function __construct(private readonly NotificationService $notificationService) {}

    public function index(Request $request): JsonResponse
    {
        $notifications = $this->notificationService->list($request->user(), [
            'per_page' => $request->query('per_page', 15),
            'unread_only' => $request->boolean('unread_only', false),
        ]);

        return $this->sendResponse(NotificationResource::collection($notifications)->response()->getData(true));
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = $this->notificationService->unreadCount($request->user());

        return $this->sendResponse(['count' => $count]);
    }

    public function markRead(Request $request, DatabaseNotification $notification): JsonResponse
    {
        if ($notification->notifiable_id !== $request->user()->id || $notification->notifiable_type !== $request->user()::class) {
            return $this->sendError(__('api.forbidden'), [], 403);
        }

        $notification = $this->notificationService->markRead($notification);

        return $this->sendResponse(new NotificationResource($notification), __('api.notifications.marked_read'));
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $this->notificationService->markAllRead($request->user());

        return $this->sendResponse([], __('api.notifications.marked_all_read'));
    }
}
