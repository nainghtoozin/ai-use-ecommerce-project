<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\ActivityLogger;
use App\Services\TenantDemoConfigurationImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class DemoConfigurationController extends Controller
{
    public function __construct(
        private readonly TenantDemoConfigurationImportService $importService
    ) {}

    public function index(): \Inertia\Response
    {
        $this->authorizeView();

        $tenant = $this->resolveTenant();

        return Inertia::render('Admin/Settings/DemoConfiguration', [
            'status' => $this->importService->status($tenant),
            'categories' => TenantDemoConfigurationImportService::CATEGORY_LABELS,
            'definitions' => $this->importService->definitions(),
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $this->authorizeImport();

        $tenant = $this->resolveTenant();

        $validated = $request->validate([
            'categories' => ['nullable', 'array'],
            'categories.*' => [Rule::in(TenantDemoConfigurationImportService::ALL_CATEGORIES)],
        ]);

        $summary = $this->importService->import(
            $tenant,
            $validated['categories'] ?? TenantDemoConfigurationImportService::ALL_CATEGORIES
        );

        ActivityLogger::log(
            "Demo configuration import executed for '{$tenant->name}'",
            'demo_configuration_imported',
            $tenant,
            $summary
        );

        return admin_redirect('admin.demo-configuration.index')
            ->with('success', $this->summaryMessage($summary))
            ->with('demo_import_summary', $summary);
    }

    private function resolveTenant(): Tenant
    {
        $tenant = tenant() ?? auth()->user()?->tenant;

        if (!$tenant) {
            abort(404, 'Tenant context is required.');
        }

        return $tenant;
    }

    private function authorizeView(): void
    {
        if (!auth()->user()?->can('settings.view')) {
            abort(403, 'Unauthorized');
        }
    }

    private function authorizeImport(): void
    {
        if (!auth()->user()?->can('settings.edit')) {
            abort(403, 'Unauthorized');
        }
    }

    private function summaryMessage(array $summary): string
    {
        $totals = $summary['totals'];

        return sprintf(
            'Demo configuration import finished: %d created, %d skipped, %d failed.',
            $totals['created'],
            $totals['skipped'],
            $totals['failed']
        );
    }
}
