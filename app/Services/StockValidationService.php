<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;

class StockValidationService
{
    public function __construct(
        private readonly StockCalculationService $calculator,
    ) {}

    public function hasSufficientStock(Product $product, float $requiredQuantity): bool
    {
        $available = $this->calculator->forProduct($product);
        return $available >= $requiredQuantity;
    }

    public function hasSufficientVariantStock(ProductVariant $variant, float $requiredQuantity): bool
    {
        $available = $this->calculator->forVariant($variant);
        return $available >= $requiredQuantity;
    }

    public function validateStockForProduct(Product $product, float $requiredQuantity): void
    {
        if (!$this->hasSufficientStock($product, $requiredQuantity)) {
            $available = $this->calculator->forProduct($product);
            throw new \RuntimeException(sprintf(
                'Insufficient stock for product "%s". Required: %d, Available: %d.',
                $product->name,
                (int) $requiredQuantity,
                (int) $available,
            ));
        }
    }

    public function validateStockForVariant(ProductVariant $variant, float $requiredQuantity): void
    {
        if (!$this->hasSufficientVariantStock($variant, $requiredQuantity)) {
            $available = $this->calculator->forVariant($variant);
            throw new \RuntimeException(sprintf(
                'Insufficient stock for variant "%s". Required: %d, Available: %d.',
                $variant->sku_display ?? $variant->id,
                (int) $requiredQuantity,
                (int) $available,
            ));
        }
    }
}
