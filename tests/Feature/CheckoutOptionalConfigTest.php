<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\City;
use App\Models\CodRule;
use App\Models\DeliveryPricing;
use App\Models\DeliveryService;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\Township;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutOptionalConfigTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Tenant $otherTenant;
    private City $city;
    private Township $township;
    private User $user;
    private Product $product;
    private PaymentMethod $bankMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Bare Store', 'slug' => 'bare-store',
            'store_url' => '/store/bare-store', 'status' => 'active',
        ]);
        $this->otherTenant = Tenant::create([
            'name' => 'Other Store', 'slug' => 'bare-other',
            'store_url' => '/store/bare-other', 'status' => 'active',
        ]);
        Tenant::setCurrent($this->tenant);

        $this->city = City::create(['name' => 'Yangon', 'is_active' => true]);
        $this->township = Township::create([
            'city_id' => $this->city->id, 'name' => 'Bahan',
            'postal_code' => '11201', 'delivery_fee' => 1000, 'is_active' => true,
        ]);

        $category = Category::create(['name' => 'Bare Cat', 'slug' => 'bare-cat']);
        $this->product = Product::create([
            'name' => 'Bare Widget', 'type' => 'single', 'price' => 5000,
            'stock' => 50, 'status' => 'active', 'category_id' => $category->id,
        ]);
        StockMovement::create([
            'product_id' => $this->product->id, 'product_variant_id' => null,
            'type' => StockMovement::TYPE_OPENING_STOCK, 'quantity' => 50,
        ]);

        $this->bankMethod = PaymentMethod::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Bank Transfer',
            'type' => 'bank_transfer', 'account_name' => 'A', 'account_number' => '1',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Bare Buyer',
            'email' => 'bare-buyer@test.com', 'password' => bcrypt('password'),
            'status' => 'active', 'allow_cod' => true,
        ]);
    }

    private function makeService(Tenant $tenant, string $name, int $fee, int $minDays = 1, int $maxDays = 3): DeliveryService
    {
        $service = DeliveryService::withoutTenantScope()->create([
            'tenant_id' => $tenant->id, 'name' => $name, 'code' => strtolower(str_replace(' ', '_', $name)),
            'base_fee' => $fee, 'min_days' => $minDays, 'max_days' => $maxDays, 'is_active' => true,
        ]);
        DeliveryPricing::create([
            'delivery_service_id' => $service->id, 'township_id' => $this->township->id,
            'min_days' => $minDays, 'max_days' => $maxDays, 'is_active' => true,
        ]);

        return $service;
    }

    private function startV2Checkout(): void
    {
        $this->actingAs($this->user, 'accounts');
        app()->instance('current.tenant', $this->tenant);
        $this->post('/store/bare-store/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);
    }

    private function v2Payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Bare', 'last_name' => 'Buyer', 'phone' => '09911122233',
            'address' => 'No. 1, Bare Road',
            'city_id' => $this->city->id, 'township_id' => $this->township->id,
            'postal_code' => '11201',
            'payment_method_id' => $this->bankMethod->id,
        ], $overrides);
    }

    private function latestOrder(): ?Order
    {
        return Order::where('tenant_id', $this->tenant->id)->latest()->first();
    }

    /** @test */
    public function bare_tenant_can_place_v2_order(): void
    {
        $this->startV2Checkout();

        $this->post('/store/bare-store/checkout', $this->v2Payload());

        $order = $this->latestOrder();
        $this->assertNotNull($order);
        $this->assertEquals(5000, (float) $order->subtotal);
        $this->assertEquals(1000, (float) $order->delivery_fee);
        $this->assertEquals(6000, (float) $order->total_amount);
        $this->assertNull($order->delivery_service_id);
        $this->assertNull($order->delivery_days_min);
        $this->assertNull($order->delivery_days_max);
        $this->assertNull($order->packaging_id);
        $this->assertNull($order->cod_fee);
    }

    /** @test */
    public function bare_tenant_can_place_legacy_order(): void
    {
        $this->actingAs($this->user)
            ->withSession(['cart' => ['k1' => ['product_id' => $this->product->id, 'quantity' => 1]]])
            ->post('/checkout', $this->v2Payload());

        $order = $this->latestOrder();
        $this->assertNotNull($order);
        $this->assertEquals(6000, (float) $order->total_amount);
        $this->assertEquals(1000, (float) $order->delivery_fee);
    }

    /** @test */
    public function no_service_or_packaging_does_not_hide_payment_methods_v2(): void
    {
        $this->startV2Checkout();

        $response = $this->get('/store/bare-store/checkout');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('packagingOptions', [])
            ->where('deliveryServices', [])
            ->where('paymentMethods', fn ($methods) => collect($methods)->pluck('type')->contains('bank_transfer')));
    }

    /** @test */
    public function no_cod_rule_only_disables_cod_v2(): void
    {
        PaymentMethod::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Cash on Delivery',
            'type' => 'cod', 'is_active' => true,
        ]);
        $this->startV2Checkout();

        $response = $this->get('/store/bare-store/checkout');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('paymentMethods', fn ($methods) => collect($methods)->pluck('type')->contains('bank_transfer')));
    }

    /** @test */
    public function no_cod_rule_only_disables_cod_legacy(): void
    {
        $this->actingAs($this->user)
            ->withSession(['cart' => ['k1' => ['product_id' => $this->product->id, 'quantity' => 1]]])
            ->get('/checkout')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('paymentMethods', fn ($methods) => collect($methods)->pluck('type')->contains('bank_transfer')));
    }

    /** @test */
    public function one_service_order_uses_its_fee_and_days(): void
    {
        $service = $this->makeService($this->tenant, 'Fast', 1500, 1, 2);
        $this->startV2Checkout();

        $this->post('/store/bare-store/checkout', $this->v2Payload([
            'delivery_service_id' => $service->id,
        ]));

        $order = $this->latestOrder();
        $this->assertNotNull($order);
        $this->assertEquals($service->id, (int) $order->delivery_service_id);
        $this->assertEquals(2500, (float) $order->delivery_fee);
        $this->assertEquals(7500, (float) $order->total_amount);
        $this->assertEquals(1, $order->delivery_days_min);
        $this->assertEquals(2, $order->delivery_days_max);
    }

    /** @test */
    public function multiple_services_customer_choice_is_honored(): void
    {
        $this->makeService($this->tenant, 'Fast', 1500, 1, 2);
        $slow = $this->makeService($this->tenant, 'Slow', 500, 3, 5);
        $this->startV2Checkout();

        $response = $this->post('/store/bare-store/checkout', $this->v2Payload([
            'delivery_service_id' => $slow->id,
        ]));

        $response->assertRedirect();
        $order = $this->latestOrder();
        $this->assertNotNull($order);
        $this->assertEquals($slow->id, (int) $order->delivery_service_id);
        $this->assertEquals(1500, (float) $order->delivery_fee);
        $this->assertEquals(6500, (float) $order->total_amount);
        $this->assertEquals(3, $order->delivery_days_min);
        $this->assertEquals(5, $order->delivery_days_max);
    }

    /** @test */
    public function foreign_service_is_rejected_and_tenant_excluded_from_lists(): void
    {
        $foreign = $this->makeService($this->otherTenant, 'Foreign', 9000);
        $this->startV2Checkout();

        $response = $this->post('/store/bare-store/checkout', $this->v2Payload([
            'delivery_service_id' => $foreign->id,
        ]));

        $response->assertRedirect();
        $this->assertNull($this->latestOrder());

        $list = $this->get('/store/bare-store/checkout');
        $list->assertOk();
        $list->assertInertia(fn ($page) => $page
            ->where('deliveryServices', fn ($services) => !collect($services)->pluck('id')->contains($foreign->id)));
    }

    /** @test */
    public function cod_rule_still_gates_only_cod_for_bare_tenant(): void
    {
        $codMethod = PaymentMethod::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Cash on Delivery',
            'type' => 'cod', 'is_active' => true,
        ]);
        CodRule::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Bare COD',
            'max_order_amount' => 100, 'is_active' => true,
        ]);
        $this->startV2Checkout();

        $response = $this->post('/store/bare-store/checkout', $this->v2Payload([
            'payment_method_id' => $codMethod->id,
        ]));

        $response->assertRedirect();
        $this->assertNull($this->latestOrder());

        $this->startV2Checkout();
        $this->post('/store/bare-store/checkout', $this->v2Payload());

        $this->assertNotNull($this->latestOrder());
    }
}
