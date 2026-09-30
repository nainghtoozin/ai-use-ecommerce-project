<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CustomerProfile;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CustomerMemberSeparationTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'users.view', 'users.create', 'users.update', 'users.delete', 'users.suspend',
            'users.ban', 'users.activate', 'users.assign-roles', 'users.view-activity',
            'customers.view',
        ];
        foreach ($permissions as $perm) {
            Permission::create(['name' => $perm, 'guard_name' => 'web']);
        }

        $superadminRole = Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        $superadminRole->syncPermissions(Permission::all());

        $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions(Permission::whereIn('name', [
            'users.view', 'users.create', 'users.update',
        ])->get());

        Role::create(['name' => 'customer', 'guard_name' => 'web']);
        Role::create(['name' => 'staff', 'guard_name' => 'web']);

        $this->superadmin = User::factory()->superadmin()->create();
    }

    public function test_members_index_excludes_customer_role_users(): void
    {
        config()->set('identity.use_accounts', false);

        $member = User::factory()->create();
        $member->syncRoles(['admin']);
        $customer = User::factory()->create();

        $response = $this->actingAs($this->superadmin)->get('/admin/users');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Users/Index')
            ->where('users.data', fn ($data) => collect($data)->pluck('email')->contains($member->email))
            ->where('users.data', fn ($data) => collect($data)->pluck('email')->doesntContain($customer->email))
        );
    }

    public function test_customers_page_lists_only_customers(): void
    {
        config()->set('identity.use_accounts', false);

        $member = User::factory()->create();
        $member->syncRoles(['admin']);
        $customer = User::factory()->create();

        $response = $this->actingAs($this->superadmin)->get('/admin/customers');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Customers/Index')
            ->where('customers.data', fn ($data) => collect($data)->pluck('email')->contains($customer->email))
            ->where('customers.data', fn ($data) => collect($data)->pluck('email')->doesntContain($member->email))
            ->where('customers.data', fn ($data) => collect($data)->pluck('email')->doesntContain($this->superadmin->email))
        );
    }

    public function test_members_store_rejects_customer_role(): void
    {
        $response = $this->actingAs($this->superadmin)->post('/admin/users', [
            'name' => 'New Customer',
            'email' => 'new-customer@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertNull(User::where('email', 'new-customer@example.com')->first());
    }

    public function test_members_update_rejects_customer_role(): void
    {
        $user = User::factory()->create();
        $user->syncRoles(['admin']);

        $response = $this->actingAs($this->superadmin)->put("/admin/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'customer',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertTrue($user->fresh()->hasRole('admin'));
    }

    public function test_customers_page_requires_customers_view_permission(): void
    {
        $tenant = Tenant::create(['slug' => 'perm-shop', 'name' => 'Perm Shop', 'status' => 'active']);
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $admin->syncRoles(['admin']);

        $this->actingAs($admin)->get('/admin/customers')->assertForbidden();
    }

    public function test_account_mode_members_excludes_customers_and_scopes_by_tenant(): void
    {
        config()->set('identity.use_accounts', true);
        [$tenantA, $tenantB, $ownerA, $customerA, $customerB] = $this->seedAccountTenants();

        $response = $this->actingAs($ownerA, 'accounts')->get('/admin/users');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Users/Index')
            ->where('users.data', fn ($data) => collect($data)->pluck('email')->contains($ownerA->email))
            ->where('users.data', fn ($data) => collect($data)->pluck('email')->doesntContain($customerA->email))
            ->where('users.data', fn ($data) => collect($data)->pluck('email')->doesntContain($customerB->email))
        );
    }

    public function test_account_mode_customers_page_is_tenant_scoped_with_profile_phone(): void
    {
        config()->set('identity.use_accounts', true);
        [$tenantA, $tenantB, $ownerA, $customerA, $customerB] = $this->seedAccountTenants();

        $response = $this->actingAs($ownerA, 'accounts')->get('/admin/customers');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Customers/Index')
            ->where('customers.data.0.email', $customerA->email)
            ->where('customers.data.0.phone', '09123456789')
            ->where('customers.data', fn ($data) => collect($data)->pluck('email')->doesntContain($customerB->email))
            ->where('customers.data', fn ($data) => collect($data)->pluck('email')->doesntContain($ownerA->email))
        );
    }

    public function test_account_mode_role_filter_is_tenant_scoped(): void
    {
        config()->set('identity.use_accounts', true);
        [$tenantA, $tenantB, $ownerA, $customerA, $customerB, $crossTenantAdmin] = $this->seedAccountTenants();

        $response = $this->actingAs($ownerA, 'accounts')->get('/admin/users?role=admin');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Users/Index')
            ->where('users.data', fn ($data) => collect($data)->pluck('email')->contains($ownerA->email))
            ->where('users.data', fn ($data) => collect($data)->pluck('email')->doesntContain($crossTenantAdmin->email))
        );
    }

    public function test_account_mode_role_validation_rejects_other_tenants_role(): void
    {
        config()->set('identity.use_accounts', true);
        [$tenantA, $tenantB, $ownerA, $customerA, $customerB, $crossTenantAdmin] = $this->seedAccountTenants();

        $foreignRole = Role::withoutTenantScope()->create([
            'name' => 'warehouse-b-only',
            'guard_name' => 'web',
            'tenant_id' => $tenantB->id,
        ]);

        $response = $this->actingAs($ownerA, 'accounts')->put("/admin/users/{$crossTenantAdmin->id}", [
            'name' => $crossTenantAdmin->name,
            'email' => $crossTenantAdmin->email,
            'role' => 'warehouse-b-only',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('role');

        $membershipA = TenantMembership::where('account_id', $crossTenantAdmin->id)
            ->where('tenant_id', $tenantA->id)
            ->first();
        $this->assertNotEquals($foreignRole->id, $membershipA->role_id);
    }

    public function test_account_mode_admin_delete_uses_membership_counts(): void
    {
        config()->set('identity.use_accounts', true);
        [$tenantA, $tenantB, $ownerA, $customerA, $customerB, $crossTenantAdmin] = $this->seedAccountTenants();

        $adminRoleA = Role::withoutTenantScope()->where('name', 'admin')->where('tenant_id', $tenantA->id)->first();
        $secondAdmin = Account::create(['name' => 'Second Admin', 'email' => 'sep-second-admin@test.com', 'password' => bcrypt('password'), 'status' => 'active']);
        TenantMembership::create(['account_id' => $secondAdmin->id, 'tenant_id' => $tenantA->id, 'role_id' => $adminRoleA->id, 'is_owner' => false, 'status' => 'active', 'joined_at' => now()]);

        $response = $this->actingAs($ownerA, 'accounts')->delete("/admin/users/{$secondAdmin->id}");

        $response->assertSessionHasNoErrors();
        $this->assertNull(Account::find($secondAdmin->id));
        $this->assertTrue(TenantMembership::where('tenant_id', $tenantA->id)->where('is_owner', true)->exists());
    }

    private function seedAccountTenants(): array
    {
        $tenantA = Tenant::create(['slug' => 'sep-shop-a', 'name' => 'Shop A', 'status' => 'active']);
        $tenantB = Tenant::create(['slug' => 'sep-shop-b', 'name' => 'Shop B', 'status' => 'active']);

        $adminRoleA = Role::withoutTenantScope()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenantA->id]);
        $adminRoleA->syncPermissions(Permission::all());
        $adminRoleB = Role::withoutTenantScope()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenantB->id]);
        $adminRoleB->syncPermissions(Permission::all());
        $staffRoleA = Role::withoutTenantScope()->firstOrCreate(['name' => 'staff', 'guard_name' => 'web', 'tenant_id' => $tenantA->id]);
        $customerRoleA = Role::withoutTenantScope()->firstOrCreate(['name' => 'customer', 'guard_name' => 'web', 'tenant_id' => $tenantA->id]);
        $customerRoleB = Role::withoutTenantScope()->firstOrCreate(['name' => 'customer', 'guard_name' => 'web', 'tenant_id' => $tenantB->id]);

        $ownerA = Account::create(['name' => 'Owner A', 'email' => 'sep-owner-a@test.com', 'password' => bcrypt('password'), 'status' => 'active']);
        TenantMembership::create(['account_id' => $ownerA->id, 'tenant_id' => $tenantA->id, 'role_id' => $adminRoleA->id, 'is_owner' => true, 'status' => 'active', 'joined_at' => now()]);

        $customerA = Account::create(['name' => 'Customer A', 'email' => 'sep-customer-a@test.com', 'password' => bcrypt('password'), 'status' => 'active']);
        $membershipA = TenantMembership::create(['account_id' => $customerA->id, 'tenant_id' => $tenantA->id, 'role_id' => $customerRoleA->id, 'is_owner' => false, 'status' => 'active', 'joined_at' => now()]);
        CustomerProfile::create(['tenant_membership_id' => $membershipA->id, 'name' => 'Customer A', 'phone' => '09123456789']);

        $customerB = Account::create(['name' => 'Customer B', 'email' => 'sep-customer-b@test.com', 'password' => bcrypt('password'), 'status' => 'active']);
        TenantMembership::create(['account_id' => $customerB->id, 'tenant_id' => $tenantB->id, 'role_id' => $customerRoleB->id, 'is_owner' => false, 'status' => 'active', 'joined_at' => now()]);

        $crossTenantAdmin = Account::create(['name' => 'Cross Admin', 'email' => 'sep-cross@test.com', 'password' => bcrypt('password'), 'status' => 'active']);
        TenantMembership::create(['account_id' => $crossTenantAdmin->id, 'tenant_id' => $tenantA->id, 'role_id' => $staffRoleA->id, 'is_owner' => false, 'status' => 'active', 'joined_at' => now()]);
        TenantMembership::create(['account_id' => $crossTenantAdmin->id, 'tenant_id' => $tenantB->id, 'role_id' => $adminRoleB->id, 'is_owner' => false, 'status' => 'active', 'joined_at' => now()]);

        return [$tenantA, $tenantB, $ownerA, $customerA, $customerB, $crossTenantAdmin];
    }
}
