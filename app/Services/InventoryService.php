<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;

class InventoryService
{
    public function __construct(
        private readonly StockMovementService $movements,
        private readonly StockCalculationService $calculator,
        private readonly StockValidationService $validator,
    ) {}

    public function handleProductCreated(Product $product, array $data): void
    {
        if (!FeatureGate::enabled('inventory_management')) {
            return;
        }

        $warehouseId = $data['warehouse_id'] ?? null;

        if ($product->isSingle() && ($product->stock ?? 0) > 0) {
            $this->movements->recordOpeningStock($product, (float) $product->stock, null, $warehouseId);
        }

        if ($product->isVariable()) {
            foreach ($product->variants as $variant) {
                if (($variant->stock ?? 0) > 0) {
                    $this->movements->recordOpeningStock($product, (float) $variant->stock, $variant, $warehouseId);
                }
            }
        }
    }

    /**
     * Record stock adjustment movements after a product update.
     *
     * Compares submitted stock values against the movement ledger and records
     * adjustment movements for any deltas. The stock column is then recalculated
     * from movements via syncProductCache().
     *
     * @param Product $product
     * @param array $data Validated request data (may contain 'stock')
     * @param array $oldVariantStocks Map of variant_id => old_stock (saved before syncVariants)
     * @param array $newVariantStocks Map of variant_id => new_stock (from request payload)
     */
    public function handleProductUpdated(Product $product, array $data, array $oldVariantStocks = [], array $newVariantStocks = []): void
    {
        if (!FeatureGate::enabled('inventory_management')) {
            return;
        }

        $warehouseId = $data['warehouse_id'] ?? null;

        if ($product->isSingle()) {
            $newStock = (float) ($data['stock'] ?? 0);
            $oldStock = $this->calculator->forProduct($product);
            $delta = $newStock - $oldStock;

            if (abs($delta) > 0.001) {
                $this->movements->record(
                    product: $product,
                    type: StockMovement::TYPE_ADJUSTMENT,
                    quantity: $delta,
                    warehouseId: $warehouseId,
                    description: 'Stock adjusted via product edit',
                );
            }
        }

        if ($product->isVariable()) {
            foreach ($product->variants as $variant) {
                $newStock = (float) ($newVariantStocks[$variant->id] ?? $variant->stock ?? 0);
                $oldStock = (float) ($oldVariantStocks[$variant->id] ?? 0);
                $isNewVariant = !array_key_exists($variant->id, $oldVariantStocks);
                $delta = $newStock - $oldStock;

                if (abs($delta) > 0.001) {
                    if ($isNewVariant && $newStock > 0) {
                        // New variant — record opening stock (immutable, one-time only)
                        $this->movements->recordOpeningStock($product, $newStock, $variant, $warehouseId);
                    } elseif (!$isNewVariant) {
                        // Existing variant — record adjustment
                        $this->movements->record(
                            product: $product,
                            type: StockMovement::TYPE_ADJUSTMENT,
                            quantity: $delta,
                            variant: $variant,
                            warehouseId: $warehouseId,
                            description: 'Variant stock adjusted via product edit',
                        );
                    }
                }
            }
        }
    }

    public function handleVariantCreated(Product $product, ProductVariant $variant): void
    {
        if (!FeatureGate::enabled('inventory_management')) {
            return;
        }

        if (($variant->stock ?? 0) > 0) {
            $this->movements->recordOpeningStock($product, (float) $variant->stock, $variant);
        }
    }

    public function recordMovement(
        Product $product,
        string $type,
        float $quantity,
        ?ProductVariant $variant = null,
        ?float $unitPrice = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $description = null,
        ?int $warehouseId = null,
    ) {
        return $this->movements->record(
            product: $product,
            type: $type,
            quantity: $quantity,
            variant: $variant,
            unitPrice: $unitPrice,
            referenceType: $referenceType,
            referenceId: $referenceId,
            description: $description,
            warehouseId: $warehouseId,
        );
    }

    public function movements(): StockMovementService
    {
        return $this->movements;
    }

    public function calculator(): StockCalculationService
    {
        return $this->calculator;
    }

    public function validator(): StockValidationService
    {
        return $this->validator;
    }
}
