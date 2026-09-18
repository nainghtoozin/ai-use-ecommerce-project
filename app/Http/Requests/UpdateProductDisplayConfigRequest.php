<?php

namespace App\Http\Requests;

use App\Models\StorefrontProductDisplayConfig;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductDisplayConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.website') ?? false;
    }

    public function rules(): array
    {
        $boolean = ['nullable', 'boolean'];

        return [
            'product_info' => ['nullable', 'array'],
            'product_info.show_category' => $boolean,
            'product_info.show_brand' => $boolean,
            'product_info.show_product_type' => $boolean,
            'product_info.show_sku' => $boolean,
            'pricing' => ['nullable', 'array'],
            'pricing.show_original_price' => $boolean,
            'pricing.show_savings' => $boolean,
            'pricing.show_discount_percentage' => $boolean,
            'stock' => ['nullable', 'array'],
            'stock.display_mode' => ['nullable', 'string', 'in:' . implode(',', StorefrontProductDisplayConfig::stockModes())],
            'stock.low_stock_threshold' => ['nullable', 'integer', 'min:' . StorefrontProductDisplayConfig::LOW_STOCK_THRESHOLD_MIN, 'max:' . StorefrontProductDisplayConfig::LOW_STOCK_THRESHOLD_MAX],
            'stock.show_out_of_stock' => $boolean,
            'actions' => ['nullable', 'array'],
            'actions.show_add_to_cart' => $boolean,
            'actions.show_buy_now' => $boolean,
            'actions.show_view_product' => $boolean,
            'actions.show_wishlist' => $boolean,
        ];
    }

    public function validatedConfiguration(): array
    {
        $validated = $this->validated();

        $configuration = array_replace_recursive(
            StorefrontProductDisplayConfig::getDefaults(),
            array_filter($validated, fn ($group) => is_array($group))
        );

        $configuration['pricing']['show_current_price'] = true;

        return $configuration;
    }
}
