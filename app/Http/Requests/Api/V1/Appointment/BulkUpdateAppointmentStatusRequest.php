<?php

namespace App\Http\Requests\Api\V1\Appointment;

use App\Models\Appointment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateAppointmentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:50'],
            'ids.*' => ['required', 'integer', 'exists:appointments,id'],
            'status' => ['required', 'string', Rule::in([
                Appointment::STATUS_PLANNED,
                Appointment::STATUS_DONE,
                Appointment::STATUS_CANCELLED,
                Appointment::STATUS_NO_SHOW,
            ])],
        ];
    }
}
