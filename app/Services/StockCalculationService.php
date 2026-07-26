<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\Warehouse;
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

    public function forProductInWarehouse(Product $product, int $warehouseId): float
    {
        return (float) StockMovement::where('product_id', $product->id)
            ->whereNull('product_variant_id')
            ->where('warehouse_id', $warehouseId)
            ->sum('quantity');
    }

    public function forVariantInWarehouse(ProductVariant $variant, int $warehouseId): float
    {
        return (float) StockMovement::where('product_variant_id', $variant->id)
            ->where('warehouse_id', $warehouseId)
            ->sum('quantity');
    }

    /**
     * Get stock breakdown by warehouse for a product.
     *
     * @return array<int, array{warehouse_id: int, warehouse_name: string, stock: float}>
     */
    public function getStockByWarehouse(Product $product): array
    {
        $rows = StockMovement::where('product_id', $product->id)
            ->whereNull('product_variant_id')
            ->whereNotNull('warehouse_id')
            ->select('warehouse_id', DB::raw('SUM(quantity) as total'))
            ->groupBy('warehouse_id')
            ->having('total', '!=', 0)
            ->with('warehouse:id,name,code')
            ->get();

        return $rows->map(fn($row) => [
            'warehouse_id' => $row->warehouse_id,
            'warehouse_name' => $row->warehouse?->name ?? 'Unknown',
            'warehouse_code' => $row->warehouse?->code,
            'stock' => (float) $row->total,
        ])->toArray();
    }

    /**
     * Get variant stock breakdown by warehouse.
     *
     * @return array<int, array{warehouse_id: int, warehouse_name: string, stock: float}>
     */
    public function getVariantStockByWarehouse(ProductVariant $variant): array
    {
        $rows = StockMovement::where('product_variant_id', $variant->id)
            ->whereNotNull('warehouse_id')
            ->select('warehouse_id', DB::raw('SUM(quantity) as total'))
            ->groupBy('warehouse_id')
            ->having('total', '!=', 0)
            ->with('warehouse:id,name,code')
            ->get();

        return $rows->map(fn($row) => [
            'warehouse_id' => $row->warehouse_id,
            'warehouse_name' => $row->warehouse?->name ?? 'Unknown',
            'warehouse_code' => $row->warehouse?->code,
            'stock' => (float) $row->total,
        ])->toArray();
    }
}
