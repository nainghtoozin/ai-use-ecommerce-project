<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StorefrontOrderCustomerTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;
    private Tenant $otherTenant;
    private Product $singleProduct;
    private Product $variableProduct;
    private ProductVariant $variant;
    private PaymentMethod $paymentMethod;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupMinimalSchema();
        $this->setupTestData();
    }

    private function setupMinimalSchema(): void
    {
        $tables = [
            'tenants', 'categories', 'products', 'product_variants',
            'orders', 'order_items', 'payment_methods', 'users',
        ];

        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                $this->markTestSkipped("Table {$table} not found. Run migrations first.");
            }
        }
    }

    private function setupTestData(): void
    {
        $this->tenant = Tenant::create([
            'name' => 'Test Store',
            'slug' => 'test-store',
            'store_url' => '/store/test-store',
            'status' => 'active',
        ]);

        $this->otherTenant = Tenant::create([
            'name' => 'Other Store',
            'slug' => 'other-store',
            'store_url' => '/store/other-store',
            'status' => 'active',
        ]);

        $this->singleProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Single Product',
            'type' => 'single',
            'price' => 100,
            'stock' => 50,
            'status' => 'active',
        ]);

        $this->variableProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Variable Product',
            'type' => 'variable',
            'price' => 200,
            'stock' => 0,
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->variableProduct->id,
            'name' => 'Size M',
            'price' => 200,
            'stock' => 30,
            'status' => 'active',
            'attributes' => ['size' => 'M'],
        ]);

        $this->paymentMethod = PaymentMethod::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Bank Transfer',
            'type' => 'bank_transfer',
            'is_active' => true,
        ]);

        $user = \App\Models\User::first() ?: \App\Models\User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
        ]);

        $this->order = Order::create([
            'user_id' => $user->id,
            'user_type' => get_class($user),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '1234567890',
            'address' => '123 Test St',
            'payment_method_id' => $this->paymentMethod->id,
            'subtotal' => 100,
            'total_amount' => 100,
            'delivery_fee' => 0,
            'discount_amount' => 0,
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
            'order_status' => Order::ORDER_STATUS_PENDING,
        ]);

        OrderItem::create([
            'order_id' => $this->order->id,
            'product_id' => $this->singleProduct->id,
            'quantity' => 1,
            'price' => 100,
        ]);
    }

    /** @test */
    public function customer_can_view_own_orders(): void
    {
        $user = $this->order->user;

        $response = $this->actingAs($user)->get('/orders');

        $response->assertOk();
        $response->assertSee('Single Product');
    }

    /** @test */
    public function customer_cannot_view_another_customers_order(): void
    {
        $otherUser = \App\Models\User::create([
            'name' => 'Other User',
            'email' => 'other@example.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($otherUser)->get('/orders/' . $this->order->id);

        $response->assertNotFound();
    }

    /** @test */
    public function order_history_shows_correct_totals(): void
    {
        $user = $this->order->user;

        $response = $this->actingAs($user)->get('/orders');

        $response->assertOk();
        $response->assertSee('100');
    }

    /** @test */
    public function order_detail_loads(): void
    {
        $user = $this->order->user;

        $response = $this->actingAs($user)->get('/orders/' . $this->order->id);

        $response->assertOk();
        $response->assertSee('Single Product');
    }

    /** @test */
    public function variable_order_preserves_variant_information(): void
    {
        $variantOrder = Order::create([
            'user_id' => $this->order->user_id,
            'user_type' => $this->order->user_type,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'phone' => '1234567890',
            'address' => '123 Test St',
            'payment_method_id' => $this->paymentMethod->id,
            'subtotal' => 200,
            'total_amount' => 200,
            'delivery_fee' => 0,
            'discount_amount' => 0,
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
            'order_status' => Order::ORDER_STATUS_PENDING,
        ]);

        OrderItem::create([
            'order_id' => $variantOrder->id,
            'product_id' => $this->variableProduct->id,
            'variant_id' => $this->variant->id,
            'quantity' => 1,
            'price' => 200,
        ]);

        $user = $this->order->user;

        $response = $this->actingAs($user)->get('/orders/' . $variantOrder->id);

        $response->assertOk();
        $response->assertSee('Variable Product');
    }

    /** @test */
    public function historical_price_remains_unchanged(): void
    {
        $this->singleProduct->update(['price' => 500]);

        $user = $this->order->user;

        $response = $this->actingAs($user)->get('/orders/' . $this->order->id);

        $response->assertOk();
        $response->assertSee('100');
    }

    /** @test */
    public function order_status_is_displayed_correctly(): void
    {
        $user = $this->order->user;

        $response = $this->actingAs($user)->get('/orders/' . $this->order->id);

        $response->assertOk();
        $response->assertSee('pending');
    }

    /** @test */
    public function payment_status_is_displayed_correctly(): void
    {
        $user = $this->order->user;

        $response = $this->actingAs($user)->get('/orders/' . $this->order->id);

        $response->assertOk();
        $response->assertSee('pending');
    }

    /** @test */
    public function unauthorized_order_access_is_rejected(): void
    {
        $otherUser = \App\Models\User::create([
            'name' => 'Unauthorized User',
            'email' => 'unauthorized@example.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($otherUser)->get('/orders/' . $this->order->id);

        $response->assertNotFound();
    }

    /** @test */
    public function internal_fields_are_not_exposed(): void
    {
        $user = $this->order->user;

        $response = $this->actingAs($user)->get('/orders/' . $this->order->id);

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringNotContainsString('cost_price', strtolower($content));
        $this->assertStringNotContainsString('supplier', strtolower($content));
    }

    /** @test */
    public function order_items_display_correctly(): void
    {
        $user = $this->order->user;

        $response = $this->actingAs($user)->get('/orders/' . $this->order->id);

        $response->assertOk();
        $response->assertSee('Single Product');
        $response->assertSee('100');
    }

    /** @test */
    public function combo_order_remains_compatible(): void
    {
        $comboProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Combo Product',
            'type' => 'combo',
            'price' => 150,
            'stock' => 20,
            'status' => 'active',
        ]);

        $comboOrder = Order::create([
            'user_id' => $this->order->user_id,
            'user_type' => $this->order->user_type,
            'first_name' => 'Combo',
            'last_name' => 'Buyer',
            'phone' => '1234567890',
            'address' => '123 Test St',
            'payment_method_id' => $this->paymentMethod->id,
            'subtotal' => 150,
            'total_amount' => 150,
            'delivery_fee' => 0,
            'discount_amount' => 0,
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
            'order_status' => Order::ORDER_STATUS_PENDING,
        ]);

        OrderItem::create([
            'order_id' => $comboOrder->id,
            'product_id' => $comboProduct->id,
            'quantity' => 1,
            'price' => 150,
        ]);

        $user = $this->order->user;

        $response = $this->actingAs($user)->get('/orders/' . $comboOrder->id);

        $response->assertOk();
        $response->assertSee('Combo Product');
    }

    /** @test */
    public function delivery_information_is_displayed(): void
    {
        $user = $this->order->user;

        $response = $this->actingAs($user)->get('/orders/' . $this->order->id);

        $response->assertOk();
        $response->assertSee('John');
        $response->assertSee('Doe');
        $response->assertSee('1234567890');
        $response->assertSee('123 Test St');
    }

    /** @test */
    public function payment_information_is_displayed(): void
    {
        $user = $this->order->user;

        $response = $this->actingAs($user)->get('/orders/' . $this->order->id);

        $response->assertOk();
        $response->assertSee('Bank Transfer');
    }

    /** @test */
    public function empty_order_history_works(): void
    {
        $emptyUser = \App\Models\User::create([
            'name' => 'Empty User',
            'email' => 'empty@example.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($emptyUser)->get('/orders');

        $response->assertOk();
        $response->assertSee('No orders yet');
    }

    /** @test */
    public function cancel_order_shows_when_cancellable(): void
    {
        $user = $this->order->user;

        $response = $this->actingAs($user)->get('/orders/' . $this->order->id);

        $response->assertOk();
        $response->assertSee('Cancel Order');
    }

    /** @test */
    public function invoice_totals_match_order_totals(): void
    {
        $user = $this->order->user;

        $response = $this->actingAs($user)->get('/orders/' . $this->order->id);

        $response->assertOk();
        $response->assertSee(number_format($this->order->total_amount, 2));
    }
}
