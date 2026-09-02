<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStorefrontConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.website') ?? false;
    }

    public function rules(): array
    {
        return [
            'site_name' => ['nullable', 'string', 'max:255'],
            'site_tagline' => ['nullable', 'string', 'max:255'],
            'site_description' => ['nullable', 'string', 'max:5000'],
            'theme_id' => ['required', 'integer', 'exists:themes,id'],
            'reset_tokens' => ['nullable', 'boolean'],
            'tokens' => ['nullable', 'array'],
            'tokens.color' => ['nullable', 'array'],
            'tokens.color.primary' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'tokens.color.surface' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'tokens.color.background' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'tokens.color.text' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'tokens.color.muted' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'tokens.color.border' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'tokens.radius' => ['nullable', 'array'],
            'tokens.radius.button' => ['nullable', 'string', 'max:32'],
            'tokens.radius.card' => ['nullable', 'string', 'max:32'],
            'tokens.radius.input' => ['nullable', 'string', 'max:32'],
            'tokens.typography.heading_weight' => ['nullable', 'in:600,700,800'],
            'tokens.typography.line_height' => ['nullable', 'in:1.4,1.5,1.55,1.6'],
            'tokens.buttons.primary_style' => ['nullable', 'in:solid,outline,soft,ghost'],
            'tokens.cards.style' => ['nullable', 'in:bordered,raised,flat,soft'],
            'tokens.product_cards.variant' => ['nullable', 'in:standard,compact,image-focused'],
            'labels' => ['nullable', 'array'],
            'labels.*' => ['nullable', 'string', 'max:100'],
        ];
    }
}