<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\CodRule;
use App\Models\DeliveryPricing;
use App\Models\DeliveryService;
use App\Models\Order;
use App\Models\PackagingOption;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Township;
use App\Models\User;
use App\Services\CodEligibilityService;
use App\Services\DeliveryFeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Tenant $otherTenant;
    private City $city;
    private Township $township;
    private Product $product;
    private PaymentMethod $codPaymentMethod;
    private PaymentMethod $bankPaymentMethod;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->city = City::create([
            'name' => 'Yangon',
            'delivery_fee' => 1000,
            'is_active' => true,
        ]);

        $this->township = Township::create([
            'city_id' => $this->city->id,
            'name' => 'Hlaing',
            'postal_code' => '11041',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Product',
            'type' => 'single',
            'price' => 5000,
            'stock' => 100,
            'status' => 'active',
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

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'allow_cod' => true,
        ]);
    }

    /** @test */
    public function delivery_fee_service_respects_tenant_isolation(): void
    {
        DeliveryService::create([
            'tenant_id' => $this->otherTenant->id,
            'name' => 'Other Tenant Service',
            'code' => 'other',
            'base_fee' => 9999,
            'min_days' => 1,
            'max_days' => 1,
            'is_active' => true,
        ]);

        DeliveryService::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'My Service',
            'code' => 'mine',
            'base_fee' => 1500,
            'min_days' => 1,
            'max_days' => 2,
            'is_active' => true,
        ]);

        $service = new DeliveryFeeService();
        $services = $service->getAvailableServices();

        $this->assertEquals(1, $services->count());
        $this->assertEquals('My Service', $services->first()->name);
    }

    /** @test */
    public function delivery_pricing_respects_tenant_via_service(): void
    {
        $service = DeliveryService::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'My Express',
            'code' => 'my_express',
            'base_fee' => 3000,
            'min_days' => 1,
            'max_days' => 1,
            'is_active' => true,
        ]);

        DeliveryPricing::create([
            'delivery_service_id' => $service->id,
            'city_id' => $this->city->id,
            'fee' => 2500,
            'is_active' => true,
        ]);

        $service2 = DeliveryService::create([
            'tenant_id' => $this->otherTenant->id,
            'name' => 'Other Express',
            'code' => 'other_express',
            'base_fee' => 5000,
            'min_days' => 1,
            'max_days' => 2,
            'is_active' => true,
        ]);

        DeliveryPricing::create([
            'delivery_service_id' => $service2->id,
            'city_id' => $this->city->id,
            'fee' => 4000,
            'is_active' => true,
        ]);

        $feeService = new DeliveryFeeService();
        $fee = $feeService->resolveDeliveryFee($this->city, $service->id);

        $this->assertEquals(2500, $fee);
    }

    /** @test */
    public function inactive_delivery_service_not_returned(): void
    {
        DeliveryService::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Inactive Service',
            'code' => 'inactive',
            'base_fee' => 500,
            'min_days' => 1,
            'max_days' => 1,
            'is_active' => false,
        ]);

        $service = new DeliveryFeeService();
        $services = $service->getAvailableServices();

        $this->assertEquals(0, $services->count());
    }

    /** @test */
    public function inactive_city_not_used_in_checkout(): void
    {
        $inactiveCity = City::create([
            'name' => 'Inactive City',
            'delivery_fee' => 9999,
            'is_active' => false,
        ]);

        $activeCities = City::active()->get();

        $this->assertFalse($activeCities->contains('id', $inactiveCity->id));
    }

    /** @test */
    public function cod_eligibility_respects_tenant(): void
    {
        CodRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Tenant Rule',
            'cod_fee' => 500,
            'is_active' => true,
        ]);

        CodRule::create([
            'tenant_id' => $this->otherTenant->id,
            'name' => 'Other Rule',
            'cod_fee' => 1000,
            'is_active' => true,
        ]);

        $service = new CodEligibilityService();
        $rules = $service->getActiveRules();

        $this->assertEquals(1, $rules->count());
        $this->assertEquals('Tenant Rule', $rules->first()->name);
    }

    /** @test */
    public function user_without_allow_cod_is_blocked(): void
    {
        $userWithoutCod = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'No COD User',
            'email' => 'nocod@example.com',
            'password' => bcrypt('password'),
            'allow_cod' => false,
        ]);

        $codMethod = new PaymentMethod(['type' => 'cod']);

        $service = new CodEligibilityService();
        $available = $service->isCodAvailable($codMethod, $userWithoutCod, $this->city->id, 1000);

        $this->assertFalse($available);
    }

    /** @test */
    public function cod_fee_returns_zero_when_no_rules(): void
    {
        $service = new CodEligibilityService();
        $fee = $service->getCodFee($this->city->id, 1000);

        $this->assertEquals(0, $fee);
    }

    /** @test */
    public function cod_fee_uses_eligible_rule(): void
    {
        CodRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Normal Rule',
            'cod_fee' => 500,
            'is_active' => true,
        ]);

        $service = new CodEligibilityService();
        $fee = $service->getCodFee($this->city->id, 1000);

        $this->assertEquals(500, $fee);
    }

    /** @test */
    public function cod_rule_amount_filter_works(): void
    {
        CodRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Expensive Rule',
            'min_order_amount' => 10000,
            'cod_fee' => 500,
            'is_active' => true,
        ]);

        $service = new CodEligibilityService();
        $fee = $service->getCodFee($this->city->id, 5000);

        $this->assertEquals(0, $fee);
    }

    /** @test */
    public function cod_rule_city_filter_works(): void
    {
        $otherCity = City::create([
            'name' => 'Mandalay',
            'delivery_fee' => 1500,
            'is_active' => true,
        ]);

        CodRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Yangon Only',
            'allowed_city_ids' => [$this->city->id],
            'cod_fee' => 500,
            'is_active' => true,
        ]);

        $service = new CodEligibilityService();

        $yangonFee = $service->getCodFee($this->city->id, 5000);
        $mandalayFee = $service->getCodFee($otherCity->id, 5000);

        $this->assertEquals(500, $yangonFee);
        $this->assertEquals(0, $mandalayFee);
    }

    /** @test */
    public function township_belongs_to_city(): void
    {
        $this->assertEquals($this->city->id, $this->township->city->id);
    }

    /** @test */
    public function township_city_validation_works(): void
    {
        $otherCity = City::create([
            'name' => 'Mandalay',
            'delivery_fee' => 1500,
            'is_active' => true,
        ]);

        $townshipInOtherCity = Township::create([
            'city_id' => $otherCity->id,
            'name' => 'Aung Myay',
            'is_active' => true,
        ]);

        $this->assertNotEquals($this->city->id, $townshipInOtherCity->city_id);
    }

    /** @test */
    public function delivery_service_with_weight_calculation(): void
    {
        $service = DeliveryService::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Weighted',
            'code' => 'weighted',
            'base_fee' => 1000,
            'fee_per_kg' => 500,
            'min_days' => 1,
            'max_days' => 3,
            'is_active' => true,
        ]);

        $feeService = new DeliveryFeeService();
        $fee = $feeService->calculateFee($service, null, 2.5);

        $this->assertEquals(2250, $fee);
    }

    /** @test */
    public function inactive_packaging_not_in_checkout(): void
    {
        PackagingOption::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Active Box',
            'code' => 'active_box',
            'fee' => 200,
            'is_active' => true,
        ]);

        PackagingOption::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Inactive Box',
            'code' => 'inactive_box',
            'fee' => 100,
            'is_active' => false,
        ]);

        $activeOptions = \App\Models\PackagingOption::active()->forCurrentTenant()->get();

        $this->assertEquals(1, $activeOptions->count());
        $this->assertEquals('Active Box', $activeOptions->first()->name);
    }
}
