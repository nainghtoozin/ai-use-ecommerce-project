<?php

namespace App\Services;

use App\Models\DeliveryPricing;
use App\Models\DeliveryService;
use App\Models\Township;
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
            'township_id' => ['required', Rule::exists('townships', 'id')->where('tenant_id', tenant()?->id)],
            'min_days' => ['nullable', 'integer', 'min:0'],
            'max_days' => ['nullable', 'integer', 'min:0', 'gte:min_days'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function addTownshipPricing(DeliveryService $deliveryService, array $data): DeliveryPricing
    {
        $township = Township::whereKey($data['township_id'])->firstOrFail();

        if ((int) $township->city->tenant_id !== (int) $township->tenant_id) {
            throw new \InvalidArgumentException('The selected township does not belong to its city.');
        }

        $existing = DeliveryPricing::where('delivery_service_id', $deliveryService->id)
            ->where('township_id', $township->id)
            ->first();

        if ($existing) {
            $existing->update([
                'min_days' => $data['min_days'] ?? null,
                'max_days' => $data['max_days'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);
            return $existing;
        }

        return $deliveryService->pricing()->create([
            'township_id' => $township->id,
            'min_days' => $data['min_days'] ?? null,
            'max_days' => $data['max_days'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function removeTownshipPricing(DeliveryPricing $pricing): bool
    {
        return $pricing->delete();
    }

    public function bulkSetPricingActive(array $ids, bool $active): array
    {
        $ids = $this->cleanIds($ids);
        $tenantId = tenant()?->id;
        $affected = $this->scopedPricingQuery($ids, $tenantId)->update(['is_active' => $active]);
        return ['affected' => $affected, 'invalid' => count($ids) - $affected];
    }

    public function bulkSetPricingDays(array $ids, ?int $minDays, ?int $maxDays): array
    {
        $ids = $this->cleanIds($ids);
        $tenantId = tenant()?->id;
        $affected = $this->scopedPricingQuery($ids, $tenantId)->update([
            'min_days' => $minDays,
            'max_days' => $maxDays,
        ]);
        return ['affected' => $affected, 'invalid' => count($ids) - $affected];
    }

    private function scopedPricingQuery(array $ids, $tenantId)
    {
        return DeliveryPricing::whereIn('id', $ids)
            ->whereHas('deliveryService', function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId);
            });
    }

    private function cleanIds(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', $ids),
            fn ($id) => $id > 0
        )));
    }

    public function getTownshipsWithoutPricing(DeliveryService $deliveryService): array
    {
        $usedTownshipIds = $deliveryService->pricing()
            ->whereNotNull('township_id')
            ->pluck('township_id')
            ->toArray();

        return Township::active()
            ->with('city:id,name')
            ->whereNotIn('id', $usedTownshipIds)
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
