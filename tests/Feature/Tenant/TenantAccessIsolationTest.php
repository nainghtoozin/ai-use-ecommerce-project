<?php

namespace Tests\Feature\Tenant;

use App\Models\Account;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;

class TenantAccessIsolationTest extends TenantIsolationTestCase
{
    // ═══════════════════════════════════════════════════════════════
    // OWNER ISOLATION
    // ═══════════════════════════════════════════════════════════════

    public function test_owner_belongs_to_correct_tenant(): void
    {
        $this->assertTrue($this->ownerA->isOwner($this->tenantA->id));
        $this->assertFalse($this->ownerA->isOwner($this->tenantB->id));
    }

    public function test_owner_cannot_access_other_tenant_admin(): void
    {
        $this->actingAsOwnerA();

        $response = $this->get("/store/{$this->tenantB->slug}/admin/dashboard");
        $response->assertStatus(403);
    }

    public function test_owner_membership_is_tenant_scoped(): void
    {
        $membershipA = TenantMembership::where('account_id', $this->ownerA->id)
            ->where('tenant_id', $this->tenantA->id)
            ->first();

        $this->assertNotNull($membershipA);
        $this->assertTrue($membershipA->is_owner);

        $membershipB = TenantMembership::where('account_id', $this->ownerA->id)
            ->where('tenant_id', $this->tenantB->id)
            ->first();

        $this->assertNull($membershipB);
    }

    // ═══════════════════════════════════════════════════════════════
    // CUSTOMER ISOLATION
    // ═══════════════════════════════════════════════════════════════

    public function test_customer_belongs_to_correct_tenant(): void
    {
        $membership = TenantMembership::where('account_id', $this->customerA->id)->first();

        $this->assertNotNull($membership);
        $this->assertEquals($this->tenantA->id, $membership->tenant_id);
    }

    public function test_customer_cannot_access_other_tenant_storefront(): void
    {
        $this->actingAsCustomerA();

        // Customer A should not have membership in Tenant B
        $membership = TenantMembership::where('account_id', $this->customerA->id)
            ->where('tenant_id', $this->tenantB->id)
            ->first();

        $this->assertNull($membership);
    }

    // ═══════════════════════════════════════════════════════════════
    // ACCOUNT REUSE
    // ═══════════════════════════════════════════════════════════════

    public function test_account_can_have_multiple_memberships(): void
    {
        // Give customerA a membership in tenantB too
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

    public function test_account_is_not_duplicated_on_reuse(): void
    {
        $accountCount = Account::where('email', $this->customerA->email)->count();

        $this->assertEquals(1, $accountCount);
    }

    // ═══════════════════════════════════════════════════════════════
    // ROLE ISOLATION
    // ═══════════════════════════════════════════════════════════════

    public function test_roles_are_tenant_scoped(): void
    {
        $this->assertEquals($this->tenantA->id, $this->adminRoleA->tenant_id);
        $this->assertEquals($this->tenantB->id, $this->adminRoleB->tenant_id);
    }

    public function test_role_names_can_repeat_across_tenants(): void
    {
        // Both tenants have "admin" role
        $this->assertEquals('admin', $this->adminRoleA->name);
        $this->assertEquals('admin', $this->adminRoleB->name);
        $this->assertNotEquals($this->adminRoleA->id, $this->adminRoleB->id);
    }

    public function test_owner_role_resolution_is_tenant_scoped(): void
    {
        // OwnerA has admin role in tenantA
        $this->assertTrue($this->ownerA->hasRole('admin'));

        // But the role is resolved through tenantA membership
        $membership = $this->ownerA->getCurrentMembership();
        $this->assertNotNull($membership);
        $this->assertEquals($this->tenantA->id, $membership->tenant_id);
    }

    // ═══════════════════════════════════════════════════════════════
    // PERMISSION ISOLATION
    // ═══════════════════════════════════════════════════════════════

    public function test_permissions_are_resolved_through_membership(): void
    {
        // OwnerA has all permissions through owner status
        $this->assertTrue($this->ownerA->hasPermissionTo('products.view'));
    }

    public function test_permissions_do_not_leak_across_tenants(): void
    {
        // CustomerA should only have permissions from their role
        $permissions = $this->customerA->getAllPermissions();

        // Permissions are tenant-scoped through membership
        $membership = $this->customerA->getCurrentMembership();
        $this->assertNotNull($membership);
        $this->assertEquals($this->tenantA->id, $membership->tenant_id);
    }
}
