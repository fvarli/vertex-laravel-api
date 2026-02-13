<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\Profile\ChangePasswordRequest;
use App\Http\Requests\Api\V1\Profile\DeleteAccountRequest;
use App\Http\Requests\Api\V1\Profile\UpdateAvatarRequest;
use App\Http\Requests\Api\V1\Profile\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\ApiLogService;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends BaseController
{
    public function __construct(
        private readonly ProfileService $profileService,
        private readonly ApiLogService $apiLogService,
    ) {}

    /**
     * Return authenticated user profile.
     */
    public function show(Request $request): JsonResponse
    {
        return $this->sendResponse(new UserResource($request->user()));
    }

    /**
     * Update authenticated user profile fields.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->profileService->updateProfile($request->user(), $request->validated());

        $this->apiLogService->info('Profile updated', $request, [
            'fields_updated' => array_keys($request->validated()),
        ]);

        return $this->sendResponse(new UserResource($user), __('api.profile.updated'));
    }

    /**
     * Change authenticated user password.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->profileService->changePassword($request->user(), $request->validated('password'));

        $this->apiLogService->info('Password changed', $request);

        return $this->sendResponse([], __('api.profile.password_changed'));
    }

    /**
     * Upload or replace authenticated user avatar.
     */
    public function updateAvatar(UpdateAvatarRequest $request): JsonResponse
    {
        $user = $this->profileService->updateAvatar($request->user(), $request->file('avatar'));

        $this->apiLogService->info('Avatar uploaded', $request);

        return $this->sendResponse(new UserResource($user), __('api.profile.avatar_uploaded'));
    }

    /**
     * Delete authenticated user avatar.
     */
    public function deleteAvatar(Request $request): JsonResponse
    {
        $this->profileService->deleteAvatar($request->user());

        $this->apiLogService->info('Avatar deleted', $request);

        return $this->sendResponse([], __('api.profile.avatar_deleted'));
    }

    /**
     * Soft delete authenticated user account and revoke tokens.
     */
    public function destroy(DeleteAccountRequest $request): JsonResponse
    {
        $this->profileService->deleteAccount($request->user());

        $this->apiLogService->info('Account deleted', $request);

        return $this->sendResponse([], __('api.profile.account_deleted'));
    }
}
