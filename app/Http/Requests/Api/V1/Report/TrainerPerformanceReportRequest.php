<?php

namespace App\Http\Requests\Api\V1\Report;

use Illuminate\Foundation\Http\FormRequest;

class TrainerPerformanceReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }
}
