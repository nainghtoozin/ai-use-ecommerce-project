<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TownshipStoreRequest;
use App\Http\Requests\TownshipUpdateRequest;
use App\Models\City;
use App\Models\Setting;
use App\Models\Township;
use App\Services\DeliveryFeeService;
use App\Services\LocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminTownshipController extends Controller
{
    public function __construct(
        private LocationService $locationService,
        private DeliveryFeeService $deliveryFeeService
    ) {}

    public function index(Request $request): \Inertia\Response
    {
        if (!auth()->user()->can('townships.view')) {
            abort(403, 'Unauthorized');
        }

        $query = Township::with('city');

        $this->applyTownshipFilters($query, $request);

        $perPage = $this->resolvePerPage($request, $query);
        $townships = $query->latest()->paginate($perPage)->withQueryString();
        $cities = City::active()->orderBy('name')->get();

        return Inertia::render('Admin/Townships/Index', [
            'townships' => $townships,
            'cities' => $cities,
            'filters' => array_merge($request->only(['search', 'city_id', 'status']), ['per_page' => $request->per_page ?? '25']),
            'other_location' => $this->deliveryFeeService->getOtherLocationSettings(),
        ]);
    }

    public function updateOtherSettings(Request $request): RedirectResponse
    {
        if (!auth()->user()->can('townships.update')) {
            abort(403, 'Unauthorized');
        }

        if (!tenant()) {
            abort(422, 'A tenant context is required to update Other location settings.');
        }

        $validated = $request->validate([
            'delivery_fee' => 'required|numeric|min:0',
            'min_days' => 'required|integer|min:0',
            'max_days' => 'required|integer|min:0|gte:min_days',
        ]);

        Setting::set('other_location.delivery_fee', (string) $validated['delivery_fee']);
        Setting::set('other_location.min_days', (string) $validated['min_days']);
        Setting::set('other_location.max_days', (string) $validated['max_days']);

        return back()->with('success', 'Other location settings updated.');
    }

    public function matchingIds(Request $request): \Illuminate\Http\JsonResponse
    {
        if (!auth()->user()->can('townships.view')) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'city_id' => 'nullable|integer|min:1',
            'status' => 'nullable|in:active,inactive',
        ]);

        $query = Township::query();
        $this->applyTownshipFilters($query, $request);

        $total = (clone $query)->count();
        $ids = $query->orderBy('id')->limit(5000)->pluck('id');

        return response()->json([
            'ids' => $ids->values(),
            'total' => $total,
            'truncated' => $total > $ids->count(),
        ]);
    }

    private function applyTownshipFilters($query, Request $request): void
    {
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('city_id')) {
            $query->where('city_id', $request->city_id);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }
    }

    private function resolvePerPage(Request $request, $filteredQuery): int
    {
        $allowed = [25, 50, 100, 1000];

        if (($request->per_page ?? null) === 'all') {
            return max((int) (clone $filteredQuery)->toBase()->getCountForPagination(), 1);
        }

        $perPage = (int) ($request->per_page ?? 25);

        return in_array($perPage, $allowed, true) ? $perPage : 25;
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

        if (!tenant()) {
            abort(422, 'A tenant context is required to create a township.');
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

    public function bulkStatus(Request $request): RedirectResponse
    {
        if (!auth()->user()->can('townships.update')) {
            abort(403, 'Unauthorized');
        }

        if (!tenant()) {
            abort(422, 'A tenant context is required to update townships.');
        }

        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:5000',
            'ids.*' => 'integer|min:1',
            'is_active' => 'required|boolean',
        ]);

        $result = $this->locationService->bulkSetTownshipActive($validated['ids'], (bool) $validated['is_active']);
        $action = $validated['is_active'] ? 'activated' : 'deactivated';

        return back()->with('success', "{$result['affected']} of " . count($validated['ids']) . " townships {$action}.");
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        if (!auth()->user()->can('townships.delete')) {
            abort(403, 'Unauthorized');
        }

        if (!tenant()) {
            abort(422, 'A tenant context is required to delete townships.');
        }

        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:5000',
            'ids.*' => 'integer|min:1',
        ]);

        $result = $this->locationService->bulkDeleteTownships($validated['ids']);

        return back()->with('success', "Deleted {$result['affected']} of " . count($validated['ids']) . " townships.");
    }

    public function updateFees(Request $request): RedirectResponse
    {
        if (!auth()->user()->can('townships.update')) {
            abort(403, 'Unauthorized');
        }

        if (!tenant()) {
            abort(422, 'A tenant context is required to update delivery fees.');
        }

        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:5000',
            'ids.*' => 'integer|min:1',
            'delivery_fee' => 'required|numeric|min:0',
        ]);

        $result = $this->locationService->setTownshipFees($validated['ids'], (float) $validated['delivery_fee']);

        return back()->with('success', "Updated delivery fee for {$result['affected']} of " . count($validated['ids']) . " townships.");
    }
}
