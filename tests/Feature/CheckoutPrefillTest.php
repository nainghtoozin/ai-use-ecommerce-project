<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\City;
use App\Models\CustomerProfile;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\Township;
use App\Services\BuyNowService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CheckoutPrefillTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;
    private Account $account;
    private City $city;
    private Township $township;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'PF Store', 'slug' => 'pf-store', 'status' => 'active']);
        Tenant::setCurrent($this->tenant);

        $category = Category::create(['name' => 'PF Cat', 'slug' => 'pf-cat']);
        $this->product = Product::create([
            'name' => 'PF Product', 'slug' => 'pf-product', 'price' => 5000,
            'category_id' => $category->id, 'status' => Product::STATUS_ACTIVE,
        ]);

        $this->city = City::create(['name' => 'Yangon', 'delivery_fee' => 3000, 'is_active' => true]);
        $this->township = Township::create([
            'city_id' => $this->city->id, 'name' => 'Kamaryut', 'postal_code' => '11041', 'is_active' => true,
        ]);

        $this->account = Account::create([
            'name' => 'PF Customer', 'email' => 'pf-customer@test.com',
            'password' => bcrypt('password'), 'status' => 'active',
        ]);

        $role = Role::create(['name' => 'customer', 'guard_name' => 'web']);

        $membership = TenantMembership::create([
            'account_id' => $this->account->id,
            'tenant_id' => $this->tenant->id,
            'role_id' => $role->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        CustomerProfile::create(['tenant_membership_id' => $membership->id, 'name' => 'PF Customer', 'phone' => '09911122233']);

        StockMovement::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'product_variant_id' => null,
            'type' => StockMovement::TYPE_OPENING_STOCK,
            'quantity' => 10,
        ]);

        app()->forgetInstance('current.tenant');
    }

    private function cartSession(): array
    {
        return ['cart' => ['p1_v0' => [
            'product_id' => $this->product->id, 'variant_id' => null, 'quantity' => 1, 'price' => 5000,
        ]]];
    }

    /** @test */
    public function full_address_prefills_checkout(): void
    {
        $this->account->addresses()->create([
            'tenant_id' => $this->tenant->id,
            'label' => 'Home',
            'first_name' => 'PF',
            'last_name' => 'Customer',
            'phone' => '09911122233',
            'address_line' => 'No. 1, Test Road',
            'city_id' => $this->city->id,
            'township_id' => $this->township->id,
            'postal_code' => '11041',
            'is_default' => true,
        ]);

        $this->actingAs($this->account, 'accounts')
            ->withSession($this->cartSession())
            ->get("/store/{$this->tenant->slug}/checkout")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Storefront/CheckoutV2')
                ->where('defaultAddress.address_line', 'No. 1, Test Road')
                ->where('defaultAddress.city_id', $this->city->id)
                ->where('defaultAddress.township_id', $this->township->id)
                ->where('profilePhone', null)
            );
    }

    /** @test */
    public function phone_only_profile_prefills_phone_without_address(): void
    {
        $this->actingAs($this->account, 'accounts')
            ->withSession($this->cartSession())
            ->get("/store/{$this->tenant->slug}/checkout")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('defaultAddress', null)
                ->where('profilePhone', '09911122233')
            );
    }

    /** @test */
    public function guest_checkout_has_no_prefill(): void
    {
        $this->withSession($this->cartSession())
            ->get("/store/{$this->tenant->slug}/checkout")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('defaultAddress', null)
                ->where('profilePhone', null)
            );
    }

    /** @test */
    public function buy_now_checkout_uses_same_prefill(): void
    {
        $this->account->addresses()->create([
            'tenant_id' => $this->tenant->id,
            'label' => 'Home',
            'first_name' => 'PF',
            'last_name' => 'Customer',
            'phone' => '09911122233',
            'address_line' => 'No. 1, Test Road',
            'city_id' => $this->city->id,
            'township_id' => $this->township->id,
            'postal_code' => '11041',
            'is_default' => true,
        ]);

        app(BuyNowService::class)->start($this->tenant, $this->product->id, null, 1);

        $this->actingAs($this->account, 'accounts')
            ->get("/store/{$this->tenant->slug}/checkout")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('isBuyNow', true)
                ->where('defaultAddress.address_line', 'No. 1, Test Road')
            );
    }
}
