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
            'checkout' => ['nullable', 'array'],
            'checkout.title' => ['nullable', 'string', 'max:100'],
            'checkout.subtitle' => ['nullable', 'string', 'max:255'],
            'checkout.show_branding' => ['nullable', 'boolean'],
            'checkout.sections' => ['nullable', 'array'],
            'checkout.sections.address' => ['nullable', 'array'],
            'checkout.sections.address.visible' => ['nullable', 'boolean'],
            'checkout.sections.address.title' => ['nullable', 'string', 'max:100'],
            'checkout.sections.address.order' => ['nullable', 'integer', 'min:1', 'max:10'],
            'checkout.sections.address.desktop_visible' => ['nullable', 'boolean'],
            'checkout.sections.address.mobile_visible' => ['nullable', 'boolean'],
            'checkout.sections.delivery' => ['nullable', 'array'],
            'checkout.sections.delivery.visible' => ['nullable', 'boolean'],
            'checkout.sections.delivery.title' => ['nullable', 'string', 'max:100'],
            'checkout.sections.delivery.order' => ['nullable', 'integer', 'min:1', 'max:10'],
            'checkout.sections.delivery.desktop_visible' => ['nullable', 'boolean'],
            'checkout.sections.delivery.mobile_visible' => ['nullable', 'boolean'],
            'checkout.sections.payment' => ['nullable', 'array'],
            'checkout.sections.payment.visible' => ['nullable', 'boolean'],
            'checkout.sections.payment.title' => ['nullable', 'string', 'max:100'],
            'checkout.sections.payment.order' => ['nullable', 'integer', 'min:1', 'max:10'],
            'checkout.sections.payment.desktop_visible' => ['nullable', 'boolean'],
            'checkout.sections.payment.mobile_visible' => ['nullable', 'boolean'],
            'checkout.button_labels' => ['nullable', 'array'],
            'checkout.button_labels.continue_to_delivery' => ['nullable', 'string', 'max:50'],
            'checkout.button_labels.continue_to_payment' => ['nullable', 'string', 'max:50'],
            'checkout.button_labels.place_order' => ['nullable', 'string', 'max:50'],
            'checkout.appearance' => ['nullable', 'array'],
            'checkout.appearance.card_style' => ['nullable', 'in:bordered,raised,flat,soft'],
            'checkout.appearance.section_spacing' => ['nullable', 'in:compact,normal,relaxed'],
            'checkout.appearance.compact_mode' => ['nullable', 'boolean'],
            'checkout.layout' => ['nullable', 'array'],
            'checkout.layout.order_summary_position' => ['nullable', 'in:left,right'],
            'checkout.layout.show_order_summary_on_mobile' => ['nullable', 'boolean'],
        ];
    }
}