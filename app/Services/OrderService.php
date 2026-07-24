<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Auth\IdentityResolver;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\City;
use App\Models\Township;
use App\Models\Coupon;
use App\Models\Promotion;
use App\Events\OrderStatusChanged;
use App\Events\LowStockAlert;
use App\Notifications\LowStockNotification;
use App\Services\BroadcastService;
use App\Services\CouponService;
use App\Services\OrderWorkflow;
use App\Services\PromotionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class OrderService
{
    public function __construct(
        private readonly NotificationPreferenceService $preferenceService,
        private readonly CouponService $couponService,
        private readonly PromotionService $promotionService,
        private readonly StockMovementService $stockMovementService,
        private readonly StockCalculationService $stockCalculationService,
    ) {}

    public function createOrder(array $orderData, array $items, ?array $couponData = null, ?array $promotionData = null): Order
    {
        Log::info('========== OrderService: Starting Order Creation ==========');
        Log::info('Order data:', $orderData);
        Log::info('Order items:', $items);

        $this->validateStock($items);

        $this->validateCodAccess($orderData);

        $order = DB::transaction(function () use ($orderData, $items, $couponData, $promotionData) {
            $deliveryFee = $this->getDeliveryFee(
                $orderData['city_id'] ?? null,
                $orderData['township_id'] ?? null
            );

            Log::info('Creating order with delivery_fee:', ['delivery_fee' => $deliveryFee]);

            $subtotal = collect($items)->sum(fn($item) => $item['price'] * $item['quantity']);
            $couponDiscount = (float) ($couponData['discount'] ?? 0);
            $promotionDiscount = (float) ($promotionData['discount'] ?? 0);
            $discountAmount = $couponDiscount + $promotionDiscount;
            $totalAmount = ($subtotal + $deliveryFee) - $discountAmount;

            Log::info('Calculated amounts:', [
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'discount_amount' => $discountAmount,
                'total_amount' => $totalAmount
            ]);

            $orderData['promotion_id'] = !empty($promotionData['promotion'])
                ? $promotionData['promotion']->id
                : ($orderData['promotion_id'] ?? null);

            $orderData['promotion_code'] = !empty($promotionData['promotion'])
                ? ($promotionData['promotion']->code ?? 'AUTO')
                : ($orderData['promotion_code'] ?? null);

            $order = Order::create([
                'user_id' => $orderData['user_id'],
                'customer_name' => trim($orderData['first_name'] . ' ' . $orderData['last_name']),
                'first_name' => $orderData['first_name'],
                'last_name' => $orderData['last_name'],
                'phone' => $orderData['phone'],
                'email' => $orderData['email'] ?? null,
                'address' => $orderData['address'],
                'city_id' => $orderData['city_id'] ?? null,
                'township_id' => $orderData['township_id'] ?? null,
                'postal_code' => $orderData['postal_code'] ?? null,
                'notes' => $orderData['notes'] ?? null,
                'payment_method_id' => $orderData['payment_method_id'],
                'payment_proof' => $orderData['payment_proof'] ?? null,
                'transaction_id' => $orderData['transaction_id'] ?? null,
                'subtotal' => $subtotal,
                'total_amount' => $totalAmount,
                'delivery_fee' => $deliveryFee,
                'discount_amount' => $discountAmount,
                'promotion_id' => $orderData['promotion_id'],
                'promotion_code' => $orderData['promotion_code'],
                'payment_status' => ! empty($orderData['payment_proof']) ? Order::PAYMENT_STATUS_PAID : Order::PAYMENT_STATUS_PENDING,
                'order_status' => 'pending',
            ]);

            Log::info('Order created in database:', ['order_id' => $order->id, 'created_at' => $order->created_at]);

            $savedOrder = Order::find($order->id);
            if (!$savedOrder) {
                throw new \Exception('Failed to save order to database - order not found after creation');
            }
            Log::info('Order verified in database:', ['order_id' => $savedOrder->id]);

            foreach ($items as $index => $item) {
                $orderItemData = [
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                ];

                if (!empty($item['variant_id'])) {
                    $orderItemData['variant_id'] = $item['variant_id'];
                }

                $orderItem = $order->items()->create($orderItemData);

                Log::info("Order item $index created:", [
                    'order_item_id' => $orderItem->id,
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'] ?? null,
                    'quantity' => $item['quantity']
                ]);
            }

            $itemCount = $order->items()->count();
            Log::info('Order items count verified:', ['count' => $itemCount]);

            if ($itemCount !== count($items)) {
                throw new \Exception('Failed to save all order items');
            }

            if ($couponData && $couponData['coupon'] ?? null) {
                $this->couponService->applyCouponToOrder(
                    $order,
                    $couponData['coupon'],
                    $couponData['discount'] ?? $discountAmount
                );
            }

            if ($promotionData && $promotionData['promotion'] ?? null) {
                $this->promotionService->applyPromotionToOrder(
                    $order,
                    $promotionData['promotion'],
                    $promotionData['discount'] ?? $discountAmount
                );
            }

            Log::info('========== OrderService: Order Creation SUCCESS ==========');

            return $order;
        });

        return $order;
    }

    private function validateCodAccess(array $orderData): void
    {
        $paymentMethodId = $orderData['payment_method_id'] ?? null;
        $userId = $orderData['user_id'] ?? null;

        if (!$paymentMethodId || !$userId) {
            return;
        }

        $paymentMethod = PaymentMethod::find($paymentMethodId);

        if (!$paymentMethod || $paymentMethod->type !== 'cod') {
            return;
        }

        $user = \App\Models\User::find($userId);

        if (!$user || !$user->allow_cod) {
            throw new \InvalidArgumentException('COD payment is not available for your account.');
        }
    }

    public function validateStock(array $items): void
    {
        Log::info('Validating stock for items:', $items);

        foreach ($items as $item) {
            $productId = $item['product_id'];
            $variantId = $item['variant_id'] ?? null;
            $quantity = $item['quantity'];

            $product = Product::with(['comboItems.comboProduct', 'comboItems.linkedVariant'])->find($productId);
            if (!$product) {
                throw new \InvalidArgumentException("Product not found: {$productId}");
            }

            if ($product->isCombo()) {
                $this->validateComboStock($product, $quantity);
            } elseif ($variantId) {
                $variant = ProductVariant::with('product')->find($variantId);
                if (!$variant || $variant->product_id !== $productId) {
                    throw new \InvalidArgumentException("Invalid variant: {$variantId}");
                }

                $stock = $this->stockCalculationService->forVariant($variant);
                if ($stock < $quantity) {
                    throw new \InvalidArgumentException(
                        "Insufficient stock for variant: {$variant->label}. Available: {$stock}, Requested: {$quantity}"
                    );
                }
            } else {
                $stock = $this->stockCalculationService->forProduct($product);
                if ($stock < $quantity) {
                    throw new \InvalidArgumentException(
                        "Insufficient stock for product: {$product->name}. Available: {$stock}, Requested: {$quantity}"
                    );
                }
            }
        }

        Log::info('Stock validation passed');
    }

    private function validateComboStock(Product $combo, int $requestedQuantity): void
    {
        $comboItems = $combo->comboItems;

        if ($comboItems->isEmpty()) {
            throw new \InvalidArgumentException("Combo product '{$combo->name}' has no components.");
        }

        foreach ($comboItems as $comboItem) {
            $componentStock = $comboItem->getEffectiveStock();
            $requiredQty = max(1, $comboItem->quantity);
            $totalRequired = $requiredQty * $requestedQuantity;

            if ($componentStock < $totalRequired) {
                $productName = $comboItem->comboProduct?->name ?? 'Unknown';
                $variantLabel = $comboItem->linkedVariant?->label;
                $itemLabel = $variantLabel ? "{$productName} ({$variantLabel})" : $productName;

                throw new \InvalidArgumentException(
                    "Insufficient stock for combo component: {$itemLabel}. Available: {$componentStock}, Required: {$totalRequired}"
                );
            }
        }
    }

    private function getDeliveryFee($cityId, $townshipId): float
    {
        if ($cityId) {
            $city = City::find($cityId);
            if ($city) {
                return (float) $city->delivery_fee;
            }
        }

        return 0;
    }

    public function reduceStock(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order->load('items.product.comboItems.comboProduct', 'items.product.comboItems.linkedVariant', 'items.variant.product');

            foreach ($order->items as $item) {
                $product = $item->product;
                if (!$product) {
                    Log::warning('Order item has no product, skipping stock reduction:', [
                        'order_item_id' => $item->id,
                    ]);
                    continue;
                }

                if ($product->isCombo()) {
                    $this->reduceComboStock($product, $item->quantity, $item->id, $order);
                } elseif ($item->variant_id) {
                    $variant = $item->variant;
                    if ($variant) {
                        $oldStock = $this->stockCalculationService->forVariant($variant);
                        $this->stockMovementService->record(
                            product: $product,
                            type: StockMovement::TYPE_SALE,
                            quantity: -$item->quantity,
                            variant: $variant,
                            referenceType: 'order',
                            referenceId: $order->id,
                            description: "Order #{$order->id} confirmed",
                        );
                        $newStock = $this->stockCalculationService->forVariant($variant);

                        Log::info('Variant stock reduced via movement:', [
                            'variant_id' => $variant->id,
                            'product_id' => $item->product_id,
                            'old_stock' => $oldStock,
                            'new_stock' => $newStock,
                        ]);

                        if ($newStock < 10 && $oldStock >= 10) {
                            $this->fireLowStockAlert($variant->product, 10);
                        }
                    }
                } else {
                    $oldStock = $this->stockCalculationService->forProduct($product);
                    $this->stockMovementService->record(
                        product: $product,
                        type: StockMovement::TYPE_SALE,
                        quantity: -$item->quantity,
                        referenceType: 'order',
                        referenceId: $order->id,
                        description: "Order #{$order->id} confirmed",
                    );
                    $newStock = $this->stockCalculationService->forProduct($product);

                    Log::info('Stock reduced via movement:', [
                        'product_id' => $product->id,
                        'old_stock' => $oldStock,
                        'new_stock' => $newStock,
                    ]);

                    if ($newStock < 10 && $oldStock >= 10) {
                        $this->fireLowStockAlert($product, 10);
                    }
                }
            }
        });
    }

    private function reduceComboStock(Product $combo, int $orderQuantity, int $orderItemId, Order $order): void
    {
        $comboItems = $combo->comboItems;

        if ($comboItems->isEmpty()) {
            Log::warning('Combo product has no components, skipping stock reduction:', [
                'combo_id' => $combo->id,
                'order_item_id' => $orderItemId,
            ]);
            return;
        }

        foreach ($comboItems as $comboItem) {
            $requiredQty = max(1, $comboItem->quantity) * $orderQuantity;

            if ($comboItem->linked_variant_id && $comboItem->linkedVariant) {
                $variant = $comboItem->linkedVariant;
                $oldStock = $this->stockCalculationService->forVariant($variant);
                $this->stockMovementService->record(
                    product: $comboItem->comboProduct,
                    type: StockMovement::TYPE_SALE,
                    quantity: -$requiredQty,
                    variant: $variant,
                    referenceType: 'order',
                    referenceId: $order->id,
                    description: "Order #{$order->id} confirmed (combo component)",
                );
                $newStock = $this->stockCalculationService->forVariant($variant);

                Log::info('Combo variant stock reduced via movement:', [
                    'combo_id' => $combo->id,
                    'order_item_id' => $orderItemId,
                    'variant_id' => $variant->id,
                    'product_id' => $comboItem->combo_product_id,
                    'required' => $requiredQty,
                    'old_stock' => $oldStock,
                    'new_stock' => $newStock,
                ]);

                if ($newStock < 10 && $oldStock >= 10) {
                    $this->fireLowStockAlert($comboItem->comboProduct, 10);
                }
            } elseif ($comboItem->comboProduct) {
                $componentProduct = $comboItem->comboProduct;
                $oldStock = $this->stockCalculationService->forProduct($componentProduct);
                $this->stockMovementService->record(
                    product: $componentProduct,
                    type: StockMovement::TYPE_SALE,
                    quantity: -$requiredQty,
                    referenceType: 'order',
                    referenceId: $order->id,
                    description: "Order #{$order->id} confirmed (combo component)",
                );
                $newStock = $this->stockCalculationService->forProduct($componentProduct);

                Log::info('Combo component stock reduced via movement:', [
                    'combo_id' => $combo->id,
                    'order_item_id' => $orderItemId,
                    'product_id' => $componentProduct->id,
                    'required' => $requiredQty,
                    'old_stock' => $oldStock,
                    'new_stock' => $newStock,
                ]);

                if ($newStock < 10 && $oldStock >= 10) {
                    $this->fireLowStockAlert($componentProduct, 10);
                }
            }
        }
    }

    public function restoreStock(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order->load('items.product.comboItems.comboProduct', 'items.product.comboItems.linkedVariant', 'items.variant.product');

            foreach ($order->items as $item) {
                $product = $item->product;
                if (!$product) {
                    Log::warning('Order item has no product, skipping stock restoration:', [
                        'order_item_id' => $item->id,
                    ]);
                    continue;
                }

                if ($product->isCombo()) {
                    $this->restoreComboStock($product, $item->quantity, $item->id, $order);
                } elseif ($item->variant_id) {
                    $variant = $item->variant;
                    if ($variant) {
                        $this->stockMovementService->record(
                            product: $product,
                            type: StockMovement::TYPE_RETURN,
                            quantity: $item->quantity,
                            variant: $variant,
                            referenceType: 'order',
                            referenceId: $order->id,
                            description: "Order #{$order->id} cancelled — stock restored",
                        );

                        Log::info('Variant stock restored via movement:', [
                            'variant_id' => $variant->id,
                            'product_id' => $item->product_id,
                            'stock_added' => $item->quantity,
                        ]);
                    }
                } else {
                    $this->stockMovementService->record(
                        product: $product,
                        type: StockMovement::TYPE_RETURN,
                        quantity: $item->quantity,
                        referenceType: 'order',
                        referenceId: $order->id,
                        description: "Order #{$order->id} cancelled — stock restored",
                    );

                    Log::info('Stock restored via movement:', [
                        'product_id' => $product->id,
                        'stock_added' => $item->quantity,
                    ]);
                }
            }
        });
    }

    private function restoreComboStock(Product $combo, int $orderQuantity, int $orderItemId, Order $order): void
    {
        $comboItems = $combo->comboItems;

        if ($comboItems->isEmpty()) {
            Log::warning('Combo product has no components, skipping stock restoration:', [
                'combo_id' => $combo->id,
                'order_item_id' => $orderItemId,
            ]);
            return;
        }

        foreach ($comboItems as $comboItem) {
            $restoreQty = max(1, $comboItem->quantity) * $orderQuantity;

            if ($comboItem->linked_variant_id && $comboItem->linkedVariant) {
                $variant = $comboItem->linkedVariant;
                $this->stockMovementService->record(
                    product: $comboItem->comboProduct,
                    type: StockMovement::TYPE_RETURN,
                    quantity: $restoreQty,
                    variant: $variant,
                    referenceType: 'order',
                    referenceId: $order->id,
                    description: "Order #{$order->id} cancelled — stock restored (combo component)",
                );

                Log::info('Combo variant stock restored via movement:', [
                    'combo_id' => $combo->id,
                    'order_item_id' => $orderItemId,
                    'variant_id' => $variant->id,
                    'product_id' => $comboItem->combo_product_id,
                    'restored' => $restoreQty,
                ]);
            } elseif ($comboItem->comboProduct) {
                $componentProduct = $comboItem->comboProduct;
                $this->stockMovementService->record(
                    product: $componentProduct,
                    type: StockMovement::TYPE_RETURN,
                    quantity: $restoreQty,
                    referenceType: 'order',
                    referenceId: $order->id,
                    description: "Order #{$order->id} cancelled — stock restored (combo component)",
                );

                Log::info('Combo component stock restored via movement:', [
                    'combo_id' => $combo->id,
                    'order_item_id' => $orderItemId,
                    'product_id' => $componentProduct->id,
                    'restored' => $restoreQty,
                ]);
            }
        }
    }

    private function fireLowStockAlert($product, int $threshold): void
    {
        $tenantId = is_object($product) ? $product->tenant_id : null;
        $admins = $tenantId
            ? IdentityResolver::resolveTenantAdmins($tenantId)
            : collect();
        $adminsWhoWantLowStock = $this->preferenceService->filterUsersByPreference($admins, 'low_stock');

        if ($adminsWhoWantLowStock->isNotEmpty()) {
            BroadcastService::fire(new LowStockAlert($product, $threshold), [
                'product_id' => $product->id,
            ]);

            try {
                Notification::send(
                    $adminsWhoWantLowStock,
                    new LowStockNotification($product, $threshold)
                );
            } catch (\Throwable $e) {
                Log::warning('Failed to send low stock DB notification', [
                    'product_id' => $product->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    public function updateOrderStatus(Order $order, string $newStatus): Order
    {
        $oldStatus = $order->order_status;

        $this->validateTransition($oldStatus, $newStatus);

        if ($oldStatus === 'pending' && $newStatus === 'confirmed') {
            app(OrderWorkflow::class)->assertCanConfirmOrder($order);
        }

        if ($oldStatus === 'confirmed' && $newStatus === 'processing') {
            app(OrderWorkflow::class)->assertCanProcessOrder($order);
        }

        if ($oldStatus === 'processing' && $newStatus === 'shipped') {
            app(OrderWorkflow::class)->assertCanShipOrder($order);
        }

        if ($oldStatus === 'shipped' && $newStatus === 'delivered') {
            app(OrderWorkflow::class)->assertCanDeliverOrder($order);
        }

        DB::transaction(function () use ($order, $newStatus, $oldStatus) {
            $order->update(['order_status' => $newStatus]);

            if ($oldStatus === 'pending' && $newStatus === 'confirmed') {
                $this->reduceStock($order);
            }

            if ($oldStatus === 'confirmed' && $newStatus === 'cancelled') {
                $this->restoreStock($order);
            }
        });

        $order->loadMissing('user');
        if (!$order->user || $this->preferenceService->userWantsNotification($order->user, 'order_status_changed')) {
            BroadcastService::fire(new OrderStatusChanged($order->fresh(), $oldStatus, $newStatus), [
                'order_id' => $order->id,
            ]);
        }

        return $order->fresh();
    }

    public function updatePaymentStatus(Order $order, string $status): Order
    {
        $order->update(['payment_status' => $status]);
        return $order->fresh();
    }

    public function getFilteredOrders(array $filters)
    {
        $query = Order::with(['user', 'items.product', 'paymentMethod', 'city', 'township']);

        if (!empty($filters['order_status'])) {
            $query->where('order_status', $filters['order_status']);
        }

        if (!empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate(15);
    }

    private function validateTransition(string $oldStatus, string $newStatus): void
    {
        $valid = [
            'pending' => ['confirmed', 'cancelled'],
            'confirmed' => ['processing', 'cancelled'],
            'processing' => ['shipped'],
            'shipped' => ['delivered'],
            'delivered' => [],
            'cancelled' => [],
        ];

        $allowed = $valid[$oldStatus] ?? [];

        if (!in_array($newStatus, $allowed, true)) {
            throw new \InvalidArgumentException(
                "Invalid order status transition: {$oldStatus} → {$newStatus}"
            );
        }
    }
}
