<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Role;
use App\Models\TeamInvitation;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('identity.use_accounts', true);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function passwordRuleFails(string $password): bool
    {
        return Validator::make(
            ['password' => $password],
            ['password' => Password::defaults()]
        )->fails();
    }

    private function makeTenant(string $slug): Tenant
    {
        return Tenant::create([
            'name' => "Shop {$slug}", 'slug' => $slug, 'status' => 'active', 'activated_at' => now(),
        ]);
    }

    private function makeOwner(Tenant $tenant, string $email, array $permissions = []): Account
    {
        $role = Role::withoutTenantScope()->firstOrCreate([
            'name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id,
        ]);

        if ($permissions) {
            foreach ($permissions as $name) {
                Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            }
            $role->syncPermissions(Permission::whereIn('name', $permissions)->get());
        }

        $owner = Account::create([
            'name' => 'Owner', 'email' => $email, 'password' => bcrypt('Password1'), 'status' => 'active',
        ]);
        $owner->assignRole($role);
        TenantMembership::create([
            'account_id' => $owner->id, 'tenant_id' => $tenant->id, 'role_id' => $role->id,
            'is_owner' => true, 'status' => 'active', 'joined_at' => now(),
        ]);

        return $owner;
    }

    public function test_shared_password_rule_matches_required_policy(): void
    {
        $this->assertTrue($this->passwordRuleFails('abcdefgh'), 'abcdefgh must be rejected');
        $this->assertTrue($this->passwordRuleFails('Abcdefgh'), 'Abcdefgh must be rejected');
        $this->assertFalse($this->passwordRuleFails('Abcdefg1'), 'Abcdefg1 must be accepted');
        $this->assertTrue($this->passwordRuleFails('ABCDEFG1'), 'ABCDEFG1 must be rejected');
        $this->assertTrue($this->passwordRuleFails('abc1'), 'Short password must be rejected');
    }

    public function test_registration_enforces_policy(): void
    {
        foreach (['abcdefgh', 'Abcdefgh', 'ABCDEFG1'] as $weak) {
            $this->post('/register', [
                'name' => 'Weak User',
                'email' => 'weak-' . uniqid() . '@example.com',
                'password' => $weak,
                'password_confirmation' => $weak,
            ])->assertSessionHasErrors('password');
        }

        $this->post('/register', [
            'name' => 'Strong User',
            'email' => 'strong@example.com',
            'password' => 'Abcdefg1',
            'password_confirmation' => 'Abcdefg1',
        ])->assertSessionDoesntHaveErrors('password');

        $this->assertDatabaseHas('accounts', ['email' => 'strong@example.com']);
    }

    public function test_registration_rejects_confirmation_mismatch(): void
    {
        $this->post('/register', [
            'name' => 'Mismatch User',
            'email' => 'mismatch@example.com',
            'password' => 'Abcdefg1',
            'password_confirmation' => 'Abcdefg2',
        ])->assertSessionHasErrors('password');
    }

    public function test_password_reset_enforces_policy(): void
    {
        $this->post('/reset-password', [
            'token' => 'invalid-token',
            'email' => 'someone@example.com',
            'password' => 'Abcdefgh',
            'password_confirmation' => 'Abcdefgh',
        ])->assertSessionHasErrors('password');
    }

    public function test_authenticated_password_change_enforces_policy(): void
    {
        $user = User::create([
            'name' => 'Changer', 'email' => 'changer@example.com',
            'password' => bcrypt('Password1'), 'status' => 'active',
        ]);

        $this->actingAs($user, 'web')
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'Password1',
                'password' => 'abcdefgh',
                'password_confirmation' => 'abcdefgh',
            ])
            ->assertSessionHasErrorsIn('updatePassword', 'password');

        $this->actingAs($user, 'web')
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'Password1',
                'password' => 'Abcdefg1',
                'password_confirmation' => 'Abcdefg1',
            ])
            ->assertSessionDoesntHaveErrors();

        $this->assertTrue(Hash::check('Abcdefg1', $user->fresh()->password));
    }

    public function test_create_store_enforces_policy(): void
    {
        $this->post('/create-store', [
            'name' => 'Weak Store',
            'slug' => 'weak-store-' . uniqid(),
            'owner_name' => 'Owner',
            'owner_email' => 'owner-weak@example.com',
            'password' => 'abcdefgh',
            'password_confirmation' => 'abcdefgh',
        ])->assertSessionHasErrors('password');
    }

    public function test_admin_user_creation_enforces_policy(): void
    {
        $tenant = $this->makeTenant('policy-admin-shop');
        $owner = $this->makeOwner($tenant, 'owner-admin@example.com', ['users.create']);

        app()->instance('current.tenant', $tenant);

        $this->actingAs($owner, 'accounts')
            ->post("/store/{$tenant->slug}/admin/users", [
                'name' => 'Staff Member',
                'email' => 'staff-weak@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'staff',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_superadmin_merchant_creation_enforces_policy(): void
    {
        $role = Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        $superadmin = User::create([
            'name' => 'Super', 'email' => 'super-policy@example.com',
            'password' => bcrypt('Password1'), 'status' => 'active',
        ]);
        $superadmin->assignRole($role);

        $this->actingAs($superadmin, 'web')
            ->post('/superadmin/tenants', [
                'name' => 'New Merchant',
                'slug' => 'new-merchant-' . uniqid(),
                'status' => 'active',
                'create_admin' => '1',
                'admin_name' => 'Merchant Admin',
                'admin_email' => 'merchant-admin-weak@example.com',
                'admin_password' => 'password123',
            ])
            ->assertSessionHasErrors('admin_password');
    }

    public function test_team_invitation_acceptance_enforces_policy(): void
    {
        $tenant = $this->makeTenant('policy-invite-shop');
        $role = Role::withoutTenantScope()->create([
            'name' => 'staff', 'guard_name' => 'web', 'tenant_id' => $tenant->id,
        ]);
        $inviter = Account::create([
            'name' => 'Inviter', 'email' => 'inviter-policy@example.com',
            'password' => bcrypt('Password1'), 'status' => 'active',
        ]);

        $invitation = TeamInvitation::create([
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
            'invited_by' => $inviter->id,
            'email' => 'invitee-weak@example.com',
            'token' => 'token-' . uniqid(),
            'status' => 'pending',
            'invited_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);

        $this->post("/store/{$tenant->slug}/team/invite/{$invitation->token}", [
            'name' => 'Invitee',
            'password' => 'abcdefgh',
            'password_confirmation' => 'abcdefgh',
        ])->assertSessionHasErrors('password');
    }

    public function test_storefront_customer_password_update_enforces_policy(): void
    {
        $tenant = $this->makeTenant('policy-customer-shop');
        $customerRole = Role::withoutTenantScope()->create([
            'name' => 'customer', 'guard_name' => 'web', 'tenant_id' => $tenant->id,
        ]);
        $customer = Account::create([
            'name' => 'Customer', 'email' => 'customer-policy@example.com',
            'password' => bcrypt('Password1'), 'status' => 'active',
        ]);
        TenantMembership::create([
            'account_id' => $customer->id, 'tenant_id' => $tenant->id,
            'role_id' => $customerRole->id, 'is_owner' => false, 'status' => 'active', 'joined_at' => now(),
        ]);

        $this->actingAs($customer, 'accounts')
            ->put("/store/{$tenant->slug}/customer/profile/password", [
                'current_password' => 'Password1',
                'password' => 'abcdefgh',
                'password_confirmation' => 'abcdefgh',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_password_forms_gate_submission_on_policy(): void
    {
        $register = (string) file_get_contents(base_path('resources/js/Pages/Auth/Register.jsx'));
        $this->assertStringContainsString('getPasswordChecks', $register);
        $this->assertStringContainsString('passwordMeetsPolicy', $register);
        $this->assertStringContainsString('Passwords do not match.', $register);
        $this->assertStringContainsString('disabled={!canSubmit}', $register);

        $profile = (string) file_get_contents(base_path('resources/js/Pages/Admin/Profile.jsx'));
        $this->assertStringContainsString('getPasswordChecks', $profile);
        $this->assertStringContainsString('passwordMeetsPolicy', $profile);
        $this->assertStringContainsString('Passwords do not match.', $profile);
    }

    public function test_login_still_works_with_legacy_weak_password(): void
    {
        $tenant = $this->makeTenant('policy-login-shop');
        $customerRole = Role::withoutTenantScope()->create([
            'name' => 'customer', 'guard_name' => 'web', 'tenant_id' => $tenant->id,
        ]);
        $customer = Account::create([
            'name' => 'Legacy Customer', 'email' => 'legacy-login@example.com',
            'password' => bcrypt('legacy'),
            'status' => 'active',
        ]);
        TenantMembership::create([
            'account_id' => $customer->id, 'tenant_id' => $tenant->id,
            'role_id' => $customerRole->id, 'is_owner' => false, 'status' => 'active', 'joined_at' => now(),
        ]);

        $this->post("/store/{$tenant->slug}/login", [
            'email' => 'legacy-login@example.com',
            'password' => 'legacy',
        ])->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($customer, 'accounts');
    }
}
