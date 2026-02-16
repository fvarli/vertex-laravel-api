<?php

namespace App\Console\Commands;

use App\Services\AppointmentReminderService;
use Illuminate\Console\Command;

class MarkMissedRemindersCommand extends Command
{
    protected $signature = 'reminders:mark-missed';

    protected $description = 'Mark pending/ready reminders as missed when schedule time is in the past';

    public function handle(AppointmentReminderService $appointmentReminderService): int
    {
        $affected = $appointmentReminderService->markMissed();
        $this->info("Marked {$affected} reminders as missed.");

        return self::SUCCESS;
    }
}
