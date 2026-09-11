<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\CodRule;
use App\Models\DeliveryPricing;
use App\Models\DeliveryService;
use App\Models\PackagingOption;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\Township;
use App\Models\User;
use App\Services\CodEligibilityService;
use App\Services\DeliveryFeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutV2ArchitectureTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Tenant $otherTenant;
    private Category $category;
    private Brand $brand;
    private City $city;
    private Township $township;
    private Product $singleProduct;
    private Product $variableProduct;
    private ProductVariant $variant;
    private PaymentMethod $codPaymentMethod;
    private PaymentMethod $bankPaymentMethod;
    private User $userWithCod;
    private User $userWithoutCod;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupTestData();
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

        $this->category = Category::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Electronics',
            'slug' => 'electronics',
            'is_active' => true,
        ]);

        $this->brand = Brand::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'TestBrand',
            'slug' => 'testbrand',
            'is_active' => true,
        ]);

        $this->city = City::create([
            'tenant_id' => null,
            'name' => 'Yangon',
            'delivery_fee' => 1000,
            'is_active' => true,
        ]);

        $this->township = Township::create([
            'tenant_id' => null,
            'city_id' => $this->city->id,
            'name' => 'Hlaing',
            'postal_code' => '11041',
            'is_active' => true,
        ]);

        $this->singleProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Single Product',
            'type' => 'single',
            'price' => 100,
            'stock' => 50,
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'status' => 'active',
        ]);

        $this->variableProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Variable Product',
            'type' => 'variable',
            'price' => 200,
            'stock' => 0,
            'category_id' => $this->category->id,
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->variableProduct->id,
            'price' => 200,
            'stock' => 30,
            'status' => 'active',
            'attributes' => ['size' => 'M'],
        ]);

        $this->codPaymentMethod = PaymentMethod::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cash on Delivery',
            'type' => 'cod',
            'is_active' => true,
        ]);

        $this->bankPaymentMethod = PaymentMethod::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Bank Transfer',
            'type' => 'bank_transfer',
            'account_name' => 'Test Account',
            'account_number' => '123456789',
            'is_active' => true,
        ]);

        $this->userWithCod = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'User With COD',
            'email' => 'cod@example.com',
            'password' => bcrypt('password'),
            'allow_cod' => true,
            'is_owner' => true,
        ]);

        $this->userWithoutCod = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'User Without COD',
            'email' => 'nocod@example.com',
            'password' => bcrypt('password'),
            'allow_cod' => false,
            'is_owner' => true,
        ]);
    }

    private function addToCart(int $productId, int $quantity, ?int $variantId = null): \Illuminate\Testing\TestResponse
    {
        return $this->post('/cart', [
            'product_id' => $productId,
            'variant_id' => $variantId,
            'quantity' => $quantity,
        ]);
    }

    /** @test */
    public function delivery_service_can_be_created(): void
    {
        $service = DeliveryService::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Standard Delivery',
            'code' => 'standard',
            'base_fee' => 1500,
            'min_days' => 2,
            'max_days' => 5,
            'is_active' => true,
        ]);

        $this->assertNotNull($service->id);
        $this->assertEquals('Standard Delivery', $service->name);
        $this->assertEquals(1500, $service->base_fee);
        $this->assertEquals(2, $service->min_days);
        $this->assertEquals(5, $service->max_days);
    }

    /** @test */
    public function delivery_service_eta_label_formats_correctly(): void
    {
        $service1 = DeliveryService::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Same Day',
            'code' => 'same_day',
            'base_fee' => 3000,
            'min_days' => 1,
            'max_days' => 1,
            'is_active' => true,
        ]);

        $service2 = DeliveryService::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Standard',
            'code' => 'standard',
            'base_fee' => 1500,
            'min_days' => 2,
            'max_days' => 5,
            'is_active' => true,
        ]);

        $this->assertEquals('1 day', $service1->eta_label);
        $this->assertEquals('2-5 days', $service2->eta_label);
    }

    /** @test */
    public function delivery_pricing_overrides_base_fee_per_city(): void
    {
        $service = DeliveryService::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Standard Delivery',
            'code' => 'standard',
            'base_fee' => 1500,
            'min_days' => 2,
            'max_days' => 5,
            'is_active' => true,
        ]);

        DeliveryPricing::create([
            'delivery_service_id' => $service->id,
            'city_id' => $this->city->id,
            'fee' => 2000,
            'min_days' => 1,
            'max_days' => 3,
            'is_active' => true,
        ]);

        $this->assertEquals(2000, $service->getFeeForCity($this->city));
        $this->assertEquals(['min' => 1, 'max' => 3], $service->getDaysForCity($this->city));
    }

    /** @test */
    public function delivery_service_falls_back_to_base_fee_when_no_pricing(): void
    {
        $service = DeliveryService::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Standard Delivery',
            'code' => 'standard',
            'base_fee' => 1500,
            'min_days' => 2,
            'max_days' => 5,
            'is_active' => true,
        ]);

        $otherCity = City::create([
            'tenant_id' => null,
            'name' => 'Mandalay',
            'delivery_fee' => 2000,
            'is_active' => true,
        ]);

        $this->assertEquals(1500, $service->getFeeForCity($otherCity));
    }

    /** @test */
    public function delivery_services_are_tenant_scoped(): void
    {
        $serviceTenant1 = DeliveryService::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Tenant 1 Service',
            'code' => 't1_service',
            'base_fee' => 1000,
            'is_active' => true,
        ]);

        $serviceTenant2 = DeliveryService::create([
            'tenant_id' => $this->otherTenant->id,
            'name' => 'Tenant 2 Service',
            'code' => 't2_service',
            'base_fee' => 2000,
            'is_active' => true,
        ]);

        $tenant1Services = DeliveryService::where('tenant_id', $this->tenant->id)->get();
        $tenant2Services = DeliveryService::where('tenant_id', $this->otherTenant->id)->get();

        $this->assertTrue($tenant1Services->contains('id', $serviceTenant1->id));
        $this->assertFalse($tenant1Services->contains('id', $serviceTenant2->id));
        $this->assertTrue($tenant2Services->contains('id', $serviceTenant2->id));
        $this->assertFalse($tenant2Services->contains('id', $serviceTenant1->id));
    }

    /** @test */
    public function cod_rule_can_be_created(): void
    {
        $rule = CodRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Standard COD',
            'is_active' => true,
            'min_order_amount' => 1000,
            'max_order_amount' => 50000,
            'cod_fee' => 500,
            'apply_cod_fee_to_total' => true,
        ]);

        $this->assertNotNull($rule->id);
        $this->assertEquals(1000, $rule->min_order_amount);
        $this->assertEquals(50000, $rule->max_order_amount);
        $this->assertEquals(500, $rule->cod_fee);
    }

    /** @test */
    public function cod_rule_amount_eligibility_works(): void
    {
        $rule = CodRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Mid-range COD',
            'is_active' => true,
            'min_order_amount' => 5000,
            'max_order_amount' => 20000,
            'cod_fee' => 300,
        ]);

        $this->assertFalse($rule->isAmountEligible(4000));
        $this->assertTrue($rule->isAmountEligible(5000));
        $this->assertTrue($rule->isAmountEligible(10000));
        $this->assertTrue($rule->isAmountEligible(20000));
        $this->assertFalse($rule->isAmountEligible(25000));
    }

    /** @test */
    public function cod_rule_city_eligibility_works(): void
    {
        $rule = CodRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Yangon Only COD',
            'is_active' => true,
            'allowed_city_ids' => [$this->city->id],
            'cod_fee' => 200,
        ]);

        $otherCity = City::create([
            'tenant_id' => null,
            'name' => 'Mandalay',
            'delivery_fee' => 2000,
            'is_active' => true,
        ]);

        $this->assertTrue($rule->isCityEligible($this->city->id));
        $this->assertFalse($rule->isCityEligible($otherCity->id));
        $this->assertTrue($rule->isCityEligible(null));
    }

    /** @test */
    public function cod_rule_excluded_cities_work(): void
    {
        $otherCity = City::create([
            'tenant_id' => null,
            'name' => 'Mandalay',
            'delivery_fee' => 2000,
            'is_active' => true,
        ]);

        $rule = CodRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Exclude Mandalay',
            'is_active' => true,
            'excluded_city_ids' => [$otherCity->id],
            'cod_fee' => 200,
        ]);

        $this->assertTrue($rule->isCityEligible($this->city->id));
        $this->assertFalse($rule->isCityEligible($otherCity->id));
    }

    /** @test */
    public function cod_eligibility_service_respects_user_block(): void
    {
        \App\Models\Tenant::setCurrent($this->tenant);

        $codMethod = PaymentMethod::where('type', 'cod')->first();

        $service = app(CodEligibilityService::class);

        $this->assertTrue($service->isCodAvailable($codMethod, $this->userWithCod, null, 10000));
        $this->assertFalse($service->isCodAvailable($codMethod, $this->userWithoutCod, null, 10000));
        $this->assertTrue($service->isCodAvailable($codMethod, null, null, 10000));
    }

    /** @test */
    public function cod_eligibility_service_uses_rules_when_present(): void
    {
        \App\Models\Tenant::setCurrent($this->tenant);

        CodRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'High Value Only',
            'is_active' => true,
            'min_order_amount' => 50000,
            'cod_fee' => 1000,
        ]);

        $codMethod = PaymentMethod::where('type', 'cod')->first();
        $service = app(CodEligibilityService::class);

        $this->assertFalse($service->isCodAvailable($codMethod, $this->userWithCod, null, 10000));
        $this->assertTrue($service->isCodAvailable($codMethod, $this->userWithCod, null, 60000));
    }

    /** @test */
    public function cod_eligibility_service_returns_cod_fee(): void
    {
        \App\Models\Tenant::setCurrent($this->tenant);

        CodRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Standard COD Fee',
            'is_active' => true,
            'cod_fee' => 500,
        ]);

        CodRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'High Value Fee',
            'is_active' => true,
            'min_order_amount' => 50000,
            'cod_fee' => 1000,
        ]);

        $service = app(CodEligibilityService::class);

        $this->assertEquals(500, $service->getCodFee(null, 10000));
        $this->assertEquals(500, $service->getCodFee(null, 60000));
    }

    /** @test */
    public function packaging_option_can_be_created(): void
    {
        $option = PackagingOption::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Gift Wrapping',
            'code' => 'gift_wrap',
            'fee' => 500,
            'is_active' => true,
        ]);

        $this->assertNotNull($option->id);
        $this->assertEquals('Gift Wrapping', $option->name);
        $this->assertEquals(500, $option->fee);
    }

    /** @test */
    public function packaging_options_are_tenant_scoped(): void
    {
        $option1 = PackagingOption::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Box',
            'code' => 'box',
            'fee' => 200,
            'is_active' => true,
        ]);

        $option2 = PackagingOption::create([
            'tenant_id' => $this->otherTenant->id,
            'name' => 'Premium Box',
            'code' => 'premium_box',
            'fee' => 500,
            'is_active' => true,
        ]);

        $tenant1Options = PackagingOption::where('tenant_id', $this->tenant->id)->get();
        $tenant2Options = PackagingOption::where('tenant_id', $this->otherTenant->id)->get();

        $this->assertTrue($tenant1Options->contains('id', $option1->id));
        $this->assertFalse($tenant1Options->contains('id', $option2->id));
        $this->assertTrue($tenant2Options->contains('id', $option2->id));
    }

    /** @test */
    public function delivery_fee_service_returns_fallback_when_no_services(): void
    {
        $service = app(DeliveryFeeService::class);

        $fee = $service->resolveDeliveryFee($this->city);
        $this->assertEquals(1000, $fee);
    }

    /** @test */
    public function delivery_fee_service_uses_service_when_available(): void
    {
        $deliveryService = DeliveryService::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Express',
            'code' => 'express',
            'base_fee' => 2500,
            'min_days' => 1,
            'max_days' => 1,
            'is_active' => true,
        ]);

        $service = app(DeliveryFeeService::class);

        $fee = $service->resolveDeliveryFee($this->city);
        $this->assertEquals(2500, $fee);
    }

    /** @test */
    public function delivery_fee_service_uses_pricing_overrides(): void
    {
        $deliveryService = DeliveryService::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Express',
            'code' => 'express',
            'base_fee' => 2500,
            'min_days' => 1,
            'max_days' => 1,
            'is_active' => true,
        ]);

        DeliveryPricing::create([
            'delivery_service_id' => $deliveryService->id,
            'city_id' => $this->city->id,
            'fee' => 3000,
            'is_active' => true,
        ]);

        $service = app(DeliveryFeeService::class);

        $fee = $service->resolveDeliveryFee($this->city);
        $this->assertEquals(3000, $fee);
    }

    /** @test */
    public function checkout_uses_new_services_when_available(): void
    {
        Tenant::setCurrent($this->tenant);

        DeliveryService::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Standard',
            'code' => 'standard',
            'base_fee' => 2000,
            'min_days' => 2,
            'max_days' => 4,
            'is_active' => true,
        ]);

        $service = app(DeliveryFeeService::class);
        $services = $service->getAvailableServices();

        $this->assertNotEmpty($services);
        $this->assertEquals('Standard', $services->first()->name);
    }

    /** @test */
    public function cod_ineligibility_returns_reason(): void
    {
        \App\Models\Tenant::setCurrent($this->tenant);

        $codMethod = PaymentMethod::where('type', 'cod')->first();
        $service = app(CodEligibilityService::class);

        $reason = $service->getIneligibilityReason($codMethod, $this->userWithoutCod, null, 10000);
        $this->assertNotNull($reason);
        $this->assertStringContainsString('not available for your account', $reason);
    }

    /** @test */
    public function cod_eligible_when_no_rules_and_user_allowed(): void
    {
        \App\Models\Tenant::setCurrent($this->tenant);

        $codMethod = PaymentMethod::where('type', 'cod')->first();
        $service = app(CodEligibilityService::class);

        $this->assertTrue($service->isCodAvailable($codMethod, $this->userWithCod, null, null));
    }

    /** @test */
    public function delivery_service_scopes_to_active_only(): void
    {
        DeliveryService::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Active Service',
            'code' => 'active',
            'base_fee' => 1000,
            'is_active' => true,
        ]);

        DeliveryService::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Inactive Service',
            'code' => 'inactive',
            'base_fee' => 500,
            'is_active' => false,
        ]);

        $activeServices = DeliveryService::active()->get();

        $this->assertEquals(1, $activeServices->count());
        $this->assertEquals('Active Service', $activeServices->first()->name);
    }

    /** @test */
    public function cod_rules_are_tenant_scoped(): void
    {
        \App\Models\Tenant::setCurrent($this->tenant);

        $ruleTenant1 = CodRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Tenant 1 Rule',
            'is_active' => true,
            'cod_fee' => 500,
        ]);

        $ruleTenant2 = CodRule::create([
            'tenant_id' => $this->otherTenant->id,
            'name' => 'Tenant 2 Rule',
            'is_active' => true,
            'cod_fee' => 1000,
        ]);

        $service = app(CodEligibilityService::class);
        $activeRules = $service->getActiveRules();

        $this->assertEquals(1, $activeRules->count());
        $this->assertEquals('Tenant 1 Rule', $activeRules->first()->name);
    }

    /** @test */
    public function cod_rules_tenant_a_cannot_access_tenant_b_rules(): void
    {
        \App\Models\Tenant::setCurrent($this->tenant);

        CodRule::create([
            'tenant_id' => $this->otherTenant->id,
            'name' => 'Other Tenant Rule',
            'is_active' => true,
            'min_order_amount' => 1000,
            'cod_fee' => 500,
        ]);

        $codMethod = PaymentMethod::where('type', 'cod')->first();
        $service = app(CodEligibilityService::class);

        $this->assertTrue($service->isCodAvailable($codMethod, $this->userWithCod, null, 500));
    }

    /** @test */
    public function cod_fee_returns_zero_when_no_rules(): void
    {
        \App\Models\Tenant::setCurrent($this->tenant);

        $service = app(CodEligibilityService::class);

        $this->assertEquals(0, $service->getCodFee(null, 10000));
    }

    /** @test */
    public function cod_rule_with_specific_fee_applies_correctly(): void
    {
        \App\Models\Tenant::setCurrent($this->tenant);

        CodRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Fee Rule',
            'is_active' => true,
            'cod_fee' => 1500,
            'apply_cod_fee_to_total' => true,
        ]);

        $service = app(CodEligibilityService::class);

        $this->assertEquals(1500, $service->getCodFee(null, 10000));
        $this->assertTrue($service->shouldApplyCodFeeToTotal(null, 10000));
    }

    /** @test */
    public function cod_ineligibility_reason_distinguishes_amount_vs_city(): void
    {
        \App\Models\Tenant::setCurrent($this->tenant);

        $otherCity = City::create([
            'tenant_id' => null,
            'name' => 'Mandalay',
            'delivery_fee' => 2000,
            'is_active' => true,
        ]);

        CodRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Amount Rule',
            'is_active' => true,
            'min_order_amount' => 50000,
            'max_order_amount' => 99999,
            'cod_fee' => 1000,
        ]);

        $codMethod = PaymentMethod::where('type', 'cod')->first();
        $service = app(CodEligibilityService::class);

        $reasonAmount = $service->getIneligibilityReason($codMethod, $this->userWithCod, null, 10000);
        $this->assertStringContainsString('order amount', $reasonAmount);

        $ruleCityOnly = CodRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'City Rule',
            'is_active' => true,
            'allowed_city_ids' => [$otherCity->id],
            'cod_fee' => 500,
        ]);

        $reasonCity = $service->getIneligibilityReason($codMethod, $this->userWithCod, $this->city->id, 100000);
        $this->assertStringContainsString('delivery location', $reasonCity);
    }
}
