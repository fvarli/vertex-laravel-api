<?php

namespace App\Http\Requests\Api\V1\MessageTemplate;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMessageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'body' => ['sometimes', 'string', 'max:2000'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
