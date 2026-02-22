<?php

namespace App\Http\Requests\Api\V1\DeviceToken;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeviceTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'platform' => ['required', 'string', 'in:ios,android,web'],
            'token' => ['required', 'string', 'max:500'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
