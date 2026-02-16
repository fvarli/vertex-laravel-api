<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::command('reminders:mark-missed')->everyFiveMinutes();
Schedule::command('reminders:prepare-ready')->everyFiveMinutes();
Schedule::command('reminders:retry-failed')->everyFiveMinutes();
Schedule::command('reminders:escalate-stale')->everyFiveMinutes();
