<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderCancellation;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OrderCancellationService;
use App\Services\OrderService;
use App\Services\OrderStatusTransitionService;
use App\Services\OrderWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrderWorkflowCancellationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $customer;
    private Product $product;
    private OrderService $orderService;
    private OrderCancellationService $cancellationService;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Notification::fake();

        $this->tenant = Tenant::create([
            'name' => 'Workflow Store',
            'slug' => 'workflow-store',
            'store_url' => '/store/workflow-store',
            'status' => 'active',
        ]);

        Tenant::setCurrent($this->tenant);
        $this->grantStorefrontEntitlement($this->tenant);

        $this->customer = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Workflow Customer',
            'email' => 'workflow@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $category = Category::create(['name' => 'Workflow', 'slug' => 'workflow']);

        $this->product = Product::create([
            'name' => 'Workflow Widget',
            'type' => 'single',
            'price' => 1000,
            'stock' => 50,
            'status' => 'active',
            'category_id' => $category->id,
        ]);

        $this->orderService = app(OrderService::class);
        $this->cancellationService = app(OrderCancellationService::class);
    }

    private function makeOrder(array $overrides = [], int $quantity = 2): Order
    {
        $order = Order::create(array_merge([
            'user_id' => $this->customer->id,
            'user_type' => User::class,
            'first_name' => 'Workflow',
            'last_name' => 'Customer',
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

        return $order->fresh();
    }

    private function setMode(string $mode): void
    {
        $settings = $this->tenant->settings ?: [];
        $settings['workflow_mode'] = $mode;
        $this->tenant->settings = $settings;
        $this->tenant->save();
        Tenant::setCurrent($this->tenant->fresh());
    }

    public function test_default_workflow_mode_is_standard(): void
    {
        $this->assertSame('standard', app(OrderWorkflow::class)->workflowMode());
    }


    public function test_simplified_mode_accepts_pending_order_and_records_confirmed_internally(): void
    {
        $this->setMode('simplified');

        $order = $this->makeOrder();

        $result = $this->orderService->acceptAndProcess($order);

        $this->assertSame(Order::ORDER_STATUS_PROCESSING, $result->order_status);
        $this->assertTrue((bool) $result->stock_reduced);
    }

    public function test_simplified_mode_deducts_stock_exactly_once(): void
    {
        $this->setMode('simplified');

        $order = $this->makeOrder([], 2);

        $this->orderService->acceptAndProcess($order);

        $this->assertGreaterThan(0, \App\Models\StockMovement::where('type', \App\Models\StockMovement::TYPE_SALE)->count());
    }

    public function test_cod_can_be_accepted_while_payment_pending(): void
    {
        $this->setMode('simplified');

        $order = $this->makeOrder([
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
        ]);

        // attach cod payment method
        $pm = \App\Models\PaymentMethod::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'COD',
            'type' => 'cod',
            'status' => 'active',
        ]);
        $order->update(['payment_method_id' => $pm->id]);

        $result = $this->orderService->acceptAndProcess($order->fresh());

        $this->assertSame(Order::ORDER_STATUS_PROCESSING, $result->order_status);
        $this->assertSame(Order::PAYMENT_STATUS_PENDING, $result->payment_status);
    }

    public function test_non_cod_payment_validation_remains_enforced(): void
    {
        $this->setMode('simplified');

        $pm = \App\Models\PaymentMethod::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Bank Transfer',
            'type' => 'bank_transfer',
            'status' => 'active',
        ]);

        $order = $this->makeOrder([
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
            'payment_method_id' => $pm->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $this->orderService->acceptAndProcess($order);
    }

    public function test_shipped_customer_cancellation_is_blocked(): void
    {
        $order = $this->makeOrder(['order_status' => Order::ORDER_STATUS_SHIPPED]);

        $evaluation = $this->cancellationService->evaluate($order, OrderCancellation::ROLE_CUSTOMER, $this->customer);

        $this->assertFalse($evaluation['allowed']);
    }

    public function test_shipped_merchant_cancellation_is_blocked(): void
    {
        $order = $this->makeOrder(['order_status' => Order::ORDER_STATUS_SHIPPED]);

        $evaluation = $this->cancellationService->evaluate($order, OrderCancellation::ROLE_MERCHANT);

        $this->assertFalse($evaluation['allowed']);
    }

    public function test_delivered_cancellation_is_blocked(): void
    {
        $order = $this->makeOrder(['order_status' => Order::ORDER_STATUS_DELIVERED, 'delivered_at' => now()]);

        $evaluation = $this->cancellationService->evaluate($order, OrderCancellation::ROLE_MERCHANT);

        $this->assertFalse($evaluation['allowed']);
    }

    public function test_approval_after_shipment_is_blocked(): void
    {
        $order = $this->makeOrder(['order_status' => Order::ORDER_STATUS_PROCESSING]);
        $case = $this->cancellationService->request($order, $this->customer, OrderCancellation::ROLE_CUSTOMER, [
            'reason_code' => 'changed_mind',
        ]);

        // ship the order before approval
        $order->update(['order_status' => Order::ORDER_STATUS_SHIPPED]);

        $this->expectException(\InvalidArgumentException::class);

        $this->cancellationService->approve($case, $this->customer, 'ok');
    }

    public function test_null_delivered_at_fails_closed_for_return_eligibility(): void
    {
        $order = $this->makeOrder(['order_status' => Order::ORDER_STATUS_DELIVERED, 'delivered_at' => null]);

        $eligibility = app(\App\Services\OrderReturnService::class)->eligibility($order);

        $this->assertFalse($eligibility['allowed']);
    }

    public function test_workflow_mode_is_tenant_scoped(): void
    {
        $this->setMode('simplified');

        $other = Tenant::create([
            'name' => 'Other Store',
            'slug' => 'other-store',
            'store_url' => '/store/other-store',
            'status' => 'active',
        ]);
        $this->grantStorefrontEntitlement($other);

        Tenant::setCurrent($other);

        $this->assertSame('standard', app(OrderWorkflow::class)->workflowMode());
    }
}
