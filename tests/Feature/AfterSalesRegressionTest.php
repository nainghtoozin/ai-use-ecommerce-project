<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderRefund;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OrderRefundService;
use App\Services\OrderStatusTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AfterSalesRegressionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $customer;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Notification::fake();

        $this->tenant = Tenant::create([
            'name' => 'Regression Store',
            'slug' => 'regression-store',
            'store_url' => '/store/regression-store',
            'status' => 'active',
        ]);

        Tenant::setCurrent($this->tenant);
        $this->grantStorefrontEntitlement($this->tenant);

        $this->customer = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Regression Customer',
            'email' => 'regression@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $category = Category::create(['name' => 'Regression Cat', 'slug' => 'regression-cat']);

        $this->product = Product::create([
            'name' => 'Regression Widget',
            'type' => 'single',
            'price' => 1000,
            'stock' => 50,
            'status' => 'active',
            'category_id' => $category->id,
        ]);
    }

    private function makeOrder(array $overrides = []): Order
    {
        $order = Order::create(array_merge([
            'user_id' => $this->customer->id,
            'user_type' => User::class,
            'first_name' => 'Regression',
            'last_name' => 'Customer',
            'phone' => '09911122233',
            'address' => 'No. 4, Regression Road',
            'subtotal' => 1000,
            'total_amount' => 1000,
            'delivery_fee' => 0,
            'paid_amount' => 1000,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'order_status' => Order::ORDER_STATUS_CONFIRMED,
            'stock_reduced' => true,
        ], $overrides));

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price' => 1000,
        ]);

        return $order;
    }

    public function test_transition_to_delivered_sets_delivered_timestamp(): void
    {
        $order = $this->makeOrder(['delivered_at' => null]);

        app(OrderStatusTransitionService::class)->transition($order, Order::ORDER_STATUS_DELIVERED);

        $this->assertSame(Order::ORDER_STATUS_DELIVERED, $order->fresh()->order_status);
        $this->assertNotNull($order->fresh()->delivered_at);
    }

    public function test_transition_not_involving_delivery_keeps_timestamp_null(): void
    {
        $order = $this->makeOrder(['delivered_at' => null]);

        app(OrderStatusTransitionService::class)->transition($order, Order::ORDER_STATUS_PROCESSING);

        $this->assertSame(Order::ORDER_STATUS_PROCESSING, $order->fresh()->order_status);
        $this->assertNull($order->fresh()->delivered_at);
    }

    public function test_effective_paid_amount_falls_back_to_total_when_paid_amount_missing(): void
    {
        $order = $this->makeOrder(['paid_amount' => 0, 'total_amount' => 750]);

        $this->assertSame(750.0, app(OrderRefundService::class)->effectivePaidAmount($order));
    }

    public function test_refundable_paid_amount_subtracts_completed_refunds(): void
    {
        $order = $this->makeOrder(['paid_amount' => 1000, 'payment_status' => Order::PAYMENT_STATUS_PAID]);

        OrderRefund::create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'refund_number' => 'REF-REG-0001',
            'amount' => 400,
            'method' => 'manual',
            'status' => OrderRefund::STATUS_COMPLETED,
        ]);

        $this->assertSame(400.0, $order->fresh()->refundedAmount());
        $this->assertSame(600.0, $order->fresh()->refundablePaidAmount());
    }

    public function test_unpaid_order_has_no_refundable_balance(): void
    {
        $order = $this->makeOrder([
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
            'paid_amount' => 0,
        ]);

        $this->assertSame(0.0, $order->fresh()->refundablePaidAmount());
    }
}
