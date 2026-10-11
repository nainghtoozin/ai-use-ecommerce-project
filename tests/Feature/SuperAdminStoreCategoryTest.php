<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\StoreCategory;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuperAdminStoreCategoryTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    private array $storeCategoryPermissions = [
        'store-categories.view',
        'store-categories.create',
        'store-categories.update',
        'store-categories.delete',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->storeCategoryPermissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $role = Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        $role->givePermissionTo($this->storeCategoryPermissions);

        $this->superadmin = User::create([
            'name' => 'Super Admin',
            'email' => 'super@admin.com',
            'password' => bcrypt('password'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->superadmin->assignRole($role);
    }

    public function test_superadmin_can_list_store_categories_with_search_and_status_filter(): void
    {
        StoreCategory::create(['name' => 'Electronics', 'slug' => 'electronics', 'is_active' => true]);
        StoreCategory::create(['name' => 'Retired', 'slug' => 'retired', 'is_active' => false]);

        $this->actingAs($this->superadmin)
            ->get('/superadmin/store-categories')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SuperAdmin/StoreCategories/Index')
                ->has('categories.data', 2)
            );

        $this->actingAs($this->superadmin)
            ->get('/superadmin/store-categories?search=electro')
            ->assertInertia(fn ($page) => $page->has('categories.data', 1));

        $this->actingAs($this->superadmin)
            ->get('/superadmin/store-categories?status=inactive')
            ->assertInertia(fn ($page) => $page->has('categories.data', 1));
    }

    public function test_superadmin_can_create_category_with_auto_slug(): void
    {
        $this->actingAs($this->superadmin)
            ->post('/superadmin/store-categories', [
                'name' => 'Home & Kitchen',
                'description' => 'Home goods',
                'is_active' => true,
            ])
            ->assertRedirect(route('superadmin.store-categories.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('store_categories', [
            'name' => 'Home & Kitchen',
            'slug' => 'home-kitchen',
        ]);
    }

    public function test_duplicate_slug_is_rejected_on_create_and_update(): void
    {
        StoreCategory::create(['name' => 'Electronics', 'slug' => 'electronics']);
        $other = StoreCategory::create(['name' => 'Gadgets', 'slug' => 'gadgets']);

        $this->actingAs($this->superadmin)
            ->post('/superadmin/store-categories', ['name' => 'Clone', 'slug' => 'electronics'])
            ->assertSessionHasErrors('slug');

        $this->actingAs($this->superadmin)
            ->put("/superadmin/store-categories/{$other->id}", ['name' => 'Gadgets', 'slug' => 'electronics'])
            ->assertSessionHasErrors('slug');

        $this->assertSame(2, StoreCategory::count());
    }

    public function test_superadmin_can_edit_category(): void
    {
        $category = StoreCategory::create(['name' => 'Fashion', 'slug' => 'fashion']);

        $this->actingAs($this->superadmin)
            ->get("/superadmin/store-categories/{$category->id}/edit")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SuperAdmin/StoreCategories/Edit')
                ->where('category.slug', 'fashion')
            );

        $this->actingAs($this->superadmin)
            ->put("/superadmin/store-categories/{$category->id}", [
                'name' => 'Fashion & Apparel',
                'slug' => 'fashion-apparel',
                'description' => 'Clothing stores',
            ])
            ->assertRedirect(route('superadmin.store-categories.index'))
            ->assertSessionHas('success');

        $fresh = $category->fresh();
        $this->assertSame('Fashion & Apparel', $fresh->name);
        $this->assertSame('fashion-apparel', $fresh->slug);
        $this->assertSame('Clothing stores', $fresh->description);
    }

    public function test_toggle_activation_cycle_for_unassigned_category(): void
    {
        $category = StoreCategory::create(['name' => 'Toys', 'slug' => 'toys', 'is_active' => false]);

        $this->actingAs($this->superadmin)
            ->post("/superadmin/store-categories/{$category->id}/toggle")
            ->assertSessionHas('success');
        $this->assertTrue($category->fresh()->is_active);

        $this->actingAs($this->superadmin)
            ->post("/superadmin/store-categories/{$category->id}/toggle")
            ->assertSessionHas('success');
        $this->assertFalse($category->fresh()->is_active);
    }

    public function test_deactivation_is_blocked_while_stores_assigned(): void
    {
        $category = StoreCategory::create(['name' => 'Electronics', 'slug' => 'electronics']);
        Tenant::create([
            'name' => 'Assigned Shop', 'slug' => 'assigned-shop', 'status' => 'active',
            'activated_at' => now(), 'store_category_id' => $category->id,
        ]);

        $this->actingAs($this->superadmin)
            ->post("/superadmin/store-categories/{$category->id}/toggle")
            ->assertSessionHas('error');
        $this->assertTrue($category->fresh()->is_active);

        $this->actingAs($this->superadmin)
            ->put("/superadmin/store-categories/{$category->id}", [
                'name' => 'Electronics',
                'slug' => 'electronics',
                'is_active' => false,
            ])
            ->assertSessionHasErrors('is_active');
        $this->assertTrue($category->fresh()->is_active);
    }

    public function test_delete_is_blocked_while_stores_assigned(): void
    {
        $category = StoreCategory::create(['name' => 'Electronics', 'slug' => 'electronics']);
        Tenant::create([
            'name' => 'Assigned Shop', 'slug' => 'assigned-shop', 'status' => 'active',
            'activated_at' => now(), 'store_category_id' => $category->id,
        ]);

        $this->actingAs($this->superadmin)
            ->delete("/superadmin/store-categories/{$category->id}")
            ->assertSessionHas('error');

        $this->assertTrue(StoreCategory::whereKey($category->id)->exists());
    }

    public function test_delete_and_deactivate_work_after_reassignment(): void
    {
        $old = StoreCategory::create(['name' => 'Old', 'slug' => 'old-cat']);
        $new = StoreCategory::create(['name' => 'New', 'slug' => 'new-cat']);
        $tenant = Tenant::create([
            'name' => 'Moved Shop', 'slug' => 'moved-shop', 'status' => 'active',
            'activated_at' => now(), 'store_category_id' => $old->id,
        ]);

        $tenant->update(['store_category_id' => $new->id]);

        $this->actingAs($this->superadmin)
            ->post("/superadmin/store-categories/{$old->id}/toggle")
            ->assertSessionHas('success');
        $this->assertFalse($old->fresh()->is_active);

        $this->actingAs($this->superadmin)
            ->delete("/superadmin/store-categories/{$old->id}")
            ->assertSessionHas('success');
        $this->assertFalse(StoreCategory::whereKey($old->id)->exists());
        $this->assertSame($new->id, $tenant->fresh()->store_category_id);
    }

    public function test_unauthorized_users_are_blocked(): void
    {
        $category = StoreCategory::create(['name' => 'Electronics', 'slug' => 'electronics']);

        $this->get('/superadmin/store-categories')->assertRedirect('/login');

        $merchant = User::create([
            'name' => 'Merchant', 'email' => 'merchant@test.com',
            'password' => bcrypt('password'), 'status' => 'active',
        ]);
        $this->actingAs($merchant)
            ->get('/superadmin/store-categories')
            ->assertForbidden();
        $this->actingAs($merchant)
            ->post('/superadmin/store-categories', ['name' => 'X'])
            ->assertForbidden();

        Role::findByName('superadmin')->revokePermissionTo('store-categories.view');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $limited = User::create([
            'name' => 'Limited', 'email' => 'limited@test.com',
            'password' => bcrypt('password'), 'status' => 'active',
        ]);
        $limited->assignRole('superadmin');

        $this->actingAs($limited)
            ->get('/superadmin/store-categories')
            ->assertForbidden();

        $this->assertTrue(StoreCategory::whereKey($category->id)->exists());
    }

    public function test_management_does_not_touch_product_categories(): void
    {
        $tenant = Tenant::create([
            'name' => 'Shop', 'slug' => 'shop', 'status' => 'active', 'activated_at' => now(),
        ]);
        Tenant::setCurrent($tenant);
        try {
            Category::create(['name' => 'Electronics', 'slug' => 'electronics']);
        } finally {
            app()->forgetInstance('current.tenant');
        }

        $this->actingAs($this->superadmin)
            ->post('/superadmin/store-categories', ['name' => 'Electronics', 'slug' => 'electronics-sc'])
            ->assertSessionHas('success');

        $this->assertSame(1, Category::withoutTenantScope()->count());
        $this->assertSame(1, StoreCategory::where('slug', 'electronics-sc')->count());
    }

    public function test_inactive_category_is_hidden_from_marketplace_options(): void
    {
        StoreCategory::create(['name' => 'Live', 'slug' => 'live-cat', 'is_active' => true]);
        StoreCategory::create(['name' => 'Hidden', 'slug' => 'hidden-cat', 'is_active' => false]);

        $response = $this->get('/marketplace');
        $response->assertOk();
        $options = array_column($response->viewData('page')['props']['categories'], 'name');

        $this->assertContains('Live', $options);
        $this->assertNotContains('Hidden', $options);
    }
}
