<?php

namespace App\Http\Requests\Api\V1\User;

use Illuminate\Foundation\Http\FormRequest;

class ListWorkspaceTrainerOverviewRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('include_inactive')) {
            $this->merge([
                'include_inactive' => filter_var($this->input('include_inactive'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }

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
