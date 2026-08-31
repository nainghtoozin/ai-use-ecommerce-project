<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BrandV2Test extends TestCase
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

        Permission::create(['name' => 'brands.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'brands.create', 'guard_name' => 'web']);
        Permission::create(['name' => 'brands.update', 'guard_name' => 'web']);
        Permission::create(['name' => 'brands.delete', 'guard_name' => 'web']);

        $this->adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $this->adminRole->syncPermissions(Permission::whereIn('name', [
            'brands.view', 'brands.create', 'brands.update', 'brands.delete'
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
            'email' => 'admin-brand-a@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $this->adminA->assignRole($this->adminRole);

        $this->adminB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Admin B',
            'email' => 'admin-brand-b@test.com',
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
    public function brand_has_banner_field(): void
    {
        $this->actAsAdminA();
        $this->assertTrue(Schema::hasColumn('brands', 'banner'));
    }

    /** @test */
    public function brand_has_featured_field(): void
    {
        $this->actAsAdminA();
        $this->assertTrue(Schema::hasColumn('brands', 'featured'));
    }

    /** @test */
    public function brand_has_sort_order_field(): void
    {
        $this->actAsAdminA();
        $this->assertTrue(Schema::hasColumn('brands', 'sort_order'));
    }

    /** @test */
    public function brand_crud_still_works(): void
    {
        $this->actAsAdminA();

        $brand = Brand::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Test Brand',
            'description' => 'Test description',
        ]);

        $this->assertNotNull($brand->id);
        $this->assertEquals('Test Brand', $brand->name);

        $brand->update(['name' => 'Updated Brand']);
        $this->assertEquals('Updated Brand', $brand->fresh()->name);

        $brand->delete();
        $this->assertNull(Brand::find($brand->id));
    }

    /** @test */
    public function existing_brand_records_remain_valid(): void
    {
        $this->actAsAdminA();

        $brand = Brand::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Legacy Brand',
            'slug' => 'legacy-brand',
            'description' => 'Legacy description',
        ]);

        $this->assertNotNull($brand->id);
        $this->assertEquals('Legacy Brand', $brand->name);
        $this->assertNull($brand->banner);
        $this->assertFalse($brand->featured);
        $this->assertEquals(0, $brand->sort_order);
        $this->assertTrue($brand->is_active);
    }

    /** @test */
    public function slug_generation_works(): void
    {
        $this->actAsAdminA();

        $brand = Brand::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Nike Shoes',
        ]);

        $this->assertEquals('nike-shoes', $brand->slug);
    }

    /** @test */
    public function slug_uniqueness_is_tenant_scoped(): void
    {
        $this->actAsAdminA();

        $brandA = Brand::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Adidas',
        ]);

        $this->actAsAdminB();

        $brandB = Brand::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Adidas',
        ]);

        $this->assertEquals('adidas', $brandA->slug);
        $this->assertEquals('adidas', $brandB->slug);
    }

    /** @test */
    public function same_slug_can_exist_in_different_tenants(): void
    {
        $this->actAsAdminA();

        $brandA = Brand::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Shared Brand',
        ]);

        $this->actAsAdminB();

        $brandB = Brand::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Shared Brand',
        ]);

        $this->assertEquals('shared-brand', $brandA->slug);
        $this->assertEquals('shared-brand', $brandB->slug);
        $this->assertNotEquals($brandA->id, $brandB->id);
    }

    /** @test */
    public function logo_upload_works(): void
    {
        Storage::fake('public');
        config(['filesystems.default' => 'public']);

        $this->actAsAdminA();

        $brand = Brand::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Logo Test Brand',
        ]);

        $file = UploadedFile::fake()->image('logo.jpg', 200, 200);
        $brand->logo = $file->store('brands', 'public');
        $brand->save();

        $this->assertNotNull($brand->fresh()->logo);
        $this->assertNotNull($brand->logo_url);
    }

    /** @test */
    public function logo_replacement_works(): void
    {
        Storage::fake('public');
        config(['filesystems.default' => 'public']);

        $this->actAsAdminA();

        $brand = Brand::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Replace Logo Test',
            'logo' => 'brands/old-logo.jpg',
        ]);

        $oldLogo = $brand->logo;

        $file = UploadedFile::fake()->image('new-logo.jpg', 200, 200);
        $newLogoPath = $file->store('brands', 'public');

        $brand->logo = $newLogoPath;
        $brand->save();

        $this->assertNotEquals($oldLogo, $brand->fresh()->logo);
        $this->assertEquals($newLogoPath, $brand->fresh()->logo);
    }

    /** @test */
    public function logo_removal_works(): void
    {
        $this->actAsAdminA();

        $brand = Brand::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Remove Logo Test',
            'logo' => 'brands/test-logo.jpg',
        ]);

        $this->assertNotNull($brand->logo);

        $brand->logo = null;
        $brand->save();

        $this->assertNull($brand->fresh()->logo);
    }

    /** @test */
    public function banner_upload_works(): void
    {
        Storage::fake('public');
        config(['filesystems.default' => 'public']);

        $this->actAsAdminA();

        $brand = Brand::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Banner Test Brand',
        ]);

        $file = UploadedFile::fake()->image('banner.jpg', 800, 400);
        $brand->banner = $file->store('brands/banners', 'public');
        $brand->save();

        $this->assertNotNull($brand->fresh()->banner);
        $this->assertNotNull($brand->banner_url);
    }

    /** @test */
    public function banner_replacement_works(): void
    {
        Storage::fake('public');
        config(['filesystems.default' => 'public']);

        $this->actAsAdminA();

        $brand = Brand::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Replace Banner Test',
            'banner' => 'brands/banners/old-banner.jpg',
        ]);

        $oldBanner = $brand->banner;

        $file = UploadedFile::fake()->image('new-banner.jpg', 800, 400);
        $newBannerPath = $file->store('brands/banners', 'public');

        $brand->banner = $newBannerPath;
        $brand->save();

        $this->assertNotEquals($oldBanner, $brand->fresh()->banner);
        $this->assertEquals($newBannerPath, $brand->fresh()->banner);
    }

    /** @test */
    public function banner_removal_works(): void
    {
        $this->actAsAdminA();

        $brand = Brand::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Remove Banner Test',
            'banner' => 'brands/banners/test-banner.jpg',
        ]);

        $this->assertNotNull($brand->banner);

        $brand->banner = null;
        $brand->save();

        $this->assertNull($brand->fresh()->banner);
    }

    /** @test */
    public function media_is_tenant_safe(): void
    {
        $this->actAsAdminA();

        $brandA = Brand::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Tenant A Brand',
            'logo' => 'brands/tenant-a-logo.jpg',
            'banner' => 'brands/banners/tenant-a-banner.jpg',
        ]);

        $this->actAsAdminB();

        $brandB = Brand::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Tenant B Brand',
            'logo' => 'brands/tenant-b-logo.jpg',
            'banner' => 'brands/banners/tenant-b-banner.jpg',
        ]);

        $this->assertNotEquals($brandA->logo, $brandB->logo);
        $this->assertNotEquals($brandA->banner, $brandB->banner);
    }

    /** @test */
    public function featured_flag_works(): void
    {
        $this->actAsAdminA();

        $brand = Brand::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Featured Test',
            'featured' => true,
        ]);

        $this->assertTrue($brand->featured);
        $this->assertEquals(1, $brand->fresh()->featured);

        $brand->update(['featured' => false]);
        $this->assertFalse($brand->fresh()->featured);
    }

    /** @test */
    public function sort_order_works(): void
    {
        $this->actAsAdminA();

        $brand1 = Brand::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Brand 1',
            'sort_order' => 3,
        ]);

        $brand2 = Brand::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Brand 2',
            'sort_order' => 1,
        ]);

        $brand3 = Brand::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Brand 3',
            'sort_order' => 2,
        ]);

        $sorted = Brand::forCurrentTenant()->sorted()->get();

        $this->assertEquals('Brand 2', $sorted[0]->name);
        $this->assertEquals('Brand 3', $sorted[1]->name);
        $this->assertEquals('Brand 1', $sorted[2]->name);
    }

    /** @test */
    public function active_inactive_works(): void
    {
        $this->actAsAdminA();

        $active = Brand::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Active Brand',
            'is_active' => true,
        ]);

        $inactive = Brand::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Inactive Brand',
            'is_active' => false,
        ]);

        $this->assertTrue($active->is_active);
        $this->assertFalse($inactive->is_active);

        $activeOnly = Brand::forCurrentTenant()->active()->get();
        $this->assertTrue($activeOnly->contains('id', $active->id));
        $this->assertFalse($activeOnly->contains('id', $inactive->id));
    }

    /** @test */
    public function existing_product_brand_relationship_still_works(): void
    {
        $this->actAsAdminA();

        $brand = Brand::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Product Brand',
        ]);

        $product = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Test Product',
            'price' => 100,
            'brand_id' => $brand->id,
            'status' => 'active',
            'type' => 'single',
        ]);

        $this->assertEquals($brand->id, $product->brand->id);
        $this->assertCount(1, $brand->products);
        $this->assertEquals('Test Product', $brand->products->first()->name);
    }

    /** @test */
    public function brand_authorization_is_enforced(): void
    {
        $this->actAsAdminA();

        $brand = Brand::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Auth Test Brand',
        ]);

        $userNoPerms = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'No Perms User',
            'email' => 'noperms-brand@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $this->actingAs($userNoPerms);

        $response = $this->get('/admin/brands');
        $response->assertStatus(403);

        $response = $this->get('/admin/brands/create');
        $response->assertStatus(403);

        $response = $this->put("/admin/brands/{$brand->id}", [
            'name' => 'Hacked',
        ]);
        $response->assertStatus(403);
    }

    /** @test */
    public function tenant_isolation_is_enforced(): void
    {
        $this->actAsAdminA();

        $brandA = Brand::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Tenant A Brand',
        ]);

        $this->actAsAdminB();

        $brandB = Brand::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Tenant B Brand',
        ]);

        $this->actingAs($this->adminA);
        \App\Models\Tenant::setCurrent($this->tenantA);

        $brands = Brand::forCurrentTenant()->get();
        $this->assertTrue($brands->contains('id', $brandA->id));
        $this->assertFalse($brands->contains('id', $brandB->id));
    }

    /** @test */
    public function featured_scope_works(): void
    {
        $this->actAsAdminA();

        Brand::create(['tenant_id' => $this->tenantA->id, 'name' => 'Featured Brand', 'featured' => true]);
        Brand::create(['tenant_id' => $this->tenantA->id, 'name' => 'Not Featured', 'featured' => false]);

        $featured = Brand::forCurrentTenant()->featured()->get();
        $this->assertCount(1, $featured);
        $this->assertEquals('Featured Brand', $featured->first()->name);
    }

    /** @test */
    public function brand_featured_can_be_changed_without_affecting_active(): void
    {
        $this->actAsAdminA();

        $brand = Brand::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Toggle Test',
            'featured' => false,
            'is_active' => true,
        ]);

        $brand->update(['featured' => true]);
        $this->assertTrue($brand->fresh()->featured);
        $this->assertTrue($brand->fresh()->is_active);

        $brand->update(['featured' => false]);
        $this->assertFalse($brand->fresh()->featured);
        $this->assertTrue($brand->fresh()->is_active);

        $brand->update(['is_active' => false]);
        $this->assertFalse($brand->fresh()->is_active);
        $this->assertFalse($brand->fresh()->featured);
    }

    /** @test */
    public function brand_banner_url_accessor_works(): void
    {
        $this->actAsAdminA();

        $brand = Brand::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Banner URL Test',
            'banner' => null,
        ]);

        $this->assertNull($brand->banner_url);

        $brand->update(['banner' => 'brands/banners/test.jpg']);
        $this->assertNotNull($brand->fresh()->banner_url);
    }

    /** @test */
    public function brand_sorted_scope_orders_correctly(): void
    {
        $this->actAsAdminA();

        Brand::create(['tenant_id' => $this->tenantA->id, 'name' => 'Zebra Brand', 'sort_order' => 10]);
        Brand::create(['tenant_id' => $this->tenantA->id, 'name' => 'Apple Brand', 'sort_order' => 5]);
        Brand::create(['tenant_id' => $this->tenantA->id, 'name' => 'Mango Brand', 'sort_order' => 5]);

        $sorted = Brand::forCurrentTenant()->sorted()->get();

        $this->assertEquals('Apple Brand', $sorted[0]->name);
        $this->assertEquals('Mango Brand', $sorted[1]->name);
        $this->assertEquals('Zebra Brand', $sorted[2]->name);
    }

    /** @test */
    public function brand_inactive_does_not_break_product_relationship(): void
    {
        $this->actAsAdminA();

        $brand = Brand::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Inactive Product Test',
            'is_active' => true,
        ]);

        $product = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Product Under Brand',
            'price' => 50,
            'brand_id' => $brand->id,
            'status' => 'active',
            'type' => 'single',
        ]);

        $brand->update(['is_active' => false]);

        $this->assertEquals($brand->id, $product->fresh()->brand_id);
        $this->assertEquals('Inactive Product Test', $product->fresh()->brand->name);
    }

    /** @test */
    public function brand_delete_removes_logo_and_banner(): void
    {
        $this->actAsAdminA();

        $brand = Brand::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Delete Media Test',
            'logo' => 'brands/logo.jpg',
            'banner' => 'brands/banners/banner.jpg',
        ]);

        $logo = $brand->logo;
        $banner = $brand->banner;

        $brand->delete();

        $this->assertNull(Brand::find($brand->id));
    }
}
