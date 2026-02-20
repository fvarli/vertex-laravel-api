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

    protected function prepareForValidation(): void
    {
        foreach (['escalated_only', 'retry_due_only'] as $field) {
            if ($this->has($field)) {
                $this->merge([
                    $field => filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
                ]);
            }
        }
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
                AppointmentReminder::STATUS_ESCALATED,
                'all',
            ])],
            'trainer_id' => ['nullable', 'integer', 'exists:users,id'],
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'escalated_only' => ['nullable', 'boolean'],
            'retry_due_only' => ['nullable', 'boolean'],
        ];
    }
}
