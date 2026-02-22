<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\DeviceToken\StoreDeviceTokenRequest;
use App\Http\Resources\Api\V1\DeviceTokenResource;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $tokens = DeviceToken::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return $this->sendResponse(DeviceTokenResource::collection($tokens));
    }

    public function store(StoreDeviceTokenRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $token = DeviceToken::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'token' => $validated['token'],
            ],
            [
                'platform' => $validated['platform'],
                'device_name' => $validated['device_name'] ?? null,
                'is_active' => true,
                'last_used_at' => now(),
            ],
        );

        return $this->sendResponse(
            new DeviceTokenResource($token),
            __('api.device_token.registered'),
            201,
        );
    }

    public function destroy(Request $request, DeviceToken $device): JsonResponse
    {
        if ($device->user_id !== $request->user()->id) {
            return $this->sendError(__('api.forbidden'), [], 403);
        }

        $device->delete();

        return $this->sendResponse([], __('api.device_token.deleted'));
    }
}
