<?php

namespace App\Exceptions;

use Exception;

class AppointmentConflictException extends Exception
{
    public function __construct(string $message = 'Appointment conflict detected.')
    {
        parent::__construct($message);
    }
}
