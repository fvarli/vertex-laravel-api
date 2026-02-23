<?php

namespace App\Exceptions;

class AppointmentConflictException extends BusinessRuleException
{
    public function __construct(string $message = 'Appointment conflict detected.')
    {
        parent::__construct($message, ['code' => ['time_slot_conflict']]);
    }
}
