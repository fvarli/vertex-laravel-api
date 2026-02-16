<?php

namespace App\Http\Requests\Api\V1\AppointmentSeries;

use App\Models\AppointmentSeries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppointmentSeriesStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in([
                AppointmentSeries::STATUS_ACTIVE,
                AppointmentSeries::STATUS_PAUSED,
                AppointmentSeries::STATUS_ENDED,
            ])],
        ];
    }
}
