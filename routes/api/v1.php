<?php

use App\Http\Controllers\Api\V1\AppointmentController;
use App\Http\Controllers\Api\V1\AppointmentSeriesController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CalendarController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DeviceTokenController;
use App\Http\Controllers\Api\V1\EmailVerificationController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MessageTemplateController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ProgramController;
use App\Http\Controllers\Api\V1\ProgramTemplateController;
use App\Http\Controllers\Api\V1\ReminderController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ReportExportController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\TrainerController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WebhookController;
use App\Http\Controllers\Api\V1\WhatsAppController;
use App\Http\Controllers\Api\V1\WorkspaceApprovalController;
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

    // Device token routes
    Route::prefix('devices')->name('v1.devices.')->controller(DeviceTokenController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::delete('/{device}', 'destroy')->name('destroy');
    });

    // Notification routes
    Route::prefix('me/notifications')->name('v1.notifications.')->controller(NotificationController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/unread-count', 'unreadCount')->name('unread-count');
        Route::patch('/read-all', 'markAllRead')->name('read-all');
        Route::patch('/{notification}/read', 'markRead')->name('read');
    });

    // Workspace routes
    Route::prefix('me')->name('v1.workspace.')->controller(WorkspaceController::class)->group(function () {
        Route::get('/workspaces', 'index')->name('index');
    });
    Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('v1.workspace.store');
    Route::put('/workspaces/{workspace}', [WorkspaceController::class, 'update'])->name('v1.workspace.update');
    Route::get('/workspaces/{workspace}/members', [WorkspaceController::class, 'members'])->name('v1.workspace.members');
    Route::post('/workspaces/{workspace}/switch', [WorkspaceController::class, 'switch'])->name('v1.workspace.switch');
    Route::prefix('platform/workspaces')->name('v1.platform.workspaces.')->middleware('platform.admin')->controller(WorkspaceApprovalController::class)->group(function () {
        Route::get('/pending', 'pending')->name('pending');
        Route::patch('/{workspace}/approval', 'update')->name('update');
    });

    Route::middleware('workspace.context')->group(function () {
        // Student routes
        Route::prefix('students')->name('v1.students.')->controller(StudentController::class)->group(function () {
            Route::post('/', 'store')->middleware('workspace.approved')->name('store');
            Route::get('/', 'index')->middleware(['cache.headers:30', 'sparse.fields'])->name('index');
            Route::get('/{student}', 'show')->middleware('sparse.fields')->name('show');
            Route::get('/{student}/timeline', 'timeline')->name('timeline');
            Route::put('/{student}', 'update')->middleware('workspace.approved')->name('update');
            Route::patch('/{student}/status', 'updateStatus')->middleware('workspace.approved')->name('status');
        });

        // Program routes
        Route::prefix('students/{student}/programs')->name('v1.programs.')->controller(ProgramController::class)->group(function () {
            Route::post('/', 'store')->middleware('workspace.approved')->name('store');
            Route::get('/', 'index')->name('index');
            Route::post('/from-template', 'storeFromTemplate')->middleware('workspace.approved')->name('store-from-template');
            Route::post('/copy-week', 'copyWeek')->middleware('workspace.approved')->name('copy-week');
        });
        Route::prefix('programs')->name('v1.programs.')->controller(ProgramController::class)->group(function () {
            Route::get('/{program}', 'show')->name('show');
            Route::put('/{program}', 'update')->middleware('workspace.approved')->name('update');
            Route::patch('/{program}/status', 'updateStatus')->middleware('workspace.approved')->name('status');
        });
        Route::prefix('program-templates')->name('v1.program-templates.')->controller(ProgramTemplateController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->middleware('workspace.approved')->name('store');
            Route::get('/{template}', 'show')->name('show');
            Route::put('/{template}', 'update')->middleware('workspace.approved')->name('update');
            Route::delete('/{template}', 'destroy')->middleware('workspace.approved')->name('destroy');
        });

        // Appointment and calendar routes
        Route::prefix('appointments')->name('v1.appointments.')->controller(AppointmentController::class)->group(function () {
            Route::post('/', 'store')->middleware(['workspace.approved', 'idempotent.appointments'])->name('store');
            Route::get('/', 'index')->middleware(['cache.headers:30', 'sparse.fields'])->name('index');
            Route::prefix('series')->name('series.')->controller(AppointmentSeriesController::class)->group(function () {
                Route::post('/', 'store')->middleware(['workspace.approved', 'idempotent.appointments'])->name('store');
                Route::get('/', 'index')->name('index');
                Route::get('/{series}', 'show')->name('show');
                Route::put('/{series}', 'update')->middleware('workspace.approved')->name('update');
                Route::patch('/{series}/status', 'updateStatus')->middleware('workspace.approved')->name('status');
            });
            Route::patch('/bulk-status', 'bulkUpdateStatus')->middleware('workspace.approved')->name('bulk-status');
            Route::patch('/{appointment}/whatsapp-status', 'updateWhatsappStatus')->middleware('workspace.approved')->name('whatsapp-status');
            Route::get('/{appointment}', 'show')->middleware('sparse.fields')->name('show');
            Route::put('/{appointment}', 'update')->middleware('workspace.approved')->name('update');
            Route::patch('/{appointment}/status', 'updateStatus')->middleware('workspace.approved')->name('status');
        });
        Route::prefix('reminders')->name('v1.reminders.')->controller(ReminderController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/export.csv', 'exportCsv')->name('export');
            Route::post('/bulk', 'bulk')->middleware('workspace.approved')->name('bulk');
            Route::patch('/{reminder}/open', 'open')->middleware('workspace.approved')->name('open');
            Route::patch('/{reminder}/mark-sent', 'markSent')->middleware('workspace.approved')->name('mark-sent');
            Route::patch('/{reminder}/requeue', 'requeue')->middleware('workspace.approved')->name('requeue');
            Route::patch('/{reminder}/cancel', 'cancel')->middleware('workspace.approved')->name('cancel');
        });
        Route::get('/dashboard/summary', [DashboardController::class, 'summary'])->middleware('cache.headers:120')->name('v1.dashboard.summary');
        Route::prefix('trainers')->name('v1.trainers.')->controller(TrainerController::class)->group(function () {
            Route::get('/overview', 'overview')->name('overview');
            Route::post('/', 'store')->middleware('workspace.approved')->name('store');
        });
        Route::prefix('reports')->name('v1.reports.')->controller(ReportController::class)->group(function () {
            Route::get('/appointments', 'appointments')->name('appointments');
            Route::get('/students', 'students')->name('students');
            Route::get('/programs', 'programs')->name('programs');
            Route::get('/reminders', 'reminders')->name('reminders');
            Route::get('/trainer-performance', 'trainerPerformance')->name('trainer-performance');
            Route::get('/student-retention', 'studentRetention')->name('student-retention');
        });
        Route::prefix('reports')->name('v1.reports.export.')->controller(ReportExportController::class)->group(function () {
            Route::get('/appointments/export', 'appointments')->name('appointments');
            Route::get('/students/export', 'students')->name('students');
            Route::get('/programs/export', 'programs')->name('programs');
            Route::get('/reminders/export', 'reminders')->name('reminders');
            Route::get('/trainer-performance/export', 'trainerPerformance')->name('trainer-performance');
        });
        Route::get('/calendar', [CalendarController::class, 'availability'])->name('v1.calendar.index');
        Route::get('/calendar/availability', [CalendarController::class, 'availability'])->name('v1.calendar.availability');

        // WhatsApp helper routes
        Route::get('/appointments/{appointment}/whatsapp-link', [WhatsAppController::class, 'appointmentLink'])->name('v1.whatsapp.appointment-link');
        Route::get('/whatsapp/bulk-links', [WhatsAppController::class, 'bulkLinks'])->name('v1.whatsapp.bulk-links');

        // Webhook routes
        Route::prefix('webhooks')->name('v1.webhooks.')->controller(WebhookController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/events', 'availableEvents')->name('events');
            Route::post('/', 'store')->middleware('workspace.approved')->name('store');
            Route::put('/{webhook}', 'update')->middleware('workspace.approved')->name('update');
            Route::delete('/{webhook}', 'destroy')->middleware('workspace.approved')->name('destroy');
        });

        // Message template routes
        Route::prefix('message-templates')->name('v1.message-templates.')->controller(MessageTemplateController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->middleware('workspace.approved')->name('store');
            Route::put('/{messageTemplate}', 'update')->middleware('workspace.approved')->name('update');
            Route::delete('/{messageTemplate}', 'destroy')->middleware('workspace.approved')->name('destroy');
        });
    });
});
