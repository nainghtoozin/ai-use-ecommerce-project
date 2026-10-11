<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Product;
use App\Models\Role;
use App\Models\StoreCategory;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Database\Seeders\StoreCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class StoreCategoryIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('identity.use_accounts', true);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private ?User $adminUser = null;

    private function superadmin(): User
    {
        if ($this->adminUser) {
            return $this->adminUser;
        }

        foreach (['store-categories.view', 'store-categories.create', 'store-categories.update', 'store-categories.delete'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $role = Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::whereIn('name', [
            'store-categories.view', 'store-categories.create', 'store-categories.update', 'store-categories.delete',
        ])->get());

        $this->adminUser = User::create([
            'name' => 'Super Admin', 'email' => 'super-e2e@admin.com',
            'password' => bcrypt('password'), 'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->adminUser->assignRole($role);

        return $this->adminUser;
    }

    private function asSuperadmin(): static
    {
        app()->forgetInstance('current.tenant');

        return $this->actingAs($this->superadmin(), 'web');
    }

    private function asMerchant(Account $account): static
    {
        auth()->guard('web')->logout();
        app()->forgetInstance('current.tenant');

        return $this->actingAs($account, 'accounts');
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

    public function test_full_lifecycle_from_superadmin_to_marketplace(): void
    {
        // 1. SuperAdmin creates + edits a category.
        $this->asSuperadmin()
            ->post('/superadmin/store-categories', ['name' => 'Home & Kitchen'])
            ->assertSessionHas('success');

        $category = StoreCategory::where('slug', 'home-kitchen')->firstOrFail();

        $this->asSuperadmin()
            ->put("/superadmin/store-categories/{$category->id}", [
                'name' => 'Home & Living',
                'slug' => 'home-living',
                'description' => 'Home goods stores',
            ])
            ->assertSessionHas('success');

        // 9. Seeder rerun keeps the SuperAdmin-managed row, keyed by stable slug.
        Artisan::call('db:seed', ['--class' => StoreCategorySeeder::class]);
        $this->assertSame(1, StoreCategory::where('slug', 'home-living')->count());

        // 2. New store onboarding saves the selected category.
        Plan::updateOrCreate(['slug' => 'free'], [
            'name' => 'Starter', 'monthly_price' => 0, 'yearly_price' => 0, 'status' => 'active',
        ]);
        PlatformSetting::query()->delete();
        PlatformSetting::create(['trial_enabled' => true, 'trial_days' => 14]);
        PlatformSetting::clearCache();

        $merchant = Account::create([
            'name' => 'Merchant', 'email' => 'merchant-e2e@test.com',
            'password' => bcrypt('password'), 'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $slug = 'e2e-shop-' . uniqid();
        $this->asMerchant($merchant)
            ->post('/onboarding/store-setup', [
                'store_name' => 'E2E Shop',
                'store_slug' => $slug,
                'business_email' => "biz-{$slug}@test.com",
                'language' => 'en', 'currency' => 'MMK',
                'timezone' => 'Asia/Yangon', 'country' => 'MM',
                'store_category_id' => $category->id,
            ])
            ->assertRedirect(route('onboarding.success', ['store_slug' => $slug]));

        $tenant = Tenant::where('slug', $slug)->firstOrFail();
        $this->assertSame($category->id, $tenant->store_category_id);

        // 10. Product categories untouched by the whole flow.
        Tenant::setCurrent($tenant);
        try {
            $productCat = Category::create(['name' => 'Home & Living', 'slug' => 'home-living-prod']);
            Product::create([
                'name' => 'E2E Lamp', 'slug' => 'e2e-lamp', 'price' => 5000,
                'category_id' => $productCat->id, 'status' => Product::STATUS_ACTIVE,
            ]);
            Product::create([
                'name' => 'E2E Draft', 'slug' => 'e2e-draft', 'price' => 7000,
                'category_id' => $productCat->id, 'status' => Product::STATUS_INACTIVE,
            ]);
        } finally {
            app()->forgetInstance('current.tenant');
        }
        $this->assertSame(1, Category::withoutTenantScope()->count());

        // 3/4/5. Merchant changes category in Settings; marketplace + counts follow.
        $fashion = StoreCategory::create(['name' => 'E2E Fashion', 'slug' => 'fashion-e2e']);
        $owner = $this->ownerOf($tenant, "owner-{$slug}@test.com");

        $this->asMerchant($owner)
            ->put("/store/{$tenant->slug}/admin/settings", ['store_category_id' => $fashion->id])
            ->assertSessionHasNoErrors();
        $this->assertSame($fashion->id, $tenant->fresh()->store_category_id);

        $rows = $this->marketplaceRows($this->get("/marketplace?category_id={$fashion->id}"));
        $this->assertSame([$slug], array_column($rows, 'slug'));
        $this->assertSame(1, array_column($rows, 'products_count', 'slug')[$slug]);

        $oldFilter = $this->marketplaceRows($this->get("/marketplace?category_id={$category->id}"));
        $this->assertSame([], array_column($oldFilter, 'slug'));

        // 6. Assigned category cannot be deactivated; unassigned one can.
        $this->asSuperadmin()
            ->post("/superadmin/store-categories/{$fashion->id}/toggle")
            ->assertSessionHas('error');
        $this->assertTrue($fashion->fresh()->is_active);

        $this->asMerchant($owner)
            ->put("/store/{$tenant->slug}/admin/settings", ['store_category_id' => ''])
            ->assertSessionHasNoErrors();
        $this->assertNull($tenant->fresh()->store_category_id);

        $this->asSuperadmin()
            ->post("/superadmin/store-categories/{$fashion->id}/toggle")
            ->assertSessionHas('success');
        $this->assertFalse($fashion->fresh()->is_active);

        // 8. NULL-category store stays visible under All Categories.
        $all = array_column($this->marketplaceRows($this->get('/marketplace')), 'slug');
        $this->assertContains($slug, $all);

        $options = array_column(
            $this->get('/marketplace')->viewData('page')['props']['categories'],
            'name'
        );
        $this->assertNotContains('E2E Fashion', $options);
    }

    public function test_slug_rename_keeps_store_assignments_working(): void
    {
        $this->asSuperadmin();
        $category = StoreCategory::create(['name' => 'Gadgets', 'slug' => 'gadgets-e2e']);
        $tenant = Tenant::create([
            'name' => 'Rename Shop', 'slug' => 'rename-shop', 'status' => 'active',
            'activated_at' => now(), 'store_category_id' => $category->id,
        ]);

        $this->asSuperadmin()
            ->put("/superadmin/store-categories/{$category->id}", [
                'name' => 'Gadgets & Gear',
                'slug' => 'gadgets-gear-e2e',
            ])
            ->assertSessionHas('success');

        $this->assertSame($category->id, $tenant->fresh()->store_category_id);

        $rows = $this->marketplaceRows($this->get("/marketplace?category_id={$category->id}"));
        $this->assertSame(['rename-shop'], array_column($rows, 'slug'));
    }

    /** @return array<int, array<string, mixed>> */
    private function marketplaceRows($response): array
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



