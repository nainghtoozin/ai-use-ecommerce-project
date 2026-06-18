<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TownshipStoreRequest;
use App\Http\Requests\TownshipUpdateRequest;
use App\Models\City;
use App\Models\Township;
use App\Services\LocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminTownshipController extends Controller
{
    public function __construct(
        private LocationService $locationService
    ) {}

    public function index(Request $request): \Inertia\Response
    {
        if (!auth()->user()->can('townships.view')) {
            abort(403, 'Unauthorized');
        }

        $query = Township::with('city');

        if ($request->has('city_id') && $request->city_id) {
            $query->where('city_id', $request->city_id);
        }

        $townships = $query->latest()->paginate(15);
        $cities = City::active()->orderBy('name')->get();

        return Inertia::render('Admin/Townships/Index', [
            'townships' => $townships,
            'cities' => $cities,
        ]);
    }

    public function create(): \Inertia\Response
    {
        if (!auth()->user()->can('townships.create')) {
            abort(403, 'Unauthorized');
        }

        $cities = City::active()->orderBy('name')->get();
        return Inertia::render('Admin/Townships/Create', [
            'cities' => $cities,
        ]);
    }

    public function store(TownshipStoreRequest $request): RedirectResponse
    {
        if (!auth()->user()->can('townships.create')) {
            abort(403, 'Unauthorized');
        }

        $this->locationService->createTownship($request->validated());

        return admin_redirect('admin.townships.index')
            ->with('success', 'Township created successfully.');
    }

    public function edit(Township $township): \Inertia\Response
    {
        if (!auth()->user()->can('townships.update')) {
            abort(403, 'Unauthorized');
        }
        $cities = City::active()->orderBy('name')->get();
        return Inertia::render('Admin/Townships/Edit', [
            'township' => $township,
            'cities' => $cities,
        ]);
    }

    public function update(TownshipUpdateRequest $request, Township $township): RedirectResponse
    {
        if (!auth()->user()->can('townships.update')) {
            abort(403, 'Unauthorized');
        }

        $this->locationService->updateTownship($township, $request->validated());

        return admin_redirect('admin.townships.index')
            ->with('success', 'Township updated successfully.');
    }

    public function destroy(Township $township): RedirectResponse
    {
        if (!auth()->user()->can('townships.delete')) {
            abort(403, 'Unauthorized');
        }

        $this->locationService->deleteTownship($township);

        return admin_redirect('admin.townships.index')
            ->with('success', 'Township deleted successfully.');
    }

    public function toggle(Township $township): RedirectResponse
    {
        if (!auth()->user()->can('townships.update')) {
            abort(403, 'Unauthorized');
        }

        $this->locationService->toggleTownshipActive($township);

        return back()->with('success', 'Status updated successfully.');
    }
}
