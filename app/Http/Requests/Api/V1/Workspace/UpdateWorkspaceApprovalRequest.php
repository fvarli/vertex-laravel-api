<?php

namespace App\Http\Requests\Api\V1\Workspace;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkspaceApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'approval_status' => ['required', Rule::in(['approved', 'rejected'])],
            'approval_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
