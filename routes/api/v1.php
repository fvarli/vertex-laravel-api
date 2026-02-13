<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\EmailVerificationController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('v1.health');

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register')->name('v1.auth.register');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('v1.auth.login');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:forgot-password')->name('v1.auth.forgot-password');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:reset-password')->name('v1.auth.reset-password');

Route::middleware(['auth:sanctum', 'user.active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('v1.auth.logout');
    Route::post('/logout-all', [AuthController::class, 'logoutAll'])->name('v1.auth.logout-all');
    Route::post('/refresh-token', [AuthController::class, 'refreshToken'])->name('v1.auth.refresh-token');

    Route::post('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:verify-email'])
        ->name('v1.verification.verify');
    Route::post('/email/resend', [EmailVerificationController::class, 'resend'])->middleware('throttle:resend-verification')->name('v1.verification.resend');

    Route::get('/users', [UserController::class, 'index'])
        ->middleware('verified')
        ->name('v1.users.index');

    Route::get('/me', [ProfileController::class, 'show'])->name('v1.profile.show');
    Route::put('/me', [ProfileController::class, 'update'])->name('v1.profile.update');
    Route::delete('/me', [ProfileController::class, 'destroy'])->name('v1.profile.destroy');
    Route::post('/me/avatar', [ProfileController::class, 'updateAvatar'])->name('v1.profile.avatar.update');
    Route::delete('/me/avatar', [ProfileController::class, 'deleteAvatar'])->name('v1.profile.avatar.delete');
    Route::put('/me/password', [ProfileController::class, 'changePassword'])->name('v1.profile.password');
});
