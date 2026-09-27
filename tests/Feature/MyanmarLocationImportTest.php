<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Tenant;
use App\Models\Township;
use App\Models\User;
use App\Services\MyanmarLocationImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MyanmarLocationImportTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private array $dataset;

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
        $this->dataset = require database_path('data/myanmar_locations.php');
    }

    private function makeAdmin(Tenant $tenant, array $permissions): User
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);

        $user = User::create([
            'tenant_id' => $tenant->id, 'name' => 'Import Admin',
            'email' => 'import-admin-' . $tenant->slug . '-' . uniqid() . '@test.com', 'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $user->assignRole($role);
        foreach ($permissions as $name) {
            $user->givePermissionTo($name);
        }

        return $user;
    }

    private function expectedTownshipTotal(): int
    {
        return collect($this->dataset)->sum(fn ($c) => count($c['townships']));
    }

    /** @test */
    public function import_creates_missing_cities_and_townships(): void
    {
        $stats = app(MyanmarLocationImportService::class)->import($this->tenantA);

        $this->assertEquals(count($this->dataset), $stats['cities_created']);
        $this->assertEquals($this->expectedTownshipTotal(), $stats['townships_created']);
        $this->assertEquals(count($this->dataset), City::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count());
        $this->assertEquals($this->expectedTownshipTotal(), Township::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count());
    }

    /** @test */
    public function import_adds_only_missing_townships_to_existing_city(): void
    {
        $city = City::withoutTenantScope()->create([
            'tenant_id' => $this->tenantA->id, 'name' => $this->dataset[0]['name'], 'is_active' => true,
        ]);

        $stats = app(MyanmarLocationImportService::class)->import($this->tenantA);

        $this->assertEquals(count($this->dataset) - 1, $stats['cities_created']);
        $this->assertEquals(1, $stats['cities_skipped']);
        $this->assertEquals($this->expectedTownshipTotal(), $stats['townships_created']);
        $this->assertEquals($city->id, City::withoutTenantScope()
            ->where('tenant_id', $this->tenantA->id)
            ->where('name', $this->dataset[0]['name'])->value('id'));
    }

    /** @test */
    public function existing_city_is_not_overwritten(): void
    {
        $city = City::withoutTenantScope()->create([
            'tenant_id' => $this->tenantA->id, 'name' => $this->dataset[0]['name'], 'is_active' => false,
        ]);

        app(MyanmarLocationImportService::class)->import($this->tenantA);

        $fresh = $city->fresh();
        $this->assertEquals($this->dataset[0]['name'], $fresh->name);
        $this->assertFalse((bool) $fresh->is_active);
    }

    /** @test */
    public function existing_township_keeps_custom_data(): void
    {
        $city = City::withoutTenantScope()->create([
            'tenant_id' => $this->tenantA->id, 'name' => $this->dataset[0]['name'], 'is_active' => true,
        ]);
        $first = $this->dataset[0]['townships'][0];
        $firstName = is_array($first) ? $first['name'] : $first;
        $township = Township::withoutTenantScope()->create([
            'tenant_id' => $this->tenantA->id, 'city_id' => $city->id,
            'name' => $firstName, 'postal_code' => 'CUSTOM', 'delivery_fee' => 9999, 'is_active' => false,
        ]);

        app(MyanmarLocationImportService::class)->import($this->tenantA);

        $fresh = $township->fresh();
        $this->assertEquals('CUSTOM', $fresh->postal_code);
        $this->assertEquals(9999, (float) $fresh->delivery_fee);
        $this->assertFalse((bool) $fresh->is_active);
    }

    /** @test */
    public function import_is_tenant_isolated(): void
    {
        app(MyanmarLocationImportService::class)->import($this->tenantA);

        $this->assertEquals(0, City::withoutTenantScope()->where('tenant_id', $this->tenantB->id)->count());
        $this->assertEquals(0, Township::withoutTenantScope()->where('tenant_id', $this->tenantB->id)->count());

        $statsB = app(MyanmarLocationImportService::class)->import($this->tenantB);

        $this->assertEquals(count($this->dataset), $statsB['cities_created']);
        $this->assertEquals(
            count($this->dataset),
            City::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count()
        );
    }

    /** @test */
    public function import_is_idempotent_without_duplicates(): void
    {
        $service = app(MyanmarLocationImportService::class);
        $first = $service->import($this->tenantA);
        $second = $service->import($this->tenantA);

        $this->assertGreaterThan(0, $first['cities_created']);
        $this->assertEquals(0, $second['cities_created']);
        $this->assertEquals(0, $second['townships_created']);
        $this->assertEquals(
            count($this->dataset),
            City::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count()
        );
        $this->assertEquals(
            $this->expectedTownshipTotal(),
            Township::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count()
        );
    }

    /** @test */
    public function import_preview_reports_counts_without_writing(): void
    {
        $city = City::withoutTenantScope()->create([
            'tenant_id' => $this->tenantA->id, 'name' => $this->dataset[0]['name'], 'is_active' => true,
        ]);
        Township::withoutTenantScope()->create([
            'tenant_id' => $this->tenantA->id, 'city_id' => $city->id,
            'name' => is_array($this->dataset[0]['townships'][0]) ? $this->dataset[0]['townships'][0]['name'] : $this->dataset[0]['townships'][0],
            'delivery_fee' => 1000, 'is_active' => true,
        ]);

        $preview = app(MyanmarLocationImportService::class)->preview($this->tenantA);

        $this->assertEquals(count($this->dataset) - 1, $preview['cities_to_add']);
        $this->assertEquals(1, $preview['existing_cities']);
        $this->assertEquals(1, $preview['existing_townships']);
        $this->assertEquals($this->expectedTownshipTotal() - 1, $preview['townships_to_add']);
        $this->assertEquals(1, City::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count());
    }

    /** @test */
    public function import_http_flow_shows_preview_and_summary(): void
    {
        $this->actingAs($this->makeAdmin($this->tenantA, ['cities.create']));

        $preview = $this->getJson('/store/store-a/admin/locations/import-myanmar/preview');
        $preview->assertOk();
        $preview->assertJson([
            'cities_to_add' => count($this->dataset),
            'townships_to_add' => $this->expectedTownshipTotal(),
            'existing_cities' => 0,
            'existing_townships' => 0,
        ]);

        $this->post('/store/store-a/admin/locations/import-myanmar')->assertRedirect();

        $this->assertEquals(count($this->dataset), City::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count());
        $this->assertEquals(0, City::withoutTenantScope()->where('tenant_id', $this->tenantB->id)->count());
    }

    /** @test */
    public function import_requires_create_permission(): void
    {
        $this->actingAs($this->makeAdmin($this->tenantA, ['cities.view']));

        $this->getJson('/store/store-a/admin/locations/import-myanmar/preview')->assertForbidden();
        $this->post('/store/store-a/admin/locations/import-myanmar')->assertForbidden();

        $this->assertEquals(0, City::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count());
    }
}
