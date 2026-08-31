<?php

namespace App\Http\Requests;

use App\Services\StorefrontConfigurationResolver;
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
            'homepage_sections' => ['required', 'array', 'min:1'],
            'homepage_sections.*.id' => ['nullable', 'integer'],
            'homepage_sections.*.enabled' => ['required', 'boolean'],
            'homepage_sections.*.desktop_visible' => ['required', 'boolean'],
            'homepage_sections.*.mobile_visible' => ['required', 'boolean'],
            'homepage_sections.*.position' => ['required', 'integer', 'min:0'],
            'homepage_sections.*.variant' => ['nullable', 'string', 'max:50', 'in:' . implode(',', StorefrontConfigurationResolver::HERO_VARIANTS)],
            'hero.variant' => ['nullable', 'string', 'in:' . implode(',', StorefrontConfigurationResolver::HERO_VARIANTS)],
            'hero.title' => ['nullable', 'string', 'max:255'],
            'hero.subtitle' => ['nullable', 'string', 'max:1000'],
            'hero.button_text' => ['nullable', 'string', 'max:100'],
            'hero.button_link' => ['nullable', 'string', 'max:500', 'regex:/^(\/|https?:\/\/)/i'],
            'hero.media_ids' => ['nullable', 'array'],
            'hero.media_ids.*' => ['integer'],
            'hero_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
            'hero_image.*' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
            'hero_alt_text' => ['nullable', 'string', 'max:255'],
            'hero_remove_image' => ['nullable', 'boolean'],
            'labels' => ['nullable', 'array'],
            'labels.*' => ['nullable', 'string', 'max:100'],
        ];
    }
}
