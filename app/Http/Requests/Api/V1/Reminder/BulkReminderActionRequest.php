<?php

namespace App\Http\Requests\Api\V1\Reminder;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkReminderActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['required', 'integer', 'exists:appointment_reminders,id'],
            'action' => ['required', 'string', Rule::in(['mark-sent', 'cancel', 'requeue'])],
            'failure_reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
