<?php

namespace App\Http\Requests\Api\V1\Student;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in([Student::STATUS_ACTIVE, Student::STATUS_PASSIVE])],
        ];
    }
}
