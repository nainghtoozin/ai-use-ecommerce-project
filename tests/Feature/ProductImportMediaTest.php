<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Storefront;
use App\Models\StorefrontMedia;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Role;
use App\Services\ImageService;
use App\Services\ImportExport\MasterDataResolver;
use App\Services\ImportExport\ProductImportEngine;
use App\Services\ImportExport\FormatHandlers\ProductImportReader;
use App\Services\InventoryService;
use App\Services\SkuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProductImportMediaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['products.view', 'products.create', 'products.update', 'products.delete', 'settings.website'] as $perm) {
            Permission::create(['name' => $perm, 'guard_name' => 'web']);
        }

        $this->tenant = Tenant::create(['name' => 'Media Import Tenant', 'slug' => 'media-import-' . uniqid(), 'status' => 'active']);
        Storefront::withoutTenantScope()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);

        $role = Role::withoutTenantScope()->create(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        $role->syncPermissions(Permission::all());

        $this->admin = User::create([
            'name' => 'Import Admin',
            'email' => 'media-import-' . uniqid() . '@example.com',
            'tenant_id' => $this->tenant->id,
            'password' => bcrypt('password'),
        ]);
        $this->admin->assignRole('admin');

        Category::create(['tenant_id' => $this->tenant->id, 'name' => 'Media Cat', 'slug' => 'media-cat-' . uniqid()]);
        Brand::create(['tenant_id' => $this->tenant->id, 'name' => 'Media Brand', 'slug' => 'media-brand-' . uniqid(), 'is_active' => true]);
    }

    public function test_image_maps_to_photo1(): void
    {
        $this->makeMedia('primary.png', 'storefront-media/primary.png');

        $engine = $this->engine();
        $products = [$this->productRow('P1', ['image' => 'primary.png'])];

        $this->assertTrue($engine->validate($products, [])['valid']);
        $engine->import($products, [], 'create_new');

        $this->assertEquals('storefront-media/primary.png', Product::withoutTenantScope()->where('sku', 'P1')->value('photo1'));
    }

    public function test_secondary_image_maps_to_photo2(): void
    {
        $this->makeMedia('secondary.png', 'storefront-media/secondary.png');

        $engine = $this->engine();
        $products = [$this->productRow('P2', ['secondary_image' => 'secondary.png'])];

        $this->assertTrue($engine->validate($products, [])['valid']);
        $engine->import($products, [], 'create_new');

        $this->assertEquals('storefront-media/secondary.png', Product::withoutTenantScope()->where('sku', 'P2')->value('photo2'));
    }

    public function test_gallery_images_parsed_in_order_with_limit(): void
    {
        $this->makeMedia('g1.png', 'storefront-media/g1.png');
        $this->makeMedia('g2.png', 'storefront-media/g2.png');
        $this->makeMedia('g3.png', 'storefront-media/g3.png');

        $engine = $this->engine();
        $products = [$this->productRow('P3', ['gallery_images' => ' g2.png | g1.png || g3.png '])];

        $this->assertTrue($engine->validate($products, [])['valid']);
        $engine->import($products, [], 'create_new');

        $gallery = Product::withoutTenantScope()->where('sku', 'P3')->first()->gallery_images;
        $this->assertEquals(
            ['storefront-media/g2.png', 'storefront-media/g1.png', 'storefront-media/g3.png'],
            array_values($gallery)
        );
    }

    public function test_gallery_limit_exceeded_is_error(): void
    {
        $names = [];
        for ($i = 1; $i <= 11; $i++) {
            $this->makeMedia("g{$i}.png", "storefront-media/g{$i}.png");
            $names[] = "g{$i}.png";
        }

        $engine = $this->engine();
        $products = [$this->productRow('P4', ['gallery_images' => implode('|', $names)])];

        $validation = $engine->validate($products, []);
        $this->assertFalse($validation['valid']);
        $this->assertStringContainsString('more than 10', $validation['errors'][0]['error'] ?? '');
    }

    public function test_empty_image_fields_are_valid(): void
    {
        $engine = $this->engine();
        $products = [$this->productRow('P5', ['image' => '', 'secondary_image' => '', 'gallery_images' => ''])];

        $this->assertTrue($engine->validate($products, [])['valid']);
        $engine->import($products, [], 'create_new');

        $this->assertNull(Product::withoutTenantScope()->where('sku', 'P5')->value('photo1'));
    }

    public function test_missing_filename_reports_exact_value_and_blocks_import(): void
    {
        $engine = $this->engine();
        $products = [$this->productRow('P6', ['image' => 'ghost.png'])];

        $validation = $engine->validate($products, []);

        $this->assertFalse($validation['valid']);
        $error = $validation['errors'][0];
        $this->assertEquals('Products', $error['sheet']);
        $this->assertEquals('image', $error['column']);
        $this->assertStringContainsString('ghost.png', $error['error']);
        $this->assertEquals(2, $error['row']);
        $this->assertSame(0, Product::withoutTenantScope()->count());
    }

    public function test_exact_filename_match_is_case_insensitive(): void
    {
        $this->makeMedia('MyPhoto.PNG', 'storefront-media/MyPhoto.PNG');

        $engine = $this->engine();
        $products = [$this->productRow('P7', ['image' => 'myphoto.png'])];

        $this->assertTrue($engine->validate($products, [])['valid']);
    }

    public function test_same_image_reused_by_multiple_products(): void
    {
        $this->makeMedia('shared.png', 'storefront-media/shared.png');

        $engine = $this->engine();
        $products = [
            $this->productRow('S1', ['image' => 'shared.png']),
            $this->productRow('S2', ['image' => 'shared.png']),
            $this->productRow('S3', ['image' => 'shared.png']),
        ];

        $this->assertTrue($engine->validate($products, [])['valid']);
        $engine->import($products, [], 'create_new');

        $paths = Product::withoutTenantScope()->whereIn('sku', ['S1', 'S2', 'S3'])->pluck('photo1')->unique()->all();
        $this->assertEquals(['storefront-media/shared.png'], array_values($paths));
        $this->assertSame(1, StorefrontMedia::withoutTenantScope()->where('original_name', 'shared.png')->count());
    }

    public function test_variant_image_maps_to_variant(): void
    {
        $this->makeMedia('variant.png', 'storefront-media/variant.png');

        $engine = $this->engine();
        $products = [$this->productRow('PV', ['product_type' => 'variable'])];
        $variants = [
            $this->variantRow('PV', 'PV-RED', 'Color', 'Red', 'variant.png'),
            $this->variantRow('PV', 'PV-BLUE', 'Color', 'Blue', 'variant.png'),
        ];

        $this->assertTrue($engine->validate($products, $variants)['valid']);
        $engine->import($products, $variants, 'create_new');

        $parent = Product::withoutTenantScope()->where('sku', 'PV')->first();
        $this->assertSame(2, $parent->variants()->count());
        $this->assertEquals(
            ['storefront-media/variant.png'],
            $parent->variants()->pluck('image')->unique()->values()->all()
        );
    }

    public function test_missing_variant_image_is_error(): void
    {
        $engine = $this->engine();
        $products = [$this->productRow('PW', ['product_type' => 'variable'])];
        $variants = [$this->variantRow('PW', 'PW-RED', 'Color', 'Red', 'nope.png')];

        $validation = $engine->validate($products, $variants);
        $this->assertFalse($validation['valid']);
        $this->assertEquals('variant_image', $validation['errors'][0]['column']);
        $this->assertStringContainsString('nope.png', $validation['errors'][0]['error']);
    }

    public function test_cross_tenant_filename_is_not_resolved(): void
    {
        $otherTenant = Tenant::create(['name' => 'Other', 'slug' => 'other-' . uniqid(), 'status' => 'active']);
        $otherStorefront = Storefront::withoutTenantScope()->create(['tenant_id' => $otherTenant->id, 'status' => 'active']);
        StorefrontMedia::withoutTenantScope()->create([
            'tenant_id' => $otherTenant->id,
            'storefront_id' => $otherStorefront->id,
            'key' => 'library',
            'path' => 'storefront-media/other-secret.png',
            'original_name' => 'other-secret.png',
            'is_visible' => true,
        ]);

        $engine = $this->engine();
        $products = [$this->productRow('P8', ['image' => 'other-secret.png'])];

        $validation = $engine->validate($products, []);
        $this->assertFalse($validation['valid']);
        $this->assertStringContainsString('not found', strtolower($validation['errors'][0]['error']));
    }

    public function test_import_rolls_back_when_image_unresolvable(): void
    {
        $this->makeMedia('ok.png', 'storefront-media/ok.png');

        $engine = $this->engine();
        $products = [
            $this->productRow('R1', ['image' => 'ok.png']),
            $this->productRow('R2', ['image' => 'missing-on-import.png']),
        ];

        try {
            $engine->import($products, [], 'create_new');
            $this->fail('Expected import to throw.');
        } catch (\Throwable $e) {
            // expected
        }

        $this->assertSame(0, Product::withoutTenantScope()->count());
    }

    public function test_same_tenant_duplicate_media_upload_is_rejected(): void
    {
        $this->mockImageUpload();

        $this->actingAs($this->admin)
            ->post("/store/{$this->tenant->slug}/admin/storefront/media", [
                'file' => UploadedFile::fake()->image('dup.png'),
            ]);

        $this->assertSame(1, StorefrontMedia::withoutTenantScope()->where('tenant_id', $this->tenant->id)->where('original_name', 'dup.png')->count());

        $this->actingAs($this->admin)
            ->post("/store/{$this->tenant->slug}/admin/storefront/media", [
                'file' => UploadedFile::fake()->image('dup.png'),
            ])
            ->assertSessionHas('error');

        $this->assertSame(1, StorefrontMedia::withoutTenantScope()->where('tenant_id', $this->tenant->id)->where('original_name', 'dup.png')->count());
    }

    public function test_different_tenants_may_upload_same_filename(): void
    {
        $otherTenant = Tenant::create(['name' => 'Other B', 'slug' => 'other-b-' . uniqid(), 'status' => 'active']);
        $otherStorefront = Storefront::withoutTenantScope()->create(['tenant_id' => $otherTenant->id, 'status' => 'active']);
        $this->makeMedia('common.png', 'storefront-media/common-a.png');
        StorefrontMedia::withoutTenantScope()->create([
            'tenant_id' => $otherTenant->id,
            'storefront_id' => $otherStorefront->id,
            'key' => 'library',
            'path' => 'storefront-media/common-b.png',
            'original_name' => 'common.png',
            'is_visible' => true,
        ]);

        $this->assertSame(1, StorefrontMedia::withoutTenantScope()->where('tenant_id', $this->tenant->id)->where('original_name', 'common.png')->count());
        $this->assertSame(1, StorefrontMedia::withoutTenantScope()->where('tenant_id', $otherTenant->id)->where('original_name', 'common.png')->count());
    }

    public function test_template_exposes_image_headers(): void
    {
        $templatePath = base_path('storage/app/product-import-template.xlsx');
        if (!file_exists($templatePath)) {
            $this->markTestSkipped('Template file not found.');
        }

        $reader = new ProductImportReader();
        $data = $reader->read(new UploadedFile($templatePath, 'template.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true));

        $productHeaders = array_keys($data['products'][0] ?? []);
        $this->assertContains('image', $productHeaders);
        $this->assertContains('secondary_image', $productHeaders);
        $this->assertContains('gallery_images', $productHeaders);

        $variantHeaders = array_keys($data['variants'][0] ?? []);
        $this->assertContains('variant_image', $variantHeaders);
    }

    private function engine(): ProductImportEngine
    {
        return new ProductImportEngine(
            new MasterDataResolver($this->tenant->id),
            app(SkuService::class),
            app(InventoryService::class)
        );
    }

    private function productRow(string $sku, array $overrides = []): array
    {
        return array_merge([
            'sku' => $sku,
            'product_name' => "Product {$sku}",
            'product_type' => 'single',
            'description' => 'Short',
            'full_description' => '',
            'category' => 'Media Cat',
            'brand' => 'Media Brand',
            'unit' => '',
            'selling_price' => '10',
            'cost_price' => '5',
            'stock' => '3',
            'barcode' => '',
            'status' => 'active',
        ], $overrides);
    }

    private function variantRow(string $parentSku, string $variantSku, string $option, string $value, string $image): array
    {
        return [
            'parent_sku' => $parentSku,
            'variant_sku' => $variantSku,
            'option_1_name' => $option,
            'option_1_value' => $value,
            'option_2_name' => '',
            'option_2_value' => '',
            'option_3_name' => '',
            'option_3_value' => '',
            'selling_price' => '12',
            'cost_price' => '6',
            'stock' => '2',
            'barcode' => '',
            'status' => 'active',
            'variant_image' => $image,
        ];
    }

    private function makeMedia(string $originalName, string $path): StorefrontMedia
    {
        $storefront = Storefront::withoutTenantScope()->where('tenant_id', $this->tenant->id)->first();

        return StorefrontMedia::withoutTenantScope()->create([
            'tenant_id' => $this->tenant->id,
            'storefront_id' => $storefront->id,
            'key' => 'library',
            'path' => $path,
            'original_name' => $originalName,
            'mime_type' => 'image/png',
            'size' => 1024,
            'alt_text' => $originalName,
            'is_visible' => true,
        ]);
    }

    private function mockImageUpload(): void
    {
        $counter = 0;
        $this->mock(ImageService::class, function ($mock) use (&$counter) {
            $mock->shouldReceive('upload')->andReturnUsing(function () use (&$counter) {
                $counter++;
                return "storefront-media/upload-{$counter}.png";
            });
            $mock->shouldReceive('delete')->andReturnTrue();
        });
    }
}
