<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\ApiLogService;
use App\Services\AuthService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends BaseController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly ApiLogService $apiLogService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        $this->apiLogService->info('User registered', $request, [
            'user_id'    => $result['user']->id,
            'user_email' => $result['user']->email,
        ]);

        return $this->sendResponse([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
        ], 'User registered successfully.', 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login($request->validated());
        } catch (AuthenticationException $e) {
            $this->apiLogService->warning('Login failed', $request, [
                'email'  => $request->input('email'),
                'reason' => $e->getMessage(),
            ]);

            return $this->sendError($e->getMessage(), [], 401);
        }

        $this->apiLogService->info('User logged in', $request, [
            'user_id' => $result['user']->id,
        ]);

        return $this->sendResponse([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
        ], 'Login successful.');
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        $this->apiLogService->info('User logged out', $request);

        return $this->sendResponse([], 'Logged out successfully.');
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $this->authService->logoutAll($request->user());

        $this->apiLogService->info('User logged out from all devices', $request);

        return $this->sendResponse([], 'Logged out from all devices successfully.');
    }
}
