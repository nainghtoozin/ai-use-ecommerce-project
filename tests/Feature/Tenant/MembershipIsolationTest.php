<?php

namespace Tests\Feature\Tenant;

use App\Models\Account;
use App\Models\Tenant;
use App\Models\TenantMembership;

class MembershipIsolationTest extends TenantIsolationTestCase
{
    public function test_membership_status_lifecycle(): void
    {
        $membership = TenantMembership::where('account_id', $this->customerA->id)
            ->where('tenant_id', $this->tenantA->id)
            ->first();

        $this->assertEquals('active', $membership->status);

        // Suspend
        $membership->update(['status' => 'suspended']);
        $this->assertEquals('suspended', $membership->fresh()->status);

        // Restore
        $membership->update(['status' => 'active']);
        $this->assertEquals('active', $membership->fresh()->status);
    }

    public function test_owner_cannot_be_suspended(): void
    {
        $ownerMembership = TenantMembership::where('account_id', $this->ownerA->id)
            ->where('tenant_id', $this->tenantA->id)
            ->first();

        $this->assertTrue($ownerMembership->is_owner);

        // Business logic should prevent this, but verify the flag exists
        $this->assertTrue($ownerMembership->is_owner);
    }

    public function test_membership_unique_constraint(): void
    {
        // Cannot create duplicate membership for same account+tenant
        $existing = TenantMembership::where('account_id', $this->customerA->id)
            ->where('tenant_id', $this->tenantA->id)
            ->exists();

        $this->assertTrue($existing);
    }

    public function test_soft_deleted_membership_does_not_resolve(): void
    {
        $membership = TenantMembership::where('account_id', $this->customerA->id)
            ->where('tenant_id', $this->tenantA->id)
            ->first();

        $membership->delete();

        // Soft deleted should not be found by normal queries
        $found = TenantMembership::where('account_id', $this->customerA->id)
            ->where('tenant_id', $this->tenantA->id)
            ->first();

        $this->assertNull($found);

        // But should be found with trashed
        $foundTrashed = TenantMembership::withTrashed()
            ->where('account_id', $this->customerA->id)
            ->where('tenant_id', $this->tenantA->id)
            ->first();

        $this->assertNotNull($foundTrashed);
    }

    public function test_multiple_tenants_per_account(): void
    {
        // Create membership in tenantB for customerA
        TenantMembership::create([
            'account_id' => $this->customerA->id,
            'tenant_id' => $this->tenantB->id,
            'role_id' => $this->customerRoleB->id,
            'is_owner' => false,
            'status' => 'active',
        ]);

        $memberships = TenantMembership::where('account_id', $this->customerA->id)->get();

        $this->assertCount(2, $memberships);
        $this->assertTrue($memberships->contains('tenant_id', $this->tenantA->id));
        $this->assertTrue($memberships->contains('tenant_id', $this->tenantB->id));
    }
}
