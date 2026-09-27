<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\DeliveryPricing;
use App\Models\DeliveryService;
use App\Models\Township;
use App\Models\Tenant;
use App\Services\DeliveryFeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationArchitectureTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(string $name, string $slug): Tenant
    {
        return Tenant::create([
            'name' => $name,
            'slug' => $slug,
            'store_url' => '/store/' . $slug,
            'status' => 'active',
        ]);
    }

    /** @test */
    public function cities_belong_to_current_tenant(): void
    {
        $tenant = $this->makeTenant('Test Store', 'test-store');
        Tenant::setCurrent($tenant);

        $city = City::create([
            'name' => 'Test City',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('cities', [
            'name' => 'Test City',
            'tenant_id' => $tenant->id,
        ]);
        $this->assertEquals($tenant->id, $city->tenant_id);
    }

    /** @test */
    public function townships_belong_to_cities(): void
    {
        $tenant = $this->makeTenant('Test Store', 'test-store');
        Tenant::setCurrent($tenant);

        $city = City::create([
            'name' => 'Mandalay',
            'is_active' => true,
        ]);

        $township = Township::create([
            'city_id' => $city->id,
            'name' => 'Chan Aye Thar Zan',
            'postal_code' => '05012',
            'delivery_fee' => 2000,
            'is_active' => true,
        ]);

        $this->assertEquals($city->id, $township->city->id);
        $this->assertEquals($tenant->id, $township->tenant_id);
        $this->assertEquals($tenant->id, $township->city->tenant_id);
    }

    /** @test */
    public function inactive_cities_are_filtered_in_checkout(): void
    {
        $tenant = $this->makeTenant('Filter Store', 'filter-store');
        Tenant::setCurrent($tenant);

        $activeCity = City::create([
            'name' => 'Active City',
            'is_active' => true,
        ]);

        $inactiveCity = City::create([
            'name' => 'Inactive City',
            'is_active' => false,
        ]);

        $activeCities = City::active()->get();

        $this->assertTrue($activeCities->contains('id', $activeCity->id));
        $this->assertFalse($activeCities->contains('id', $inactiveCity->id));
    }

    /** @test */
    public function inactive_townships_are_filtered_by_city(): void
    {
        $tenant = $this->makeTenant('Filter Store', 'filter-store-twp');
        Tenant::setCurrent($tenant);

        $city = City::create([
            'name' => 'Test City',
            'is_active' => true,
        ]);

        Township::create([
            'city_id' => $city->id,
            'name' => 'Active Township',
            'delivery_fee' => 1000,
            'is_active' => true,
        ]);

        Township::create([
            'city_id' => $city->id,
            'name' => 'Inactive Township',
            'delivery_fee' => 1000,
            'is_active' => false,
        ]);

        $activeTownships = Township::where('city_id', $city->id)->active()->get();

        $this->assertEquals(1, $activeTownships->count());
        $this->assertEquals('Active Township', $activeTownships->first()->name);
    }

    /** @test */
    public function city_name_must_be_unique_within_tenant(): void
    {
        $tenant = $this->makeTenant('Test Store', 'test-store');
        Tenant::setCurrent($tenant);

        City::create([
            'tenant_id' => $tenant->id,
            'name' => 'Unique City',
            'is_active' => true,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        City::withoutTenantScope()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Unique City',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function city_name_may_repeat_across_tenants(): void
    {
        $tenantA = $this->makeTenant('Store A', 'store-a');
        $tenantB = $this->makeTenant('Store B', 'store-b');

        City::withoutTenantScope()->create(['tenant_id' => $tenantA->id, 'name' => 'Yangon', 'is_active' => true]);
        City::withoutTenantScope()->create(['tenant_id' => $tenantB->id, 'name' => 'Yangon', 'is_active' => true]);

        $this->assertEquals(1, City::withoutTenantScope()->where('tenant_id', $tenantA->id)->where('name', 'Yangon')->count());
        $this->assertEquals(1, City::withoutTenantScope()->where('tenant_id', $tenantB->id)->where('name', 'Yangon')->count());
    }

    /** @test */
    public function township_name_must_be_unique_within_city_and_tenant(): void
    {
        $tenant = $this->makeTenant('Test Store', 'test-store');
        Tenant::setCurrent($tenant);

        $city = City::create([
            'tenant_id' => $tenant->id,
            'name' => 'Test City',
            'is_active' => true,
        ]);

        Township::create([
            'tenant_id' => $tenant->id,
            'city_id' => $city->id,
            'name' => 'Unique Township',
            'delivery_fee' => 1000,
            'is_active' => true,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Township::withoutTenantScope()->create([
            'tenant_id' => $tenant->id,
            'city_id' => $city->id,
            'name' => 'Unique Township',
            'delivery_fee' => 1000,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function delivery_pricing_sets_township_days_and_base_fee_applies(): void
    {
        $tenant = $this->makeTenant('Test Store', 'test-store');

        $city = City::withoutTenantScope()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Pricing City',
            'is_active' => true,
        ]);
        $township = Township::withoutTenantScope()->create([
            'tenant_id' => $tenant->id,
            'city_id' => $city->id,
            'name' => 'Pricing Township',
            'delivery_fee' => 1000,
            'is_active' => true,
        ]);

        $service = DeliveryService::create([
            'tenant_id' => $tenant->id,
            'name' => 'Express Delivery',
            'code' => 'express',
            'base_fee' => 3000,
            'fee_per_kg' => 500,
            'min_days' => 1,
            'max_days' => 2,
            'is_active' => true,
        ]);

        DeliveryPricing::create([
            'delivery_service_id' => $service->id,
            'township_id' => $township->id,
            'fee' => 2500,
            'min_days' => 1,
            'max_days' => 1,
            'is_active' => true,
        ]);

        $this->assertEquals(3000, $service->getFeeForTownship($township));
        $this->assertEquals(['min' => 1, 'max' => 1], $service->getDaysForTownship($township));
    }

    /** @test */
    public function delivery_falls_back_to_base_fee_without_pricing(): void
    {
        $tenant = $this->makeTenant('Test Store', 'test-store2');

        $city = City::withoutTenantScope()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Fallback City',
            'is_active' => true,
        ]);
        $township = Township::withoutTenantScope()->create([
            'tenant_id' => $tenant->id,
            'city_id' => $city->id,
            'name' => 'Fallback Township',
            'delivery_fee' => 1000,
            'is_active' => true,
        ]);

        $service = DeliveryService::create([
            'tenant_id' => $tenant->id,
            'name' => 'Standard Delivery',
            'code' => 'standard',
            'base_fee' => 2000,
            'fee_per_kg' => 300,
            'min_days' => 3,
            'max_days' => 5,
            'is_active' => true,
        ]);

        $this->assertEquals(2000, $service->getFeeForTownship($township));
        $this->assertEquals(['min' => 3, 'max' => 5], $service->getDaysForTownship($township));
    }

    /** @test */
    public function delivery_fee_service_uses_township_fee_when_no_services(): void
    {
        $tenant = $this->makeTenant('Fallback Store', 'fallback-store');
        Tenant::setCurrent($tenant);

        $township = Township::create([
            'city_id' => City::create(['name' => 'Fallback Only City', 'is_active' => true])->id,
            'name' => 'Fallback Township',
            'delivery_fee' => 1500,
            'is_active' => true,
        ]);

        $service = new DeliveryFeeService();
        $fee = $service->resolveDeliveryFee($township);

        $this->assertEquals(1500, $fee);
    }

    /** @test */
    public function inactive_delivery_pricing_is_ignored(): void
    {
        $tenant = $this->makeTenant('Test Store', 'test-store3');

        $city = City::withoutTenantScope()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Inactive Pricing City',
            'is_active' => true,
        ]);
        $township = Township::withoutTenantScope()->create([
            'tenant_id' => $tenant->id,
            'city_id' => $city->id,
            'name' => 'Inactive Pricing Township',
            'delivery_fee' => 500,
            'is_active' => true,
        ]);

        $service = DeliveryService::create([
            'tenant_id' => $tenant->id,
            'name' => 'Standard Delivery',
            'code' => 'standard2',
            'base_fee' => 3000,
            'min_days' => 2,
            'max_days' => 4,
            'is_active' => true,
        ]);

        DeliveryPricing::create([
            'delivery_service_id' => $service->id,
            'township_id' => $township->id,
            'fee' => 100,
            'is_active' => false,
        ]);

        $this->assertEquals(3000, $service->getFeeForTownship($township));
        $this->assertEquals(['min' => 2, 'max' => 4], $service->getDaysForTownship($township));
    }

    /** @test */
    public function inactive_delivery_service_is_ignored(): void
    {
        $tenant = $this->makeTenant('Test Store', 'test-store4');

        $city = City::withoutTenantScope()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Inactive Service City',
            'is_active' => true,
        ]);

        $inactiveService = DeliveryService::create([
            'tenant_id' => $tenant->id,
            'name' => 'Inactive Service',
            'code' => 'inactive',
            'base_fee' => 500,
            'min_days' => 1,
            'max_days' => 1,
            'is_active' => false,
        ]);

        Tenant::setCurrent($tenant);

        $service = new DeliveryFeeService();
        $services = $service->getAvailableServices();

        $this->assertFalse($services->contains('id', $inactiveService->id));
    }
}
