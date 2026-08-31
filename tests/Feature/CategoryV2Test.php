<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ImageService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CategoryV2Test extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $adminA;
    private User $adminB;
    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupTenantEnvironment();
    }

    private function setupTenantEnvironment(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'categories.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'categories.create', 'guard_name' => 'web']);
        Permission::create(['name' => 'categories.update', 'guard_name' => 'web']);
        Permission::create(['name' => 'categories.delete', 'guard_name' => 'web']);

        $this->adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $this->adminRole->syncPermissions(Permission::whereIn('name', [
            'categories.view', 'categories.create', 'categories.update', 'categories.delete'
        ])->get());

        $plan = \App\Models\Plan::create([
            'name' => 'Test Plan',
            'slug' => 'test-plan',
            'monthly_price' => 0,
            'yearly_price' => 0,
            'status' => 'active',
        ]);

        $this->tenantA = Tenant::create(['name' => 'Store A', 'slug' => 'store-a']);
        $this->tenantB = Tenant::create(['name' => 'Store B', 'slug' => 'store-b']);

        $this->tenantA->subscription_plan_id = $plan->id;
        $this->tenantA->save();
        $this->tenantB->subscription_plan_id = $plan->id;
        $this->tenantB->save();

        $this->adminA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Admin A',
            'email' => 'admin-a@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $this->adminA->assignRole($this->adminRole);

        $this->adminB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Admin B',
            'email' => 'admin-b@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $this->adminB->assignRole($this->adminRole);
    }

    private function actAsAdminA(): void
    {
        $this->actingAs($this->adminA);
        \App\Models\Tenant::setCurrent($this->tenantA);
    }

    private function actAsAdminB(): void
    {
        $this->actingAs($this->adminB);
        \App\Models\Tenant::setCurrent($this->tenantB);
    }

    /** @test */
    public function category_has_image_field(): void
    {
        $this->actAsAdminA();
        $this->assertTrue(Schema::hasColumn('categories', 'image'));
    }

    /** @test */
    public function category_has_featured_field(): void
    {
        $this->actAsAdminA();
        $this->assertTrue(Schema::hasColumn('categories', 'featured'));
    }

    /** @test */
    public function category_has_sort_order_field(): void
    {
        $this->actAsAdminA();
        $this->assertTrue(Schema::hasColumn('categories', 'sort_order'));
    }

    /** @test */
    public function category_crud_still_works(): void
    {
        $this->actAsAdminA();

        $category = Category::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Test Category',
            'description' => 'Test description',
        ]);

        $this->assertNotNull($category->id);
        $this->assertEquals('Test Category', $category->name);

        $category->update(['name' => 'Updated Category']);
        $this->assertEquals('Updated Category', $category->fresh()->name);

        $category->delete();
        $this->assertNull(Category::find($category->id));
    }

    /** @test */
    public function existing_category_records_remain_valid(): void
    {
        $this->actAsAdminA();

        $category = Category::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Legacy Category',
            'description' => 'Legacy description',
        ]);

        $this->assertNotNull($category->id);
        $this->assertEquals('Legacy Category', $category->name);
        $this->assertNull($category->image);
        $this->assertFalse($category->featured);
        $this->assertEquals(0, $category->sort_order);
        $this->assertTrue($category->is_active);
    }

    /** @test */
    public function slug_generation_works(): void
    {
        $this->actAsAdminA();

        $category = Category::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Electronics',
        ]);

        $this->assertEquals('electronics', $category->slug);
    }

    /** @test */
    public function slug_uniqueness_is_tenant_scoped(): void
    {
        $this->actAsAdminA();

        $catA = Category::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Fashion',
        ]);

        $this->actAsAdminB();

        $catB = Category::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Fashion',
        ]);

        $this->assertEquals('fashion', $catA->slug);
        $this->assertEquals('fashion', $catB->slug);
    }

    /** @test */
    public function same_slug_can_exist_in_different_tenants(): void
    {
        $this->actAsAdminA();

        $catA = Category::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Shared Name',
        ]);

        $this->actAsAdminB();

        $catB = Category::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Shared Name',
        ]);

        $this->assertEquals('shared-name', $catA->slug);
        $this->assertEquals('shared-name', $catB->slug);
        $this->assertNotEquals($catA->id, $catB->id);
    }

    /** @test */
    public function parent_category_works(): void
    {
        $this->actAsAdminA();

        $parent = Category::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Parent Category',
        ]);

        $child = Category::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Child Category',
            'parent_id' => $parent->id,
        ]);

        $this->assertEquals($parent->id, $child->parent_id);
        $this->assertEquals('Parent Category', $child->parent->name);
        $this->assertCount(1, $parent->children);
        $this->assertEquals('Child Category', $parent->children->first()->name);
    }

    /** @test */
    public function cross_tenant_parent_assignment_is_rejected(): void
    {
        $this->actAsAdminA();

        $parentA = Category::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Parent A',
        ]);

        $this->actAsAdminB();

        $childB = Category::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Child B',
        ]);

        $this->actingAs($this->adminB);
        \App\Models\Tenant::setCurrent($this->tenantB);

        $request = new \App\Http\Requests\UpdateCategoryRequest();
        $request->merge(['parent_id' => $parentA->id]);

        $validator = \Illuminate\Support\Facades\Validator::make(
            ['parent_id' => $parentA->id],
            ['parent_id' => 'nullable|integer|exists:categories,id']
        );

        $validator->after(function ($validator) use ($parentA) {
            $parent = \App\Models\Category::withoutGlobalScopes()->find($parentA->id);
            if ($parent && $parent->tenant_id !== \App\Models\Tenant::getCurrent()?->id) {
                $validator->errors()->add('parent_id', 'The selected parent category does not belong to your store.');
            }
        });

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('parent_id'));
    }

    /** @test */
    public function self_parent_assignment_is_rejected(): void
    {
        $this->actAsAdminA();

        $category = Category::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Self Parent Test',
        ]);

        $this->assertTrue($category->hasCircularReference($category->id));
    }

    /** @test */
    public function circular_hierarchy_is_rejected(): void
    {
        $this->actAsAdminA();

        $parent = Category::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Grandparent',
        ]);

        $child = Category::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Child',
            'parent_id' => $parent->id,
        ]);

        $grandchild = Category::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Grandchild',
            'parent_id' => $child->id,
        ]);

        $this->assertTrue($grandchild->hasCircularReference($parent->id));
        $this->assertFalse($grandchild->hasCircularReference($child->id));
    }

    /** @test */
    public function category_image_upload_works(): void
    {
        Storage::fake('public');
        config(['filesystems.default' => 'public']);

        $this->actAsAdminA();

        $category = Category::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Image Test Category',
        ]);

        $file = UploadedFile::fake()->image('category.jpg', 400, 400);

        $category->image = $file->store('categories', 'public');
        $category->save();

        $this->assertNotNull($category->fresh()->image);
        $this->assertNotNull($category->image_url);
    }

    /** @test */
    public function category_image_replacement_works(): void
    {
        Storage::fake('public');
        config(['filesystems.default' => 'public']);

        $this->actAsAdminA();

        $category = Category::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Replace Image Test',
            'image' => 'categories/old-image.jpg',
        ]);

        $oldImage = $category->image;

        $file = UploadedFile::fake()->image('new-category.jpg', 400, 400);
        $newImagePath = $file->store('categories', 'public');

        $category->image = $newImagePath;
        $category->save();

        $this->assertNotEquals($oldImage, $category->fresh()->image);
        $this->assertEquals($newImagePath, $category->fresh()->image);
    }

    /** @test */
    public function category_image_removal_works(): void
    {
        $this->actAsAdminA();

        $category = Category::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Remove Image Test',
            'image' => 'categories/test-image.jpg',
        ]);

        $this->assertNotNull($category->image);

        $category->image = null;
        $category->save();

        $this->assertNull($category->fresh()->image);
    }

    /** @test */
    public function uploaded_media_is_tenant_safe(): void
    {
        $this->actAsAdminA();

        $categoryA = Category::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Tenant A Image',
            'image' => 'categories/tenant-a-image.jpg',
        ]);

        $this->actAsAdminB();

        $categoryB = Category::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Tenant B Image',
            'image' => 'categories/tenant-b-image.jpg',
        ]);

        $this->assertNotEquals($categoryA->image, $categoryB->image);
        $this->assertEquals('categories/tenant-a-image.jpg', $categoryA->image);
        $this->assertEquals('categories/tenant-b-image.jpg', $categoryB->image);
    }

    /** @test */
    public function featured_flag_works(): void
    {
        $this->actAsAdminA();

        $category = Category::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Featured Test',
            'featured' => true,
        ]);

        $this->assertTrue($category->featured);
        $this->assertEquals(1, $category->fresh()->featured);

        $category->update(['featured' => false]);
        $this->assertFalse($category->fresh()->featured);
    }

    /** @test */
    public function sort_order_works(): void
    {
        $this->actAsAdminA();

        $cat1 = Category::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Category 1',
            'sort_order' => 3,
        ]);

        $cat2 = Category::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Category 2',
            'sort_order' => 1,
        ]);

        $cat3 = Category::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Category 3',
            'sort_order' => 2,
        ]);

        $sorted = Category::forCurrentTenant()->sorted()->get();

        $this->assertEquals('Category 2', $sorted[0]->name);
        $this->assertEquals('Category 3', $sorted[1]->name);
        $this->assertEquals('Category 1', $sorted[2]->name);
    }

    /** @test */
    public function active_inactive_works(): void
    {
        $this->actAsAdminA();

        $active = Category::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Active Category',
            'is_active' => true,
        ]);

        $inactive = Category::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Inactive Category',
            'is_active' => false,
        ]);

        $this->assertTrue($active->is_active);
        $this->assertFalse($inactive->is_active);

        $activeOnly = Category::forCurrentTenant()->active()->get();
        $this->assertTrue($activeOnly->contains('id', $active->id));
        $this->assertFalse($activeOnly->contains('id', $inactive->id));
    }

    /** @test */
    public function existing_product_category_relationship_still_works(): void
    {
        $this->actAsAdminA();

        $category = Category::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Product Category',
        ]);

        $product = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Test Product',
            'price' => 100,
            'category_id' => $category->id,
            'status' => 'active',
            'type' => 'single',
        ]);

        $this->assertEquals($category->id, $product->category->id);
        $this->assertCount(1, $category->products);
        $this->assertEquals('Test Product', $category->products->first()->name);
    }

    /** @test */
    public function category_authorization_is_enforced(): void
    {
        $this->actAsAdminA();

        $category = Category::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Auth Test',
        ]);

        $userNoPerms = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'No Perms User',
            'email' => 'noperms@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $this->actingAs($userNoPerms);

        $response = $this->get('/admin/categories');
        $response->assertStatus(403);

        $response = $this->get('/admin/categories/create');
        $response->assertStatus(403);

        $response = $this->put("/admin/categories/{$category->id}", [
            'name' => 'Hacked',
        ]);
        $response->assertStatus(403);
    }

    /** @test */
    public function tenant_isolation_is_enforced(): void
    {
        $this->actAsAdminA();

        $catA = Category::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Tenant A Category',
        ]);

        $this->actAsAdminB();

        $catB = Category::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Tenant B Category',
        ]);

        $this->actingAs($this->adminA);
        \App\Models\Tenant::setCurrent($this->tenantA);

        $categories = Category::forCurrentTenant()->get();
        $this->assertTrue($categories->contains('id', $catA->id));
        $this->assertFalse($categories->contains('id', $catB->id));
    }

    /** @test */
    public function featured_scope_works(): void
    {
        $this->actAsAdminA();

        Category::create(['tenant_id' => $this->tenantA->id, 'name' => 'Featured Cat', 'featured' => true]);
        Category::create(['tenant_id' => $this->tenantA->id, 'name' => 'Not Featured', 'featured' => false]);

        $featured = Category::forCurrentTenant()->featured()->get();
        $this->assertCount(1, $featured);
        $this->assertEquals('Featured Cat', $featured->first()->name);
    }

    /** @test */
    public function category_featured_can_be_changed_without_affecting_active(): void
    {
        $this->actAsAdminA();

        $category = Category::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Toggle Test',
            'featured' => false,
            'is_active' => true,
        ]);

        $category->update(['featured' => true]);
        $this->assertTrue($category->fresh()->featured);
        $this->assertTrue($category->fresh()->is_active);

        $category->update(['featured' => false]);
        $this->assertFalse($category->fresh()->featured);
        $this->assertTrue($category->fresh()->is_active);

        $category->update(['is_active' => false]);
        $this->assertFalse($category->fresh()->is_active);
        $this->assertFalse($category->fresh()->featured);
    }

    /** @test */
    public function category_image_url_accessor_works(): void
    {
        $this->actAsAdminA();

        $category = Category::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Image URL Test',
            'image' => null,
        ]);

        $this->assertNull($category->image_url);

        $category->update(['image' => 'categories/test.jpg']);

        Storage::fake('public');
        config(['filesystems.default' => 'public']);

        $url = $category->fresh()->image_url;
        $this->assertNotNull($url);
    }

    /** @test */
    public function category_sorted_scope_orders_correctly(): void
    {
        $this->actAsAdminA();

        Category::create(['tenant_id' => $this->tenantA->id, 'name' => 'Zebra', 'sort_order' => 10]);
        Category::create(['tenant_id' => $this->tenantA->id, 'name' => 'Apple', 'sort_order' => 5]);
        Category::create(['tenant_id' => $this->tenantA->id, 'name' => 'Mango', 'sort_order' => 5]);

        $sorted = Category::forCurrentTenant()->sorted()->get();

        $this->assertEquals('Apple', $sorted[0]->name);
        $this->assertEquals('Mango', $sorted[1]->name);
        $this->assertEquals('Zebra', $sorted[2]->name);
    }

    /** @test */
    public function category_inactive_does_not_break_product_relationship(): void
    {
        $this->actAsAdminA();

        $category = Category::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Inactive Product Test',
            'is_active' => true,
        ]);

        $product = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Product Under Category',
            'price' => 50,
            'category_id' => $category->id,
            'status' => 'active',
            'type' => 'single',
        ]);

        $category->update(['is_active' => false]);

        $this->assertEquals($category->id, $product->fresh()->category_id);
        $this->assertEquals('Inactive Product Test', $product->fresh()->category->name);
    }
}
