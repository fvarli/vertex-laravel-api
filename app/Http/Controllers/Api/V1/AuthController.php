<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Services\ApiLogService;
use App\Services\AuthService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

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
        ], __('api.auth.registered'), 201);
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
        ], __('api.auth.login_success'));
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        $this->apiLogService->info('User logged out', $request);

        return $this->sendResponse([], __('api.auth.logged_out'));
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $this->authService->logoutAll($request->user());

        $this->apiLogService->info('User logged out from all devices', $request);

        return $this->sendResponse([], __('api.auth.logged_out_all'));
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authService->sendResetLink($request->validated('email'));

        $this->apiLogService->info('Password reset requested', $request, [
            'email' => $request->input('email'),
        ]);

        return $this->sendResponse([], __('api.password.reset_link'));
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = $this->authService->resetPassword($request->validated());

        if ($status === Password::PASSWORD_RESET) {
            $this->apiLogService->info('Password reset successful', $request, [
                'email' => $request->input('email'),
            ]);

            return $this->sendResponse([], __('api.password.reset_success'));
        }

        $this->apiLogService->warning('Password reset failed', $request, [
            'email' => $request->input('email'),
            'status' => $status,
        ]);

        return $this->sendError(__('api.password.reset_failed'), [], 400);
    }

    public function refreshToken(Request $request): JsonResponse
    {
        $token = $this->authService->refreshToken($request->user());

        $this->apiLogService->info('Token refreshed', $request);

        return $this->sendResponse(['token' => $token], __('api.auth.token_refreshed'));
    }
}
