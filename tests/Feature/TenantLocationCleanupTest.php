<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\DeliveryPricing;
use App\Models\DeliveryService;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\Township;
use App\Models\User;
use App\Services\DeliveryFeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use App\Models\Role;
use Tests\TestCase;

class TenantLocationCleanupTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Cleanup Store', 'slug' => 'cleanup-store',
            'store_url' => '/store/cleanup-store', 'status' => 'active',
        ]);
        Tenant::setCurrent($this->tenant);
    }

    /** @test */
    public function stale_global_township_unique_index_is_removed(): void
    {
        $names = collect(Schema::getIndexes('townships'))->pluck('name');

        $this->assertNotContains('townships_city_id_name_unique', $names);

        $perTenant = collect(Schema::getIndexes('townships'))
            ->firstWhere('name', 'townships_tenant_id_city_id_name_unique');

        $this->assertNotNull($perTenant);
    }

    /** @test */
    public function city_creation_without_tenant_is_rejected(): void
    {
        app()->forgetInstance('current.tenant');

        $this->expectException(\InvalidArgumentException::class);

        City::create(['name' => 'Tenantless City', 'is_active' => true]);
    }

    /** @test */
    public function township_creation_with_mismatched_tenant_is_rejected(): void
    {
        $city = City::create(['name' => 'Yangon', 'is_active' => true]);
        $otherTenant = Tenant::create([
            'name' => 'Other Store', 'slug' => 'other-store',
            'store_url' => '/store/other-store', 'status' => 'active',
        ]);

        $this->expectException(\InvalidArgumentException::class);

        Township::create([
            'tenant_id' => $otherTenant->id, 'city_id' => $city->id, 'name' => 'Bahan',
            'delivery_fee' => 3000, 'is_active' => true,
        ]);
    }

    /** @test */
    public function explicit_tenant_creation_works_without_current_tenant(): void
    {
        app()->forgetInstance('current.tenant');

        $city = City::create(['tenant_id' => $this->tenant->id, 'name' => 'Yangon', 'is_active' => true]);
        $township = Township::create([
            'tenant_id' => $this->tenant->id, 'city_id' => $city->id,
            'name' => 'Bahan', 'delivery_fee' => 3000, 'is_active' => true,
        ]);

        $this->assertEquals($this->tenant->id, $city->tenant_id);
        $this->assertEquals($this->tenant->id, $township->tenant_id);
        $this->assertDatabaseMissing('cities', ['tenant_id' => null]);
        $this->assertDatabaseMissing('townships', ['tenant_id' => null]);
    }

    /** @test */
    public function superadmin_create_without_tenant_returns_422(): void
    {
        app()->forgetInstance('current.tenant');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'cities.create', 'guard_name' => 'web']);
        $role = Role::withoutTenantScope()->firstOrCreate(
            ['name' => 'superadmin', 'guard_name' => 'web'],
            ['tenant_id' => null]
        );

        $superadmin = User::create([
            'name' => 'Super', 'email' => 'super@test.com',
            'password' => bcrypt('password'), 'status' => 'active',
        ]);
        $superadmin->assignRole($role);
        $superadmin->givePermissionTo('cities.create');

        $response = $this->actingAs($superadmin)->post('/admin/cities', ['name' => 'Ghost City']);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('cities', ['name' => 'Ghost City']);
        $this->assertDatabaseMissing('cities', ['tenant_id' => null]);
    }

    /** @test */
    public function township_fee_plus_service_surcharge_is_not_double_counted(): void
    {
        $city = City::create(['name' => 'Yangon', 'is_active' => true]);
        $township = Township::create([
            'city_id' => $city->id, 'name' => 'Bahan', 'delivery_fee' => 1000, 'is_active' => true,
        ]);
        $service = DeliveryService::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Express', 'code' => 'express',
            'base_fee' => 2500, 'min_days' => 1, 'max_days' => 1, 'is_active' => true,
        ]);
        DeliveryPricing::create([
            'delivery_service_id' => $service->id, 'township_id' => $township->id,
            'min_days' => 1, 'max_days' => 1, 'is_active' => true,
        ]);

        $fees = app(DeliveryFeeService::class);

        $this->assertEquals(1000, $fees->getTownshipFee($township));

        $breakdown = $fees->resolveDeliveryBreakdown($township, $service->id);
        $this->assertEquals(1000, $breakdown['township_fee']);
        $this->assertEquals(2500, $breakdown['service_fee']);
        $this->assertEquals(3500, $fees->resolveDeliveryFee($township, $service->id));
    }

    /** @test */
    public function legacy_checkout_uses_same_township_final_fee(): void
    {
        $city = City::create(['name' => 'Yangon', 'is_active' => true]);
        $township = Township::create([
            'city_id' => $city->id, 'name' => 'Bahan', 'delivery_fee' => 3000, 'is_active' => true,
        ]);
        $category = \App\Models\Category::create(['name' => 'Legacy', 'slug' => 'legacy']);
        $product = Product::create([
            'name' => 'Legacy Widget', 'type' => 'single', 'price' => 5000,
            'stock' => 50, 'status' => 'active', 'category_id' => $category->id,
        ]);
        StockMovement::create([
            'product_id' => $product->id, 'product_variant_id' => null,
            'type' => StockMovement::TYPE_OPENING_STOCK, 'quantity' => 50,
        ]);
        $paymentMethod = PaymentMethod::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Bank Transfer',
            'type' => 'bank_transfer', 'account_name' => 'A', 'account_number' => '1',
            'is_active' => true,
        ]);
        $user = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Legacy Buyer',
            'email' => 'legacy@test.com', 'password' => bcrypt('password'),
            'status' => 'active', 'allow_cod' => true,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['cart' => ['p1' => [
                'product_id' => $product->id, 'quantity' => 1,
            ]]])
            ->post('/checkout', [
                'first_name' => 'Legacy', 'last_name' => 'Buyer', 'phone' => '09911122233',
                'address' => 'No. 2, Legacy Road',
                'city_id' => $city->id, 'township_id' => $township->id,
                'payment_method_id' => $paymentMethod->id,
            ]);

        $order = Order::where('tenant_id', $this->tenant->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertEquals(3000, (float) $order->delivery_fee);
        $this->assertEquals(8000, (float) $order->total_amount);
    }

    /** @test */
    public function old_order_fee_survives_township_fee_change(): void
    {
        $city = City::create(['name' => 'Yangon', 'is_active' => true]);
        $township = Township::create([
            'city_id' => $city->id, 'name' => 'Bahan', 'delivery_fee' => 3000, 'is_active' => true,
        ]);
        $buyer = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Old Buyer',
            'email' => 'old-buyer@test.com', 'password' => bcrypt('password'), 'status' => 'active',
        ]);
        $order = Order::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $buyer->id,
            'first_name' => 'Old', 'last_name' => 'Buyer', 'phone' => '09900000000',
            'address' => 'Old Road', 'city_id' => $city->id, 'township_id' => $township->id,
            'subtotal' => 5000, 'delivery_fee' => 3000, 'total_amount' => 8000,
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
            'order_status' => Order::ORDER_STATUS_PENDING,
        ]);

        $township->update(['delivery_fee' => 7500]);

        $this->assertEquals(3000, (float) $order->fresh()->delivery_fee);
        $this->assertEquals(8000, (float) $order->fresh()->total_amount);
        $this->assertEquals(7500, (float) $township->fresh()->delivery_fee);
    }
}
