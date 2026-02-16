<?php

namespace App\Http\Requests\Api\V1\Reminder;

use Illuminate\Foundation\Http\FormRequest;

class RequeueReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'failure_reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
