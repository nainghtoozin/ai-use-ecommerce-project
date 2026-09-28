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
use App\Services\CodEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CodAccountCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private City $city;
    private Township $township;
    private Account $account;
    private Product $product;
    private PaymentMethod $codMethod;
    private PaymentMethod $bankMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Account Store', 'slug' => 'account-store',
            'store_url' => '/store/account-store', 'status' => 'active',
        ]);
        Tenant::setCurrent($this->tenant);

        $this->city = City::create(['name' => 'Yangon', 'is_active' => true]);
        $this->township = Township::create([
            'city_id' => $this->city->id, 'name' => 'Bahan',
            'postal_code' => '11201', 'delivery_fee' => 1000, 'is_active' => true,
        ]);

        $category = Category::create(['name' => 'Acct Cat', 'slug' => 'acct-cat']);
        $this->product = Product::create([
            'name' => 'Acct Widget', 'type' => 'single', 'price' => 5000,
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

        $this->account = Account::create([
            'name' => 'Acct Buyer', 'email' => 'acct-buyer@test.com',
            'password' => bcrypt('password'), 'status' => 'active',
        ]);

        $role = Role::create([
            'name' => 'customer', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id,
        ]);
        TenantMembership::create([
            'account_id' => $this->account->id,
            'tenant_id' => $this->tenant->id,
            'role_id' => $role->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->account->addresses()->create([
            'tenant_id' => $this->tenant->id,
            'label' => 'Home',
            'first_name' => 'Acct',
            'last_name' => 'Buyer',
            'phone' => '09911122233',
            'address_line' => 'No. 1, Acct Road',
            'city_id' => $this->city->id,
            'township_id' => $this->township->id,
            'postal_code' => '11201',
            'is_default' => true,
        ]);
    }

    private function startCheckout(): void
    {
        $this->actingAs($this->account, 'accounts');
        app()->instance('current.tenant', $this->tenant);
        $this->post('/store/account-store/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);
    }

    /** @test */
    public function account_reaches_checkout_with_resolved_address(): void
    {
        $this->startCheckout();

        $response = $this->get('/store/account-store/checkout');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('defaultAddress.city_id', $this->city->id)
            ->where('defaultAddress.township_id', $this->township->id));
    }

    /** @test */
    public function account_sees_cod_when_eligible(): void
    {
        CodRule::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Open COD',
            'is_active' => true,
        ]);
        $this->startCheckout();

        $response = $this->get('/store/account-store/checkout');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('paymentMethods', fn ($methods) => collect($methods)->pluck('type')->contains('cod')));
    }

    /** @test */
    public function account_cod_eligibility_has_no_type_error(): void
    {
        CodRule::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Open COD',
            'is_active' => true,
        ]);

        $service = app(CodEligibilityService::class);

        $this->assertTrue($service->isCodAvailable($this->codMethod, $this->account, $this->city->id, 6000));
        $this->assertNull(
            $service->getIneligibilityReason($this->codMethod, $this->account, $this->city->id, 6000)
        );

        $flaggedUser = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Flagged',
            'email' => 'flagged@test.com', 'password' => bcrypt('password'),
            'status' => 'active', 'allow_cod' => false,
        ]);
        $this->assertTrue($service->isCodAvailable($this->codMethod, $flaggedUser, $this->city->id, 6000));
    }

    /** @test */
    public function account_places_cod_order_end_to_end(): void
    {
        CodRule::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Open COD',
            'is_active' => true,
        ]);
        $this->startCheckout();

        $this->post('/store/account-store/checkout', [
            'first_name' => 'Acct', 'last_name' => 'Buyer', 'phone' => '09911122233',
            'address' => 'No. 1, Acct Road',
            'city_id' => $this->city->id, 'township_id' => $this->township->id,
            'postal_code' => '11201',
            'payment_method_id' => $this->codMethod->id,
        ]);

        $order = Order::where('tenant_id', $this->tenant->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertEquals($this->codMethod->id, (int) $order->payment_method_id);
        $this->assertNull($order->cod_fee);
        $this->assertEquals(6000, (float) $order->total_amount);
    }

    /** @test */
    public function account_cod_rejected_for_excluded_city(): void
    {
        $otherCity = City::create(['name' => 'Mandalay', 'is_active' => true]);
        $otherTownship = Township::create([
            'city_id' => $otherCity->id, 'name' => 'Chanmyathazi',
            'delivery_fee' => 2000, 'is_active' => true,
        ]);
        CodRule::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Yangon Only',
            'allowed_city_ids' => [$this->city->id],
            'is_active' => true,
        ]);
        $this->startCheckout();

        $response = $this->post('/store/account-store/checkout', [
            'first_name' => 'Acct', 'last_name' => 'Buyer', 'phone' => '09911122233',
            'address' => 'No. 2, Other Road',
            'city_id' => $otherCity->id, 'township_id' => $otherTownship->id,
            'payment_method_id' => $this->codMethod->id,
        ]);

        $response->assertRedirect();
        $this->assertEquals(0, Order::where('tenant_id', $this->tenant->id)->count());
    }
}
