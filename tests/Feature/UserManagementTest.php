<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

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

        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
        $this->superadmin->assignRole('superadmin');
    }

    public function test_create_page_passes_all_roles(): void
    {
        $response = $this->actingAs($this->superadmin)->get('/admin/users/create');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Users/Create')
            ->has('roles', 3)
            ->where('roles.0', 'admin')
            ->where('roles.1', 'customer')
            ->where('roles.2', 'superadmin')
        );
    }

    public function test_edit_page_passes_all_roles_and_user_role(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $user->assignRole('customer');

        $response = $this->actingAs($this->superadmin)->get("/admin/users/{$user->id}/edit");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Users/Edit')
            ->has('roles', 3)
            ->has('user')
        );
    }

    public function test_user_can_be_assigned_any_existing_role(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $user->assignRole('customer');

        $response = $this->actingAs($this->superadmin)->put("/admin/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'admin',
            'status' => 'active',
        ]);

        $response->assertRedirect();
        $user->refresh();
        $this->assertTrue($user->hasRole('admin'));
        $this->assertEquals('admin', $user->role);
    }

    public function test_syncRoles_replaces_previous_role(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $user->assignRole('customer');

        $this->assertTrue($user->hasRole('customer'));

        $user->syncRoles(['admin']);
        $user->update(['role' => 'admin']);
        $user->refresh();

        $this->assertTrue($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('customer'));
        $this->assertCount(1, $user->roles);
    }

    public function test_only_one_role_is_assigned(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $user->assignRole('customer');

        $user->syncRoles(['admin']);
        $user->refresh();

        $this->assertCount(1, $user->roles);
    }

    public function test_user_created_with_syncRoles_gets_single_role(): void
    {
        $response = $this->actingAs($this->superadmin)->post('/admin/users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $response->assertRedirect();

        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('admin'));
        $this->assertCount(1, $user->roles);
        $this->assertEquals('admin', $user->role);
    }

    public function test_last_superadmin_cannot_be_demoted(): void
    {
        $response = $this->actingAs($this->superadmin)->put("/admin/users/{$this->superadmin->id}", [
            'name' => $this->superadmin->name,
            'email' => $this->superadmin->email,
            'role' => 'customer',
            'status' => 'active',
        ]);

        $response->assertRedirect()->assertSessionHas('error', 'Cannot remove the last remaining superadmin.');
        $this->superadmin->refresh();
        $this->assertTrue($this->superadmin->hasRole('superadmin'));
    }

    public function test_last_superadmin_cannot_be_deleted(): void
    {
        $response = $this->actingAs($this->superadmin)->delete("/admin/users/{$this->superadmin->id}");

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $this->superadmin->id]);
    }

    public function test_role_validation_rejects_nonexistent_role(): void
    {
        $response = $this->actingAs($this->superadmin)->post('/admin/users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'nonexistent-role',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('role');
    }

    public function test_role_is_required_when_creating_user(): void
    {
        $response = $this->actingAs($this->superadmin)->post('/admin/users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('role');
    }

    public function test_index_page_passes_roles(): void
    {
        $response = $this->actingAs($this->superadmin)->get('/admin/users');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Users/Index')
            ->has('roles', 3)
        );
    }
}
