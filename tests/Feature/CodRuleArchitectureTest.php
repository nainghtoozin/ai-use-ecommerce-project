<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\CodRule;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CodEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CodRuleArchitectureTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Tenant $otherTenant;
    private City $city;

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
    }

    /** @test */
    public function cod_rule_respects_tenant_isolation(): void
    {
        Tenant::setCurrent($this->tenant);

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

        $tenantRules = CodRule::forCurrentTenant()->get();

        $this->assertEquals(1, $tenantRules->count());
        $this->assertEquals('Tenant Rule', $tenantRules->first()->name);
    }

    /** @test */
    public function min_amount_must_be_less_than_max_amount(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $request = new \App\Http\Requests\StoreCodRuleRequest();
        $rules = $request->rules();

        $validator = \Validator::make([
            'name' => 'Test Rule',
            'min_order_amount' => 1000,
            'max_order_amount' => 500,
            'cod_fee' => 100,
        ], $rules);

        if (!$validator->errors()->isEmpty()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }
    }

    /** @test */
    public function cod_rule_amount_eligibility_works(): void
    {
        $rule = CodRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Amount Rule',
            'min_order_amount' => 1000,
            'max_order_amount' => 5000,
            'cod_fee' => 500,
            'is_active' => true,
        ]);

        $this->assertFalse($rule->isAmountEligible(500));
        $this->assertTrue($rule->isAmountEligible(1000));
        $this->assertTrue($rule->isAmountEligible(3000));
        $this->assertTrue($rule->isAmountEligible(5000));
        $this->assertFalse($rule->isAmountEligible(6000));
    }

    /** @test */
    public function cod_rule_city_eligibility_works(): void
    {
        $otherCity = City::create([
            'name' => 'Mandalay',
            'delivery_fee' => 1500,
            'is_active' => true,
        ]);

        $rule = CodRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'City Rule',
            'allowed_city_ids' => [$this->city->id],
            'cod_fee' => 500,
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
            'name' => 'Mandalay',
            'delivery_fee' => 1500,
            'is_active' => true,
        ]);

        $rule = CodRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Exclude Rule',
            'excluded_city_ids' => [$this->city->id],
            'cod_fee' => 500,
            'is_active' => true,
        ]);

        $this->assertFalse($rule->isCityEligible($this->city->id));
        $this->assertTrue($rule->isCityEligible($otherCity->id));
        $this->assertTrue($rule->isCityEligible(null));
    }

    /** @test */
    public function inactive_rules_are_excluded(): void
    {
        CodRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Active Rule',
            'cod_fee' => 500,
            'is_active' => true,
        ]);

        CodRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Inactive Rule',
            'cod_fee' => 1000,
            'is_active' => false,
        ]);

        $activeRules = CodRule::active()->forCurrentTenant()->get();

        $this->assertEquals(1, $activeRules->count());
        $this->assertEquals('Active Rule', $activeRules->first()->name);
    }

    /** @test */
    public function user_allow_cod_blocks_cod(): void
    {
        $userWithCod = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'User With COD',
            'email' => 'cod@test.com',
            'password' => bcrypt('password'),
            'allow_cod' => true,
        ]);

        $userWithoutCod = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'User Without COD',
            'email' => 'nocod@test.com',
            'password' => bcrypt('password'),
            'allow_cod' => false,
        ]);

        $rule = CodRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Rule',
            'cod_fee' => 500,
            'is_active' => true,
        ]);

        $service = new CodEligibilityService();

        $paymentMethod = new \App\Models\PaymentMethod(['type' => 'cod']);

        $this->assertTrue($service->isCodAvailable($paymentMethod, $userWithCod, $this->city->id, 1000));
        $this->assertFalse($service->isCodAvailable($paymentMethod, $userWithoutCod, $this->city->id, 1000));
    }

    /** @test */
    public function cod_fee_returns_zero_when_no_eligible_rules(): void
    {
        $rule = CodRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Expensive Rule',
            'min_order_amount' => 10000,
            'cod_fee' => 500,
            'is_active' => true,
        ]);

        $service = new CodEligibilityService();
        $fee = $service->getCodFee($this->city->id, 1000);

        $this->assertEquals(0, $fee);
    }

    /** @test */
    public function cod_fee_returns_rule_fee_when_eligible(): void
    {
        $rule = CodRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Normal Rule',
            'min_order_amount' => 0,
            'cod_fee' => 500,
            'is_active' => true,
        ]);

        $service = new CodEligibilityService();
        $fee = $service->getCodFee($this->city->id, 1000);

        $this->assertEquals(500, $fee);
    }

    /** @test */
    public function apply_cod_fee_to_total_is_respected(): void
    {
        $ruleWithFee = CodRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Fee to Total',
            'cod_fee' => 500,
            'apply_cod_fee_to_total' => true,
            'is_active' => true,
        ]);

        $ruleWithoutFee = CodRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Fee not to Total',
            'cod_fee' => 500,
            'apply_cod_fee_to_total' => false,
            'is_active' => true,
        ]);

        $service = new CodEligibilityService();

        $this->assertTrue($service->shouldApplyCodFeeToTotal($this->city->id, 1000));
    }

    /** @test */
    public function first_eligible_rule_is_used_for_fee(): void
    {
        CodRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'First Rule',
            'cod_fee' => 300,
            'is_active' => true,
        ]);

        CodRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Second Rule',
            'cod_fee' => 500,
            'is_active' => true,
        ]);

        $service = new CodEligibilityService();
        $fee = $service->getCodFee($this->city->id, 1000);

        $this->assertEquals(300, $fee);
    }
}
