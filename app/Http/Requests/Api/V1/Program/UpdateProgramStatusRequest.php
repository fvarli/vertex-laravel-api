<?php

namespace App\Http\Requests\Api\V1\Program;

use App\Models\Program;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProgramStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in([Program::STATUS_DRAFT, Program::STATUS_ACTIVE, Program::STATUS_ARCHIVED])],
        ];
    }
}
