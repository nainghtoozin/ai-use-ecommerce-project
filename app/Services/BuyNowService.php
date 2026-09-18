<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use Illuminate\Validation\ValidationException;

class BuyNowService
{
    public const SESSION_KEY = 'buy_now';

    public const CONTEXT_TTL_HOURS = 24;

    public function __construct(
        private readonly StockCalculationService $stockCalculationService,
    ) {}

    public function start(Tenant $tenant, int $productId, ?int $variantId, int $quantity): array
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity must be at least 1.',
            ]);
        }

        $product = Product::where('tenant_id', $tenant->id)
            ->active()
            ->find($productId);

        if (!$product) {
            throw ValidationException::withMessages([
                'product_id' => 'The selected product is not available.',
            ]);
        }

        $variant = null;
        $basePrice = (float) $product->price;

        if ($variantId) {
            $variant = ProductVariant::active()
                ->where('product_id', $product->id)
                ->find($variantId);

            if (!$variant) {
                throw ValidationException::withMessages([
                    'variant_id' => 'The selected variant is not available for this product.',
                ]);
            }

            $basePrice = (float) ($variant->price ?? $product->price);
            $stock = $this->stockCalculationService->forVariant($variant);
        } else {
            $stock = $this->stockCalculationService->forProduct($product);
        }

        if ($stock < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => $variant
                    ? "Insufficient stock for a product variant. Only {$stock} available."
                    : "Insufficient stock for {$product->name}. Only {$stock} available.",
            ]);
        }

        $key = 'p' . $product->id . '_v' . ($variant?->id ?? '0');
        $context = [
            'tenant_id' => (int) $tenant->id,
            'items' => [
                $key => [
                    'product_id' => (int) $product->id,
                    'variant_id' => $variant ? (int) $variant->id : null,
                    'quantity' => $quantity,
                    'price' => $basePrice,
                ],
            ],
            'started_at' => time(),
        ];

        session()->put(self::SESSION_KEY, $context);
        session()->save();

        return $context;
    }

    public function getActive(Tenant $tenant): ?array
    {
        $context = session()->get(self::SESSION_KEY);

        if (!is_array($context) || empty($context['items']) || !is_array($context['items'])) {
            return null;
        }

        if ((int) ($context['tenant_id'] ?? 0) !== (int) $tenant->id) {
            return null;
        }

        $startedAt = (int) ($context['started_at'] ?? 0);
        if ($startedAt > 0 && $startedAt < time() - self::CONTEXT_TTL_HOURS * 3600) {
            $this->clear();

            return null;
        }

        return $context;
    }

    public function isActive(Tenant $tenant): bool
    {
        return $this->getActive($tenant) !== null;
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
        session()->save();
    }
}
