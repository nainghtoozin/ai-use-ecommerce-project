<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStorefrontNavigationRequest extends FormRequest
{
    private const ALLOWED_PATHS = [
        '/',
        '/products',
        '/brands',
        '/contact',
        '/faq',
        '/about',
        '/customer/orders',
        '/customer/account',
        '/privacy-policy',
        '/terms-and-conditions',
        '/shipping-policy',
        '/return-policy',
        '/refund-policy',
    ];

    public function authorize(): bool
    {
        return $this->user()?->can('settings.website') ?? false;
    }

    public function rules(): array
    {
        return [
            'show_store_name' => ['required', 'boolean'],
            'show_search' => ['required', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.key' => ['required', 'string', 'max:50'],
            'items.*.label' => ['required', 'string', 'max:100'],
            'items.*.path' => ['required', Rule::in(self::ALLOWED_PATHS)],
            'items.*.enabled' => ['required', 'boolean'],
            'items.*.position' => ['required', 'integer', 'min:0'],
            'items.*.group' => ['sometimes', 'string', Rule::in(['header', 'footer'])],
            'footer_items' => ['sometimes', 'array'],
            'footer_items.*.id' => ['required_with:footer_items', 'integer'],
            'footer_items.*.key' => ['required_with:footer_items', 'string', 'max:50'],
            'footer_items.*.label' => ['required_with:footer_items', 'string', 'max:100'],
            'footer_items.*.path' => ['required_with:footer_items', Rule::in(self::ALLOWED_PATHS)],
            'footer_items.*.enabled' => ['required_with:footer_items', 'boolean'],
            'footer_items.*.position' => ['required_with:footer_items', 'integer', 'min:0'],
        ];
    }

    public static function allowedPaths(): array
    {
        return self::ALLOWED_PATHS;
    }
}
