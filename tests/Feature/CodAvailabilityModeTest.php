<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\City;
use App\Models\CodRule;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\Township;
use App\Models\User;
use App\Models\WebsiteInfo;
use App\Services\CodEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CodAvailabilityModeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private City $city;
    private Township $township;
    private User $user;
    private Account $account;
    private Product $product;
    private PaymentMethod $codMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'Store A', 'slug' => 'mode-a',
            'store_url' => '/store/mode-a', 'status' => 'active',
        ]);
        $this->tenantB = Tenant::create([
            'name' => 'Store B', 'slug' => 'mode-b',
            'store_url' => '/store/mode-b', 'status' => 'active',
        ]);
        Tenant::setCurrent($this->tenantA);

        $this->city = City::create(['name' => 'Yangon', 'is_active' => true]);
        $this->township = Township::create([
            'city_id' => $this->city->id, 'name' => 'Bahan',
            'postal_code' => '11201', 'delivery_fee' => 1000, 'is_active' => true,
        ]);

        $category = Category::create(['name' => 'Mode Cat', 'slug' => 'mode-cat']);
        $this->product = Product::create([
            'name' => 'Mode Widget', 'type' => 'single', 'price' => 5000,
            'stock' => 50, 'status' => 'active', 'category_id' => $category->id,
        ]);
        StockMovement::create([
            'product_id' => $this->product->id, 'product_variant_id' => null,
            'type' => StockMovement::TYPE_OPENING_STOCK, 'quantity' => 50,
        ]);

        $this->codMethod = PaymentMethod::create([
            'tenant_id' => $this->tenantA->id, 'name' => 'Cash on Delivery',
            'type' => 'cod', 'is_active' => true,
        ]);
        PaymentMethod::create([
            'tenant_id' => $this->tenantA->id, 'name' => 'Bank Transfer',
            'type' => 'bank_transfer', 'account_name' => 'A', 'account_number' => '1',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenantA->id, 'name' => 'Mode Buyer',
            'email' => 'mode-buyer@test.com', 'password' => bcrypt('password'),
            'status' => 'active', 'allow_cod' => true,
        ]);

        $this->account = Account::create([
            'name' => 'Mode Account', 'email' => 'mode-account@test.com',
            'password' => bcrypt('password'), 'status' => 'active',
        ]);
        $role = Role::create(['name' => 'customer', 'guard_name' => 'web', 'tenant_id' => $this->tenantA->id]);
        TenantMembership::create([
            'account_id' => $this->account->id, 'tenant_id' => $this->tenantA->id,
            'role_id' => $role->id, 'status' => 'active', 'joined_at' => now(),
        ]);

        $this->websiteInfo($this->tenantA, true);
        $this->websiteInfo($this->tenantB, true);
    }

    private function websiteInfo(Tenant $tenant, bool $codEnabled): void
    {
        $row = WebsiteInfo::withoutTenantScope()->firstOrNew(['tenant_id' => $tenant->id]);
        $row->tenant_id = $tenant->id;
        $row->site_name = $tenant->name;
        $row->cod_enabled = $codEnabled;
        $row->save();
    }

    private function setMode(Tenant $tenant, string $mode): void
    {
        Tenant::setCurrent($tenant);
        Setting::set('cod_availability_mode', $mode);
        Tenant::setCurrent($this->tenantA);
    }

    private function makeRule(array $overrides = []): CodRule
    {
        return CodRule::create(array_merge([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Mode Rule',
            'is_active' => true,
        ], $overrides));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Mode', 'last_name' => 'Buyer', 'phone' => '09911122233',
            'address' => 'No. 1, Mode Road',
            'city_id' => $this->city->id, 'township_id' => $this->township->id,
            'postal_code' => '11201',
            'payment_method_id' => $this->codMethod->id,
        ], $overrides);
    }

    /** @test */
    public function disabled_mode_blocks_everyone_and_everything(): void
    {
        Tenant::setCurrent($this->tenantA);
        WebsiteInfo::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->update(['cod_enabled' => false]);
        $this->makeRule();

        $service = app(CodEligibilityService::class);
        $this->assertFalse($service->isCodAvailable($this->codMethod, $this->user, $this->city->id, 6000));
        $this->assertFalse($service->isCodAvailable($this->codMethod, $this->account, $this->city->id, 6000));

        $this->actingAs($this->user, 'accounts');
        app()->instance('current.tenant', $this->tenantA);
        $this->post('/store/mode-a/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);
        $this->post('/store/mode-a/checkout', $this->payload())->assertRedirect();
        $this->assertEquals(0, Order::where('tenant_id', $this->tenantA->id)->count());
    }

    /** @test */
    public function all_mode_allows_cod_without_rules_and_adds_no_charge(): void
    {
        $this->setMode($this->tenantA, 'all');

        $service = app(CodEligibilityService::class);
        $this->assertTrue($service->isCodAvailable($this->codMethod, $this->user, $this->city->id, 6000));

        $this->actingAs($this->user, 'accounts');
        app()->instance('current.tenant', $this->tenantA);
        $this->post('/store/mode-a/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);
        $this->post('/store/mode-a/checkout', $this->payload());

        $order = Order::where('tenant_id', $this->tenantA->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertNull($order->cod_fee);
        $this->assertEquals(6000, (float) $order->total_amount);
    }

    /** @test */
    public function all_mode_ignores_restrictive_rules(): void
    {
        $this->setMode($this->tenantA, 'all');
        $this->makeRule(['max_order_amount' => 100, 'allowed_city_ids' => [999999]]);

        $service = app(CodEligibilityService::class);
        $this->assertTrue($service->isCodAvailable($this->codMethod, $this->user, $this->city->id, 6000));
        $this->assertNull($service->getIneligibilityReason($this->codMethod, $this->user, $this->city->id, 6000));

        $this->actingAs($this->user, 'accounts');
        app()->instance('current.tenant', $this->tenantA);
        $this->post('/store/mode-a/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);
        $this->post('/store/mode-a/checkout', $this->payload());

        $this->assertNotNull(Order::where('tenant_id', $this->tenantA->id)->latest()->first());
    }

    /** @test */
    public function rules_mode_uses_existing_rules(): void
    {
        $this->setMode($this->tenantA, 'rules');
        $this->makeRule();

        $this->actingAs($this->user, 'accounts');
        app()->instance('current.tenant', $this->tenantA);
        $this->post('/store/mode-a/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);
        $this->post('/store/mode-a/checkout', $this->payload());

        $order = Order::where('tenant_id', $this->tenantA->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertNull($order->cod_fee);
        $this->assertEquals(6000, (float) $order->total_amount);
    }

    /** @test */
    public function rules_mode_without_rules_rejects(): void
    {
        $this->setMode($this->tenantA, 'rules');

        $this->assertFalse(app(CodEligibilityService::class)->isCodAvailable(
            $this->codMethod, $this->user, $this->city->id, 6000
        ));

        $this->actingAs($this->user, 'accounts');
        app()->instance('current.tenant', $this->tenantA);
        $this->post('/store/mode-a/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);
        $this->post('/store/mode-a/checkout', $this->payload())->assertRedirect();
        $this->assertEquals(0, Order::where('tenant_id', $this->tenantA->id)->count());
    }

    /** @test */
    public function rules_mode_preserves_amount_and_city_semantics_without_charge(): void
    {
        $this->setMode($this->tenantA, 'rules');
        $otherCity = City::create(['name' => 'Mandalay', 'is_active' => true]);
        $this->makeRule(['max_order_amount' => 10000, 'allowed_city_ids' => [$this->city->id]]);

        $service = app(CodEligibilityService::class);
        $this->assertFalse($service->isCodAvailable($this->codMethod, $this->user, $otherCity->id, 6000));
        $this->assertFalse($service->isCodAvailable($this->codMethod, $this->user, $this->city->id, 20000));
        $this->assertTrue($service->isCodAvailable($this->codMethod, $this->user, $this->city->id, 6000));

        $this->actingAs($this->user, 'accounts');
        app()->instance('current.tenant', $this->tenantA);
        $this->post('/store/mode-a/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);
        $this->post('/store/mode-a/checkout', $this->payload());

        $order = Order::where('tenant_id', $this->tenantA->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertNull($order->cod_fee);
        $this->assertEquals(6000, (float) $order->total_amount);
    }

    /** @test */
    public function user_and_account_follow_same_mode_behavior(): void
    {
        $this->setMode($this->tenantA, 'all');

        $service = app(CodEligibilityService::class);
        $this->assertTrue($service->isCodAvailable($this->codMethod, $this->user, $this->city->id, 6000));
        $this->assertTrue($service->isCodAvailable($this->codMethod, $this->account, $this->city->id, 6000));

        $this->actingAs($this->account, 'accounts');
        app()->instance('current.tenant', $this->tenantA);
        $this->post('/store/mode-a/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);
        $this->post('/store/mode-a/checkout', $this->payload());

        $order = Order::where('tenant_id', $this->tenantA->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertNull($order->cod_fee);
    }

    /** @test */
    public function legacy_checkout_follows_mode(): void
    {
        $this->setMode($this->tenantA, 'all');

        $this->actingAs($this->user)
            ->withSession(['cart' => ['k1' => ['product_id' => $this->product->id, 'quantity' => 1]]])
            ->post('/checkout', $this->payload());

        $order = Order::where('tenant_id', $this->tenantA->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertNull($order->cod_fee);
    }

    /** @test */
    public function mode_is_tenant_isolated_with_rules_default(): void
    {
        $this->setMode($this->tenantA, 'all');

        $service = app(CodEligibilityService::class);
        $this->assertEquals('rules', $service->getCodAvailabilityMode($this->tenantB));
        Tenant::setCurrent($this->tenantB);
        $this->assertFalse($service->isCodAvailable($this->codMethod, $this->user, $this->city->id, 6000));

        Tenant::setCurrent($this->tenantA);
        $this->assertEquals('all', $service->getCodAvailabilityMode($this->tenantA));
        $this->assertTrue($service->isCodAvailable($this->codMethod, $this->user, $this->city->id, 6000));
    }

    /** @test */
    public function guest_quote_reflects_mode(): void
    {
        $this->setMode($this->tenantA, 'all');

        $response = $this->postJson('/store/mode-a/checkout/quote', [
            'city_id' => $this->city->id,
            'township_id' => $this->township->id,
        ]);

        $response->assertOk();
        $this->assertTrue($response->json('cod.available'));
    }
}
