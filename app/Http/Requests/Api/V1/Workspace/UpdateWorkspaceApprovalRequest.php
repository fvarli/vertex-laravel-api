<?php

namespace App\Http\Requests\Api\V1\Workspace;

use App\Enums\ApprovalStatus;
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
            'approval_status' => ['required', Rule::in([ApprovalStatus::Approved->value, ApprovalStatus::Rejected->value])],
            'approval_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
