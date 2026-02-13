<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\EmailVerificationController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/health', HealthController::class)->name('v1.health');

Route::controller(AuthController::class)->group(function () {
    Route::post('/register', 'register')
        ->middleware('throttle:register')
        ->name('v1.auth.register');
    Route::post('/login', 'login')
        ->middleware('throttle:login')
        ->name('v1.auth.login');
    Route::post('/forgot-password', 'forgotPassword')
        ->middleware('throttle:forgot-password')
        ->name('v1.auth.forgot-password');
    Route::post('/reset-password', 'resetPassword')
        ->middleware('throttle:reset-password')
        ->name('v1.auth.reset-password');
});

Route::middleware(['auth:sanctum', 'user.active'])->group(function () {
    // Protected auth routes
    Route::controller(AuthController::class)->group(function () {
        Route::post('/logout', 'logout')->name('v1.auth.logout');
        Route::post('/logout-all', 'logoutAll')->name('v1.auth.logout-all');
        Route::post('/refresh-token', 'refreshToken')->name('v1.auth.refresh-token');
    });

    // Email verification routes
    Route::prefix('email')->name('v1.verification.')->controller(EmailVerificationController::class)->group(function () {
        Route::post('/verify/{id}/{hash}', 'verify')
            ->middleware(['signed', 'throttle:verify-email'])
            ->name('verify');
        Route::post('/resend', 'resend')
            ->middleware('throttle:resend-verification')
            ->name('resend');
    });

    // User routes
    Route::controller(UserController::class)->group(function () {
        Route::get('/users', 'index')
            ->middleware('verified')
            ->name('v1.users.index');
    });

    // Profile routes
    Route::prefix('me')->name('v1.profile.')->controller(ProfileController::class)->group(function () {
        Route::get('/', 'show')->name('show');
        Route::put('/', 'update')->name('update');
        Route::delete('/', 'destroy')
            ->middleware('throttle:delete-account')
            ->name('destroy');
        Route::post('/avatar', 'updateAvatar')
            ->middleware('throttle:avatar-upload')
            ->name('avatar.update');
        Route::delete('/avatar', 'deleteAvatar')->name('avatar.delete');
        Route::put('/password', 'changePassword')->name('password');
    });
});
