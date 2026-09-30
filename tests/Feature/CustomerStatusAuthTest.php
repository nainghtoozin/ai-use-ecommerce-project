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

class CustomerStatusAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['customers.view', 'users.update', 'users.delete', 'users.suspend', 'users.ban', 'users.activate'] as $perm) {
            Permission::create(['name' => $perm, 'guard_name' => 'web']);
        }

        Role::create(['name' => 'superadmin', 'guard_name' => 'web'])->syncPermissions(Permission::all());
        Role::create(['name' => 'admin', 'guard_name' => 'web'])->syncPermissions(Permission::all());
        Role::create(['name' => 'staff', 'guard_name' => 'web']);
        Role::create(['name' => 'customer', 'guard_name' => 'web']);
    }

    public function test_active_customer_can_login(): void
    {
        config()->set('identity.use_accounts', true);
        [$tenant, $owner, $customer] = $this->seedTenant('auth-shop', '09811111111');

        $response = $this->post("/store/{$tenant->slug}/login", [
            'email' => $customer->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasNoErrors();
    }

    public function test_suspended_customer_cannot_login(): void
    {
        config()->set('identity.use_accounts', true);
        [$tenant, $owner, $customer] = $this->seedTenant('auth-susp-shop', '09822222222');
        $this->setMembershipStatus($customer->id, $tenant->id, 'suspended', null);

        $response = $this->post("/store/{$tenant->slug}/login", [
            'email' => $customer->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Your account has been suspended.', $this->loginError());
        $this->assertGuest('accounts');
    }

    public function test_banned_customer_cannot_login(): void
    {
        config()->set('identity.use_accounts', true);
        [$tenant, $owner, $customer] = $this->seedTenant('auth-ban-shop', '09833333333');
        $this->setMembershipStatus($customer->id, $tenant->id, 'banned', null);

        $response = $this->post("/store/{$tenant->slug}/login", [
            'email' => $customer->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Your account has been banned.', $this->loginError());
        $this->assertGuest('accounts');
    }

    public function test_suspended_customer_with_reason_sees_reason(): void
    {
        config()->set('identity.use_accounts', true);
        [$tenant, $owner, $customer] = $this->seedTenant('auth-reason-shop', '09844444444');
        $this->setMembershipStatus($customer->id, $tenant->id, 'suspended', 'Unpaid dues');

        $this->post("/store/{$tenant->slug}/login", [
            'email' => $customer->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertStringContainsString('Reason: Unpaid dues', $this->loginError());
        $this->assertEquals('suspended', session('customer_status')['status'] ?? null);
        $this->assertEquals('Unpaid dues', session('customer_status')['reason'] ?? null);
    }

    public function test_banned_customer_with_reason_sees_reason(): void
    {
        config()->set('identity.use_accounts', true);
        [$tenant, $owner, $customer] = $this->seedTenant('auth-ban-reason-shop', '09855555555');
        $this->setMembershipStatus($customer->id, $tenant->id, 'banned', 'Severe fraud');

        $this->post("/store/{$tenant->slug}/login", [
            'email' => $customer->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertStringContainsString('Reason: Severe fraud', $this->loginError());
        $this->assertEquals('banned', session('customer_status')['status'] ?? null);
        $this->assertEquals('Severe fraud', session('customer_status')['reason'] ?? null);
    }

    public function test_authenticated_suspended_customer_is_logged_out(): void
    {
        config()->set('identity.use_accounts', true);
        [$tenant, $owner, $customer] = $this->seedTenant('auth-kick-shop', '09866666666');

        $this->actingAs($customer, 'accounts')
            ->get("/store/{$tenant->slug}/customer/account")
            ->assertStatus(200);

        $this->setMembershipStatus($customer->id, $tenant->id, 'suspended', 'Policy violation');

        $response = $this->actingAs($customer, 'accounts')->get("/store/{$tenant->slug}/customer/account");

        $response->assertRedirect(route('storefront.login', ['store_slug' => $tenant->slug], absolute: false));
        $this->assertStringContainsString('Your account has been suspended.', (string) session('error'));
        $this->assertStringContainsString('Reason: Policy violation', (string) session('error'));
        $this->assertGuest('accounts');
    }

    public function test_authenticated_banned_customer_is_logged_out(): void
    {
        config()->set('identity.use_accounts', true);
        [$tenant, $owner, $customer] = $this->seedTenant('auth-kick-ban-shop', '09877777777');

        $this->setMembershipStatus($customer->id, $tenant->id, 'banned', 'Chargeback fraud');

        $response = $this->actingAs($customer, 'accounts')->get("/store/{$tenant->slug}/customer/account");

        $response->assertRedirect(route('storefront.login', ['store_slug' => $tenant->slug], absolute: false));
        $this->assertStringContainsString('Your account has been banned.', (string) session('error'));
        $this->assertGuest('accounts');
    }

    public function test_suspension_does_not_affect_other_store(): void
    {
        config()->set('identity.use_accounts', true);

        $tenantA = Tenant::create(['slug' => 'iso-shop-a', 'name' => 'Iso A', 'status' => 'active']);
        $tenantB = Tenant::create(['slug' => 'iso-shop-b', 'name' => 'Iso B', 'status' => 'active']);

        foreach ([$tenantA, $tenantB] as $t) {
            Role::withoutTenantScope()->firstOrCreate(['name' => 'customer', 'guard_name' => 'web', 'tenant_id' => $t->id]);
        }

        $shared = Account::create(['name' => 'Shared', 'email' => 'shared@iso.test', 'password' => bcrypt('password'), 'status' => 'active']);
        TenantMembership::create(['account_id' => $shared->id, 'tenant_id' => $tenantA->id, 'role_id' => Role::withoutTenantScope()->where('name', 'customer')->where('tenant_id', $tenantA->id)->first()->id, 'is_owner' => false, 'status' => 'active', 'joined_at' => now()]);
        TenantMembership::create(['account_id' => $shared->id, 'tenant_id' => $tenantB->id, 'role_id' => Role::withoutTenantScope()->where('name', 'customer')->where('tenant_id', $tenantB->id)->first()->id, 'is_owner' => false, 'status' => 'active', 'joined_at' => now()]);

        $this->setMembershipStatus($shared->id, $tenantA->id, 'suspended', 'Store A issue');

        $this->post("/store/{$tenantB->slug}/login", [
            'email' => 'shared@iso.test',
            'password' => 'password',
        ])->assertSessionHasNoErrors();

        $this->actingAs($shared, 'accounts')
            ->get("/store/{$tenantB->slug}/customer/account")
            ->assertStatus(200);

        $this->actingAs($shared, 'accounts')
            ->get("/store/{$tenantA->slug}/customer/account")
            ->assertRedirect(route('storefront.login', ['store_slug' => $tenantA->slug], absolute: false));
        $this->assertGuest('accounts');
    }

    public function test_legacy_suspended_customer_blocked_with_reason(): void
    {
        config()->set('identity.use_accounts', false);
        $superadmin = User::factory()->superadmin()->create();
        $tenant = Tenant::create(['slug' => 'legacy-auth-shop', 'name' => 'Legacy Auth', 'status' => 'active']);
        $customer = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('password')]);
        $customer->syncRoles(['customer']);

        $this->actingAs($superadmin)->post("/admin/customers/{$customer->id}/suspend", ['reason' => 'Legacy abuse']);

        $blocked = $this->post("/store/{$tenant->slug}/login", [
            'email' => $customer->email,
            'password' => 'password',
        ]);
        $blocked->assertSessionHasErrors('email');
        $this->assertStringContainsString('Reason: Legacy abuse', $this->loginError());

        $response = $this->actingAs($customer->refresh())->get("/store/{$tenant->slug}/customer/account");
        $response->assertRedirect();
        $this->assertGuest('web');
    }

    private function loginError(): string
    {
        return (string) session()->get('errors')->first('email');
    }

    private function setMembershipStatus(int $accountId, int $tenantId, string $status, ?string $reason): void
    {
        TenantMembership::where('account_id', $accountId)->where('tenant_id', $tenantId)->update([
            'status' => $status,
            'status_reason' => $reason,
        ]);
    }

    private function seedTenant(string $slug, string $phone): array
    {
        $tenant = Tenant::create(['slug' => $slug, 'name' => $slug, 'status' => 'active']);

        $adminRole = Role::withoutTenantScope()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $adminRole->syncPermissions(Permission::all());
        $customerRole = Role::withoutTenantScope()->firstOrCreate(['name' => 'customer', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);

        $owner = Account::create(['name' => "Owner {$slug}", 'email' => "owner@{$slug}.test", 'password' => bcrypt('password'), 'status' => 'active']);
        TenantMembership::create(['account_id' => $owner->id, 'tenant_id' => $tenant->id, 'role_id' => $adminRole->id, 'is_owner' => true, 'status' => 'active', 'joined_at' => now()]);

        $customer = Account::create(['name' => "Customer {$slug}", 'email' => "customer@{$slug}.test", 'password' => bcrypt('password'), 'status' => 'active']);
        $membership = TenantMembership::create(['account_id' => $customer->id, 'tenant_id' => $tenant->id, 'role_id' => $customerRole->id, 'is_owner' => false, 'status' => 'active', 'joined_at' => now()]);
        CustomerProfile::create(['tenant_membership_id' => $membership->id, 'name' => "Customer {$slug}", 'phone' => $phone]);

        return [$tenant, $owner, $customer];
    }
}
