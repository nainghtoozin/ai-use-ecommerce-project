<?php

namespace Tests\Feature;

use App\Models\Category;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DeliveryTownshipRulesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private City $city;
    private Township $bahan;
    private Township $kamayut;
    private DeliveryService $express;
    private DeliveryService $standard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Rules Store', 'slug' => 'rules-store',
            'store_url' => '/store/rules-store', 'status' => 'active',
        ]);
        Tenant::setCurrent($this->tenant);

        $this->city = City::create(['name' => 'Yangon', 'is_active' => true]);
        $this->bahan = Township::create([
            'city_id' => $this->city->id, 'name' => 'Bahan',
            'delivery_fee' => 3000, 'is_active' => true,
        ]);
        $this->kamayut = Township::create([
            'city_id' => $this->city->id, 'name' => 'Kamayut',
            'delivery_fee' => 3500, 'is_active' => true,
        ]);

        $this->express = DeliveryService::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Express', 'code' => 'express',
            'base_fee' => 2500, 'min_days' => 1, 'max_days' => 2, 'is_active' => true,
        ]);
        $this->standard = DeliveryService::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Standard', 'code' => 'standard',
            'base_fee' => 1500, 'min_days' => 2, 'max_days' => 5, 'is_active' => true,
        ]);

        DeliveryPricing::create([
            'delivery_service_id' => $this->express->id,
            'township_id' => $this->bahan->id,
            'min_days' => 1,
            'max_days' => 2,
            'is_active' => true,
        ]);
    }

    private function makeAdmin(): User
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'delivery-services.update', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);

        $admin = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Rules Admin',
            'email' => 'rules-admin@test.com', 'password' => bcrypt('password'), 'status' => 'active',
        ]);
        $admin->assignRole($role);
        $admin->givePermissionTo('delivery-services.update');

        return $admin;
    }

    private function makeBuyer(): array
    {
        $category = Category::create(['name' => 'Rules', 'slug' => 'rules']);
        $product = Product::create([
            'name' => 'Rules Widget', 'type' => 'single', 'price' => 5000,
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
            'tenant_id' => $this->tenant->id, 'name' => 'Rules Buyer',
            'email' => 'rules-buyer@test.com', 'password' => bcrypt('password'),
            'status' => 'active', 'allow_cod' => true,
        ]);

        return [$user, $product, $paymentMethod];
    }

    private function orderPayload(City $city, Township $township, PaymentMethod $paymentMethod, ?int $serviceId): array
    {
        return [
            'first_name' => 'Rules', 'last_name' => 'Buyer', 'phone' => '09911122233',
            'address' => 'No. 1, Rules Road',
            'city_id' => $city->id, 'township_id' => $township->id,
            'payment_method_id' => $paymentMethod->id,
            'delivery_service_id' => $serviceId,
        ];
    }

    /** @test */
    public function admin_can_add_township_delivery_rule(): void
    {
        $this->actingAs($this->makeAdmin());

        $response = $this->post("/store/rules-store/admin/delivery-services/{$this->standard->id}/add-township-pricing", [
            'township_id' => $this->kamayut->id,
            'min_days' => 3,
            'max_days' => 5,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('delivery_pricing', [
            'delivery_service_id' => $this->standard->id,
            'township_id' => $this->kamayut->id,
            'min_days' => 3,
            'max_days' => 5,
        ]);
    }

    /** @test */
    public function admin_re_adding_rule_updates_it(): void
    {
        $this->actingAs($this->makeAdmin());

        $this->post("/store/rules-store/admin/delivery-services/{$this->express->id}/add-township-pricing", [
            'township_id' => $this->bahan->id,
            'min_days' => 2,
            'max_days' => 6,
        ])->assertRedirect();

        $this->assertEquals(
            1,
            DeliveryPricing::where('delivery_service_id', $this->express->id)
                ->where('township_id', $this->bahan->id)
                ->count()
        );
        $this->assertDatabaseHas('delivery_pricing', [
            'delivery_service_id' => $this->express->id,
            'township_id' => $this->bahan->id,
            'min_days' => 2,
            'max_days' => 6,
        ]);
    }

    /** @test */
    public function admin_can_remove_township_delivery_rule(): void
    {
        $this->actingAs($this->makeAdmin());
        $pricingId = DeliveryPricing::where('delivery_service_id', $this->express->id)
            ->where('township_id', $this->bahan->id)
            ->value('id');

        $this->delete("/store/rules-store/admin/delivery-services/pricing/{$pricingId}")->assertRedirect();

        $this->assertDatabaseMissing('delivery_pricing', ['id' => $pricingId]);
    }

    /** @test */
    public function admin_cannot_add_rule_for_foreign_tenant_township(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'Foreign Store', 'slug' => 'foreign-store',
            'store_url' => '/store/foreign-store', 'status' => 'active',
        ]);
        $foreignCity = City::withoutTenantScope()->create([
            'tenant_id' => $otherTenant->id, 'name' => 'Yangon', 'is_active' => true,
        ]);
        $foreignTownship = Township::withoutTenantScope()->create([
            'tenant_id' => $otherTenant->id, 'city_id' => $foreignCity->id,
            'name' => 'Bahan', 'delivery_fee' => 4000, 'is_active' => true,
        ]);

        $this->actingAs($this->makeAdmin());

        $response = $this->post("/store/rules-store/admin/delivery-services/{$this->express->id}/add-township-pricing", [
            'township_id' => $foreignTownship->id,
            'min_days' => 1,
            'max_days' => 2,
        ]);

        $response->assertSessionHasErrors('township_id');
        $this->assertDatabaseMissing('delivery_pricing', [
            'delivery_service_id' => $this->express->id,
            'township_id' => $foreignTownship->id,
        ]);
    }

    /** @test */
    public function quote_shows_only_services_available_for_township(): void
    {
        $response = $this->postJson('/store/rules-store/checkout/quote', [
            'city_id' => $this->city->id,
            'township_id' => $this->bahan->id,
        ]);

        $response->assertOk();
        $ids = collect($response->json('services'))->pluck('id');
        $this->assertTrue($ids->contains($this->express->id));
        $this->assertFalse($ids->contains($this->standard->id));
    }

    /** @test */
    public function quote_shows_township_fee_service_fee_and_days(): void
    {
        $response = $this->postJson('/store/rules-store/checkout/quote', [
            'city_id' => $this->city->id,
            'township_id' => $this->bahan->id,
            'delivery_service_id' => $this->express->id,
        ]);

        $response->assertOk();
        $delivery = $response->json('delivery');
        $this->assertEquals(3000, $delivery['township_fee']);
        $this->assertEquals(2500, $delivery['service_fee']);
        $this->assertEquals(5500, $delivery['fee']);
        $this->assertEquals(1, $delivery['eta_min']);
        $this->assertEquals(2, $delivery['eta_max']);

        $entry = collect($response->json('services'))->firstWhere('id', $this->express->id);
        $this->assertEquals(2500, $entry['fee']);
        $this->assertEquals(1, $entry['eta_min']);
        $this->assertEquals(2, $entry['eta_max']);
    }

    /** @test */
    public function inactive_mapping_hides_service_from_quote(): void
    {
        DeliveryPricing::where('delivery_service_id', $this->express->id)
            ->where('township_id', $this->bahan->id)
            ->update(['is_active' => false]);

        $response = $this->postJson('/store/rules-store/checkout/quote', [
            'city_id' => $this->city->id,
            'township_id' => $this->bahan->id,
        ]);

        $response->assertOk();
        $this->assertFalse(collect($response->json('services'))->pluck('id')->contains($this->express->id));
    }

    /** @test */
    public function crafted_order_with_unmapped_service_is_rejected(): void
    {
        [$user, $product, $paymentMethod] = $this->makeBuyer();
        $this->actingAs($user, 'accounts');
        app()->instance('current.tenant', $this->tenant);
        $this->post('/store/rules-store/cart/add', ['product_id' => $product->id, 'quantity' => 1]);

        $response = $this->post(
            '/store/rules-store/checkout',
            $this->orderPayload($this->city, $this->bahan, $paymentMethod, $this->standard->id)
        );

        $response->assertSessionHasErrors('delivery_service_id');
        $this->assertEquals(0, Order::where('tenant_id', $this->tenant->id)->count());
    }

    /** @test */
    public function crafted_order_with_inactive_service_is_rejected(): void
    {
        $this->express->update(['is_active' => false]);
        [$user, $product, $paymentMethod] = $this->makeBuyer();
        $this->actingAs($user, 'accounts');
        app()->instance('current.tenant', $this->tenant);
        $this->post('/store/rules-store/cart/add', ['product_id' => $product->id, 'quantity' => 1]);

        $response = $this->post(
            '/store/rules-store/checkout',
            $this->orderPayload($this->city, $this->bahan, $paymentMethod, $this->express->id)
        );

        $response->assertSessionHasErrors('delivery_service_id');
        $this->assertEquals(0, Order::where('tenant_id', $this->tenant->id)->count());
    }

    /** @test */
    public function client_supplied_fee_and_days_cannot_override_server_calculation(): void
    {
        [$user, $product, $paymentMethod] = $this->makeBuyer();
        $this->actingAs($user, 'accounts');
        app()->instance('current.tenant', $this->tenant);
        $this->post('/store/rules-store/cart/add', ['product_id' => $product->id, 'quantity' => 1]);

        $payload = $this->orderPayload($this->city, $this->bahan, $paymentMethod, $this->express->id);
        $payload['delivery_fee'] = 1;
        $payload['delivery_days_min'] = 99;
        $payload['delivery_days_max'] = 99;

        $this->post('/store/rules-store/checkout', $payload);

        $order = Order::where('tenant_id', $this->tenant->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertEquals(5500, (float) $order->delivery_fee);
        $this->assertEquals(1, (int) $order->delivery_days_min);
        $this->assertEquals(2, (int) $order->delivery_days_max);
        $this->assertEquals(10500, (float) $order->total_amount);
    }
}
