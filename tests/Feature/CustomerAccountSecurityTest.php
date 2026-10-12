<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Order;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CustomerAccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('identity.use_accounts', true);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function makeTenant(string $slug): Tenant
    {
        return Tenant::create([
            'name' => "Shop {$slug}", 'slug' => $slug, 'status' => 'active', 'activated_at' => now(),
        ]);
    }

    private function makeCustomer(Tenant $tenant, string $email, string $password = 'Password1'): Account
    {
        $role = Role::withoutTenantScope()->firstOrCreate([
            'name' => 'customer', 'guard_name' => 'web', 'tenant_id' => $tenant->id,
        ]);

        $customer = Account::create([
            'name' => 'Customer', 'email' => $email, 'password' => bcrypt($password), 'status' => 'active',
        ]);
        TenantMembership::create([
            'account_id' => $customer->id, 'tenant_id' => $tenant->id,
            'role_id' => $role->id, 'is_owner' => false, 'status' => 'active', 'joined_at' => now(),
        ]);

        return $customer;
    }

    private function makeOrder(Tenant $tenant, Account $customer): Order
    {
        Tenant::setCurrent($tenant);

        try {
            return $customer->orders()->create([
                'invoice_number' => 'INV-' . uniqid(),
                'first_name' => 'Customer',
                'last_name' => 'Test',
                'phone' => '09123456789',
                'address' => 'No. 1, Test Road',
                'total_amount' => 25000,
                'order_status' => Order::ORDER_STATUS_PENDING,
                'payment_status' => Order::PAYMENT_STATUS_PENDING,
            ]);
        } finally {
            app()->forgetInstance('current.tenant');
        }
    }

    private function ziggyRoutes(): array
    {
        $source = (string) file_get_contents(base_path('resources/js/ziggy.js'));
        preg_match_all("/'([^']+)'\s*:\s*'([^']+)'/", $source, $matches, PREG_SET_ORDER);

        $routes = [];
        foreach ($matches as $match) {
            $routes[$match[1]] = $match[2];
        }

        return $routes;
    }

    public function test_ziggy_map_covers_all_customer_route_references(): void
    {
        $routes = $this->ziggyRoutes();

        $this->assertSame('/store/{store_slug}/customer/profile/password', $routes['storefront.customer.profile.password']);
        $this->assertSame('/store/{store_slug}/customer/profile', $routes['storefront.customer.profile.update']);
        $this->assertSame('/store/{store_slug}/customer/profile', $routes['storefront.customer.profile']);
        $this->assertSame('/store/{store_slug}/customer/account', $routes['storefront.customer.account.delete']);

        $files = [
            base_path('resources/js/Pages/Storefront/Account.jsx'),
            base_path('resources/js/Pages/Storefront/Profile.jsx'),
            base_path('resources/js/Pages/Storefront/Addresses.jsx'),
            base_path('resources/js/Pages/Storefront/Orders.jsx'),
            base_path('resources/js/Pages/Storefront/OrderShow.jsx'),
        ];

        $missing = [];
        foreach ($files as $file) {
            preg_match_all("/route\\('([^']+)'/", (string) file_get_contents($file), $used);
            foreach (array_unique($used[1]) as $name) {
                if (str_starts_with($name, 'storefront.customer.') && !isset($routes[$name])) {
                    $missing[] = "{$name} (used in " . basename($file) . ')';
                }
            }
        }

        $this->assertSame([], $missing);
    }

    public function test_password_change_succeeds_with_valid_inputs(): void
    {
        $tenant = $this->makeTenant('pw-shop');
        $customer = $this->makeCustomer($tenant, 'pw-customer@test.com');

        $response = $this->actingAs($customer, 'accounts')
            ->put("/store/{$tenant->slug}/customer/profile/password", [
                'current_password' => 'Password1',
                'password' => 'Abcdefg1',
                'password_confirmation' => 'Abcdefg1',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('storefront.login', ['store_slug' => $tenant->slug], absolute: false));
        $this->assertTrue(Hash::check('Abcdefg1', $customer->fresh()->password));
        $this->assertGuest('accounts');
    }

    public function test_cross_store_password_change_is_denied(): void
    {
        $tenantA = $this->makeTenant('pw-tenant-a');
        $tenantB = $this->makeTenant('pw-tenant-b');
        $customerA = $this->makeCustomer($tenantA, 'pw-cross@test.com');

        $this->actingAs($customerA, 'accounts')
            ->put("/store/{$tenantB->slug}/customer/profile/password", [
                'current_password' => 'Password1',
                'password' => 'Abcdefg1',
                'password_confirmation' => 'Abcdefg1',
            ])
            ->assertForbidden();

        $this->assertTrue(Hash::check('Password1', $customerA->fresh()->password));
    }

    public function test_customer_logout_redirects_to_store_login(): void
    {
        $tenant = $this->makeTenant('logout-shop');
        $customer = $this->makeCustomer($tenant, 'logout-customer@test.com');

        $this->actingAs($customer, 'accounts')
            ->post('/logout', ['context' => 'storefront', 'store_slug' => $tenant->slug])
            ->assertRedirect(route('storefront.login', ['store_slug' => $tenant->slug], absolute: false));

        $this->assertGuest('accounts');
    }

    public function test_merchant_logout_keeps_existing_admin_login_redirect(): void
    {
        $tenant = $this->makeTenant('merchant-logout-shop');

        $role = Role::withoutTenantScope()->firstOrCreate([
            'name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id,
        ]);
        $owner = Account::create([
            'name' => 'Owner', 'email' => 'merchant-logout@test.com',
            'password' => bcrypt('Password1'), 'status' => 'active',
        ]);
        $owner->assignRole($role);
        \App\Models\TenantMembership::create([
            'account_id' => $owner->id, 'tenant_id' => $tenant->id,
            'role_id' => $role->id, 'is_owner' => true,
            'status' => 'active', 'joined_at' => now(),
        ]);

        $this->actingAs($owner, 'accounts')
            ->post('/logout', ['context' => 'admin', 'store_slug' => $tenant->slug])
            ->assertRedirect(route('storefront.admin.login', ['store_slug' => $tenant->slug], absolute: false));

        $this->assertGuest('accounts');
    }

    public function test_password_change_rejects_invalid_current_password(): void
    {
        $tenant = $this->makeTenant('pw-bad-current');
        $customer = $this->makeCustomer($tenant, 'pw-bad-current@test.com');

        $this->actingAs($customer, 'accounts')
            ->put("/store/{$tenant->slug}/customer/profile/password", [
                'current_password' => 'WrongPassword1',
                'password' => 'Abcdefg1',
                'password_confirmation' => 'Abcdefg1',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('Password1', $customer->fresh()->password));
    }

    public function test_password_change_rejects_weak_new_password(): void
    {
        $tenant = $this->makeTenant('pw-weak');
        $customer = $this->makeCustomer($tenant, 'pw-weak@test.com');

        $this->actingAs($customer, 'accounts')
            ->put("/store/{$tenant->slug}/customer/profile/password", [
                'current_password' => 'Password1',
                'password' => 'abcdefgh',
                'password_confirmation' => 'abcdefgh',
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('Password1', $customer->fresh()->password));
    }

    public function test_password_change_rejects_confirmation_mismatch(): void
    {
        $tenant = $this->makeTenant('pw-mismatch');
        $customer = $this->makeCustomer($tenant, 'pw-mismatch@test.com');

        $this->actingAs($customer, 'accounts')
            ->put("/store/{$tenant->slug}/customer/profile/password", [
                'current_password' => 'Password1',
                'password' => 'Abcdefg1',
                'password_confirmation' => 'Abcdefg2',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_profile_update_patch_succeeds(): void
    {
        $tenant = $this->makeTenant('profile-shop');
        $customer = $this->makeCustomer($tenant, 'profile-customer@test.com');

        $this->actingAs($customer, 'accounts')
            ->patch("/store/{$tenant->slug}/customer/profile", [
                'name' => 'Renamed Customer',
                'email' => 'profile-customer@test.com',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed Customer', $customer->fresh()->name);
    }

    public function test_delete_without_orders_removes_account(): void
    {
        $tenant = $this->makeTenant('del-shop');
        $customer = $this->makeCustomer($tenant, 'del-customer@test.com');

        $response = $this->actingAs($customer, 'accounts')
            ->delete("/store/{$tenant->slug}/customer/account", ['password' => 'Password1']);

        $response->assertRedirect('/');
        $response->assertSessionHas('success');
        $this->assertSoftDeleted('accounts', ['id' => $customer->id]);
        $this->assertGuest('accounts');
    }

    public function test_delete_with_orders_anonymizes_and_preserves_orders(): void
    {
        $tenant = $this->makeTenant('del-orders-shop');
        $customer = $this->makeCustomer($tenant, 'del-orders@test.com');
        $order = $this->makeOrder($tenant, $customer);

        $response = $this->actingAs($customer, 'accounts')
            ->delete("/store/{$tenant->slug}/customer/account", ['password' => 'Password1']);

        $response->assertRedirect('/');
        $response->assertSessionHas('success');

        $fresh = $customer->fresh();
        $this->assertSame('Deleted User', $fresh->name);
        $this->assertStringStartsWith('deleted_', $fresh->email);
        $this->assertSame('deleted', $fresh->status);
        $this->assertFalse(Hash::check('Password1', $fresh->password));

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertGuest('accounts');
    }

    public function test_delete_with_wrong_password_is_rejected(): void
    {
        $tenant = $this->makeTenant('del-wrong-pw');
        $customer = $this->makeCustomer($tenant, 'del-wrong-pw@test.com');

        $this->actingAs($customer, 'accounts')
            ->delete("/store/{$tenant->slug}/customer/account", ['password' => 'WrongPassword1'])
            ->assertSessionHasErrors('password');

        $this->assertSame('Customer', $customer->fresh()->name);
        $this->assertSame('active', $customer->fresh()->status);
    }

    public function test_cross_tenant_delete_is_denied(): void
    {
        $tenantA = $this->makeTenant('del-tenant-a');
        $tenantB = $this->makeTenant('del-tenant-b');
        $customerA = $this->makeCustomer($tenantA, 'del-a@test.com');
        $customerB = $this->makeCustomer($tenantB, 'del-b@test.com');

        $this->actingAs($customerA, 'accounts')
            ->delete("/store/{$tenantB->slug}/customer/account", ['password' => 'Password1'])
            ->assertForbidden();

        $this->assertSame('active', $customerA->fresh()->status);
        $this->assertSame('active', $customerB->fresh()->status);
    }

    public function test_self_delete_leaves_other_customers_untouched(): void
    {
        $tenant = $this->makeTenant('del-other-shop');
        $customerA = $this->makeCustomer($tenant, 'del-other-a@test.com');
        $customerB = $this->makeCustomer($tenant, 'del-other-b@test.com');

        $this->actingAs($customerA, 'accounts')
            ->delete("/store/{$tenant->slug}/customer/account", ['password' => 'Password1'])
            ->assertRedirect('/');

        $this->assertNotNull(Account::find($customerB->id));
        $this->assertSame('active', $customerB->fresh()->status);

        $this->post("/store/{$tenant->slug}/login", [
            'email' => 'del-other-b@test.com',
            'password' => 'Password1',
        ])->assertSessionHasNoErrors();
    }

    public function test_account_page_loads_for_customer(): void
    {
        $tenant = $this->makeTenant('account-page-shop');
        $customer = $this->makeCustomer($tenant, 'account-page@test.com');

        $this->actingAs($customer, 'accounts')
            ->get("/store/{$tenant->slug}/customer/account")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Storefront/Account'));
    }
}
