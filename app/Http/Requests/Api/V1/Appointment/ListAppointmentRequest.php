<?php

namespace App\Http\Requests\Api\V1\Appointment;

use App\Models\Appointment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAppointmentRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', Rule::in([
                Appointment::STATUS_PLANNED,
                Appointment::STATUS_DONE,
                Appointment::STATUS_CANCELLED,
                Appointment::STATUS_NO_SHOW,
            ])],
            'whatsapp_status' => ['nullable', 'string', Rule::in([
                Appointment::WHATSAPP_STATUS_SENT,
                Appointment::WHATSAPP_STATUS_NOT_SENT,
                'all',
            ])],
            'sort' => ['nullable', 'string', Rule::in(['id', 'starts_at', 'ends_at', 'created_at'])],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'trainer_id' => ['nullable', 'integer', 'exists:users,id'],
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }
}
