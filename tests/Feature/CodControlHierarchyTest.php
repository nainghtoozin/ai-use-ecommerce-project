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
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\Township;
use App\Models\User;
use App\Models\WebsiteInfo;
use App\Services\CodEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CodControlHierarchyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private City $city;
    private Township $township;
    private User $userAllowed;
    private User $userBlocked;
    private Account $account;
    private Product $product;
    private PaymentMethod $codMethod;
    private PaymentMethod $bankMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'Store A', 'slug' => 'hierarchy-a',
            'store_url' => '/store/hierarchy-a', 'status' => 'active',
        ]);
        $this->tenantB = Tenant::create([
            'name' => 'Store B', 'slug' => 'hierarchy-b',
            'store_url' => '/store/hierarchy-b', 'status' => 'active',
        ]);
        Tenant::setCurrent($this->tenantA);

        $this->city = City::create(['name' => 'Yangon', 'is_active' => true]);
        $this->township = Township::create([
            'city_id' => $this->city->id, 'name' => 'Bahan',
            'postal_code' => '11201', 'delivery_fee' => 1000, 'is_active' => true,
        ]);

        $category = Category::create(['name' => 'H Cat', 'slug' => 'h-cat']);
        $this->product = Product::create([
            'name' => 'H Widget', 'type' => 'single', 'price' => 5000,
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
        $this->bankMethod = PaymentMethod::create([
            'tenant_id' => $this->tenantA->id, 'name' => 'Bank Transfer',
            'type' => 'bank_transfer', 'account_name' => 'A', 'account_number' => '1',
            'is_active' => true,
        ]);

        $this->userAllowed = User::create([
            'tenant_id' => $this->tenantA->id, 'name' => 'Allowed',
            'email' => 'allowed-h@test.com', 'password' => bcrypt('password'),
            'status' => 'active', 'allow_cod' => true,
        ]);
        $this->userBlocked = User::create([
            'tenant_id' => $this->tenantA->id, 'name' => 'Blocked',
            'email' => 'blocked-h@test.com', 'password' => bcrypt('password'),
            'status' => 'active', 'allow_cod' => false,
        ]);

        $this->account = Account::create([
            'name' => 'H Account', 'email' => 'h-account@test.com',
            'password' => bcrypt('password'), 'status' => 'active',
        ]);
        $role = Role::create(['name' => 'customer', 'guard_name' => 'web', 'tenant_id' => $this->tenantA->id]);
        TenantMembership::create([
            'account_id' => $this->account->id, 'tenant_id' => $this->tenantA->id,
            'role_id' => $role->id, 'status' => 'active', 'joined_at' => now(),
        ]);
    }

    private function setCodEnabled(Tenant $tenant, bool $enabled): void
    {
        Tenant::setCurrent($tenant);
        WebsiteInfo::updateOrCreate(
            ['tenant_id' => $tenant->id],
            ['site_name' => $tenant->name, 'cod_enabled' => $enabled]
        );
        Tenant::setCurrent($this->tenantA);
    }

    private function makeRule(array $overrides = []): CodRule
    {
        return CodRule::create(array_merge([
            'tenant_id' => $this->tenantA->id,
            'name' => 'H COD Rule',
            'is_active' => true,
        ], $overrides));
    }

    private function startCheckout($principal = null, string $guard = 'accounts'): void
    {
        $this->actingAs($principal ?? $this->userAllowed, $guard);
        app()->instance('current.tenant', $this->tenantA);
        $this->post('/store/hierarchy-a/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'H', 'last_name' => 'Buyer', 'phone' => '09911122233',
            'address' => 'No. 1, H Road',
            'city_id' => $this->city->id, 'township_id' => $this->township->id,
            'postal_code' => '11201',
            'payment_method_id' => $this->codMethod->id,
        ], $overrides);
    }

    /** @test */
    public function globally_disabled_cod_is_unavailable_and_rejected(): void
    {
        $this->setCodEnabled($this->tenantA, false);
        $this->makeRule();

        $service = app(CodEligibilityService::class);
        $this->assertFalse($service->isCodAvailable($this->codMethod, $this->userAllowed, $this->city->id, 6000));
        $this->assertFalse($service->isCodAvailable($this->codMethod, $this->account, $this->city->id, 6000));

        $this->postJson('/store/hierarchy-a/checkout/quote', [
            'city_id' => $this->city->id, 'township_id' => $this->township->id,
        ])->assertOk()->assertJsonPath('cod.available', false);

        $this->startCheckout();
        $this->post('/store/hierarchy-a/checkout', $this->payload())->assertRedirect();
        $this->assertEquals(0, Order::where('tenant_id', $this->tenantA->id)->count());

        $this->startCheckout();
        $this->actingAs($this->account, 'accounts');
        app()->instance('current.tenant', $this->tenantA);
        $this->post('/store/hierarchy-a/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);
        $this->post('/store/hierarchy-a/checkout', $this->payload())->assertRedirect();
        $this->assertEquals(0, Order::where('tenant_id', $this->tenantA->id)->count());
    }

    /** @test */
    public function enabled_without_rules_is_unavailable_and_rejected(): void
    {
        $this->setCodEnabled($this->tenantA, true);

        $service = app(CodEligibilityService::class);
        $this->assertFalse($service->isCodAvailable($this->codMethod, $this->userAllowed, $this->city->id, 6000));

        $this->postJson('/store/hierarchy-a/checkout/quote', [
            'city_id' => $this->city->id, 'township_id' => $this->township->id,
        ])->assertOk()->assertJsonPath('cod.available', false);

        $this->startCheckout();
        $this->post('/store/hierarchy-a/checkout', $this->payload())->assertRedirect();
        $this->assertEquals(0, Order::where('tenant_id', $this->tenantA->id)->count());
    }

    /** @test */
    public function enabled_with_eligible_rule_allows_order(): void
    {
        $this->setCodEnabled($this->tenantA, true);
        $this->makeRule();
        $this->startCheckout();

        $this->post('/store/hierarchy-a/checkout', $this->payload());

        $order = Order::where('tenant_id', $this->tenantA->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertNull($order->cod_fee);
        $this->assertEquals(6000, (float) $order->total_amount);
    }

    /** @test */
    public function user_and_account_share_rule_behavior_without_allow_cod(): void
    {
        $this->setCodEnabled($this->tenantA, true);
        $this->makeRule();
        $service = app(CodEligibilityService::class);

        $this->assertTrue($service->isCodAvailable($this->codMethod, $this->userAllowed, $this->city->id, 6000));
        $this->assertTrue($service->isCodAvailable($this->codMethod, $this->userBlocked, $this->city->id, 6000));
        $this->assertTrue($service->isCodAvailable($this->codMethod, $this->account, $this->city->id, 6000));
        $this->assertTrue($service->isCodAvailable($this->codMethod, null, $this->city->id, 6000));
    }

    /** @test */
    public function tenant_setting_and_rules_are_isolated(): void
    {
        $this->setCodEnabled($this->tenantA, false);
        $this->setCodEnabled($this->tenantB, true);
        $this->makeRule();
        CodRule::withoutTenantScope()->create([
            'tenant_id' => $this->tenantB->id, 'name' => 'B Rule', 'is_active' => true,
        ]);

        $service = app(CodEligibilityService::class);
        Tenant::setCurrent($this->tenantA);
        $this->assertFalse($service->isCodAvailable($this->codMethod, $this->userAllowed, $this->city->id, 6000));

        Tenant::setCurrent($this->tenantB);
        $this->assertTrue($service->isCodAvailable($this->codMethod, $this->userAllowed, $this->city->id, 6000));
        Tenant::setCurrent($this->tenantA);
    }

    /** @test */
    public function legacy_checkout_follows_global_setting_and_rules(): void
    {
        $this->setCodEnabled($this->tenantA, false);
        $this->makeRule();

        $this->actingAs($this->userAllowed)
            ->withSession(['cart' => ['k1' => ['product_id' => $this->product->id, 'quantity' => 1]]])
            ->post('/checkout', $this->payload())
            ->assertRedirect();
        $this->assertEquals(0, Order::where('tenant_id', $this->tenantA->id)->count());

        $this->setCodEnabled($this->tenantA, true);

        $this->actingAs($this->userAllowed)
            ->withSession(['cart' => ['k1' => ['product_id' => $this->product->id, 'quantity' => 1]]])
            ->post('/checkout', $this->payload());

        $order = Order::where('tenant_id', $this->tenantA->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertNull($order->cod_fee);
        $this->assertEquals(6000, (float) $order->total_amount);
    }
}
