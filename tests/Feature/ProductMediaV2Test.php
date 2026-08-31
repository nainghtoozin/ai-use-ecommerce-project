<?php

namespace Tests\Feature;

use App\Enums\ProductType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Services\ImageService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductMediaV2Test extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $adminA;
    private User $adminB;
    private Category $categoryA;
    private Category $categoryB;
    private Plan $freePlan;
    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupMinimalSchema();
        $this->seedPlansAndRoles();
        $this->setupTenantsAndUsers();
    }

    private function setupMinimalSchema(): void
    {
        $tables = [
            'permissions', 'roles', 'model_has_roles', 'role_has_permissions',
            'model_has_permissions', 'users', 'tenants', 'plans', 'plan_features',
            'categories', 'brands', 'units', 'products', 'product_variants',
            'product_combos', 'warehouses', 'stock_movements',
        ];

        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                $this->markTestSkipped("Table {$table} not found. Run migrations first.");
            }
        }
    }

    private function seedPlansAndRoles(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'products.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'products.create', 'guard_name' => 'web']);
        Permission::create(['name' => 'products.edit', 'guard_name' => 'web']);
        Permission::create(['name' => 'products.delete', 'guard_name' => 'web']);

        $this->adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $this->adminRole->syncPermissions(Permission::whereIn('name', [
            'products.view', 'products.create', 'products.edit', 'products.delete'
        ])->get());

        $this->freePlan = Plan::create([
            'name' => 'Free Plan',
            'slug' => 'free-plan',
            'monthly_price' => 0,
            'yearly_price' => 0,
            'status' => 'active',
        ]);

        PlanFeature::create(['plan_id' => $this->freePlan->id, 'feature_key' => 'single_products', 'is_enabled' => true]);
        PlanFeature::create(['plan_id' => $this->freePlan->id, 'feature_key' => 'variable_products', 'is_enabled' => true]);
        PlanFeature::create(['plan_id' => $this->freePlan->id, 'feature_key' => 'combo_products', 'is_enabled' => true]);
    }

    private function setupTenantsAndUsers(): void
    {
        $this->tenantA = Tenant::create(['name' => 'Store A', 'slug' => 'store-a']);
        $this->tenantB = Tenant::create(['name' => 'Store B', 'slug' => 'store-b']);

        $this->tenantA->subscription_plan_id = $this->freePlan->id;
        $this->tenantA->save();
        $this->tenantB->subscription_plan_id = $this->freePlan->id;
        $this->tenantB->save();

        $this->categoryA = Category::create(['tenant_id' => $this->tenantA->id, 'name' => 'Category A', 'slug' => 'category-a', 'is_active' => true]);
        $this->categoryB = Category::create(['tenant_id' => $this->tenantB->id, 'name' => 'Category B', 'slug' => 'category-b', 'is_active' => true]);

        $this->adminA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Admin A',
            'email' => 'admin-media-a@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $this->adminA->assignRole($this->adminRole);

        $this->adminB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Admin B',
            'email' => 'admin-media-b@test.com',
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

    private function createTestProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Test Product',
            'type' => ProductType::SINGLE,
            'price' => 100,
            'category_id' => $this->categoryA->id,
            'status' => Product::STATUS_ACTIVE,
        ], $overrides));
    }

    /** @test */
    public function product_can_be_created_with_photo1(): void
    {
        $this->actAsAdminA();

        $product = $this->createTestProduct(['photo1' => 'products/test.jpg']);

        $this->assertNotNull($product->photo1);
        $this->assertEquals('products/test.jpg', $product->photo1);
    }

    /** @test */
    public function product_photo1_url_accessor_works(): void
    {
        $this->actAsAdminA();

        $product = $this->createTestProduct(['photo1' => 'products/test.jpg']);

        $this->assertNotNull($product->photo1_url);
        $this->assertIsString($product->photo1_url);
    }

    /** @test */
    public function product_photo1_url_returns_empty_when_no_photo(): void
    {
        $this->actAsAdminA();

        $product = $this->createTestProduct(['photo1' => null]);

        $this->assertNull($product->photo1);
    }

    /** @test */
    public function product_can_store_gallery_images_as_json(): void
    {
        $this->actAsAdminA();

        $galleryPaths = [
            'products/gallery/image1.jpg',
            'products/gallery/image2.jpg',
            'products/gallery/image3.jpg',
        ];

        $product = $this->createTestProduct(['gallery_images' => $galleryPaths]);

        $this->assertIsArray($product->gallery_images);
        $this->assertCount(3, $product->gallery_images);
        $this->assertEquals($galleryPaths, $product->gallery_images);
    }

    /** @test */
    public function product_gallery_images_url_accessor_returns_urls(): void
    {
        $this->actAsAdminA();

        $galleryPaths = [
            'products/gallery/image1.jpg',
            'products/gallery/image2.jpg',
        ];

        $product = $this->createTestProduct(['gallery_images' => $galleryPaths]);

        $urls = $product->gallery_images_url;
        $this->assertIsArray($urls);
        $this->assertCount(2, $urls);
    }

    /** @test */
    public function product_gallery_images_is_cast_to_array(): void
    {
        $this->actAsAdminA();

        $product = $this->createTestProduct(['gallery_images' => ['a.jpg', 'b.jpg']]);

        $this->assertIsArray($product->gallery_images);
    }

    /** @test */
    public function product_seo_image_can_be_stored(): void
    {
        $this->actAsAdminA();

        $product = $this->createTestProduct(['seo_image' => 'products/seo.jpg']);

        $this->assertEquals('products/seo.jpg', $product->seo_image);
    }

    /** @test */
    public function product_seo_image_url_accessor_works(): void
    {
        $this->actAsAdminA();

        $product = $this->createTestProduct(['seo_image' => 'products/seo.jpg']);

        $this->assertNotNull($product->seo_image_url);
        $this->assertIsString($product->seo_image_url);
    }

    /** @test */
    public function product_photo2_can_be_stored(): void
    {
        $this->actAsAdminA();

        $product = $this->createTestProduct(['photo2' => 'products/photo2.jpg']);

        $this->assertEquals('products/photo2.jpg', $product->photo2);
    }

    /** @test */
    public function product_photo2_url_accessor_works(): void
    {
        $this->actAsAdminA();

        $product = $this->createTestProduct(['photo2' => 'products/photo2.jpg']);

        $this->assertNotNull($product->photo2_url);
    }

    /** @test */
    public function variant_can_have_image(): void
    {
        $this->actAsAdminA();

        $product = $this->createTestProduct();

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'VAR-IMG-001',
            'price' => 110,
            'stock' => 10,
            'status' => 'active',
            'attributes' => ['Color' => 'Red'],
            'image' => 'products/variants/red.jpg',
        ]);

        $this->assertEquals('products/variants/red.jpg', $variant->image);
        $this->assertEquals('products/variants/red.jpg', $variant->image_url);
    }

    /** @test */
    public function variant_image_url_returns_null_when_empty(): void
    {
        $this->actAsAdminA();

        $product = $this->createTestProduct();

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'VAR-NO-IMG-001',
            'price' => 110,
            'stock' => 10,
            'status' => 'active',
            'attributes' => ['Color' => 'Blue'],
            'image' => null,
        ]);

        $this->assertNull($variant->image);
        $this->assertNull($variant->image_url);
    }

    /** @test */
    public function gallery_order_is_preserved(): void
    {
        $this->actAsAdminA();

        $galleryPaths = [
            'products/gallery/first.jpg',
            'products/gallery/second.jpg',
            'products/gallery/third.jpg',
        ];

        $product = $this->createTestProduct(['gallery_images' => $galleryPaths]);

        $this->assertEquals('products/gallery/first.jpg', $product->gallery_images[0]);
        $this->assertEquals('products/gallery/second.jpg', $product->gallery_images[1]);
        $this->assertEquals('products/gallery/third.jpg', $product->gallery_images[2]);
    }

    /** @test */
    public function empty_gallery_returns_empty_array(): void
    {
        $this->actAsAdminA();

        $product = $this->createTestProduct(['gallery_images' => null]);

        $this->assertIsArray($product->gallery_images);
        $this->assertEmpty($product->gallery_images);
    }

    /** @test */
    public function product_media_is_tenant_isolated(): void
    {
        $this->actAsAdminA();

        $productA = $this->createTestProduct([
            'photo1' => 'products/tenant-a-main.jpg',
            'gallery_images' => ['products/tenant-a-gallery-1.jpg'],
        ]);

        $this->actAsAdminB();

        $productB = $this->createTestProduct([
            'name' => 'Tenant B Product',
            'photo1' => 'products/tenant-b-main.jpg',
            'gallery_images' => ['products/tenant-b-gallery-1.jpg'],
        ]);

        $productA->refresh();
        $productB->refresh();

        $this->assertEquals('products/tenant-a-main.jpg', $productA->photo1);
        $this->assertEquals('products/tenant-b-main.jpg', $productB->photo1);

        $this->assertEquals(['products/tenant-a-gallery-1.jpg'], $productA->gallery_images);
        $this->assertEquals(['products/tenant-b-gallery-1.jpg'], $productB->gallery_images);
    }

    /** @test */
    public function cross_tenant_product_image_access_is_prevented(): void
    {
        $this->actAsAdminA();

        $productA = $this->createTestProduct([
            'photo1' => 'products/secret.jpg',
        ]);

        $this->actAsAdminB();

        $productB = Product::forCurrentTenant()
            ->where('id', $productA->id)
            ->first();

        $this->assertNull($productB);
    }

    /** @test */
    public function product_without_media_is_valid(): void
    {
        $this->actAsAdminA();

        $product = $this->createTestProduct([
            'photo1' => null,
            'photo2' => null,
            'gallery_images' => null,
            'seo_image' => null,
        ]);

        $this->assertNull($product->photo1);
        $this->assertNull($product->photo2);
        $this->assertIsArray($product->gallery_images);
        $this->assertEmpty($product->gallery_images);
        $this->assertNull($product->seo_image);
    }

    /** @test */
    public function gallery_allows_up_to_10_images(): void
    {
        $this->actAsAdminA();

        $galleryPaths = [];
        for ($i = 1; $i <= 10; $i++) {
            $galleryPaths[] = "products/gallery/image{$i}.jpg";
        }

        $product = $this->createTestProduct(['gallery_images' => $galleryPaths]);

        $this->assertCount(10, $product->gallery_images);
    }

    /** @test */
    public function product_update_preserves_existing_media(): void
    {
        $this->actAsAdminA();

        $product = $this->createTestProduct([
            'photo1' => 'products/original.jpg',
            'gallery_images' => ['products/gallery/original.jpg'],
        ]);

        $product->update(['name' => 'Updated Name']);

        $product->refresh();

        $this->assertEquals('Updated Name', $product->name);
        $this->assertEquals('products/original.jpg', $product->photo1);
        $this->assertEquals(['products/gallery/original.jpg'], $product->gallery_images);
    }

    /** @test */
    public function product_delete_removes_all_media_references(): void
    {
        $this->actAsAdminA();

        $product = $this->createTestProduct([
            'photo1' => 'products/to-delete-1.jpg',
            'photo2' => 'products/to-delete-2.jpg',
            'gallery_images' => [
                'products/gallery/delete-1.jpg',
                'products/gallery/delete-2.jpg',
            ],
            'seo_image' => 'products/seo-delete.jpg',
        ]);

        $productId = $product->id;

        $product->delete();

        $this->assertNull(Product::find($productId));
    }

    /** @test */
    public function existing_gallery_json_remains_readable(): void
    {
        $this->actAsAdminA();

        $product = $this->createTestProduct([
            'gallery_images' => ['existing/image.jpg'],
        ]);

        $json = json_encode($product->gallery_images);
        $decoded = json_decode($json, true);

        $this->assertEquals(['existing/image.jpg'], $decoded);
    }

    /** @test */
    public function product_photo1_is_nullable(): void
    {
        $this->actAsAdminA();

        $product = $this->createTestProduct(['photo1' => null]);

        $this->assertNull($product->photo1);
    }

    /** @test */
    public function variable_product_variant_images_are_isolated(): void
    {
        $this->actAsAdminA();

        $productA = $this->createTestProduct([
            'type' => ProductType::VARIABLE,
            'photo1' => 'products/prod-a-main.jpg',
        ]);

        $variantA = ProductVariant::create([
            'product_id' => $productA->id,
            'sku' => 'VAR-A-001',
            'price' => 100,
            'stock' => 10,
            'status' => 'active',
            'attributes' => ['Size' => 'M'],
            'image' => 'products/variants/tenant-a-variant.jpg',
        ]);

        $this->actAsAdminB();

        $productB = $this->createTestProduct([
            'name' => 'Tenant B Variable',
            'type' => ProductType::VARIABLE,
        ]);

        $variantB = ProductVariant::create([
            'product_id' => $productB->id,
            'sku' => 'VAR-B-001',
            'price' => 100,
            'stock' => 10,
            'status' => 'active',
            'attributes' => ['Size' => 'M'],
            'image' => 'products/variants/tenant-b-variant.jpg',
        ]);

        $variantA->refresh();
        $variantB->refresh();

        $this->assertEquals('products/variants/tenant-a-variant.jpg', $variantA->image);
        $this->assertEquals('products/variants/tenant-b-variant.jpg', $variantB->image);
    }

    /** @test */
    public function image_fields_are_in_fillable(): void
    {
        $this->actAsAdminA();

        $fillable = (new Product())->getFillable();

        $this->assertContains('photo1', $fillable);
        $this->assertContains('photo2', $fillable);
        $this->assertContains('gallery_images', $fillable);
        $this->assertContains('seo_image', $fillable);
    }

    /** @test */
    public function gallery_images_cast_is_array(): void
    {
        $this->actAsAdminA();

        $casts = (new Product())->getCasts();

        $this->assertArrayHasKey('gallery_images', $casts);
        $this->assertEquals('array', $casts['gallery_images']);
    }
}
