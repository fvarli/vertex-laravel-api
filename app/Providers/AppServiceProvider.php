<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\AppointmentSeries;
use App\Models\Program;
use App\Models\Student;
use App\Policies\AppointmentPolicy;
use App\Policies\AppointmentReminderPolicy;
use App\Policies\AppointmentSeriesPolicy;
use App\Policies\ProgramPolicy;
use App\Policies\StudentPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->register(\Barryvdh\DomPDF\ServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Student::class, StudentPolicy::class);
        Gate::policy(Program::class, ProgramPolicy::class);
        Gate::policy(Appointment::class, AppointmentPolicy::class);
        Gate::policy(AppointmentSeries::class, AppointmentSeriesPolicy::class);
        Gate::policy(AppointmentReminder::class, AppointmentReminderPolicy::class);

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(3)->by($request->ip()));

        RateLimiter::for('forgot-password', fn (Request $request) => Limit::perMinute(3)->by($request->ip()));

        RateLimiter::for('verify-email', fn (Request $request) => Limit::perMinute(6)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('resend-verification', fn (Request $request) => Limit::perMinute(3)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('reset-password', fn (Request $request) => Limit::perMinute(5)->by(($request->input('email') ?? 'unknown').'|'.$request->ip()));

        RateLimiter::for('avatar-upload', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('delete-account', fn (Request $request) => Limit::perMinute(3)->by($request->user()?->id ?: $request->ip()));
    }
}
