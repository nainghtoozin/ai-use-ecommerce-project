<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

class TelegramSampleOrderFactory
{
    public function create(?int $tenantId = null): Order
    {
        $tenantId = $tenantId ?? Tenant::getCurrent()?->id;

        $order = new Order([
            'customer_name' => 'Sample Customer',
            'first_name' => 'Sample',
            'last_name' => 'Customer',
            'phone' => '09-123-456-789',
            'email' => 'customer@example.com',
            'address' => '123 Main Street',
            'subtotal' => 50000,
            'total_amount' => 53000,
            'delivery_fee' => 3000,
            'paid_amount' => 53000,
            'order_status' => 'pending',
            'payment_status' => 'paid',
            'tenant_id' => $tenantId,
        ]);
        $order->id = 99999;
        $order->created_at = now();

        $product = new Product(['name' => 'Sample Product']);
        $item = new OrderItem(['product_id' => 999, 'quantity' => 2, 'price' => 25000]);
        $item->setRelation('product', $product);

        $order->setRelation('items', collect([$item]));
        $order->setRelation('paymentMethod', null);
        $order->setRelation('tenant', Tenant::getCurrent());

        Log::info('[TelegramSampleOrderFactory] Sample order created', [
            'order_id' => $order->id,
            'tenant_id' => $tenantId,
            'item_count' => 1,
        ]);

        return $order;
    }
}
