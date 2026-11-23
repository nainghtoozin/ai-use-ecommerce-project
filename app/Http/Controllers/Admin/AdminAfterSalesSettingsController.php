<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\AfterSalesSettingsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminAfterSalesSettingsController extends Controller
{
    public function __construct(
        private readonly AfterSalesSettingsService $settingsService,
    ) {}

    public function editCancellation()
    {
        if (!auth()->user()->can('cancellations.settings')) {
            abort(403, 'Unauthorized');
        }

        $tenant = $this->tenant();

        return Inertia::render('Admin/Settings/CancellationPolicy', [
            'settings' => $this->settingsService->cancellationSettings($tenant->id),
        ]);
    }

    public function updateCancellation(Request $request)
    {
        if (!auth()->user()->can('cancellations.settings')) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'customer_cancel_enabled' => 'boolean',
            'merchant_cancel_enabled' => 'boolean',
            'status_rules' => 'array',
            'instant_cancel_window_hours' => 'nullable|integer|min:0|max:8760',
            'max_cancellations' => 'nullable|integer|min:0|max:1000',
            'limit_period_days' => 'required|integer|min:1|max:365',
            'limit_exceeded_action' => 'required|in:block,require_approval',
            'allow_request_after_limit' => 'boolean',
            'require_reason' => 'boolean',
            'require_note' => 'boolean',
            'refund_shipping_fee' => 'boolean',
            'reasons' => 'array',
            'reasons.*.label' => 'required|string|max:255',
        ]);

        $this->settingsService->updateCancellationSettings($this->tenant(), $request->all());

        return back()->with('success', 'Cancellation policy saved.');
    }

    public function editReturn()
    {
        if (!auth()->user()->can('returns.settings')) {
            abort(403, 'Unauthorized');
        }

        return Inertia::render('Admin/Settings/ReturnPolicy', [
            'settings' => $this->settingsService->returnSettings($this->tenant()->id),
        ]);
    }

    public function updateReturn(Request $request)
    {
        if (!auth()->user()->can('returns.settings')) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'returns_enabled' => 'boolean',
            'return_window_days' => 'required|integer|min:0|max:365',
            'allow_full_return' => 'boolean',
            'allow_partial_return' => 'boolean',
            'require_reason' => 'boolean',
            'require_note' => 'boolean',
            'require_evidence' => 'boolean',
            'allow_refund' => 'boolean',
            'allow_exchange' => 'boolean',
            'allow_replacement' => 'boolean',
            'return_shipping_responsibility' => 'required|in:customer,merchant',
            'refund_shipping_fee' => 'boolean',
            'reasons' => 'array',
            'reasons.*.label' => 'required|string|max:255',
        ]);

        $this->settingsService->updateReturnSettings($this->tenant(), $request->all());

        return back()->with('success', 'Return policy saved.');
    }

    public function editWorkflow()
    {
        if (!auth()->user()->can('orders.update-status')) {
            abort(403, 'Unauthorized');
        }

        $tenant = $this->tenant();

        return Inertia::render('Admin/Settings/OrderWorkflow', [
            'workflow_mode' => $tenant->settings['workflow_mode'] ?? 'standard',
        ]);
    }

    public function updateWorkflow(Request $request)
    {
        if (!auth()->user()->can('orders.update-status')) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'workflow_mode' => 'required|in:standard,simplified',
        ]);

        $tenant = $this->tenant();
        $settings = $tenant->settings ?: [];
        $settings['workflow_mode'] = $request->workflow_mode;
        $tenant->settings = $settings;
        $tenant->save();

        return back()->with('success', 'Order workflow saved.');
    }

    private function tenant(): Tenant
    {
        $tenant = Tenant::getCurrent();

        if (!$tenant) {
            abort(404, 'Store context is required.');
        }

        return $tenant;
    }
}
