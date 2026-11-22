<?php

namespace Tests\Feature;

use App\Models\CancellationSetting;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderCancellation;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AfterSalesStatusNotification;
use App\Services\OrderCancellationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrderCancellationAfterSalesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $customer;
    private Product $product;
    private OrderCancellationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Notification::fake();

        $this->tenant = Tenant::create([
            'name' => 'After Sales Store',
            'slug' => 'after-sales-store',
            'store_url' => '/store/after-sales-store',
            'status' => 'active',
        ]);

        Tenant::setCurrent($this->tenant);
        $this->grantStorefrontEntitlement($this->tenant);

        $this->customer = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'After Sales Customer',
            'email' => 'after-sales@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $category = Category::create(['name' => 'After Sales', 'slug' => 'after-sales']);

        $this->product = Product::create([
            'name' => 'After Sales Widget',
            'type' => 'single',
            'price' => 1000,
            'stock' => 50,
            'status' => 'active',
            'category_id' => $category->id,
        ]);

        $this->service = app(OrderCancellationService::class);
    }

    private function makeOrder(array $overrides = [], int $quantity = 2): Order
    {
        $order = Order::create(array_merge([
            'user_id' => $this->customer->id,
            'user_type' => User::class,
            'first_name' => 'After',
            'last_name' => 'Sales',
            'phone' => '09911122233',
            'address' => 'No. 1, Test Road',
            'subtotal' => 1000 * $quantity,
            'total_amount' => (1000 * $quantity) + 500,
            'delivery_fee' => 500,
            'paid_amount' => 0,
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
            'order_status' => Order::ORDER_STATUS_PENDING,
            'stock_reduced' => false,
        ], $overrides));

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => $quantity,
            'price' => 1000,
        ]);

        return $order;
    }

    private function settings(): CancellationSetting
    {
        return app(\App\Services\AfterSalesSettingsService::class)
            ->cancellationSettings($this->tenant->id);
    }

    public function test_pending_order_is_cancelled_instantly_without_refund_for_unpaid_orders(): void
    {
        $order = $this->makeOrder();

        $case = $this->service->request($order, $this->customer, OrderCancellation::ROLE_CUSTOMER, [
            'reason_code' => 'changed_mind',
        ]);

        $this->assertSame(OrderCancellation::STATUS_COMPLETED, $case->status);
        $this->assertSame(Order::ORDER_STATUS_CANCELLED, $order->fresh()->order_status);
        $this->assertNull($case->refund_id);
        $this->assertSame(0, StockMovement::where('type', StockMovement::TYPE_RETURN)->count());
    }

    public function test_confirmed_cancellation_requires_approval_and_creates_refund_without_shipping_fees(): void
    {
        $order = $this->makeOrder([
            'order_status' => Order::ORDER_STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'paid_amount' => 2500,
            'packaging_fee' => 100,
            'cod_fee' => 50,
            'stock_reduced' => true,
        ]);

        $case = $this->service->request($order, $this->customer, OrderCancellation::ROLE_CUSTOMER, [
            'reason_code' => 'changed_mind',
            'note' => 'Found a cheaper option',
        ]);

        $this->assertSame(OrderCancellation::STATUS_REQUESTED, $case->status);
        $this->assertSame(CancellationSetting::MODE_APPROVAL, $case->mode);
        $this->assertSame(Order::ORDER_STATUS_CONFIRMED, $order->fresh()->order_status);

        $approved = $this->service->approve($case, $this->customer, 'Approved');

        $this->assertSame(OrderCancellation::STATUS_COMPLETED, $approved->status);
        $this->assertSame(Order::ORDER_STATUS_CANCELLED, $order->fresh()->order_status);
        $this->assertNotNull($approved->refund_id);
        $this->assertSame(1850.0, round((float) $approved->refund->amount, 2));
        $this->assertSame(Order::PAYMENT_STATUS_PAID, $order->fresh()->payment_status);
        $this->assertSame(1, StockMovement::where('type', StockMovement::TYPE_RETURN)->count());
        $this->assertNotNull($approved->stock_restored_at);
    }

    public function test_shipped_order_is_not_cancellable_by_default(): void
    {
        $order = $this->makeOrder([
            'order_status' => Order::ORDER_STATUS_SHIPPED,
            'stock_reduced' => true,
        ]);

        $evaluation = $this->service->evaluate($order, OrderCancellation::ROLE_CUSTOMER, $this->customer);

        $this->assertFalse($evaluation['allowed']);
        $this->assertStringContainsString('not available', $evaluation['blocked_reason']);
    }

    public function test_delivered_order_redirects_customer_to_return_flow(): void
    {
        $order = $this->makeOrder([
            'order_status' => Order::ORDER_STATUS_DELIVERED,
            'delivered_at' => now()->subDay(),
        ]);

        $evaluation = $this->service->evaluate($order, OrderCancellation::ROLE_CUSTOMER, $this->customer);

        $this->assertFalse($evaluation['allowed']);
        $this->assertTrue($evaluation['redirect_to_returns']);
    }

    public function test_duplicate_active_cancellation_is_blocked(): void
    {
        $order = $this->makeOrder([
            'order_status' => Order::ORDER_STATUS_CONFIRMED,
        ]);

        $this->service->request($order, $this->customer, OrderCancellation::ROLE_CUSTOMER, ['reason_code' => 'changed_mind']);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->request($order, $this->customer, OrderCancellation::ROLE_CUSTOMER, ['reason_code' => 'changed_mind']);
    }

    public function test_customer_cancellation_limit_blocks_new_requests_when_configured(): void
    {
        $settings = $this->settings();
        $settings->update([
            'max_cancellations' => 1,
            'limit_exceeded_action' => 'block',
            'limit_period_days' => 30,
        ]);

        $previousOrder = $this->makeOrder(['order_status' => Order::ORDER_STATUS_CANCELLED]);
        OrderCancellation::create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $previousOrder->id,
            'cancellation_number' => 'CAN-TEST-0001',
            'requested_by_type' => User::class,
            'requested_by_id' => $this->customer->id,
            'requested_by_role' => OrderCancellation::ROLE_CUSTOMER,
            'reason_code' => 'changed_mind',
            'reason_label' => 'Changed my mind',
            'status' => OrderCancellation::STATUS_COMPLETED,
            'mode' => CancellationSetting::MODE_INSTANT,
            'requested_at' => now()->subDay(),
            'completed_at' => now()->subDay(),
        ]);

        $order = $this->makeOrder();

        $evaluation = $this->service->evaluate($order, OrderCancellation::ROLE_CUSTOMER, $this->customer);

        $this->assertFalse($evaluation['allowed']);
        $this->assertTrue($evaluation['limit_exceeded']);
        $this->assertSame(1, $evaluation['used_cancellations']);
    }

    public function test_customer_cancellation_limit_falls_back_to_approval_when_allowed(): void
    {
        $settings = $this->settings();
        $settings->update([
            'max_cancellations' => 1,
            'limit_exceeded_action' => 'require_approval',
            'allow_request_after_limit' => true,
        ]);

        $previousOrder = $this->makeOrder(['order_status' => Order::ORDER_STATUS_CANCELLED]);
        OrderCancellation::create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $previousOrder->id,
            'cancellation_number' => 'CAN-TEST-0002',
            'requested_by_type' => User::class,
            'requested_by_id' => $this->customer->id,
            'requested_by_role' => OrderCancellation::ROLE_CUSTOMER,
            'status' => OrderCancellation::STATUS_COMPLETED,
            'requested_at' => now()->subDay(),
            'completed_at' => now()->subDay(),
        ]);

        $order = $this->makeOrder();

        $evaluation = $this->service->evaluate($order, OrderCancellation::ROLE_CUSTOMER, $this->customer);

        $this->assertTrue($evaluation['allowed']);
        $this->assertSame(CancellationSetting::MODE_APPROVAL, $evaluation['mode']);
    }

    public function test_merchant_cannot_cancel_delivered_order(): void
    {
        $order = $this->makeOrder([
            'order_status' => Order::ORDER_STATUS_DELIVERED,
            'delivered_at' => now(),
        ]);

        $evaluation = $this->service->evaluate($order, OrderCancellation::ROLE_MERCHANT);

        $this->assertFalse($evaluation['allowed']);
        $this->assertTrue($evaluation['redirect_to_returns']);
    }

    public function test_cancellation_is_blocked_after_return_stock_was_restored(): void
    {
        $order = $this->makeOrder(['order_status' => Order::ORDER_STATUS_CONFIRMED]);

        $order->returns()->create([
            'tenant_id' => $this->tenant->id,
            'rma_number' => 'RMA-TEST-0001',
            'requested_by_type' => User::class,
            'requested_by_id' => $this->customer->id,
            'status' => \App\Models\OrderReturn::STATUS_COMPLETED,
            'resolution' => \App\Models\OrderReturn::RESOLUTION_REFUND,
            'requested_at' => now(),
        ])->items()->create([
            'tenant_id' => $this->tenant->id,
            'order_item_id' => $order->items()->first()->id,
            'quantity' => 1,
            'condition' => 'resellable',
            'restock' => true,
            'stock_restored_at' => now(),
        ]);

        $evaluation = $this->service->evaluate($order, OrderCancellation::ROLE_CUSTOMER, $this->customer);

        $this->assertFalse($evaluation['allowed']);
        $this->assertStringContainsString('return workflow', $evaluation['blocked_reason']);
    }

    public function test_merchant_is_notified_when_customer_cancels_instantly(): void
    {
        config(['identity.use_accounts' => false]);

        $merchant = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cancellation Merchant',
            'email' => 'cancel-merchant@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $role = \App\Models\Role::withoutTenantScope()->firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
            'tenant_id' => $this->tenant->id,
        ]);
        $merchant->assignRole($role);

        $order = $this->makeOrder();

        $this->service->request($order, $this->customer, OrderCancellation::ROLE_CUSTOMER, [
            'reason_code' => 'changed_mind',
        ]);

        Notification::assertSentTo($merchant, AfterSalesStatusNotification::class);
    }
}
