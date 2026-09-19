<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\City;
use App\Models\CustomerAddress;
use App\Models\Product;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\Township;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class CustomerAddressDefaultTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private Account $account;
    private City $city;
    private Township $township;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create(['name' => 'AD Store A', 'slug' => 'ad-store-a', 'status' => 'active']);
        $this->tenantB = Tenant::create(['name' => 'AD Store B', 'slug' => 'ad-store-b', 'status' => 'active']);

        $role = Role::create(['name' => 'customer', 'guard_name' => 'web']);
        Tenant::setCurrent($this->tenantA);

        $category = Category::create(['name' => 'AD Cat', 'slug' => 'ad-cat']);
        $this->product = Product::create([
            'name' => 'AD Product', 'slug' => 'ad-product', 'price' => 5000,
            'category_id' => $category->id, 'status' => Product::STATUS_ACTIVE,
        ]);

        $this->city = City::create(['name' => 'Yangon', 'delivery_fee' => 3000, 'is_active' => true]);
        $this->township = Township::create([
            'city_id' => $this->city->id, 'name' => 'Kamaryut', 'postal_code' => '11041', 'is_active' => true,
        ]);

        $this->account = Account::create([
            'name' => 'AD Customer', 'email' => 'ad-customer@test.com',
            'password' => bcrypt('password'), 'status' => 'active',
        ]);

        $this->roleId = $role->id;

        TenantMembership::create([
            'account_id' => $this->account->id,
            'tenant_id' => $this->tenantA->id,
            'role_id' => $role->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        app()->forgetInstance('current.tenant');
    }

    private int $roleId;

    private function ensureMembership(Tenant $tenant): void
    {
        TenantMembership::firstOrCreate(
            ['account_id' => $this->account->id, 'tenant_id' => $tenant->id],
            ['role_id' => $this->roleId, 'status' => 'active', 'joined_at' => now()]
        );
    }

    private function makeAddress(Tenant $tenant, bool $default, string $line = 'No. 1, Test Road'): CustomerAddress
    {
        return $this->account->addresses()->create([
            'tenant_id' => $tenant->id,
            'label' => 'Home',
            'first_name' => 'AD',
            'last_name' => 'Customer',
            'phone' => '09911122233',
            'address_line' => $line,
            'city_id' => $this->city->id,
            'township_id' => $this->township->id,
            'postal_code' => '11041',
            'is_default' => $default,
        ]);
    }

    private function cartSession(): array
    {
        return ['cart' => ['p1_v0' => [
            'product_id' => $this->product->id, 'variant_id' => null, 'quantity' => 1, 'price' => 5000,
        ]]];
    }

    /** @test */
    public function checkout_uses_only_current_tenant_default(): void
    {
        $this->makeAddress($this->tenantA, true);
        $this->ensureMembership($this->tenantB);

        Tenant::setCurrent($this->tenantB);
        $categoryB = Category::create(['name' => 'AD Cat B', 'slug' => 'ad-cat-b']);
        $productB = Product::create([
            'name' => 'AD Product B', 'slug' => 'ad-product-b', 'price' => 5000,
            'category_id' => $categoryB->id, 'status' => Product::STATUS_ACTIVE,
        ]);
        app()->forgetInstance('current.tenant');

        $this->actingAs($this->account, 'accounts')
            ->withSession(['cart' => ['p9_v0' => [
                'product_id' => $productB->id, 'variant_id' => null, 'quantity' => 1, 'price' => 5000,
            ]]])
            ->get("/store/{$this->tenantB->slug}/checkout")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('defaultAddress', null));
    }

    /** @test */
    public function setting_default_in_one_tenant_keeps_other_tenant_default(): void
    {
        $addressA = $this->makeAddress($this->tenantA, true);
        $this->ensureMembership($this->tenantB);
        $addressB1 = $this->makeAddress($this->tenantB, false, 'No. 2, Other Road');
        $addressB2 = $this->makeAddress($this->tenantB, false, 'No. 3, Other Road');

        $this->actingAs($this->account, 'accounts')
            ->post("/store/{$this->tenantB->slug}/customer/addresses/{$addressB2->id}/default")
            ->assertRedirect();

        $this->assertTrue($addressA->fresh()->is_default);
        $this->assertFalse($addressB1->fresh()->is_default);
        $this->assertTrue($addressB2->fresh()->is_default);
    }

    /** @test */
    public function cross_tenant_address_mutation_is_rejected(): void
    {
        $addressA = $this->makeAddress($this->tenantA, true);
        $this->ensureMembership($this->tenantB);

        $this->actingAs($this->account, 'accounts')
            ->post("/store/{$this->tenantB->slug}/customer/addresses/{$addressA->id}/default")
            ->assertNotFound();

        $this->actingAs($this->account, 'accounts')
            ->delete("/store/{$this->tenantB->slug}/customer/addresses/{$addressA->id}")
            ->assertNotFound();

        $this->assertTrue($addressA->fresh()->is_default);
        $this->assertDatabaseHas('customer_addresses', ['id' => $addressA->id]);
    }

    /** @test */
    public function registration_creates_tenant_scoped_default(): void
    {
        $this->makeAddress($this->tenantA, true);

        Auth::guard('accounts')->logout();

        $this->post("/store/{$this->tenantB->slug}/register", [
            'name' => 'AD Customer',
            'email' => 'ad-customer@test.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'phone' => '09911122233',
            'address' => 'No. 9, New Road',
            'city_id' => $this->city->id,
            'township_id' => $this->township->id,
        ])->assertRedirect();

        app()->forgetInstance('current.tenant');

        $addressB = CustomerAddress::where('user_id', $this->account->id)
            ->where('tenant_id', $this->tenantB->id)->first();

        $this->assertNotNull($addressB);
        $this->assertTrue((bool) $addressB->is_default);
        $this->assertTrue(
            CustomerAddress::where('user_id', $this->account->id)
                ->where('tenant_id', $this->tenantA->id)->first()->is_default
        );
    }
}
