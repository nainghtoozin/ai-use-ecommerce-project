<?php

namespace Tests\Feature;

use App\Http\Controllers\Client\ClientOrderController;
use App\Models\Account;
use App\Models\Category;
use App\Models\City;
use App\Models\CodRule;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\Role;
use App\Models\Township;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Legacy (/checkout via OrderController) COD alignment tests.
 *
 * The legacy path now enforces CodEligibilityService like the storefront:
 * the old bypass (allow_cod-only gate, no fee) is gone.
 */
class LegacyCheckoutAuditTest extends TestCase
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
    private PaymentMethod $codMethod;
    private PaymentMethod $bankMethod;
    private PaymentMethod $foreignMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Legacy Shop', 'slug' => 'legacy-shop',
            'store_url' => '/store/legacy-shop', 'status' => 'active',
        ]);
        $this->otherTenant = Tenant::create([
            'name' => 'Foreign Shop', 'slug' => 'foreign-shop',
            'store_url' => '/store/foreign-shop', 'status' => 'active',
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

        $category = Category::create(['name' => 'Legacy Cat', 'slug' => 'legacy-cat']);
        $this->product = Product::create([
            'name' => 'Legacy Widget', 'type' => 'single', 'price' => 5000,
            'stock' => 50, 'status' => 'active', 'category_id' => $category->id,
        ]);
        StockMovement::create([
            'product_id' => $this->product->id, 'product_variant_id' => null,
            'type' => StockMovement::TYPE_OPENING_STOCK, 'quantity' => 50,
        ]);

        $this->codMethod = PaymentMethod::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Cash on Delivery',
            'type' => 'cod', 'is_active' => true,
        ]);
        $this->bankMethod = PaymentMethod::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Bank Transfer',
            'type' => 'bank_transfer', 'account_name' => 'A', 'account_number' => '1',
            'is_active' => true,
        ]);
        $this->foreignMethod = PaymentMethod::withoutTenantScope()->create([
            'name' => 'Foreign COD', 'type' => 'cod', 'is_active' => true,
        ]);
        $this->foreignMethod->tenant_id = $this->otherTenant->id;
        $this->foreignMethod->save();

        $this->user = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Legacy Buyer',
            'email' => 'legacy-buyer@test.com', 'password' => bcrypt('password'),
            'status' => 'active', 'allow_cod' => true,
        ]);
    }

    private function makeRule(array $overrides = []): CodRule
    {
        return CodRule::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'Legacy COD Rule',
            'is_active' => true,
        ], $overrides));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Legacy', 'last_name' => 'Buyer', 'phone' => '09911122233',
            'address' => 'No. 1, Legacy Road',
            'city_id' => $this->city->id, 'township_id' => $this->township->id,
            'postal_code' => '11201',
            'payment_method_id' => $this->codMethod->id,
        ], $overrides);
    }

    private function cartSession()
    {
        return ['cart' => ['k1' => ['product_id' => $this->product->id, 'quantity' => 1]]];
    }

    /** @test */
    public function client_order_store_has_no_route(): void
    {
        $match = collect(Route::getRoutes())->first(
            fn ($route) => str_contains($route->getActionName(), 'ClientOrderController@store')
        );

        $this->assertNull($match, 'ClientOrderController@store must remain unrouted (dead path).');
    }

    /** @test */
    public function legacy_checkout_route_is_active(): void
    {
        $response = $this->actingAs($this->user)->post('/checkout', []);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['first_name', 'last_name', 'phone', 'address', 'payment_method_id']);
    }

    /** @test */
    public function eligible_cod_has_no_additional_charge(): void
    {
        $this->makeRule();
        $this->actingAs($this->user)
            ->withSession($this->cartSession())
            ->post('/checkout', $this->payload());

        $order = Order::where('tenant_id', $this->tenant->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertNull($order->cod_fee);
        $this->assertEquals(6000, (float) $order->total_amount);
    }

    /** @test */
    public function amount_ineligible_cod_is_rejected(): void
    {
        $this->makeRule(['max_order_amount' => 100]);
        $response = $this->actingAs($this->user)
            ->withSession($this->cartSession())
            ->post('/checkout', $this->payload());

        $response->assertRedirect();
        $this->assertEquals(0, Order::where('tenant_id', $this->tenant->id)->count());
    }

    /** @test */
    public function city_ineligible_cod_is_rejected(): void
    {
        $this->makeRule(['allowed_city_ids' => [$this->city->id]]);
        $response = $this->actingAs($this->user)
            ->withSession($this->cartSession())
            ->post('/checkout', $this->payload([
                'city_id' => $this->otherCity->id,
                'township_id' => $this->otherTownship->id,
            ]));

        $response->assertRedirect();
        $this->assertEquals(0, Order::where('tenant_id', $this->tenant->id)->count());
    }

    /** @test */
    public function forged_cod_fee_and_total_are_ignored(): void
    {
        $this->makeRule();
        $payload = $this->payload();
        $payload['cod_fee'] = 1;
        $payload['total_amount'] = 100;

        $this->actingAs($this->user)
            ->withSession($this->cartSession())
            ->post('/checkout', $payload);

        $order = Order::where('tenant_id', $this->tenant->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertNull($order->cod_fee);
        $this->assertEquals(6000, (float) $order->total_amount);
    }

    /** @test */
    public function cross_tenant_payment_method_is_rejected(): void
    {
        $this->makeRule();
        $response = $this->actingAs($this->user)
            ->withSession($this->cartSession())
            ->post('/checkout', $this->payload(['payment_method_id' => $this->foreignMethod->id]));

        $response->assertSessionHasErrors('payment_method_id');
        $this->assertEquals(0, Order::where('tenant_id', $this->tenant->id)->count());
    }

    /** @test */
    public function non_cod_legacy_order_unchanged(): void
    {
        $this->makeRule();
        $payload = $this->payload();
        $payload['payment_method_id'] = $this->bankMethod->id;

        $this->actingAs($this->user)
            ->withSession($this->cartSession())
            ->post('/checkout', $payload);

        $order = Order::where('tenant_id', $this->tenant->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertNull($order->cod_fee);
        $this->assertEquals(6000, (float) $order->total_amount);
    }

    /** @test */
    public function account_legacy_cod_order_works_without_type_error(): void
    {
        $this->makeRule();

        $account = Account::create([
            'name' => 'Legacy Acct', 'email' => 'legacy-acct@test.com',
            'password' => bcrypt('password'), 'status' => 'active',
        ]);
        $role = Role::create(['name' => 'customer', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        TenantMembership::create([
            'account_id' => $account->id, 'tenant_id' => $this->tenant->id,
            'role_id' => $role->id, 'status' => 'active', 'joined_at' => now(),
        ]);

        $this->actingAs($account)
            ->withSession($this->cartSession())
            ->post('/checkout', $this->payload());

        $order = Order::where('tenant_id', $this->tenant->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertNull($order->cod_fee);
        $this->assertEquals(6000, (float) $order->total_amount);
    }

    /** @test */
    public function cod_quote_matches_server_total_without_fee(): void
    {
        $this->makeRule();
        $response = $this->actingAs($this->user)
            ->withSession($this->cartSession())
            ->get("/checkout/cod-quote?city_id={$this->city->id}&township_id={$this->township->id}");

        $response->assertOk();
        $this->assertTrue($response->json('available'));
        $response->assertJsonMissingPath('cod_fee');
        $this->assertEquals(6000, (float) $response->json('total'));
        $this->assertNull($response->json('unavailable_reason'));
    }

    /** @test */
    public function cod_quote_reports_unavailable_with_reason(): void
    {
        $this->makeRule(['max_order_amount' => 100]);
        $response = $this->actingAs($this->user)
            ->withSession($this->cartSession())
            ->get("/checkout/cod-quote?city_id={$this->city->id}&township_id={$this->township->id}");

        $response->assertOk();
        $this->assertFalse($response->json('available'));
        $response->assertJsonMissingPath('cod_fee');
        $this->assertEquals(6000, (float) $response->json('total'));
        $this->assertNotEmpty($response->json('unavailable_reason'));
    }

    /** @test */
    public function cod_quote_without_rules_is_unavailable(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession($this->cartSession())
            ->get("/checkout/cod-quote?city_id={$this->city->id}&township_id={$this->township->id}");

        $response->assertOk();
        $this->assertFalse($response->json('available'));
        $response->assertJsonMissingPath('cod_fee');
        $this->assertEquals(6000, (float) $response->json('total'));
    }

    /** @test */
    public function historical_order_keeps_original_fee_and_total(): void
    {
        $this->makeRule();
        $this->actingAs($this->user)
            ->withSession($this->cartSession())
            ->post('/checkout', $this->payload());

        $order = Order::where('tenant_id', $this->tenant->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertNull($order->cod_fee);

        $order->update(['cod_fee' => 500, 'total_amount' => 6500]);

        $fresh = $order->fresh();
        $this->assertEquals(500, (float) $fresh->cod_fee);
        $this->assertEquals(6500, (float) $fresh->total_amount);
    }
}
