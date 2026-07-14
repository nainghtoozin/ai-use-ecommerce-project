<?php

namespace Tests\Feature\Tenant;

use App\Models\Account;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TenantIsolationTestCase extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected Account $ownerA;
    protected Account $ownerB;
    protected Account $customerA;
    protected Account $customerB;
    protected Role $adminRoleA;
    protected Role $adminRoleB;
    protected Role $customerRoleA;
    protected Role $customerRoleB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedMinimalData();
    }

    protected function seedMinimalData(): void
    {
        // Create tenants
        $this->tenantA = Tenant::create([
            'name' => 'Store A',
            'slug' => 'store-a',
            'status' => 'active',
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Store B',
            'slug' => 'store-b',
            'status' => 'active',
        ]);

        // Create roles per tenant
        $this->adminRoleA = Role::create(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $this->tenantA->id]);
        $this->adminRoleB = Role::create(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $this->tenantB->id]);
        $this->customerRoleA = Role::create(['name' => 'customer', 'guard_name' => 'web', 'tenant_id' => $this->tenantA->id]);
        $this->customerRoleB = Role::create(['name' => 'customer', 'guard_name' => 'web', 'tenant_id' => $this->tenantB->id]);

        // Create accounts
        $this->ownerA = Account::create(['name' => 'Owner A', 'email' => 'owner-a@test.com', 'password' => bcrypt('password'), 'status' => 'active']);
        $this->ownerB = Account::create(['name' => 'Owner B', 'email' => 'owner-b@test.com', 'password' => bcrypt('password'), 'status' => 'active']);
        $this->customerA = Account::create(['name' => 'Customer A', 'email' => 'customer-a@test.com', 'password' => bcrypt('password'), 'status' => 'active']);
        $this->customerB = Account::create(['name' => 'Customer B', 'email' => 'customer-b@test.com', 'password' => bcrypt('password'), 'status' => 'active']);

        // Create memberships
        TenantMembership::create(['account_id' => $this->ownerA->id, 'tenant_id' => $this->tenantA->id, 'role_id' => $this->adminRoleA->id, 'is_owner' => true, 'status' => 'active', 'joined_at' => now()]);
        TenantMembership::create(['account_id' => $this->ownerB->id, 'tenant_id' => $this->tenantB->id, 'role_id' => $this->adminRoleB->id, 'is_owner' => true, 'status' => 'active', 'joined_at' => now()]);
        TenantMembership::create(['account_id' => $this->customerA->id, 'tenant_id' => $this->tenantA->id, 'role_id' => $this->customerRoleA->id, 'is_owner' => false, 'status' => 'active', 'joined_at' => now()]);
        TenantMembership::create(['account_id' => $this->customerB->id, 'tenant_id' => $this->tenantB->id, 'role_id' => $this->customerRoleB->id, 'is_owner' => false, 'status' => 'active', 'joined_at' => now()]);
    }

    protected function actingAsOwnerA(): static
    {
        $this->actingAs($this->ownerA, 'accounts');
        app()->instance('current.tenant', $this->tenantA);
        return $this;
    }

    protected function actingAsOwnerB(): static
    {
        $this->actingAs($this->ownerB, 'accounts');
        app()->instance('current.tenant', $this->tenantB);
        return $this;
    }

    protected function actingAsCustomerA(): static
    {
        $this->actingAs($this->customerA, 'accounts');
        app()->instance('current.tenant', $this->tenantA);
        return $this;
    }

    protected function actingAsCustomerB(): static
    {
        $this->actingAs($this->customerB, 'accounts');
        app()->instance('current.tenant', $this->tenantB);
        return $this;
    }
}
