<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends BaseController
{
    public function verify(Request $request, int $id, string $hash): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return $this->sendResponse([], __('api.verification.already_verified'));
        }

        if (! hash_equals((string) $id, (string) $user->getKey())) {
            return $this->sendError(__('api.verification.invalid_link'), [], 400);
        }

        if (! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return $this->sendError(__('api.verification.invalid_link'), [], 400);
        }

        $user->markEmailAsVerified();

        return $this->sendResponse([], __('api.verification.verified'));
    }

    public function resend(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return $this->sendResponse([], __('api.verification.already_verified'));
        }

        $request->user()->sendEmailVerificationNotification();

        return $this->sendResponse([], __('api.verification.link_sent'));
    }
}
