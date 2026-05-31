<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:255',
            'sku'         => [
                'nullable', 'string', 'max:100',
                Rule::unique('products', 'sku')->where('tenant_id', tenant()?->id),
            ],
            'description' => 'nullable|string',
            'price'       => 'required|numeric|min:0',
            'base_price'  => 'required|numeric|min:0',
            'stock'       => 'nullable|integer|min:0',
            'category_id' => 'required|exists:categories,id',
            'status'      => 'required|in:active,inactive,draft',
            'type'        => 'nullable|in:single,variable,combo',
            'variants'    => 'nullable|json',
            'combo_items' => 'nullable|json',
            'photo1'      => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'photo2'      => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ];
    }
}
