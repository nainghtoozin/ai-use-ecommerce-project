<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CityStoreRequest;
use App\Http\Requests\CityUpdateRequest;
use App\Models\City;
use App\Services\LocationService;
use App\Services\MyanmarLocationImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminCityController extends Controller
{
    public function __construct(
        private LocationService $locationService
    ) {}

    public function index(): \Inertia\Response
    {
        if (!auth()->user()->can('cities.view')) {
            abort(403, 'Unauthorized');
        }

        $cities = City::withCount('townships')->latest()->paginate(15);
        return Inertia::render('Admin/Cities/Index', [
            'cities' => $cities,
        ]);
    }

    public function create(): \Inertia\Response
    {
        if (!auth()->user()->can('cities.create')) {
            abort(403, 'Unauthorized');
        }

        return Inertia::render('Admin/Cities/Create');
    }

    public function store(CityStoreRequest $request): RedirectResponse
    {
        if (!auth()->user()->can('cities.create')) {
            abort(403, 'Unauthorized');
        }

        $this->locationService->createCity($request->validated());
        return admin_redirect('admin.cities.index')
            ->with('success', 'City created successfully.');
    }

    public function edit(City $city): \Inertia\Response
    {
        if (!auth()->user()->can('cities.update')) {
            abort(403, 'Unauthorized');
        }

        return Inertia::render('Admin/Cities/Edit', [
            'city' => $city,
        ]);
    }

    public function update(CityUpdateRequest $request, City $city): RedirectResponse
    {
        if (!auth()->user()->can('cities.update')) {
            abort(403, 'Unauthorized');
        }

        $this->locationService->updateCity($city, $request->validated());
        return admin_redirect('admin.cities.index')
            ->with('success', 'City updated successfully.');
    }

    public function destroy(City $city): RedirectResponse
    {
        if (!auth()->user()->can('cities.delete')) {
            abort(403, 'Unauthorized');
        }

        $this->locationService->deleteCity($city);
        return admin_redirect('admin.cities.index')
            ->with('success', 'City deleted successfully.');
    }

    public function toggle(City $city): JsonResponse
    {
        if (!auth()->user()->can('cities.update')) {
            abort(403, 'Unauthorized');
        }

        $city = $this->locationService->toggleCityActive($city);
        return response()->json([
            'success' => true,
            'is_active' => $city->is_active,
        ]);
    }

    public function importMyanmar(MyanmarLocationImportService $service): RedirectResponse
    {
        if (!auth()->user()->can('cities.create')) {
            abort(403, 'Unauthorized');
        }

        $stats = $service->import();

        $message = sprintf(
            'Myanmar locations imported: %d cities created, %d skipped, %d townships created, %d skipped.',
            $stats['cities_created'],
            $stats['cities_skipped'],
            $stats['townships_created'],
            $stats['townships_skipped']
        );

        return admin_redirect('admin.cities.index')
            ->with('success', $message);
    }
}
