<?php

namespace App\Console\Commands;

use App\Services\AppointmentReminderService;
use Illuminate\Console\Command;

class PrepareReadyRemindersCommand extends Command
{
    protected $signature = 'reminders:prepare-ready';

    protected $description = 'Promote due reminders from pending to ready with quiet-hours policy';

    public function handle(AppointmentReminderService $appointmentReminderService): int
    {
        $affected = $appointmentReminderService->prepareReady();
        $this->info("Prepared {$affected} reminders as ready.");

        return self::SUCCESS;
    }
}
