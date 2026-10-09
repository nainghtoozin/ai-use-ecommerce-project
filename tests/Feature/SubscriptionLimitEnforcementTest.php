<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StorefrontMedia;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SubscriptionLimitEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['products.view', 'products.create', 'products.update', 'products.delete', 'users.view', 'users.create', 'settings.website'] as $perm) {
            Permission::create(['name' => $perm, 'guard_name' => 'web']);
        }

        config()->set('identity.use_accounts', false);
        config()->set('app.dev_mode', true);
    }

    public function test_product_creation_at_limit_is_rejected(): void
    {
        [$tenant, $admin, $category] = $this->seedTenant(productLimit: 1, staffLimit: 5);
        $this->seedProduct($tenant, $category, 'EXISTING');

        $response = $this->actingAs($admin)->post('/admin/products', [
            'name' => 'New Product',
            'sku' => 'NEW1',
            'type' => 'single',
            'price' => 10,
            'base_price' => 10,
            'category_id' => $category->id,
            'status' => 'active',
            'photo1_media_id' => $this->seedMedia($tenant),
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(1, $this->productCount($tenant));
        $this->assertNull(Product::withoutTenantScope()->where('tenant_id', $tenant->id)->where('sku', 'NEW1')->first());
    }

    public function test_excel_import_within_limit_succeeds(): void
    {
        [$tenant, $admin, $category] = $this->seedTenant(productLimit: 5, staffLimit: 5);

        $file = $this->makeImportFile(
            ['SKU', 'Product Name', 'Product Type', 'Category', 'Selling Price', 'Status'],
            [
                ['IMP1', 'Import One', 'single', 'Limit Cat', '10', 'active'],
                ['IMP2', 'Import Two', 'single', 'Limit Cat', '20', 'active'],
            ]
        );

        $this->actingAs($admin)
            ->post('/admin/products/import/validate-sheet', ['file' => $this->reUpload($file), 'import_mode' => 'create_new'], ['Accept' => 'application/json'])
            ->assertStatus(200)
            ->assertJson(['valid' => true]);

        $this->actingAs($admin)
            ->post('/admin/products/import/execute', ['file' => $this->reUpload($file), 'import_mode' => 'create_new'], ['Accept' => 'application/json'])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertSame(2, $this->productCount($tenant));
    }

    public function test_excel_import_exceeding_limit_rejected_before_writes(): void
    {
        [$tenant, $admin, $category] = $this->seedTenant(productLimit: 1, staffLimit: 5);

        $file = $this->makeImportFile(
            ['SKU', 'Product Name', 'Product Type', 'Category', 'Selling Price', 'Status'],
            [
                ['X1', 'Over One', 'single', 'Limit Cat', '10', 'active'],
                ['X2', 'Over Two', 'single', 'Limit Cat', '20', 'active'],
            ]
        );

        $this->actingAs($admin)
            ->post('/admin/products/import/validate-sheet', ['file' => $this->reUpload($file), 'import_mode' => 'create_new'], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('type', 'subscription_limit');

        $this->actingAs($admin)
            ->post('/admin/products/import/execute', ['file' => $this->reUpload($file), 'import_mode' => 'create_new'], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('type', 'subscription_limit');

        $this->assertSame(0, $this->productCount($tenant));
    }

    public function test_no_partial_import_when_limit_exceeded(): void
    {
        [$tenant, $admin] = $this->seedTenant(productLimit: 1, staffLimit: 5);

        $file = $this->makeImportFile(
            ['SKU', 'Product Name', 'Product Type', 'Category', 'Selling Price', 'Status'],
            [
                ['P_OK', 'Valid Row', 'single', 'Limit Cat', '10', 'active'],
                ['P_OVER', 'Excess Row', 'single', 'Limit Cat', '10', 'active'],
            ]
        );

        $this->actingAs($admin)
            ->post('/admin/products/import/execute', ['file' => $this->reUpload($file), 'import_mode' => 'create_new'], ['Accept' => 'application/json'])
            ->assertStatus(422);

        $this->assertSame(0, $this->productCount($tenant));
        $this->assertNull(Product::withoutTenantScope()->where('tenant_id', $tenant->id)->where('sku', 'P_OK')->first());
    }

    public function test_updating_existing_sku_at_limit_still_works(): void
    {
        [$tenant, $admin, $category] = $this->seedTenant(productLimit: 1, staffLimit: 5);
        $this->seedProduct($tenant, $category, 'EX1', 'Old Name');

        $file = $this->makeImportFile(
            ['SKU', 'Product Name', 'Product Type', 'Category', 'Selling Price', 'Status'],
            [['EX1', 'Updated Name', 'single', 'Limit Cat', '10', 'active']]
        );

        $this->actingAs($admin)
            ->post('/admin/products/import/execute', ['file' => $this->reUpload($file), 'import_mode' => 'create_update'], ['Accept' => 'application/json'])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertSame(1, $this->productCount($tenant));
        $this->assertSame('Updated Name', Product::withoutTenantScope()->where('tenant_id', $tenant->id)->where('sku', 'EX1')->value('name'));
    }

    public function test_update_only_does_not_create_new_products(): void
    {
        [$tenant, $admin, $category] = $this->seedTenant(productLimit: 1, staffLimit: 5);
        $this->seedProduct($tenant, $category, 'EX1');

        $file = $this->makeImportFile(
            ['SKU', 'Product Name', 'Product Type', 'Category', 'Selling Price', 'Status'],
            [
                ['EX1', 'Updated', 'single', 'Limit Cat', '10', 'active'],
                ['BRAND_NEW', 'Should Not Exist', 'single', 'Limit Cat', '10', 'active'],
            ]
        );

        $this->actingAs($admin)
            ->post('/admin/products/import/execute', ['file' => $this->reUpload($file), 'import_mode' => 'update_only'], ['Accept' => 'application/json'])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertSame(1, $this->productCount($tenant));
        $this->assertNull(Product::withoutTenantScope()->where('tenant_id', $tenant->id)->where('sku', 'BRAND_NEW')->first());
    }

    public function test_variable_product_counts_as_one_and_variants_not_counted(): void
    {
        [$tenant, $admin] = $this->seedTenant(productLimit: 1, staffLimit: 5);

        $spreadsheet = new Spreadsheet();
        $products = $spreadsheet->getActiveSheet();
        $products->setTitle('Products');
        $products->fromArray(['SKU', 'Product Name', 'Product Type', 'Category', 'Selling Price', 'Status'], null, 'A1');
        $products->fromArray(['VAR1', 'Variable One', 'variable', 'Limit Cat', '10', 'active'], null, 'A2');
        $variants = $spreadsheet->createSheet();
        $variants->setTitle('Variants');
        $variants->fromArray(['Parent SKU', 'Variant SKU', 'Option 1 Name', 'Option 1 Value', 'Selling Price', 'Stock', 'Status'], null, 'A1');
        $variants->fromArray(['VAR1', 'VAR1-R', 'Color', 'Red', '10', '5', 'active'], null, 'A2');
        $variants->fromArray(['VAR1', 'VAR1-B', 'Color', 'Blue', '10', '5', 'active'], null, 'A3');
        $path = $this->saveSpreadsheet($spreadsheet);

        $this->actingAs($admin)
            ->post('/admin/products/import/execute', [
                'file' => new UploadedFile($path, 'import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
                'import_mode' => 'create_new',
            ], ['Accept' => 'application/json'])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertSame(1, $this->productCount($tenant));
        $parent = Product::withoutTenantScope()->where('tenant_id', $tenant->id)->where('sku', 'VAR1')->first();
        $this->assertNotNull($parent);
        $this->assertSame(2, ProductVariant::withoutTenantScope()->where('product_id', $parent->id)->count());
    }

    public function test_staff_creation_beyond_limit_blocked_user_friendly(): void
    {
        [$tenant, $admin] = $this->seedTenant(productLimit: 5, staffLimit: 1);

        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Second Admin',
            'email' => 'second-admin-' . uniqid() . '@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(1, User::where('tenant_id', $tenant->id)->count());
    }

    public function test_limit_errors_are_tenant_specific(): void
    {
        [$tenantA, $adminA] = $this->seedTenant(productLimit: 1, staffLimit: 5);
        [$tenantB, $adminB] = $this->seedTenant(productLimit: 5, staffLimit: 5);

        $file = fn () => $this->makeImportFile(
            ['SKU', 'Product Name', 'Product Type', 'Category', 'Selling Price', 'Status'],
            [
                ['T1', 'One', 'single', 'Limit Cat', '10', 'active'],
                ['T2', 'Two', 'single', 'Limit Cat', '10', 'active'],
            ]
        );

        $this->actingAs($adminA)
            ->post('/admin/products/import/execute', ['file' => $this->reUpload($file()), 'import_mode' => 'create_new'], ['Accept' => 'application/json'])
            ->assertStatus(422);

        $this->actingAs($adminB)
            ->post('/admin/products/import/execute', ['file' => $this->reUpload($file()), 'import_mode' => 'create_new'], ['Accept' => 'application/json'])
            ->assertStatus(200);

        $this->assertSame(0, $this->productCount($tenantA));
        $this->assertSame(2, $this->productCount($tenantB));
    }

    public function test_normal_import_below_limit_unaffected(): void
    {
        [$tenant, $admin] = $this->seedTenant(productLimit: 10, staffLimit: 10);

        $file = $this->makeImportFile(
            ['SKU', 'Product Name', 'Product Type', 'Category', 'Selling Price', 'Status'],
            [['BELOW1', 'Below Limit', 'single', 'Limit Cat', '10', 'active']]
        );

        $this->actingAs($admin)
            ->post('/admin/products/import/execute', ['file' => $this->reUpload($file), 'import_mode' => 'create_new'], ['Accept' => 'application/json'])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertSame(1, $this->productCount($tenant));
    }

    private function seedTenant(int $productLimit, int $staffLimit): array
    {
        $plan = Plan::create([
            'name' => 'Limited Plan',
            'slug' => 'limited-' . uniqid(),
            'monthly_price' => 0,
            'yearly_price' => 0,
            'status' => 'active',
            'product_limit' => $productLimit,
            'staff_limit' => $staffLimit,
        ]);

        $tenant = Tenant::create(['name' => 'Limit Store', 'slug' => 'limit-store-' . uniqid(), 'status' => 'active']);
        $tenant->subscription_plan_id = $plan->id;
        $tenant->save();

        $subscription = new Subscription([
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_interval' => 'monthly',
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
        ]);
        $subscription->tenant_id = $tenant->id;
        $subscription->save();

        $role = Role::withoutTenantScope()->firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]
        );
        $role->syncPermissions(Permission::all());

        $admin = User::create([
            'name' => 'Owner Admin',
            'email' => 'owner-' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'status' => 'active',
            'tenant_id' => $tenant->id,
        ]);
        $admin->assignRole('admin');

        $category = new Category(['name' => 'Limit Cat', 'slug' => 'limit-cat-' . uniqid()]);
        $category->tenant_id = $tenant->id;
        $category->save();

        return [$tenant, $admin, $category];
    }

    private function seedProduct(Tenant $tenant, Category $category, string $sku, string $name = 'Seed Product'): Product
    {
        $product = new Product([
            'name' => $name,
            'type' => 'single',
            'price' => 10,
            'base_price' => 10,
            'category_id' => $category->id,
            'sku' => $sku,
            'status' => Product::STATUS_ACTIVE,
        ]);
        $product->tenant_id = $tenant->id;
        $product->save();

        return $product;
    }

    private function seedMedia(Tenant $tenant): int
    {
        $media = StorefrontMedia::withoutTenantScope()->create([
            'tenant_id' => $tenant->id,
            'key' => 'library',
            'path' => 'storefront-media/limit-test.png',
            'original_name' => 'limit-test-' . uniqid() . '.png',
            'is_visible' => true,
        ]);

        return $media->id;
    }

    private function makeImportFile(array $headers, array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Products');
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray($rows, null, 'A2');

        return $this->saveSpreadsheet($spreadsheet);
    }

    private function saveSpreadsheet(Spreadsheet $spreadsheet): string
    {
        $path = sys_get_temp_dir() . '/limit-import-' . uniqid() . '.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    private function reUpload(string $path): UploadedFile
    {
        return new UploadedFile($path, 'import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function productCount(Tenant $tenant): int
    {
        return Product::withoutTenantScope()->where('tenant_id', $tenant->id)->count();
    }
}
