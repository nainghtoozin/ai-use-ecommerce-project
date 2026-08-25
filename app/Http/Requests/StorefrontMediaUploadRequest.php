<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorefrontMediaUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.website') ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,webp,svg', 'max:10240'],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ];
    }
}
