<?php

namespace App\Services\ImportExport;

use App\Enums\ProductType;
use App\Exports\ProductDataExport;
use App\Exports\ProductExportSheet;
use App\Exports\VariantExportSheet;
use App\Exports\VariableProductExport;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ImportExport\FormatHandlers\CsvHandler;
use App\Services\ImportExport\FormatHandlers\GoogleSheetsHandler;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelFormat;

class ProductExportService
{
    public function __construct(
        private readonly CsvHandler $csvHandler,
        private readonly GoogleSheetsHandler $sheetsHandler,
    ) {}

    public function export(string $format, array $filters, int $tenantId)
    {
        $query = Product::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->with(['category', 'brand', 'unit', 'variants']);

        $this->applyFilters($query, $filters);

        $products = $query->orderBy('name')->get();

        if ($products->isEmpty()) {
            return response()->json(['error' => 'No products found to export.'], 422);
        }

        [$productRows, $variantRows] = $this->buildExportData($products);

        $filename = 'products_' . now()->format('Y-m-d_His');

        if ($format === 'xlsx') {
            return Excel::download(
                new ProductDataExport($productRows, $variantRows, [], [], []),
                "{$filename}.xlsx",
                ExcelFormat::XLSX
            );
        }

        if ($format === 'google_sheets') {
            return $this->exportToGoogleSheets($productRows, $variantRows);
        }

        return $this->csvHandler->write(
            $this->getProductsheetHeaders(),
            $productRows,
            $filename
        );
    }

    public function exportProducts(string $format, array $filters, int $tenantId)
    {
        $query = Product::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('type', ProductType::SINGLE)
            ->with(['category', 'brand', 'unit']);

        $this->applyFilters($query, $filters);

        $products = $query->orderBy('name')->get();

        if ($products->isEmpty()) {
            return response()->json(['error' => 'No products found to export.'], 422);
        }

        $rows = [];
        foreach ($products as $product) {
            $rows[] = [
                $product->sku ?? '',
                $product->name,
                $product->type === ProductType::VARIABLE ? 'variable' : 'single',
                $product->short_description ?? '',
                $product->description ?? '',
                $product->category?->name ?? '',
                $product->brand?->name ?? '',
                $product->unit?->name ?? '',
                $product->price,
                $product->cost_price ?? '',
                $product->type === ProductType::VARIABLE ? '' : ($product->stock ?? 0),
                $product->barcode ?? '',
                $product->status,
                '',
            ];
        }

        $filename = 'products_' . now()->format('Y-m-d_His');

        if ($format === 'xlsx') {
            return Excel::download(new ProductExportSheet($rows), "{$filename}.xlsx", ExcelFormat::XLSX);
        }

        return $this->csvHandler->write($this->getProductsheetHeaders(), $rows, $filename);
    }

    public function exportVariants(string $format, array $filters, int $tenantId)
    {
        $query = ProductVariant::withoutTenantScope()
            ->where('product_variants.tenant_id', $tenantId)
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('products.type', ProductType::VARIABLE)
            ->select('product_variants.*')
            ->with('product');

        if (!empty($filters['ids'])) {
            $query->whereIn('product_variants.id', (array) $filters['ids']);
        }

        $variants = $query->orderBy('product_variants.sku')->get();

        if ($variants->isEmpty()) {
            return response()->json(['error' => 'No variants found to export.'], 422);
        }

        $rows = [];
        foreach ($variants as $variant) {
            $attrs = $variant->attributes ?? [];
            $attrKeys = array_keys($attrs);
            $attrValues = array_values($attrs);

            $rows[] = [
                $variant->product?->sku ?? '',
                $variant->sku ?? '',
                $attrKeys[0] ?? '',
                $attrValues[0] ?? '',
                $attrKeys[1] ?? '',
                $attrValues[1] ?? '',
                $attrKeys[2] ?? '',
                $attrValues[2] ?? '',
                $variant->price ?? $variant->product?->price ?? '',
                $variant->cost_price ?? '',
                $variant->stock ?? 0,
                $variant->barcode ?? '',
                $variant->status ?? 'active',
            ];
        }

        $filename = 'variants_' . now()->format('Y-m-d_His');

        $headers = [
            'Parent SKU', 'Variant SKU', 'Option 1 Name', 'Option 1 Value',
            'Option 2 Name', 'Option 2 Value', 'Option 3 Name', 'Option 3 Value',
            'Selling Price', 'Cost Price', 'Stock', 'Barcode', 'Status',
        ];

        if ($format === 'xlsx') {
            return Excel::download(new VariantExportSheet($rows), "{$filename}.xlsx", ExcelFormat::XLSX);
        }

        return $this->csvHandler->write($headers, $rows, $filename);
    }

    public function exportVariableProducts(string $format, array $filters, int $tenantId)
    {
        $query = Product::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('type', ProductType::VARIABLE)
            ->with(['category', 'brand', 'unit', 'variants']);

        $this->applyFilters($query, $filters);

        $products = $query->orderBy('name')->get();

        if ($products->isEmpty()) {
            return response()->json(['error' => 'No variable products found to export.'], 422);
        }

        $productRows = [];
        $variantRows = [];

        foreach ($products as $product) {
            $productRows[] = [
                $product->sku ?? '',
                $product->name,
                'variable',
                $product->short_description ?? '',
                $product->description ?? '',
                $product->category?->name ?? '',
                $product->brand?->name ?? '',
                $product->unit?->name ?? '',
                $product->price,
                $product->cost_price ?? '',
                '',
                $product->barcode ?? '',
                $product->status,
            ];

            foreach ($product->variants as $variant) {
                $attrs = $variant->attributes ?? [];
                $attrKeys = array_keys($attrs);
                $attrValues = array_values($attrs);

                $variantRows[] = [
                    $product->sku,
                    $variant->sku ?? '',
                    $attrKeys[0] ?? '',
                    $attrValues[0] ?? '',
                    $attrKeys[1] ?? '',
                    $attrValues[1] ?? '',
                    $attrKeys[2] ?? '',
                    $attrValues[2] ?? '',
                    $variant->price ?? $product->price,
                    $variant->cost_price ?? '',
                    $variant->stock ?? 0,
                    $variant->barcode ?? '',
                    $variant->status ?? 'active',
                ];
            }
        }

        $filename = 'variable_products_' . now()->format('Y-m-d_His');

        if ($format === 'xlsx') {
            return Excel::download(
                new VariableProductExport($productRows, $variantRows),
                "{$filename}.xlsx",
                ExcelFormat::XLSX
            );
        }

        return $this->csvHandler->write(
            $this->getVariableProductSheetHeaders(),
            $productRows,
            $filename
        );
    }

    private function applyFilters($query, array $filters): void
    {
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'LIKE', "%{$filters['search']}%")
                  ->orWhere('sku', 'LIKE', "%{$filters['search']}%")
                  ->orWhere('barcode', 'LIKE', "%{$filters['search']}%");
            });
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['brand_id'])) {
            $query->where('brand_id', $filters['brand_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['stock'])) {
            match ($filters['stock']) {
                'in_stock' => $query->where('stock', '>', 10),
                'low_stock' => $query->where('stock', '>', 0)->where('stock', '<=', 10),
                'out_of_stock' => $query->where('stock', '<=', 0),
                default => null,
            };
        }

        if (!empty($filters['ids'])) {
            $query->whereIn('id', (array) $filters['ids']);
        }
    }

    private function buildExportData($products): array
    {
        $productRows = [];
        $variantRows = [];

        foreach ($products as $product) {
            $productRows[] = [
                $product->sku ?? '',
                $product->name,
                $product->type === ProductType::VARIABLE ? 'variable' : 'single',
                $product->short_description ?? '',
                $product->description ?? '',
                $product->category?->name ?? '',
                $product->brand?->name ?? '',
                $product->unit?->name ?? '',
                $product->price,
                $product->cost_price ?? '',
                $product->type === ProductType::VARIABLE ? '' : ($product->stock ?? 0),
                $product->barcode ?? '',
                $product->status,
                '',
            ];

            if ($product->type === ProductType::VARIABLE) {
                foreach ($product->variants as $variant) {
                    $attrs = $variant->attributes ?? [];
                    $attrKeys = array_keys($attrs);
                    $attrValues = array_values($attrs);

                    $variantRows[] = [
                        $product->sku,
                        $variant->sku ?? '',
                        $attrKeys[0] ?? '',
                        $attrValues[0] ?? '',
                        $attrKeys[1] ?? '',
                        $attrValues[1] ?? '',
                        $attrKeys[2] ?? '',
                        $attrValues[2] ?? '',
                        $variant->price ?? $product->price,
                        $variant->cost_price ?? '',
                        $variant->stock ?? 0,
                        $variant->barcode ?? '',
                        $variant->status ?? 'active',
                    ];
                }
            }
        }

        return [$productRows, $variantRows];
    }

    private function getProductsheetHeaders(): array
    {
        return [
            'SKU', 'Product Name', 'Product Type', 'Description', 'Full Description',
            'Category', 'Brand', 'Unit', 'Selling Price', 'Cost Price',
            'Stock', 'Barcode', 'Status', 'Notes',
        ];
    }

    private function getVariableProductSheetHeaders(): array
    {
        return [
            'Parent SKU', 'Parent Name', 'Category', 'Brand', 'Unit',
            'Variant SKU', 'Option 1 Name', 'Option 1 Value',
            'Option 2 Name', 'Option 2 Value', 'Option 3 Name', 'Option 3 Value',
            'Selling Price', 'Cost Price', 'Stock', 'Barcode', 'Status',
        ];
    }

    private function exportToGoogleSheets(array $productRows, array $variantRows)
    {
        $token = session('google_sheets_token');
        if (!$token) {
            return response()->json(['error' => 'Google Sheets not connected. Please connect first.'], 401);
        }

        try {
            $this->sheetsHandler->setAccessToken($token);

            if ($this->sheetsHandler->isTokenExpired()) {
                $newToken = $this->sheetsHandler->refreshToken();
                $this->sheetsHandler->setAccessToken($newToken);
                session(['google_sheets_token' => $newToken]);
            }

            $result = $this->sheetsHandler->createSpreadsheet('Product Export - ' . now()->format('Y-m-d'));
            $spreadsheetId = $result['spreadsheetId'];

            $this->sheetsHandler->write($spreadsheetId, 'Products!A1', $this->getProductsheetHeaders(), $productRows);

            if (!empty($variantRows)) {
                $variantHeaders = [
                    'Parent SKU', 'Variant SKU', 'Option 1 Name', 'Option 1 Value',
                    'Option 2 Name', 'Option 2 Value', 'Option 3 Name', 'Option 3 Value',
                    'Selling Price', 'Cost Price', 'Stock', 'Barcode', 'Status',
                ];
                $this->sheetsHandler->write($spreadsheetId, 'Variants!A1', $variantHeaders, $variantRows);
            }

            return response()->json([
                'success' => true,
                'url' => $result['url'],
                'spreadsheetId' => $spreadsheetId,
            ]);
        } catch (\Throwable $e) {
            Log::error('Google Sheets export failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Export failed: ' . $e->getMessage()], 500);
        }
    }
}
