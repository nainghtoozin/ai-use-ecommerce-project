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

class TownshipPaginationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private City $cityA;
    private array $townshipIds = [];

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

        $this->cityA = City::withoutTenantScope()->create([
            'tenant_id' => $this->tenantA->id, 'name' => 'Bago', 'is_active' => true,
        ]);
        $cityB = City::withoutTenantScope()->create([
            'tenant_id' => $this->tenantB->id, 'name' => 'Bago', 'is_active' => true,
        ]);

        for ($i = 1; $i <= 30; $i++) {
            $this->townshipIds[] = Township::withoutTenantScope()->create([
                'tenant_id' => $this->tenantA->id, 'city_id' => $this->cityA->id,
                'name' => sprintf('TWP-%02d', $i), 'delivery_fee' => 1000 + $i, 'is_active' => $i % 2 === 0,
            ])->id;
        }

        Township::withoutTenantScope()->create([
            'tenant_id' => $this->tenantB->id, 'city_id' => $cityB->id,
            'name' => 'Foreign', 'delivery_fee' => 9999, 'is_active' => true,
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
            'tenant_id' => $tenant->id, 'name' => 'Page Admin',
            'email' => 'page-admin-' . $tenant->slug . '-' . uniqid() . '@test.com', 'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $user->assignRole($role);
        foreach ($permissions as $name) {
            $user->givePermissionTo($name);
        }

        return $user;
    }

    private function getIndex(array $params = [])
    {
        $this->actingAs($this->makeAdmin($this->tenantA, ['townships.view']));

        return $this->get('/store/store-a/admin/townships?' . http_build_query($params));
    }

    /** @test */
    public function page_size_25_is_default(): void
    {
        $response = $this->getIndex();

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('townships.data', 25)
            ->where('townships.total', 30)
            ->where('filters.per_page', '25'));
    }

    /** @test */
    public function page_size_50_and_100(): void
    {
        $this->getIndex(['per_page' => '50'])->assertOk()
            ->assertInertia(fn ($page) => $page->has('townships.data', 30));

        $this->getIndex(['per_page' => '100'])->assertOk()
            ->assertInertia(fn ($page) => $page->has('townships.data', 30));
    }

    /** @test */
    public function page_size_1000_and_all(): void
    {
        $this->getIndex(['per_page' => '1000'])->assertOk()
            ->assertInertia(fn ($page) => $page->has('townships.data', 30));

        $this->getIndex(['per_page' => 'all'])->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('townships.data', 30)
                ->where('filters.per_page', 'all'));
    }

    /** @test */
    public function invalid_page_size_falls_back_to_default(): void
    {
        $this->getIndex(['per_page' => '10'])->assertOk()
            ->assertInertia(fn ($page) => $page->has('townships.data', 25));

        $this->getIndex(['per_page' => 'everything'])->assertOk()
            ->assertInertia(fn ($page) => $page->has('townships.data', 25));
    }

    /** @test */
    public function matching_ids_returns_filtered_tenant_scoped_ids(): void
    {
        $this->actingAs($this->makeAdmin($this->tenantA, ['townships.view']));

        $response = $this->getJson('/store/store-a/admin/townships/matching-ids?city_id=' . $this->cityA->id);

        $response->assertOk();
        $this->assertEquals(30, $response->json('total'));
        $this->assertEqualsCanonicalizing($this->townshipIds, $response->json('ids'));
        $this->assertFalse($response->json('truncated'));
    }

    /** @test */
    public function matching_ids_respects_search_and_status(): void
    {
        $this->actingAs($this->makeAdmin($this->tenantA, ['townships.view']));

        $response = $this->getJson('/store/store-a/admin/townships/matching-ids?search=TWP-01&status=inactive');

        $response->assertOk();
        $this->assertEquals(1, $response->json('total'));
        $this->assertCount(1, $response->json('ids'));
    }

    /** @test */
    public function matching_ids_requires_view_permission(): void
    {
        $this->actingAs($this->makeAdmin($this->tenantA, ['cities.view']));

        $this->getJson('/store/store-a/admin/townships/matching-ids')->assertForbidden();
    }

    /** @test */
    public function bulk_fee_update_across_multiple_pages(): void
    {
        $this->actingAs($this->makeAdmin($this->tenantA, ['townships.update']));

        $response = $this->post('/store/store-a/admin/townships/update-fees', [
            'ids' => $this->townshipIds,
            'delivery_fee' => 7777,
        ]);

        $response->assertRedirect();
        $this->assertEquals(30, Township::withoutTenantScope()
            ->whereIn('id', $this->townshipIds)
            ->where('delivery_fee', 7777)
            ->count());
    }

    /** @test */
    public function bulk_activate_across_multiple_pages_ignores_foreign_ids(): void
    {
        $foreignId = Township::withoutTenantScope()->where('tenant_id', $this->tenantB->id)->value('id');
        $this->actingAs($this->makeAdmin($this->tenantA, ['townships.update']));

        $response = $this->post('/store/store-a/admin/townships/bulk-status', [
            'ids' => array_merge($this->townshipIds, [$foreignId]),
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertEquals(30, Township::withoutTenantScope()
            ->whereIn('id', $this->townshipIds)
            ->where('is_active', true)
            ->count());
        $this->assertEquals(9999, (float) Township::withoutTenantScope()->find($foreignId)->delivery_fee);
    }

    /** @test */
    public function oversized_bulk_request_is_rejected(): void
    {
        $this->actingAs($this->makeAdmin($this->tenantA, ['townships.update']));

        $this->post('/store/store-a/admin/townships/update-fees', [
            'ids' => range(1, 5001),
            'delivery_fee' => 100,
        ])->assertSessionHasErrors('ids');
    }
}
