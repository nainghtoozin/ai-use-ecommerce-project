<?php

namespace App\Services;

use App\Models\City;
use App\Models\DeliveryPricing;
use App\Models\DeliveryService;
use Illuminate\Validation\Rule;

class DeliveryServiceService
{
    public function list(int $perPage = 15)
    {
        return DeliveryService::forCurrentTenant()
            ->ordered()
            ->paginate($perPage);
    }

    public function search(string $query, int $perPage = 15)
    {
        return DeliveryService::forCurrentTenant()
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('code', 'like', "%{$query}%");
            })
            ->ordered()
            ->paginate($perPage);
    }

    public function create(array $data): DeliveryService
    {
        return DeliveryService::create($this->prepareData($data));
    }

    public function update(DeliveryService $deliveryService, array $data): DeliveryService
    {
        $data = $this->prepareData($data, $deliveryService);

        $deliveryService->update($data);

        return $deliveryService->fresh();
    }

    public function delete(DeliveryService $deliveryService): ?bool
    {
        $inUse = $deliveryService->orders()->exists();
        if ($inUse) {
            $deliveryService->update(['is_active' => false]);
            return null;
        }

        $deliveryService->pricing()->delete();

        return $deliveryService->delete();
    }

    public function toggleActive(DeliveryService $deliveryService): DeliveryService
    {
        $deliveryService->update(['is_active' => !$deliveryService->is_active]);
        return $deliveryService->fresh();
    }

    public function rules(?DeliveryService $deliveryService = null): array
    {
        $tenantId = tenant()?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('delivery_services', 'code')
                    ->where('tenant_id', $tenantId)
                    ->ignore($deliveryService?->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'base_fee' => ['required', 'integer', 'min:0'],
            'fee_per_kg' => ['nullable', 'integer', 'min:0'],
            'min_days' => ['required', 'integer', 'min:0'],
            'max_days' => ['required', 'integer', 'min:0', 'gte:min_days'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function pricingRules(): array
    {
        return [
            'city_id' => ['required', 'exists:cities,id'],
            'fee' => ['nullable', 'integer', 'min:0'],
            'min_days' => ['nullable', 'integer', 'min:0'],
            'max_days' => ['nullable', 'integer', 'min:0', 'gte:min_days'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function addCityPricing(DeliveryService $deliveryService, array $data): DeliveryPricing
    {
        $existing = DeliveryPricing::where('delivery_service_id', $deliveryService->id)
            ->where('city_id', $data['city_id'])
            ->first();

        if ($existing) {
            $existing->update([
                'fee' => $data['fee'] ?? null,
                'min_days' => $data['min_days'] ?? null,
                'max_days' => $data['max_days'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);
            return $existing;
        }

        return $deliveryService->pricing()->create([
            'city_id' => $data['city_id'],
            'fee' => $data['fee'] ?? null,
            'min_days' => $data['min_days'] ?? null,
            'max_days' => $data['max_days'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function removeCityPricing(DeliveryPricing $pricing): bool
    {
        return $pricing->delete();
    }

    public function getCitiesWithoutPricing(DeliveryService $deliveryService): array
    {
        $usedCityIds = $deliveryService->pricing()->pluck('city_id')->toArray();

        return City::where('is_active', true)
            ->whereNotIn('id', $usedCityIds)
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    private function prepareData(array $data, ?DeliveryService $deliveryService = null): array
    {
        if (!isset($data['is_active'])) {
            $data['is_active'] = true;
        }

        if (!isset($data['sort_order'])) {
            $data['sort_order'] = 0;
        }

        return $data;
    }
}
