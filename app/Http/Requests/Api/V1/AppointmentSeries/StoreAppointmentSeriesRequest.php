<?php

namespace App\Http\Requests\Api\V1\AppointmentSeries;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppointmentSeriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'trainer_user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'title' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'starts_at_time' => ['required', 'date_format:H:i:s'],
            'ends_at_time' => ['required', 'date_format:H:i:s', 'after:starts_at_time'],
            'recurrence_rule' => ['required', 'array'],
            'recurrence_rule.freq' => ['required', 'string', Rule::in(['weekly', 'monthly'])],
            'recurrence_rule.interval' => ['nullable', 'integer', 'min:1', 'max:12'],
            'recurrence_rule.count' => ['nullable', 'integer', 'min:1', 'max:365'],
            'recurrence_rule.until' => ['nullable', 'date'],
            'recurrence_rule.byweekday' => ['nullable', 'array'],
            'recurrence_rule.byweekday.*' => ['integer', 'between:1,7'],
        ];
    }
}
