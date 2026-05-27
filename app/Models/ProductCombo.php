<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * ProductCombo model.
 *
 * Represents a single product (or specific variant) that is included in a combo/bundle.
 * Each record links a combo product to one of its component products,
 * along with the quantity of that component required per combo.
 *
 * Variant support:
 *   - linked_variant_id NULL → uses parent product's stock/price
 *   - linked_variant_id SET → uses specific variant's stock/price
 *
 * Relationship:
 *   product_id         → the combo (parent) product
 *   combo_product_id   → the included (child) product
 *   linked_variant_id  → specific variant (nullable)
 *   quantity           → how many of the child product/variant are needed per combo
 *
 * Example:
 *   Combo "Gift Set" (product_id=100) includes:
 *     - Mug (combo_product_id=10, linked_variant_id=null, quantity=1)
 *     - T-Shirt L/Red (combo_product_id=20, linked_variant_id=5, quantity=1)
 *     - Coffee 250g (combo_product_id=30, linked_variant_id=null, quantity=2)
 *
 * @see Product
 * @see ProductVariant
 */
class ProductCombo extends Model
{
    use HasFactory;

    protected $table = 'product_combos';

    protected $fillable = [
        'product_id',
        'combo_product_id',
        'linked_variant_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    protected $attributes = [
        'quantity' => 1,
    ];

    /* ── Relationships ── */

    /**
     * The combo (parent) product that contains items.
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * The included (child) product that is part of the combo.
     */
    public function comboProduct()
    {
        return $this->belongsTo(Product::class, 'combo_product_id');
    }

    /**
     * The specific variant linked to this combo item (nullable).
     *
     * When present, this variant's stock and price override
     * the parent product's effective values.
     */
    public function linkedVariant()
    {
        return $this->belongsTo(ProductVariant::class, 'linked_variant_id');
    }

    /* ── Scopes ── */

    public function scopeActive($query)
    {
        return $query->whereHas('comboProduct', function ($q) {
            $q->where('status', Product::STATUS_ACTIVE);
        });
    }

    /**
     * Scope to only combo items that link to a specific variant.
     */
    public function scopeWithVariant($query)
    {
        return $query->whereNotNull('linked_variant_id')->with('linkedVariant');
    }

    /**
     * Scope to only combo items that use the parent product (no variant).
     */
    public function scopeWithoutVariant($query)
    {
        return $query->whereNull('linked_variant_id');
    }

    /* ── Helper methods ── */

    /**
     * Get the effective stock available for this combo item.
     *
     * If a variant is linked, returns that variant's stock.
     * Otherwise returns the parent product's effective stock.
     */
    public function getEffectiveStock(): int
    {
        if ($this->linked_variant_id && $this->linkedVariant) {
            return (int) ($this->linkedVariant->stock ?? 0);
        }

        return $this->comboProduct?->getEffectiveStock() ?? 0;
    }

    /**
     * Get the effective unit price for this combo item.
     *
     * If a variant is linked, returns that variant's effective price.
     * Otherwise returns the parent product's effective price.
     */
    public function getEffectivePrice(): float
    {
        if ($this->linked_variant_id && $this->linkedVariant) {
            return $this->linkedVariant->getEffectivePrice();
        }

        return $this->comboProduct?->getEffectivePrice() ?? 0;
    }

    /**
     * Get the subtotal value of this combo item.
     *
     * Price × quantity for base price calculation.
     */
    public function getItemSubtotal(): float
    {
        return $this->getEffectivePrice() * $this->quantity;
    }

    /**
     * Check if this combo item is available (active + sufficient stock).
     */
    public function isAvailable(): bool
    {
        $stock = $this->getEffectiveStock();
        $productActive = $this->comboProduct?->status === Product::STATUS_ACTIVE;

        return $productActive && $stock >= $this->quantity;
    }

    /**
     * Get a human-readable label for this combo item.
     *
     * Includes variant details if linked.
     */
    public function getLabel(): string
    {
        $label = $this->comboProduct?->name ?? 'Unknown Product';

        if ($this->linkedVariant) {
            $label .= ' (' . $this->linkedVariant->label . ')';
        }

        return $label;
    }

    /**
     * Determine the type of linkage for this combo item.
     *
     * Returns: 'variant' or 'product'
     */
    public function getLinkType(): string
    {
        return $this->linked_variant_id ? 'variant' : 'product';
    }

    /**
     * Calculate how many combos can be made from this item alone.
     *
     * Stock / required quantity, rounded down.
     */
    public function getPossibleCombos(): int
    {
        $stock = $this->getEffectiveStock();
        $required = max(1, $this->quantity);

        return (int) floor($stock / $required);
    }

    /**
     * Check if this item is a bottleneck (lowest stock ratio).
     *
     * @param int $comboStock The total available combo stock
     */
    public function isBottleneck(int $comboStock): bool
    {
        return $this->getPossibleCombos() === $comboStock && $comboStock > 0;
    }

    /**
     * Get remaining stock after reserving for a given number of combos.
     *
     * Useful for warehouse/reserved stock calculations.
     *
     * @param int $reservedCombos Number of combos to reserve stock for
     */
    public function getRemainingStockAfterReservation(int $reservedCombos = 0): int
    {
        $effectiveStock = $this->getEffectiveStock();
        $usedStock = $reservedCombos * $this->quantity;

        return max(0, $effectiveStock - $usedStock);
    }

    /**
     * Get a human-readable stock description.
     */
    public function getStockDescription(): string
    {
        $stock = $this->getEffectiveStock();
        $possible = $this->getPossibleCombos();

        if ($stock <= 0) {
            return 'Out of stock';
        }

        if ($this->linked_variant_id) {
            return "{$stock} in stock ({$possible} combos possible)";
        }

        return "{$stock} in stock ({$possible} combos possible)";
    }
}
