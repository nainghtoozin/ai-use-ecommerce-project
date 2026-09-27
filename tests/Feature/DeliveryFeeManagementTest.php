<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Tenant;
use App\Models\Township;
use App\Models\User;
use App\Services\LocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DeliveryFeeManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private Township $feeA1;
    private Township $feeA2;
    private Township $feeB;

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

        $this->feeA1 = Township::withoutTenantScope()->create([
            'tenant_id' => $this->tenantA->id, 'city_id' => $cityA->id,
            'name' => 'Bahan', 'delivery_fee' => 3000, 'is_active' => true,
        ]);
        $this->feeA2 = Township::withoutTenantScope()->create([
            'tenant_id' => $this->tenantA->id, 'city_id' => $cityA->id,
            'name' => 'Kamayut', 'delivery_fee' => 3500, 'is_active' => true,
        ]);
        $this->feeB = Township::withoutTenantScope()->create([
            'tenant_id' => $this->tenantB->id, 'city_id' => $cityB->id,
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
            'tenant_id' => $tenant->id, 'name' => 'Fee Admin',
            'email' => 'fee-admin-' . $tenant->slug . '@test.com', 'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $user->assignRole($role);
        foreach ($permissions as $name) {
            $user->givePermissionTo($name);
        }

        return $user;
    }

    /** @test */
    public function townships_page_lists_only_current_tenant_fees(): void
    {
        $this->actingAs($this->makeAdmin($this->tenantA, ['townships.view']));

        $response = $this->get('/store/store-a/admin/townships');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('townships.data', 2)
            ->where('townships.data.0.delivery_fee', 3000)
            ->where('townships.data.1.delivery_fee', 3500));
    }

    /** @test */
    public function single_fee_update(): void
    {
        $this->actingAs($this->makeAdmin($this->tenantA, ['townships.update']));

        $response = $this->post('/store/store-a/admin/townships/update-fees', [
            'ids' => [$this->feeA1->id],
            'delivery_fee' => 4500,
        ]);

        $response->assertRedirect();
        $this->assertEquals(4500, (float) $this->feeA1->fresh()->delivery_fee);
        $this->assertEquals(3500, (float) $this->feeA2->fresh()->delivery_fee);
        $this->assertEquals(4000, (float) $this->feeB->fresh()->delivery_fee);
    }

    /** @test */
    public function bulk_fee_update(): void
    {
        $this->actingAs($this->makeAdmin($this->tenantA, ['townships.update']));

        $response = $this->post('/store/store-a/admin/townships/update-fees', [
            'ids' => [$this->feeA1->id, $this->feeA2->id],
            'delivery_fee' => 5000,
        ]);

        $response->assertRedirect();
        $this->assertEquals(5000, (float) $this->feeA1->fresh()->delivery_fee);
        $this->assertEquals(5000, (float) $this->feeA2->fresh()->delivery_fee);
        $this->assertEquals(4000, (float) $this->feeB->fresh()->delivery_fee);
    }

    /** @test */
    public function foreign_township_ids_are_ignored(): void
    {
        $this->actingAs($this->makeAdmin($this->tenantA, ['townships.update']));

        $response = $this->post('/store/store-a/admin/townships/update-fees', [
            'ids' => [$this->feeA1->id, $this->feeB->id, 999999],
            'delivery_fee' => 6000,
        ]);

        $response->assertRedirect();
        $this->assertEquals(6000, (float) $this->feeA1->fresh()->delivery_fee);
        $this->assertEquals(4000, (float) $this->feeB->fresh()->delivery_fee);
    }

    /** @test */
    public function invalid_fee_is_rejected(): void
    {
        $this->actingAs($this->makeAdmin($this->tenantA, ['townships.update']));

        $this->post('/store/store-a/admin/townships/update-fees', [
            'ids' => [$this->feeA1->id],
            'delivery_fee' => -100,
        ])->assertSessionHasErrors('delivery_fee');

        $this->post('/store/store-a/admin/townships/update-fees', [
            'ids' => [$this->feeA1->id],
            'delivery_fee' => 'free',
        ])->assertSessionHasErrors('delivery_fee');

        $this->post('/store/store-a/admin/townships/update-fees', [
            'ids' => [],
            'delivery_fee' => 100,
        ])->assertSessionHasErrors('ids');

        $this->assertEquals(3000, (float) $this->feeA1->fresh()->delivery_fee);
    }

    /** @test */
    public function fee_endpoints_require_permissions(): void
    {
        $this->actingAs($this->makeAdmin($this->tenantA, ['townships.view']));

        $this->post('/store/store-a/admin/townships/update-fees', [
            'ids' => [$this->feeA1->id],
            'delivery_fee' => 6000,
        ])->assertForbidden();

        $this->assertEquals(3000, (float) $this->feeA1->fresh()->delivery_fee);
    }

    /** @test */
    public function fee_update_invalidates_location_cache(): void
    {
        Tenant::setCurrent($this->tenantA);
        $before = City::getActiveWithTownships();
        $this->assertEquals(3000, (float) $before->firstWhere('name', 'Yangon')
            ->townships->firstWhere('name', 'Bahan')->delivery_fee);

        app(LocationService::class)->setTownshipFees([$this->feeA1->id], 7777);

        $after = City::getActiveWithTownships();
        $this->assertEquals(7777, (float) $after->firstWhere('name', 'Yangon')
            ->townships->firstWhere('name', 'Bahan')->delivery_fee);
    }
}
