<?php

namespace Tests\Unit;

use App\Services\ImportExport\FormatHandlers\ProductImportReader;
use App\Services\ImportExport\ColumnMapper;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class ProductImportReaderTest extends TestCase
{
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

    public function test_parses_products_sheet_correctly(): void
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
        $uploadedFile = new UploadedFile($filePath, 'test.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $data = $reader->read($uploadedFile);

        $this->assertTrue($data['has_products_sheet']);
        $this->assertTrue($data['has_variants_sheet']);
        $this->assertCount(2, $data['products']);
        $this->assertCount(3, $data['variants']);

        $this->assertEquals('WM001', $data['products'][0]['sku']);
        $this->assertEquals('Wireless Mouse', $data['products'][0]['product_name']);
        $this->assertEquals('single', $data['products'][0]['product_type']);
        $this->assertEquals('19.99', $data['products'][0]['selling_price']);
        $this->assertEquals('Electronics', $data['products'][0]['category']);
        $this->assertEquals('Generic', $data['products'][0]['brand']);
        $this->assertEquals('Piece', $data['products'][0]['unit']);
        $this->assertEquals('50', $data['products'][0]['stock']);
        $this->assertEquals('active', $data['products'][0]['status']);

        $this->assertEquals('TS001', $data['products'][1]['sku']);
        $this->assertEquals('variable', $data['products'][1]['product_type']);

        $this->assertEquals('TS001', $data['variants'][0]['parent_sku']);
        $this->assertEquals('TS001-RED-S', $data['variants'][0]['variant_sku']);
        $this->assertEquals('Color', $data['variants'][0]['option_1_name']);
        $this->assertEquals('Red', $data['variants'][0]['option_1_value']);
        $this->assertEquals('Size', $data['variants'][0]['option_2_name']);
        $this->assertEquals('S', $data['variants'][0]['option_2_value']);
        $this->assertEquals('', $data['variants'][0]['option_3_name']);
        $this->assertEquals('', $data['variants'][0]['option_3_value']);
        $this->assertEquals('20', $data['variants'][0]['stock']);

        unlink($filePath);
    }

    public function test_normalizes_headers_correctly(): void
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
        $uploadedFile = new UploadedFile($filePath, 'test.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

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
        $this->assertArrayHasKey('unit', $product);
        $this->assertArrayHasKey('barcode', $product);
        $this->assertArrayHasKey('status', $product);
        $this->assertArrayHasKey('notes', $product);

        unlink($filePath);
    }

    public function test_handles_whitespace_in_headers(): void
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
        $uploadedFile = new UploadedFile($filePath, 'test.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $data = $reader->read($uploadedFile);

        $this->assertCount(1, $data['products']);
        $this->assertEquals('SKU001', $data['products'][0]['sku']);
        $this->assertEquals('Test Product', $data['products'][0]['product_name']);

        unlink($filePath);
    }

    public function test_handles_empty_cells_correctly(): void
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
        $uploadedFile = new UploadedFile($filePath, 'test.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $data = $reader->read($uploadedFile);

        $this->assertCount(1, $data['products']);
        $this->assertEquals('', $data['products'][0]['description']);
        $this->assertEquals('', $data['products'][0]['category']);
        $this->assertEquals('', $data['products'][0]['brand']);
        $this->assertEquals('', $data['products'][0]['cost_price']);
        $this->assertEquals('', $data['products'][0]['barcode']);

        unlink($filePath);
    }

    public function test_skips_completely_empty_rows(): void
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
        $uploadedFile = new UploadedFile($filePath, 'test.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $data = $reader->read($uploadedFile);

        $this->assertCount(2, $data['products']);

        unlink($filePath);
    }

    public function test_handles_missing_optional_columns(): void
    {
        $productsHeaders = ['SKU', 'Product Name', 'Product Type', 'Selling Price'];
        $productsData = [
            ['SKU001', 'Minimal Product', 'single', 10.00],
        ];

        $filePath = $this->createTestExcelFile(
            array_merge([$productsHeaders], $productsData),
            [[]]
        );

        $reader = new ProductImportReader();
        $uploadedFile = new UploadedFile($filePath, 'test.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $data = $reader->read($uploadedFile);

        $this->assertCount(1, $data['products']);
        $this->assertEquals('SKU001', $data['products'][0]['sku']);
        $this->assertEquals('Minimal Product', $data['products'][0]['product_name']);
        $this->assertEquals('single', $data['products'][0]['product_type']);
        $this->assertEquals('10', $data['products'][0]['selling_price']);

        unlink($filePath);
    }

    public function test_detects_sheet_names_case_insensitive(): void
    {
        $spreadsheet = new Spreadsheet();
        $productsSheet = $spreadsheet->getActiveSheet();
        $productsSheet->setTitle('PRODUCTS');
        $productsSheet->setCellValue('A1', 'SKU');

        $variantsSheet = $spreadsheet->createSheet();
        $variantsSheet->setTitle('VARIANTS');
        $variantsSheet->setCellValue('A1', 'Parent SKU');

        $path = sys_get_temp_dir() . '/test_case_' . uniqid() . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();

        $reader = new ProductImportReader();
        $uploadedFile = new UploadedFile($path, 'test.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $data = $reader->read($uploadedFile);

        $this->assertTrue($data['has_products_sheet']);
        $this->assertTrue($data['has_variants_sheet']);

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

    public function test_column_mapper_handles_full_description_mapping(): void
    {
        $fileHeaders = ['SKU', 'Product Name', 'Full Description'];
        $mapped = ColumnMapper::autoMap($fileHeaders);

        $this->assertEquals('name', $mapped['Product Name']);
        $this->assertEquals('description', $mapped['Full Description']);
    }

    public function test_numerical_values_preserved_as_strings(): void
    {
        $productsHeaders = ['SKU', 'Product Name', 'Product Type', 'Selling Price', 'Cost Price', 'Stock'];
        $productsData = [
            ['SKU001', 'Test', 'single', 19.99, 10.00, 50],
        ];

        $filePath = $this->createTestExcelFile(
            array_merge([$productsHeaders], $productsData),
            [[]]
        );

        $reader = new ProductImportReader();
        $uploadedFile = new UploadedFile($filePath, 'test.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $data = $reader->read($uploadedFile);

        $this->assertEquals('19.99', $data['products'][0]['selling_price']);
        $this->assertEquals('10', $data['products'][0]['cost_price']);
        $this->assertEquals('50', $data['products'][0]['stock']);

        unlink($filePath);
    }

    public function test_status_is_parsed_when_present(): void
    {
        $productsHeaders = ['SKU', 'Product Name', 'Product Type', 'Selling Price', 'Status'];
        $productsData = [
            ['SKU001', 'Test Product', 'single', 10.00, 'active'],
        ];

        $filePath = $this->createTestExcelFile(
            array_merge([$productsHeaders], $productsData),
            [[]]
        );

        $reader = new ProductImportReader();
        $uploadedFile = new UploadedFile($filePath, 'test.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $data = $reader->read($uploadedFile);

        $this->assertEquals('active', $data['products'][0]['status']);

        unlink($filePath);
    }

    public function test_find_sheet_prefers_exact_match(): void
    {
        $spreadsheet = new Spreadsheet();
        $productsSheet = $spreadsheet->getActiveSheet();
        $productsSheet->setTitle('Products');
        $productsSheet->setCellValue('A1', 'SKU');

        $path = sys_get_temp_dir() . '/test_find_' . uniqid() . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();

        $reader = new ProductImportReader();
        $uploadedFile = new UploadedFile($path, 'test.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $data = $reader->read($uploadedFile);

        $this->assertTrue($data['has_products_sheet']);

        unlink($path);
    }

    public function test_handles_bom_in_header_cells(): void
    {
        $spreadsheet = new Spreadsheet();
        $productsSheet = $spreadsheet->getActiveSheet();
        $productsSheet->setTitle('Products');
        $productsSheet->setCellValueByColumnAndRow(1, 1, "\xEF\xBB\xBFSKU");
        $productsSheet->setCellValueByColumnAndRow(2, 1, "\xEF\xBB\xBFProduct Name");
        $productsSheet->setCellValueByColumnAndRow(3, 1, "\xEF\xBB\xBFProduct Type");
        $productsSheet->setCellValueByColumnAndRow(4, 1, "\xEF\xBB\xBFSelling Price");
        $productsSheet->setCellValueByColumnAndRow(1, 2, 'SKU001');
        $productsSheet->setCellValueByColumnAndRow(2, 2, 'Test Product');
        $productsSheet->setCellValueByColumnAndRow(3, 2, 'single');
        $productsSheet->setCellValueByColumnAndRow(4, 2, 10.00);

        $path = sys_get_temp_dir() . '/test_bom_' . uniqid() . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();

        $reader = new ProductImportReader();
        $uploadedFile = new UploadedFile($path, 'test.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $data = $reader->read($uploadedFile);

        $this->assertTrue($data['has_products_sheet']);
        $this->assertCount(1, $data['products']);
        $this->assertEquals('SKU001', $data['products'][0]['sku']);
        $this->assertEquals('Test Product', $data['products'][0]['product_name']);
        $this->assertEquals('single', $data['products'][0]['product_type']);

        unlink($path);
    }

    public function test_handles_numeric_values_correctly(): void
    {
        $spreadsheet = new Spreadsheet();
        $productsSheet = $spreadsheet->getActiveSheet();
        $productsSheet->setTitle('Products');
        $productsSheet->setCellValueByColumnAndRow(1, 1, 'SKU');
        $productsSheet->setCellValueByColumnAndRow(2, 1, 'Product Name');
        $productsSheet->setCellValueByColumnAndRow(3, 1, 'Product Type');
        $productsSheet->setCellValueByColumnAndRow(4, 1, 'Selling Price');
        $productsSheet->setCellValueByColumnAndRow(5, 1, 'Cost Price');
        $productsSheet->setCellValueByColumnAndRow(6, 1, 'Stock');
        $productsSheet->setCellValueByColumnAndRow(1, 2, 'SKU001');
        $productsSheet->setCellValueByColumnAndRow(2, 2, 'Test Product');
        $productsSheet->setCellValueByColumnAndRow(3, 2, 'single');
        $productsSheet->setCellValueByColumnAndRow(4, 2, 19.99);
        $productsSheet->setCellValueByColumnAndRow(5, 2, 10.00);
        $productsSheet->setCellValueByColumnAndRow(6, 2, 50);

        $path = sys_get_temp_dir() . '/test_numeric_' . uniqid() . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();

        $reader = new ProductImportReader();
        $uploadedFile = new UploadedFile($path, 'test.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $data = $reader->read($uploadedFile);

        $this->assertCount(1, $data['products']);
        $this->assertEquals('SKU001', $data['products'][0]['sku']);
        $this->assertEquals('19.99', $data['products'][0]['selling_price']);
        $this->assertEquals('10', $data['products'][0]['cost_price']);
        $this->assertEquals('50', $data['products'][0]['stock']);

        unlink($path);
    }
}
