<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorefrontPromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.website') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'storefront_media_id' => ['nullable', 'integer'],
            'cta_label' => ['nullable', 'string', 'max:100'],
            'link' => ['nullable', 'string', 'max:500'],
            'is_active' => ['required', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'position' => ['nullable', 'integer', 'min:0'],
            'desktop_visible' => ['required', 'boolean'],
            'mobile_visible' => ['required', 'boolean'],
        ];
    }
}
