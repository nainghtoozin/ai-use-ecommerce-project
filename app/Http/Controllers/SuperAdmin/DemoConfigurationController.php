<?php

namespace App\Http\Controllers\SuperAdmin;

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

    public function index(Request $request): \Inertia\Response
    {
        $this->authorizeSuperAdmin();

        $search = $request->get('search');

        $tenants = Tenant::query()
            ->when($search, fn ($q, $s) => $q->where(function ($query) use ($s) {
                $query->where('name', 'like', "%{$s}%")
                    ->orWhere('slug', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%");
            }))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $statuses = $this->importService->statusForTenants(
            $tenants->getCollection()->pluck('id')->all()
        );

        $tenants->getCollection()->transform(function (Tenant $tenant) use ($statuses) {
            $tenant->setAttribute('demo_status', $statuses[$tenant->id] ?? null);

            return $tenant;
        });

        return Inertia::render('SuperAdmin/DemoConfiguration/Index', [
            'tenants' => $tenants,
            'eligibleCount' => Tenant::count(),
            'categories' => TenantDemoConfigurationImportService::CATEGORY_LABELS,
            'filters' => ['search' => $search],
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $this->authorizeSuperAdmin();

        $validated = $request->validate([
            'tenant_ids' => ['required', 'array', 'min:1'],
            'tenant_ids.*' => ['integer', Rule::exists('tenants', 'id')],
            'categories' => ['nullable', 'array'],
            'categories.*' => [Rule::in(TenantDemoConfigurationImportService::ALL_CATEGORIES)],
        ]);

        $tenants = Tenant::whereIn('id', $validated['tenant_ids'])
            ->orderBy('name')
            ->get();

        $results = $this->importService->importForTenants(
            $tenants->all(),
            $validated['categories'] ?? TenantDemoConfigurationImportService::ALL_CATEGORIES
        );

        $totals = ['created' => 0, 'skipped' => 0, 'failed' => 0];
        $failedTenants = 0;

        foreach ($results as $result) {
            $totals['created'] += $result['totals']['created'];
            $totals['skipped'] += $result['totals']['skipped'];
            $totals['failed'] += $result['totals']['failed'];

            if (!empty($result['error'])) {
                $failedTenants++;
            }
        }

        ActivityLogger::log(
            'Demo configuration bulk import executed',
            'demo_configuration_bulk_imported',
            null,
            [
                'tenant_count' => $tenants->count(),
                'categories' => $validated['categories'] ?? TenantDemoConfigurationImportService::ALL_CATEGORIES,
                'totals' => $totals,
                'tenant_ids' => $tenants->pluck('id')->all(),
            ]
        );

        return redirect()->route('superadmin.demo-configuration.index')
            ->with('success', sprintf(
                'Demo configuration bulk import finished for %d tenant(s): %d created, %d skipped, %d failed.',
                $tenants->count(),
                $totals['created'],
                $totals['skipped'],
                $totals['failed']
            ))
            ->with('demo_bulk_summary', [
                'results' => $results,
                'totals' => $totals,
                'failed_tenants' => $failedTenants,
                'categories' => $validated['categories'] ?? TenantDemoConfigurationImportService::ALL_CATEGORIES,
            ]);
    }

    private function authorizeSuperAdmin(): void
    {
        if (!auth()->user()?->isSuperAdmin()) {
            abort(403, 'Unauthorized');
        }
    }
}
