<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\DeliveryPricing;
use App\Models\DeliveryService;
use App\Models\Tenant;
use App\Models\Township;
use App\Services\DeliveryFeeService;
use App\Services\DeliveryPricingBackfillService;
use App\Services\DeliveryServiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeliveryPricingExpansionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private DeliveryService $service;
    private City $city;
    private Township $township;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Expansion Store', 'slug' => 'expansion-store',
            'store_url' => '/store/expansion-store', 'status' => 'active',
        ]);
        Tenant::setCurrent($this->tenant);

        $this->service = DeliveryService::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Express', 'code' => 'express',
            'base_fee' => 2500, 'min_days' => 1, 'max_days' => 2, 'is_active' => true,
        ]);
        $this->city = City::create(['name' => 'Yangon', 'is_active' => true]);
        $this->township = Township::create([
            'city_id' => $this->city->id, 'name' => 'Bahan',
            'delivery_fee' => 3000, 'is_active' => true,
        ]);
    }

    /** @test */
    public function final_schema_is_township_grain_only(): void
    {
        $this->assertTrue(Schema::hasColumn('delivery_pricing', 'township_id'));
        $this->assertTrue(Schema::hasColumn('delivery_pricing', 'delivery_service_id'));
        $this->assertFalse(Schema::hasColumn('delivery_pricing', 'city_id'));
        $this->assertFalse(Schema::hasColumn('delivery_pricing', 'fee'));

        $names = collect(Schema::getIndexes('delivery_pricing'))->pluck('name');
        $this->assertContains('delivery_pricing_service_township_unique', $names);
        $this->assertNotContains('delivery_pricing_unique', $names);
    }

    /** @test */
    public function backfill_is_noop_on_contracted_schema(): void
    {
        $stats = app(DeliveryPricingBackfillService::class)->run();

        $this->assertEquals(0, $stats['source_rows']);
        $this->assertEquals(0, $stats['township_rows_created']);
        $this->assertEquals(0, DeliveryPricing::count());
    }

    /** @test */
    public function township_mapping_crud_works_on_final_schema(): void
    {
        $service = app(DeliveryServiceService::class);

        $pricing = $service->addTownshipPricing($this->service, [
            'township_id' => $this->township->id,
            'min_days' => 1,
            'max_days' => 3,
        ]);

        $this->assertNotNull($pricing->id);
        $this->assertEquals($this->township->id, $pricing->township_id);
        $this->assertDatabaseHas('delivery_pricing', [
            'delivery_service_id' => $this->service->id,
            'township_id' => $this->township->id,
            'min_days' => 1,
            'max_days' => 3,
        ]);

        $fees = app(DeliveryFeeService::class);
        $this->assertEquals(['min' => 1, 'max' => 3], $this->service->fresh()->getDaysForTownship($this->township));
        $this->assertEquals(5500, $fees->resolveDeliveryFee($this->township, $this->service->id));

        $this->assertTrue($service->removeTownshipPricing($pricing));
        $this->assertEquals(0, DeliveryPricing::count());
    }

    /** @test */
    public function township_mapping_enforces_unique_per_service(): void
    {
        DeliveryPricing::create([
            'delivery_service_id' => $this->service->id,
            'township_id' => $this->township->id,
            'is_active' => true,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DeliveryPricing::create([
            'delivery_service_id' => $this->service->id,
            'township_id' => $this->township->id,
            'is_active' => true,
        ]);
    }
}
