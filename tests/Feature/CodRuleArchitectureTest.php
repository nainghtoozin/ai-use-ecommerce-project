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

        Tenant::setCurrent($this->tenant);

        $this->city = City::create([
            'name' => 'Yangon',
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
            'is_active' => true,
        ]);

        CodRule::create([
            'tenant_id' => $this->otherTenant->id,
            'name' => 'Other Rule',
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
            'is_active' => true,
        ]);

        $rule = CodRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'City Rule',
            'allowed_city_ids' => [$this->city->id],
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
            'is_active' => true,
        ]);

        $rule = CodRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Exclude Rule',
            'excluded_city_ids' => [$this->city->id],
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
            'is_active' => true,
        ]);

        CodRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Inactive Rule',
            'is_active' => false,
        ]);

        $activeRules = CodRule::active()->forCurrentTenant()->get();

        $this->assertEquals(1, $activeRules->count());
        $this->assertEquals('Active Rule', $activeRules->first()->name);
    }

    /** @test */
    public function user_allow_cod_no_longer_blocks_cod(): void
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
            'is_active' => true,
        ]);

        $service = new CodEligibilityService();

        $paymentMethod = new \App\Models\PaymentMethod(['type' => 'cod']);

        $this->assertTrue($service->isCodAvailable($paymentMethod, $userWithCod, $this->city->id, 1000));
        $this->assertTrue($service->isCodAvailable($paymentMethod, $userWithoutCod, $this->city->id, 1000));
    }

    /** @test */
    public function cod_unavailable_when_no_eligible_rules(): void
    {
        $rule = CodRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Expensive Rule',
            'min_order_amount' => 10000,
            'is_active' => true,
        ]);

        $service = new CodEligibilityService();
        $paymentMethod = new \App\Models\PaymentMethod(['type' => 'cod']);

        $this->assertFalse($service->isCodAvailable($paymentMethod, null, $this->city->id, 1000));
    }

    /** @test */
    public function cod_available_when_rule_eligible(): void
    {
        $rule = CodRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Normal Rule',
            'min_order_amount' => 0,
            'is_active' => true,
        ]);

        $service = new CodEligibilityService();
        $paymentMethod = new \App\Models\PaymentMethod(['type' => 'cod']);

        $this->assertTrue($service->isCodAvailable($paymentMethod, null, $this->city->id, 1000));
    }

    /** @test */
    public function first_eligible_rule_is_used_for_availability(): void
    {
        CodRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'First Rule',
            'max_order_amount' => 500,
            'is_active' => true,
        ]);

        CodRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Second Rule',
            'is_active' => true,
        ]);

        $service = new CodEligibilityService();

        $this->assertEquals('Second Rule', $service->getFirstEligibleRule($this->city->id, 1000)->name);
    }

    /** @test */
    public function allowed_cities_list_returns_tenant_scoped_collection(): void
    {
        Tenant::setCurrent($this->tenant);

        $foreignCity = City::withoutTenantScope()->create([
            'tenant_id' => $this->otherTenant->id, 'name' => 'Foreign City', 'is_active' => true,
        ]);

        $rule = CodRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Scoped Rule',
            'allowed_city_ids' => [$this->city->id, $foreignCity->id],
            'is_active' => true,
        ]);

        $list = $rule->allowedCitiesList();

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $list);
        $this->assertTrue($list->has($this->city->id));
        $this->assertFalse($list->has($foreignCity->id));
    }

    /** @test */
    public function excluded_cities_list_returns_tenant_scoped_collection(): void
    {
        Tenant::setCurrent($this->tenant);

        $rule = CodRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Excluded Rule',
            'excluded_city_ids' => [$this->city->id],
            'is_active' => true,
        ]);

        $list = $rule->excludedCitiesList();

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $list);
        $this->assertTrue($list->has($this->city->id));
        $this->assertEquals(['type' => 'excluded'], $list->get($this->city->id)->pivot);
    }

    /** @test */
    public function cod_rule_edit_page_renders(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'cod-rules.update', 'guard_name' => 'web']);
        $role = \Spatie\Permission\Models\Role::firstOrCreate([
            'name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id,
        ]);

        $admin = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'COD Admin',
            'email' => 'cod-admin@test.com', 'password' => bcrypt('password'), 'status' => 'active',
        ]);
        $admin->assignRole($role);
        $admin->givePermissionTo('cod-rules.update');

        $rule = CodRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Editable Rule',
            'allowed_city_ids' => [$this->city->id],
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get("/store/test-store/admin/cod-rules/{$rule->id}/edit")
            ->assertOk();
    }
}
