<?php

namespace App\Http\Requests\Api\V1\Appointment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['sometimes', 'integer', 'exists:students,id'],
            'trainer_user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['sometimes', 'date'],
            'location' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'edit_scope' => ['sometimes', 'string', Rule::in(['single', 'future', 'all'])],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $startsAt = $this->input('starts_at');
            $endsAt = $this->input('ends_at');

            if ($startsAt && $endsAt && strtotime((string) $endsAt) <= strtotime((string) $startsAt)) {
                $validator->errors()->add('ends_at', 'The ends_at must be after starts_at.');
            }
        });
    }
}
