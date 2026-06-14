<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $product = $this->route('product');
        $type = $this->input('type', $product?->type ?? 'single');

        $rules = [
            'name'            => 'required|string|max:255',
            'slug'            => 'nullable|string|max:255',
            'sku'             => [
                'nullable', 'string', 'max:100',
                Rule::unique('products', 'sku')->where('tenant_id', tenant()?->id)->ignore($product?->id),
            ],
            'barcode'         => 'nullable|string|max:100',
            'short_description' => 'nullable|string|max:500',
            'description'     => 'nullable|string',
            'cost_price'      => 'nullable|numeric|min:0',
            'stock'           => 'nullable|integer|min:0',
            'low_stock_alert' => 'nullable|integer|min:0',
            'category_id'     => 'required|exists:categories,id',
            'brand_id'        => 'nullable|exists:brands,id',
            'unit_id'         => 'nullable|exists:units,id',
            'status'          => 'required|in:active,inactive,draft',
            'type'            => 'nullable|in:single,variable,combo',
            'variants'        => 'nullable|json',
            'combo_items'     => 'nullable|json',
            'photo1'          => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'photo2'          => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'gallery_images'  => 'nullable|array|max:10',
            'gallery_images.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'seo_title'       => 'nullable|string|max:255',
            'seo_description' => 'nullable|string|max:500',
            'seo_keywords'    => 'nullable|string|max:500',
            'seo_image'       => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'variant_images'  => 'nullable|array',
            'variant_images.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ];

        if ($type === 'variable' || $type === 'combo') {
            $rules['price'] = 'nullable|numeric|min:0';
            $rules['base_price'] = 'nullable|numeric|min:0';
        } else {
            $rules['price'] = 'required|numeric|min:0';
            $rules['base_price'] = 'required|numeric|min:0';
        }

        if ($type === 'variable') {
            $rules['variants'] = 'required|json';
        }

        return $rules;
    }

    public function after(): array
    {
        $product = $this->route('product');
        $type = $this->input('type', $product?->type ?? 'single');

        return [
            function ($validator) use ($type) {
                if ($type !== 'variable') {
                    return;
                }

                $variants = json_decode($this->input('variants', '[]'), true);
                if (!is_array($variants)) {
                    $validator->errors()->add('variants', 'Variants data is invalid.');
                    return;
                }

                if (empty($variants)) {
                    $validator->errors()->add('variants', 'At least one variant is required for variable products.');
                    return;
                }

                foreach ($variants as $index => $variant) {
                    if (!isset($variant['price']) || !is_numeric($variant['price']) || (float) $variant['price'] < 0) {
                        $validator->errors()->add("variants.{$index}.price", 'Each variant must have a valid price (numeric, min: 0).');
                    }
                    if (!isset($variant['stock']) || !is_numeric($variant['stock']) || (int) $variant['stock'] < 0) {
                        $validator->errors()->add("variants.{$index}.stock", 'Each variant must have a valid stock (integer, min: 0).');
                    }
                }
            },
        ];
    }
}
