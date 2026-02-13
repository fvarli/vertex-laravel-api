<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok', 'version' => 'v1']))
    ->name('v1.health');

Route::post('/register', [AuthController::class, 'register'])->name('v1.auth.register');
Route::post('/login', [AuthController::class, 'login'])->name('v1.auth.login');

Route::middleware(['auth:sanctum', 'user.active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('v1.auth.logout');
    Route::post('/logout-all', [AuthController::class, 'logoutAll'])->name('v1.auth.logout-all');

    Route::get('/me', [ProfileController::class, 'show'])->name('v1.profile.show');
    Route::put('/me', [ProfileController::class, 'update'])->name('v1.profile.update');
    Route::put('/me/password', [ProfileController::class, 'changePassword'])->name('v1.profile.password');
});
