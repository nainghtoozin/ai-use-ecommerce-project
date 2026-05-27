<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;

/**
 * SkuService — handles SKU generation and validation.
 *
 * SKU format conventions:
 * - Single products: PRD-{id} (e.g. PRD-1001)
 * - Variable products: PRD-{id} (e.g. PRD-1050)
 * - Variant SKUs: VAR-{id} (e.g. VAR-2034)
 * - Combo products: CMB-{id} (e.g. CMB-3010)
 *
 * SKUs are auto-generated when the user leaves the SKU field empty.
 * Manual SKU input is always respected (override behavior).
 *
 * Designed to be scalable for future:
 * - Multi-warehouse SKU prefixes
 * - Barcode/UPC generation
 * - SaaS merchant ID integration
 * - POS system compatibility
 */
class SkuService
{
    /**
     * Prefixes for different product types.
     *
     * Future extensibility: add warehouse prefixes, merchant prefixes, etc.
     */
    const PREFIX_PRODUCT = 'PRD';
    const PREFIX_VARIANT = 'VAR';
    const PREFIX_COMBO = 'CMB';

    /**
     * Generate a SKU for a product.
     *
     * If the product already has a SKU, returns it unchanged.
     * Otherwise generates a new unique SKU based on type and ID.
     *
     * @param Product $product
     * @return string|null Generated SKU or null if product has no ID yet
     */
    public function generateProductSku(Product $product): ?string
    {
        if ($product->sku) {
            return $product->sku;
        }

        if (!$product->id) {
            return null;
        }

        $prefix = $product->isCombo()
            ? self::PREFIX_COMBO
            : self::PREFIX_PRODUCT;

        $sku = "{$prefix}-{$product->id}";

        // Ensure uniqueness — append suffix if collision exists
        $existing = Product::where('sku', $sku)
            ->where('id', '!=', $product->id)
            ->first();

        if ($existing) {
            $sku = "{$prefix}-{$product->id}-" . strtoupper(substr(md5(uniqid()), 0, 4));
        }

        return $sku;
    }

    /**
     * Generate a SKU for a variant.
     *
     * If the variant already has a SKU, returns it unchanged.
     * Otherwise generates a new unique SKU.
     *
     * @param ProductVariant $variant
     * @return string|null Generated SKU or null if variant has no ID yet
     */
    public function generateVariantSku(ProductVariant $variant): ?string
    {
        if ($variant->sku) {
            return $variant->sku;
        }

        if (!$variant->id) {
            return null;
        }

        $sku = self::PREFIX_VARIANT . "-{$variant->id}";

        // Ensure uniqueness — append suffix if collision exists
        $existing = ProductVariant::where('sku', $sku)
            ->where('id', '!=', $variant->id)
            ->first();

        if ($existing) {
            $sku = self::PREFIX_VARIANT . "-{$variant->id}-" . strtoupper(substr(md5(uniqid()), 0, 4));
        }

        return $sku;
    }

    /**
     * Validate that a SKU is unique across products.
     *
     * @param string $sku
     * @param int|null $exceptProductId Exclude this product from the check
     * @return bool
     */
    public function isProductSkuUnique(string $sku, ?int $exceptProductId = null): bool
    {
        $query = Product::where('sku', $sku);

        if ($exceptProductId) {
            $query->where('id', '!=', $exceptProductId);
        }

        return !$query->exists();
    }

    /**
     * Validate that a SKU is unique across variants.
     *
     * @param string $sku
     * @param int|null $exceptVariantId Exclude this variant from the check
     * @return bool
     */
    public function isVariantSkuUnique(string $sku, ?int $exceptVariantId = null): bool
    {
        $query = ProductVariant::where('sku', $sku);

        if ($exceptVariantId) {
            $query->where('id', '!=', $exceptVariantId);
        }

        return !$query->exists();
    }

    /**
     * Determine the next available product ID for SKU pre-generation.
     *
     * Useful for showing the user what SKU will be generated before save.
     *
     * @return int
     */
    public function getNextProductId(): int
    {
        $maxId = Product::max('id') ?? 0;
        return $maxId + 1;
    }

    /**
     * Determine the next available variant ID for SKU pre-generation.
     *
     * @return int
     */
    public function getNextVariantId(): int
    {
        $maxId = ProductVariant::max('id') ?? 0;
        return $maxId + 1;
    }
}
