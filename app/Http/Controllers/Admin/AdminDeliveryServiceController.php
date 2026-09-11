<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeliveryServiceRequest;
use App\Http\Requests\UpdateDeliveryServiceRequest;
use App\Models\City;
use App\Models\DeliveryPricing;
use App\Services\ActivityLogger;
use App\Services\DeliveryServiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class AdminDeliveryServiceController extends Controller
{
    public function __construct(
        private DeliveryServiceService $deliveryServiceService
    ) {}

    public function index(): \Inertia\Response
    {
        if (!auth()->user()->can('delivery-services.view')) {
            abort(403, 'Unauthorized');
        }

        $deliveryServices = $this->deliveryServiceService->list(10);
        $cities = City::where('is_active', true)->orderBy('name')->get();

        return Inertia::render('Admin/DeliveryServices/Index', [
            'deliveryServices' => $deliveryServices,
            'cities' => $cities,
        ]);
    }

    public function create(): \Inertia\Response
    {
        if (!auth()->user()->can('delivery-services.create')) {
            abort(403, 'Unauthorized');
        }

        $cities = City::where('is_active', true)->orderBy('name')->get();

        return Inertia::render('Admin/DeliveryServices/Create', [
            'cities' => $cities,
        ]);
    }

    public function store(StoreDeliveryServiceRequest $request): RedirectResponse
    {
        if (!auth()->user()->can('delivery-services.create')) {
            abort(403, 'Unauthorized');
        }

        $deliveryService = $this->deliveryServiceService->create($request->validated());

        ActivityLogger::log("Delivery service '{$deliveryService->name}' created", 'delivery_service_created', $deliveryService);

        return admin_redirect('admin.delivery-services.index')
            ->with('success', 'Delivery service created successfully.');
    }

    public function edit(\App\Models\DeliveryService $deliveryService): \Inertia\Response
    {
        if (!auth()->user()->can('delivery-services.update')) {
            abort(403, 'Unauthorized');
        }

        $deliveryService->load('pricing.city');
        $cities = City::where('is_active', true)->orderBy('name')->get();
        $citiesWithoutPricing = $this->deliveryServiceService->getCitiesWithoutPricing($deliveryService);

        return Inertia::render('Admin/DeliveryServices/Edit', [
            'deliveryService' => $deliveryService,
            'cities' => $cities,
            'citiesWithoutPricing' => $citiesWithoutPricing,
        ]);
    }

    public function update(UpdateDeliveryServiceRequest $request, \App\Models\DeliveryService $deliveryService): RedirectResponse
    {
        if (!auth()->user()->can('delivery-services.update')) {
            abort(403, 'Unauthorized');
        }

        $this->deliveryServiceService->update($deliveryService, $request->validated());

        ActivityLogger::log("Delivery service '{$deliveryService->name}' updated", 'delivery_service_updated', $deliveryService);

        return admin_redirect('admin.delivery-services.index')
            ->with('success', 'Delivery service updated successfully.');
    }

    public function destroy(\App\Models\DeliveryService $deliveryService): RedirectResponse
    {
        if (!auth()->user()->can('delivery-services.delete')) {
            abort(403, 'Unauthorized');
        }

        $result = $this->deliveryServiceService->delete($deliveryService);

        if ($result === null) {
            return admin_redirect('admin.delivery-services.index')
                ->with('warning', 'Delivery service is in use. It has been deactivated instead of deleted.');
        }

        ActivityLogger::log("Delivery service '{$deliveryService->name}' deleted", 'delivery_service_deleted', $deliveryService);

        return admin_redirect('admin.delivery-services.index')
            ->with('success', 'Delivery service deleted successfully.');
    }

    public function toggle(\App\Models\DeliveryService $deliveryService): JsonResponse
    {
        if (!auth()->user()->can('delivery-services.update')) {
            abort(403, 'Unauthorized');
        }

        $deliveryService = $this->deliveryServiceService->toggleActive($deliveryService);

        return response()->json([
            'success' => true,
            'is_active' => $deliveryService->is_active,
            'message' => $deliveryService->is_active ? 'Delivery service activated.' : 'Delivery service deactivated.',
        ]);
    }

    public function addCityPricing(\Illuminate\Http\Request $request, \App\Models\DeliveryService $deliveryService): RedirectResponse
    {
        if (!auth()->user()->can('delivery-services.update')) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate($this->deliveryServiceService->pricingRules());

        $this->deliveryServiceService->addCityPricing($deliveryService, $validated);

        return back()->with('success', 'City pricing added successfully.');
    }

    public function removeCityPricing(DeliveryPricing $pricing): RedirectResponse
    {
        if (!auth()->user()->can('delivery-services.update')) {
            abort(403, 'Unauthorized');
        }

        $tenantId = tenantId();
        if ($tenantId && $pricing->deliveryService->tenant_id != $tenantId) {
            abort(403, 'Unauthorized');
        }

        $this->deliveryServiceService->removeCityPricing($pricing);

        return back()->with('success', 'City pricing removed successfully.');
    }
}
