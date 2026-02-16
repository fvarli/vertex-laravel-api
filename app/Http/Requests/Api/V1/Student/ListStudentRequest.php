<?php

namespace App\Http\Requests\Api\V1\Student;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListStudentRequest extends FormRequest
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
            'status' => ['nullable', 'string', Rule::in([Student::STATUS_ACTIVE, Student::STATUS_PASSIVE, 'all'])],
            'sort' => ['nullable', 'string', Rule::in(['id', 'full_name', 'created_at'])],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }
}
