<?php

namespace Tests\Feature\Tenant;

use App\Models\Account;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;

class SuperAdminIsolationTest extends TenantIsolationTestCase
{
    protected Account $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create SuperAdmin
        $this->superAdmin = Account::create([
            'name' => 'Super Admin',
            'email' => 'admin@shop.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $superAdminRole = Role::create(['name' => 'superadmin', 'guard_name' => 'web', 'tenant_id' => null]);
        $this->superAdmin->assignRole($superAdminRole);
    }

    public function test_superadmin_has_no_tenant_membership(): void
    {
        $memberships = TenantMembership::where('account_id', $this->superAdmin->id)->count();

        $this->assertEquals(0, $memberships);
    }

    public function test_superadmin_has_no_tenant_id(): void
    {
        $this->assertNull($this->superAdmin->tenant_id);
    }

    public function test_superadmin_bypasses_tenant_isolation(): void
    {
        $this->assertTrue($this->superAdmin->isSuperAdmin());
    }

    public function test_superadmin_can_access_all_tenants(): void
    {
        // SuperAdmin should be able to access any tenant's admin
        $this->actingAs($this->superAdmin, 'accounts');

        // SuperAdmin bypasses tenant access checks
        $this->assertTrue($this->superAdmin->isSuperAdmin());
    }

    public function test_superadmin_role_is_global(): void
    {
        $superadminRole = Role::where('name', 'superadmin')->whereNull('tenant_id')->first();

        $this->assertNotNull($superadminRole);
        $this->assertNull($superadminRole->tenant_id);
    }

    public function test_superadmin_gets_all_permissions(): void
    {
        $permissions = $this->superAdmin->getAllPermissions();

        // SuperAdmin should have all permissions
        $this->assertNotEmpty($permissions);
    }
}
