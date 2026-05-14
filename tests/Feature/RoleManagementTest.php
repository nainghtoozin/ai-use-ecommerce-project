<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;
    private User $admin;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create all 34 permissions
        $permissions = [
            'users.view', 'users.create', 'users.update', 'users.delete', 'users.suspend',
            'users.ban', 'users.activate', 'users.assign-roles', 'users.view-activity',
            'roles.view', 'roles.create', 'roles.update', 'roles.delete', 'permissions.view',
            'products.view', 'products.create', 'products.update', 'products.delete',
            'categories.view', 'categories.create', 'categories.update', 'categories.delete',
            'orders.view', 'orders.view-own', 'orders.create', 'orders.update-status',
            'orders.cancel-own', 'orders.cancel-any',
            'payments.upload-proof', 'payments.view', 'payments.verify',
            'dashboard.view',
            'activity-logs.view',
        ];
        foreach ($permissions as $perm) {
            Permission::create(['name' => $perm, 'guard_name' => 'web']);
        }

        // Create roles
        $superadminRole = Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        $superadminRole->syncPermissions(Permission::all());

        $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $adminPermissionNames = [
            'users.view', 'users.create', 'users.update', 'users.delete',
            'users.suspend', 'users.ban', 'users.activate', 'users.assign-roles', 'users.view-activity',
            'roles.view', 'permissions.view',
            'products.view', 'products.create', 'products.update', 'products.delete',
            'categories.view', 'categories.create', 'categories.update', 'categories.delete',
            'orders.view', 'orders.update-status', 'orders.cancel-any',
            'payments.view', 'payments.verify',
            'dashboard.view',
            'activity-logs.view',
        ];
        $adminRole->syncPermissions(Permission::whereIn('name', $adminPermissionNames)->get());

        Role::create(['name' => 'customer', 'guard_name' => 'web']);

        // Create users
        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
        $this->superadmin->assignRole('superadmin');

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->assignRole('admin');

        $this->customer = User::factory()->create(['role' => 'customer']);
        $this->customer->assignRole('customer');
    }

    public function test_superadmin_can_view_roles_index(): void
    {
        $response = $this->actingAs($this->superadmin)->get('/admin/roles');

        $response->assertStatus(200);
        $response->assertSee('superadmin');
        $response->assertSee('admin');
        $response->assertSee('customer');
    }

    public function test_superadmin_can_view_role_create_page(): void
    {
        $response = $this->actingAs($this->superadmin)->get('/admin/roles/create');

        $response->assertStatus(200);
    }

    public function test_superadmin_can_create_role(): void
    {
        $response = $this->actingAs($this->superadmin)->post('/admin/roles', [
            'name' => 'editor',
            'permissions' => ['products.view', 'products.update', 'categories.view'],
        ]);

        $response->assertSessionHas('success');
        $response->assertRedirect('/admin/roles');

        $this->assertDatabaseHas('roles', ['name' => 'editor', 'guard_name' => 'web']);
        $role = Role::where('name', 'editor')->first();
        $this->assertTrue($role->hasPermissionTo('products.view'));
        $this->assertTrue($role->hasPermissionTo('products.update'));
        $this->assertTrue($role->hasPermissionTo('categories.view'));
        $this->assertFalse($role->hasPermissionTo('users.view'));
    }

    public function test_superadmin_can_assign_permissions_to_role(): void
    {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

        $response = $this->actingAs($this->superadmin)->put("/admin/roles/{$role->id}", [
            'name' => 'editor',
            'permissions' => ['users.view', 'users.create', 'products.view'],
        ]);

        $response->assertSessionHas('success');
        $response->assertRedirect('/admin/roles');

        $role->refresh();
        $this->assertTrue($role->hasPermissionTo('users.view'));
        $this->assertTrue($role->hasPermissionTo('users.create'));
        $this->assertTrue($role->hasPermissionTo('products.view'));
        $this->assertFalse($role->hasPermissionTo('users.update'));
    }

    public function test_superadmin_can_view_role_details(): void
    {
        $role = Role::where('name', 'admin')->first();

        $response = $this->actingAs($this->superadmin)->get("/admin/roles/{$role->id}");

        $response->assertStatus(200);
        $response->assertSee('admin');
    }

    public function test_superadmin_can_edit_role_page(): void
    {
        $role = Role::where('name', 'admin')->first();

        $response = $this->actingAs($this->superadmin)->get("/admin/roles/{$role->id}/edit");

        $response->assertStatus(200);
    }

    public function test_superadmin_cannot_delete_superadmin_role(): void
    {
        $role = Role::where('name', 'superadmin')->first();

        $response = $this->actingAs($this->superadmin)->delete("/admin/roles/{$role->id}");

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('roles', ['name' => 'superadmin']);
    }

    public function test_superadmin_cannot_delete_customer_role(): void
    {
        $role = Role::where('name', 'customer')->first();

        $response = $this->actingAs($this->superadmin)->delete("/admin/roles/{$role->id}");

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('roles', ['name' => 'customer']);
    }

    public function test_superadmin_cannot_delete_role_with_users(): void
    {
        $role = Role::create(['name' => 'test-role', 'guard_name' => 'web']);
        $user = User::factory()->create(['role' => 'test-role']);
        $user->assignRole('test-role');

        $response = $this->actingAs($this->superadmin)->delete("/admin/roles/{$role->id}");

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('roles', ['name' => 'test-role']);
    }

    public function test_superadmin_can_delete_unused_role(): void
    {
        $role = Role::create(['name' => 'temp-role', 'guard_name' => 'web']);

        $response = $this->actingAs($this->superadmin)->delete("/admin/roles/{$role->id}");

        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('roles', ['name' => 'temp-role']);
    }

    public function test_superadmin_cannot_remove_all_permissions_from_superadmin_role(): void
    {
        $role = Role::where('name', 'superadmin')->first();

        $response = $this->actingAs($this->superadmin)->put("/admin/roles/{$role->id}", [
            'name' => 'superadmin',
            'permissions' => [],
        ]);

        $response->assertSessionHas('error');
        $role->refresh();
        $this->assertGreaterThan(0, $role->permissions()->count());
    }

    public function test_admin_with_roles_view_can_view_roles(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/roles');

        $response->assertStatus(200);
    }

    public function test_admin_without_roles_create_cannot_access_create_page(): void
    {
        // Admin doesn't have roles.create permission by default
        $response = $this->actingAs($this->admin)->get('/admin/roles/create');

        $response->assertStatus(403);
    }

    public function test_customer_cannot_access_roles(): void
    {
        $response = $this->actingAs($this->customer)->get('/admin/roles');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_roles(): void
    {
        $response = $this->get('/admin/roles');

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_role_name_must_be_unique(): void
    {
        $response = $this->actingAs($this->superadmin)->post('/admin/roles', [
            'name' => 'superadmin',
            'permissions' => ['dashboard.view'],
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_role_update_validates_unique_name(): void
    {
        $role = Role::where('name', 'admin')->first();

        $response = $this->actingAs($this->superadmin)->put("/admin/roles/{$role->id}", [
            'name' => 'customer',
            'permissions' => ['dashboard.view'],
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_invalid_permissions_are_rejected(): void
    {
        $response = $this->actingAs($this->superadmin)->post('/admin/roles', [
            'name' => 'hacker',
            'permissions' => ['nonexistent.permission'],
        ]);

        $response->assertSessionHasErrors('permissions.0');
    }

    public function test_superadmin_can_view_permissions_index(): void
    {
        $response = $this->actingAs($this->superadmin)->get('/admin/permissions');

        $response->assertStatus(200);
    }

    public function test_user_without_permissions_view_cannot_access_permissions_page(): void
    {
        $restrictedRole = Role::create(['name' => 'restricted', 'guard_name' => 'web']);
        $restrictedRole->syncPermissions(['dashboard.view']);
        $restrictedUser = User::factory()->create(['role' => 'restricted']);
        $restrictedUser->assignRole('restricted');

        $response = $this->actingAs($restrictedUser)->get('/admin/permissions');

        $response->assertStatus(403);
    }
}
