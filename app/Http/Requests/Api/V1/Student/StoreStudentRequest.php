<?php

namespace App\Http\Requests\Api\V1\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $workspaceId = $this->user()?->active_workspace_id;

        return [
            'full_name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'string', 'min:8', 'max:32', Rule::unique('students', 'phone')->where('workspace_id', $workspaceId)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'trainer_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
