<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Role;
use App\Models\StoreCategory;
use App\Models\Tenant;
use App\Models\TenantMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class StoreCategoryAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('identity.use_accounts', true);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function activeCategory(string $name = 'Electronics', string $slug = 'electronics'): StoreCategory
    {
        return StoreCategory::create(['name' => $name, 'slug' => $slug, 'is_active' => true]);
    }

    private function storePayload(string $slug, array $overrides = []): array
    {
        return array_merge([
            'store_name' => 'Shop ' . $slug,
            'store_slug' => $slug,
            'business_email' => "biz-{$slug}@test.com",
            'phone' => '09123456789',
            'language' => 'en',
            'currency' => 'MMK',
            'timezone' => 'Asia/Yangon',
            'country' => 'MM',
            'theme_color' => '#6366F1',
            'description' => 'A test store.',
        ], $overrides);
    }

    private function freshAccount(string $email): Account
    {
        $account = Account::create([
            'name' => 'Merchant',
            'email' => $email,
            'password' => bcrypt('password'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($account, 'accounts');

        return $account;
    }

    private function enableTrial(): void
    {
        Plan::updateOrCreate(['slug' => 'free'], [
            'name' => 'Starter', 'monthly_price' => 0, 'yearly_price' => 0, 'status' => 'active',
        ]);
        PlatformSetting::query()->delete();
        PlatformSetting::create(['trial_enabled' => true, 'trial_days' => 14]);
        PlatformSetting::clearCache();
    }

    private function ownerOf(Tenant $tenant, string $email): Account
    {
        if (!Permission::where('name', 'settings.website')->exists()) {
            Permission::create(['name' => 'settings.website', 'guard_name' => 'web']);
        }

        $adminRole = Role::withoutTenantScope()->firstOrCreate([
            'name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id,
        ]);
        $adminRole->syncPermissions(Permission::all());

        $owner = Account::create([
            'name' => "Owner {$tenant->slug}", 'email' => $email,
            'password' => bcrypt('password'), 'status' => 'active',
        ]);
        $owner->assignRole($adminRole);
        TenantMembership::create([
            'account_id' => $owner->id, 'tenant_id' => $tenant->id,
            'role_id' => $adminRole->id, 'is_owner' => true,
            'status' => 'active', 'joined_at' => now(),
        ]);

        return $owner;
    }

    private function merchantTenant(string $slug, ?int $storeCategoryId = null): array
    {
        $tenant = Tenant::create([
            'name' => "Shop {$slug}", 'slug' => $slug, 'status' => 'active',
            'activated_at' => now(), 'store_category_id' => $storeCategoryId,
        ]);
        $owner = $this->ownerOf($tenant, "owner@{$slug}.test");

        return [$tenant, $owner];
    }

    public function test_store_setup_saves_selected_category(): void
    {
        $this->enableTrial();
        $category = $this->activeCategory();
        $this->freshAccount('merchant-cat@test.com');

        $slug = 'cat-store-' . uniqid();
        $response = $this->post(
            '/onboarding/store-setup',
            $this->storePayload($slug, ['store_category_id' => $category->id])
        );

        $response->assertRedirect(route('onboarding.success', ['store_slug' => $slug]));
        $this->assertSame($category->id, Tenant::where('slug', $slug)->firstOrFail()->store_category_id);
    }

    public function test_store_setup_without_category_succeeds_with_null(): void
    {
        $this->enableTrial();
        $this->freshAccount('merchant-nocat@test.com');

        $slug = 'nocat-store-' . uniqid();
        $response = $this->post('/onboarding/store-setup', $this->storePayload($slug));

        $response->assertRedirect(route('onboarding.success', ['store_slug' => $slug]));
        $this->assertNull(Tenant::where('slug', $slug)->firstOrFail()->store_category_id);
    }

    public function test_store_setup_rejects_inactive_and_unknown_categories(): void
    {
        $this->enableTrial();
        $inactive = StoreCategory::create(['name' => 'Retired', 'slug' => 'retired', 'is_active' => false]);
        $this->freshAccount('merchant-badcat@test.com');

        $this->post(
            '/onboarding/store-setup',
            $this->storePayload('badcat-a', ['store_category_id' => $inactive->id])
        )->assertSessionHasErrors('store_category_id');

        $this->post(
            '/onboarding/store-setup',
            $this->storePayload('badcat-b', ['store_category_id' => 999999])
        )->assertSessionHasErrors('store_category_id');

        $this->assertSame(0, Tenant::whereIn('slug', ['badcat-a', 'badcat-b'])->count());
    }

    public function test_create_store_endpoint_accepts_optional_category(): void
    {
        $category = $this->activeCategory();
        $slug = 'guestcat-' . uniqid();
        $email = "guestcat-{$slug}@test.com";

        $response = $this->post('/create-store', [
            'name' => 'Guest Store',
            'slug' => $slug,
            'owner_name' => 'Guest Owner',
            'owner_email' => $email,
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'store_category_id' => $category->id,
        ]);

        $response->assertRedirect(route('create-store.success', ['store' => $slug]));
        $this->assertSame($category->id, Tenant::where('slug', $slug)->firstOrFail()->store_category_id);
    }

    public function test_merchant_can_update_own_store_category(): void
    {
        $fashion = $this->activeCategory('Fashion', 'fashion');
        [$tenant, $owner] = $this->merchantTenant('owned-shop');

        $this->assertNull($tenant->fresh()->store_category_id);

        $this->actingAs($owner, 'accounts')
            ->put("/store/{$tenant->slug}/admin/settings", ['store_category_id' => $fashion->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($fashion->id, $tenant->fresh()->store_category_id);
    }

    public function test_merchant_can_clear_store_category(): void
    {
        $category = $this->activeCategory();
        [$tenant, $owner] = $this->merchantTenant('clear-shop', $category->id);

        $this->actingAs($owner, 'accounts')
            ->put("/store/{$tenant->slug}/admin/settings", ['store_category_id' => ''])
            ->assertSessionHasNoErrors();

        $this->assertNull($tenant->fresh()->store_category_id);
    }

    public function test_merchant_cannot_update_other_tenants_category(): void
    {
        $category = $this->activeCategory();
        [$tenantA] = $this->merchantTenant('iso-shop-a');
        [$tenantB, $ownerB] = $this->merchantTenant('iso-shop-b');

        $this->actingAs($ownerB, 'accounts')
            ->put("/store/{$tenantA->slug}/admin/settings", ['store_category_id' => $category->id])
            ->assertForbidden();

        $this->assertNull($tenantA->fresh()->store_category_id);
        $this->assertNull($tenantB->fresh()->store_category_id);
    }

    public function test_merchant_settings_rejects_inactive_category(): void
    {
        $inactive = StoreCategory::create(['name' => 'Retired', 'slug' => 'retired-2', 'is_active' => false]);
        [$tenant, $owner] = $this->merchantTenant('reject-shop');

        $this->actingAs($owner, 'accounts')
            ->put("/store/{$tenant->slug}/admin/settings", ['store_category_id' => $inactive->id])
            ->assertSessionHasErrors('store_category_id');

        $this->assertNull($tenant->fresh()->store_category_id);
    }

    public function test_uncategorized_store_appears_in_all_but_not_in_filtered_results(): void
    {
        $category = $this->activeCategory();

        Tenant::create([
            'name' => 'No Cat Shop', 'slug' => 'no-cat-shop',
            'status' => 'active', 'activated_at' => now(),
        ]);
        Tenant::create([
            'name' => 'Cat Shop', 'slug' => 'cat-shop',
            'status' => 'active', 'activated_at' => now(),
            'store_category_id' => $category->id,
        ]);

        $all = array_column(
            $this->storeRows($this->get('/marketplace')),
            'slug'
        );
        $this->assertContains('no-cat-shop', $all);
        $this->assertContains('cat-shop', $all);

        $filtered = array_column(
            $this->storeRows($this->get("/marketplace?category_id={$category->id}")),
            'slug'
        );
        $this->assertSame(['cat-shop'], $filtered);
    }

    public function test_settings_page_exposes_categories_and_current_selection(): void
    {
        $category = $this->activeCategory();
        [$tenant, $owner] = $this->merchantTenant('props-shop', $category->id);

        $this->actingAs($owner, 'accounts')
            ->get("/store/{$tenant->slug}/admin/settings")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Settings/Edit')
                ->where('store_category_id', $category->id)
                ->has('storeCategories')
            );
    }

    public function test_trialing_merchant_settings_show_saved_category(): void
    {
        $category = $this->activeCategory('Toys & Games', 'toys-games');
        $tenant = Tenant::create([
            'name' => 'Aye', 'slug' => 'aye', 'status' => 'trialing',
            'activated_at' => now(), 'store_category_id' => $category->id,
        ]);
        \App\Models\WebsiteInfo::withoutTenantScope(function () use ($tenant) {
            \App\Models\WebsiteInfo::create([
                'tenant_id' => $tenant->id, 'site_name' => 'Aye', 'is_active' => true,
            ]);
        });
        \App\Models\Storefront::withoutTenantScope()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        Tenant::setCurrent($tenant);
        $plan = \App\Models\Plan::create([
            'name' => 'Starter', 'slug' => 'starter-trial', 'monthly_price' => 0,
            'yearly_price' => 0, 'status' => 'active',
        ]);
        \App\Models\Subscription::create([
            'tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'status' => 'trialing',
            'trial_ends_at' => now()->addDays(14), 'expires_at' => now()->addDays(14),
        ]);
        app()->forgetInstance('current.tenant');

        if (!\Spatie\Permission\Models\Permission::where('name', 'settings.website')->exists()) {
            \Spatie\Permission\Models\Permission::create(['name' => 'settings.website', 'guard_name' => 'web']);
        }
        $role = \App\Models\Role::withoutTenantScope()->firstOrCreate([
            'name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id,
        ]);
        $role->syncPermissions(\Spatie\Permission\Models\Permission::all());
        $owner = \App\Models\Account::create([
            'name' => 'Aye Chan', 'email' => 'aye7@test.com',
            'password' => bcrypt('password'), 'status' => 'active',
        ]);
        $owner->assignRole($role);
        \App\Models\TenantMembership::create([
            'account_id' => $owner->id, 'tenant_id' => $tenant->id,
            'role_id' => $role->id, 'is_owner' => true,
            'status' => 'active', 'joined_at' => now(),
        ]);

        $props = $this->actingAs(\App\Models\Account::find($owner->id), 'accounts')
            ->get("/store/{$tenant->slug}/admin/settings")
            ->viewData('page')['props'];

        $this->assertSame($category->id, $props['store_category_id']);
        $this->assertContains($category->id, array_column($props['storeCategories'], 'id'));
    }

    public function test_single_membership_merchant_always_resolves_own_store(): void
    {
        $category = $this->activeCategory();
        [$tenant, $owner] = $this->merchantTenant('single-shop', $category->id);

        foreach (['garbage-slug', $tenant->slug] as $sessionSlug) {
            $props = $this->actingAs(\App\Models\Account::find($owner->id), 'accounts')
                ->withSession(['current_tenant_slug' => $sessionSlug])
                ->get('/admin/settings')
                ->viewData('page')['props'];

            $this->assertSame($category->id, $props['store_category_id']);
        }
    }

    public function test_browser_payload_overwrites_existing_category(): void
    {
        $old = $this->activeCategory('Old Cat', 'old-cat-bp');
        $new = $this->activeCategory('New Cat', 'new-cat-bp');
        [$tenant, $owner] = $this->merchantTenant('overwrite-shop', $old->id);

        $response = $this->actingAs($owner, 'accounts')->call(
            'POST',
            "/store/{$tenant->slug}/admin/website-info/edit",
            [
                'site_name' => 'Overwrite Shop',
                'theme_color' => '#3B82F6',
                'default_shipping_fee' => '5',
                'store_category_id' => (string) $new->id,
                'hero_images_payload' => '[]',
                '_method' => 'PUT',
            ],
            [],
            [],
            ['CONTENT_TYPE' => 'multipart/form-data']
        );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertSame($new->id, $tenant->fresh()->store_category_id);

        $props = $this->actingAs($owner, 'accounts')
            ->get("/store/{$tenant->slug}/admin/settings")
            ->viewData('page')['props'];
        $this->assertSame($new->id, $props['store_category_id']);
    }

    public function test_inactive_saved_category_is_shown_explicitly(): void
    {
        $category = StoreCategory::create(['name' => 'Retired', 'slug' => 'retired', 'is_active' => false]);
        [$tenant, $owner] = $this->merchantTenant('inactive-saved-shop', $category->id);

        $props = $this->actingAs($owner, 'accounts')
            ->get("/store/{$tenant->slug}/admin/settings")
            ->viewData('page')['props'];

        $this->assertSame($category->id, $props['store_category_id']);
        $this->assertSame($category->id, $props['currentStoreCategory']['id']);
        $this->assertFalse((bool) $props['currentStoreCategory']['is_active']);
        $this->assertNotContains(
            $category->id,
            array_column($props['storeCategories'], 'id')
        );

        $source = (string) file_get_contents(base_path('resources/js/Pages/Admin/Settings/Edit.jsx'));
        $this->assertStringContainsString('currentStoreCategory', $source);
        $this->assertStringContainsString('(inactive)', $source);
    }

    public function test_browser_payload_clear_sets_null(): void
    {
        $category = $this->activeCategory();
        [$tenant, $owner] = $this->merchantTenant('browser-clear-shop', $category->id);

        $this->actingAs($owner, 'accounts')->call(
            'POST',
            "/store/{$tenant->slug}/admin/website-info/edit",
            ['site_name' => 'Browser Clear Shop', 'store_category_id' => '', '_method' => 'PUT'],
            [],
            [],
            ['CONTENT_TYPE' => 'multipart/form-data']
        )->assertSessionHasNoErrors();

        $this->assertNull($tenant->fresh()->store_category_id);
    }

    public function test_settings_roundtrip_returns_new_selection(): void
    {
        $old = $this->activeCategory('Old Cat', 'old-cat-rt');
        $new = $this->activeCategory('New Cat', 'new-cat-rt');
        [$tenant, $owner] = $this->merchantTenant('roundtrip-shop', $old->id);

        $this->actingAs($owner, 'accounts')
            ->put("/store/{$tenant->slug}/admin/settings", ['store_category_id' => $new->id])
            ->assertSessionHasNoErrors();

        $props = $this->actingAs($owner, 'accounts')
            ->get("/store/{$tenant->slug}/admin/settings")
            ->viewData('page')['props'];

        $this->assertSame($new->id, $tenant->fresh()->store_category_id);
        $this->assertSame($new->id, $props['store_category_id']);
    }

    public function test_unrelated_website_settings_update_alongside_category(): void
    {
        $category = $this->activeCategory();
        [$tenant, $owner] = $this->merchantTenant('alongside-shop');

        $this->actingAs($owner, 'accounts')
            ->put("/store/{$tenant->slug}/admin/settings", [
                'site_name' => 'Alongside Shop Renamed',
                'theme_color' => '#10B981',
                'default_shipping_fee' => '7.50',
                'store_category_id' => $category->id,
            ])
            ->assertSessionHas('success');

        $this->assertSame($category->id, $tenant->fresh()->store_category_id);
        $this->assertDatabaseHas('website_infos', [
            'tenant_id' => $tenant->id,
            'site_name' => 'Alongside Shop Renamed',
            'theme_color' => '#10B981',
        ]);
    }

    public function test_legacy_settings_follow_session_tenant_context(): void
    {
        $category = $this->activeCategory();
        [$tenantA] = $this->merchantTenant('ctx-shop-a');
        [$tenantB, $ownerB] = $this->merchantTenant('ctx-shop-b');

        $this->actingAs($ownerB, 'accounts')
            ->withSession(['current_tenant_slug' => $tenantB->slug])
            ->put('/admin/website-info/edit', [
                'site_name' => 'Ctx Shop B', 'store_category_id' => $category->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($tenantA->fresh()->store_category_id);
        $this->assertSame($category->id, $tenantB->fresh()->store_category_id);
    }

    public function test_account_without_membership_is_forbidden_without_changes(): void
    {
        $category = $this->activeCategory();
        $tenant = \App\Models\Tenant::create([
            'name' => 'Ctx Shop', 'slug' => 'ctx-shop', 'status' => 'active', 'activated_at' => now(),
        ]);
        $stranger = \App\Models\Account::create([
            'name' => 'Stranger', 'email' => 'stranger@test.com',
            'password' => bcrypt('password'), 'status' => 'active',
        ]);

        $response = $this->actingAs($stranger, 'accounts')
            ->put("/store/{$tenant->slug}/admin/settings", ['store_category_id' => $category->id]);

        $response->assertForbidden();
        $this->assertNull($tenant->fresh()->store_category_id);
    }

    public function test_legacy_settings_get_returns_saved_category(): void
    {
        $category = $this->activeCategory('Toys & Games', 'toys-games-legacy');
        [$tenant, $owner] = $this->merchantTenant('legacy-read-shop', $category->id);

        $this->actingAs(\App\Models\Account::find($owner->id), 'accounts')
            ->get('/admin/settings')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Settings/Edit')
                ->where('store_category_id', $category->id)
            );

        $props = $this->actingAs(\App\Models\Account::find($owner->id), 'accounts')
            ->get('/admin/settings')
            ->viewData('page')['props'];
        $this->assertSame($category->id, $props['store_category_id']);
        $this->assertContains($category->id, array_column($props['storeCategories'], 'id'));
    }

    public function test_legacy_settings_get_ignores_foreign_session_slug(): void
    {
        $category = $this->activeCategory('Fashion', 'fashion-legacy');
        [$tenant, $owner] = $this->merchantTenant('foreign-session-shop', $category->id);
        $other = Tenant::create([
            'name' => 'Other Shop', 'slug' => 'other-shop', 'status' => 'active',
            'activated_at' => now(),
        ]);

        foreach (['other-shop', 'garbage-slug'] as $sessionSlug) {
            $props = $this->actingAs(\App\Models\Account::find($owner->id), 'accounts')
                ->withSession(['current_tenant_slug' => $sessionSlug])
                ->get('/admin/settings')
                ->viewData('page')['props'];

            $this->assertSame($category->id, $props['store_category_id']);
        }

        $this->assertNull($other->fresh()->store_category_id);
    }

    public function test_legacy_settings_get_follows_session_tenant_context(): void
    {
        $category = $this->activeCategory('Electronics', 'electronics-ctx');
        $tenantA = Tenant::create([
            'name' => 'Ctx A', 'slug' => 'ctx-get-a', 'status' => 'active',
            'activated_at' => now(), 'store_category_id' => $category->id,
        ]);
        $tenantB = Tenant::create([
            'name' => 'Ctx B', 'slug' => 'ctx-get-b', 'status' => 'active', 'activated_at' => now(),
        ]);

        if (!\Spatie\Permission\Models\Permission::where('name', 'settings.website')->exists()) {
            \Spatie\Permission\Models\Permission::create(['name' => 'settings.website', 'guard_name' => 'web']);
        }
        $owner = \App\Models\Account::create([
            'name' => 'Ctx Owner', 'email' => 'ctx-get@test.com',
            'password' => bcrypt('password'), 'status' => 'active',
        ]);
        foreach ([$tenantA, $tenantB] as $t) {
            $role = \App\Models\Role::withoutTenantScope()->firstOrCreate([
                'name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $t->id,
            ]);
            $role->syncPermissions(\Spatie\Permission\Models\Permission::all());
            \App\Models\TenantMembership::create([
                'account_id' => $owner->id, 'tenant_id' => $t->id,
                'role_id' => $role->id, 'is_owner' => true,
                'status' => 'active', 'joined_at' => now(),
            ]);
        }

        $propsA = $this->actingAs(\App\Models\Account::find($owner->id), 'accounts')
            ->withSession(['current_tenant_slug' => $tenantA->slug])
            ->get('/admin/settings')
            ->viewData('page')['props'];
        $this->assertSame($category->id, $propsA['store_category_id']);

        $propsB = $this->actingAs(\App\Models\Account::find($owner->id), 'accounts')
            ->withSession(['current_tenant_slug' => $tenantB->slug])
            ->get('/admin/settings')
            ->viewData('page')['props'];
        $this->assertNull($propsB['store_category_id']);
    }

    /** @return array<int, array<string, mixed>> */
    private function storeRows($response): array
    {
        $stores = $response->viewData('page')['props']['stores'];

        if (is_array($stores)) {
            return array_values($stores['data'] ?? []);
        }

        return $stores instanceof \Traversable
            ? iterator_to_array($stores)
            : $stores->items();
    }
}
