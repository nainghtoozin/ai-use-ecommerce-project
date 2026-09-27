<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\DeliveryPricing;
use App\Models\DeliveryService;
use App\Models\Tenant;
use App\Models\Township;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DeliveryServiceRulesBulkTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private DeliveryService $serviceA;
    private DeliveryPricing $ruleA1;
    private DeliveryPricing $ruleA2;
    private DeliveryPricing $ruleB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'Store A', 'slug' => 'store-a',
            'store_url' => '/store/store-a', 'status' => 'active',
        ]);
        $this->tenantB = Tenant::create([
            'name' => 'Store B', 'slug' => 'store-b',
            'store_url' => '/store/store-b', 'status' => 'active',
        ]);

        $cityA = City::withoutTenantScope()->create([
            'tenant_id' => $this->tenantA->id, 'name' => 'Yangon', 'is_active' => true,
        ]);
        $cityB = City::withoutTenantScope()->create([
            'tenant_id' => $this->tenantB->id, 'name' => 'Yangon', 'is_active' => true,
        ]);

        $this->serviceA = DeliveryService::create([
            'tenant_id' => $this->tenantA->id, 'name' => 'Express', 'code' => 'express',
            'base_fee' => 2500, 'min_days' => 1, 'max_days' => 2, 'is_active' => true,
        ]);
        $serviceB = DeliveryService::create([
            'tenant_id' => $this->tenantB->id, 'name' => 'Express', 'code' => 'express',
            'base_fee' => 2500, 'min_days' => 1, 'max_days' => 2, 'is_active' => true,
        ]);

        $twpA1 = Township::withoutTenantScope()->create([
            'tenant_id' => $this->tenantA->id, 'city_id' => $cityA->id,
            'name' => 'Bahan', 'delivery_fee' => 3000, 'is_active' => true,
        ]);
        $twpA2 = Township::withoutTenantScope()->create([
            'tenant_id' => $this->tenantA->id, 'city_id' => $cityA->id,
            'name' => 'Kamayut', 'delivery_fee' => 3500, 'is_active' => true,
        ]);
        $twpB = Township::withoutTenantScope()->create([
            'tenant_id' => $this->tenantB->id, 'city_id' => $cityB->id,
            'name' => 'Bahan', 'delivery_fee' => 4000, 'is_active' => true,
        ]);

        $this->ruleA1 = DeliveryPricing::create([
            'delivery_service_id' => $this->serviceA->id, 'township_id' => $twpA1->id,
            'min_days' => 1, 'max_days' => 2, 'is_active' => true,
        ]);
        $this->ruleA2 = DeliveryPricing::create([
            'delivery_service_id' => $this->serviceA->id, 'township_id' => $twpA2->id,
            'min_days' => 1, 'max_days' => 2, 'is_active' => true,
        ]);
        $this->ruleB = DeliveryPricing::create([
            'delivery_service_id' => $serviceB->id, 'township_id' => $twpB->id,
            'min_days' => 1, 'max_days' => 2, 'is_active' => true,
        ]);
    }

    private function makeAdmin(Tenant $tenant, array $permissions): User
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);

        $user = User::create([
            'tenant_id' => $tenant->id, 'name' => 'Rules Admin',
            'email' => 'rules-admin-' . $tenant->slug . '-' . uniqid() . '@test.com', 'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $user->assignRole($role);
        foreach ($permissions as $name) {
            $user->givePermissionTo($name);
        }

        return $user;
    }

    /** @test */
    public function bulk_activate_and_deactivate_rules(): void
    {
        $this->actingAs($this->makeAdmin($this->tenantA, ['delivery-services.update']));

        $this->post('/store/store-a/admin/delivery-services/pricing/bulk-status', [
            'ids' => [$this->ruleA1->id, $this->ruleA2->id],
            'is_active' => false,
        ])->assertRedirect();

        $this->assertFalse((bool) $this->ruleA1->fresh()->is_active);
        $this->assertFalse((bool) $this->ruleA2->fresh()->is_active);
        $this->assertTrue((bool) $this->ruleB->fresh()->is_active);

        $this->post('/store/store-a/admin/delivery-services/pricing/bulk-status', [
            'ids' => [$this->ruleA1->id],
            'is_active' => true,
        ])->assertRedirect();

        $this->assertTrue((bool) $this->ruleA1->fresh()->is_active);
    }

    /** @test */
    public function bulk_set_days(): void
    {
        $this->actingAs($this->makeAdmin($this->tenantA, ['delivery-services.update']));

        $response = $this->post('/store/store-a/admin/delivery-services/pricing/bulk-days', [
            'ids' => [$this->ruleA1->id, $this->ruleA2->id],
            'min_days' => 4,
            'max_days' => 7,
        ]);

        $response->assertRedirect();
        $this->assertEquals(4, $this->ruleA1->fresh()->min_days);
        $this->assertEquals(7, $this->ruleA2->fresh()->max_days);
        $this->assertEquals(1, $this->ruleB->fresh()->min_days);
    }

    /** @test */
    public function bulk_set_days_validates_min_max(): void
    {
        $this->actingAs($this->makeAdmin($this->tenantA, ['delivery-services.update']));

        $this->post('/store/store-a/admin/delivery-services/pricing/bulk-days', [
            'ids' => [$this->ruleA1->id],
            'min_days' => -1,
            'max_days' => 7,
        ])->assertSessionHasErrors('min_days');

        $this->post('/store/store-a/admin/delivery-services/pricing/bulk-days', [
            'ids' => [$this->ruleA1->id],
            'min_days' => 7,
            'max_days' => 4,
        ])->assertSessionHasErrors('max_days');

        $this->assertEquals(1, $this->ruleA1->fresh()->min_days);
        $this->assertEquals(2, $this->ruleA1->fresh()->max_days);
    }

    /** @test */
    public function foreign_rule_ids_are_ignored(): void
    {
        $this->actingAs($this->makeAdmin($this->tenantA, ['delivery-services.update']));

        $response = $this->post('/store/store-a/admin/delivery-services/pricing/bulk-status', [
            'ids' => [$this->ruleA1->id, $this->ruleB->id, 999999],
            'is_active' => false,
        ]);

        $response->assertRedirect();
        $this->assertFalse((bool) $this->ruleA1->fresh()->is_active);
        $this->assertTrue((bool) $this->ruleB->fresh()->is_active);

        $this->post('/store/store-a/admin/delivery-services/pricing/bulk-days', [
            'ids' => [$this->ruleB->id],
            'min_days' => 9,
            'max_days' => 9,
        ])->assertRedirect();

        $this->assertEquals(1, $this->ruleB->fresh()->min_days);
    }

    /** @test */
    public function edit_page_exposes_rules_with_township_and_city(): void
    {
        $this->actingAs($this->makeAdmin($this->tenantA, ['delivery-services.update']));

        $serviceId = $this->serviceA->id;
        $response = $this->get("/store/store-a/admin/delivery-services/{$serviceId}/edit");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('deliveryService.pricing', 2)
            ->where('deliveryService.pricing.0.township.name', 'Bahan')
            ->where('deliveryService.pricing.0.township.city.name', 'Yangon'));
    }

    /** @test */
    public function bulk_set_days_across_all_matching_rules(): void
    {
        $ids = [];
        for ($i = 1; $i <= 5; $i++) {
            $twp = Township::withoutTenantScope()->create([
                'tenant_id' => $this->tenantA->id,
                'city_id' => City::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->value('id'),
                'name' => 'Extra-' . $i, 'delivery_fee' => 1000, 'is_active' => true,
            ]);
            $ids[] = DeliveryPricing::create([
                'delivery_service_id' => $this->serviceA->id, 'township_id' => $twp->id,
                'min_days' => 1, 'max_days' => 2, 'is_active' => true,
            ])->id;
        }

        $this->actingAs($this->makeAdmin($this->tenantA, ['delivery-services.update']));

        $response = $this->post('/store/store-a/admin/delivery-services/pricing/bulk-days', [
            'ids' => $ids,
            'min_days' => 4,
            'max_days' => 7,
        ]);

        $response->assertRedirect();
        $this->assertEquals(5, DeliveryPricing::whereIn('id', $ids)->where('min_days', 4)->where('max_days', 7)->count());
    }

    /** @test */
    public function bulk_rules_require_permission(): void
    {
        $this->actingAs($this->makeAdmin($this->tenantA, ['delivery-services.view']));

        $this->post('/store/store-a/admin/delivery-services/pricing/bulk-status', [
            'ids' => [$this->ruleA1->id],
            'is_active' => false,
        ])->assertForbidden();

        $this->post('/store/store-a/admin/delivery-services/pricing/bulk-days', [
            'ids' => [$this->ruleA1->id],
            'min_days' => 4,
            'max_days' => 7,
        ])->assertForbidden();

        $this->assertTrue((bool) $this->ruleA1->fresh()->is_active);
    }
}
