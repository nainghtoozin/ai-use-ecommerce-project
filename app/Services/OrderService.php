<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\City;
use App\Models\Township;
use App\Models\User;
use App\Models\Coupon;
use App\Models\Promotion;
use App\Events\OrderStatusChanged;
use App\Events\LowStockAlert;
use App\Notifications\LowStockNotification;
use App\Services\BroadcastService;
use App\Services\CouponService;
use App\Services\PromotionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class OrderService
{
    public function __construct(
        private readonly NotificationPreferenceService $preferenceService,
        private readonly CouponService $couponService,
        private readonly PromotionService $promotionService
    ) {}

    public function createOrder(array $orderData, array $items, ?array $couponData = null, ?array $promotionData = null): Order
    {
        Log::info('========== OrderService: Starting Order Creation ==========');
        Log::info('Order data:', $orderData);
        Log::info('Order items:', $items);

        $this->validateStock($items);

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
                'payment_status' => ! empty($orderData['payment_proof']) ? 'paid' : 'unpaid',
                'order_status' => 'pending',
            ]);

            Log::info('Order created in database:', ['order_id' => $order->id, 'created_at' => $order->created_at]);

            $savedOrder = Order::find($order->id);
            if (!$savedOrder) {
                throw new \Exception('Failed to save order to database - order not found after creation');
            }
            Log::info('Order verified in database:', ['order_id' => $savedOrder->id]);

            foreach ($items as $index => $item) {
                $orderItem = $order->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                ]);

                Log::info("Order item $index created:", [
                    'order_item_id' => $orderItem->id,
                    'product_id' => $item['product_id'],
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

    public function validateStock(array $items): void
    {
        Log::info('Validating stock for items:', $items);

        foreach ($items as $item) {
            $product = Product::find($item['product_id']);

            if (!$product) {
                throw new \InvalidArgumentException("Product not found: {$item['product_id']}");
            }

            if ($product->stock < $item['quantity']) {
                throw new \InvalidArgumentException(
                    "Insufficient stock for product: {$product->name}. Available: {$product->stock}, Requested: {$item['quantity']}"
                );
            }
        }

        Log::info('Stock validation passed');
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
            foreach ($order->items as $item) {
                $product = $item->product;
                if ($product) {
                    $oldStock = $product->stock;
                    $newStock = max(0, $product->stock - $item->quantity);
                    $product->update(['stock' => $newStock]);

                    Log::info('Stock reduced:', [
                        'product_id' => $product->id,
                        'old_stock' => $product->stock,
                        'new_stock' => $newStock
                    ]);

                    if ($newStock < 10 && $oldStock >= 10) {
                        $admins = User::where('role', User::ROLE_ADMIN)->get();
                        $adminsWhoWantLowStock = $this->preferenceService->filterUsersByPreference($admins, 'low_stock');

                        if ($adminsWhoWantLowStock->isNotEmpty()) {
                            BroadcastService::fire(new LowStockAlert($product, 10), [
                                'product_id' => $product->id,
                            ]);

                            try {
                                Notification::send(
                                    $adminsWhoWantLowStock,
                                    new LowStockNotification($product, 10)
                                );
                            } catch (\Throwable $e) {
                                Log::warning('Failed to send low stock DB notification', [
                                    'product_id' => $product->id,
                                    'message' => $e->getMessage(),
                                ]);
                            }
                        }
                    }
                }
            }
        });
    }

    public function restoreStock(Order $order): void
    {
        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                $product = $item->product;
                if ($product) {
                    $product->increment('stock', $item->quantity);

                    Log::info('Stock restored:', [
                        'product_id' => $product->id,
                        'stock_added' => $item->quantity
                    ]);
                }
            }
        });
    }

    public function updateOrderStatus(Order $order, string $newStatus): Order
    {
        $oldStatus = $order->order_status;

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
}
