<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\City;
use App\Models\Township;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService
{
    /**
     * Create order with guaranteed database persistence
     * 
     * @param array $orderData
     * @param array $items
     * @return Order
     * @throws \Exception
     */
    public function createOrder(array $orderData, array $items): Order
    {
        Log::info('========== OrderService: Starting Order Creation ==========');
        Log::info('Order data:', $orderData);
        Log::info('Order items:', $items);

        // Validate stock first
        $this->validateStock($items);

        // Create order inside transaction with error handling
        $order = DB::transaction(function () use ($orderData, $items) {
            // Get delivery fee
            $deliveryFee = $this->getDeliveryFee(
                $orderData['city_id'] ?? null,
                $orderData['township_id'] ?? null
            );

            Log::info('Creating order with delivery_fee:', ['delivery_fee' => $deliveryFee]);

            // Calculate subtotal from items (this is the correct way)
            $subtotal = collect($items)->sum(fn($item) => $item['price'] * $item['quantity']);
            
            // Calculate total = subtotal + delivery_fee
            $totalAmount = $subtotal + $deliveryFee;

            Log::info('Calculated amounts:', [
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'total_amount' => $totalAmount
            ]);

            // Create the order
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
                'payment_status' => ! empty($orderData['payment_proof']) ? 'paid' : 'unpaid',
                'order_status' => 'pending',
            ]);

            Log::info('Order created in database:', ['order_id' => $order->id, 'created_at' => $order->created_at]);

            // Verify order was actually saved
            $savedOrder = Order::find($order->id);
            if (!$savedOrder) {
                throw new \Exception('Failed to save order to database - order not found after creation');
            }
            Log::info('Order verified in database:', ['order_id' => $savedOrder->id]);

            // Create order items
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

            // Verify items were saved
            $itemCount = $order->items()->count();
            Log::info('Order items count verified:', ['count' => $itemCount]);

            if ($itemCount !== count($items)) {
                throw new \Exception('Failed to save all order items');
            }

            Log::info('========== OrderService: Order Creation SUCCESS ==========');
            
            return $order;
        });

        return $order;
    }

    /**
     * Validate stock availability for all items
     * 
     * @param array $items
     * @throws \InvalidArgumentException
     */
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

    /**
     * Get delivery fee from city 
     * 
     * @param int|null $cityId
     * @param int|null $townshipId
     * @return float
     */
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

    /**
     * Reduce stock when order is confirmed
     * 
     * @param Order $order
     */
    public function reduceStock(Order $order): void
    {
        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                $product = $item->product;
                if ($product) {
                    $newStock = max(0, $product->stock - $item->quantity);
                    $product->update(['stock' => $newStock]);
                    
                    Log::info('Stock reduced:', [
                        'product_id' => $product->id,
                        'old_stock' => $product->stock,
                        'new_stock' => $newStock
                    ]);
                }
            }
        });
    }

    /**
     * Restore stock when order is cancelled
     * 
     * @param Order $order
     */
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

    /**
     * Update order status
     * 
     * @param Order $order
     * @param string $newStatus
     * @return Order
     */
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

        return $order->fresh();
    }

    /**
     * Update payment status
     * 
     * @param Order $order
     * @param string $status
     * @return Order
     */
    public function updatePaymentStatus(Order $order, string $status): Order
    {
        $order->update(['payment_status' => $status]);
        return $order->fresh();
    }

    /**
     * Get filtered orders for admin
     * 
     * @param array $filters
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
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
