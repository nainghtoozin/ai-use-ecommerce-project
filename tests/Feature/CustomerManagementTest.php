<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CustomerProfile;
use App\Models\Order;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['customers.view', 'users.view', 'users.update', 'users.delete', 'users.suspend', 'users.ban', 'users.activate'] as $perm) {
            Permission::create(['name' => $perm, 'guard_name' => 'web']);
        }

        Role::create(['name' => 'superadmin', 'guard_name' => 'web'])->syncPermissions(Permission::all());
        Role::create(['name' => 'admin', 'guard_name' => 'web'])->syncPermissions(Permission::all());
        Role::create(['name' => 'staff', 'guard_name' => 'web']);
        Role::create(['name' => 'customer', 'guard_name' => 'web']);
    }

    public function test_phone_search_finds_customer(): void
    {
        config()->set('identity.use_accounts', true);
        [$tenantA, $ownerA, $customerA] = $this->seedTenant('phone-shop', '09111111111');

        $response = $this->actingAs($ownerA, 'accounts')->get('/admin/customers?search=09111111111');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Customers/Index')
            ->where('customers.data', fn ($data) => collect($data)->pluck('email')->contains($customerA->email))
        );

        $miss = $this->actingAs($ownerA, 'accounts')->get('/admin/customers?search=09999999999');

        $miss->assertInertia(fn ($page) => $page
            ->where('customers.data', fn ($data) => collect($data)->isEmpty())
        );
    }

    public function test_joined_date_uses_membership_not_account_creation(): void
    {
        config()->set('identity.use_accounts', true);
        [$tenantA, $ownerA, $customerA] = $this->seedTenant('join-shop', '09222222222', now()->subDays(9));

        $response = $this->actingAs($ownerA, 'accounts')->get('/admin/customers');

        $expected = now()->subDays(9)->toDateString();
        $response->assertInertia(fn ($page) => $page
            ->where('customers.data.0.email', $customerA->email)
            ->where('customers.data.0.joined_at', fn ($v) => str_starts_with((string) $v, $expected))
        );
    }

    public function test_inactive_status_filter(): void
    {
        config()->set('identity.use_accounts', true);
        [$tenantA, $ownerA, $customerA] = $this->seedTenant('status-shop', '09333333333');
        TenantMembership::where('account_id', $customerA->id)->where('tenant_id', $tenantA->id)->update(['status' => 'inactive']);

        $response = $this->actingAs($ownerA, 'accounts')->get('/admin/customers?status=inactive');

        $response->assertInertia(fn ($page) => $page
            ->where('customers.data', fn ($data) => collect($data)->pluck('email')->contains($customerA->email))
        );
    }

    public function test_per_page_all_is_capped(): void
    {
        config()->set('identity.use_accounts', true);
        [$tenantA, $ownerA, $customerA] = $this->seedTenant('cap-shop', '09444444444');

        $response = $this->actingAs($ownerA, 'accounts')->get('/admin/customers?per_page=all');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Customers/Index')
            ->where('showPagination', true)
            ->where('warning', 'Showing up to 500 customers per page.')
            ->where('customers.data', fn ($data) => collect($data)->pluck('email')->contains($customerA->email))
        );
    }

    public function test_show_page_with_orders_and_cross_tenant_404(): void
    {
        config()->set('identity.use_accounts', true);
        [$tenantA, $ownerA, $customerA] = $this->seedTenant('show-shop-a', '09555555555');
        [$tenantB, $ownerB, $customerB] = $this->seedTenant('show-shop-b', '09666666666');

        $this->makeOrder($tenantA, $customerA, Account::class, 150.00);

        $orderForItems = $this->makeOrder($tenantA, $customerA, Account::class, 75.00);
        $category = \App\Models\Category::factory()->create(['tenant_id' => $tenantA->id]);
        $product = \App\Models\Product::factory()->create(['tenant_id' => $tenantA->id, 'category_id' => $category->id, 'name' => 'Test Widget']);
        $item = new \App\Models\OrderItem();
        $item->tenant_id = $tenantA->id;
        $item->order_id = $orderForItems->id;
        $item->product_id = $product->id;
        $item->quantity = 2;
        $item->price = 30.00;
        $item->save();

        $response = $this->actingAs($ownerA, 'accounts')->get("/admin/customers/{$customerA->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Customers/Show')
            ->where('customer.email', $customerA->email)
            ->where('orderStats.count', 2)
            ->where('orderStats.total_spent', fn ($v) => (float) $v === 225.0)
            ->where('lastOrder.id', $orderForItems->id)
            ->where('orderHistory.data', fn ($data) => collect($data)->contains(fn ($o) => $o['id'] === $orderForItems->id && $o['items_count'] === 2 && str_contains($o['items_summary'][0]['product_name'] ?? '', 'Test Widget')))
        );

        $this->actingAs($ownerA, 'accounts')->get("/admin/customers/{$customerB->id}")->assertNotFound();
        $this->actingAs($ownerA, 'accounts')->get("/admin/customers/{$ownerA->id}")->assertNotFound();
    }

    public function test_update_name_email_phone(): void
    {
        config()->set('identity.use_accounts', true);
        [$tenantA, $ownerA, $customerA] = $this->seedTenant('edit-shop', '09777777777');

        $response = $this->actingAs($ownerA, 'accounts')->put("/admin/customers/{$customerA->id}", [
            'name' => 'Renamed Customer',
            'email' => 'renamed@test.com',
            'phone' => '09888888888',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertEquals('Renamed Customer', $customerA->fresh()->name);
        $this->assertEquals('renamed@test.com', $customerA->fresh()->email);
        $membership = TenantMembership::where('account_id', $customerA->id)->where('tenant_id', $tenantA->id)->first();
        $this->assertEquals('09888888888', CustomerProfile::where('tenant_membership_id', $membership->id)->value('phone'));
    }

    public function test_update_rejects_duplicate_email(): void
    {
        config()->set('identity.use_accounts', true);
        [$tenantA, $ownerA, $customerA] = $this->seedTenant('dup-shop', '09911111111');

        $response = $this->actingAs($ownerA, 'accounts')->put("/admin/customers/{$customerA->id}", [
            'email' => $ownerA->email,
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_suspend_ban_activate_flow(): void
    {
        config()->set('identity.use_accounts', true);
        [$tenantA, $ownerA, $customerA] = $this->seedTenant('flow-shop', '09922222222');

        $membership = fn () => TenantMembership::where('account_id', $customerA->id)->where('tenant_id', $tenantA->id)->first();

        $this->actingAs($ownerA, 'accounts')->post("/admin/customers/{$customerA->id}/suspend", ['reason' => 'Abusive messages']);
        $this->assertEquals('suspended', $membership()->status);
        $this->assertEquals('Abusive messages', $membership()->status_reason);
        $this->assertEquals('active', $customerA->fresh()->status);

        $this->actingAs($ownerA, 'accounts')->post("/admin/customers/{$customerA->id}/ban", ['reason' => 'Fraud']);
        $this->assertEquals('banned', $membership()->status);
        $this->assertEquals('Fraud', $membership()->status_reason);

        $this->actingAs($ownerA, 'accounts')->post("/admin/customers/{$customerA->id}/activate");
        $this->assertEquals('active', $membership()->status);
        $this->assertNull($membership()->status_reason);
    }

    public function test_status_action_requires_permission(): void
    {
        config()->set('identity.use_accounts', true);
        [$tenantA, $ownerA, $customerA] = $this->seedTenant('perm-shop', '09933333333');

        $staffRole = Role::withoutTenantScope()->firstOrCreate(['name' => 'staff', 'guard_name' => 'web', 'tenant_id' => $tenantA->id]);
        $staffRole->syncPermissions(Permission::where('name', 'customers.view')->get());
        $staff = Account::create(['name' => 'Staff', 'email' => 'staff@perm-shop.test', 'password' => bcrypt('password'), 'status' => 'active']);
        TenantMembership::create(['account_id' => $staff->id, 'tenant_id' => $tenantA->id, 'role_id' => $staffRole->id, 'is_owner' => false, 'status' => 'active', 'joined_at' => now()]);

        $this->actingAs($staff, 'accounts')->post("/admin/customers/{$customerA->id}/suspend", ['reason' => 'x'])->assertForbidden();
        $this->assertEquals('active', TenantMembership::where('account_id', $customerA->id)->where('tenant_id', $tenantA->id)->value('status'));
    }

    public function test_account_mode_delete_removes_membership_and_preserves_orders(): void
    {
        config()->set('identity.use_accounts', true);
        [$tenantA, $ownerA, $customerA] = $this->seedTenant('del-shop', '09944444444');

        $order = $this->makeOrder($tenantA, $customerA, Account::class, 200.00);

        $response = $this->actingAs($ownerA, 'accounts')->delete("/admin/customers/{$customerA->id}");

        $response->assertSessionHasNoErrors();
        $this->assertFalse(TenantMembership::where('account_id', $customerA->id)->where('tenant_id', $tenantA->id)->exists());
        $this->assertNotNull(Account::find($customerA->id));
        $this->assertNotNull(Order::withoutTenantScope()->find($order->id));

        $list = $this->actingAs($ownerA, 'accounts')->get('/admin/customers');
        $list->assertInertia(fn ($page) => $page
            ->where('customers.data', fn ($data) => collect($data)->pluck('email')->doesntContain($customerA->email))
        );
    }

    public function test_legacy_delete_blocked_when_orders_exist(): void
    {
        config()->set('identity.use_accounts', false);
        $superadmin = User::factory()->superadmin()->create();
        $tenant = Tenant::create(['slug' => 'legacy-shop', 'name' => 'Legacy Shop', 'status' => 'active']);
        $customer = User::factory()->create(['tenant_id' => $tenant->id]);
        $customer->syncRoles(['customer']);
        $order = $this->makeOrder($tenant, $customer, User::class, 99.00);

        $response = $this->actingAs($superadmin)->delete("/admin/customers/{$customer->id}");

        $response->assertSessionHas('error');
        $this->assertNotNull(User::find($customer->id));
        $this->assertNotNull(Order::withoutTenantScope()->find($order->id));
    }

    public function test_legacy_delete_succeeds_without_orders(): void
    {
        config()->set('identity.use_accounts', false);
        $superadmin = User::factory()->superadmin()->create();
        $tenant = Tenant::create(['slug' => 'legacy-del-shop', 'name' => 'Legacy Del', 'status' => 'active']);
        $customer = User::factory()->create(['tenant_id' => $tenant->id]);
        $customer->syncRoles(['customer']);

        $this->actingAs($superadmin)->delete("/admin/customers/{$customer->id}")->assertSessionHasNoErrors();
        $this->assertNull(User::find($customer->id));
    }

    public function test_suspend_and_ban_require_reason(): void
    {
        config()->set('identity.use_accounts', true);
        [$tenantA, $ownerA, $customerA] = $this->seedTenant('reason-shop', '09955555555');

        $this->actingAs($ownerA, 'accounts')->post("/admin/customers/{$customerA->id}/suspend", [])->assertSessionHasErrors('reason');
        $this->actingAs($ownerA, 'accounts')->post("/admin/customers/{$customerA->id}/ban", [])->assertSessionHasErrors('reason');

        $this->assertEquals('active', TenantMembership::where('account_id', $customerA->id)->where('tenant_id', $tenantA->id)->value('status'));
    }

    public function test_detail_shows_status_reason(): void
    {
        config()->set('identity.use_accounts', true);
        [$tenantA, $ownerA, $customerA] = $this->seedTenant('detail-shop', '09966666666');

        $this->actingAs($ownerA, 'accounts')->post("/admin/customers/{$customerA->id}/suspend", ['reason' => 'Repeated no-shows']);

        $this->actingAs($ownerA, 'accounts')->get("/admin/customers/{$customerA->id}")->assertInertia(fn ($page) => $page
            ->component('Admin/Customers/Show')
            ->where('customer.status', 'suspended')
            ->where('customer.status_reason', 'Repeated no-shows')
        );
    }

    public function test_suspension_is_tenant_scoped_with_login_enforcement(): void
    {
        config()->set('identity.use_accounts', true);

        $tenantA = Tenant::create(['slug' => 'scope-shop-a', 'name' => 'Scope A', 'status' => 'active']);
        $tenantB = Tenant::create(['slug' => 'scope-shop-b', 'name' => 'Scope B', 'status' => 'active']);

        $adminRoleA = Role::withoutTenantScope()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenantA->id]);
        $adminRoleA->syncPermissions(Permission::all());
        $customerRoleA = Role::withoutTenantScope()->firstOrCreate(['name' => 'customer', 'guard_name' => 'web', 'tenant_id' => $tenantA->id]);
        $customerRoleB = Role::withoutTenantScope()->firstOrCreate(['name' => 'customer', 'guard_name' => 'web', 'tenant_id' => $tenantB->id]);

        $ownerA = Account::create(['name' => 'Owner A', 'email' => 'owner@scope-shop-a.test', 'password' => bcrypt('password'), 'status' => 'active']);
        TenantMembership::create(['account_id' => $ownerA->id, 'tenant_id' => $tenantA->id, 'role_id' => $adminRoleA->id, 'is_owner' => true, 'status' => 'active', 'joined_at' => now()]);

        $shared = Account::create(['name' => 'Shared Customer', 'email' => 'shared@scope.test', 'password' => bcrypt('password'), 'status' => 'active']);
        TenantMembership::create(['account_id' => $shared->id, 'tenant_id' => $tenantA->id, 'role_id' => $customerRoleA->id, 'is_owner' => false, 'status' => 'active', 'joined_at' => now()]);
        TenantMembership::create(['account_id' => $shared->id, 'tenant_id' => $tenantB->id, 'role_id' => $customerRoleB->id, 'is_owner' => false, 'status' => 'active', 'joined_at' => now()]);

        $this->actingAs($ownerA, 'accounts')->post("/admin/customers/{$shared->id}/suspend", ['reason' => 'Payment fraud']);

        $this->assertEquals('suspended', TenantMembership::where('account_id', $shared->id)->where('tenant_id', $tenantA->id)->value('status'));
        $this->assertEquals('active', TenantMembership::where('account_id', $shared->id)->where('tenant_id', $tenantB->id)->value('status'));
        $this->assertEquals('active', $shared->fresh()->status);

        $blocked = $this->post("/store/{$tenantA->slug}/login", ['email' => 'shared@scope.test', 'password' => 'password']);
        $blocked->assertSessionHasErrors('email');
        $this->assertStringContainsString('Payment fraud', (string) session()->get('errors')->first('email'));

        $allowed = $this->post("/store/{$tenantB->slug}/login", ['email' => 'shared@scope.test', 'password' => 'password']);
        $allowed->assertSessionHasNoErrors();
    }

    public function test_legacy_suspend_with_reason_blocks_storefront_login(): void
    {
        config()->set('identity.use_accounts', false);
        $superadmin = User::factory()->superadmin()->create();
        $tenant = Tenant::create(['slug' => 'legacy-status-shop', 'name' => 'Legacy Status', 'status' => 'active']);
        $customer = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('password')]);
        $customer->syncRoles(['customer']);

        $this->actingAs($superadmin)->post("/admin/customers/{$customer->id}/suspend", ['reason' => 'Chargeback abuse']);

        $this->assertEquals('suspended', $customer->fresh()->status);
        $this->assertEquals('Chargeback abuse', $customer->fresh()->status_reason);

        $blocked = $this->post("/store/{$tenant->slug}/login", ['email' => $customer->email, 'password' => 'password']);
        $blocked->assertSessionHasErrors('email');
    }

    private function seedTenant(string $slug, string $phone, $joinedAt = null): array
    {
        $tenant = Tenant::create(['slug' => $slug, 'name' => $slug, 'status' => 'active']);

        $adminRole = Role::withoutTenantScope()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $adminRole->syncPermissions(Permission::all());
        $customerRole = Role::withoutTenantScope()->firstOrCreate(['name' => 'customer', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);

        $owner = Account::create(['name' => "Owner {$slug}", 'email' => "owner@{$slug}.test", 'password' => bcrypt('password'), 'status' => 'active']);
        TenantMembership::create(['account_id' => $owner->id, 'tenant_id' => $tenant->id, 'role_id' => $adminRole->id, 'is_owner' => true, 'status' => 'active', 'joined_at' => now()]);

        $customer = Account::create(['name' => "Customer {$slug}", 'email' => "customer@{$slug}.test", 'password' => bcrypt('password'), 'status' => 'active']);
        $membership = TenantMembership::create(['account_id' => $customer->id, 'tenant_id' => $tenant->id, 'role_id' => $customerRole->id, 'is_owner' => false, 'status' => 'active', 'joined_at' => $joinedAt ?? now()]);
        CustomerProfile::create(['tenant_membership_id' => $membership->id, 'name' => "Customer {$slug}", 'phone' => $phone]);

        return [$tenant, $owner, $customer];
    }

    private function makeOrder(Tenant $tenant, $customer, string $userType, float $total): Order
    {
        $order = new Order();
        $order->tenant_id = $tenant->id;
        $order->user_id = $customer->id;
        $order->user_type = $userType;
        $order->customer_name = $customer->name;
        $order->first_name = 'Test';
        $order->last_name = 'Customer';
        $order->phone = '09000000000';
        $order->email = $customer->email;
        $order->address = '123 Test Street';
        $order->total_amount = $total;
        $order->payment_status = Order::PAYMENT_STATUS_PENDING;
        $order->order_status = Order::ORDER_STATUS_PENDING;
        $order->save();

        return $order;
    }
}
