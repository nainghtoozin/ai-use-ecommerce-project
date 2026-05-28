<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;

/**
 * ProductVariant model.
 *
 * Represents a single variant of a variable product.
 * Each variant has its own pricing, stock, SKU, and attribute combination.
 *
 * Example attributes structure:
 *   { "size": "XL", "color": "Black" }
 *   { "size": "M", "color": "White", "material": "Cotton" }
 *
 * @see Product
 */
class ProductVariant extends Model
{
    use HasFactory, TenantAware;

    /* ── Status constants ── */
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_DRAFT = 'draft';

    protected $table = 'product_variants';

    protected $fillable = [
        'product_id',
        'sku',
        'barcode',
        'price',
        'compare_price',
        'cost_price',
        'stock',
        'low_stock_threshold',
        'image',
        'attributes',
        'status',
    ];

    protected $casts = [
        'price' => 'float',
        'compare_price' => 'float',
        'cost_price' => 'float',
        'stock' => 'integer',
        'low_stock_threshold' => 'integer',
        'attributes' => 'array',
    ];

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
        'stock' => 0,
        'low_stock_threshold' => 5,
    ];

    protected $appends = ['image_url', 'sku_display'];

    /* ── Scopes ── */

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeInactive($query)
    {
        return $query->where('status', self::STATUS_INACTIVE);
    }

    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }

    /**
     * Scope by a specific attribute value.
     *
     * Usage: ProductVariant::withAttribute('size', 'XL')
     */
    public function scopeWithAttribute($query, string $key, string $value)
    {
        return $query->where("attributes->{$key}", $value);
    }

    /**
     * Scope by multiple attribute values.
     *
     * Usage: ProductVariant::withAttributes(['size' => 'XL', 'color' => 'Black'])
     */
    public function scopeWithAttributes($query, array $attributes)
    {
        foreach ($attributes as $key => $value) {
            $query->where("attributes->{$key}", $value);
        }
        return $query;
    }

    /* ── Relationships ── */

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /* ── Helper methods ── */

    /**
     * Check if this variant is active and in stock.
     */
    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->stock > 0;
    }

    /**
     * Get the effective price — falls back to parent product price if null.
     */
    public function getEffectivePrice(): float
    {
        if ($this->price !== null && $this->price > 0) {
            return (float) $this->price;
        }
        return $this->product?->getEffectivePrice() ?? 0;
    }

    /**
     * Get a human-readable label from the variant's attributes.
     *
     * Example: "XL / Black" or "M / White / Cotton"
     */
    public function getLabelAttribute(): string
    {
        $attrs = $this->getAttribute('attributes');
        if (empty($attrs) || !is_array($attrs)) {
            return "Variant #{$this->id}";
        }
        return implode(' / ', array_values($attrs));
    }

    /**
     * Get the attribute keys this variant uses.
     *
     * Example: ['option1', 'option2']
     */
    public function getAttributeKeys(): array
    {
        $attrs = $this->getAttribute('attributes');
        if (empty($attrs) || !is_array($attrs)) {
            return [];
        }
        return array_keys($attrs);
    }

    /**
     * Get a specific attribute option value from the attributes JSON.
     *
     * Example: $variant->getAttributeOption('option1') => 'XL'
     */
    public function getAttributeOption(string $key): ?string
    {
        $attrs = $this->getAttribute('attributes');
        return $attrs[$key] ?? null;
    }

    /**
     * Check if this variant has low stock.
     */
    public function isLowStock(): bool
    {
        return $this->stock > 0 && $this->stock <= $this->low_stock_threshold;
    }

    /**
     * Check if this variant is out of stock.
     */
    public function isOutOfStock(): bool
    {
        return $this->stock <= 0;
    }

    /* ── URL accessors ── */

    public function getImageUrlAttribute(): ?string
    {
        if (empty($this->image)) {
            return null;
        }

        if (str_starts_with($this->image, 'http://') || str_starts_with($this->image, 'https://')) {
            return $this->image;
        }

        return asset('storage/' . $this->image);
    }

    public function getSkuDisplayAttribute(): string
    {
        if ($this->sku) {
            return $this->sku;
        }
        return 'VAR-' . $this->id;
    }
}
