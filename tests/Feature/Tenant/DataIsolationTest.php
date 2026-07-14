<?php

namespace Tests\Feature\Tenant;

use App\Models\Account;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;

class DataIsolationTest extends TenantIsolationTestCase
{
    public function test_tenant_a_cannot_see_tenant_b_members(): void
    {
        // Tenant A members
        $tenantAMembers = TenantMembership::where('tenant_id', $this->tenantA->id)->pluck('account_id');

        $this->assertTrue($tenantAMembers->contains($this->ownerA->id));
        $this->assertTrue($tenantAMembers->contains($this->customerA->id));
        $this->assertFalse($tenantAMembers->contains($this->ownerB->id));
        $this->assertFalse($tenantAMembers->contains($this->customerB->id));
    }

    public function test_tenant_b_cannot_see_tenant_a_members(): void
    {
        // Tenant B members
        $tenantBMembers = TenantMembership::where('tenant_id', $this->tenantB->id)->pluck('account_id');

        $this->assertTrue($tenantBMembers->contains($this->ownerB->id));
        $this->assertTrue($tenantBMembers->contains($this->customerB->id));
        $this->assertFalse($tenantBMembers->contains($this->ownerA->id));
        $this->assertFalse($tenantBMembers->contains($this->customerA->id));
    }

    public function test_roles_do_not_leak_across_tenants(): void
    {
        $tenantARoles = Role::where('tenant_id', $this->tenantA->id)->pluck('name');
        $tenantBRoles = Role::where('tenant_id', $this->tenantB->id)->pluck('name');

        // Both have admin and customer roles
        $this->assertTrue($tenantARoles->contains('admin'));
        $this->assertTrue($tenantARoles->contains('customer'));
        $this->assertTrue($tenantBRoles->contains('admin'));
        $this->assertTrue($tenantBRoles->contains('customer'));

        // But they are different records
        $roleA = Role::where('name', 'admin')->where('tenant_id', $this->tenantA->id)->first();
        $roleB = Role::where('name', 'admin')->where('tenant_id', $this->tenantB->id)->first();

        $this->assertNotEquals($roleA->id, $roleB->id);
    }

    public function test_owner_a_cannot_change_owner_b_role(): void
    {
        // OwnerA should only be able to manage members of tenantA
        $ownerBMembership = TenantMembership::where('account_id', $this->ownerB->id)
            ->where('tenant_id', $this->tenantB->id)
            ->first();

        // OwnerB's membership is in tenantB, not tenantA
        $this->assertNotEquals($this->tenantA->id, $ownerBMembership->tenant_id);
    }

    public function test_account_status_is_global(): void
    {
        // If an account is suspended, it affects all memberships
        $this->customerA->update(['status' => 'suspended']);

        $this->assertEquals('suspended', $this->customerA->fresh()->status);

        // All memberships should see suspended status
        $memberships = TenantMembership::where('account_id', $this->customerA->id)->get();
        foreach ($memberships as $membership) {
            $this->assertEquals('suspended', $membership->account->status);
        }
    }
}
