<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class StockCalculationService
{
    public function forProduct(Product $product): float
    {
        return (float) StockMovement::where('product_id', $product->id)
            ->whereNull('product_variant_id')
            ->sum('quantity');
    }

    public function forVariant(ProductVariant $variant): float
    {
        return (float) StockMovement::where('product_variant_id', $variant->id)
            ->sum('quantity');
    }

    public function forProductWithVariants(Product $product): float
    {
        return (float) StockMovement::where('product_id', $product->id)
            ->sum('quantity');
    }

    public function forProductAsOf(Product $product, string $date): float
    {
        return (float) StockMovement::where('product_id', $product->id)
            ->whereNull('product_variant_id')
            ->whereDate('created_at', '<=', $date)
            ->sum('quantity');
    }

    public function getStockStatus(Product $product): string
    {
        $stock = $this->forProduct($product);
        $threshold = $product->low_stock_alert ?? 5;

        if ($stock <= 0) {
            return 'out_of_stock';
        }

        if ($stock <= $threshold) {
            return 'low_stock';
        }

        return 'in_stock';
    }

    public function getStockStatusForProductWithVariants(Product $product): string
    {
        if ($product->isVariable()) {
            $variants = $product->variants;
            if ($variants->isEmpty()) {
                return 'out_of_stock';
            }

            $hasStock = false;
            $allLow = true;

            foreach ($variants as $variant) {
                $vStock = $this->forVariant($variant);
                if ($vStock > 0) {
                    $hasStock = true;
                }
                if ($vStock > ($variant->low_stock_threshold ?? 5)) {
                    $allLow = false;
                }
            }

            if (!$hasStock) {
                return 'out_of_stock';
            }

            return $allLow ? 'low_stock' : 'in_stock';
        }

        return $this->getStockStatus($product);
    }

    public function getInventorySummary(Product $product): array
    {
        $stock = $this->forProduct($product);
        $variantStock = 0;

        if ($product->isVariable()) {
            $variantStock = $this->forProductWithVariants($product) - $stock;
        }

        return [
            'product_stock' => $stock,
            'variant_stock' => $variantStock,
            'total' => $stock + $variantStock,
            'status' => $this->getStockStatusForProductWithVariants($product),
        ];
    }
}
