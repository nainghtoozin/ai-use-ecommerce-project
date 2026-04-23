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
use Illuminate\View\View;

class AdminTownshipController extends Controller
{
    public function __construct(
        private LocationService $locationService
    ) {}

    public function index(Request $request): View
    {
        $query = Township::with('city');

        if ($request->has('city_id') && $request->city_id) {
            $query->where('city_id', $request->city_id);
        }

        $townships = $query->latest()->paginate(15);
        $cities = City::active()->orderBy('name')->get();

        return view('Admin.townships.index', compact('townships', 'cities'));
    }

    public function create(): View
    {
        $cities = City::active()->orderBy('name')->get();
        return view('Admin.townships.create', compact('cities'));
    }

    public function store(TownshipStoreRequest $request): RedirectResponse
    {
        $this->locationService->createTownship($request->validated());

        return redirect()->route('admin.townships.index')
            ->with('success', 'Township created successfully.');
    }

    public function edit(Township $township): View
    {
        $cities = City::active()->orderBy('name')->get();
        return view('Admin.townships.edit', compact('township', 'cities'));
    }

    public function update(TownshipUpdateRequest $request, Township $township): RedirectResponse
    {
        $this->locationService->updateTownship($township, $request->validated());

        return redirect()->route('admin.townships.index')
            ->with('success', 'Township updated successfully.');
    }

    public function destroy(Township $township): RedirectResponse
    {
        $this->locationService->deleteTownship($township);

        return redirect()->route('admin.townships.index')
            ->with('success', 'Township deleted successfully.');
    }

    public function toggle(Township $township): RedirectResponse
    {
        $this->locationService->toggleTownshipActive($township);

        return back()->with('success', 'Status updated successfully.');
    }
}