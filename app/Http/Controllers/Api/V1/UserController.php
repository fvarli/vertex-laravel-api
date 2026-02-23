<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\User\ListUserRequest;
use App\Http\Resources\UserResource;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;

class UserController extends BaseController
{
    public function __construct(private readonly UserService $userService) {}

    /**
     * List users with pagination.
     */
    public function index(ListUserRequest $request): JsonResponse
    {
        $users = $this->userService->list($request->validated());

        return $this->sendResponse(UserResource::collection($users)->response()->getData(true));
    }
}
