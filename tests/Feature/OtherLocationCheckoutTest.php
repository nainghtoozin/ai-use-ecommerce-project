<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\City;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\Township;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtherLocationCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Tenant $otherTenant;
    private City $city;
    private Township $township;
    private Township $foreignTownship;
    private User $user;
    private Product $product;
    private PaymentMethod $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Other Store', 'slug' => 'other-store',
            'store_url' => '/store/other-store', 'status' => 'active',
        ]);
        $this->otherTenant = Tenant::create([
            'name' => 'Foreign Store', 'slug' => 'foreign-store',
            'store_url' => '/store/foreign-store', 'status' => 'active',
        ]);
        Tenant::setCurrent($this->tenant);

        $this->city = City::create(['name' => 'Bago', 'is_active' => true]);
        $this->township = Township::create([
            'city_id' => $this->city->id, 'name' => 'Bago Township',
            'postal_code' => '08011', 'delivery_fee' => 3000, 'is_active' => true,
        ]);
        $foreignCity = City::withoutTenantScope()->create([
            'tenant_id' => $this->otherTenant->id, 'name' => 'Bago', 'is_active' => true,
        ]);
        $this->foreignTownship = Township::withoutTenantScope()->create([
            'tenant_id' => $this->otherTenant->id, 'city_id' => $foreignCity->id,
            'name' => 'Foreign Township', 'delivery_fee' => 4000, 'is_active' => true,
        ]);

        $category = Category::create(['name' => 'Other Cat', 'slug' => 'other-cat']);
        $this->product = Product::create([
            'name' => 'Other Widget', 'type' => 'single', 'price' => 5000,
            'stock' => 50, 'status' => 'active', 'category_id' => $category->id,
        ]);
        StockMovement::create([
            'product_id' => $this->product->id, 'product_variant_id' => null,
            'type' => StockMovement::TYPE_OPENING_STOCK, 'quantity' => 50,
        ]);
        $this->paymentMethod = PaymentMethod::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Bank Transfer',
            'type' => 'bank_transfer', 'account_name' => 'A', 'account_number' => '1',
            'is_active' => true,
        ]);
        $this->user = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Other Buyer',
            'email' => 'other-buyer@test.com', 'password' => bcrypt('password'),
            'status' => 'active', 'allow_cod' => true,
        ]);
    }

    private function startCheckout(): void
    {
        $this->actingAs($this->user, 'accounts');
        app()->instance('current.tenant', $this->tenant);
        $this->post('/store/other-store/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Other', 'last_name' => 'Buyer', 'phone' => '09911122233',
            'address' => 'No. 99, Unknown Road, Some Ward',
            'payment_method_id' => $this->paymentMethod->id,
        ], $overrides);
    }

    /** @test */
    public function normal_city_and_township_unchanged(): void
    {
        $this->startCheckout();

        $this->post('/store/other-store/checkout', $this->payload([
            'city_id' => $this->city->id,
            'township_id' => $this->township->id,
            'postal_code' => '08011',
        ]));

        $order = Order::where('tenant_id', $this->tenant->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertEquals($this->city->id, (int) $order->city_id);
        $this->assertEquals($this->township->id, (int) $order->township_id);
        $this->assertEquals(3000, (float) $order->delivery_fee);
        $this->assertEquals('08011', $order->postal_code);
        $this->assertEquals(8000, (float) $order->total_amount);
    }

    /** @test */
    public function normal_city_with_other_township(): void
    {
        $this->startCheckout();

        $this->post('/store/other-store/checkout', $this->payload([
            'city_id' => $this->city->id,
            'township_id' => 'other',
        ]));

        $order = Order::where('tenant_id', $this->tenant->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertEquals($this->city->id, (int) $order->city_id);
        $this->assertNull($order->township_id);
        $this->assertEquals(5000, (float) $order->delivery_fee);
        $this->assertEquals(1, (int) $order->delivery_days_min);
        $this->assertEquals(7, (int) $order->delivery_days_max);
        $this->assertNull($order->postal_code);
        $this->assertEquals(10000, (float) $order->total_amount);
    }

    /** @test */
    public function other_city_with_other_township(): void
    {
        $this->startCheckout();

        $this->post('/store/other-store/checkout', $this->payload([
            'city_id' => 'other',
            'township_id' => 'other',
        ]));

        $order = Order::where('tenant_id', $this->tenant->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertNull($order->city_id);
        $this->assertNull($order->township_id);
        $this->assertEquals(5000, (float) $order->delivery_fee);
        $this->assertEquals(1, (int) $order->delivery_days_min);
        $this->assertEquals(7, (int) $order->delivery_days_max);
        $this->assertEquals('No. 99, Unknown Road, Some Ward', $order->address);
    }

    /** @test */
    public function other_city_with_normal_township_is_rejected(): void
    {
        $response = $this->postJson('/store/other-store/checkout/quote', [
            'city_id' => 'other',
            'township_id' => $this->township->id,
        ]);
        $response->assertStatus(422);

        $this->startCheckout();

        $this->post('/store/other-store/checkout', $this->payload([
            'city_id' => 'other',
            'township_id' => $this->township->id,
        ]))->assertSessionHasErrors('township_id');

        $this->assertEquals(0, Order::where('tenant_id', $this->tenant->id)->count());
    }

    /** @test */
    public function foreign_tenant_ids_are_rejected(): void
    {
        $this->postJson('/store/other-store/checkout/quote', [
            'city_id' => $this->city->id,
            'township_id' => $this->foreignTownship->id,
        ])->assertStatus(422);

        $this->startCheckout();

        $this->post('/store/other-store/checkout', $this->payload([
            'city_id' => $this->city->id,
            'township_id' => $this->foreignTownship->id,
        ]))->assertSessionHasErrors('township_id');

        $this->assertEquals(0, Order::where('tenant_id', $this->tenant->id)->count());
    }

    /** @test */
    public function inactive_normal_locations_are_rejected(): void
    {
        $this->city->update(['is_active' => false]);

        $this->postJson('/store/other-store/checkout/quote', [
            'city_id' => $this->city->id,
            'township_id' => $this->township->id,
        ])->assertStatus(422);
    }

    /** @test */
    public function quote_flags_other_location_with_fallback_fee(): void
    {
        $response = $this->postJson('/store/other-store/checkout/quote', [
            'city_id' => $this->city->id,
            'township_id' => 'other',
        ]);

        $response->assertOk();
        $this->assertTrue((bool) $response->json('is_other_location'));
        $this->assertEquals(5000, $response->json('delivery.fee'));

        $normal = $this->postJson('/store/other-store/checkout/quote', [
            'city_id' => $this->city->id,
            'township_id' => $this->township->id,
        ]);

        $normal->assertOk();
        $this->assertFalse((bool) $normal->json('is_other_location'));
    }

    /** @test */
    public function any_service_is_rejected_for_other_orders(): void
    {
        $service = \App\Models\DeliveryService::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Standard', 'code' => 'standard',
            'base_fee' => 1500, 'min_days' => 2, 'max_days' => 5, 'is_active' => true,
        ]);
        $foreignService = \App\Models\DeliveryService::withoutTenantScope()->create([
            'tenant_id' => $this->otherTenant->id, 'name' => 'Foreign', 'code' => 'foreign',
            'base_fee' => 1500, 'min_days' => 2, 'max_days' => 5, 'is_active' => true,
        ]);

        $this->startCheckout();

        $this->post('/store/other-store/checkout', $this->payload([
            'city_id' => 'other',
            'township_id' => 'other',
            'delivery_service_id' => $foreignService->id,
        ]))->assertSessionHasErrors('delivery_service_id');

        $this->post('/store/other-store/checkout', $this->payload([
            'city_id' => 'other',
            'township_id' => 'other',
            'delivery_service_id' => $service->id,
        ]))->assertSessionHasErrors('delivery_service_id');

        $this->assertEquals(0, Order::where('tenant_id', $this->tenant->id)->count());

        $this->postJson('/store/other-store/checkout/quote', [
            'city_id' => 'other',
            'township_id' => 'other',
            'delivery_service_id' => $service->id,
        ])->assertStatus(422);
    }

    /** @test */
    public function other_order_snapshot_has_no_service_and_flat_totals(): void
    {
        $this->startCheckout();

        $this->post('/store/other-store/checkout', $this->payload([
            'city_id' => 'other',
            'township_id' => 'other',
        ]));

        $order = Order::where('tenant_id', $this->tenant->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertNull($order->delivery_service_id);
        $this->assertEquals(5000, (float) $order->delivery_fee);
        $this->assertEquals(1, (int) $order->delivery_days_min);
        $this->assertEquals(7, (int) $order->delivery_days_max);
        $this->assertEquals(10000, (float) $order->total_amount);
    }

    /** @test */
    public function custom_fallback_settings_are_used(): void
    {
        Tenant::setCurrent($this->tenant);
        \App\Models\Setting::set('other_location.delivery_fee', '6000');
        \App\Models\Setting::set('other_location.min_days', '2');
        \App\Models\Setting::set('other_location.max_days', '9');

        $response = $this->postJson('/store/other-store/checkout/quote', [
            'city_id' => 'other',
            'township_id' => 'other',
        ]);

        $response->assertOk();
        $this->assertEquals(6000, $response->json('delivery.fee'));
        $this->assertEquals(2, $response->json('delivery.eta_min'));
        $this->assertEquals(9, $response->json('delivery.eta_max'));
    }

    /** @test */
    public function admin_can_change_other_location_settings(): void
    {
        $this->actingAs($this->makeSettingsAdmin());

        $response = $this->post('/store/other-store/admin/townships/other-settings', [
            'delivery_fee' => 6500,
            'min_days' => 2,
            'max_days' => 6,
        ]);

        $response->assertRedirect();
        $this->assertEquals('6500', \App\Models\Setting::get('other_location.delivery_fee', null, $this->tenant->id));
        $this->assertEquals('2', \App\Models\Setting::get('other_location.min_days', null, $this->tenant->id));
        $this->assertEquals('6', \App\Models\Setting::get('other_location.max_days', null, $this->tenant->id));

        $settings = app(\App\Services\DeliveryFeeService::class)->getOtherLocationSettings($this->tenant);
        $this->assertEquals(6500, $settings['delivery_fee']);
        $this->assertEquals(2, $settings['min_days']);
        $this->assertEquals(6, $settings['max_days']);
    }

    /** @test */
    public function invalid_other_location_settings_are_rejected(): void
    {
        $this->actingAs($this->makeSettingsAdmin());

        $this->post('/store/other-store/admin/townships/other-settings', [
            'delivery_fee' => -100,
            'min_days' => 1,
            'max_days' => 7,
        ])->assertSessionHasErrors('delivery_fee');

        $this->post('/store/other-store/admin/townships/other-settings', [
            'delivery_fee' => 5000,
            'min_days' => 7,
            'max_days' => 4,
        ])->assertSessionHasErrors('max_days');

        $this->post('/store/other-store/admin/townships/other-settings', [
            'delivery_fee' => 5000,
            'min_days' => 1.5,
            'max_days' => 7,
        ])->assertSessionHasErrors('min_days');

        $settings = app(\App\Services\DeliveryFeeService::class)->getOtherLocationSettings($this->tenant);
        $this->assertEquals(5000, $settings['delivery_fee']);
    }

    /** @test */
    public function other_location_settings_are_tenant_isolated(): void
    {
        Tenant::setCurrent($this->tenant);
        \App\Models\Setting::set('other_location.delivery_fee', '6000');

        $settingsA = app(\App\Services\DeliveryFeeService::class)->getOtherLocationSettings($this->tenant);
        $settingsB = app(\App\Services\DeliveryFeeService::class)->getOtherLocationSettings($this->otherTenant);

        $this->assertEquals(6000, $settingsA['delivery_fee']);
        $this->assertEquals(5000, $settingsB['delivery_fee']);
        $this->assertEquals([1, 7], [$settingsB['min_days'], $settingsB['max_days']]);
    }

    /** @test */
    public function client_supplied_fee_cannot_override_other_calculation(): void
    {
        $this->startCheckout();

        $payload = $this->payload([
            'city_id' => 'other',
            'township_id' => 'other',
        ]);
        $payload['delivery_fee'] = 1;
        $payload['delivery_days_min'] = 99;
        $payload['delivery_days_max'] = 99;

        $this->post('/store/other-store/checkout', $payload);

        $order = Order::where('tenant_id', $this->tenant->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertEquals(5000, (float) $order->delivery_fee);
        $this->assertEquals(1, (int) $order->delivery_days_min);
        $this->assertEquals(7, (int) $order->delivery_days_max);
        $this->assertNull($order->delivery_service_id);
    }

    private function makeSettingsAdmin(): User
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'townships.update', 'guard_name' => 'web']);
        $role = \Spatie\Permission\Models\Role::firstOrCreate([
            'name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id,
        ]);

        $admin = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Settings Admin',
            'email' => 'settings-admin@test.com', 'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $admin->assignRole($role);
        $admin->givePermissionTo('townships.update');

        return $admin;
    }

    /** @test */
    public function existing_orders_remain_unchanged(): void
    {
        $this->startCheckout();
        $this->post('/store/other-store/checkout', $this->payload([
            'city_id' => $this->city->id,
            'township_id' => $this->township->id,
            'postal_code' => '08011',
        ]));
        $old = Order::where('tenant_id', $this->tenant->id)->latest()->first();
        $this->assertNotNull($old);

        $this->post('/store/other-store/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);
        $this->post('/store/other-store/checkout', $this->payload([
            'city_id' => 'other',
            'township_id' => 'other',
        ]));

        $fresh = $old->fresh();
        $this->assertEquals(3000, (float) $fresh->delivery_fee);
        $this->assertEquals($this->township->id, (int) $fresh->township_id);
        $this->assertEquals(2, Order::where('tenant_id', $this->tenant->id)->count());
    }
}
