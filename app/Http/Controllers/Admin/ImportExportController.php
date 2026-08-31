<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ErrorReportExport;
use App\Exports\ProductImportTemplate;
use App\Http\Controllers\Controller;
use App\Models\ImportHistory;
use App\Services\ImportExport\ColumnMapper;
use App\Services\ImportExport\FormatHandlers\MultiSheetExcelReader;
use App\Services\ImportExport\FormatHandlers\ProductImportReader;
use App\Services\ImportExport\FormatHandlers\VariantImportReader;
use App\Services\ImportExport\FormatHandlers\GoogleSheetsHandler;
use App\Services\ImportExport\MasterDataResolver;
use App\Services\ImportExport\OrderExportService;
use App\Services\ImportExport\ProductExportService;
use App\Services\ImportExport\ProductImportEngine;
use App\Services\ImportExport\ProductImportService;
use App\Services\ImportExport\ReportExportService;
use App\Services\InventoryService;
use App\Services\SkuService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelFormat;

class ImportExportController extends Controller
{
    public function __construct(
        private readonly ProductImportService $importService,
        private readonly ProductExportService $productExportService,
        private readonly OrderExportService $orderExportService,
        private readonly ReportExportService $reportExportService,
        private readonly GoogleSheetsHandler $sheetsHandler,
    ) {}

    public function parseFile(Request $request)
    {
        if (!auth()->user()->can('products.create')) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls|max:10240',
        ]);

        try {
            $data = $this->importService->readFile($request->file('file'));
            return response()->json([
                'headers' => $data['headers'],
                'rows' => array_slice($data['rows'], 0, 5),
                'total_rows' => count($data['rows']),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to parse file: ' . $e->getMessage()], 422);
        }
    }

    public function validateImport(Request $request)
    {
        if (!auth()->user()->can('products.create')) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls|max:10240',
            'mapping' => 'required|json',
        ]);

        try {
            $tenantId = tenant()?->id;
            $data = $this->importService->readFile($request->file('file'));
            $mapping = json_decode($request->input('mapping'), true);

            $mappedRows = [];
            foreach ($data['rows'] as $row) {
                $mappedRows[] = ColumnMapper::mapRow($row, $mapping);
            }

            $validation = $this->importService->validate($mappedRows, $tenantId);

            return response()->json([
                'validation' => $validation,
                'preview' => array_slice($mappedRows, 0, 5),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Validation failed: ' . $e->getMessage()], 422);
        }
    }

    public function import(Request $request)
    {
        if (!auth()->user()->can('products.create')) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls|max:10240',
            'mapping' => 'required|json',
            'import_mode' => 'required|in:create_new,create_update,update_only',
        ]);

        try {
            $tenantId = tenant()?->id;
            $data = $this->importService->readFile($request->file('file'));
            $mapping = json_decode($request->input('mapping'), true);
            $mode = $request->input('import_mode', 'create_new');

            $mappedRows = [];
            foreach ($data['rows'] as $row) {
                $mappedRows[] = ColumnMapper::mapRow($row, $mapping);
            }

            $result = $this->importService->import($mappedRows, $tenantId, $mode);

            return response()->json($result);
        } catch (\Throwable $e) {
            Log::error('Product import failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Import failed: ' . $e->getMessage()], 500);
        }
    }

    public function template(Request $request)
    {
        $format = $request->input('format', 'xlsx');

        if ($format === 'csv') {
            $legacyType = $request->input('legacy_type', 'simple');
            $template = $this->importService->generateTemplate($legacyType);

            $callback = function () use ($template) {
                $file = fopen('php://output', 'w');
                fputcsv($file, $template['headers']);
                foreach ($template['rows'] as $row) {
                    fputcsv($file, array_values($row));
                }
                fclose($file);
            };

            return response()->stream($callback, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$template['filename']}.csv\"",
            ]);
        }

        return Excel::download(
            new ProductImportTemplate(),
            'products_import_template.xlsx',
            ExcelFormat::XLSX
        );
    }

    public function exportProducts(Request $request)
    {
        if (!auth()->user()->can('products.view')) {
            abort(403, 'Unauthorized');
        }

        $format = $request->input('format', 'xlsx');
        $scope = $request->input('scope', 'all');
        $tenantId = tenant()?->id;

        $filters = [];

        if ($scope === 'filtered') {
            $filters = $request->only(['search', 'category_id', 'brand_id', 'status', 'type', 'stock']);
        }

        if ($scope === 'selected' && $request->has('ids')) {
            $filters['ids'] = $request->input('ids');
        }

        return $this->productExportService->exportProducts($format, $filters, $tenantId);
    }

    public function exportVariants(Request $request)
    {
        if (!auth()->user()->can('products.view')) {
            abort(403, 'Unauthorized');
        }

        $format = $request->input('format', 'xlsx');
        $tenantId = tenant()?->id;
        $filters = $request->only(['ids']);

        return $this->productExportService->exportVariants($format, $filters, $tenantId);
    }

    public function validateVariableProducts(Request $request)
    {
        return $this->validateMultiSheet($request);
    }

    public function importVariableProducts(Request $request)
    {
        return $this->importMultiSheet($request);
    }

    public function exportVariableProducts(Request $request)
    {
        if (!auth()->user()->can('products.view')) {
            abort(403, 'Unauthorized');
        }

        $format = $request->input('format', 'xlsx');
        $scope = $request->input('scope', 'all');
        $tenantId = tenant()?->id;

        $filters = [];

        if ($scope === 'filtered') {
            $filters = $request->only(['search', 'category_id', 'brand_id', 'status', 'stock']);
        }

        if ($scope === 'selected' && $request->has('ids')) {
            $filters['ids'] = $request->input('ids');
        }

        return $this->productExportService->exportVariableProducts($format, $filters, $tenantId);
    }

    public function exportOrders(Request $request)
    {
        if (!auth()->user()->can('orders.view')) {
            abort(403, 'Unauthorized');
        }

        $format = $request->input('format', 'csv');
        $tenantId = tenant()?->id;

        $filters = $request->only(['search', 'order_status', 'payment_status', 'payment_method_id', 'date_from', 'date_to', 'ids']);

        return $this->orderExportService->export($format, $filters, $tenantId);
    }

    public function exportReport(Request $request)
    {
        if (!auth()->user()->can('reports.sales')) {
            abort(403, 'Unauthorized');
        }

        $format = $request->input('format', 'csv');
        $reportType = $request->input('report_type', 'sales');
        $tenantId = tenant()?->id;

        $filters = $request->only(['date_from', 'date_to', 'category_id', 'payment_method_id', 'search', 'stock_status']);

        return match ($reportType) {
            'sales' => $this->reportExportService->exportSales($format, $filters, $tenantId),
            'product_sales' => $this->reportExportService->exportProductSales($format, $filters, $tenantId),
            'payments' => $this->reportExportService->exportPayments($format, $filters, $tenantId),
            'inventory' => $this->reportExportService->exportInventory($format, $filters, $tenantId),
            'customers' => $this->reportExportService->exportCustomers($format, $filters, $tenantId),
            default => response()->json(['error' => 'Unknown report type.'], 400),
        };
    }

    public function exportGoogleSheets(Request $request)
    {
        $token = session('google_sheets_token');
        if (!$token) {
            return response()->json(['error' => 'Google Sheets not connected. Please connect first.'], 401);
        }

        $type = $request->input('type', 'products');
        $tenantId = tenant()?->id;
        $filters = $request->except(['type', 'format']);

        try {
            $this->sheetsHandler->setAccessToken($token);

            if ($this->sheetsHandler->isTokenExpired()) {
                $newToken = $this->sheetsHandler->refreshToken();
                $this->sheetsHandler->setAccessToken($newToken);
                session(['google_sheets_token' => $newToken]);
            }

            $format = 'google_sheets';
            $request->merge(['format' => $format]);

            return match ($type) {
                'products' => $this->productExportService->export($format, $filters, $tenantId),
                'orders' => $this->orderExportService->export($format, $filters, $tenantId),
                default => response()->json(['error' => 'Unsupported export type for Google Sheets.'], 400),
            };
        } catch (\Throwable $e) {
            Log::error('Google Sheets export failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Export failed: ' . $e->getMessage()], 500);
        }
    }

    public function uploadMultiSheet(Request $request)
    {
        if (!auth()->user()->can('products.create')) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'file' => 'required|file|mimes:xlsx|max:10240',
        ]);

        try {
            $reader = new MultiSheetExcelReader();
            $structure = $reader->validateStructure($request->file('file'));

            if (!$structure['valid']) {
                return response()->json([
                    'error' => 'This file does not match the product import template. Please download the template and try again.',
                    'missing' => $structure['missing'],
                ], 422);
            }

            $data = $reader->read($request->file('file'));

            return response()->json([
                'success' => true,
                'sheets' => [
                    'products' => [
                        'headers' => $data['Products']['headers'] ?? [],
                        'rows' => $data['Products']['rows'] ?? [],
                        'total' => count($data['Products']['rows'] ?? []),
                    ],
                    'variants' => [
                        'headers' => $data['Variants']['headers'] ?? [],
                        'rows' => $data['Variants']['rows'] ?? [],
                        'total' => count($data['Variants']['rows'] ?? []),
                    ],
                    'master_data' => [
                        'categories' => $data['Categories']['rows'] ?? [],
                        'brands' => $data['Brands']['rows'] ?? [],
                        'units' => $data['Units']['rows'] ?? [],
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Multi-sheet upload failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Could not read this file. Make sure it is a valid Excel file.'], 422);
        }
    }

    public function validateMultiSheet(Request $request)
    {
        if (!auth()->user()->can('products.create')) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'file' => 'required|file|mimes:xlsx|max:10240',
        ]);

        $tenantId = tenant()?->id;
        if (!$tenantId) {
            return response()->json(['error' => 'Unable to determine your store. Please refresh and try again.'], 422);
        }

        try {
            $reader = new ProductImportReader();
            $data = $reader->read($request->file('file'));

            if (!$data['has_products_sheet']) {
                return response()->json(['error' => 'Products sheet is required. Please download the Product Import Template.'], 422);
            }

            $productRows = $data['products'] ?? [];
            $variantRows = $data['variants'] ?? [];

            $hasVariable = false;
            foreach ($productRows as $row) {
                $type = strtolower(trim((string)($row['product_type'] ?? 'single')));
                if ($type === 'variable') {
                    $hasVariable = true;
                    break;
                }
            }

            if ($hasVariable && empty($variantRows)) {
                return response()->json(['error' => 'Variants sheet is required because variable products were detected in the Products sheet.'], 422);
            }

            $resolver = new MasterDataResolver($tenantId);
            $skuService = app(SkuService::class);
            $inventoryService = app(InventoryService::class);

            $engine = new ProductImportEngine($resolver, $skuService, $inventoryService);
            $validation = $engine->validate($productRows, $variantRows);

            return response()->json([
                'valid' => $validation['valid'],
                'errors' => $validation['errors'],
                'warnings' => $validation['warnings'],
                'summary' => $validation['summary'],
                'preview' => [
                    'products' => array_slice($productRows, 0, 5),
                    'variants' => array_slice($variantRows, 0, 5),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Product validation failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json(['error' => 'Could not process this file. Please verify the template format and try again.'], 422);
        }
    }

    public function importMultiSheet(Request $request)
    {
        if (!auth()->user()->can('products.create')) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'file' => 'required|file|mimes:xlsx|max:10240',
            'import_mode' => 'required|in:create_new,create_update,update_only',
        ]);

        $tenantId = tenant()?->id;
        if (!$tenantId) {
            return response()->json(['error' => 'Unable to determine your store. Please refresh and try again.'], 422);
        }

        $startTime = microtime(true);
        $history = null;

        try {
            $reader = new ProductImportReader();
            $data = $reader->read($request->file('file'));

            if (!$data['has_products_sheet']) {
                return response()->json(['error' => 'Products sheet is required. Please download the Product Import Template.'], 422);
            }

            $productRows = $data['products'] ?? [];
            $variantRows = $data['variants'] ?? [];

            $hasVariable = false;
            foreach ($productRows as $row) {
                $type = strtolower(trim((string)($row['product_type'] ?? 'single')));
                if ($type === 'variable') {
                    $hasVariable = true;
                    break;
                }
            }

            if ($hasVariable && empty($variantRows)) {
                return response()->json(['error' => 'Variants sheet is required because variable products were detected in the Products sheet.'], 422);
            }

            $history = ImportHistory::create([
                'tenant_id' => $tenantId,
                'user_id' => auth()->id(),
                'file_name' => $request->file('file')->getClientOriginalName(),
                'file_type' => 'xlsx',
                'import_type' => 'products',
                'status' => ImportHistory::STATUS_IMPORTING,
                'import_mode' => $request->input('import_mode'),
            ]);

            $resolver = new MasterDataResolver($tenantId);
            $skuService = app(SkuService::class);
            $inventoryService = app(InventoryService::class);

            $engine = new ProductImportEngine($resolver, $skuService, $inventoryService);
            $mode = $request->input('import_mode', 'create_new');

            $validation = $engine->validate($productRows, $variantRows);
            $allErrors = array_merge($validation['errors'], $validation['warnings']);
            $errorReportPath = null;

            if (!empty($allErrors)) {
                $errorReportPath = "import-errors/{$tenantId}/errors_{$history->id}.xlsx";
                Storage::disk('local')->put($errorReportPath, Excel::raw(new ErrorReportExport($allErrors), ExcelFormat::XLSX));
            }

            $history->update([
                'total_rows' => count($productRows) + count($variantRows),
                'total_products' => count($productRows),
                'total_variants' => count($variantRows),
                'warning_count' => $validation['summary']['warning_count'] ?? 0,
                'error_count' => count($validation['errors']),
                'errors' => $validation['errors'],
                'warnings' => $validation['warnings'],
                'error_report_path' => $errorReportPath,
            ]);

            if (!empty($validation['errors'])) {
                $duration = (int) ((microtime(true) - $startTime) * 1000);
                $history->update([
                    'status' => ImportHistory::STATUS_FAILED,
                    'duration_ms' => $duration,
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Validation found errors. Please fix them and try again.',
                    'validation' => $validation,
                    'history_id' => $history->id,
                ], 422);
            }

            $result = $engine->import($productRows, $variantRows, $mode);
            $duration = (int) ((microtime(true) - $startTime) * 1000);

            $status = ($validation['summary']['warning_count'] ?? 0) > 0
                ? ImportHistory::STATUS_COMPLETED_WITH_WARNINGS
                : ImportHistory::STATUS_COMPLETED;

            $history->update([
                'status' => $status,
                'products_created' => $result['products_created'] ?? 0,
                'products_skipped' => $result['products_skipped'] ?? 0,
                'variants_created' => $result['variants_created'] ?? 0,
                'variants_skipped' => $result['variants_skipped'] ?? 0,
                'duration_ms' => $duration,
            ]);

            return response()->json([
                'success' => true,
                'result' => $result,
                'history_id' => $history->id,
                'has_warnings' => ($validation['summary']['warning_count'] ?? 0) > 0,
            ]);
        } catch (\Throwable $e) {
            $duration = (int) ((microtime(true) - $startTime) * 1000);
            if ($history) {
                $history->update([
                    'status' => ImportHistory::STATUS_FAILED,
                    'duration_ms' => $duration,
                ]);
            }

            Log::error('Product import failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Import failed. No changes were made. Please check your file and try again.'], 500);
        }
    }

    public function validateVariantSheet(Request $request)
    {
        if (!auth()->user()->can('products.create')) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'file' => 'required|file|mimes:xlsx|max:10240',
        ]);

        $tenantId = tenant()?->id;
        if (!$tenantId) {
            return response()->json(['error' => 'Unable to determine your store. Please refresh and try again.'], 422);
        }

        try {
            $reader = new VariantImportReader();
            $structure = $reader->validateStructure($request->file('file'));

            if (!$structure['valid']) {
                return response()->json(['error' => 'This file does not have a "Variants" sheet. Please use the variant import template.'], 422);
            }

            $data = $reader->read($request->file('file'));

            $resolver = new MasterDataResolver($tenantId);
            $skuService = app(SkuService::class);
            $inventoryService = app(InventoryService::class);

            $engine = new ProductImportEngine($resolver, $skuService, $inventoryService);
            $validation = $engine->validateVariantsOnly($data['rows'] ?? []);

            return response()->json([
                'valid' => $validation['valid'],
                'errors' => $validation['errors'],
                'warnings' => $validation['warnings'],
                'summary' => $validation['summary'],
                'preview' => array_slice($data['rows'] ?? [], 0, 5),
            ]);
        } catch (\Throwable $e) {
            Log::error('Variant validation failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json(['error' => 'Could not process this file. Please make sure it matches the variant import template.'], 422);
        }
    }

    public function importVariantSheet(Request $request)
    {
        if (!auth()->user()->can('products.create')) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'file' => 'required|file|mimes:xlsx|max:10240',
            'import_mode' => 'required|in:create_new,create_update,update_only',
        ]);

        $tenantId = tenant()?->id;
        if (!$tenantId) {
            return response()->json(['error' => 'Unable to determine your store. Please refresh and try again.'], 422);
        }

        $startTime = microtime(true);
        $history = null;

        try {
            $history = ImportHistory::create([
                'tenant_id' => $tenantId,
                'user_id' => auth()->id(),
                'file_name' => $request->file('file')->getClientOriginalName(),
                'file_type' => 'xlsx',
                'import_type' => 'variants',
                'status' => ImportHistory::STATUS_IMPORTING,
                'import_mode' => $request->input('import_mode'),
            ]);

            $reader = new VariantImportReader();
            $data = $reader->read($request->file('file'));

            $resolver = new MasterDataResolver($tenantId);
            $skuService = app(SkuService::class);
            $inventoryService = app(InventoryService::class);

            $engine = new ProductImportEngine($resolver, $skuService, $inventoryService);
            $mode = $request->input('import_mode', 'create_new');

            $variantRows = $data['rows'] ?? [];

            $validation = $engine->validateVariantsOnly($variantRows);
            $allErrors = array_merge($validation['errors'], $validation['warnings']);
            $errorReportPath = null;

            if (!empty($allErrors)) {
                $errorReportPath = "import-errors/{$tenantId}/errors_{$history->id}.xlsx";
                Storage::disk('local')->put($errorReportPath, Excel::raw(new ErrorReportExport($allErrors), ExcelFormat::XLSX));
            }

            $history->update([
                'total_rows' => count($variantRows),
                'total_variants' => count($variantRows),
                'warning_count' => $validation['summary']['warning_count'] ?? 0,
                'error_count' => count($validation['errors']),
                'errors' => $validation['errors'],
                'warnings' => $validation['warnings'],
                'error_report_path' => $errorReportPath,
            ]);

            if (!empty($validation['errors'])) {
                $duration = (int) ((microtime(true) - $startTime) * 1000);
                $history->update([
                    'status' => ImportHistory::STATUS_FAILED,
                    'duration_ms' => $duration,
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Validation found errors. Please fix them and try again.',
                    'validation' => $validation,
                    'history_id' => $history->id,
                ], 422);
            }

            $result = $engine->importVariants($variantRows, $mode);
            $duration = (int) ((microtime(true) - $startTime) * 1000);

            $status = ($validation['summary']['warning_count'] ?? 0) > 0
                ? ImportHistory::STATUS_COMPLETED_WITH_WARNINGS
                : ImportHistory::STATUS_COMPLETED;

            $history->update([
                'status' => $status,
                'variants_created' => $result['variants_created'] ?? 0,
                'duration_ms' => $duration,
            ]);

            return response()->json([
                'success' => true,
                'result' => $result,
                'history_id' => $history->id,
                'has_warnings' => ($validation['summary']['warning_count'] ?? 0) > 0,
            ]);
        } catch (\Throwable $e) {
            $duration = (int) ((microtime(true) - $startTime) * 1000);
            if ($history) {
                $history->update([
                    'status' => ImportHistory::STATUS_FAILED,
                    'duration_ms' => $duration,
                ]);
            }

            Log::error('Variant import failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json(['error' => 'Import failed. No changes were made. Please check your file and try again.'], 500);
        }
    }

    public function importHistory(Request $request)
    {
        if (!auth()->user()->can('products.view')) {
            abort(403, 'Unauthorized');
        }

        $tenantId = tenant()?->id;

        $query = ImportHistory::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->with('user');

        if ($search = $request->input('search')) {
            $query->where('file_name', 'LIKE', "%{$search}%");
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($fileType = $request->input('file_type')) {
            $query->where('file_type', $fileType);
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }

        $imports = $query->orderByDesc('created_at')->paginate(15)->withQueryString();

        return response()->json([
            'imports' => $imports,
        ]);
    }

    public function importHistoryShow(ImportHistory $importHistory)
    {
        if (!auth()->user()->can('products.view')) {
            abort(403, 'Unauthorized');
        }

        if ($importHistory->tenant_id !== tenant()?->id) {
            abort(403, 'Unauthorized');
        }

        $importHistory->load('user');

        return response()->json([
            'import' => $importHistory,
        ]);
    }

    public function importErrorReport(ImportHistory $importHistory)
    {
        if (!auth()->user()->can('products.view')) {
            abort(403, 'Unauthorized');
        }

        if ($importHistory->tenant_id !== tenant()?->id) {
            abort(403, 'Unauthorized');
        }

        if (!$importHistory->hasErrorReport()) {
            abort(404, 'No error report available.');
        }

        if (!Storage::disk('local')->exists($importHistory->error_report_path)) {
            abort(404, 'Error report file not found.');
        }

        return response()->download(
            Storage::disk('local')->path($importHistory->error_report_path),
            'product-import-errors.xlsx'
        );
    }

    public function importHistoryPage(Request $request)
    {
        if (!auth()->user()->can('products.view')) {
            abort(403, 'Unauthorized');
        }

        $tenantId = tenant()?->id;

        $query = ImportHistory::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->with('user');

        if ($search = $request->input('search')) {
            $query->where('file_name', 'LIKE', "%{$search}%");
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $imports = $query->orderByDesc('created_at')->paginate(15)->withQueryString();

        return Inertia::render('Admin/ImportHistory/Index', [
            'imports' => $imports,
            'filters' => $request->only(['search', 'status', 'date_from', 'date_to']),
        ]);
    }

    public function importHistoryShowPage(ImportHistory $importHistory)
    {
        if (!auth()->user()->can('products.view')) {
            abort(403, 'Unauthorized');
        }

        if ($importHistory->tenant_id !== tenant()?->id) {
            abort(403, 'Unauthorized');
        }

        $importHistory->load('user');

        return Inertia::render('Admin/ImportHistory/Show', [
            'import' => $importHistory,
        ]);
    }
}
