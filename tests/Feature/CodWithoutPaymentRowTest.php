<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\City;
use App\Models\CodRule;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\Township;
use App\Models\User;
use App\Models\WebsiteInfo;
use App\Services\OrderWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CodWithoutPaymentRowTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Tenant $otherTenant;
    private City $city;
    private City $otherCity;
    private Township $township;
    private Township $otherTownship;
    private User $user;
    private Product $product;
    private PaymentMethod $bankMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'No Row Shop', 'slug' => 'no-row-shop',
            'store_url' => '/store/no-row-shop', 'status' => 'active',
        ]);
        $this->otherTenant = Tenant::create([
            'name' => 'Foreign Shop', 'slug' => 'no-row-foreign',
            'store_url' => '/store/no-row-foreign', 'status' => 'active',
        ]);
        Tenant::setCurrent($this->tenant);

        $this->city = City::create(['name' => 'Yangon', 'is_active' => true]);
        $this->otherCity = City::create(['name' => 'Mandalay', 'is_active' => true]);
        $this->township = Township::create([
            'city_id' => $this->city->id, 'name' => 'Bahan',
            'postal_code' => '11201', 'delivery_fee' => 1000, 'is_active' => true,
        ]);
        $this->otherTownship = Township::create([
            'city_id' => $this->otherCity->id, 'name' => 'Chanmyathazi',
            'delivery_fee' => 2000, 'is_active' => true,
        ]);

        $category = Category::create(['name' => 'NoRow Cat', 'slug' => 'norow-cat']);
        $this->product = Product::create([
            'name' => 'NoRow Widget', 'type' => 'single', 'price' => 5000,
            'stock' => 50, 'status' => 'active', 'category_id' => $category->id,
        ]);
        StockMovement::create([
            'product_id' => $this->product->id, 'product_variant_id' => null,
            'type' => StockMovement::TYPE_OPENING_STOCK, 'quantity' => 50,
        ]);

        $this->bankMethod = PaymentMethod::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Bank Transfer',
            'type' => 'bank_transfer', 'account_name' => 'A', 'account_number' => '1',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'NoRow Buyer',
            'email' => 'norow-buyer@test.com', 'password' => bcrypt('password'),
            'status' => 'active', 'allow_cod' => true,
        ]);
    }

    private function setCodEnabled(bool $enabled): void
    {
        Tenant::setCurrent($this->tenant);
        WebsiteInfo::updateOrCreate(
            ['tenant_id' => $this->tenant->id],
            ['site_name' => $this->tenant->name, 'cod_enabled' => $enabled]
        );
        Tenant::setCurrent($this->tenant);
    }

    private function setMode(string $mode): void
    {
        Tenant::setCurrent($this->tenant);
        Setting::set('cod_availability_mode', $mode);
        Tenant::setCurrent($this->tenant);
    }

    private function makeRule(array $overrides = []): CodRule
    {
        return CodRule::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'NoRow COD Rule',
            'is_active' => true,
        ], $overrides));
    }

    private function cartSession(): array
    {
        return ['cart' => ['k1' => ['product_id' => $this->product->id, 'quantity' => 1]]];
    }

    private function startV2Checkout(): void
    {
        $this->actingAs($this->user, 'accounts');
        app()->instance('current.tenant', $this->tenant);
        $this->post('/store/no-row-shop/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'NoRow', 'last_name' => 'Buyer', 'phone' => '09911122233',
            'address' => 'No. 1, NoRow Road',
            'city_id' => $this->city->id, 'township_id' => $this->township->id,
            'postal_code' => '11201',
            'payment_method_id' => 'cod',
        ], $overrides);
    }

    /** @test */
    public function all_mode_shows_cod_without_row_and_order_succeeds_v2(): void
    {
        $this->setCodEnabled(true);
        $this->setMode('all');
        $this->assertEquals(0, PaymentMethod::withoutTenantScope()->where('type', 'cod')->count());
        $this->startV2Checkout();

        $this->get('/store/no-row-shop/checkout')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('paymentMethods', fn ($methods) => collect($methods)->pluck('id')->contains('cod')));

        $this->post('/store/no-row-shop/checkout', $this->payload());

        $order = Order::where('tenant_id', $this->tenant->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertNull($order->payment_method_id);
        $this->assertNull($order->cod_fee);
        $this->assertEquals(6000, (float) $order->total_amount);
        $this->assertTrue(app(OrderWorkflow::class)->isCod($order));
    }

    /** @test */
    public function all_mode_shows_cod_without_row_and_order_succeeds_legacy(): void
    {
        $this->setCodEnabled(true);
        $this->setMode('all');

        $this->actingAs($this->user)
            ->withSession($this->cartSession())
            ->get('/checkout')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('paymentMethods', fn ($methods) => collect($methods)->pluck('id')->contains('cod')));

        $this->actingAs($this->user)
            ->withSession($this->cartSession())
            ->post('/checkout', $this->payload());

        $order = Order::where('tenant_id', $this->tenant->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertNull($order->payment_method_id);
        $this->assertEquals(6000, (float) $order->total_amount);
    }

    /** @test */
    public function rules_mode_eligible_cod_works_without_row(): void
    {
        $this->setCodEnabled(true);
        $this->setMode('rules');
        $this->makeRule();
        $this->startV2Checkout();

        $this->get('/store/no-row-shop/checkout')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('paymentMethods', fn ($methods) => collect($methods)->pluck('id')->contains('cod')));

        $this->post('/store/no-row-shop/checkout', $this->payload());

        $order = Order::where('tenant_id', $this->tenant->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertNull($order->payment_method_id);
        $this->assertEquals(6000, (float) $order->total_amount);
    }

    /** @test */
    public function rules_mode_ineligible_cod_rejected_without_row(): void
    {
        $this->setCodEnabled(true);
        $this->setMode('rules');
        $this->makeRule(['max_order_amount' => 100]);
        $this->startV2Checkout();

        $response = $this->post('/store/no-row-shop/checkout', $this->payload());

        $response->assertRedirect();
        $this->assertEquals(0, Order::where('tenant_id', $this->tenant->id)->count());
    }

    /** @test */
    public function disabled_cod_hides_option_without_row(): void
    {
        $this->setCodEnabled(false);
        $this->setMode('all');
        $this->makeRule();
        $this->startV2Checkout();

        $this->get('/store/no-row-shop/checkout')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('paymentMethods', fn ($methods) => !collect($methods)->pluck('type')->contains('cod')));

        $response = $this->post('/store/no-row-shop/checkout', $this->payload());

        $response->assertRedirect();
        $this->assertEquals(0, Order::where('tenant_id', $this->tenant->id)->count());
    }

    /** @test */
    public function legacy_cod_row_does_not_duplicate_option(): void
    {
        $this->setCodEnabled(true);
        $this->setMode('all');
        PaymentMethod::withoutTenantScope()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Cash On Delivery',
            'type' => 'cod', 'is_active' => true,
        ]);
        $this->startV2Checkout();

        $this->get('/store/no-row-shop/checkout')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('paymentMethods', fn ($methods) => collect($methods)->where('type', 'cod')->count() === 1));
    }

    /** @test */
    public function legacy_cod_row_id_still_places_compatible_order(): void
    {
        $this->setCodEnabled(true);
        $this->setMode('all');
        $legacy = PaymentMethod::withoutTenantScope()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Cash On Delivery',
            'type' => 'cod', 'is_active' => true,
        ]);
        $this->startV2Checkout();

        $this->post('/store/no-row-shop/checkout', $this->payload(['payment_method_id' => $legacy->id]));

        $order = Order::where('tenant_id', $this->tenant->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertEquals($legacy->id, (int) $order->payment_method_id);
        $this->assertEquals(6000, (float) $order->total_amount);
        $this->assertTrue(app(OrderWorkflow::class)->isCod($order));
    }

    /** @test */
    public function deactivating_merchant_methods_does_not_disable_system_cod(): void
    {
        $this->setCodEnabled(true);
        $this->setMode('all');
        $this->bankMethod->update(['is_active' => false]);
        $this->startV2Checkout();

        $this->get('/store/no-row-shop/checkout')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('paymentMethods', fn ($methods) => collect($methods)->pluck('id')->contains('cod')));

        $this->post('/store/no-row-shop/checkout', $this->payload());

        $this->assertNotNull(Order::where('tenant_id', $this->tenant->id)->latest()->first());
    }

    /** @test */
    public function inactive_legacy_cod_row_id_is_rejected(): void
    {
        $this->setCodEnabled(true);
        $this->setMode('all');
        $legacy = PaymentMethod::withoutTenantScope()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Cash On Delivery',
            'type' => 'cod', 'is_active' => false,
        ]);
        $this->startV2Checkout();

        $response = $this->post('/store/no-row-shop/checkout', $this->payload(['payment_method_id' => $legacy->id]));

        $response->assertSessionHasErrors('payment_method_id');
        $this->assertEquals(0, Order::where('tenant_id', $this->tenant->id)->count());
    }

    /** @test */
    public function no_cod_fee_added_and_delivery_unchanged(): void
    {
        $this->setCodEnabled(true);
        $this->setMode('all');
        $this->startV2Checkout();

        $this->post('/store/no-row-shop/checkout', $this->payload());

        $order = Order::where('tenant_id', $this->tenant->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertNull($order->cod_fee);
        $this->assertEquals(1000, (float) $order->delivery_fee);
        $this->assertEquals(5000, (float) $order->subtotal);
        $this->assertEquals(6000, (float) $order->total_amount);
    }

    /** @test */
    public function foreign_rules_do_not_enable_local_cod(): void
    {
        $this->setCodEnabled(true);
        $this->setMode('rules');
        CodRule::withoutTenantScope()->create([
            'tenant_id' => $this->otherTenant->id, 'name' => 'Foreign Rule', 'is_active' => true,
        ]);
        $this->startV2Checkout();

        $response = $this->post('/store/no-row-shop/checkout', $this->payload());

        $response->assertRedirect();
        $this->assertEquals(0, Order::where('tenant_id', $this->tenant->id)->count());
    }

    /** @test */
    public function guests_do_not_see_system_cod(): void
    {
        $this->setCodEnabled(true);
        $this->setMode('all');

        $this->withSession($this->cartSession())
            ->get('/store/no-row-shop/checkout')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('paymentMethods', fn ($methods) => !collect($methods)->pluck('type')->contains('cod')));
    }

    /** @test */
    public function cod_quote_works_without_row(): void
    {
        $this->setCodEnabled(true);
        $this->setMode('all');

        $response = $this->actingAs($this->user)
            ->withSession($this->cartSession())
            ->get("/checkout/cod-quote?city_id={$this->city->id}&township_id={$this->township->id}");

        $response->assertOk();
        $this->assertTrue($response->json('available'));
        $this->assertEquals(6000, (float) $response->json('total'));
    }

    /** @test */
    public function merchant_cannot_create_new_cod_type(): void
    {
        $admin = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'NoRow Admin',
            'email' => 'norow-admin@test.com', 'password' => bcrypt('password'),
            'status' => 'active', 'email_verified_at' => now(),
        ]);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        foreach (['payments.view', 'payments.create'] as $name) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $role = \App\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        $admin->assignRole($role);
        $admin->givePermissionTo(['payments.view', 'payments.create']);

        $response = $this->actingAs($admin)
            ->post('/store/no-row-shop/admin/payment-methods', [
                'name' => 'Sneaky COD', 'type' => 'cod', 'is_active' => true,
            ]);

        $response->assertSessionHasErrors('type');
        $this->assertEquals(0, PaymentMethod::withoutTenantScope()
            ->where('tenant_id', $this->tenant->id)->where('type', 'cod')->count());
    }
}
