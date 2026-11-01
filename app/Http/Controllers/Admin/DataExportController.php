<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Export\MerchantDataExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataExportController extends Controller
{
    public function __construct(
        private readonly MerchantDataExportService $exporter
    ) {}

    public function index()
    {
        $this->authorizeExport();

        return Inertia::render('Admin/DataExport/Index', [
            'categoryGroups' => $this->exporter->groupedCategories(),
            'formats' => [
                MerchantDataExportService::FORMAT_CSV,
                MerchantDataExportService::FORMAT_XLSX,
                MerchantDataExportService::FORMAT_JSON,
            ],
        ]);
    }

    public function download(Request $request, string $category): StreamedResponse|BinaryFileResponse
    {
        $tenant = $this->authorizeExport();

        if (!$this->exporter->hasCategory($category)) {
            abort(404, 'Unknown export category.');
        }

        $format = (string) $request->query('format', MerchantDataExportService::FORMAT_CSV);

        if (!in_array($format, [MerchantDataExportService::FORMAT_CSV, MerchantDataExportService::FORMAT_XLSX, MerchantDataExportService::FORMAT_JSON], true)) {
            abort(404, 'Unsupported export format.');
        }

        Log::info('Merchant data export requested', [
            'tenant_id' => $tenant->id,
            'user_id' => auth()->id(),
            'category' => $category,
            'format' => $format,
        ]);

        return $this->exporter->downloadCategory($category, $tenant, $format);
    }

    public function downloadAll(): BinaryFileResponse
    {
        $tenant = $this->authorizeExport();

        Log::info('Merchant data archive requested', [
            'tenant_id' => $tenant->id,
            'user_id' => auth()->id(),
            'format' => MerchantDataExportService::FORMAT_ZIP,
        ]);

        return $this->exporter->downloadArchive($tenant);
    }

    private function authorizeExport(): Tenant
    {
        if (!auth()->user()?->can('billing.view')) {
            abort(403, 'Unauthorized');
        }

        $tenant = Tenant::getCurrent();

        if (!$tenant) {
            abort(403, 'Store not found.');
        }

        return $tenant;
    }
}
