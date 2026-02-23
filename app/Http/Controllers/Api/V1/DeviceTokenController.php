<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\DeviceToken\StoreDeviceTokenRequest;
use App\Http\Resources\Api\V1\DeviceTokenResource;
use App\Models\DeviceToken;
use App\Services\DeviceTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends BaseController
{
    public function __construct(private readonly DeviceTokenService $deviceTokenService) {}

    public function index(Request $request): JsonResponse
    {
        $tokens = $this->deviceTokenService->listForUser($request->user()->id);

        return $this->sendResponse(DeviceTokenResource::collection($tokens));
    }

    public function store(StoreDeviceTokenRequest $request): JsonResponse
    {
        $token = $this->deviceTokenService->register($request->user()->id, $request->validated());

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

        $this->deviceTokenService->delete($device);

        return $this->sendResponse([], __('api.device_token.deleted'));
    }
}
