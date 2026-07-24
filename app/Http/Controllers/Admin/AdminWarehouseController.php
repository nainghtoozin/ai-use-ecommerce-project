<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWarehouseRequest;
use App\Http\Requests\UpdateWarehouseRequest;
use App\Models\Warehouse;
use App\Services\FeatureGate;
use App\Services\WarehouseService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminWarehouseController extends Controller
{
    public function __construct(
        private readonly WarehouseService $warehouseService,
    ) {}

    public function index()
    {
        if (!FeatureGate::enabled('warehouse_management')) {
            return redirect()->back()->with('feature_locked', [
                'feature' => FeatureGate::getLabelStatic('warehouse_management'),
                'required_plan' => FeatureGate::getUpgradeHintStatic('warehouse_management') ?? 'Starter',
            ]);
        }

        if (!auth()->user()->can('warehouses.view')) {
            abort(403, 'Unauthorized');
        }

        return Inertia::render('Admin/Warehouses/Index', [
            'warehouses' => $this->warehouseService->list(),
        ]);
    }

    public function create()
    {
        if (!FeatureGate::enabled('warehouse_management')) {
            return redirect()->back()->with('feature_locked', [
                'feature' => FeatureGate::getLabelStatic('warehouse_management'),
                'required_plan' => FeatureGate::getUpgradeHintStatic('warehouse_management') ?? 'Starter',
            ]);
        }

        if (!auth()->user()->can('warehouses.create')) {
            abort(403, 'Unauthorized');
        }

        return Inertia::render('Admin/Warehouses/Create');
    }

    public function store(StoreWarehouseRequest $request)
    {
        if (!FeatureGate::enabled('warehouse_management')) {
            return redirect()->back()->with('feature_locked', [
                'feature' => FeatureGate::getLabelStatic('warehouse_management'),
                'required_plan' => FeatureGate::getUpgradeHintStatic('warehouse_management') ?? 'Starter',
            ]);
        }

        if (!auth()->user()->can('warehouses.create')) {
            abort(403, 'Unauthorized');
        }

        $warehouse = $this->warehouseService->create($request->validated());

        return admin_redirect('admin.warehouses.index')
            ->with('success', 'Warehouse created successfully.');
    }

    public function edit(Warehouse $warehouse)
    {
        if (!FeatureGate::enabled('warehouse_management')) {
            return redirect()->back()->with('feature_locked', [
                'feature' => FeatureGate::getLabelStatic('warehouse_management'),
                'required_plan' => FeatureGate::getUpgradeHintStatic('warehouse_management') ?? 'Starter',
            ]);
        }

        if (!auth()->user()->can('warehouses.update')) {
            abort(403, 'Unauthorized');
        }

        return Inertia::render('Admin/Warehouses/Edit', [
            'warehouse' => $warehouse,
        ]);
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse)
    {
        if (!FeatureGate::enabled('warehouse_management')) {
            return redirect()->back()->with('feature_locked', [
                'feature' => FeatureGate::getLabelStatic('warehouse_management'),
                'required_plan' => FeatureGate::getUpgradeHintStatic('warehouse_management') ?? 'Starter',
            ]);
        }

        if (!auth()->user()->can('warehouses.update')) {
            abort(403, 'Unauthorized');
        }

        $this->warehouseService->update($warehouse, $request->validated());

        return admin_redirect('admin.warehouses.index')
            ->with('success', 'Warehouse updated successfully.');
    }

    public function destroy(Warehouse $warehouse)
    {
        if (!FeatureGate::enabled('warehouse_management')) {
            return redirect()->back()->with('feature_locked', [
                'feature' => FeatureGate::getLabelStatic('warehouse_management'),
                'required_plan' => FeatureGate::getUpgradeHintStatic('warehouse_management') ?? 'Starter',
            ]);
        }

        if (!auth()->user()->can('warehouses.delete')) {
            abort(403, 'Unauthorized');
        }

        try {
            $this->warehouseService->delete($warehouse);
            return admin_redirect('admin.warehouses.index')
                ->with('success', 'Warehouse deleted successfully.');
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function search(Request $request)
    {
        if (!FeatureGate::enabled('warehouse_management')) {
            return redirect()->back()->with('feature_locked', [
                'feature' => FeatureGate::getLabelStatic('warehouse_management'),
                'required_plan' => FeatureGate::getUpgradeHintStatic('warehouse_management') ?? 'Starter',
            ]);
        }

        if (!auth()->user()->can('warehouses.view')) {
            abort(403, 'Unauthorized');
        }

        $query = $request->input('query');

        return Inertia::render('Admin/Warehouses/Index', [
            'warehouses' => $this->warehouseService->search($query),
            'query' => $query,
        ]);
    }
}
