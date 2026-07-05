<?php

namespace App\Models;

use App\Enums\ProductType;
use App\Models\Traits\TenantAware;
use App\Services\ImageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory, TenantAware;

    /* ── Status constants ── */
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';

    /* ── Type constants (alias to ProductType enum for convenience) ── */
    const TYPE_SINGLE = ProductType::SINGLE;
    const TYPE_VARIABLE = ProductType::VARIABLE;
    const TYPE_COMBO = ProductType::COMBO;

    protected $fillable = [
        'name', 'slug', 'sku', 'barcode', 'short_description', 'description',
        'price', 'base_price', 'cost_price', 'category_id', 'brand_id', 'unit_id',
        'stock', 'low_stock_alert', 'photo1', 'photo2', 'gallery_images', 'seo_title', 'seo_description', 'seo_keywords', 'seo_image', 'status', 'type',
    ];

    protected $casts = [
        'price' => 'float',
        'gallery_images' => 'array',
    ];

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
        'type' => ProductType::SINGLE,
    ];

    protected $appends = ['photo1_url', 'photo2_url', 'gallery_images_url', 'seo_image_url', 'has_orders', 'is_variable', 'is_combo', 'is_single', 'effective_stock', 'total_variant_stock', 'variant_count', 'inventory_summary', 'sku_display', 'stock_status', 'price_range', 'display_price_label', 'display_price_summary'];

    /* ── Scopes ── */

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeInactive($query)
    {
        return $query->where('status', self::STATUS_INACTIVE);
    }

    /**
     * Scope by product type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for single products only.
     */
    public function scopeSingle($query)
    {
        return $query->where('type', ProductType::SINGLE);
    }

    /**
     * Scope for products that can be added to combos.
     *
     * Excludes combo products themselves to prevent circular references.
     */
    public function scopeComboSelectable($query)
    {
        return $query->active()
            ->where('type', '!=', ProductType::COMBO)
            ->orderBy('name');
    }

    /* ── Type helper methods ── */

    /**
     * Check if this is a single (standard) product.
     */
    public function isSingle(): bool
    {
        return $this->type === ProductType::SINGLE;
    }

    /**
     * Check if this is a variable product with variants.
     */
    public function isVariable(): bool
    {
        return $this->type === ProductType::VARIABLE;
    }

    /**
     * Check if this is a combo/bundle product.
     */
    public function isCombo(): bool
    {
        return $this->type === ProductType::COMBO;
    }

    /**
     * Get the human-readable product type label.
     */
    public function getTypeLabel(): string
    {
        return ProductType::label($this->type ?? ProductType::SINGLE);
    }

    /**
     * Accessors for JSON serialization (used by $appends).
     */
    public function getIsSingleAttribute(): bool
    {
        return $this->isSingle();
    }

    public function getIsVariableAttribute(): bool
    {
        return $this->isVariable();
    }

    public function getIsComboAttribute(): bool
    {
        return $this->isCombo();
    }

    public function getSkuDisplayAttribute(): string
    {
        if ($this->sku) {
            return $this->sku;
        }
        return 'PRD-' . $this->id;
    }

    /**
     * Check if this product supports variants.
     */
    public function supportsVariants(): bool
    {
        return ProductType::supportsVariants($this->type ?? ProductType::SINGLE);
    }

    /**
     * Check if this product actually has variants defined.
     */
    public function hasVariants(): bool
    {
        if (!$this->supportsVariants()) {
            return false;
        }
        return $this->variants()->exists();
    }

    /* ── Relationships ── */

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function promotions()
    {
        return $this->belongsToMany(Promotion::class, 'promotion_product');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get all variants for this variable product.
     *
     * Only applicable when product.type = 'variable'.
     */
    public function variants()
    {
        return $this->hasMany(ProductVariant::class)->orderBy('id');
    }

    /**
     * Get only active variants.
     */
    public function activeVariants()
    {
        return $this->hasMany(ProductVariant::class)->active()->orderBy('id');
    }

    /**
     * Products included in this combo (when this product IS the combo).
     *
     * Only applicable when product.type = 'combo'.
     * Each ProductCombo record represents one component product and its quantity.
     */
    public function comboItems()
    {
        return $this->hasMany(ProductCombo::class)->with(['comboProduct', 'linkedVariant'])->orderBy('id');
    }

    /**
     * Combos that include this product (when this product is INCLUDED in a combo).
     *
     * Useful for answering: "Which bundles is this product part of?"
     */
    public function comboProductOf()
    {
        return $this->hasMany(ProductCombo::class, 'combo_product_id')->with('product')->orderBy('id');
    }

    /* ── Price & Stock accessors (variant-aware) ── */

    /**
     * Get the effective selling price for this product.
     *
     * For single products: returns the product's price field.
     * For variable products: returns the parent product's price as the base/default.
     *   Individual variant prices are resolved via ProductVariant::getEffectivePrice().
     * For combo products: returns the combo's set price.
     *   Use getComboBasePrice() for the sum of individual item prices.
     */
    public function getEffectivePrice(): float
    {
        return (float) ($this->price ?? 0);
    }

    /**
     * Get the effective stock quantity for this product.
     *
     * For single products: returns the product's stock field.
     * For variable products: sums stock across all active variants.
     * For combo products: returns the minimum possible combos
     *   based on component stock-to-quantity ratios.
     */
    public function getEffectiveStock(): int
    {
        if ($this->isVariable()) {
            if ($this->relationLoaded('variants')) {
                return (int) $this->variants->where('status', ProductVariant::STATUS_ACTIVE)->sum('stock');
            }
            return (int) $this->variants()->active()->sum('stock');
        }

        if ($this->isCombo()) {
            return $this->comboStock();
        }

        return (int) ($this->stock ?? 0);
    }

    /**
     * Check if this product is in stock.
     */
    public function isInStock(): bool
    {
        return $this->getEffectiveStock() > 0;
    }

    /**
     * Get the default (first active) variant for this product.
     *
     * Useful for automatically selecting a variant when viewing
     * a variable product on the frontend.
     *
     * @return ProductVariant|null
     */
    public function defaultVariant(): ?ProductVariant
    {
        if (!$this->supportsVariants()) {
            return null;
        }

        return $this->variants()->active()->inStock()->first();
    }

    /**
     * Find a variant by its attribute combination.
     *
     * Example: $product->findVariantByAttributes(['size' => 'XL', 'color' => 'Black'])
     *
     * @param array $attributes
     * @return ProductVariant|null
     */
    public function findVariantByAttributes(array $attributes): ?ProductVariant
    {
        if (!$this->supportsVariants() || empty($attributes)) {
            return null;
        }

        $query = $this->variants();
        foreach ($attributes as $key => $value) {
            $query->where("attributes->{$key}", $value);
        }

        return $query->first();
    }

    /**
     * Get the total stock across all variants.
     *
     * Alias for getEffectiveStock() for clarity in calling code.
     */
    public function variantStock(): int
    {
        return $this->getEffectiveStock();
    }

    /**
     * Get the price range across all variants.
     *
     * Returns [minPrice, maxPrice].
     * Falls back to parent product price if no variants exist.
     *
     * @return array{0: float, 1: float}
     */
    public function getPriceRange(): array
    {
        if (!$this->supportsVariants()) {
            $price = $this->getEffectivePrice();
            return [$price, $price];
        }

        if ($this->relationLoaded('variants')) {
            $activeVariants = $this->variants->where('status', ProductVariant::STATUS_ACTIVE);
            if ($activeVariants->isEmpty()) {
                return [$this->getEffectivePrice(), $this->getEffectivePrice()];
            }
            $prices = $activeVariants->pluck('price')->filter()->map(fn($p) => (float) $p);
            if ($prices->isEmpty()) {
                return [$this->getEffectivePrice(), $this->getEffectivePrice()];
            }
            return [$prices->min(), $prices->max()];
        }

        $activeVariants = $this->variants()->active()->get(['price']);
        if ($activeVariants->isEmpty()) {
            return [$this->getEffectivePrice(), $this->getEffectivePrice()];
        }

        $minPrice = $activeVariants->min('price') ?? $this->price;
        $maxPrice = $activeVariants->max('price') ?? $this->price;

        return [(float) $minPrice, (float) $maxPrice];
    }

    public function getPriceRangeAttribute(): array
    {
        return $this->getPriceRange();
    }

    public function getDisplayPriceLabelAttribute(): string
    {
        if ($this->isVariable()) {
            return 'From';
        }
        return '';
    }

    public function getDisplayPriceSummaryAttribute(): array
    {
        if ($this->isVariable()) {
            [$min, $max] = $this->getPriceRange();
            return [
                'type' => 'variable',
                'min' => $min,
                'max' => $max,
                'label' => 'From',
                'display' => $min === $max
                    ? number_format($min) . ' MMK'
                    : 'From ' . number_format($min) . ' MMK',
            ];
        }

        if ($this->isCombo()) {
            return [
                'type' => 'combo',
                'price' => $this->getEffectivePrice(),
                'base_price' => $this->getComboBasePrice(),
                'savings' => max(0, $this->getComboBasePrice() - $this->getEffectivePrice()),
                'display' => number_format($this->getEffectivePrice()) . ' MMK',
            ];
        }

        return [
            'type' => 'single',
            'price' => (float) ($this->price ?? 0),
            'display' => number_format((float) ($this->price ?? 0)) . ' MMK',
        ];
    }

    /* ── Combo helpers ── */

    public function hasComboItems(): bool
    {
        if (!$this->isCombo()) {
            return false;
        }
        return $this->comboItems()->exists();
    }

    /**
     * Add a product to this combo.
     *
     * @param int $productId
     * @param int $quantity
     * @return ProductCombo
     */
    public function addComboItem(int $productId, int $quantity = 1): ProductCombo
    {
        return ProductCombo::firstOrCreate(
            ['product_id' => $this->id, 'combo_product_id' => $productId, 'linked_variant_id' => null],
            ['quantity' => $quantity]
        );
    }

    /**
     * Add a specific variant to this combo.
     *
     * @param int $productId
     * @param int $variantId
     * @param int $quantity
     * @return ProductCombo
     */
    public function addComboVariant(int $productId, int $variantId, int $quantity = 1): ProductCombo
    {
        return ProductCombo::firstOrCreate(
            ['product_id' => $this->id, 'combo_product_id' => $productId, 'linked_variant_id' => $variantId],
            ['quantity' => $quantity]
        );
    }

    /**
     * Get a summary of this combo's components.
     *
     * Returns an array with:
     *   - items: array of component details
     *   - base_price: sum of all component prices × quantities
     *   - effective_stock: max combos possible from component stock
     *   - savings: price difference vs buying individually (if combo price is set)
     */
    public function getComboSummary(): array
    {
        $items = $this->relationLoaded('comboItems') ? $this->comboItems : $this->comboItems()->get();

        $itemDetails = $items->map(function ($item) {
            $comboProduct = $item->comboProduct;

            return [
                'id' => $item->id,
                'product_id' => $item->combo_product_id,
                'product_name' => $comboProduct?->name,
                'variant_id' => $item->linked_variant_id,
                'variant_label' => $item->linkedVariant?->label,
                'link_type' => $item->getLinkType(),
                'quantity' => $item->quantity,
                'unit_price' => $item->getEffectivePrice(),
                'subtotal' => $item->getItemSubtotal(),
                'stock_available' => $item->getEffectiveStock(),
                'is_available' => $item->isAvailable(),
                'photo_url' => $comboProduct?->photo1_url,
            ];
        })->toArray();

        $basePrice = $this->getComboBasePrice();
        $comboPrice = $this->getEffectivePrice();

        return [
            'items' => $itemDetails,
            'item_count' => $items->count(),
            'base_price' => $basePrice,
            'combo_price' => $comboPrice,
            'savings' => max(0, $basePrice - $comboPrice),
            'savings_percentage' => $basePrice > 0 ? round((($basePrice - $comboPrice) / $basePrice) * 100, 2) : 0,
            'effective_stock' => $this->comboStock(),
        ];
    }

    /**
     * Calculate the total stock available for this combo.
     *
     * Combo stock is limited by the component with the lowest
     * stock-to-quantity ratio. For example:
     *   Item A: stock=10, qty=1 → can make 10 combos
     *   Item B: stock=6, qty=2 → can make 3 combos
     *   Combo stock = 3
     *
     * Returns 0 if any component is out of stock or missing.
     */
    public function comboStock(): int
    {
        if (!$this->isCombo()) {
            return 0;
        }

        $items = $this->relationLoaded('comboItems') ? $this->comboItems : $this->comboItems()->get();

        if ($items->isEmpty()) {
            return 0;
        }

        $maxCombos = PHP_INT_MAX;

        foreach ($items as $comboItem) {
            $componentStock = $comboItem->getEffectiveStock();
            $requiredQty = max(1, $comboItem->quantity);

            $possibleCombos = (int) floor($componentStock / $requiredQty);
            $maxCombos = min($maxCombos, $possibleCombos);
        }

        return $maxCombos === PHP_INT_MAX ? 0 : (int) $maxCombos;
    }

    /**
     * Calculate the sum of all component prices × quantities.
     *
     * Returns the base price of the combo before any bundle discount.
     * Useful for displaying savings ("Save X MMK vs buying individually").
     */
    public function getComboBasePrice(): float
    {
        if (!$this->isCombo()) {
            return $this->getEffectivePrice();
        }

        $items = $this->relationLoaded('comboItems') ? $this->comboItems : $this->comboItems()->get();

        if ($items->isEmpty()) {
            return (float) ($this->price ?? 0);
        }

        return (float) $items->sum(function ($item) {
            return $item->getItemSubtotal();
        });
    }

    /**
     * Check if all combo components are available (active + in stock).
     */
    public function areComboItemsAvailable(): bool
    {
        if (!$this->isCombo()) {
            return false;
        }

        $items = $this->relationLoaded('comboItems') ? $this->comboItems : $this->comboItems()->get();

        if ($items->isEmpty()) {
            return false;
        }

        return $items->every(function ($item) {
            return $item->isAvailable();
        });
    }

    /**
     * Calculate detailed combo availability with bottleneck information.
     *
     * Returns an array with:
     *   - available_stock: total combos possible
     *   - bottleneck: the component limiting stock (null if no items)
     *   - bottleneck_stock: stock of the bottleneck component
     *   - bottleneck_ratio: stock-to-quantity ratio of bottleneck
     *   - all_available: whether all components are in stock
     *   - out_of_stock_items: list of items that are out of stock
     *   - low_stock_items: list of items with stock < 5 combos
     *
     * This is the foundation for warehouse/reserved stock extensions.
     */
    public function calculateComboAvailability(): array
    {
        if (!$this->isCombo()) {
            return [
                'available_stock' => 0,
                'bottleneck' => null,
                'bottleneck_stock' => 0,
                'bottleneck_ratio' => 0,
                'all_available' => false,
                'out_of_stock_items' => [],
                'low_stock_items' => [],
            ];
        }

        $items = $this->relationLoaded('comboItems') ? $this->comboItems : $this->comboItems()->get();

        if ($items->isEmpty()) {
            return [
                'available_stock' => 0,
                'bottleneck' => null,
                'bottleneck_stock' => 0,
                'bottleneck_ratio' => 0,
                'all_available' => false,
                'out_of_stock_items' => [],
                'low_stock_items' => [],
            ];
        }

        $maxCombos = PHP_INT_MAX;
        $bottleneck = null;
        $bottleneckStock = 0;
        $bottleneckRatio = 0;
        $outOfStockItems = [];
        $lowStockItems = [];

        foreach ($items as $item) {
            $componentStock = $item->getEffectiveStock();
            $requiredQty = max(1, $item->quantity);
            $possibleCombos = (int) floor($componentStock / $requiredQty);

            if ($possibleCombos <= 0) {
                $outOfStockItems[] = [
                    'product_name' => $item->comboProduct?->name,
                    'variant_label' => $item->linkedVariant?->label,
                    'stock' => $componentStock,
                    'required' => $requiredQty,
                ];
            } elseif ($possibleCombos < 5) {
                $lowStockItems[] = [
                    'product_name' => $item->comboProduct?->name,
                    'variant_label' => $item->linkedVariant?->label,
                    'stock' => $componentStock,
                    'possible_combos' => $possibleCombos,
                ];
            }

            if ($possibleCombos < $maxCombos) {
                $maxCombos = $possibleCombos;
                $bottleneck = [
                    'product_id' => $item->combo_product_id,
                    'product_name' => $item->comboProduct?->name,
                    'variant_id' => $item->linked_variant_id,
                    'variant_label' => $item->linkedVariant?->label,
                    'stock' => $componentStock,
                    'required_quantity' => $requiredQty,
                    'possible_combos' => $possibleCombos,
                ];
                $bottleneckStock = $componentStock;
                $bottleneckRatio = $possibleCombos;
            }
        }

        $availableStock = $maxCombos === PHP_INT_MAX ? 0 : max(0, $maxCombos);

        return [
            'available_stock' => $availableStock,
            'bottleneck' => $bottleneck,
            'bottleneck_stock' => $bottleneckStock,
            'bottleneck_ratio' => $bottleneckRatio,
            'all_available' => empty($outOfStockItems) && empty($lowStockItems),
            'out_of_stock_items' => $outOfStockItems,
            'low_stock_items' => $lowStockItems,
        ];
    }

    /**
     * Get the stock status label for this product.
     *
     * For combo products, this is derived from component availability.
     */
    public function getStockStatus(): string
    {
        $stock = $this->getEffectiveStock();

        if ($stock <= 0) {
            return 'out_of_stock';
        }

        if ($stock < 10) {
            return 'low_stock';
        }

        return 'in_stock';
    }

    public function getStockStatusAttribute(): string
    {
        return $this->getStockStatus();
    }

    /**
     * Get a unified inventory summary for any product type.
     *
     * Returns consistent structure regardless of product type:
     *   - type: 'single', 'variable', or 'combo'
     *   - stock: effective stock quantity
     *   - status: 'in_stock', 'low_stock', or 'out_of_stock'
     *   - details: type-specific additional info
     */
    public function getInventorySummary(): array
    {
        $stock = $this->getEffectiveStock();
        $status = $this->getStockStatus();

        if ($this->isVariable()) {
            $allVariants = $this->relationLoaded('variants') ? $this->variants : $this->variants()->get();
            $activeVariants = $allVariants->where('status', ProductVariant::STATUS_ACTIVE);
            $inStockVariants = $activeVariants->where('stock', '>', 0);

            return [
                'type' => 'variable',
                'stock' => $stock,
                'status' => $status,
                'details' => [
                    'total_variants' => $allVariants->count(),
                    'active_variants' => $activeVariants->count(),
                    'per_variant_stock' => $inStockVariants->values()->map(fn($v) => [
                        'id' => $v->id,
                        'label' => $v->label,
                        'stock' => $v->stock,
                    ])->toArray(),
                ],
            ];
        }

        if ($this->isCombo()) {
            $availability = $this->calculateComboAvailability();
            $comboItems = $this->relationLoaded('comboItems') ? $this->comboItems : $this->comboItems()->get();

            return [
                'type' => 'combo',
                'stock' => $stock,
                'status' => $status,
                'details' => [
                    'component_count' => $comboItems->count(),
                    'available_stock' => $availability['available_stock'],
                    'bottleneck' => $availability['bottleneck'],
                    'all_available' => $availability['all_available'],
                    'out_of_stock_count' => count($availability['out_of_stock_items']),
                    'low_stock_count' => count($availability['low_stock_items']),
                ],
            ];
        }

        return [
            'type' => 'single',
            'stock' => $stock,
            'status' => $status,
            'details' => [
                'direct_stock' => (int) ($this->stock ?? 0),
            ],
        ];
    }

    /* ── Deletion ── */

    /**
     * Check if this product has been referenced in any orders.
     */
    public function hasOrders(): bool
    {
        return $this->orderItems()->exists();
    }

    /* ── URL accessors ── */

    public function getPhoto1UrlAttribute(): ?string
    {
        if (empty($this->photo1)) {
            return null;
        }

        return ImageService::url($this->photo1);
    }

    public function getPhoto2UrlAttribute(): ?string
    {
        if (empty($this->photo2)) {
            return null;
        }

        return ImageService::url($this->photo2);
    }

    public function getGalleryImagesUrlAttribute(): array
    {
        $images = $this->gallery_images ?? [];

        return array_map(fn($path) => $path ? ImageService::url($path) : null, $images);
    }

    public function getSeoImageUrlAttribute(): ?string
    {
        if (empty($this->seo_image)) return null;

        return ImageService::url($this->seo_image);
    }

    public function getHasOrdersAttribute(): bool
    {
        return $this->orderItems()->exists();
    }

    public function getEffectiveStockAttribute(): int
    {
        return $this->getEffectiveStock();
    }

    public function getVariantCountAttribute(): int
    {
        if ($this->relationLoaded('variants')) {
            return $this->variants->count();
        }
        return $this->variants()->count();
    }

    public function getTotalVariantStockAttribute(): int
    {
        if ($this->isVariable()) {
            if ($this->relationLoaded('variants')) {
                return (int) $this->variants->sum('stock');
            }
            return (int) $this->variants()->sum('stock');
        }
        return 0;
    }

    public function getInventorySummaryAttribute(): array
    {
        return $this->getInventorySummary();
    }

    /* ── Inventory status helpers ── */

    /**
     * Get a human-readable inventory status label.
     *
     * Returns one of: 'in_stock', 'low_stock', 'out_of_stock'
     *
     * For variable products: considers total active variant stock.
     * For combo products: considers the bottleneck component ratio.
     *
     * @return string
     */
    public function getInventoryStatus(): string
    {
        $stock = $this->getEffectiveStock();

        if ($stock <= 0) {
            return 'out_of_stock';
        }

        // Use the product's own low_stock_alert if available, fallback to 10
        $threshold = (int) ($this->low_stock_alert ?? 10);

        if ($stock <= $threshold) {
            return 'low_stock';
        }

        return 'in_stock';
    }

    /**
     * Get a human-readable inventory status label for display.
     *
     * @return string
     */
    public function getInventoryStatusLabel(): string
    {
        return match ($this->getInventoryStatus()) {
            'out_of_stock' => 'Out of Stock',
            'low_stock' => 'Low Stock',
            default => 'In Stock',
        };
    }

    /**
     * Get the CSS color class for the inventory status.
     *
     * @return string
     */
    public function getInventoryStatusColor(): string
    {
        return match ($this->getInventoryStatus()) {
            'out_of_stock' => 'red',
            'low_stock' => 'amber',
            default => 'green',
        };
    }
}
