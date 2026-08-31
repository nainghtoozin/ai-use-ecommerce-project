<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Services\ImportExport\FormatHandlers\ProductImportReader;
use App\Services\ImportExport\ProductImportEngine;
use App\Services\ImportExport\MasterDataResolver;
use App\Services\ImportExport\ColumnMapper;
use App\Services\SkuService;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductImportTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $admin;
    private Category $category;
    private Brand $brand;
    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlansAndRoles();
        $this->setupTenantAndUser();
    }

    private function seedPlansAndRoles(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'products.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'products.create', 'guard_name' => 'web']);
        Permission::create(['name' => 'products.update', 'guard_name' => 'web']);
        Permission::create(['name' => 'products.delete', 'guard_name' => 'web']);

        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $role->syncPermissions(Permission::whereIn('name', [
            'products.view', 'products.create', 'products.update', 'products.delete',
        ])->get());
    }

    private function setupTenantAndUser(): void
    {
        $this->tenant = Tenant::create([
            'name' => 'Test Import Tenant',
            'slug' => 'test-import-' . uniqid(),
            'status' => 'active',
        ]);

        $this->admin = User::create([
            'name' => 'Import Test Admin',
            'email' => 'import-test-' . uniqid() . '@example.com',
            'tenant_id' => $this->tenant->id,
            'password' => bcrypt('password'),
        ]);

        $this->category = Category::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Electronics',
            'slug' => 'electronics-' . uniqid(),
        ]);

        $this->brand = Brand::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Generic',
            'slug' => 'generic-' . uniqid(),
        ]);

        $this->unit = Unit::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Piece',
            'slug' => 'piece-' . uniqid(),
        ]);

        $this->admin->assignRole('admin');
    }

    private function createTestExcelFile(array $productsSheetData, array $variantsSheetData): string
    {
        $spreadsheet = new Spreadsheet();
        $productsSheet = $spreadsheet->getActiveSheet();
        $productsSheet->setTitle('Products');

        $rowIdx = 1;
        foreach ($productsSheetData as $row) {
            $colIdx = 1;
            foreach ($row as $value) {
                $productsSheet->setCellValueByColumnAndRow($colIdx, $rowIdx, $value);
                $colIdx++;
            }
            $rowIdx++;
        }

        $variantsSheet = $spreadsheet->createSheet();
        $variantsSheet->setTitle('Variants');

        $rowIdx = 1;
        foreach ($variantsSheetData as $row) {
            $colIdx = 1;
            foreach ($row as $value) {
                $variantsSheet->setCellValueByColumnAndRow($colIdx, $rowIdx, $value);
                $colIdx++;
            }
            $rowIdx++;
        }

        $path = sys_get_temp_dir() . '/test_import_' . uniqid() . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    public function test_product_import_reader_parses_products_sheet_correctly(): void
    {
        $productsHeaders = ['SKU', 'Product Name', 'Product Type', 'Description', 'Full Description', 'Category', 'Brand', 'Unit', 'Selling Price', 'Cost Price', 'Stock', 'Barcode', 'Status', 'Notes'];
        $productsData = [
            ['WM001', 'Wireless Mouse', 'single', 'Ergonomic mouse', '', 'Electronics', 'Generic', 'Piece', 19.99, 10.00, 50, '123456789012', 'active', ''],
            ['TS001', 'Basic T-Shirt', 'variable', 'Cotton t-shirt', '', 'Clothing', 'Apparel Co', 'Piece', 14.99, 6.00, 0, '123456789020', 'active', ''],
        ];

        $variantsHeaders = ['Parent SKU', 'Variant SKU', 'Option 1 Name', 'Option 1 Value', 'Option 2 Name', 'Option 2 Value', 'Option 3 Name', 'Option 3 Value', 'Selling Price', 'Cost Price', 'Stock', 'Barcode', 'Status'];
        $variantsData = [
            ['TS001', 'TS001-RED-S', 'Color', 'Red', 'Size', 'S', '', '', 14.99, 6.00, 20, '123456789021', 'active'],
            ['TS001', 'TS001-RED-M', 'Color', 'Red', 'Size', 'M', '', '', 14.99, 6.00, 25, '123456789022', 'active'],
            ['TS001', 'TS001-BLUE-S', 'Color', 'Blue', 'Size', 'S', '', '', 14.99, 6.00, 15, '123456789023', 'active'],
        ];

        $filePath = $this->createTestExcelFile(
            array_merge([$productsHeaders], $productsData),
            array_merge([$variantsHeaders], $variantsData)
        );

        $reader = new ProductImportReader();
        $uploadedFile = new UploadedFile($filePath, 'test-import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $data = $reader->read($uploadedFile);

        $this->assertTrue($data['has_products_sheet'], 'Products sheet should be detected');
        $this->assertTrue($data['has_variants_sheet'], 'Variants sheet should be detected');
        $this->assertCount(2, $data['products'], 'Should have 2 products');
        $this->assertCount(3, $data['variants'], 'Should have 3 variants');

        $this->assertEquals('WM001', $data['products'][0]['sku']);
        $this->assertEquals('Wireless Mouse', $data['products'][0]['product_name']);
        $this->assertEquals('single', $data['products'][0]['product_type']);
        $this->assertEquals('19.99', $data['products'][0]['selling_price']);

        $this->assertEquals('TS001', $data['products'][1]['sku']);
        $this->assertEquals('variable', $data['products'][1]['product_type']);

        $this->assertEquals('TS001', $data['variants'][0]['parent_sku']);
        $this->assertEquals('TS001-RED-S', $data['variants'][0]['variant_sku']);
        $this->assertEquals('Color', $data['variants'][0]['option_1_name']);
        $this->assertEquals('Red', $data['variants'][0]['option_1_value']);

        unlink($filePath);
    }

    public function test_product_import_reader_normalizes_headers_correctly(): void
    {
        $productsHeaders = ['SKU', 'Product Name', 'Product Type', 'Description', 'Full Description', 'Category', 'Brand', 'Unit', 'Selling Price', 'Cost Price', 'Stock', 'Barcode', 'Status', 'Notes'];
        $productsData = [
            ['SKU001', 'Test Product', 'single', 'Description', '', 'Category', 'Brand', 'Piece', 10.00, 5.00, 100, 'BARCODE', 'active', ''],
        ];

        $filePath = $this->createTestExcelFile(
            array_merge([$productsHeaders], $productsData),
            [[]]
        );

        $reader = new ProductImportReader();
        $uploadedFile = new UploadedFile($filePath, 'test-import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $data = $reader->read($uploadedFile);

        $product = $data['products'][0];
        $this->assertArrayHasKey('sku', $product);
        $this->assertArrayHasKey('product_name', $product);
        $this->assertArrayHasKey('product_type', $product);
        $this->assertArrayHasKey('selling_price', $product);
        $this->assertArrayHasKey('cost_price', $product);
        $this->assertArrayHasKey('stock', $product);
        $this->assertArrayHasKey('category', $product);
        $this->assertArrayHasKey('brand', $product);

        unlink($filePath);
    }

    public function test_product_import_reader_handles_whitespace_in_headers(): void
    {
        $productsHeaders = ['  SKU  ', 'Product Name  ', ' Product Type', 'Description', 'Full Description', 'Category', 'Brand', 'Unit', 'Selling Price', 'Cost Price', 'Stock', 'Barcode', 'Status', 'Notes'];
        $productsData = [
            ['SKU001', 'Test Product', 'single', 'Description', '', 'Category', 'Brand', 'Piece', 10.00, 5.00, 100, 'BARCODE', 'active', ''],
        ];

        $filePath = $this->createTestExcelFile(
            array_merge([$productsHeaders], $productsData),
            [[]]
        );

        $reader = new ProductImportReader();
        $uploadedFile = new UploadedFile($filePath, 'test-import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $data = $reader->read($uploadedFile);

        $this->assertCount(1, $data['products'], 'Should parse product despite whitespace in headers');
        $this->assertEquals('SKU001', $data['products'][0]['sku']);

        unlink($filePath);
    }

    public function test_product_import_reader_handles_empty_cells_correctly(): void
    {
        $productsHeaders = ['SKU', 'Product Name', 'Product Type', 'Description', 'Full Description', 'Category', 'Brand', 'Unit', 'Selling Price', 'Cost Price', 'Stock', 'Barcode', 'Status', 'Notes'];
        $productsData = [
            ['SKU001', 'Test Product', 'single', '', '', '', '', '', 10.00, '', 100, '', 'active', ''],
        ];

        $filePath = $this->createTestExcelFile(
            array_merge([$productsHeaders], $productsData),
            [[]]
        );

        $reader = new ProductImportReader();
        $uploadedFile = new UploadedFile($filePath, 'test-import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $data = $reader->read($uploadedFile);

        $this->assertCount(1, $data['products']);
        $this->assertEquals('', $data['products'][0]['description']);
        $this->assertEquals('', $data['products'][0]['category']);
        $this->assertEquals('', $data['products'][0]['brand']);
        $this->assertEquals('', $data['products'][0]['cost_price']);

        unlink($filePath);
    }

    public function test_product_import_reader_skips_completely_empty_rows(): void
    {
        $productsHeaders = ['SKU', 'Product Name', 'Product Type', 'Description', 'Full Description', 'Category', 'Brand', 'Unit', 'Selling Price', 'Cost Price', 'Stock', 'Barcode', 'Status', 'Notes'];
        $productsData = [
            ['SKU001', 'Product 1', 'single', 'Desc', '', 'Cat', 'Brand', 'Piece', 10.00, 5.00, 100, '', 'active', ''],
            ['', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['SKU002', 'Product 2', 'single', 'Desc', '', 'Cat', 'Brand', 'Piece', 15.00, 7.00, 50, '', 'active', ''],
        ];

        $filePath = $this->createTestExcelFile(
            array_merge([$productsHeaders], $productsData),
            [[]]
        );

        $reader = new ProductImportReader();
        $uploadedFile = new UploadedFile($filePath, 'test-import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $data = $reader->read($uploadedFile);

        $this->assertCount(2, $data['products'], 'Empty rows should be skipped');

        unlink($filePath);
    }

    public function test_product_import_validation_with_valid_data(): void
    {
        $productsHeaders = ['SKU', 'Product Name', 'Product Type', 'Description', 'Full Description', 'Category', 'Brand', 'Unit', 'Selling Price', 'Cost Price', 'Stock', 'Barcode', 'Status', 'Notes'];
        $productsData = [
            ['WM001', 'Wireless Mouse', 'single', 'Ergonomic mouse', '', 'Electronics', 'Generic', 'Piece', 19.99, 10.00, 50, '123456789012', 'active', ''],
            ['TS001', 'Basic T-Shirt', 'variable', 'Cotton t-shirt', '', 'Clothing', 'Apparel Co', 'Piece', 14.99, 6.00, 0, '123456789020', 'active', ''],
        ];

        $variantsHeaders = ['Parent SKU', 'Variant SKU', 'Option 1 Name', 'Option 1 Value', 'Option 2 Name', 'Option 2 Value', 'Option 3 Name', 'Option 3 Value', 'Selling Price', 'Cost Price', 'Stock', 'Barcode', 'Status'];
        $variantsData = [
            ['TS001', 'TS001-RED-S', 'Color', 'Red', 'Size', 'S', '', '', 14.99, 6.00, 20, '123456789021', 'active'],
            ['TS001', 'TS001-RED-M', 'Color', 'Red', 'Size', 'M', '', '', 14.99, 6.00, 25, '123456789022', 'active'],
            ['TS001', 'TS001-BLUE-S', 'Color', 'Blue', 'Size', 'S', '', '', 14.99, 6.00, 15, '123456789023', 'active'],
        ];

        $filePath = $this->createTestExcelFile(
            array_merge([$productsHeaders], $productsData),
            array_merge([$variantsHeaders], $variantsData)
        );

        $reader = new ProductImportReader();
        $uploadedFile = new UploadedFile($filePath, 'test-import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $data = $reader->read($uploadedFile);

        $resolver = new MasterDataResolver($this->tenant->id);
        $skuService = app(SkuService::class);
        $inventoryService = app(InventoryService::class);

        $engine = new ProductImportEngine($resolver, $skuService, $inventoryService);
        $validation = $engine->validate($data['products'], $data['variants']);

        $this->assertTrue($validation['valid'], 'Validation should pass for valid data');
        $this->assertEquals(2, $validation['summary']['total_products'], 'Should detect 2 products');
        $this->assertEquals(3, $validation['summary']['total_variants'], 'Should detect 3 variants');
        $this->assertEquals(0, $validation['summary']['error_products'], 'Should have 0 product errors');
        $this->assertEquals(0, $validation['summary']['error_variants'], 'Should have 0 variant errors');

        unlink($filePath);
    }

    public function test_product_import_validation_requires_product_name(): void
    {
        $productsHeaders = ['SKU', 'Product Name', 'Product Type', 'Description', 'Full Description', 'Category', 'Brand', 'Unit', 'Selling Price', 'Cost Price', 'Stock', 'Barcode', 'Status', 'Notes'];
        $productsData = [
            ['', '', 'single', 'Description', '', 'Cat', 'Brand', 'Piece', 10.00, 5.00, 100, '', 'active', ''],
        ];

        $filePath = $this->createTestExcelFile(
            array_merge([$productsHeaders], $productsData),
            [[]]
        );

        $reader = new ProductImportReader();
        $uploadedFile = new UploadedFile($filePath, 'test-import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $data = $reader->read($uploadedFile);

        $resolver = new MasterDataResolver($this->tenant->id);
        $skuService = app(SkuService::class);
        $inventoryService = app(InventoryService::class);

        $engine = new ProductImportEngine($resolver, $skuService, $inventoryService);
        $validation = $engine->validate($data['products'], []);

        $this->assertFalse($validation['valid'], 'Validation should fail for missing product name');
        $this->assertGreaterThan(0, count($validation['errors']), 'Should have at least one error');

        unlink($filePath);
    }

    public function test_product_import_validation_requires_sku(): void
    {
        $productsHeaders = ['SKU', 'Product Name', 'Product Type', 'Description', 'Full Description', 'Category', 'Brand', 'Unit', 'Selling Price', 'Cost Price', 'Stock', 'Barcode', 'Status', 'Notes'];
        $productsData = [
            ['', 'Product Without SKU', 'single', 'Description', '', 'Cat', 'Brand', 'Piece', 10.00, 5.00, 100, '', 'active', ''],
        ];

        $filePath = $this->createTestExcelFile(
            array_merge([$productsHeaders], $productsData),
            [[]]
        );

        $reader = new ProductImportReader();
        $uploadedFile = new UploadedFile($filePath, 'test-import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $data = $reader->read($uploadedFile);

        $resolver = new MasterDataResolver($this->tenant->id);
        $skuService = app(SkuService::class);
        $inventoryService = app(InventoryService::class);

        $engine = new ProductImportEngine($resolver, $skuService, $inventoryService);
        $validation = $engine->validate($data['products'], []);

        $this->assertFalse($validation['valid'], 'Validation should fail for missing SKU');
        $this->assertGreaterThan(0, count($validation['errors']), 'Should have at least one error');

        unlink($filePath);
    }

    public function test_product_import_validation_requires_variants_for_variable_products(): void
    {
        $productsHeaders = ['SKU', 'Product Name', 'Product Type', 'Description', 'Full Description', 'Category', 'Brand', 'Unit', 'Selling Price', 'Cost Price', 'Stock', 'Barcode', 'Status', 'Notes'];
        $productsData = [
            ['TS001', 'Basic T-Shirt', 'variable', 'Cotton t-shirt', '', 'Clothing', 'Apparel Co', 'Piece', 14.99, 6.00, 0, '123456789020', 'active', ''],
        ];

        $filePath = $this->createTestExcelFile(
            array_merge([$productsHeaders], $productsData),
            [[]]
        );

        $reader = new ProductImportReader();
        $uploadedFile = new UploadedFile($filePath, 'test-import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $data = $reader->read($uploadedFile);

        $resolver = new MasterDataResolver($this->tenant->id);
        $skuService = app(SkuService::class);
        $inventoryService = app(InventoryService::class);

        $engine = new ProductImportEngine($resolver, $skuService, $inventoryService);
        $validation = $engine->validate($data['products'], []);

        $this->assertFalse($validation['valid'], 'Validation should fail when variable product has no variants');
        $this->assertGreaterThan(0, count($validation['errors']), 'Should have at least one error');

        unlink($filePath);
    }

    public function test_product_import_validation_validates_numeric_price(): void
    {
        $productsHeaders = ['SKU', 'Product Name', 'Product Type', 'Description', 'Full Description', 'Category', 'Brand', 'Unit', 'Selling Price', 'Cost Price', 'Stock', 'Barcode', 'Status', 'Notes'];
        $productsData = [
            ['SKU001', 'Test Product', 'single', 'Description', '', 'Cat', 'Brand', 'Piece', 'not-a-number', 5.00, 100, '', 'active', ''],
        ];

        $filePath = $this->createTestExcelFile(
            array_merge([$productsHeaders], $productsData),
            [[]]
        );

        $reader = new ProductImportReader();
        $uploadedFile = new UploadedFile($filePath, 'test-import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $data = $reader->read($uploadedFile);

        $resolver = new MasterDataResolver($this->tenant->id);
        $skuService = app(SkuService::class);
        $inventoryService = app(InventoryService::class);

        $engine = new ProductImportEngine($resolver, $skuService, $inventoryService);
        $validation = $engine->validate($data['products'], []);

        $this->assertFalse($validation['valid'], 'Validation should fail for non-numeric price');
        $this->assertGreaterThan(0, count($validation['errors']), 'Should have at least one error');

        unlink($filePath);
    }

    public function test_product_import_http_validate_sheet_endpoint(): void
    {
        $productsHeaders = ['SKU', 'Product Name', 'Product Type', 'Description', 'Full Description', 'Category', 'Brand', 'Unit', 'Selling Price', 'Cost Price', 'Stock', 'Barcode', 'Status', 'Notes'];
        $productsData = [
            ['WM001', 'Wireless Mouse', 'single', 'Ergonomic mouse', '', 'Electronics', 'Generic', 'Piece', 19.99, 10.00, 50, '123456789012', 'active', ''],
            ['TS001', 'Basic T-Shirt', 'variable', 'Cotton t-shirt', '', 'Clothing', 'Apparel Co', 'Piece', 14.99, 6.00, 0, '123456789020', 'active', ''],
        ];

        $variantsHeaders = ['Parent SKU', 'Variant SKU', 'Option 1 Name', 'Option 1 Value', 'Option 2 Name', 'Option 2 Value', 'Option 3 Name', 'Option 3 Value', 'Selling Price', 'Cost Price', 'Stock', 'Barcode', 'Status'];
        $variantsData = [
            ['TS001', 'TS001-RED-S', 'Color', 'Red', 'Size', 'S', '', '', 14.99, 6.00, 20, '123456789021', 'active'],
            ['TS001', 'TS001-RED-M', 'Color', 'Red', 'Size', 'M', '', '', 14.99, 6.00, 25, '123456789022', 'active'],
            ['TS001', 'TS001-BLUE-S', 'Color', 'Blue', 'Size', 'S', '', '', 14.99, 6.00, 15, '123456789023', 'active'],
        ];

        $filePath = $this->createTestExcelFile(
            array_merge([$productsHeaders], $productsData),
            array_merge([$variantsHeaders], $variantsData)
        );

        $this->actingAs($this->admin);

        $response = $this->post('/admin/products/import/validate-sheet', [
            'file' => new UploadedFile($filePath, 'test-import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(200);
        $response->assertJson([
            'valid' => true,
        ]);

        $json = $response->json();
        $this->assertEquals(2, $json['summary']['total_products'] ?? null, 'Should detect 2 products in HTTP response');
        $this->assertEquals(3, $json['summary']['total_variants'] ?? null, 'Should detect 3 variants in HTTP response');
        $this->assertCount(2, $json['preview']['products'] ?? [], 'Preview should contain 2 products');
        $this->assertCount(3, $json['preview']['variants'] ?? [], 'Preview should contain 3 variants');

        unlink($filePath);
    }

    public function test_product_import_http_validate_requires_products_sheet(): void
    {
        $spreadsheet = new Spreadsheet();
        $wrongSheet = $spreadsheet->getActiveSheet();
        $wrongSheet->setTitle('WrongSheet');
        $wrongSheet->setCellValue('A1', 'Some data');

        $path = sys_get_temp_dir() . '/test_wrong_sheet_' . uniqid() . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();

        $this->actingAs($this->admin);

        $response = $this->post('/admin/products/import/validate-sheet', [
            'file' => new UploadedFile($path, 'test-import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422);
        $response->assertJsonFragment(['error' => 'Products sheet is required. Please download the Product Import Template.']);

        unlink($path);
    }

    public function test_column_mapper_normalizes_headers(): void
    {
        $fileHeaders = ['SKU', 'Product Name', 'product_type', 'SELLING PRICE', 'Cost Price', 'stock', 'image_url', 'variant_image'];
        $mapped = ColumnMapper::autoMap($fileHeaders);

        $this->assertEquals('sku', $mapped['SKU']);
        $this->assertEquals('name', $mapped['Product Name']);
        $this->assertEquals('type', $mapped['product_type']);
        $this->assertEquals('price', $mapped['SELLING PRICE']);
        $this->assertEquals('cost_price', $mapped['Cost Price']);
        $this->assertEquals('stock', $mapped['stock']);
        $this->assertEquals('photo1', $mapped['image_url']);
        $this->assertEquals('variant_image', $mapped['variant_image']);
    }

    public function test_actual_template_file_parsing(): void
    {
        $templatePath = base_path('storage/app/product-import-template.xlsx');
        if (!file_exists($templatePath)) {
            $this->markTestSkipped('Template file not found at storage/app/product-import-template.xlsx');
        }

        $reader = new ProductImportReader();
        $uploadedFile = new UploadedFile($templatePath, 'product-import-template.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $data = $reader->read($uploadedFile);

        $this->assertTrue($data['has_products_sheet']);
        $this->assertTrue($data['has_variants_sheet']);
        $this->assertCount(2, $data['products'], 'Template should have 2 product rows');
        $this->assertCount(3, $data['variants'], 'Template should have 3 variant rows');
    }
}
