<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Role;
use App\Models\StoreCategory;
use App\Models\Tenant;
use App\Models\TenantMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class StoreContextConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('identity.use_accounts', true);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function merchantTenant(string $slug, ?int $storeCategoryId = null): array
    {
        $tenant = Tenant::create([
            'name' => "Shop {$slug}", 'slug' => $slug, 'status' => 'active',
            'activated_at' => now(), 'store_category_id' => $storeCategoryId,
        ]);

        if (!Permission::where('name', 'settings.website')->exists()) {
            Permission::create(['name' => 'settings.website', 'guard_name' => 'web']);
        }
        $adminRole = Role::withoutTenantScope()->firstOrCreate([
            'name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id,
        ]);
        $adminRole->syncPermissions(Permission::all());

        $owner = Account::create([
            'name' => "Owner {$slug}", 'email' => "ctx-owner@test.com",
            'password' => bcrypt('password'), 'status' => 'active',
        ]);
        $owner->assignRole($adminRole);
        TenantMembership::create([
            'account_id' => $owner->id, 'tenant_id' => $tenant->id,
            'role_id' => $adminRole->id, 'is_owner' => true,
            'status' => 'active', 'joined_at' => now(),
        ]);

        return [$tenant, $owner];
    }

    private function multiStoreOwner(string $email, Tenant ...$tenants): Account
    {
        $owner = Account::create([
            'name' => 'Multi Owner', 'email' => $email,
            'password' => bcrypt('password'), 'status' => 'active',
        ]);

        foreach ($tenants as $tenant) {
            if (!Permission::where('name', 'settings.website')->exists()) {
                Permission::create(['name' => 'settings.website', 'guard_name' => 'web']);
            }
            $role = Role::withoutTenantScope()->firstOrCreate([
                'name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id,
            ]);
            $role->syncPermissions(Permission::all());
            $owner->assignRole($role);
            TenantMembership::create([
                'account_id' => $owner->id, 'tenant_id' => $tenant->id,
                'role_id' => $role->id, 'is_owner' => true,
                'status' => 'active', 'joined_at' => now(),
            ]);
        }

        app()->forgetInstance('current.tenant');

        return $owner;
    }

    private function makeTenant(string $slug, ?int $storeCategoryId = null): Tenant
    {
        return Tenant::create([
            'name' => "Shop {$slug}", 'slug' => $slug, 'status' => 'active',
            'activated_at' => now(), 'store_category_id' => $storeCategoryId,
        ]);
    }

    public function test_sidebar_prefers_explicit_store_scoped_urls(): void
    {
        $sidebar = (string) file_get_contents(base_path('resources/js/Components/AdminSidebar.jsx'));

        $this->assertStringContainsString('auth?.user?.tenant_slug', $sidebar);
        $this->assertStringContainsString('adminUrl(linkItem.href, navStoreSlug)', $sidebar);
    }

    public function test_settings_form_posts_to_explicit_store_url(): void
    {
        $source = (string) file_get_contents(base_path('resources/js/Pages/Admin/Settings/Edit.jsx'));

        $this->assertStringContainsString("adminUrl('/admin/website-info/edit', storeSlug)", $source);
    }

    public function test_workspace_switch_updates_context_and_settings_follow(): void
    {
        $category = StoreCategory::create(['name' => 'Fashion', 'slug' => 'fashion']);
        $tenantA = $this->makeTenant('switch-a');
        $tenantB = $this->makeTenant('switch-b', $category->id);
        $owner = $this->multiStoreOwner('switcher@test.com', $tenantA, $tenantB);

        $this->actingAs(Account::find($owner->id), 'accounts')
            ->post('/workspace/switch/switch-b')
            ->assertRedirect(route('admin.dashboard'));

        $props = $this->actingAs(Account::find($owner->id), 'accounts')
            ->withSession(['current_tenant_slug' => 'switch-b'])
            ->get('/admin/settings')
            ->viewData('page')['props'];

        $this->assertSame($category->id, $props['store_category_id']);

        $this->actingAs(Account::find($owner->id), 'accounts')
            ->put("/store/switch-b/admin/settings", ['store_category_id' => ''])
            ->assertSessionHasNoErrors();

        $this->assertNull($tenantB->fresh()->store_category_id);
        $this->assertNull($tenantA->fresh()->store_category_id);
    }

    public function test_workspace_switch_to_unowned_store_is_denied(): void
    {
        [$tenantA, $ownerA] = $this->merchantTenant('owned-a');
        $tenantB = $this->makeTenant('foreign-b');

        $this->actingAs(Account::find($ownerA->id), 'accounts')
            ->withSession(['current_tenant_slug' => 'owned-a'])
            ->post('/workspace/switch/foreign-b')
            ->assertForbidden();

        $props = $this->actingAs(Account::find($ownerA->id), 'accounts')
            ->withSession(['current_tenant_slug' => 'owned-a'])
            ->get('/admin/settings')
            ->viewData('page')['props'];

        $this->assertNull($props['store_category_id']);
    }

    public function test_guests_cannot_switch_workspaces(): void
    {
        $this->post('/workspace/switch/any-slug')->assertRedirect('/login');
    }

    public function test_category_update_after_switch_affects_only_selected_tenant(): void
    {
        $category = StoreCategory::create(['name' => 'Electronics', 'slug' => 'electronics']);
        $tenantA = $this->makeTenant('isolated-a');
        $tenantB = $this->makeTenant('isolated-b');
        $owner = $this->multiStoreOwner('isolated@test.com', $tenantA, $tenantB);

        $this->actingAs(Account::find($owner->id), 'accounts')
            ->withSession(['current_tenant_slug' => 'isolated-b'])
            ->put('/admin/website-info/edit', ['store_category_id' => $category->id])
            ->assertSessionHasNoErrors();

        $this->assertNull($tenantA->fresh()->store_category_id);
        $this->assertSame($category->id, $tenantB->fresh()->store_category_id);
    }
}
