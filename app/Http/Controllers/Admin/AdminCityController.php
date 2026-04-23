<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CityStoreRequest;
use App\Http\Requests\CityUpdateRequest;
use App\Models\City;
use App\Services\LocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminCityController extends Controller
{
    public function __construct(
        private LocationService $locationService
    ) {}

    public function index(): View
    {
        $cities = City::withCount('townships')->latest()->paginate(15);
        return view('Admin.cities.index', compact('cities'));
    }

    public function create(): View
    {
        return view('Admin.cities.create');
    }

    public function store(CityStoreRequest $request): RedirectResponse
    {
        $this->locationService->createCity($request->validated());
        return redirect()->route('admin.cities.index')
            ->with('success', 'City created successfully.');
    }

    public function edit(City $city): View
    {
        return view('Admin.cities.edit', compact('city'));
    }

    public function update(CityUpdateRequest $request, City $city): RedirectResponse
    {
        $this->locationService->updateCity($city, $request->validated());
        return redirect()->route('admin.cities.index')
            ->with('success', 'City updated successfully.');
    }

    public function destroy(City $city): RedirectResponse
    {
        $this->locationService->deleteCity($city);
        return redirect()->route('admin.cities.index')
            ->with('success', 'City deleted successfully.');
    }

    public function toggle(City $city): JsonResponse
    {
        $city = $this->locationService->toggleCityActive($city);
        return response()->json([
            'success' => true,
            'is_active' => $city->is_active,
        ]);
    }
}
