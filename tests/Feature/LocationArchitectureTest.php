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

    /** @test */
    public function cities_are_created_without_tenant_id(): void
    {
        $city = City::create([
            'name' => 'Test City',
            'delivery_fee' => 1500,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('cities', [
            'name' => 'Test City',
            'delivery_fee' => 1500,
        ]);
    }

    /** @test */
    public function townships_belong_to_cities(): void
    {
        $city = City::create([
            'name' => 'Mandalay',
            'delivery_fee' => 2000,
            'is_active' => true,
        ]);

        $township = Township::create([
            'city_id' => $city->id,
            'name' => 'Chan Aye Thar Zan',
            'postal_code' => '05012',
            'is_active' => true,
        ]);

        $this->assertEquals($city->id, $township->city->id);
    }

    /** @test */
    public function inactive_cities_are_filtered_in_checkout(): void
    {
        $activeCity = City::create([
            'name' => 'Active City',
            'delivery_fee' => 1000,
            'is_active' => true,
        ]);

        $inactiveCity = City::create([
            'name' => 'Inactive City',
            'delivery_fee' => 2000,
            'is_active' => false,
        ]);

        $activeCities = City::active()->get();

        $this->assertTrue($activeCities->contains('id', $activeCity->id));
        $this->assertFalse($activeCities->contains('id', $inactiveCity->id));
    }

    /** @test */
    public function inactive_townships_are_filtered_by_city(): void
    {
        $city = City::create([
            'name' => 'Test City',
            'delivery_fee' => 1000,
            'is_active' => true,
        ]);

        Township::create([
            'city_id' => $city->id,
            'name' => 'Active Township',
            'is_active' => true,
        ]);

        Township::create([
            'city_id' => $city->id,
            'name' => 'Inactive Township',
            'is_active' => false,
        ]);

        $activeTownships = Township::where('city_id', $city->id)->active()->get();

        $this->assertEquals(1, $activeTownships->count());
        $this->assertEquals('Active Township', $activeTownships->first()->name);
    }

    /** @test */
    public function city_name_must_be_unique(): void
    {
        City::create([
            'name' => 'Unique City',
            'delivery_fee' => 1000,
            'is_active' => true,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        City::create([
            'name' => 'Unique City',
            'delivery_fee' => 2000,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function township_name_must_be_unique_within_city(): void
    {
        $city = City::create([
            'name' => 'Test City',
            'delivery_fee' => 1000,
            'is_active' => true,
        ]);

        Township::create([
            'city_id' => $city->id,
            'name' => 'Unique Township',
            'is_active' => true,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Township::create([
            'city_id' => $city->id,
            'name' => 'Unique Township',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function delivery_pricing_overrides_base_fee(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Store',
            'slug' => 'test-store',
            'store_url' => '/store/test-store',
            'status' => 'active',
        ]);

        $city = City::create([
            'name' => 'Pricing City',
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
            'city_id' => $city->id,
            'fee' => 2500,
            'min_days' => 1,
            'max_days' => 1,
            'is_active' => true,
        ]);

        $this->assertEquals(2500, $service->getFeeForCity($city));
        $this->assertEquals(['min' => 1, 'max' => 1], $service->getDaysForCity($city));
    }

    /** @test */
    public function delivery_falls_back_to_base_fee_without_pricing(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Store',
            'slug' => 'test-store2',
            'store_url' => '/store/test-store2',
            'status' => 'active',
        ]);

        $city = City::create([
            'name' => 'Fallback City',
            'delivery_fee' => 500,
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

        $this->assertEquals(2000, $service->getFeeForCity($city));
        $this->assertEquals(['min' => 3, 'max' => 5], $service->getDaysForCity($city));
    }

    /** @test */
    public function delivery_fee_service_uses_fallback_when_no_services(): void
    {
        $city = City::create([
            'name' => 'Fallback Only City',
            'delivery_fee' => 1500,
            'is_active' => true,
        ]);

        $service = new DeliveryFeeService();
        $fee = $service->resolveDeliveryFee($city, null);

        $this->assertEquals(1500, $fee);
    }

    /** @test */
    public function inactive_delivery_pricing_is_ignored(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Store',
            'slug' => 'test-store3',
            'store_url' => '/store/test-store3',
            'status' => 'active',
        ]);

        $city = City::create([
            'name' => 'Inactive Pricing City',
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
            'city_id' => $city->id,
            'fee' => 100,
            'is_active' => false,
        ]);

        $this->assertEquals(3000, $service->getFeeForCity($city));
    }

    /** @test */
    public function inactive_delivery_service_is_ignored(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Store',
            'slug' => 'test-store4',
            'store_url' => '/store/test-store4',
            'status' => 'active',
        ]);

        $city = City::create([
            'name' => 'Inactive Service City',
            'delivery_fee' => 1000,
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

        $service = new DeliveryFeeService();
        $services = $service->getAvailableServices($city);

        $this->assertFalse($services->contains('id', $inactiveService->id));
    }
}
