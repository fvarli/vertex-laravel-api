<?php

namespace App\Console\Commands;

use App\Services\AppointmentReminderService;
use Illuminate\Console\Command;

class EscalateStaleRemindersCommand extends Command
{
    protected $signature = 'reminders:escalate-stale';

    protected $description = 'Escalate stale reminders after retry policy exhaustion';

    public function handle(AppointmentReminderService $appointmentReminderService): int
    {
        $affected = $appointmentReminderService->escalateStale();
        $this->info("Escalated {$affected} reminders.");

        return self::SUCCESS;
    }
}
