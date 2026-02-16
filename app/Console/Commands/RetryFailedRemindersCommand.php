<?php

namespace App\Console\Commands;

use App\Services\AppointmentReminderService;
use Illuminate\Console\Command;

class RetryFailedRemindersCommand extends Command
{
    protected $signature = 'reminders:retry-failed';

    protected $description = 'Retry failed or missed reminders according to workspace retry policy';

    public function handle(AppointmentReminderService $appointmentReminderService): int
    {
        $affected = $appointmentReminderService->retryFailed();
        $this->info("Scheduled {$affected} reminder retries.");

        return self::SUCCESS;
    }
}
