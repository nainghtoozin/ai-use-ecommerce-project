<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class AdjustmentService
{
    const REASON_OPENING_CORRECTION = 'opening_correction';
    const REASON_STOCK_COUNT = 'stock_count';
    const REASON_DAMAGED = 'damaged';
    const REASON_LOST = 'lost';
    const REASON_EXPIRED = 'expired';
    const REASON_MANUAL_CORRECTION = 'manual_correction';
    const REASON_CUSTOMER_RETURN = 'customer_return';
    const REASON_SUPPLIER_CORRECTION = 'supplier_correction';
    const REASON_OTHER = 'other';

    public static function getReasons(): array
    {
        return [
            self::REASON_OPENING_CORRECTION => 'Opening Stock Correction',
            self::REASON_STOCK_COUNT => 'Stock Count',
            self::REASON_DAMAGED => 'Damaged',
            self::REASON_LOST => 'Lost',
            self::REASON_EXPIRED => 'Expired',
            self::REASON_MANUAL_CORRECTION => 'Manual Correction',
            self::REASON_CUSTOMER_RETURN => 'Customer Return',
            self::REASON_SUPPLIER_CORRECTION => 'Supplier Correction',
            self::REASON_OTHER => 'Other',
        ];
    }

    public function __construct(
        private readonly StockMovementService $movements,
        private readonly StockCalculationService $calculator,
    ) {}

    public function adjust(
        Product $product,
        float $quantity,
        string $reason,
        string $description = '',
        string $reference = '',
        ?ProductVariant $variant = null,
        ?int $warehouseId = null,
    ): StockMovement {
        if (abs($quantity) < 0.001) {
            throw new \InvalidArgumentException('Adjustment quantity cannot be zero.');
        }

        if (empty(trim($reason))) {
            throw new \InvalidArgumentException('Adjustment reason is required.');
        }

        $reasons = self::getReasons();
        if (!isset($reasons[$reason])) {
            throw new \InvalidArgumentException("Invalid adjustment reason: {$reason}");
        }

        $fullDescription = $reasons[$reason];
        if (!empty(trim($reference))) {
            $fullDescription .= ' [Ref: ' . trim($reference) . ']';
        }
        if (!empty(trim($description))) {
            $fullDescription .= ' — ' . trim($description);
        }

        $adjustmentNumber = $this->generateAdjustmentNumber();

        return $this->movements->record(
            product: $product,
            type: StockMovement::TYPE_ADJUSTMENT,
            quantity: $quantity,
            variant: $variant,
            warehouseId: $warehouseId,
            adjustmentNumber: $adjustmentNumber,
            description: $fullDescription,
        );
    }

    public function getAdjustmentHistory(int $perPage = 20): \Illuminate\Pagination\LengthAwarePaginator
    {
        return StockMovement::with(['product:id,name,sku', 'variant:id,sku', 'warehouse:id,name,code'])
            ->where('type', StockMovement::TYPE_ADJUSTMENT)
            ->latest()
            ->paginate($perPage);
    }

    public function getAdjustmentsForProduct(Product $product, int $perPage = 20): \Illuminate\Pagination\LengthAwarePaginator
    {
        return StockMovement::with(['warehouse:id,name,code'])
            ->where('product_id', $product->id)
            ->where('type', StockMovement::TYPE_ADJUSTMENT)
            ->latest()
            ->paginate($perPage);
    }

    public function preview(Product $product, float $quantity, ?ProductVariant $variant = null): array
    {
        $currentStock = $variant
            ? $this->calculator->forVariant($variant)
            : $this->calculator->forProduct($product);

        return [
            'current_stock' => $currentStock,
            'new_stock' => max(0, $currentStock + $quantity),
            'delta' => $quantity,
        ];
    }

    private function generateAdjustmentNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = 'ADJ-' . $date . '-';

        $lastNumber = StockMovement::where('type', StockMovement::TYPE_ADJUSTMENT)
            ->where('adjustment_number', 'like', $prefix . '%')
            ->orderByDesc('adjustment_number')
            ->value('adjustment_number');

        if ($lastNumber) {
            $seq = (int) substr($lastNumber, -6) + 1;
        } else {
            $seq = 1;
        }

        return $prefix . str_pad($seq, 6, '0', STR_PAD_LEFT);
    }

    public static function extractReasonKey(?string $description, array $reasons): string
    {
        if (!$description) return 'other';

        foreach ($reasons as $key => $label) {
            if (str_starts_with($description, $label)) {
                return $key;
            }
        }

        return 'other';
    }

    public static function extractReference(?string $description): string
    {
        if (!$description) return '';
        if (preg_match('/\[Ref:\s*(.+?)\]/', $description, $matches)) {
            return trim($matches[1]);
        }
        return '';
    }

    public static function extractNotes(?string $description): string
    {
        if (!$description) return '';
        $parts = explode(' — ', $description, 2);
        return isset($parts[1]) ? trim($parts[1]) : '';
    }
}
