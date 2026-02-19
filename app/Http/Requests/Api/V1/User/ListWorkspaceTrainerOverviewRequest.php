<?php

namespace App\Http\Requests\Api\V1\User;

use Illuminate\Foundation\Http\FormRequest;

class ListWorkspaceTrainerOverviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'include_inactive' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:120'],
        ];
    }
}
