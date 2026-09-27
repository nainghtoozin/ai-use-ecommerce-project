<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\Township;
use App\Models\User;
use App\Services\DeliveryFeeService;
use App\Services\LocationService;
use App\Services\MyanmarLocationImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantLocationIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;

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
    }

    private function makeCity(Tenant $tenant, string $name, bool $active = true): City
    {
        return City::withoutTenantScope()->create([
            'tenant_id' => $tenant->id, 'name' => $name, 'is_active' => $active,
        ]);
    }

    private function makeTownship(Tenant $tenant, City $city, string $name, float $fee = 0, bool $active = true): Township
    {
        return Township::withoutTenantScope()->create([
            'tenant_id' => $tenant->id, 'city_id' => $city->id,
            'name' => $name, 'delivery_fee' => $fee, 'is_active' => $active,
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
            'tenant_id' => $tenant->id, 'name' => 'Admin A',
            'email' => 'admin-a@test.com', 'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $user->assignRole($role);
        foreach ($permissions as $name) {
            $user->givePermissionTo($name);
        }

        return $user;
    }

    /** @test */
    public function test_1_tenant_can_see_its_cities(): void
    {
        $city = $this->makeCity($this->tenantA, 'Yangon');

        Tenant::setCurrent($this->tenantA);

        $this->assertTrue(City::all()->contains('id', $city->id));
        $this->assertTrue(City::getActiveWithTownships()->contains('id', $city->id));
    }

    /** @test */
    public function test_2_tenant_cannot_see_other_tenant_cities(): void
    {
        $cityB = $this->makeCity($this->tenantB, 'Yangon');

        Tenant::setCurrent($this->tenantA);

        $this->assertFalse(City::all()->contains('id', $cityB->id));
        $this->assertFalse(City::getActiveWithTownships()->contains('id', $cityB->id));
        $this->assertNull(City::find($cityB->id));
    }

    /** @test */
    public function test_3_tenant_can_create_a_city(): void
    {
        Tenant::setCurrent($this->tenantA);

        $city = app(LocationService::class)->createCity(['name' => 'Naypyidaw']);

        $this->assertEquals($this->tenantA->id, $city->tenant_id);
        $this->assertDatabaseHas('cities', ['id' => $city->id, 'tenant_id' => $this->tenantA->id]);
    }

    /** @test */
    public function test_4_tenant_cannot_update_other_tenant_city(): void
    {
        $cityB = $this->makeCity($this->tenantB, 'Yangon');
        $admin = $this->makeAdmin($this->tenantA, ['cities.update']);
        $this->actingAs($admin);

        $response = $this->put("/store/store-a/admin/cities/{$cityB->id}", [
            'name' => 'Hijacked',
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseMissing('cities', ['id' => $cityB->id, 'name' => 'Hijacked']);
    }

    /** @test */
    public function test_5_tenant_cannot_delete_other_tenant_city(): void
    {
        $cityB = $this->makeCity($this->tenantB, 'Yangon');
        $admin = $this->makeAdmin($this->tenantA, ['cities.delete']);
        $this->actingAs($admin);

        $response = $this->delete("/store/store-a/admin/cities/{$cityB->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('cities', ['id' => $cityB->id, 'tenant_id' => $this->tenantB->id]);
    }

    /** @test */
    public function test_6_tenant_can_create_township_under_its_city(): void
    {
        Tenant::setCurrent($this->tenantA);
        $city = $this->makeCity($this->tenantA, 'Yangon');

        $township = app(LocationService::class)->createTownship([
            'city_id' => $city->id, 'name' => 'Bahan', 'delivery_fee' => 3000,
        ]);

        $this->assertEquals($this->tenantA->id, $township->tenant_id);
        $this->assertEquals($city->id, $township->city_id);
    }

    /** @test */
    public function test_7_tenant_cannot_attach_township_to_other_tenant_city(): void
    {
        Tenant::setCurrent($this->tenantA);
        $cityB = $this->makeCity($this->tenantB, 'Yangon');

        $this->expectException(\InvalidArgumentException::class);

        app(LocationService::class)->createTownship([
            'city_id' => $cityB->id, 'name' => 'Bahan', 'delivery_fee' => 3000,
        ]);
    }

    /** @test */
    public function test_8_tenant_can_set_township_delivery_fee(): void
    {
        Tenant::setCurrent($this->tenantA);
        $city = $this->makeCity($this->tenantA, 'Yangon');
        $township = $this->makeTownship($this->tenantA, $city, 'Bahan', 3000);

        $updated = app(LocationService::class)->updateTownship($township, [
            'city_id' => $city->id, 'name' => 'Bahan', 'delivery_fee' => 4500,
        ]);

        $this->assertEquals(4500, (float) $updated->delivery_fee);
    }

    /** @test */
    public function test_9_different_townships_can_have_different_fees(): void
    {
        Tenant::setCurrent($this->tenantA);
        $city = $this->makeCity($this->tenantA, 'Yangon');
        $bahan = $this->makeTownship($this->tenantA, $city, 'Bahan', 3000);
        $kamayut = $this->makeTownship($this->tenantA, $city, 'Kamayut', 3500);

        $fees = app(DeliveryFeeService::class);

        $this->assertEquals(3000, $fees->resolveDeliveryFee($bahan));
        $this->assertEquals(3500, $fees->resolveDeliveryFee($kamayut));
    }

    /** @test */
    public function test_10_tenant_fee_change_does_not_affect_other_tenant(): void
    {
        $cityA = $this->makeCity($this->tenantA, 'Yangon');
        $cityB = $this->makeCity($this->tenantB, 'Yangon');
        $townshipA = $this->makeTownship($this->tenantA, $cityA, 'Bahan', 3000);
        $townshipB = $this->makeTownship($this->tenantB, $cityB, 'Bahan', 4000);

        Tenant::setCurrent($this->tenantA);
        app(LocationService::class)->updateTownship($townshipA, [
            'city_id' => $cityA->id, 'name' => 'Bahan', 'delivery_fee' => 9000,
        ]);

        $this->assertEquals(4000, (float) $townshipB->fresh()->delivery_fee);
    }

    /** @test */
    public function test_11_inactive_township_cannot_be_ordered(): void
    {
        Tenant::setCurrent($this->tenantA);
        $city = $this->makeCity($this->tenantA, 'Yangon');
        $township = $this->makeTownship($this->tenantA, $city, 'Bahan', 3000, false);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(LocationService::class)->resolveTenantLocation($city->id, $township->id, $this->tenantA);
    }

    /** @test */
    public function test_12_inactive_city_cannot_be_ordered(): void
    {
        Tenant::setCurrent($this->tenantA);
        $city = $this->makeCity($this->tenantA, 'Yangon', false);
        $township = $this->makeTownship($this->tenantA, $city, 'Bahan', 3000);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(LocationService::class)->resolveTenantLocation($city->id, $township->id, $this->tenantA);
    }

    /** @test */
    public function test_13_foreign_tenant_ids_fail_checkout_validation(): void
    {
        $cityB = $this->makeCity($this->tenantB, 'Yangon');
        $townshipB = $this->makeTownship($this->tenantB, $cityB, 'Bahan', 4000);

        $response = $this->postJson('/store/store-a/checkout/quote', [
            'city_id' => $cityB->id,
            'township_id' => $townshipB->id,
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function test_14_checkout_uses_township_delivery_fee(): void
    {
        $city = $this->makeCity($this->tenantA, 'Yangon');
        $township = $this->makeTownship($this->tenantA, $city, 'Bahan', 3000);

        Tenant::setCurrent($this->tenantA);

        $user = User::create([
            'tenant_id' => $this->tenantA->id, 'name' => 'Buyer',
            'email' => 'buyer@test.com', 'password' => bcrypt('password'),
            'status' => 'active', 'allow_cod' => true,
        ]);
        $category = \App\Models\Category::create([
            'name' => 'Widgets', 'slug' => 'widgets',
        ]);
        $product = Product::create([
            'name' => 'Widget',
            'type' => 'single', 'price' => 5000, 'stock' => 50, 'status' => 'active',
            'category_id' => $category->id,
        ]);
        StockMovement::create([
            'product_id' => $product->id,
            'product_variant_id' => null, 'type' => StockMovement::TYPE_OPENING_STOCK, 'quantity' => 50,
        ]);
        $paymentMethod = PaymentMethod::create([
            'tenant_id' => $this->tenantA->id, 'name' => 'Bank Transfer',
            'type' => 'bank_transfer', 'account_name' => 'A', 'account_number' => '1',
            'is_active' => true,
        ]);

        $this->actingAs($user, 'accounts');

        $this->post('/store/store-a/cart/add', ['product_id' => $product->id, 'quantity' => 1]);

        $response = $this->post('/store/store-a/checkout', [
            'first_name' => 'Test', 'last_name' => 'Buyer', 'phone' => '09911122233',
            'address' => 'No. 1, Test Road',
            'city_id' => $city->id, 'township_id' => $township->id,
            'payment_method_id' => $paymentMethod->id,
        ]);

        $order = Order::where('tenant_id', $this->tenantA->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertEquals(3000, (float) $order->delivery_fee);
        $this->assertEquals(8000, (float) $order->total_amount);
    }

    /** @test */
    public function test_15_old_order_fee_unchanged_after_fee_update(): void
    {
        Tenant::setCurrent($this->tenantA);
        $city = $this->makeCity($this->tenantA, 'Yangon');
        $township = $this->makeTownship($this->tenantA, $city, 'Bahan', 3000);
        $buyer = User::create([
            'tenant_id' => $this->tenantA->id, 'name' => 'Old Buyer',
            'email' => 'old-buyer@test.com', 'password' => bcrypt('password'), 'status' => 'active',
        ]);

        $order = Order::create([
            'tenant_id' => $this->tenantA->id, 'user_id' => $buyer->id,
            'first_name' => 'Old', 'last_name' => 'Buyer', 'phone' => '09900000000',
            'address' => 'Old Road', 'city_id' => $city->id, 'township_id' => $township->id,
            'subtotal' => 5000, 'delivery_fee' => 3000, 'total_amount' => 8000,
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
            'order_status' => Order::ORDER_STATUS_PENDING,
        ]);

        $township->update(['delivery_fee' => 5000]);

        $this->assertEquals(3000, (float) $order->fresh()->delivery_fee);
        $this->assertEquals(8000, (float) $order->fresh()->total_amount);
    }

    /** @test */
    public function test_16_location_import_does_not_affect_other_tenant(): void
    {
        Tenant::setCurrent($this->tenantA);
        $cityA = $this->makeCity($this->tenantA, 'Yangon');
        $townshipA = $this->makeTownship($this->tenantA, $cityA, 'Bahan', 3000);

        app(MyanmarLocationImportService::class)->import($this->tenantB);

        $this->assertEquals(3000, (float) $townshipA->fresh()->delivery_fee);
        $this->assertEquals(
            1,
            Township::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->where('name', 'Bahan')->count()
        );

        $importedBahan = Township::withoutTenantScope()
            ->where('tenant_id', $this->tenantB->id)
            ->where('name', 'Bahan')
            ->first();
        $this->assertNotNull($importedBahan);
        $this->assertNotEquals($townshipA->id, $importedBahan->id);

        $before = Township::withoutTenantScope()->where('tenant_id', $this->tenantB->id)->count();
        app(MyanmarLocationImportService::class)->import($this->tenantB);
        $this->assertEquals(
            $before,
            Township::withoutTenantScope()->where('tenant_id', $this->tenantB->id)->count()
        );
    }

    /** @test */
    public function test_17_cache_does_not_leak_across_tenants(): void
    {
        $cityA = $this->makeCity($this->tenantA, 'Yangon');
        $cityB = $this->makeCity($this->tenantB, 'Mandalay');

        Tenant::setCurrent($this->tenantA);
        $citiesA = City::getActiveWithTownships();

        Tenant::setCurrent($this->tenantB);
        $citiesB = City::getActiveWithTownships();

        $this->assertTrue($citiesA->contains('id', $cityA->id));
        $this->assertFalse($citiesA->contains('id', $cityB->id));
        $this->assertTrue($citiesB->contains('id', $cityB->id));
        $this->assertFalse($citiesB->contains('id', $cityA->id));
    }

    /** @test */
    public function test_18_deleting_tenant_city_does_not_affect_other_tenant(): void
    {
        $cityA = $this->makeCity($this->tenantA, 'Yangon');
        $townshipA = $this->makeTownship($this->tenantA, $cityA, 'Bahan', 3000);
        $cityB = $this->makeCity($this->tenantB, 'Yangon');
        $townshipB = $this->makeTownship($this->tenantB, $cityB, 'Bahan', 4000);

        $buyerB = User::create([
            'tenant_id' => $this->tenantB->id, 'name' => 'B Buyer',
            'email' => 'b-buyer@test.com', 'password' => bcrypt('password'), 'status' => 'active',
        ]);
        $orderB = Order::create([
            'tenant_id' => $this->tenantB->id, 'user_id' => $buyerB->id,
            'first_name' => 'B', 'last_name' => 'Buyer', 'phone' => '09900000001',
            'address' => 'B Road', 'city_id' => $cityB->id, 'township_id' => $townshipB->id,
            'subtotal' => 5000, 'delivery_fee' => 4000, 'total_amount' => 9000,
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
            'order_status' => Order::ORDER_STATUS_PENDING,
        ]);

        Tenant::setCurrent($this->tenantA);
        app(LocationService::class)->deleteCity($cityA->fresh());

        $this->assertDatabaseHas('cities', ['id' => $cityB->id]);
        $this->assertDatabaseHas('townships', ['id' => $townshipB->id]);
        $this->assertEquals($cityB->id, (int) $orderB->fresh()->city_id);
        $this->assertEquals($townshipB->id, (int) $orderB->fresh()->township_id);
    }
}
