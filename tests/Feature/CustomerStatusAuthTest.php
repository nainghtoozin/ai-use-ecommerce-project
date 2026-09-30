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

    public function test_login_page_shows_tenant_support_info_with_isolation(): void
    {
        config()->set('identity.use_accounts', true);

        $tenantA = Tenant::create(['slug' => 'sup-shop-a', 'name' => 'Sup A', 'status' => 'active']);
        $tenantB = Tenant::create(['slug' => 'sup-shop-b', 'name' => 'Sup B', 'status' => 'active']);

        $this->setTenantSupport($tenantA, [
            'site_name' => 'Sup A',
            'support_email' => 'support-a@sup.test',
            'phone' => '09111111111',
            'whatsapp_number' => '09111111111',
            'contact_info' => ['telegram_username' => 'sup_a_bot'],
        ]);
        $this->setTenantSupport($tenantB, [
            'site_name' => 'Sup B',
            'support_email' => 'support-b@sup.test',
        ]);

        $this->get("/store/{$tenantA->slug}/login")->assertInertia(fn ($page) => $page
            ->where('website_info.support_email', 'support-a@sup.test')
            ->where('website_info.phone', '09111111111')
        );

        $this->get("/store/{$tenantB->slug}/login")->assertInertia(fn ($page) => $page
            ->where('website_info.support_email', 'support-b@sup.test')
            ->where('website_info.phone', fn ($v) => empty($v))
        );
    }

    public function test_blocked_login_keeps_support_info_available(): void
    {
        config()->set('identity.use_accounts', true);
        [$tenant, $owner, $customer] = $this->seedTenant('sup-blocked-shop', '09888888888');

        $this->setTenantSupport($tenant, [
            'site_name' => 'Sup Blocked',
            'support_email' => 'help@blocked.test',
        ]);

        $this->setMembershipStatus($customer->id, $tenant->id, 'banned', 'Fraud');

        $this->post("/store/{$tenant->slug}/login", [
            'email' => $customer->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->get("/store/{$tenant->slug}/login")->assertInertia(fn ($page) => $page
            ->where('website_info.support_email', 'help@blocked.test')
        );
    }

    private function setTenantSupport(Tenant $tenant, array $attributes): void
    {
        $info = \App\Models\WebsiteInfo::withoutTenantScope()->where('tenant_id', $tenant->id)->first()
            ?? new \App\Models\WebsiteInfo();
        $info->tenant_id = $tenant->id;
        foreach ($attributes as $key => $value) {
            $info->{$key} = $value;
        }
        $info->save();
    }

    public function test_contact_page_shows_tenant_support_info_with_isolation(): void
    {
        config()->set('identity.use_accounts', true);

        $tenantA = Tenant::create(['slug' => 'contact-shop-a', 'name' => 'Contact A', 'status' => 'active']);
        $tenantB = Tenant::create(['slug' => 'contact-shop-b', 'name' => 'Contact B', 'status' => 'active']);

        $this->setTenantSupport($tenantA, [
            'support_email' => 'support@contact-a.test',
            'phone' => '09222222222',
            'whatsapp_number' => '09222222222',
            'contact_info' => ['telegram_username' => 'contact_a_bot'],
        ]);

        $this->get("/store/{$tenantA->slug}/contact")->assertInertia(fn ($page) => $page
            ->component('Storefront/Cms/Contact')
            ->where('contact.support_email', 'support@contact-a.test')
            ->where('contact.phone', '09222222222')
            ->where('contact.whatsapp', '09222222222')
            ->where('contact.telegram', 'contact_a_bot')
        );

        $this->get("/store/{$tenantB->slug}/contact")->assertInertia(fn ($page) => $page
            ->component('Storefront/Cms/Contact')
            ->where('contact.support_email', fn ($v) => empty($v))
            ->where('contact.phone', fn ($v) => empty($v))
        );
    }

    public function test_suspended_customer_can_reach_support_page(): void
    {
        config()->set('identity.use_accounts', true);
        [$tenant, $owner, $customer] = $this->seedTenant('contact-blocked-shop', '09899999999');

        $this->setTenantSupport($tenant, ['support_email' => 'help@contact-blocked.test']);
        $this->setMembershipStatus($customer->id, $tenant->id, 'suspended', 'Policy breach');

        $this->post("/store/{$tenant->slug}/login", [
            'email' => $customer->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->get("/store/{$tenant->slug}/contact")->assertStatus(200)->assertInertia(fn ($page) => $page
            ->component('Storefront/Cms/Contact')
            ->where('contact.support_email', 'help@contact-blocked.test')
        );
    }

    public function test_support_page_shows_tenant_support_with_isolation(): void
    {
        config()->set('identity.use_accounts', true);

        $tenantA = Tenant::create(['slug' => 'hs-shop-a', 'name' => 'HS A', 'status' => 'active']);
        $tenantB = Tenant::create(['slug' => 'hs-shop-b', 'name' => 'HS B', 'status' => 'active']);

        $this->setTenantSupport($tenantA, [
            'support_info' => [
                'email' => 'help@hs-a.test',
                'phone' => '09333333333',
                'whatsapp' => '09333333333',
                'telegram' => 'hs_a_bot',
                'hours' => 'Mon-Fri 9am-6pm',
                'message' => 'We reply within a day.',
            ],
        ]);

        $this->get("/store/{$tenantA->slug}/support")->assertInertia(fn ($page) => $page
            ->component('Storefront/Cms/Support')
            ->where('support.email', 'help@hs-a.test')
            ->where('support.phone', '09333333333')
            ->where('support.telegram', 'hs_a_bot')
            ->where('support.hours', 'Mon-Fri 9am-6pm')
        );

        $this->get("/store/{$tenantB->slug}/support")->assertInertia(fn ($page) => $page
            ->component('Storefront/Cms/Support')
            ->where('support.email', fn ($v) => empty($v))
            ->where('support.phone', fn ($v) => empty($v))
        );
    }

    public function test_admin_can_save_support_info_without_touching_public_contact(): void
    {
        config()->set('identity.use_accounts', true);

        if (!\Spatie\Permission\Models\Permission::where('name', 'settings.website')->exists()) {
            \Spatie\Permission\Models\Permission::create(['name' => 'settings.website', 'guard_name' => 'web']);
        }

        $tenant = Tenant::create(['slug' => 'hs-admin-shop', 'name' => 'HS Admin', 'status' => 'active']);
        $adminRole = Role::withoutTenantScope()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $adminRole->syncPermissions(\Spatie\Permission\Models\Permission::all());
        $owner = Account::create(['name' => 'Owner', 'email' => 'owner@hs-admin.test', 'password' => bcrypt('password'), 'status' => 'active']);
        TenantMembership::create(['account_id' => $owner->id, 'tenant_id' => $tenant->id, 'role_id' => $adminRole->id, 'is_owner' => true, 'status' => 'active', 'joined_at' => now()]);

        $this->setTenantSupport($tenant, ['support_email' => 'public@hs-admin.test']);

        $response = $this->actingAs($owner, 'accounts')->put("/store/{$tenant->slug}/admin/settings", [
            'support_contact_email' => 'help@hs-admin.test',
            'support_contact_phone' => '09444444444',
            'support_contact_whatsapp' => '09444444444',
            'support_contact_telegram' => 'hs_admin_bot',
            'support_hours' => 'Daily 8am-10pm',
            'support_message' => 'We are here to help.',
        ]);

        $response->assertSessionHasNoErrors();

        $info = \App\Models\WebsiteInfo::withoutTenantScope()->where('tenant_id', $tenant->id)->first();
        $this->assertEquals('help@hs-admin.test', $info->support_info['email'] ?? null);
        $this->assertEquals('hs_admin_bot', $info->support_info['telegram'] ?? null);
        $this->assertEquals('public@hs-admin.test', $info->support_email);

        $this->get("/store/{$tenant->slug}/support")->assertInertia(fn ($page) => $page
            ->where('support.email', 'help@hs-admin.test')
        );
    }

    public function test_contact_and_support_pages_stay_separate(): void
    {
        config()->set('identity.use_accounts', true);

        $tenant = Tenant::create(['slug' => 'sep-pages-shop', 'name' => 'Sep Pages', 'status' => 'active']);

        $info = \App\Models\WebsiteInfo::withoutTenantScope()->where('tenant_id', $tenant->id)->first()
            ?? new \App\Models\WebsiteInfo();
        $info->tenant_id = $tenant->id;
        $info->site_name = 'Sep Pages';
        $info->contact_email = 'hello@sep-pages.test';
        $info->phone = '09555555555';
        $info->support_info = ['email' => 'help@sep-pages.test', 'phone' => '09666666666'];
        $info->save();

        $this->get("/store/{$tenant->slug}/contact")->assertInertia(fn ($page) => $page
            ->component('Storefront/Cms/Contact')
            ->where('contact.email', 'hello@sep-pages.test')
            ->missing('support')
        );

        $this->get("/store/{$tenant->slug}/support")->assertInertia(fn ($page) => $page
            ->component('Storefront/Cms/Support')
            ->where('support.email', 'help@sep-pages.test')
            ->where('support.phone', '09666666666')
            ->missing('contact')
        );
    }

    public function test_privacy_and_terms_pages_load(): void
    {
        $tenant = Tenant::create(['slug' => 'legal-shop', 'name' => 'Legal', 'status' => 'active']);

        foreach (['privacy-policy' => 'Privacy Policy', 'terms-and-conditions' => 'Terms & Conditions'] as $path => $title) {
            $this->get("/store/{$tenant->slug}/{$path}")->assertStatus(200)->assertInertia(fn ($page) => $page
                ->component('Storefront/Cms/Policy')
                ->where('page.title', $title)
            );
        }
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
