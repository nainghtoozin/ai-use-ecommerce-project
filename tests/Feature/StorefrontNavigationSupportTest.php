<?php

namespace Tests\Feature;

use App\Http\Requests\UpdateStorefrontNavigationRequest;
use App\Models\Account;
use App\Models\Role;
use App\Models\Storefront;
use App\Models\StorefrontNavigation;
use App\Models\StorefrontNavigationItem;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Services\StorefrontConfigurationResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class StorefrontNavigationSupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_path_is_allowed(): void
    {
        $this->assertContains('/support', UpdateStorefrontNavigationRequest::allowedPaths());
    }

    public function test_ensure_adds_missing_support_without_touching_others(): void
    {
        [$tenant, $navigation] = $this->seedNavigation('nav-shop-a');

        StorefrontConfigurationResolver::ensureSupportItem($navigation);

        $items = $this->headerItems($navigation);
        $this->assertEquals(['/', '/products', '/contact', '/support', '/customer/orders'], $items->pluck('path')->all());
        $this->assertEquals(['Home', 'Products', 'Contact', 'Help & Support', 'My Orders'], $items->pluck('label')->all());
        $this->assertEquals([0, 1, 2, 3, 4], $items->pluck('position')->all());
        $this->assertTrue($items->every(fn ($i) => (bool) $i->enabled));
        $support = $items->firstWhere('key', 'support');
        $this->assertEquals('bi-life-preserver', $support->icon);
        $this->assertEquals($tenant->id, (int) $support->tenant_id);
    }

    public function test_ensure_is_idempotent(): void
    {
        [$tenant, $navigation] = $this->seedNavigation('nav-shop-b');

        StorefrontConfigurationResolver::ensureSupportItem($navigation);
        StorefrontConfigurationResolver::ensureSupportItem($navigation);

        $this->assertEquals(1, StorefrontNavigationItem::withoutTenantScope()
            ->where('navigation_id', $navigation->id)
            ->where('key', 'support')
            ->count());
        $this->assertEquals(5, StorefrontNavigationItem::withoutTenantScope()
            ->where('navigation_id', $navigation->id)
            ->where('group', 'header')
            ->count());
    }

    public function test_ensure_respects_tenant_isolation(): void
    {
        [$tenantA, $navA] = $this->seedNavigation('nav-shop-c');
        [$tenantB, $navB] = $this->seedNavigation('nav-shop-d');

        StorefrontConfigurationResolver::ensureSupportItem($navA);

        $this->assertTrue(StorefrontNavigationItem::withoutTenantScope()
            ->where('navigation_id', $navA->id)->where('key', 'support')->exists());
        $this->assertFalse(StorefrontNavigationItem::withoutTenantScope()
            ->where('navigation_id', $navB->id)->where('key', 'support')->exists());
        $this->assertEquals(
            ['/', '/products', '/contact', '/customer/orders'],
            $this->headerItems($navB)->pluck('path')->all()
        );
    }

    public function test_admin_can_save_support_path(): void
    {
        if (!Permission::where('name', 'settings.website')->exists()) {
            Permission::create(['name' => 'settings.website', 'guard_name' => 'web']);
        }
        config()->set('identity.use_accounts', true);

        $tenant = Tenant::create(['slug' => 'nav-admin-shop', 'name' => 'Nav Admin', 'status' => 'active']);
        $adminRole = Role::withoutTenantScope()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $adminRole->syncPermissions(Permission::all());
        $owner = Account::create(['name' => 'Owner', 'email' => 'owner@nav-admin.test', 'password' => bcrypt('password'), 'status' => 'active']);
        TenantMembership::create(['account_id' => $owner->id, 'tenant_id' => $tenant->id, 'role_id' => $adminRole->id, 'is_owner' => true, 'status' => 'active', 'joined_at' => now()]);

        Storefront::withoutTenantScope()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        [$_, $navigation] = $this->seedNavigation('nav-admin-shop', $tenant);
        StorefrontConfigurationResolver::ensureSupportItem($navigation->fresh());

        $items = $this->headerItems($navigation);

        $response = $this->actingAs($owner, 'accounts')->put("/store/{$tenant->slug}/admin/storefront/navigation", [
            'show_store_name' => true,
            'show_search' => true,
            'items' => $items->map(fn ($i) => [
                'id' => $i->id,
                'key' => $i->key,
                'label' => $i->label,
                'path' => $i->path,
                'enabled' => true,
                'position' => $i->position,
                'group' => 'header',
            ])->values()->all(),
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertTrue(StorefrontNavigationItem::withoutTenantScope()
            ->where('navigation_id', $navigation->id)
            ->where('key', 'support')
            ->where('path', '/support')
            ->where('enabled', true)
            ->exists());
    }

    public function test_rejects_unknown_path(): void
    {
        if (!Permission::where('name', 'settings.website')->exists()) {
            Permission::create(['name' => 'settings.website', 'guard_name' => 'web']);
        }
        config()->set('identity.use_accounts', true);

        $tenant = Tenant::create(['slug' => 'nav-reject-shop', 'name' => 'Nav Reject', 'status' => 'active']);
        $adminRole = Role::withoutTenantScope()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $adminRole->syncPermissions(Permission::all());
        $owner = Account::create(['name' => 'Owner', 'email' => 'owner@nav-reject.test', 'password' => bcrypt('password'), 'status' => 'active']);
        TenantMembership::create(['account_id' => $owner->id, 'tenant_id' => $tenant->id, 'role_id' => $adminRole->id, 'is_owner' => true, 'status' => 'active', 'joined_at' => now()]);

        Storefront::withoutTenantScope()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        [$_, $navigation] = $this->seedNavigation('nav-reject-shop', $tenant);

        $items = $this->headerItems($navigation);
        $payload = $items->map(fn ($i) => [
            'id' => $i->id,
            'key' => $i->key,
            'label' => $i->label,
            'path' => $i->key === 'contact' ? '/evil-path' : $i->path,
            'enabled' => true,
            'position' => $i->position,
            'group' => 'header',
        ])->values()->all();

        $this->actingAs($owner, 'accounts')
            ->put("/store/{$tenant->slug}/admin/storefront/navigation", [
                'show_store_name' => true,
                'show_search' => true,
                'items' => $payload,
            ])
            ->assertSessionHasErrors('items.2.path');
    }

    public function test_published_revision_backfill_and_resolve_show_support(): void
    {
        $tenant = Tenant::create(['slug' => 'nav-rev-shop', 'name' => 'Nav Rev', 'status' => 'active']);
        $storefront = Storefront::withoutTenantScope()->create(['tenant_id' => $tenant->id, 'status' => 'active']);

        $revision = \App\Models\StorefrontRevision::withoutTenantScope()->create([
            'tenant_id' => $tenant->id,
            'storefront_id' => $storefront->id,
            'revision_number' => 1,
            'status' => 'published',
            'configuration' => [
                'navigation' => [
                    'show_store_name' => true,
                    'show_search' => true,
                    'items' => [
                        ['key' => 'home', 'label' => 'Home', 'path' => '/', 'icon' => 'bi-house-door', 'position' => 0],
                        ['key' => 'products', 'label' => 'Products', 'path' => '/products', 'icon' => 'bi-grid', 'position' => 1],
                        ['key' => 'contact', 'label' => 'Contact', 'path' => '/contact', 'icon' => 'bi-envelope', 'position' => 2],
                        ['key' => 'orders', 'label' => 'My Orders', 'path' => '/customer/orders', 'icon' => 'bi-receipt', 'position' => 3],
                    ],
                    'footer_items' => [],
                ],
            ],
        ]);
        $storefront->update(['published_revision_id' => $revision->id]);

        $this->assertTrue(StorefrontConfigurationResolver::backfillSupportInRevision($revision->fresh()));
        $this->assertFalse(StorefrontConfigurationResolver::backfillSupportInRevision($revision->fresh()));

        $resolved = app(StorefrontConfigurationResolver::class)->resolve($tenant, 'published', false);
        $paths = collect($resolved['navigation']['items'] ?? [])->pluck('path')->all();
        $this->assertEquals(['/', '/products', '/contact', '/support', '/customer/orders'], $paths);
    }

    public function test_default_support_label_renamed_custom_labels_preserved(): void
    {
        $tenant = Tenant::create(['slug' => 'nav-rename-shop', 'name' => 'Nav Rename', 'status' => 'active']);
        $storefront = Storefront::withoutTenantScope()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        $navigation = StorefrontNavigation::withoutTenantScope()->create([
            'tenant_id' => $tenant->id,
            'storefront_id' => $storefront->id,
            'settings' => ['show_store_name' => true, 'show_search' => true],
        ]);
        StorefrontNavigationItem::withoutTenantScope()->create([
            'tenant_id' => $tenant->id, 'navigation_id' => $navigation->id,
            'key' => 'support', 'label' => 'Support', 'path' => '/support',
            'icon' => 'bi-life-preserver', 'group' => 'header', 'enabled' => true, 'position' => 3,
        ]);
        StorefrontNavigationItem::withoutTenantScope()->create([
            'tenant_id' => $tenant->id, 'navigation_id' => $navigation->id,
            'key' => 'contact', 'label' => 'Get In Touch', 'path' => '/contact',
            'icon' => 'bi-envelope', 'group' => 'header', 'enabled' => true, 'position' => 2,
        ]);

        $revision = \App\Models\StorefrontRevision::withoutTenantScope()->create([
            'tenant_id' => $tenant->id,
            'storefront_id' => $storefront->id,
            'revision_number' => 1,
            'status' => 'published',
            'configuration' => ['navigation' => ['items' => [
                ['key' => 'support', 'label' => 'Support', 'path' => '/support', 'position' => 3],
                ['key' => 'contact', 'label' => 'Reach Us', 'path' => '/contact', 'position' => 2],
            ]]],
        ]);
        $storefront->update(['published_revision_id' => $revision->id]);

        $migration = require database_path('migrations/2026_09_30_160000_rename_support_nav_to_help_support.php');
        $migration->up();

        $this->assertEquals('Help & Support', StorefrontNavigationItem::withoutTenantScope()
            ->where('navigation_id', $navigation->id)->where('key', 'support')->value('label'));
        $this->assertEquals('Get In Touch', StorefrontNavigationItem::withoutTenantScope()
            ->where('navigation_id', $navigation->id)->where('key', 'contact')->value('label'));

        $snapshot = \App\Models\StorefrontRevision::withoutTenantScope()->find($revision->id)->configuration;
        $byKey = collect($snapshot['navigation']['items'])->keyBy('key');
        $this->assertEquals('Help & Support', $byKey['support']['label']);
        $this->assertEquals('Reach Us', $byKey['contact']['label']);

        $resolved = app(StorefrontConfigurationResolver::class)->resolve($tenant, 'published', false);
        $this->assertContains('Help & Support', collect($resolved['navigation']['items'] ?? [])->pluck('label')->all());
    }

    public function test_navigation_page_exposes_revision_status_and_save_creates_draft(): void
    {
        if (!Permission::where('name', 'settings.website')->exists()) {
            Permission::create(['name' => 'settings.website', 'guard_name' => 'web']);
        }
        config()->set('identity.use_accounts', true);

        $tenant = Tenant::create(['slug' => 'nav-flow-shop', 'name' => 'Nav Flow', 'status' => 'active']);
        $adminRole = Role::withoutTenantScope()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $adminRole->syncPermissions(Permission::all());
        $owner = Account::create(['name' => 'Owner', 'email' => 'owner@nav-flow.test', 'password' => bcrypt('password'), 'status' => 'active']);
        TenantMembership::create(['account_id' => $owner->id, 'tenant_id' => $tenant->id, 'role_id' => $adminRole->id, 'is_owner' => true, 'status' => 'active', 'joined_at' => now()]);

        Storefront::withoutTenantScope()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        [$_, $navigation] = $this->seedNavigation('nav-flow-shop', $tenant);

        $this->actingAs($owner, 'accounts')
            ->get("/store/{$tenant->slug}/admin/storefront/navigation")
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Storefront/Navigation')
                ->has('revision')
                ->has('navigation.items')
            );

        $items = $this->headerItems($navigation);
        $this->actingAs($owner, 'accounts')
            ->put("/store/{$tenant->slug}/admin/storefront/navigation", [
                'show_store_name' => true,
                'show_search' => true,
                'items' => $items->map(fn ($i) => [
                    'id' => $i->id,
                    'key' => $i->key,
                    'label' => $i->key === 'contact' ? 'Contact Us' : $i->label,
                    'path' => $i->path,
                    'enabled' => true,
                    'position' => $i->position,
                    'group' => 'header',
                ])->values()->all(),
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($owner, 'accounts')
            ->get("/store/{$tenant->slug}/admin/storefront/navigation")
            ->assertInertia(fn ($page) => $page
                ->where('revision.has_unpublished_changes', true)
            );

        $this->assertEquals('Contact Us', StorefrontNavigationItem::withoutTenantScope()
            ->where('navigation_id', $navigation->id)->where('key', 'contact')->value('label'));
    }

    private function seedNavigation(string $slug, ?Tenant $existing = null)
    {
        $tenant = $existing ?? Tenant::create(['slug' => $slug, 'name' => $slug, 'status' => 'active']);

        $storefront = Storefront::withoutTenantScope()->firstOrCreate(
            ['tenant_id' => $tenant->id],
            ['status' => 'active']
        );

        $navigation = StorefrontNavigation::withoutTenantScope()->create([
            'tenant_id' => $tenant->id,
            'storefront_id' => $storefront->id,
            'settings' => ['show_store_name' => true, 'show_search' => true],
        ]);

        foreach ([
            ['key' => 'home', 'label' => 'Home', 'path' => '/', 'icon' => 'bi-house-door'],
            ['key' => 'products', 'label' => 'Products', 'path' => '/products', 'icon' => 'bi-grid'],
            ['key' => 'contact', 'label' => 'Contact', 'path' => '/contact', 'icon' => 'bi-envelope'],
            ['key' => 'orders', 'label' => 'My Orders', 'path' => '/customer/orders', 'icon' => 'bi-receipt'],
        ] as $position => $item) {
            StorefrontNavigationItem::withoutTenantScope()->create([
                'tenant_id' => $tenant->id,
                'navigation_id' => $navigation->id,
                ...$item,
                'group' => 'header',
                'enabled' => true,
                'position' => $position,
            ]);
        }

        return [$tenant, $navigation];
    }

    private function headerItems(StorefrontNavigation $navigation)
    {
        return StorefrontNavigationItem::withoutTenantScope()
            ->where('navigation_id', $navigation->id)
            ->where('group', 'header')
            ->orderBy('position')
            ->get();
    }
}
