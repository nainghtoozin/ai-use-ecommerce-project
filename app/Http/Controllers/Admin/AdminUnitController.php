<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Services\UnitService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminUnitController extends Controller
{
    public function __construct(
        private readonly UnitService $unitService,
    ) {}

    public function index()
    {
        if (!auth()->user()->can('units.view')) {
            abort(403, 'Unauthorized');
        }

        $units = $this->unitService->list();

        return Inertia::render('Admin/Units/Index', [
            'units' => $units,
        ]);
    }

    public function create()
    {
        if (!auth()->user()->can('units.create')) {
            abort(403, 'Unauthorized');
        }

        return Inertia::render('Admin/Units/Create');
    }

    public function store(Request $request)
    {
        if (!auth()->user()->can('units.create')) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate($this->unitService->rules());

        $this->unitService->create($validated);

        return admin_redirect('admin.units.index')
            ->with('success', 'Unit created successfully!');
    }

    public function edit(Unit $unit)
    {
        if (!auth()->user()->can('units.update')) {
            abort(403, 'Unauthorized');
        }

        return Inertia::render('Admin/Units/Edit', [
            'unit' => $unit,
        ]);
    }

    public function update(Request $request, Unit $unit)
    {
        if (!auth()->user()->can('units.update')) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate($this->unitService->rules($unit));

        $this->unitService->update($unit, $validated);

        return admin_redirect('admin.units.index')
            ->with('success', 'Unit updated successfully!');
    }

    public function destroy(Unit $unit)
    {
        if (!auth()->user()->can('units.delete')) {
            abort(403, 'Unauthorized');
        }

        $this->unitService->delete($unit);

        return admin_redirect('admin.units.index')
            ->with('success', 'Unit deleted successfully!');
    }

    public function search(Request $request)
    {
        if (!auth()->user()->can('units.view')) {
            abort(403, 'Unauthorized');
        }

        $query = $request->input('query');
        $units = $this->unitService->search($query);
        $units->appends(['query' => $query]);

        return Inertia::render('Admin/Units/Index', [
            'units' => $units,
            'query' => $query,
        ]);
    }
}
