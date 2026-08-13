<?php

namespace App\Services\ImportExport;

use App\Enums\ProductType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Unit;
use App\Services\ImportExport\FormatHandlers\CsvHandler;
use App\Services\ImportExport\FormatHandlers\ExcelHandler;
use App\Services\ImportExport\FormatHandlers\GoogleSheetsHandler;
use App\Services\InventoryService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProductImportService
{
    public function __construct(
        private readonly CsvHandler $csvHandler,
        private readonly ExcelHandler $excelHandler,
        private readonly GoogleSheetsHandler $sheetsHandler,
        private readonly ImportValidator $validator,
        private readonly ColumnMapper $columnMapper,
        private readonly InventoryService $inventoryService,
    ) {}

    /**
     * Read file and return raw data with headers.
     */
    public function readFile(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (in_array($extension, ['xlsx', 'xls'])) {
            return $this->excelHandler->read($file);
        }

        return $this->csvHandler->read($file);
    }

    /**
     * Read from Google Sheets.
     */
    public function readGoogleSheet(string $spreadsheetId, string $range, array $token): array
    {
        $this->sheetsHandler->setAccessToken($token);

        if ($this->sheetsHandler->isTokenExpired()) {
            $token = $this->sheetsHandler->refreshToken();
            $this->sheetsHandler->setAccessToken($token);
        }

        return $this->sheetsHandler->read($spreadsheetId, $range);
    }

    /**
     * Get Google Sheets worksheets.
     */
    public function getGoogleSheetsWorksheets(string $spreadsheetId, array $token): array
    {
        $this->sheetsHandler->setAccessToken($token);
        return $this->sheetsHandler->getWorksheets($spreadsheetId);
    }

    /**
     * Validate import data before processing.
     */
    public function validate(array $rows, int $tenantId): array
    {
        return $this->validator->validateAll($rows, $tenantId);
    }

    /**
     * Process the import.
     *
     * @param array $rows Processed rows (after column mapping)
     * @param int $tenantId Current tenant ID
     * @param string $mode 'create_new', 'create_update', 'update_only'
     * @return array Results summary
     */
    public function import(array $rows, int $tenantId, string $mode = 'create_new'): array
    {
        $results = [
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        // Group rows: parent products and their variants
        $grouped = $this->groupRows($rows);

        DB::beginTransaction();

        try {
            // Process simple products first
            foreach ($grouped['simple'] as $index => $row) {
                $lineNumber = $index + 2;
                try {
                    $result = $this->processSimpleProduct($row, $tenantId, $mode);
                    $results[$result]++;
                } catch (\Throwable $e) {
                    $results['failed']++;
                    $results['errors'][] = "Line {$lineNumber}: " . $e->getMessage();
                }
            }

            // Process variable products
            foreach ($grouped['variable'] as $parentSku => $group) {
                try {
                    $result = $this->processVariableProduct($group['parent'], $group['variants'], $tenantId, $mode);
                    $results[$result['parent']]++;
                    foreach ($result['variants'] as $v) {
                        $results[$v]++;
                    }
                } catch (\Throwable $e) {
                    $results['failed']++;
                    $results['errors'][] = "Variable product '{$parentSku}': " . $e->getMessage();
                }
            }

            if (empty($results['errors'])) {
                DB::commit();
            } else {
                DB::rollBack();
                $results['imported'] = 0;
                $results['updated'] = 0;
                $results['skipped'] = 0;
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Product import failed', ['error' => $e->getMessage()]);
            $results['errors'][] = 'Import failed: ' . $e->getMessage();
        }

        return $results;
    }

    /**
     * Group rows into simple products and variable product groups.
     */
    private function groupRows(array $rows): array
    {
        $simple = [];
        $variable = [];

        foreach ($rows as $row) {
            $type = strtolower(trim($row['type'] ?? 'single'));
            $parentSku = $row['parent_sku'] ?? null;

            if ($parentSku) {
                // This is a variant row
                if (!isset($variable[$parentSku])) {
                    $variable[$parentSku] = ['parent' => null, 'variants' => []];
                }
                $variable[$parentSku]['variants'][] = $row;
            } elseif ($type === 'variable') {
                // This is a variable parent
                $sku = $row['sku'] ?? Str::slug($row['name']);
                if (!isset($variable[$sku])) {
                    $variable[$sku] = ['parent' => $row, 'variants' => []];
                } else {
                    $variable[$sku]['parent'] = $row;
                }
            } else {
                $simple[] = $row;
            }
        }

        return ['simple' => $simple, 'variable' => $variable];
    }

    /**
     * Process a simple (single) product.
     */
    private function processSimpleProduct(array $row, int $tenantId, string $mode): string
    {
        $sku = $row['sku'] ?? null;

        // Check for existing product
        $existing = null;
        if ($sku) {
            $existing = Product::withoutTenantScope()
                ->where('tenant_id', $tenantId)
                ->where('sku', $sku)
                ->first();
        }

        if ($existing && $mode === 'create_new') {
            return 'skipped';
        }

        if (!$existing && $mode === 'update_only') {
            return 'skipped';
        }

        $productData = $this->mapProductData($row, $tenantId);

        if ($existing) {
            $existing->update($productData);
            $this->handleStockUpdate($existing, $row);
            return 'updated';
        }

        $product = Product::withoutTenantScope()->create($productData);
        $this->handleStockUpdate($product, $row);
        return 'imported';
    }

    /**
     * Process a variable product with its variants.
     */
    private function processVariableProduct(?array $parentRow, array $variantRows, int $tenantId, string $mode): array
    {
        $result = ['parent' => 'skipped', 'variants' => []];

        if (!$parentRow) {
            // Parent row not in file, try to find existing by SKU from first variant
            $firstVariant = $variantRows[0] ?? null;
            $parentSku = $firstVariant['parent_sku'] ?? null;
            if (!$parentSku) {
                throw new \RuntimeException('Cannot determine parent product.');
            }
            $parent = Product::withoutTenantScope()
                ->where('tenant_id', $tenantId)
                ->where('sku', $parentSku)
                ->first();

            if (!$parent) {
                throw new \RuntimeException("Parent product with SKU '{$parentSku}' not found.");
            }
        } else {
            $sku = $parentRow['sku'] ?? null;
            $existing = null;

            if ($sku) {
                $existing = Product::withoutTenantScope()
                    ->where('tenant_id', $tenantId)
                    ->where('sku', $sku)
                    ->first();
            }

            if ($existing && $mode === 'create_new') {
                $parent = $existing;
                $result['parent'] = 'skipped';
            } elseif (!$existing && $mode === 'update_only') {
                return $result;
            } else {
                $productData = $this->mapProductData($parentRow, $tenantId);
                $productData['type'] = ProductType::VARIABLE;

                if ($existing) {
                    $existing->update($productData);
                    $parent = $existing;
                    $result['parent'] = 'updated';
                } else {
                    $parent = Product::withoutTenantScope()->create($productData);
                    $result['parent'] = 'imported';
                }
            }
        }

        // Process variants
        foreach ($variantRows as $variantRow) {
            try {
                $variantResult = $this->processVariant($variantRow, $parent, $tenantId, $mode);
                $result['variants'][] = $variantResult;
            } catch (\Throwable $e) {
                $result['variants'][] = 'failed';
            }
        }

        return $result;
    }

    /**
     * Process a single variant.
     */
    private function processVariant(array $row, $parent, int $tenantId, string $mode): string
    {
        $variantSku = $row['variant_sku'] ?? null;

        $existing = null;
        if ($variantSku) {
            $existing = ProductVariant::withoutTenantScope()
                ->where('product_id', $parent->id)
                ->where('sku', $variantSku)
                ->first();
        }

        if ($existing && $mode === 'create_new') {
            return 'skipped';
        }

        // Build attributes array
        $attributes = [];
        if (!empty($row['attribute_name']) && !empty($row['attribute_value'])) {
            $attributes[$row['attribute_name']] = $row['attribute_value'];
        }

        $variantData = [
            'product_id' => $parent->id,
            'sku' => $variantSku,
            'barcode' => $row['variant_barcode'] ?? $row['barcode'] ?? null,
            'price' => (float) ($row['variant_price'] ?? $row['price'] ?? 0),
            'cost_price' => (float) ($row['variant_cost'] ?? $row['cost_price'] ?? 0),
            'stock' => (int) ($row['variant_stock'] ?? $row['stock'] ?? 0),
            'attributes' => $attributes,
            'status' => 'active',
        ];

        if ($existing) {
            $existing->update($variantData);
            return 'updated';
        }

        ProductVariant::withoutTenantScope()->create($variantData);
        return 'imported';
    }

    /**
     * Map row data to product model fields.
     */
    private function mapProductData(array $row, int $tenantId): array
    {
        $type = strtolower(trim($row['type'] ?? 'single'));
        $productType = match ($type) {
            'variable' => ProductType::VARIABLE,
            'combo' => ProductType::COMBO,
            default => ProductType::SINGLE,
        };

        $data = [
            'tenant_id' => $tenantId,
            'name' => trim($row['name']),
            'slug' => Str::slug($row['name']) . '-' . Str::random(5),
            'sku' => $row['sku'] ?? null,
            'barcode' => $row['barcode'] ?? null,
            'short_description' => $row['short_description'] ?? $row['description'] ?? null,
            'description' => $row['full_description'] ?? null,
            'price' => (float) ($row['price'] ?? 0),
            'cost_price' => (float) ($row['cost_price'] ?? 0),
            'stock' => (int) ($row['stock'] ?? 0),
            'weight' => is_numeric($row['weight'] ?? null) ? (float) $row['weight'] : null,
            'status' => strtolower(trim($row['status'] ?? 'active')),
            'type' => $productType,
        ];

        // Resolve category
        if (!empty($row['category'])) {
            $category = Category::withoutTenantScope()
                ->where('tenant_id', $tenantId)
                ->where('name', $row['category'])
                ->first();
            if ($category) {
                $data['category_id'] = $category->id;
            }
        }

        // Resolve brand
        if (!empty($row['brand'])) {
            $brand = Brand::withoutTenantScope()
                ->where('tenant_id', $tenantId)
                ->where('name', $row['brand'])
                ->first();
            if ($brand) {
                $data['brand_id'] = $brand->id;
            }
        }

        // Resolve unit
        if (!empty($row['unit'])) {
            $unit = Unit::withoutTenantScope()
                ->where('tenant_id', $tenantId)
                ->where('name', $row['unit'])
                ->first();
            if ($unit) {
                $data['unit_id'] = $unit->id;
            }
        }

        return $data;
    }

    /**
     * Handle stock update via InventoryService.
     */
    private function handleStockUpdate(Product $product, array $row): void
    {
        // Stock is managed via the product model directly for imports
        // The InventoryService handles stock movements for order-based changes
        // For opening stock import, we update the product stock directly
        // and let the syncProductCache handle any variant stock
    }

    /**
     * Generate a template for download.
     */
    public function generateTemplate(string $type = 'simple'): array
    {
        if ($type === 'variable') {
            $headers = [
                'Product Name', 'Parent SKU', 'Variant SKU', 'Product Type',
                'Description', 'Category', 'Brand', 'Unit',
                'Selling Price', 'Cost Price', 'Barcode', 'Status',
                'Attribute Name', 'Attribute Value',
                'Variant Price', 'Variant Cost', 'Variant Stock', 'Variant Barcode',
            ];

            $sampleRows = [
                [
                    'Nike T-Shirt', 'NIKE-TS-001', '', 'variable',
                    'Premium cotton t-shirt', 'Fashion', 'Nike', 'Piece',
                    '29.99', '15.00', '123456789', 'active',
                    '', '', '', '', '', '',
                ],
                [
                    '', '', 'NIKE-TS-001-RED-S', '',
                    '', '', '', '', '', '', '', '',
                    'Color', 'Red', '29.99', '15.00', '10', '123456789-RS',
                ],
                [
                    '', '', 'NIKE-TS-001-RED-M', '',
                    '', '', '', '', '', '', '', '',
                    'Color', 'Red', '29.99', '15.00', '15', '123456789-RM',
                ],
                [
                    '', '', 'NIKE-TS-001-BLUE-S', '',
                    '', '', '', '', '', '', '', '',
                    'Color', 'Blue', '29.99', '15.00', '8', '123456789-BS',
                ],
            ];
        } else {
            $headers = [
                'Product Name', 'SKU', 'Product Type', 'Description',
                'Category', 'Brand', 'Unit', 'Selling Price',
                'Cost Price', 'Stock', 'Barcode', 'Status', 'Weight',
            ];

            $sampleRows = [
                [
                    'Wireless Mouse', 'WM-001', 'single', 'Ergonomic wireless mouse',
                    'Electronics', 'Generic', 'Piece', '19.99',
                    '10.00', '50', '123456789012', 'active', '0.2',
                ],
                [
                    'USB-C Cable', 'USB-C-001', 'single', 'Fast charging USB-C cable',
                    'Electronics', 'No Brand', 'Piece', '9.99',
                    '3.00', '100', '123456789013', 'active', '0.1',
                ],
            ];
        }

        return [
            'headers' => $headers,
            'rows' => $sampleRows,
            'filename' => "product_import_template_{$type}",
        ];
    }
}
