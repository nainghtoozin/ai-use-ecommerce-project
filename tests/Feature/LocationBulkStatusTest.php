<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Tenant;
use App\Models\Township;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LocationBulkStatusTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private City $cityA1;
    private City $cityA2;
    private City $cityB;
    private Township $townshipA1;
    private Township $townshipA2;
    private Township $townshipB;

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

        $this->cityA1 = City::withoutTenantScope()->create([
            'tenant_id' => $this->tenantA->id, 'name' => 'Yangon', 'is_active' => false,
        ]);
        $this->cityA2 = City::withoutTenantScope()->create([
            'tenant_id' => $this->tenantA->id, 'name' => 'Mandalay', 'is_active' => false,
        ]);
        $this->cityB = City::withoutTenantScope()->create([
            'tenant_id' => $this->tenantB->id, 'name' => 'Yangon', 'is_active' => true,
        ]);

        $this->townshipA1 = Township::withoutTenantScope()->create([
            'tenant_id' => $this->tenantA->id, 'city_id' => $this->cityA1->id,
            'name' => 'Bahan', 'delivery_fee' => 3000, 'is_active' => false,
        ]);
        $this->townshipA2 = Township::withoutTenantScope()->create([
            'tenant_id' => $this->tenantA->id, 'city_id' => $this->cityA1->id,
            'name' => 'Kamayut', 'delivery_fee' => 3500, 'is_active' => true,
        ]);
        $this->townshipB = Township::withoutTenantScope()->create([
            'tenant_id' => $this->tenantB->id, 'city_id' => $this->cityB->id,
            'name' => 'Bahan', 'delivery_fee' => 4000, 'is_active' => true,
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
            'tenant_id' => $tenant->id, 'name' => 'Bulk Admin',
            'email' => 'bulk-admin-' . $tenant->slug . '@test.com', 'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $user->assignRole($role);
        foreach ($permissions as $name) {
            $user->givePermissionTo($name);
        }

        return $user;
    }

    /** @test */
    public function bulk_activate_cities(): void
    {
        $this->actingAs($this->makeAdmin($this->tenantA, ['cities.update']));

        $response = $this->post('/store/store-a/admin/cities/bulk-status', [
            'ids' => [$this->cityA1->id, $this->cityA2->id],
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertTrue((bool) $this->cityA1->fresh()->is_active);
        $this->assertTrue((bool) $this->cityA2->fresh()->is_active);
        $this->assertTrue((bool) $this->cityB->fresh()->is_active);
    }

    /** @test */
    public function bulk_deactivate_townships(): void
    {
        $this->actingAs($this->makeAdmin($this->tenantA, ['townships.update']));

        $response = $this->post('/store/store-a/admin/townships/bulk-status', [
            'ids' => [$this->townshipA1->id, $this->townshipA2->id],
            'is_active' => false,
        ]);

        $response->assertRedirect();
        $this->assertFalse((bool) $this->townshipA1->fresh()->is_active);
        $this->assertFalse((bool) $this->townshipA2->fresh()->is_active);
        $this->assertTrue((bool) $this->townshipB->fresh()->is_active);
    }

    /** @test */
    public function foreign_ids_are_ignored_and_never_modified(): void
    {
        $this->actingAs($this->makeAdmin($this->tenantA, ['cities.update', 'townships.update']));

        $response = $this->post('/store/store-a/admin/cities/bulk-status', [
            'ids' => [$this->cityA1->id, $this->cityB->id, 999999],
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertTrue((bool) $this->cityA1->fresh()->is_active);
        $this->assertTrue((bool) $this->cityB->fresh()->is_active);
        $this->assertEquals('Yangon', $this->cityB->fresh()->name);

        $response = $this->post('/store/store-a/admin/townships/bulk-status', [
            'ids' => [$this->townshipA1->id, $this->townshipB->id],
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertTrue((bool) $this->townshipA1->fresh()->is_active);
        $this->assertTrue((bool) $this->townshipB->fresh()->is_active);
        $this->assertEquals(4000, (float) $this->townshipB->fresh()->delivery_fee);
    }

    /** @test */
    public function bulk_deactivate_city_leaves_township_status_unchanged(): void
    {
        $this->actingAs($this->makeAdmin($this->tenantA, ['cities.update']));

        $this->cityA1->update(['is_active' => true]);

        $this->post('/store/store-a/admin/cities/bulk-status', [
            'ids' => [$this->cityA1->id],
            'is_active' => false,
        ])->assertRedirect();

        $this->assertFalse((bool) $this->cityA1->fresh()->is_active);
        $this->assertFalse((bool) $this->townshipA1->fresh()->is_active);
        $this->assertTrue((bool) $this->townshipA2->fresh()->is_active);
    }

    /** @test */
    public function bulk_delete_townships(): void
    {
        $this->actingAs($this->makeAdmin($this->tenantA, ['townships.delete']));

        $response = $this->post('/store/store-a/admin/townships/bulk-destroy', [
            'ids' => [$this->townshipA1->id, $this->townshipA2->id, $this->townshipB->id],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('townships', ['id' => $this->townshipA1->id]);
        $this->assertDatabaseMissing('townships', ['id' => $this->townshipA2->id]);
        $this->assertDatabaseHas('townships', ['id' => $this->townshipB->id]);
    }

    /** @test */
    public function bulk_requests_are_validated(): void
    {
        $this->actingAs($this->makeAdmin($this->tenantA, ['cities.update', 'townships.update']));

        $this->post('/store/store-a/admin/cities/bulk-status', [
            'ids' => [],
            'is_active' => true,
        ])->assertSessionHasErrors('ids');

        $this->post('/store/store-a/admin/cities/bulk-status', [
            'ids' => [$this->cityA1->id],
        ])->assertSessionHasErrors('is_active');

        $this->post('/store/store-a/admin/townships/bulk-status', [
            'ids' => ['not-an-id'],
            'is_active' => true,
        ])->assertSessionHasErrors('ids.0');
    }

    /** @test */
    public function bulk_requires_update_permission(): void
    {
        $this->actingAs($this->makeAdmin($this->tenantA, ['cities.view']));

        $this->post('/store/store-a/admin/cities/bulk-status', [
            'ids' => [$this->cityA1->id],
            'is_active' => true,
        ])->assertForbidden();

        $this->assertFalse((bool) $this->cityA1->fresh()->is_active);
    }
}
