<?php

namespace App\Http\Requests\Api\V1\Webhook;

use App\Services\WebhookService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'url', 'max:500'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['required', 'string', Rule::in(array_merge(WebhookService::EVENTS, ['*']))],
        ];
    }
}
