<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStorefrontNavigationRequest extends FormRequest
{
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
            'items.*.key' => ['required', Rule::in(['home', 'products', 'contact', 'orders'])],
            'items.*.label' => ['required', 'string', 'max:100'],
            'items.*.path' => ['required', Rule::in(['/', '/products', '/contact', '/customer/orders'])],
            'items.*.enabled' => ['required', 'boolean'],
            'items.*.position' => ['required', 'integer', 'min:0'],
        ];
    }
}
