<?php

namespace App\Http\Requests\Api\V1\AppointmentSeries;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppointmentSeriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'start_date' => ['sometimes', 'date'],
            'starts_at_time' => ['sometimes', 'date_format:H:i:s'],
            'ends_at_time' => ['sometimes', 'date_format:H:i:s', 'after:starts_at_time'],
            'recurrence_rule' => ['sometimes', 'array'],
            'recurrence_rule.freq' => ['sometimes', 'string', Rule::in(['weekly', 'monthly'])],
            'recurrence_rule.interval' => ['nullable', 'integer', 'min:1', 'max:12'],
            'recurrence_rule.count' => ['nullable', 'integer', 'min:1', 'max:365'],
            'recurrence_rule.until' => ['nullable', 'date'],
            'recurrence_rule.byweekday' => ['nullable', 'array'],
            'recurrence_rule.byweekday.*' => ['integer', 'between:1,7'],
            'edit_scope' => ['required', 'string', Rule::in(['future', 'all'])],
        ];
    }
}
