<?php

namespace App\Http\Requests\Api\V1\Program;

use App\Models\Program;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProgramFromTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'template_id' => ['required', 'integer', 'exists:program_templates,id'],
            'week_start_date' => ['required', 'date'],
            'status' => ['nullable', 'string', Rule::in([Program::STATUS_DRAFT, Program::STATUS_ACTIVE, Program::STATUS_ARCHIVED])],
            'title' => ['nullable', 'string', 'min:3', 'max:150'],
            'goal' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
