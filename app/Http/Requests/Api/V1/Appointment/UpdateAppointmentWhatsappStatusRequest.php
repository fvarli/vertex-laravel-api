<?php

namespace App\Http\Requests\Api\V1\Appointment;

use App\Models\Appointment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppointmentWhatsappStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'whatsapp_status' => ['required', 'string', Rule::in([
                Appointment::WHATSAPP_STATUS_SENT,
                Appointment::WHATSAPP_STATUS_NOT_SENT,
            ])],
        ];
    }
}
