<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;

class StorefrontProductDisplayConfig extends Model
{
    use TenantAware;

    protected $fillable = ['tenant_id', 'storefront_id', 'configuration'];

    protected $casts = ['configuration' => 'array'];

    public const STOCK_MODE_STATUS = 'status';
    public const STOCK_MODE_QUANTITY = 'quantity';
    public const STOCK_MODE_STATUS_QUANTITY = 'status_quantity';
    public const STOCK_MODE_HIDDEN = 'hidden';

    public const LOW_STOCK_THRESHOLD_MIN = 0;
    public const LOW_STOCK_THRESHOLD_MAX = 1000;

    public static function stockModes(): array
    {
        return [
            self::STOCK_MODE_STATUS,
            self::STOCK_MODE_QUANTITY,
            self::STOCK_MODE_STATUS_QUANTITY,
            self::STOCK_MODE_HIDDEN,
        ];
    }

    public function storefront()
    {
        return $this->belongsTo(Storefront::class);
    }

    /**
     * Merge stored configuration over defaults and sanitize known keys so
     * old revisions, partial payloads, or corrupt values can never break
     * rendering. Unknown keys are preserved for forward compatibility.
     *
     * Stock contract:
     * - status: availability badge only (In Stock / Low Stock / Out of Stock)
     * - quantity: numeric units only
     * - status_quantity: badge plus numeric units
     * - hidden: no stock display at all
     * - show_out_of_stock=false hides the badge for zero-stock items
     * - low_stock_threshold: units at or below which stocked items read "low"
     */
    public static function resolveConfiguration(mixed $stored): array
    {
        $defaults = self::getDefaults();
        $merged = $stored !== null
            ? array_replace_recursive($defaults, array_filter((array) $stored, fn ($group) => is_array($group)))
            : $defaults;

        foreach (['product_info' => ['show_category', 'show_brand', 'show_product_type', 'show_sku'], 'pricing' => ['show_original_price', 'show_savings', 'show_discount_percentage'], 'actions' => ['show_add_to_cart', 'show_buy_now', 'show_view_product', 'show_wishlist']] as $group => $keys) {
            foreach ($keys as $key) {
                $merged[$group][$key] = self::toBool($merged[$group][$key] ?? null, $defaults[$group][$key]);
            }
        }

        $merged['pricing']['show_current_price'] = true;

        $mode = $merged['stock']['display_mode'] ?? null;
        $merged['stock']['display_mode'] = in_array($mode, self::stockModes(), true) ? $mode : self::STOCK_MODE_STATUS;

        $threshold = $merged['stock']['low_stock_threshold'] ?? null;
        $threshold = is_numeric($threshold) ? (int) $threshold : $defaults['stock']['low_stock_threshold'];
        $merged['stock']['low_stock_threshold'] = max(self::LOW_STOCK_THRESHOLD_MIN, min(self::LOW_STOCK_THRESHOLD_MAX, $threshold));

        $merged['stock']['show_out_of_stock'] = self::toBool($merged['stock']['show_out_of_stock'] ?? null, $defaults['stock']['show_out_of_stock']);

        return $merged;
    }

    private static function toBool(mixed $value, bool $fallback): bool
    {
        if ($value === null) {
            return $fallback;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $parsed ?? $fallback;
    }

    public static function getDefaults(): array
    {
        return [
            'product_info' => [
                'show_category' => true,
                'show_brand' => true,
                'show_product_type' => true,
                'show_sku' => true,
            ],
            'pricing' => [
                'show_current_price' => true,
                'show_original_price' => true,
                'show_savings' => true,
                'show_discount_percentage' => true,
            ],
            'stock' => [
                'display_mode' => self::STOCK_MODE_STATUS,
                'low_stock_threshold' => 10,
                'show_out_of_stock' => true,
            ],
            'actions' => [
                'show_add_to_cart' => true,
                'show_buy_now' => true,
                'show_view_product' => true,
                'show_wishlist' => true,
            ],
        ];
    }
}
