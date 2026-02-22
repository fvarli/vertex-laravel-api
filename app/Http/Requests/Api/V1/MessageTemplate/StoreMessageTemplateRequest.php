<?php

namespace App\Http\Requests\Api\V1\MessageTemplate;

use Illuminate\Foundation\Http\FormRequest;

class StoreMessageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['sometimes', 'string', 'in:whatsapp'],
            'body' => ['required', 'string', 'max:2000'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
