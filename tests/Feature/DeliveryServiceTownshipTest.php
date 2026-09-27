<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\DeliveryPricing;
use App\Models\DeliveryService;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\Township;
use App\Models\User;
use App\Services\DeliveryFeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeliveryServiceTownshipTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private City $yangon;
    private Township $bahan;
    private Township $myitkyina;
    private DeliveryService $express;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Delivery Store', 'slug' => 'delivery-store',
            'store_url' => '/store/delivery-store', 'status' => 'active',
        ]);
        Tenant::setCurrent($this->tenant);

        $this->yangon = City::create(['name' => 'Yangon', 'is_active' => true]);
        $this->bahan = Township::create([
            'city_id' => $this->yangon->id, 'name' => 'Bahan',
            'delivery_fee' => 3000, 'is_active' => true,
        ]);
        $this->myitkyina = Township::create([
            'city_id' => $this->yangon->id, 'name' => 'Myitkyina',
            'delivery_fee' => 5000, 'is_active' => true,
        ]);

        $this->express = DeliveryService::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Express', 'code' => 'express',
            'base_fee' => 2500, 'min_days' => 2, 'max_days' => 4, 'is_active' => true,
        ]);

        DeliveryPricing::create([
            'delivery_service_id' => $this->express->id,
            'township_id' => $this->bahan->id,
            'min_days' => 1,
            'max_days' => 2,
            'is_active' => true,
        ]);
        DeliveryPricing::create([
            'delivery_service_id' => $this->express->id,
            'township_id' => $this->myitkyina->id,
            'min_days' => 3,
            'max_days' => 5,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function townships_in_same_city_can_have_different_delivery_days(): void
    {
        $this->assertEquals(['min' => 1, 'max' => 2], $this->express->getDaysForTownship($this->bahan));
        $this->assertEquals(['min' => 3, 'max' => 5], $this->express->getDaysForTownship($this->myitkyina));

        $fees = app(DeliveryFeeService::class);
        $this->assertEquals(['min' => 1, 'max' => 2], $fees->resolveDeliveryDays($this->express->id, $this->bahan));
        $this->assertEquals(['min' => 3, 'max' => 5], $fees->resolveDeliveryDays($this->express->id, $this->myitkyina));
    }

    /** @test */
    public function final_fee_is_township_fee_plus_service_base_fee(): void
    {
        $fees = app(DeliveryFeeService::class);

        $this->assertEquals(5500, $fees->resolveDeliveryFee($this->bahan, $this->express->id));
        $this->assertEquals(7500, $fees->resolveDeliveryFee($this->myitkyina, $this->express->id));
    }

    /** @test */
    public function pricing_fee_override_is_not_used(): void
    {
        $this->assertFalse(Schema::hasColumn('delivery_pricing', 'fee'));

        $fees = app(DeliveryFeeService::class);

        $this->assertEquals(2500, $this->express->fresh()->getFeeForTownship($this->bahan));
        $this->assertEquals(5500, $fees->resolveDeliveryFee($this->bahan, $this->express->id));
    }

    /** @test */
    public function inactive_mapping_makes_service_unavailable_with_default_days(): void
    {
        DeliveryPricing::where('delivery_service_id', $this->express->id)
            ->where('township_id', $this->bahan->id)
            ->update(['is_active' => false]);

        $fees = app(DeliveryFeeService::class);

        $this->assertFalse($fees->getAvailableServices($this->bahan)->contains('id', $this->express->id));
        $this->assertTrue($fees->getAvailableServices($this->myitkyina)->contains('id', $this->express->id));
        $this->assertFalse(
            collect($fees->getServicesWithPricing($this->bahan))->contains(fn ($e) => $e['service']->id === $this->express->id)
        );
        $this->assertEquals(['min' => 2, 'max' => 4], $this->express->fresh()->getDaysForTownship($this->bahan));
    }

    /** @test */
    public function cross_tenant_township_service_combination_is_rejected(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'Other Store', 'slug' => 'other-delivery-store',
            'store_url' => '/store/other-delivery-store', 'status' => 'active',
        ]);
        $otherCity = City::withoutTenantScope()->create([
            'tenant_id' => $otherTenant->id, 'name' => 'Yangon', 'is_active' => true,
        ]);
        $otherTownship = Township::withoutTenantScope()->create([
            'tenant_id' => $otherTenant->id, 'city_id' => $otherCity->id,
            'name' => 'Bahan', 'delivery_fee' => 4000, 'is_active' => true,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        app(DeliveryFeeService::class)->resolveDeliveryFee($otherTownship, $this->express->id);
    }

    /** @test */
    public function service_days_fall_back_to_defaults_without_mapping(): void
    {
        $unmapped = Township::create([
            'city_id' => $this->yangon->id, 'name' => 'Unmapped',
            'delivery_fee' => 1000, 'is_active' => true,
        ]);

        $this->assertEquals(['min' => 2, 'max' => 4], $this->express->getDaysForTownship($unmapped));
        $this->assertEquals(['min' => 2, 'max' => 4], app(DeliveryFeeService::class)->resolveDeliveryDays($this->express->id, $unmapped));
    }

    /** @test */
    public function no_service_selected_means_township_fee_only(): void
    {
        $fees = app(DeliveryFeeService::class);

        $this->assertEquals(3000, $fees->resolveDeliveryFee($this->bahan));
        $this->assertEquals(5000, $fees->resolveDeliveryFee($this->myitkyina));
    }

    /** @test */
    public function order_snapshot_keeps_final_fee_after_changes(): void
    {
        $buyer = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Snapshot Buyer',
            'email' => 'snapshot@test.com', 'password' => bcrypt('password'), 'status' => 'active',
        ]);
        $order = Order::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $buyer->id,
            'first_name' => 'Snap', 'last_name' => 'Buyer', 'phone' => '09900000000',
            'address' => 'Snap Road', 'city_id' => $this->yangon->id, 'township_id' => $this->bahan->id,
            'delivery_service_id' => $this->express->id,
            'delivery_days_min' => 1, 'delivery_days_max' => 2,
            'subtotal' => 5000, 'delivery_fee' => 5500, 'total_amount' => 10500,
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
            'order_status' => Order::ORDER_STATUS_PENDING,
        ]);

        $this->bahan->update(['delivery_fee' => 9000]);
        $this->express->update(['base_fee' => 99000]);

        $fresh = $order->fresh();
        $this->assertEquals(5500, (float) $fresh->delivery_fee);
        $this->assertEquals(10500, (float) $fresh->total_amount);
        $this->assertEquals($this->express->id, (int) $fresh->delivery_service_id);
        $this->assertEquals(1, (int) $fresh->delivery_days_min);
        $this->assertEquals(2, (int) $fresh->delivery_days_max);
    }
}
