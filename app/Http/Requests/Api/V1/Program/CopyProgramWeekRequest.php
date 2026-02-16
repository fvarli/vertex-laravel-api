<?php

namespace App\Http\Requests\Api\V1\Program;

use App\Models\Program;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CopyProgramWeekRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_week_start_date' => ['required', 'date'],
            'target_week_start_date' => ['required', 'date', 'different:source_week_start_date'],
            'status' => ['nullable', 'string', Rule::in([Program::STATUS_DRAFT, Program::STATUS_ACTIVE, Program::STATUS_ARCHIVED])],
        ];
    }
}
