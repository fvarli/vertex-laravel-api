<?php

namespace App\Http\Requests\Api\V1\Webhook;

use App\Services\WebhookService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'url' => ['sometimes', 'url', 'max:500'],
            'events' => ['sometimes', 'array', 'min:1'],
            'events.*' => ['required', 'string', Rule::in(array_merge(WebhookService::EVENTS, ['*']))],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
