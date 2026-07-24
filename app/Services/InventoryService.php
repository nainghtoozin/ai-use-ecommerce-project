<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;

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
