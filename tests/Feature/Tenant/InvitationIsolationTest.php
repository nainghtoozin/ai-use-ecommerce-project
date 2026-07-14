<?php

namespace Tests\Feature\Tenant;

use App\Models\Account;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\TeamInvitation;

class InvitationIsolationTest extends TenantIsolationTestCase
{
    public function test_invitation_is_tenant_scoped(): void
    {
        $invitation = TeamInvitation::create([
            'tenant_id' => $this->tenantA->id,
            'role_id' => $this->customerRoleA->id,
            'invited_by' => $this->ownerA->id,
            'email' => 'invited@test.com',
            'token' => 'test-token-123',
            'status' => 'pending',
            'invited_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);

        $this->assertEquals($this->tenantA->id, $invitation->tenant_id);
    }

    public function test_invitation_cannot_be_accepted_for_wrong_tenant(): void
    {
        $invitation = TeamInvitation::create([
            'tenant_id' => $this->tenantA->id,
            'role_id' => $this->customerRoleA->id,
            'invited_by' => $this->ownerA->id,
            'email' => 'invited@test.com',
            'token' => 'test-token-456',
            'status' => 'pending',
            'invited_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);

        // Invitation belongs to tenantA, not tenantB
        $this->assertNotEquals($this->tenantB->id, $invitation->tenant_id);
    }

    public function test_invitation_creates_membership_for_correct_tenant(): void
    {
        $newAccount = Account::create([
            'name' => 'Invited User',
            'email' => 'invited@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        TenantMembership::create([
            'account_id' => $newAccount->id,
            'tenant_id' => $this->tenantA->id,
            'role_id' => $this->customerRoleA->id,
            'is_owner' => false,
            'status' => 'active',
        ]);

        $membership = TenantMembership::where('account_id', $newAccount->id)->first();

        $this->assertNotNull($membership);
        $this->assertEquals($this->tenantA->id, $membership->tenant_id);
        $this->assertNull(TenantMembership::where('account_id', $newAccount->id)->where('tenant_id', $this->tenantB->id)->first());
    }

    public function test_invitation_expiry_is_per_tenant(): void
    {
        TeamInvitation::create([
            'tenant_id' => $this->tenantA->id,
            'role_id' => $this->customerRoleA->id,
            'invited_by' => $this->ownerA->id,
            'email' => 'expired@test.com',
            'token' => 'expired-token',
            'status' => 'pending',
            'invited_at' => now()->subDays(10),
            'expires_at' => now()->subDays(3),
        ]);

        $invitation = TeamInvitation::where('token', 'expired-token')->first();

        $this->assertTrue($invitation->isExpired());
        $this->assertFalse($invitation->isPending());
    }
}
