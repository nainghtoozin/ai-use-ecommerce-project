<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\City;
use App\Models\CodRule;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\Township;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CodOrderE2ETest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private City $yangon;
    private City $mandalay;
    private Township $bahan;
    private Township $chanmyathazi;
    private User $user;
    private Product $product;
    private PaymentMethod $codMethod;
    private PaymentMethod $bankMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'COD E2E Store', 'slug' => 'cod-e2e-store',
            'store_url' => '/store/cod-e2e-store', 'status' => 'active',
        ]);
        Tenant::setCurrent($this->tenant);

        $this->yangon = City::create(['name' => 'Yangon', 'is_active' => true]);
        $this->mandalay = City::create(['name' => 'Mandalay', 'is_active' => true]);
        $this->bahan = Township::create([
            'city_id' => $this->yangon->id, 'name' => 'Bahan',
            'postal_code' => '11201', 'delivery_fee' => 1000, 'is_active' => true,
        ]);
        $this->chanmyathazi = Township::create([
            'city_id' => $this->mandalay->id, 'name' => 'Chanmyathazi',
            'postal_code' => '05001', 'delivery_fee' => 2000, 'is_active' => true,
        ]);

        $category = Category::create(['name' => 'E2E Cat', 'slug' => 'e2e-cat']);
        $this->product = Product::create([
            'name' => 'E2E Widget', 'type' => 'single', 'price' => 5000,
            'stock' => 50, 'status' => 'active', 'category_id' => $category->id,
        ]);
        StockMovement::create([
            'product_id' => $this->product->id, 'product_variant_id' => null,
            'type' => StockMovement::TYPE_OPENING_STOCK, 'quantity' => 50,
        ]);

        $this->codMethod = PaymentMethod::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Cash on Delivery',
            'type' => 'cod', 'is_active' => true,
        ]);
        $this->bankMethod = PaymentMethod::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Bank Transfer',
            'type' => 'bank_transfer', 'account_name' => 'A', 'account_number' => '1',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'E2E Buyer',
            'email' => 'e2e-buyer@test.com', 'password' => bcrypt('password'),
            'status' => 'active', 'allow_cod' => true,
        ]);
    }

    private function makeRule(array $overrides = []): CodRule
    {
        return CodRule::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'E2E COD Rule',
            'is_active' => true,
        ], $overrides));
    }

    private function startCheckout(): void
    {
        $this->actingAs($this->user, 'accounts');
        app()->instance('current.tenant', $this->tenant);
        $this->post('/store/cod-e2e-store/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'E2E', 'last_name' => 'Buyer', 'phone' => '09911122233',
            'address' => 'No. 1, E2E Road',
            'city_id' => $this->yangon->id, 'township_id' => $this->bahan->id,
            'postal_code' => '11201',
            'payment_method_id' => $this->codMethod->id,
        ], $overrides);
    }

    private function latestOrder(): ?Order
    {
        return Order::where('tenant_id', $this->tenant->id)->latest()->first();
    }

    /** @test */
    public function eligible_cod_order_is_created_without_additional_charge(): void
    {
        $this->makeRule();
        $this->startCheckout();

        $this->post('/store/cod-e2e-store/checkout', $this->payload());

        $order = $this->latestOrder();
        $this->assertNotNull($order);
        $this->assertEquals($this->codMethod->id, (int) $order->payment_method_id);
        $this->assertNull($order->cod_fee);
        $this->assertEquals(6000, (float) $order->total_amount);
        $this->assertEquals(Order::PAYMENT_STATUS_PENDING, $order->payment_status);
    }

    /** @test */
    public function cod_ineligible_by_order_amount_is_rejected(): void
    {
        $this->makeRule(['min_order_amount' => 50000]);
        $this->startCheckout();

        $response = $this->post('/store/cod-e2e-store/checkout', $this->payload());

        $response->assertRedirect();
        $this->assertNull($this->latestOrder());
    }

    /** @test */
    public function cod_ineligible_by_city_is_rejected(): void
    {
        $this->makeRule(['allowed_city_ids' => [$this->yangon->id]]);
        $this->startCheckout();

        $response = $this->post('/store/cod-e2e-store/checkout', $this->payload([
            'city_id' => $this->mandalay->id,
            'township_id' => $this->chanmyathazi->id,
            'postal_code' => '05001',
        ]));

        $response->assertRedirect();
        $this->assertNull($this->latestOrder());
    }

    /** @test */
    public function crafted_cod_request_cannot_bypass_eligibility(): void
    {
        $this->makeRule(['max_order_amount' => 100]);
        $this->startCheckout();

        $payload = $this->payload();
        $payload['cod_fee'] = 0;
        $payload['total_amount'] = 100;

        $response = $this->post('/store/cod-e2e-store/checkout', $payload);

        $response->assertRedirect();
        $this->assertNull($this->latestOrder());
    }

    /** @test */
    public function client_cannot_manipulate_cod_fee(): void
    {
        $this->makeRule();
        $this->startCheckout();

        $payload = $this->payload();
        $payload['cod_fee'] = 1;

        $this->post('/store/cod-e2e-store/checkout', $payload);

        $order = $this->latestOrder();
        $this->assertNotNull($order);
        $this->assertNull($order->cod_fee);
        $this->assertEquals(6000, (float) $order->total_amount);
    }

    /** @test */
    public function non_cod_order_regression(): void
    {
        $this->makeRule();
        $this->startCheckout();

        $payload = $this->payload();
        $payload['payment_method_id'] = $this->bankMethod->id;
        $this->post('/store/cod-e2e-store/checkout', $payload);

        $order = $this->latestOrder();
        $this->assertNotNull($order);
        $this->assertEquals($this->bankMethod->id, (int) $order->payment_method_id);
        $this->assertNull($order->cod_fee);
        $this->assertEquals(6000, (float) $order->total_amount);
        $this->assertEquals(Order::PAYMENT_STATUS_PENDING, $order->payment_status);
    }
}
