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
use App\Services\CodEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CodApplyToTotalTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private City $city;
    private Township $township;
    private User $user;
    private Product $product;
    private PaymentMethod $codMethod;
    private PaymentMethod $bankMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'COD Store', 'slug' => 'cod-store',
            'store_url' => '/store/cod-store', 'status' => 'active',
        ]);
        Tenant::setCurrent($this->tenant);

        $this->city = City::create(['name' => 'Yangon', 'is_active' => true]);
        $this->township = Township::create([
            'city_id' => $this->city->id, 'name' => 'Bahan',
            'postal_code' => '11201', 'delivery_fee' => 1000, 'is_active' => true,
        ]);

        $category = Category::create(['name' => 'COD Cat', 'slug' => 'cod-cat']);
        $this->product = Product::create([
            'name' => 'COD Widget', 'type' => 'single', 'price' => 5000,
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
            'tenant_id' => $this->tenant->id, 'name' => 'COD Buyer',
            'email' => 'cod-buyer@test.com', 'password' => bcrypt('password'),
            'status' => 'active', 'allow_cod' => true,
        ]);
    }

    private function makeRule(array $overrides = []): CodRule
    {
        return CodRule::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'COD Rule',
            'is_active' => true,
        ], $overrides));
    }

    private function checkoutWithCod(): void
    {
        $this->actingAs($this->user, 'accounts');
        app()->instance('current.tenant', $this->tenant);
        $this->post('/store/cod-store/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);
    }

    private function payload(): array
    {
        return [
            'first_name' => 'COD', 'last_name' => 'Buyer', 'phone' => '09911122233',
            'address' => 'No. 1, COD Road',
            'city_id' => $this->city->id, 'township_id' => $this->township->id,
            'postal_code' => '11201',
            'payment_method_id' => $this->codMethod->id,
        ];
    }

    /** @test */
    public function cod_rule_has_no_fee_configuration(): void
    {
        $fillable = (new CodRule)->getFillable();

        $this->assertNotContains('cod_fee', $fillable);
        $this->assertNotContains('apply_cod_fee_to_total', $fillable);

        $rule = $this->makeRule();

        $this->assertArrayNotHasKey('cod_fee', $rule->getAttributes());
        $this->assertArrayNotHasKey('apply_cod_fee_to_total', $rule->getAttributes());
    }

    /** @test */
    public function eligibility_service_exposes_no_fee_calculation(): void
    {
        $this->assertFalse(method_exists(CodEligibilityService::class, 'getCodFee'));
        $this->assertFalse(method_exists(CodEligibilityService::class, 'getChargedCodFee'));
        $this->assertFalse(method_exists(CodEligibilityService::class, 'shouldApplyCodFeeToTotal'));
    }

    /** @test */
    public function cod_order_has_no_additional_charge(): void
    {
        $this->makeRule();
        $this->checkoutWithCod();

        $this->post('/store/cod-store/checkout', $this->payload());

        $order = Order::where('tenant_id', $this->tenant->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertNull($order->cod_fee);
        $this->assertEquals(6000, (float) $order->total_amount);
    }

    /** @test */
    public function forged_fee_is_ignored_and_no_charge_added(): void
    {
        $this->makeRule();
        $this->checkoutWithCod();

        $payload = $this->payload();
        $payload['cod_fee'] = 9999;

        $this->post('/store/cod-store/checkout', $payload);

        $order = Order::where('tenant_id', $this->tenant->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertNull($order->cod_fee);
        $this->assertEquals(6000, (float) $order->total_amount);
    }

    /** @test */
    public function quote_has_no_fee_and_matches_store_total(): void
    {
        $this->makeRule();

        $response = $this->postJson('/store/cod-store/checkout/quote', [
            'city_id' => $this->city->id,
            'township_id' => $this->township->id,
            'payment_method_id' => $this->codMethod->id,
        ]);

        $response->assertOk();
        $response->assertJsonMissingPath('cod.fee');
        $this->assertTrue($response->json('cod.available'));
    }

    /** @test */
    public function first_eligible_rule_still_determines_availability(): void
    {
        $this->makeRule(['name' => 'Low Range', 'max_order_amount' => 1000]);
        $this->makeRule(['name' => 'Open Range']);

        $service = app(CodEligibilityService::class);

        $this->assertTrue($service->isCodAvailable($this->codMethod, null, $this->city->id, 6000));
        $this->assertEquals('Open Range', $service->getFirstEligibleRule($this->city->id, 6000)->name);
    }

    /** @test */
    public function non_cod_order_total_unchanged(): void
    {
        $this->makeRule();
        $this->checkoutWithCod();

        $payload = $this->payload();
        $payload['payment_method_id'] = $this->bankMethod->id;
        $this->post('/store/cod-store/checkout', $payload);

        $order = Order::where('tenant_id', $this->tenant->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertNull($order->cod_fee);
        $this->assertEquals(6000, (float) $order->total_amount);
    }
}
