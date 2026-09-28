<?php

namespace Tests\Feature;

use App\Models\CodRule;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CodRuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use App\Models\Role;
use Tests\TestCase;

class CodSingleRuleTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'Store A', 'slug' => 'single-a',
            'store_url' => '/store/single-a', 'status' => 'active',
        ]);
        $this->tenantB = Tenant::create([
            'name' => 'Store B', 'slug' => 'single-b',
            'store_url' => '/store/single-b', 'status' => 'active',
        ]);
        Tenant::setCurrent($this->tenantA);
    }

    private function makeAdmin(Tenant $tenant): User
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['cod-rules.view', 'cod-rules.create', 'cod-rules.update', 'cod-rules.delete'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);

        $user = User::create([
            'tenant_id' => $tenant->id, 'name' => 'Single Admin',
            'email' => 'single-admin-' . $tenant->slug . '@test.com', 'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $user->assignRole($role);
        $user->givePermissionTo(['cod-rules.view', 'cod-rules.create', 'cod-rules.update', 'cod-rules.delete']);

        return $user;
    }

    private function ruleData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Single Rule',
            'is_active' => true,
        ], $overrides);
    }

    /** @test */
    public function tenant_with_no_rule_can_create_one(): void
    {
        $this->actingAs($this->makeAdmin($this->tenantA));

        $response = $this->post('/store/single-a/admin/cod-rules', $this->ruleData());

        $response->assertRedirect();
        $this->assertEquals(1, CodRule::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count());
    }

    /** @test */
    public function tenant_with_existing_rule_cannot_create_another(): void
    {
        CodRule::withoutTenantScope()->create(array_merge(
            $this->ruleData(), ['tenant_id' => $this->tenantA->id]
        ));
        $this->actingAs($this->makeAdmin($this->tenantA));

        $response = $this->post('/store/single-a/admin/cod-rules', $this->ruleData(['name' => 'Second']));

        $response->assertSessionHasErrors('name');
        $this->assertEquals(1, CodRule::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count());
    }

    /** @test */
    public function service_create_throws_for_second_rule(): void
    {
        CodRule::withoutTenantScope()->create(array_merge(
            $this->ruleData(), ['tenant_id' => $this->tenantA->id]
        ));

        $this->expectException(ValidationException::class);

        app(CodRuleService::class)->create($this->ruleData(['name' => 'Second']));
    }

    /** @test */
    public function create_page_redirects_to_edit_when_rule_exists(): void
    {
        $rule = CodRule::withoutTenantScope()->create(array_merge(
            $this->ruleData(), ['tenant_id' => $this->tenantA->id]
        ));
        $this->actingAs($this->makeAdmin($this->tenantA));

        $response = $this->get('/store/single-a/admin/cod-rules/create');

        $response->assertRedirect("/store/single-a/admin/cod-rules/{$rule->id}/edit");
    }

    /** @test */
    public function existing_rule_can_be_edited_toggled_and_deleted(): void
    {
        $rule = CodRule::withoutTenantScope()->create(array_merge(
            $this->ruleData(), ['tenant_id' => $this->tenantA->id]
        ));
        $this->actingAs($this->makeAdmin($this->tenantA));

        $this->put("/store/single-a/admin/cod-rules/{$rule->id}", array_merge(
            $this->ruleData(),
            ['name' => 'Renamed', 'min_order_amount' => 100, 'max_order_amount' => 9000]
        ))->assertRedirect();
        $this->assertEquals('Renamed', $rule->fresh()->name);
        $this->assertEquals(9000, (float) $rule->fresh()->max_order_amount);

        $this->post("/store/single-a/admin/cod-rules/{$rule->id}/toggle")
            ->assertRedirect()
            ->assertSessionHas('success', 'COD rule deactivated.');
        $this->assertFalse((bool) $rule->fresh()->is_active);

        $this->post("/store/single-a/admin/cod-rules/{$rule->id}/toggle")
            ->assertRedirect()
            ->assertSessionHas('success', 'COD rule activated.');
        $this->assertTrue((bool) $rule->fresh()->is_active);

        $this->delete("/store/single-a/admin/cod-rules/{$rule->id}")->assertRedirect();
        $this->assertEquals(0, CodRule::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count());
    }

    /** @test */
    public function different_tenants_can_each_have_one_rule(): void
    {
        CodRule::withoutTenantScope()->create(array_merge(
            $this->ruleData(), ['tenant_id' => $this->tenantA->id]
        ));
        $this->actingAs($this->makeAdmin($this->tenantB));

        $response = $this->post('/store/single-b/admin/cod-rules', $this->ruleData());

        $response->assertRedirect();
        $this->assertEquals(1, CodRule::withoutTenantScope()->where('tenant_id', $this->tenantB->id)->count());
        $this->assertEquals(1, CodRule::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count());
    }

    /** @test */
    public function create_with_cities_persists_them(): void
    {
        $allowed = \App\Models\City::create(['name' => 'Allowed City', 'is_active' => true]);
        $excluded = \App\Models\City::create(['name' => 'Excluded City', 'is_active' => true]);
        $this->actingAs($this->makeAdmin($this->tenantA));

        $response = $this->post('/store/single-a/admin/cod-rules', array_merge(
            $this->ruleData(),
            ['allowed_city_ids' => [$allowed->id], 'excluded_city_ids' => [$excluded->id]]
        ));

        $response->assertRedirect();
        $rule = CodRule::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->first();
        $this->assertEquals([$allowed->id], $rule->allowed_city_ids);
        $this->assertEquals([$excluded->id], $rule->excluded_city_ids);
    }

    /** @test */
    public function update_persists_city_ids(): void
    {
        $allowed = \App\Models\City::create(['name' => 'Allowed City', 'is_active' => true]);
        $excluded = \App\Models\City::create(['name' => 'Excluded City', 'is_active' => true]);
        $rule = CodRule::withoutTenantScope()->create(array_merge(
            $this->ruleData(), ['tenant_id' => $this->tenantA->id]
        ));
        $this->actingAs($this->makeAdmin($this->tenantA));

        $this->put("/store/single-a/admin/cod-rules/{$rule->id}", array_merge(
            $this->ruleData(),
            ['allowed_city_ids' => [$allowed->id], 'excluded_city_ids' => [$excluded->id]]
        ))->assertRedirect();

        $this->assertEquals([$allowed->id], $rule->fresh()->allowed_city_ids);
        $this->assertEquals([$excluded->id], $rule->fresh()->excluded_city_ids);
    }

    /** @test */
    public function tenant_isolation_preserved_on_create_page(): void
    {
        CodRule::withoutTenantScope()->create(array_merge(
            $this->ruleData(), ['tenant_id' => $this->tenantB->id]
        ));
        $this->actingAs($this->makeAdmin($this->tenantA));

        $response = $this->get('/store/single-a/admin/cod-rules/create');

        $response->assertOk();
    }
}
