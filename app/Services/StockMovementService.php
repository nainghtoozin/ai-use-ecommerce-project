<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class StockMovementService
{
    public function recordOpeningStock(Product $product, float $quantity, ?ProductVariant $variant = null, ?int $warehouseId = null): StockMovement
    {
        return $this->record(
            product: $product,
            type: StockMovement::TYPE_OPENING_STOCK,
            quantity: abs($quantity),
            variant: $variant,
            warehouseId: $warehouseId,
            description: 'Initial opening stock for ' . ($variant ? 'variant ' . $variant->sku_display : 'product ' . $product->name),
        );
    }

    public function record(
        Product $product,
        string $type,
        float $quantity,
        ?ProductVariant $variant = null,
        ?float $unitPrice = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $description = null,
        ?int $warehouseId = null,
    ): StockMovement {
        return DB::transaction(function () use ($product, $type, $quantity, $variant, $unitPrice, $referenceType, $referenceId, $description, $warehouseId) {
            $movement = StockMovement::create([
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'warehouse_id' => $warehouseId,
                'type' => $type,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description,
            ]);

            $this->syncProductCache($product, $variant);

            return $movement;
        });
    }

    public function getMovements(
        ?int $productId = null,
        ?string $type = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = StockMovement::with(['product:id,name,sku,type,unit_id', 'variant:id,sku,attributes'])
            ->latest();

        if ($productId) {
            $query->where('product_id', $productId);
        }

        if ($type) {
            $query->where('type', $type);
        }

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        return $query->paginate($perPage);
    }

    public function getProductMovements(Product $product, int $perPage = 50): LengthAwarePaginator
    {
        return StockMovement::with('variant')
            ->where('product_id', $product->id)
            ->latest()
            ->paginate($perPage);
    }

    private function syncProductCache(Product $product, ?ProductVariant $variant = null): void
    {
        if ($variant) {
            $calculated = app(StockCalculationService::class)->forVariant($variant);
            $variant->update(['stock' => max(0, (int) $calculated)]);
        }

        $calculated = app(StockCalculationService::class)->forProduct($product);
        $product->update(['stock' => max(0, (int) $calculated)]);
    }
}
