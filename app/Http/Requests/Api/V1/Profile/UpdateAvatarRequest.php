<?php

namespace App\Http\Requests\Api\V1\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'avatar' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'],
        ];
    }
}
