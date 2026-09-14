<?php

namespace App\Services;

use App\Models\FlashSale;
use App\Models\FlashSaleProduct;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FlashSaleService
{
    /**
     * Cache key prefix for flash sale data.
     */
    private const CACHE_PREFIX = 'flash_sale:';
    private const CACHE_TTL = 300; // 5 minutes

    /**
     * Get the currently active flash sale for a specific product and optional variant.
     * Returns null if no flash sale applies.
     */
    public function getActiveFlashSale(int $productId, ?int $variantId = null): ?array
    {
        $flashSaleProduct = FlashSaleProduct::where('product_id', $productId)
            ->when($variantId, fn($q) => $q->where('variant_id', $variantId), fn($q) => $q->whereNull('variant_id'))
            ->whereHas('flashSale', function ($q) {
                $q->where('is_active', true)
                    ->where(function ($q) {
                        $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
                    })
                    ->where(function ($q) {
                        $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
                    });
            })
            ->with('flashSale')
            ->first();

        if (!$flashSaleProduct || !$flashSaleProduct->flashSale) {
            return null;
        }

        $flashSale = $flashSaleProduct->flashSale;

        if ($flashSale->hasReachedUsageLimit()) {
            return null;
        }

        if ($flashSaleProduct->hasStock() === false) {
            return null;
        }

        return [
            'id' => $flashSale->id,
            'name' => $flashSale->name,
            'flash_price' => (float) $flashSaleProduct->flash_price,
            'discount_type' => $flashSale->discount_type,
            'discount_value' => (float) $flashSale->discount_value,
            'max_discount_amount' => $flashSale->max_discount_amount ? (float) $flashSale->max_discount_amount : null,
            'starts_at' => $flashSale->starts_at?->toISOString(),
            'ends_at' => $flashSale->ends_at?->toISOString(),
            'remaining_stock' => $flashSaleProduct->remainingStock(),
            'priority' => $flashSale->priority,
        ];
    }

    /**
     * Batch-resolve flash sale data for multiple products.
     * Returns an array keyed by product_id => ['simple' => [...], 'variants' => [variant_id => [...]]]
     */
    public function getFlashSalesForProducts(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $now = now();

        $flashSaleProducts = FlashSaleProduct::whereIn('product_id', $productIds)
            ->whereHas('flashSale', function ($q) use ($now) {
                $q->where('is_active', true)
                    ->where(function ($q) use ($now) {
                        $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
                    })
                    ->where(function ($q) use ($now) {
                        $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
                    });
            })
            ->with('flashSale')
            ->get();

        $result = [];

        foreach ($flashSaleProducts as $fsp) {
            $flashSale = $fsp->flashSale;
            if (!$flashSale || $flashSale->hasReachedUsageLimit()) {
                continue;
            }
            if ($fsp->hasStock() === false) {
                continue;
            }

            $data = [
                'id' => $flashSale->id,
                'name' => $flashSale->name,
                'flash_price' => (float) $fsp->flash_price,
                'discount_type' => $flashSale->discount_type,
                'discount_value' => (float) $flashSale->discount_value,
                'max_discount_amount' => $flashSale->max_discount_amount ? (float) $flashSale->max_discount_amount : null,
                'starts_at' => $flashSale->starts_at?->toISOString(),
                'ends_at' => $flashSale->ends_at?->toISOString(),
                'remaining_stock' => $fsp->remainingStock(),
                'priority' => $flashSale->priority,
            ];

            $productId = $fsp->product_id;

            if (!isset($result[$productId])) {
                $result[$productId] = [
                    'simple' => null,
                    'variants' => [],
                ];
            }

            if ($fsp->variant_id) {
                $result[$productId]['variants'][$fsp->variant_id] = $data;
            } else {
                $result[$productId]['simple'] = $data;
            }
        }

        return $result;
    }

    /**
     * Enrich a product model with flash sale data for storefront display.
     * Does NOT modify the original price in the database.
     */
    public function enrichProductWithFlashSale(Product $product, ?array $flashSaleData = null): Product
    {
        $product->is_flash_sale = false;
        $product->flash_sale_price = null;
        $product->flash_sale_original_price = null;
        $product->flash_sale_discount = null;
        $product->flash_sale_discount_percentage = null;
        $product->flash_sale_ends_at = null;
        $product->flash_sale_remaining_stock = null;
        $product->flash_sale_name = null;

        if ($flashSaleData && isset($flashSaleData['simple'])) {
            $fs = $flashSaleData['simple'];
            $originalPrice = (float) $product->getEffectivePrice();

            $product->is_flash_sale = true;
            $product->flash_sale_price = $fs['flash_price'];
            $product->flash_sale_original_price = $originalPrice;
            $product->flash_sale_discount = round($originalPrice - $fs['flash_price'], 2);
            $product->flash_sale_discount_percentage = $originalPrice > 0
                ? round((($originalPrice - $fs['flash_price']) / $originalPrice) * 100)
                : 0;
            $product->flash_sale_ends_at = $fs['ends_at'];
            $product->flash_sale_remaining_stock = $fs['remaining_stock'];
            $product->flash_sale_name = $fs['name'];
        }

        if ($product->is_variable && isset($flashSaleData['variants'])) {
            $product->flash_sale_variants = $flashSaleData['variants'];
        } else {
            $product->flash_sale_variants = [];
        }

        return $product;
    }

    /**
     * Get flash sale price for a specific variant.
     */
    public function getVariantFlashSalePrice(int $productId, int $variantId, array $flashSaleVariants): ?array
    {
        return $flashSaleVariants[$variantId] ?? null;
    }

    /**
     * Calculate the display price for a product considering flash sales.
     * Returns [price, original_price, is_flash_sale, flash_sale_data].
     */
    public function resolveDisplayPrice(Product $product, ?ProductVariant $variant = null, ?array $flashSaleData = null): array
    {
        $originalPrice = $variant
            ? (float) $variant->getEffectivePrice()
            : (float) $product->getEffectivePrice();

        if (!$flashSaleData) {
            return [
                'price' => $originalPrice,
                'original_price' => $originalPrice,
                'is_flash_sale' => false,
                'flash_sale_data' => null,
            ];
        }

        $fs = null;
        if ($variant && isset($flashSaleData['variants'][$variant->id])) {
            $fs = $flashSaleData['variants'][$variant->id];
        } elseif (!$variant && isset($flashSaleData['simple'])) {
            $fs = $flashSaleData['simple'];
        }

        if (!$fs) {
            return [
                'price' => $originalPrice,
                'original_price' => $originalPrice,
                'is_flash_sale' => false,
                'flash_sale_data' => null,
            ];
        }

        return [
            'price' => $fs['flash_price'],
            'original_price' => $originalPrice,
            'is_flash_sale' => true,
            'flash_sale_data' => $fs,
        ];
    }

    /**
     * Resolve the effective price for a cart/order item.
     * Returns array with price, original_price, flash_sale_id, and display data.
     * Always resolves server-side — never trusts browser-sent prices.
     */
    public function resolveEffectivePrice(int $productId, ?int $variantId, float $basePrice): array
    {
        $flashSale = $this->getActiveFlashSale($productId, $variantId);

        if (!$flashSale) {
            return [
                'price' => $basePrice,
                'original_price' => $basePrice,
                'flash_sale_id' => null,
                'is_flash_sale' => false,
                'flash_sale_name' => null,
                'flash_sale_ends_at' => null,
                'flash_sale_remaining_stock' => null,
            ];
        }

        return [
            'price' => $flashSale['flash_price'],
            'original_price' => $basePrice,
            'flash_sale_id' => $flashSale['id'],
            'is_flash_sale' => true,
            'flash_sale_name' => $flashSale['name'],
            'flash_sale_ends_at' => $flashSale['ends_at'],
            'flash_sale_remaining_stock' => $flashSale['remaining_stock'],
        ];
    }

    /**
     * Batch-resolve effective prices for cart items.
     * Returns array keyed by "p{product_id}_v{variant_id}" with resolved price data.
     */
    public function resolveCartPrices(array $cartItems): array
    {
        $productIds = array_unique(array_column($cartItems, 'product_id'));
        $flashSaleData = $this->getFlashSalesForProducts($productIds);

        $resolved = [];
        foreach ($cartItems as $cartKey => $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $variantId = !empty($item['variant_id']) ? (int) $item['variant_id'] : null;
            $basePrice = (float) ($item['price'] ?? 0);

            $itemFlashSale = $flashSaleData[$productId] ?? null;
            $fs = null;
            if ($variantId && isset($itemFlashSale['variants'][$variantId])) {
                $fs = $itemFlashSale['variants'][$variantId];
            } elseif (!$variantId && isset($itemFlashSale['simple'])) {
                $fs = $itemFlashSale['simple'];
            }

            if ($fs) {
                $resolved[$cartKey] = [
                    'price' => $fs['flash_price'],
                    'original_price' => $basePrice,
                    'flash_sale_id' => $fs['id'],
                    'is_flash_sale' => true,
                    'flash_sale_name' => $fs['name'],
                    'flash_sale_ends_at' => $fs['ends_at'],
                    'flash_sale_remaining_stock' => $fs['remaining_stock'],
                    'flash_sale_discount_percentage' => $basePrice > 0
                        ? round((($basePrice - $fs['flash_price']) / $basePrice) * 100)
                        : 0,
                ];
            } else {
                $resolved[$cartKey] = [
                    'price' => $basePrice,
                    'original_price' => $basePrice,
                    'flash_sale_id' => null,
                    'is_flash_sale' => false,
                    'flash_sale_name' => null,
                    'flash_sale_ends_at' => null,
                    'flash_sale_remaining_stock' => null,
                    'flash_sale_discount_percentage' => null,
                ];
            }
        }

        return $resolved;
    }

    /**
     * Validate flash sale quantity limits for cart items WITH row-level locking.
     * MUST be called inside a DB::transaction() to hold locks until commit/rollback.
     * Returns array of error messages (empty if valid).
     */
    public function validateFlashSaleQuantity(array $items): array
    {
        $errors = [];
        $flashSaleIds = array_filter(array_column($items, 'flash_sale_id'));

        if (empty($flashSaleIds)) {
            return $errors;
        }

        $flashSaleProducts = FlashSaleProduct::whereIn('flash_sale_id', $flashSaleIds)
            ->whereHas('flashSale', function (Builder $q) {
                $q->where('tenant_id', tenantId());
            })
            ->lockForUpdate()
            ->with('flashSale')
            ->get()
            ->keyBy(fn($fsp) => $fsp->flash_sale_id . '_' . $fsp->product_id . '_' . ($fsp->variant_id ?? '0'));

        foreach ($items as $item) {
            $flashSaleId = $item['flash_sale_id'] ?? null;
            if (!$flashSaleId) {
                continue;
            }

            $key = $flashSaleId . '_' . $item['product_id'] . '_' . ($item['variant_id'] ?? '0');
            $fsp = $flashSaleProducts->get($key);

            if (!$fsp) {
                $errors[] = 'A flash sale product in your cart is no longer available at the sale price.';
                continue;
            }

            if (!$fsp->flashSale || !$fsp->flashSale->isCurrentlyActive()) {
                $errors[] = 'A flash sale in your cart has expired. Prices have been updated.';
                continue;
            }

            if ($fsp->hasStock() === false) {
                $errors[] = "The flash sale for a product in your cart has sold out.";
                continue;
            }

            $remaining = $fsp->remainingStock();
            if ($remaining !== null && $item['quantity'] > $remaining) {
                $errors[] = "Only {$remaining} items left in the flash sale for a product in your cart.";
            }
        }

        return $errors;
    }

    /**
     * Increment quantity_sold for flash sale items after order creation.
     * MUST be called inside the same DB::transaction() that holds the lockFrom validateFlashSaleQuantity().
     * Rows are already locked by the validation query — no additional lock needed.
     */
    public function incrementQuantitySold(array $orderItems): void
    {
        foreach ($orderItems as $item) {
            $flashSaleId = $item['flash_sale_id'] ?? null;
            if (!$flashSaleId) {
                continue;
            }

            FlashSaleProduct::where('flash_sale_id', $flashSaleId)
                ->where('product_id', $item['product_id'])
                ->where('variant_id', $item['variant_id'] ?? null)
                ->increment('quantity_sold', $item['quantity']);
        }
    }

    /**
     * Decrement quantity_sold for flash sale items on order cancellation.
     * Prevents quantity_sold from going below zero via WHERE guard.
     */
    public function decrementQuantitySold(array $orderItems): void
    {
        foreach ($orderItems as $item) {
            $flashSaleId = $item['flash_sale_id'] ?? null;
            if (!$flashSaleId) {
                continue;
            }

            FlashSaleProduct::where('flash_sale_id', $flashSaleId)
                ->where('product_id', $item['product_id'])
                ->where('variant_id', $item['variant_id'] ?? null)
                ->where('quantity_sold', '>=', $item['quantity'])
                ->decrement('quantity_sold', $item['quantity']);
        }
    }
}
