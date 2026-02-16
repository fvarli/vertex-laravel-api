<?php

namespace App\Http\Requests\Api\V1\Reminder;

use App\Models\AppointmentReminder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'status' => ['nullable', 'string', Rule::in([
                AppointmentReminder::STATUS_PENDING,
                AppointmentReminder::STATUS_READY,
                AppointmentReminder::STATUS_SENT,
                AppointmentReminder::STATUS_MISSED,
                AppointmentReminder::STATUS_CANCELLED,
                AppointmentReminder::STATUS_FAILED,
                'all',
            ])],
            'trainer_id' => ['nullable', 'integer', 'exists:users,id'],
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ];
    }
}
