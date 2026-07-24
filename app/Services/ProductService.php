<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductCombo;
use App\Enums\ProductType;

/**
 * ProductService — central service for product-related business logic.
 *
 * This service provides an abstraction layer between controllers and the
 * Product model, making it easier to support multiple product types
 * (single, variable, combo) without scattering type-checking logic
 * throughout the codebase.
 *
 * Current scope:
 * - Product type detection and validation
 * - Price resolution (single + variable + combo products)
 * - Stock resolution (single + variable + combo products)
 * - Variant lookup and creation helpers
 * - Combo/bundle management helpers
 *
 * Future scope (TODO):
 * - Multi-warehouse stock management
 * - SaaS feature-gating per product type
 * - Promotional bundles / buy-together discounts
 */
class ProductService
{
    public function __construct(
        private readonly SkuService $skuService,
    ) {}

    /**
     * Validate that a product type is supported.
     *
     * @throws \InvalidArgumentException if the type is not valid
     */
    public function validateType(string $type): void
    {
        if (!ProductType::isValid($type)) {
            throw new \InvalidArgumentException(
                "Invalid product type: {$type}. Supported: " . implode(', ', ProductType::all())
            );
        }

        // SaaS feature-gating: check if the current plan supports this type
        if (!ProductType::isAvailable($type)) {
            throw new \InvalidArgumentException(
                ProductType::label($type) . ' is not available on your current plan.'
            );
        }
    }

    /**
     * Resolve the effective selling price for a product.
     *
     * For single products: returns the product's price field.
     * For variable products: returns the parent product's base price.
     *   Use resolveVariantPrice() for per-variant pricing.
     * For combo products: returns the combo's set price.
     *   Use resolveComboBasePrice() for sum of individual items.
     *
     * @param Product $product
     * @param array $options Reserved for future: variant_id, quantity, etc.
     * @return float
     */
    public function getPrice(Product $product, array $options = []): float
    {
        if ($product->isVariable()) {
            return $product->getEffectivePrice();
        }

        if ($product->isCombo()) {
            return $product->getEffectivePrice();
        }

        return (float) ($product->price ?? 0);
    }

    /**
     * Resolve the price for a specific variant.
     *
     * Falls back to the parent product price if the variant has no price set.
     *
     * @param ProductVariant $variant
     * @return float
     */
    public function resolveVariantPrice(ProductVariant $variant): float
    {
        return $variant->getEffectivePrice();
    }

    /**
     * Resolve the effective stock quantity for a product.
     *
     * For single products: returns the product's stock field.
     * For variable products: sums stock across all active variants.
     * For combo products: returns minimum possible combos based on
     *   component stock-to-quantity ratios.
     *
     * @param Product $product
     * @return int
     */
    public function getStock(Product $product): int
    {
        return $product->getEffectiveStock();
    }

    /**
     * Resolve the base price of a combo (sum of individual items).
     *
     * This is the price before any bundle discount is applied.
     * Useful for calculating savings: basePrice - comboPrice = savings.
     *
     * @param Product $product
     * @return float
     */
    public function resolveComboBasePrice(Product $product): float
    {
        if (!$product->isCombo()) {
            return 0;
        }

        return $product->getComboBasePrice();
    }

    /**
     * Resolve the stock for a specific variant.
     *
     * @param ProductVariant $variant
     * @return int
     */
    public function resolveVariantStock(ProductVariant $variant): int
    {
        return (int) ($variant->stock ?? 0);
    }

    /**
     * Resolve a purchasable item from a product (and optional variant).
     *
     * Returns a unified array with:
     *   - product_id, product
     *   - variant_id, variant (nullable)
     *   - price (resolved from variant if applicable)
     *   - stock (resolved from variant if applicable)
     *   - name, photo1 (from product)
     *
     * @param int $productId
     * @param int|null $variantId
     * @return array
     * @throws \InvalidArgumentException if product not found, type mismatch, or insufficient stock
     */
    public function resolvePurchasable(int $productId, ?int $variantId = null): array
    {
        $product = Product::select(['id', 'name', 'price', 'stock', 'type', 'photo1'])->findOrFail($productId);

        if ($product->isVariable()) {
            if (!$variantId) {
                throw new \InvalidArgumentException(
                    "Variant selection required for variable product: {$product->name}"
                );
            }

            $variant = $product->variants()->active()->find($variantId);
            if (!$variant) {
                throw new \InvalidArgumentException(
                    "Invalid or inactive variant for product: {$product->name}"
                );
            }

            return [
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'product' => $product,
                'variant' => $variant,
                'price' => $this->resolveVariantPrice($variant),
                'stock' => $this->resolveVariantStock($variant),
                'name' => $product->name,
                'photo1' => $product->photo1,
            ];
        }

        // Single product: variant_id must be null
        if ($product->isCombo()) {
            return [
                'product_id' => $product->id,
                'variant_id' => null,
                'product' => $product,
                'variant' => null,
                'price' => (float) ($product->price ?? 0),
                'stock' => $product->comboStock(),
                'name' => $product->name,
                'photo1' => $product->photo1,
            ];
        }

        return [
            'product_id' => $product->id,
            'variant_id' => null,
            'product' => $product,
            'variant' => null,
            'price' => (float) ($product->price ?? 0),
            'stock' => (int) ($product->stock ?? 0),
            'name' => $product->name,
            'photo1' => $product->photo1,
        ];
    }

    /**
     * Check if a product has sufficient stock for a given quantity.
     *
     * @param Product $product
     * @param int $quantity
     * @return bool
     */
    public function hasStock(Product $product, int $quantity = 1): bool
    {
        return $this->getStock($product) >= $quantity;
    }

    /**
     * Determine if a product type supports variants.
     */
    public function supportsVariants(string $type): bool
    {
        return ProductType::supportsVariants($type);
    }

    /**
     * Determine if a product type is a bundle.
     */
    public function isBundle(string $type): bool
    {
        return ProductType::isBundle($type);
    }

    /**
     * Get default form data for a given product type.
     */
    public function getDefaultFormData(string $type): array
    {
        $defaults = [
            'type' => $type,
            'name' => '',
            'description' => '',
            'price' => '',
            'base_price' => '',
            'stock' => 0,
            'status' => Product::STATUS_ACTIVE,
        ];

        // Variable-specific defaults
        if ($type === ProductType::VARIABLE) {
            $defaults['variants'] = [];
            $defaults['variant_options'] = [];
        }

        // Combo-specific defaults
        if ($type === ProductType::COMBO) {
            $defaults['combo_items'] = [];
        }

        return $defaults;
    }

    /**
     * Sanitize product data before persistence.
     *
     * Removes fields that don't apply to the given product type.
     *
     * @param array $data
     * @param string $type
     * @return array
     */
    public function sanitizeData(array $data, string $type): array
    {
        $allowed = [
            'name', 'slug', 'description', 'short_description',
            'price', 'base_price', 'cost_price', 'stock',
            'category_id', 'brand_id', 'unit_id', 'status', 'type',
            'photo1', 'photo2', 'gallery_images', 'sku', 'barcode', 'low_stock_alert',
            'seo_title', 'seo_description', 'seo_keywords', 'seo_image',
            'warehouse_id',
        ];

        // Type-specific allowed fields
        switch ($type) {
            case ProductType::VARIABLE:
                $allowed = array_merge($allowed, ['variants', 'variant_options']);
                break;
            case ProductType::COMBO:
                $allowed = array_merge($allowed, ['combo_items']);
                break;
        }

        return array_intersect_key($data, array_flip($allowed));
    }

    /* ── Variant helpers ── */

    /**
     * Create a variant for a variable product.
     *
     * @param Product $product
     * @param array $variantData
     * @return ProductVariant
     */
    public function createVariant(Product $product, array $variantData): ProductVariant
    {
        if (!$product->isVariable()) {
            throw new \InvalidArgumentException(
                "Cannot create variant: product '{$product->name}' is not a variable product."
            );
        }

        return $product->variants()->create($variantData);
    }

    /**
     * Create multiple variants for a variable product.
     *
     * @param Product $product
     * @param array $variants Array of variant data arrays
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function createVariants(Product $product, array $variants)
    {
        if (!$product->isVariable()) {
            throw new \InvalidArgumentException(
                "Cannot create variants: product '{$product->name}' is not a variable product."
            );
        }

        return $product->variants()->createMany($variants);
    }

    /**
     * Delete all variants for a product.
     *
     * @param Product $product
     * @return int Number of deleted variants
     */
    public function deleteAllVariants(Product $product): int
    {
        return $product->variants()->delete();
    }

    /**
     * Sync variants for a product (create, update, delete).
     *
     * Used when saving a variable product with its variant data.
     *
     * @param Product $product
     * @param array $variants Expected format:
     *   [
     *     ['sku' => 'ABC-1', 'price' => 100, 'stock' => 10, 'attributes' => ['size' => 'S']],
     *     ['id' => 5, 'sku' => 'ABC-2', 'price' => 120, ...], // existing variant with id
     *   ]
     * @return void
     */
    public function syncVariants(Product $product, array $variants): void
    {
        if (!$product->isVariable()) {
            return;
        }

        $existingIds = $product->variants()->pluck('id')->toArray();
        $incomingIds = [];

        foreach ($variants as $variantData) {
            if (isset($variantData['id']) && in_array($variantData['id'], $existingIds)) {
                // Update existing variant
                $variant = $product->variants()->find($variantData['id']);
                $variant->update($variantData);
                $incomingIds[] = $variantData['id'];
            } else {
                // Check for duplicate attribute combination before creating
                $newAttrs = $variantData['attributes'] ?? [];
                $allVariants = $product->variants()->get();
                $duplicate = $allVariants->first(function ($v) use ($newAttrs) {
                    return json_encode($v->getAttribute('attributes')) === json_encode($newAttrs);
                });
                if ($duplicate) {
                    throw new \InvalidArgumentException(
                        "Duplicate variant combination already exists for product '{$product->name}'."
                    );
                }

                // Create new variant
                $variant = $this->createVariant($product, $variantData);

                // Auto-generate SKU if empty
                if (empty($variant->sku)) {
                    $generatedSku = $this->skuService->generateVariantSku($variant);
                    if ($generatedSku) {
                        $variant->update(['sku' => $generatedSku]);
                    }
                }

                $incomingIds[] = $variant->id;
            }
        }

        // Delete variants not in the incoming data
        $toDelete = array_diff($existingIds, $incomingIds);
        if (!empty($toDelete)) {
            $product->variants()->whereIn('id', $toDelete)->delete();
        }
    }

    /**
     * Get all unique attribute keys across a product's variants.
     *
     * Useful for building the variant option selector UI.
     *
     * Example return: ['size', 'color']
     *
     * @param Product $product
     * @return array
     */
    public function getVariantOptionKeys(Product $product): array
    {
        if (!$product->isVariable()) {
            return [];
        }

        $keys = [];
        foreach ($product->variants as $variant) {
            $attrs = $variant->getAttribute('attributes');
            if (is_array($attrs)) {
                $keys = array_merge($keys, array_keys($attrs));
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * Get all possible values for a specific option key.
     *
     * Example: $this->getOptionValues($product, 'option1') => ['XL', 'M', 'S']
     *
     * @param Product $product
     * @param string $key
     * @return array
     */
    public function getOptionValues(Product $product, string $key): array
    {
        if (!$product->isVariable()) {
            return [];
        }

        $values = [];
        foreach ($product->variants as $variant) {
            $attrs = $variant->getAttribute('attributes');
            if (isset($attrs[$key])) {
                $values[] = $attrs[$key];
            }
        }

        return array_values(array_unique($values));
    }

    /* ── Combo helpers ── */

    /**
     * Add a product to a combo.
     *
     * @param Product $comboProduct The combo (parent) product
     * @param Product $product The product to include in the combo
     * @param int $quantity How many of $product are needed per combo
     * @return ProductCombo
     */
    public function addToCombo(Product $comboProduct, Product $product, int $quantity = 1): ProductCombo
    {
        if (!$comboProduct->isCombo()) {
            throw new \InvalidArgumentException(
                "Cannot add combo item: product '{$comboProduct->name}' is not a combo product."
            );
        }

        if ($comboProduct->id === $product->id) {
            throw new \InvalidArgumentException(
                "A combo cannot include itself."
            );
        }

        return ProductCombo::firstOrCreate(
            [
                'product_id' => $comboProduct->id,
                'combo_product_id' => $product->id,
                'linked_variant_id' => null,
            ],
            ['quantity' => $quantity]
        );
    }

    /**
     * Add a specific variant to a combo.
     *
     * @param Product $comboProduct The combo (parent) product
     * @param Product $product The variable product containing the variant
     * @param ProductVariant $variant The specific variant to include
     * @param int $quantity How many of this variant are needed per combo
     * @return ProductCombo
     */
    public function addToComboWithVariant(Product $comboProduct, Product $product, ProductVariant $variant, int $quantity = 1): ProductCombo
    {
        if (!$comboProduct->isCombo()) {
            throw new \InvalidArgumentException(
                "Cannot add combo item: product '{$comboProduct->name}' is not a combo product."
            );
        }

        if ($comboProduct->id === $product->id) {
            throw new \InvalidArgumentException(
                "A combo cannot include itself."
            );
        }

        if ($variant->product_id !== $product->id) {
            throw new \InvalidArgumentException(
                "Variant does not belong to product: {$product->name}"
            );
        }

        return ProductCombo::firstOrCreate(
            [
                'product_id' => $comboProduct->id,
                'combo_product_id' => $product->id,
                'linked_variant_id' => $variant->id,
            ],
            ['quantity' => $quantity]
        );
    }

    /**
     * Sync combo items for a product (create, update, delete).
     *
     * Supports both product-level and variant-level linking.
     *
     * Expected format:
     *   [
     *     ['combo_product_id' => 10, 'quantity' => 1],                           // product-level
     *     ['combo_product_id' => 20, 'linked_variant_id' => 5, 'quantity' => 1], // variant-level
     *   ]
     *
     * @param Product $comboProduct The combo (parent) product
     * @param array $comboItems
     * @return void
     */
    public function syncComboItems(Product $comboProduct, array $comboItems): void
    {
        if (!$comboProduct->isCombo()) {
            return;
        }

        $existingKeys = $comboProduct->comboItems()
            ->get()
            ->map(fn($item) => "{$item->combo_product_id}:{$item->linked_variant_id}")
            ->toArray();

        $incomingKeys = [];

        foreach ($comboItems as $item) {
            $comboProductId = $item['combo_product_id'] ?? null;
            $linkedVariantId = isset($item['linked_variant_id']) ? (int) $item['linked_variant_id'] : null;
            $quantity = $item['quantity'] ?? 1;

            if (!$comboProductId) {
                continue;
            }

            // Prevent self-reference
            if ((int) $comboProductId === $comboProduct->id) {
                continue;
            }

            $comboItem = ProductCombo::firstOrCreate(
                [
                    'product_id' => $comboProduct->id,
                    'combo_product_id' => (int) $comboProductId,
                    'linked_variant_id' => $linkedVariantId,
                ],
                ['quantity' => (int) $quantity]
            );

            // Update quantity if item already existed
            if (in_array("{$comboProductId}:{$linkedVariantId}", $existingKeys)) {
                $comboItem->update(['quantity' => (int) $quantity]);
            }

            $incomingKeys[] = "{$comboProductId}:{$linkedVariantId}";
        }

        // Remove combo items not in the incoming data
        $comboProduct->comboItems()
            ->get()
            ->filter(fn($item) => !in_array("{$item->combo_product_id}:{$item->linked_variant_id}", $incomingKeys))
            ->each(fn($item) => $item->delete());
    }

    /**
     * Get combo stock for a product (derived from components).
     *
     * @param Product $comboProduct
     * @return int
     */
    public function getComboStock(Product $comboProduct): int
    {
        if (!$comboProduct->isCombo()) {
            return 0;
        }

        return $comboProduct->comboStock();
    }

    /**
     * Get combo items with full details.
     *
     * @param Product $comboProduct
     * @return array
     */
    public function getComboItems(Product $comboProduct): array
    {
        if (!$comboProduct->isCombo()) {
            return [];
        }

        return $comboProduct->getComboSummary();
    }

    /**
     * Get combo price details (base, selling, savings).
     *
     * @param Product $comboProduct
     * @return array
     */
    public function getComboPrice(Product $comboProduct): array
    {
        if (!$comboProduct->isCombo()) {
            return [
                'base_price' => 0,
                'combo_price' => 0,
                'savings' => 0,
                'savings_percentage' => 0,
            ];
        }

        $basePrice = $comboProduct->getComboBasePrice();
        $comboPrice = $comboProduct->getEffectivePrice();

        return [
            'base_price' => $basePrice,
            'combo_price' => $comboPrice,
            'savings' => max(0, $basePrice - $comboPrice),
            'savings_percentage' => $basePrice > 0 ? round((($basePrice - $comboPrice) / $basePrice) * 100, 2) : 0,
        ];
    }

    /**
     * Remove a product from a combo (all variants included).
     *
     * @param Product $comboProduct The combo (parent) product
     * @param int $productId The product to remove
     * @return bool
     */
    public function removeFromCombo(Product $comboProduct, int $productId): bool
    {
        return ProductCombo::where('product_id', $comboProduct->id)
            ->where('combo_product_id', $productId)
            ->delete() > 0;
    }

    /**
     * Remove a specific variant linkage from a combo.
     *
     * @param Product $comboProduct The combo (parent) product
     * @param int $productId The product containing the variant
     * @param int $variantId The specific variant to remove
     * @return bool
     */
    public function removeVariantFromCombo(Product $comboProduct, int $productId, int $variantId): bool
    {
        return ProductCombo::where('product_id', $comboProduct->id)
            ->where('combo_product_id', $productId)
            ->where('linked_variant_id', $variantId)
            ->delete() > 0;
    }

    /**
     * Resolve unified detail data for a product detail page.
     *
     * Returns type-aware data structure with variants (for variable)
     * or combo summary (for combo), plus unified price/stock.
     *
     * @param Product $product
     * @return array
     */
    public function resolveForDetail(Product $product): array
    {
        $data = [
            'price' => $this->getPrice($product),
            'stock' => $this->getStock($product),
            'inventory_status' => $this->getInventoryStatus($product),
            'display_price' => $this->getDisplayPrice($product),
        ];

        if ($product->isVariable()) {
            $data['variants'] = $product->variants
                ->where('status', ProductVariant::STATUS_ACTIVE)
                ->values()
                ->map(fn($v) => [
                    'id' => $v->id,
                    'price' => (float) ($v->price ?? $product->price),
                    'stock' => (int) $v->stock,
                    'sku' => $v->sku,
                    'attributes' => $v->attributes,
                    'label' => $v->label,
                    'image_url' => $v->getImageUrlAttribute(),
                ]);

            $keys = [];
            foreach ($product->variants as $variant) {
                $attrs = $variant->getAttribute('attributes');
                if (is_array($attrs)) {
                    $keys = array_merge($keys, array_keys($attrs));
                }
            }
            $data['option_keys'] = array_values(array_unique($keys));

            $names = [];
            foreach ($data['option_keys'] as $key) {
                $names[$key] = ucwords(str_replace('_', ' ', preg_replace('/^option(\d+)$/', 'Option $1', $key)));
            }
            $data['option_names'] = $names;

            $values = [];
            foreach ($data['option_keys'] as $key) {
                $vals = [];
                foreach ($product->variants as $variant) {
                    $attrs = $variant->getAttribute('attributes');
                    if (isset($attrs[$key])) {
                        $vals[] = $attrs[$key];
                    }
                }
                $values[$key] = array_values(array_unique($vals));
            }
            $data['option_values'] = $values;
        }

        if ($product->isCombo()) {
            $data['combo_summary'] = $product->getComboSummary();
            $data['combo_availability'] = $product->calculateComboAvailability();
        }

        return $data;
    }

    /**
     * Calculate the savings percentage for a combo.
     *
     * Savings = ((basePrice - comboPrice) / basePrice) × 100
     *
     * @param Product $comboProduct
     * @return float Percentage saved (0 if no savings)
     */
    public function getSavingsPercentage(Product $comboProduct): float
    {
        if (!$comboProduct->isCombo()) {
            return 0;
        }

        $basePrice = $comboProduct->getComboBasePrice();
        $comboPrice = $comboProduct->getEffectivePrice();

        if ($basePrice <= 0) {
            return 0;
        }

        return round((($basePrice - $comboPrice) / $basePrice) * 100, 2);
    }

    /**
     * Get combo products that include a specific product.
     *
     * Useful for cross-selling: "This product is also part of these bundles".
     *
     * @param int $productId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getCombosForProduct(int $productId)
    {
        return ProductCombo::where('combo_product_id', $productId)
            ->with('product')
            ->get()
            ->pluck('product');
    }

    /* ── Inventory abstractions ── */

    /**
     * Get unified inventory summary for any product.
     *
     * Returns consistent structure for single, variable, and combo products.
     *
     * @param Product $product
     * @return array
     */
    public function getInventorySummary(Product $product): array
    {
        return $product->getInventorySummary();
    }

    /**
     * Calculate combo availability with bottleneck identification.
     *
     * @param Product $comboProduct
     * @return array
     */
    public function calculateComboAvailability(Product $comboProduct): array
    {
        if (!$comboProduct->isCombo()) {
            return [];
        }

        return $comboProduct->calculateComboAvailability();
    }

    /**
     * Get the inventory status label for any product type.
     *
     * Returns one of: 'in_stock', 'low_stock', 'out_of_stock'
     *
     * @param Product $product
     * @return string
     */
    public function getInventoryStatus(Product $product): string
    {
        return $product->getInventoryStatus();
    }

    /**
     * Get a formatted display price string for any product type.
     *
     * Examples:
     *   Single: "25,000 MMK"
     *   Variable: "From 20,000 MMK"
     *   Combo: "35,000 MMK"
     *
     * @param Product $product
     * @return string
     */
    public function getDisplayPrice(Product $product): string
    {
        if ($product->isVariable()) {
            [$min, $max] = $product->getPriceRange();
            if ($min === $max) {
                return number_format($min) . ' ' . config('payments.default_currency');
            }
            return 'From ' . number_format($min) . ' ' . config('payments.default_currency');
        }

        if ($product->isCombo()) {
            return number_format($product->getEffectivePrice()) . ' ' . config('payments.default_currency');
        }

        return number_format((float) ($product->price ?? 0)) . ' ' . config('payments.default_currency');
    }

    /**
     * Get structured price summary for any product type.
     *
     * Returns type-aware price information for frontend display.
     *
     * @param Product $product
     * @return array
     */
    public function getPriceSummary(Product $product): array
    {
        if ($product->isVariable()) {
            [$min, $max] = $product->getPriceRange();
            return [
                'type' => 'variable',
                'min_price' => $min,
                'max_price' => $max,
                'has_range' => $min !== $max,
                'label' => 'From',
            ];
        }

        if ($product->isCombo()) {
            $basePrice = $product->getComboBasePrice();
            $comboPrice = $product->getEffectivePrice();
            return [
                'type' => 'combo',
                'price' => $comboPrice,
                'base_price' => $basePrice,
                'savings' => max(0, $basePrice - $comboPrice),
                'savings_percentage' => $basePrice > 0 ? round((($basePrice - $comboPrice) / $basePrice) * 100, 1) : 0,
            ];
        }

        return [
            'type' => 'single',
            'price' => (float) ($product->price ?? 0),
        ];
    }

    /**
     * Check if a product has sufficient stock for a given quantity.
     *
     * For combo products, this verifies that ALL components have
     * sufficient stock for the requested quantity.
     *
     * @param Product $product
     * @param int $quantity
     * @return bool
     */
    public function hasSufficientStock(Product $product, int $quantity = 1): bool
    {
        if ($quantity <= 0) {
            return true;
        }

        if ($product->isCombo()) {
            $availability = $product->calculateComboAvailability();
            return $availability['available_stock'] >= $quantity;
        }

        return $product->getEffectiveStock() >= $quantity;
    }

    /**
     * Calculate stock impact of reserving combos.
     *
     * Returns remaining stock for each component after reservation.
     * This is the foundation for warehouse/reserved stock systems.
     *
     * @param Product $comboProduct
     * @param int $reservedQuantity Number of combos to reserve
     * @return array
     */
    public function calculateComboStockImpact(Product $comboProduct, int $reservedQuantity = 0): array
    {
        if (!$comboProduct->isCombo() || $reservedQuantity <= 0) {
            return [];
        }

        $impacts = [];

        foreach ($comboProduct->comboItems()->get() as $item) {
            $impacts[] = [
                'product_id' => $item->combo_product_id,
                'product_name' => $item->comboProduct?->name,
                'variant_id' => $item->linked_variant_id,
                'quantity_per_combo' => $item->quantity,
                'current_stock' => $item->getEffectiveStock(),
                'reserved_stock' => $item->quantity * $reservedQuantity,
                'remaining_stock' => $item->getRemainingStockAfterReservation($reservedQuantity),
                'is_sufficient' => $item->getEffectiveStock() >= ($item->quantity * $reservedQuantity),
            ];
        }

        return $impacts;
    }
}
