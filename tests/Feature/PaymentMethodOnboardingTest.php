<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\City;
use App\Models\CodRule;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\Township;
use App\Models\User;
use App\Services\TenantBootstrapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PaymentMethodOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Tenant $otherTenant;
    private City $city;
    private Township $township;
    private User $user;
    private User $owner;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Onboard Store', 'slug' => 'onboard-store',
            'store_url' => '/store/onboard-store', 'status' => 'active',
        ]);
        $this->otherTenant = Tenant::create([
            'name' => 'Other Store', 'slug' => 'onboard-other',
            'store_url' => '/store/onboard-other', 'status' => 'active',
        ]);
        Tenant::setCurrent($this->tenant);

        $this->city = City::create(['name' => 'Yangon', 'is_active' => true]);
        $this->township = Township::create([
            'city_id' => $this->city->id, 'name' => 'Bahan',
            'postal_code' => '11201', 'delivery_fee' => 1000, 'is_active' => true,
        ]);

        $category = Category::create(['name' => 'Onboard Cat', 'slug' => 'onboard-cat']);
        $this->product = Product::create([
            'name' => 'Onboard Widget', 'type' => 'single', 'price' => 5000,
            'stock' => 50, 'status' => 'active', 'category_id' => $category->id,
        ]);
        StockMovement::create([
            'product_id' => $this->product->id, 'product_variant_id' => null,
            'type' => StockMovement::TYPE_OPENING_STOCK, 'quantity' => 50,
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Onboard Buyer',
            'email' => 'onboard-buyer@test.com', 'password' => bcrypt('password'),
            'status' => 'active', 'allow_cod' => true,
        ]);
        $this->owner = $this->makeAdmin($this->tenant);
    }

    private function makeAdmin(Tenant $tenant): User
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['payments.view', 'payments.create', 'payments.update', 'payments.delete'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $role = \App\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);

        $user = User::create([
            'tenant_id' => $tenant->id, 'name' => 'Onboard Admin',
            'email' => 'onboard-admin-' . $tenant->slug . '@test.com', 'password' => bcrypt('password'),
            'status' => 'active', 'email_verified_at' => now(),
        ]);
        $user->assignRole($role);
        $user->givePermissionTo(['payments.view', 'payments.create', 'payments.update', 'payments.delete']);

        return $user;
    }

    private function bootstrapTenant(string $slug): Tenant
    {
        $tenant = Tenant::create([
            'name' => 'Fresh ' . $slug, 'slug' => $slug,
            'store_url' => '/store/' . $slug, 'status' => 'pending',
        ]);

        app(TenantBootstrapService::class)->bootstrap($tenant, [
            'owner_name' => 'Fresh Owner',
            'owner_email' => 'fresh-' . $slug . '@test.com',
            'owner_password' => 'password',
            'status' => 'active',
            'email_verified' => true,
        ]);

        return $tenant;
    }

    private function bankMethod(Tenant $tenant, bool $active = true): PaymentMethod
    {
        return PaymentMethod::withoutTenantScope()->create([
            'tenant_id' => $tenant->id, 'name' => 'Bank Transfer ' . $tenant->slug,
            'type' => 'bank_transfer', 'account_name' => 'A', 'account_number' => '1',
            'is_active' => $active,
        ]);
    }

    /** @test */
    public function bootstrap_creates_inactive_manual_placeholder(): void
    {
        $tenant = $this->bootstrapTenant('fresh-one');

        $methods = PaymentMethod::withoutTenantScope()->where('tenant_id', $tenant->id)->get();
        $this->assertEquals(1, $methods->count());
        $this->assertEquals('Manual Payment', $methods->first()->name);
        $this->assertEquals('manual', $methods->first()->type);
        $this->assertFalse((bool) $methods->first()->is_active);
        $this->assertNull($methods->first()->account_name);
        $this->assertNull($methods->first()->account_number);
        $this->assertNull($methods->first()->instructions);
        $this->assertEquals(0, CodRule::withoutTenantScope()->where('tenant_id', $tenant->id)->count());
    }

    /** @test */
    public function bootstrap_creates_no_cash_cod_or_bank_transfer(): void
    {
        $tenant = $this->bootstrapTenant('fresh-two');

        $types = PaymentMethod::withoutTenantScope()
            ->where('tenant_id', $tenant->id)->pluck('type')->all();
        $this->assertEquals(['manual'], $types);
    }

    /** @test */
    public function bootstrap_skips_default_when_methods_already_exist(): void
    {
        $tenant = Tenant::create([
            'name' => 'Existing Fresh', 'slug' => 'fresh-existing',
            'store_url' => '/store/fresh-existing', 'status' => 'pending',
        ]);
        PaymentMethod::withoutTenantScope()->create([
            'tenant_id' => $tenant->id, 'name' => 'Bank Transfer',
            'type' => 'bank_transfer', 'account_name' => 'A', 'account_number' => '1',
            'is_active' => true,
        ]);

        app(TenantBootstrapService::class)->bootstrap($tenant, [
            'owner_name' => 'Existing Owner',
            'owner_email' => 'fresh-existing@test.com',
            'owner_password' => 'password',
            'status' => 'active',
            'email_verified' => true,
        ]);

        $methods = PaymentMethod::withoutTenantScope()->where('tenant_id', $tenant->id)->get();
        $this->assertEquals(1, $methods->count());
        $this->assertEquals('bank_transfer', $methods->first()->type);
    }

    /** @test */
    public function merchant_can_create_first_payment_method_during_onboarding(): void
    {
        $this->actingAs($this->owner);

        $response = $this->post('/store/onboard-store/admin/payment-methods', [
            'name' => 'KBZ Pay', 'type' => 'bank_transfer',
            'account_name' => 'Shop', 'account_number' => '09911122233',
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $method = PaymentMethod::withoutTenantScope()->where('tenant_id', $this->tenant->id)->first();
        $this->assertNotNull($method);
        $this->assertTrue((bool) $method->is_active);
    }

    /** @test */
    public function merchant_can_create_cash_manually_for_future_pos(): void
    {
        $this->actingAs($this->owner);

        $response = $this->post('/store/onboard-store/admin/payment-methods', [
            'name' => 'Cash', 'type' => 'cash', 'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertEquals(
            'cash',
            PaymentMethod::withoutTenantScope()->where('tenant_id', $this->tenant->id)->first()->type
        );
    }

    /** @test */
    public function dashboard_and_sidebar_share_setup_required_flag(): void
    {
        $this->actingAs($this->owner)->get('/store/onboard-store/admin/payment-methods')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('paymentSetupRequired', true));

        $this->bankMethod($this->tenant);

        $this->actingAs($this->owner)->get('/store/onboard-store/admin/payment-methods')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('paymentSetupRequired', false));
    }

    /** @test */
    public function configure_placeholder_then_activate(): void
    {
        $tenant = $this->bootstrapTenant('fresh-configure');
        $placeholder = PaymentMethod::withoutTenantScope()->where('tenant_id', $tenant->id)->first();
        Tenant::setCurrent($tenant);
        $admin = $this->makeAdmin($tenant);

        $this->actingAs($admin)->put("/store/fresh-configure/admin/payment-methods/{$placeholder->id}", [
            'name' => 'Manual Payment',
            'type' => 'manual',
            'account_name' => 'Shop Owner',
            'account_number' => '09911122233',
            'bank_name' => 'KBZPay',
            'instructions' => 'Pay to the account above and send the screenshot.',
            'is_active' => true,
        ])->assertRedirect();

        $fresh = $placeholder->fresh();
        $this->assertTrue((bool) $fresh->is_active);
        $this->assertEquals('Shop Owner', $fresh->account_name);
        $this->assertEquals('09911122233', $fresh->account_number);
        $this->assertEquals('Pay to the account above and send the screenshot.', $fresh->instructions);
        $this->assertEquals(1, PaymentMethod::withoutTenantScope()->where('tenant_id', $tenant->id)->count());
    }

    /** @test */
    public function onboarding_success_flags_missing_payment_setup(): void
    {
        $response = $this->actingAs($this->owner)->get('/onboarding/success/onboard-store');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('paymentSetup.has_active_payment_method', false)
            ->whereNotNull('paymentSetup.payment_method_create_url'));

        $this->bankMethod($this->tenant);

        $this->actingAs($this->owner)->get('/onboarding/success/onboard-store')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('paymentSetup.has_active_payment_method', true));
    }

    /** @test */
    public function store_becomes_checkout_ready_after_first_active_method(): void
    {
        $this->bankMethod($this->tenant);
        $this->actingAs($this->user, 'accounts');
        app()->instance('current.tenant', $this->tenant);
        $this->post('/store/onboard-store/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);

        $this->post('/store/onboard-store/checkout', [
            'first_name' => 'Onboard', 'last_name' => 'Buyer', 'phone' => '09911122233',
            'address' => 'No. 1, Onboard Road',
            'city_id' => $this->city->id, 'township_id' => $this->township->id,
            'postal_code' => '11201',
            'payment_method_id' => PaymentMethod::withoutTenantScope()
                ->where('tenant_id', $this->tenant->id)->first()->id,
        ]);

        $order = Order::where('tenant_id', $this->tenant->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertEquals(6000, (float) $order->total_amount);
    }

    /** @test */
    public function existing_merchant_methods_are_never_auto_modified(): void
    {
        $cash = PaymentMethod::withoutTenantScope()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Cash',
            'type' => 'cash', 'is_active' => true,
        ]);
        $cod = PaymentMethod::withoutTenantScope()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Cash On Delivery',
            'type' => 'cod', 'is_active' => true,
        ]);
        $bank = $this->bankMethod($this->tenant);

        $this->actingAs($this->user, 'accounts');
        app()->instance('current.tenant', $this->tenant);
        $this->post('/store/onboard-store/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);
        $this->post('/store/onboard-store/checkout', [
            'first_name' => 'Onboard', 'last_name' => 'Buyer', 'phone' => '09911122233',
            'address' => 'No. 1, Onboard Road',
            'city_id' => $this->city->id, 'township_id' => $this->township->id,
            'postal_code' => '11201',
            'payment_method_id' => $bank->id,
        ]);

        $this->assertNotNull(Order::where('tenant_id', $this->tenant->id)->latest()->first());
        foreach ([$cash, $cod, $bank] as $method) {
            $this->assertTrue((bool) $method->fresh()->is_active);
        }
        $this->assertEquals(3, PaymentMethod::withoutTenantScope()->where('tenant_id', $this->tenant->id)->count());
    }

    /** @test */
    public function inactive_methods_are_hidden_and_rejected(): void
    {
        $inactive = $this->bankMethod($this->tenant, false);

        $this->actingAs($this->user, 'accounts');
        app()->instance('current.tenant', $this->tenant);
        $this->post('/store/onboard-store/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);
        $this->get('/store/onboard-store/checkout')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('paymentMethods', fn ($methods) => !collect($methods)->pluck('id')->contains($inactive->id)));

        $this->actingAs($this->user)
            ->withSession(['cart' => ['k1' => ['product_id' => $this->product->id, 'quantity' => 1]]])
            ->get('/checkout')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('paymentMethods', fn ($methods) => !collect($methods)->pluck('id')->contains($inactive->id)));

        app()->instance('current.tenant', $this->tenant);
        $this->post('/store/onboard-store/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);
        $response = $this->post('/store/onboard-store/checkout', [
            'first_name' => 'Onboard', 'last_name' => 'Buyer', 'phone' => '09911122233',
            'address' => 'No. 1, Onboard Road',
            'city_id' => $this->city->id, 'township_id' => $this->township->id,
            'postal_code' => '11201',
            'payment_method_id' => $inactive->id,
        ]);

        $response->assertSessionHasErrors('payment_method_id');
        $this->assertEquals(0, Order::where('tenant_id', $this->tenant->id)->count());

        $this->actingAs($this->owner)
            ->post("/store/onboard-store/admin/payment-methods/{$inactive->id}/toggle")
            ->assertRedirect();

        $this->assertTrue((bool) $inactive->fresh()->is_active);
    }

    /** @test */
    public function order_requires_a_payment_method(): void
    {
        $this->actingAs($this->user, 'accounts');
        app()->instance('current.tenant', $this->tenant);
        $this->post('/store/onboard-store/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);

        $response = $this->post('/store/onboard-store/checkout', [
            'first_name' => 'Onboard', 'last_name' => 'Buyer', 'phone' => '09911122233',
            'address' => 'No. 1, Onboard Road',
            'city_id' => $this->city->id, 'township_id' => $this->township->id,
            'postal_code' => '11201',
            'payment_method_id' => 999999,
        ]);

        $response->assertSessionHasErrors('payment_method_id');
        $this->assertEquals(0, Order::where('tenant_id', $this->tenant->id)->count());
    }

    /** @test */
    public function manual_payment_places_normal_orders_without_fee(): void
    {
        $manual = PaymentMethod::withoutTenantScope()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Manual Payment',
            'type' => 'manual', 'account_name' => 'Shop Owner', 'account_number' => '09911122233',
            'instructions' => 'Pay to the account above.',
            'is_active' => true,
        ]);

        $this->actingAs($this->user, 'accounts');
        app()->instance('current.tenant', $this->tenant);
        $this->post('/store/onboard-store/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);
        $this->get('/store/onboard-store/checkout')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('paymentMethods', fn ($methods) => collect($methods)->pluck('type')->contains('manual'))
                ->where('paymentMethods.0.account_name', 'Shop Owner')
                ->where('paymentMethods.0.account_number', '09911122233')
                ->where('paymentMethods.0.instructions', 'Pay to the account above.'));

        $this->post('/store/onboard-store/checkout', [
            'first_name' => 'Onboard', 'last_name' => 'Buyer', 'phone' => '09911122233',
            'address' => 'No. 1, Onboard Road',
            'city_id' => $this->city->id, 'township_id' => $this->township->id,
            'postal_code' => '11201',
            'payment_method_id' => $manual->id,
        ]);

        $order = Order::where('tenant_id', $this->tenant->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertEquals($manual->id, (int) $order->payment_method_id);
        $this->assertNull($order->cod_fee);
        $this->assertEquals(6000, (float) $order->total_amount);
    }

    /** @test */
    public function manual_payment_works_in_legacy_checkout(): void
    {
        $manual = PaymentMethod::withoutTenantScope()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Manual Payment',
            'type' => 'manual', 'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->withSession(['cart' => ['k1' => ['product_id' => $this->product->id, 'quantity' => 1]]])
            ->post('/checkout', [
                'first_name' => 'Onboard', 'last_name' => 'Buyer', 'phone' => '09911122233',
                'address' => 'No. 1, Onboard Road',
                'city_id' => $this->city->id, 'township_id' => $this->township->id,
                'postal_code' => '11201',
                'payment_method_id' => $manual->id,
            ]);

        $order = Order::where('tenant_id', $this->tenant->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertNull($order->cod_fee);
        $this->assertEquals(6000, (float) $order->total_amount);
    }

    /** @test */
    public function manual_payment_can_be_edited_and_deactivated(): void
    {
        $manual = PaymentMethod::withoutTenantScope()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Manual Payment',
            'type' => 'manual', 'is_active' => true,
        ]);

        $this->actingAs($this->owner)->put("/store/onboard-store/admin/payment-methods/{$manual->id}", [
            'name' => 'Pay on Pickup', 'type' => 'manual',
            'instructions' => 'Pay when you collect your order.',
        ])->assertRedirect();

        $fresh = $manual->fresh();
        $this->assertEquals('Pay on Pickup', $fresh->name);
        $this->assertEquals('Pay when you collect your order.', $fresh->instructions);

        $this->actingAs($this->owner)
            ->post("/store/onboard-store/admin/payment-methods/{$manual->id}/toggle")
            ->assertRedirect();

        $this->assertFalse((bool) $manual->fresh()->is_active);
    }

    /** @test */
    public function all_inactive_methods_block_checkout_with_clear_state(): void
    {
        $this->bankMethod($this->tenant, false);

        $this->actingAs($this->user, 'accounts');
        app()->instance('current.tenant', $this->tenant);
        $this->post('/store/onboard-store/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);
        $this->get('/store/onboard-store/checkout')->assertOk()
            ->assertInertia(fn ($page) => $page->where('paymentMethods', []));

        $response = $this->post('/store/onboard-store/checkout', [
            'first_name' => 'Onboard', 'last_name' => 'Buyer', 'phone' => '09911122233',
            'address' => 'No. 1, Onboard Road',
            'city_id' => $this->city->id, 'township_id' => $this->township->id,
            'postal_code' => '11201',
            'payment_method_id' => 999999,
        ]);

        $response->assertSessionHasErrors('payment_method_id');
        $this->assertEquals(0, Order::where('tenant_id', $this->tenant->id)->count());
    }

    /** @test */
    public function cod_stays_gated_while_manual_works(): void
    {
        $manual = PaymentMethod::withoutTenantScope()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Manual Payment',
            'type' => 'manual', 'is_active' => true,
        ]);
        $cod = PaymentMethod::withoutTenantScope()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Cash on Delivery',
            'type' => 'cod', 'is_active' => true,
        ]);

        $base = [
            'first_name' => 'Onboard', 'last_name' => 'Buyer', 'phone' => '09911122233',
            'address' => 'No. 1, Onboard Road',
            'city_id' => $this->city->id, 'township_id' => $this->township->id,
            'postal_code' => '11201',
        ];

        $this->actingAs($this->user, 'accounts');
        app()->instance('current.tenant', $this->tenant);
        $this->post('/store/onboard-store/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);
        $this->post('/store/onboard-store/checkout', $base + ['payment_method_id' => $cod->id])
            ->assertRedirect();
        $this->assertEquals(0, Order::where('tenant_id', $this->tenant->id)->count());

        $this->post('/store/onboard-store/checkout', $base + ['payment_method_id' => $manual->id]);

        $order = Order::where('tenant_id', $this->tenant->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertEquals(6000, (float) $order->total_amount);
    }

    /** @test */
    public function manual_instructions_reach_both_checkouts(): void
    {
        $manual = PaymentMethod::withoutTenantScope()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Manual Payment',
            'type' => 'manual', 'instructions' => 'Pay when you collect your order.',
            'is_active' => true,
        ]);

        $this->actingAs($this->user, 'accounts');
        app()->instance('current.tenant', $this->tenant);
        $this->post('/store/onboard-store/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);
        $this->get('/store/onboard-store/checkout')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('paymentMethods.0.instructions', 'Pay when you collect your order.'));

        $this->actingAs($this->user)
            ->withSession(['cart' => ['k1' => ['product_id' => $this->product->id, 'quantity' => 1]]])
            ->get('/checkout')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('paymentMethods.0.instructions', 'Pay when you collect your order.'));
    }

    /** @test */
    public function payment_methods_are_tenant_isolated(): void
    {
        $foreign = $this->bankMethod($this->otherTenant);
        $local = $this->bankMethod($this->tenant);

        $this->actingAs($this->user, 'accounts');
        app()->instance('current.tenant', $this->tenant);
        $this->post('/store/onboard-store/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);
        $this->get('/store/onboard-store/checkout')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('paymentMethods', fn ($methods) => collect($methods)->pluck('id')->contains($local->id)
                    && !collect($methods)->pluck('id')->contains($foreign->id)));

        app()->instance('current.tenant', $this->tenant);
        $this->post('/store/onboard-store/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);
        $response = $this->post('/store/onboard-store/checkout', [
            'first_name' => 'Onboard', 'last_name' => 'Buyer', 'phone' => '09911122233',
            'address' => 'No. 1, Onboard Road',
            'city_id' => $this->city->id, 'township_id' => $this->township->id,
            'postal_code' => '11201',
            'payment_method_id' => $foreign->id,
        ]);

        $response->assertSessionHasErrors('payment_method_id');
        $this->assertEquals(0, Order::where('tenant_id', $this->tenant->id)->count());
    }
}
