<?php

use App\Http\Controllers\Api\V1\AppointmentController;
use App\Http\Controllers\Api\V1\AppointmentSeriesController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CalendarController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\EmailVerificationController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ProgramController;
use App\Http\Controllers\Api\V1\ProgramTemplateController;
use App\Http\Controllers\Api\V1\ReminderController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WhatsAppController;
use App\Http\Controllers\Api\V1\WorkspaceController;
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

    // Workspace routes
    Route::prefix('me')->name('v1.workspace.')->controller(WorkspaceController::class)->group(function () {
        Route::get('/workspaces', 'index')->name('index');
    });
    Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('v1.workspace.store');
    Route::post('/workspaces/{workspace}/switch', [WorkspaceController::class, 'switch'])->name('v1.workspace.switch');

    Route::middleware('workspace.context')->group(function () {
        // Student routes
        Route::prefix('students')->name('v1.students.')->controller(StudentController::class)->group(function () {
            Route::post('/', 'store')->name('store');
            Route::get('/', 'index')->name('index');
            Route::get('/{student}', 'show')->name('show');
            Route::get('/{student}/timeline', 'timeline')->name('timeline');
            Route::put('/{student}', 'update')->name('update');
            Route::patch('/{student}/status', 'updateStatus')->name('status');
        });

        // Program routes
        Route::prefix('students/{student}/programs')->name('v1.programs.')->controller(ProgramController::class)->group(function () {
            Route::post('/', 'store')->name('store');
            Route::get('/', 'index')->name('index');
            Route::post('/from-template', 'storeFromTemplate')->name('store-from-template');
            Route::post('/copy-week', 'copyWeek')->name('copy-week');
        });
        Route::prefix('programs')->name('v1.programs.')->controller(ProgramController::class)->group(function () {
            Route::get('/{program}', 'show')->name('show');
            Route::put('/{program}', 'update')->name('update');
            Route::patch('/{program}/status', 'updateStatus')->name('status');
        });
        Route::prefix('program-templates')->name('v1.program-templates.')->controller(ProgramTemplateController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('/{template}', 'show')->name('show');
            Route::put('/{template}', 'update')->name('update');
            Route::delete('/{template}', 'destroy')->name('destroy');
        });

        // Appointment and calendar routes
        Route::prefix('appointments')->name('v1.appointments.')->controller(AppointmentController::class)->group(function () {
            Route::post('/', 'store')->middleware('idempotent.appointments')->name('store');
            Route::get('/', 'index')->name('index');
            Route::prefix('series')->name('series.')->controller(AppointmentSeriesController::class)->group(function () {
                Route::post('/', 'store')->middleware('idempotent.appointments')->name('store');
                Route::get('/', 'index')->name('index');
                Route::get('/{series}', 'show')->name('show');
                Route::put('/{series}', 'update')->name('update');
                Route::patch('/{series}/status', 'updateStatus')->name('status');
            });
            Route::patch('/{appointment}/whatsapp-status', 'updateWhatsappStatus')->name('whatsapp-status');
            Route::get('/{appointment}', 'show')->name('show');
            Route::put('/{appointment}', 'update')->name('update');
            Route::patch('/{appointment}/status', 'updateStatus')->name('status');
        });
        Route::prefix('reminders')->name('v1.reminders.')->controller(ReminderController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::patch('/{reminder}/open', 'open')->name('open');
            Route::patch('/{reminder}/mark-sent', 'markSent')->name('mark-sent');
            Route::patch('/{reminder}/cancel', 'cancel')->name('cancel');
        });
        Route::get('/dashboard/summary', [DashboardController::class, 'summary'])->name('v1.dashboard.summary');
        Route::prefix('reports')->name('v1.reports.')->controller(ReportController::class)->group(function () {
            Route::get('/appointments', 'appointments')->name('appointments');
            Route::get('/students', 'students')->name('students');
            Route::get('/programs', 'programs')->name('programs');
        });
        Route::get('/calendar', [CalendarController::class, 'availability'])->name('v1.calendar.index');
        Route::get('/calendar/availability', [CalendarController::class, 'availability'])->name('v1.calendar.availability');

        // WhatsApp helper routes
        Route::get('/appointments/{appointment}/whatsapp-link', [WhatsAppController::class, 'appointmentLink'])->name('v1.whatsapp.appointment-link');
    });
});
