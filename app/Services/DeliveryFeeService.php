<?php

namespace App\Services;

use App\Models\City;
use App\Models\DeliveryPricing;
use App\Models\DeliveryService;
use Illuminate\Support\Collection;

class DeliveryFeeService
{
    public function getAvailableServices(?City $city = null): Collection
    {
        $query = DeliveryService::forCurrentTenant()
            ->active()
            ->ordered();

        if (!$query->exists()) {
            return collect();
        }

        return $query->get();
    }

    public function getServicesWithPricing(?City $city = null): Collection
    {
        $services = $this->getAvailableServices($city);

        if ($services->isEmpty()) {
            return collect();
        }

        if ($city === null) {
            return $services->map(function ($service) {
                return [
                    'service' => $service,
                    'fee' => $service->base_fee,
                    'eta_min' => $service->min_days,
                    'eta_max' => $service->max_days,
                    'eta_label' => $service->eta_label,
                ];
            });
        }

        $serviceIds = $services->pluck('id')->toArray();
        $pricingByService = DeliveryPricing::where('city_id', $city->id)
            ->whereIn('delivery_service_id', $serviceIds)
            ->where('is_active', true)
            ->get()
            ->keyBy('delivery_service_id');

        return $services->map(function ($service) use ($city, $pricingByService) {
            $pricing = $pricingByService->get($service->id);

            if ($pricing && $pricing->fee !== null) {
                $fee = $pricing->fee;
            } else {
                $fee = $service->base_fee;
            }

            if ($pricing && $pricing->min_days !== null) {
                $minDays = $pricing->min_days;
                $maxDays = $pricing->max_days ?? $pricing->min_days;
            } else {
                $minDays = $service->min_days;
                $maxDays = $service->max_days;
            }

            return [
                'service' => $service,
                'fee' => $fee,
                'eta_min' => $minDays,
                'eta_max' => $maxDays,
                'eta_label' => $this->formatEtaLabel($minDays, $maxDays),
            ];
        });
    }

    public function getCheapestService(?City $city = null): ?array
    {
        $services = $this->getServicesWithPricing($city);

        if ($services->isEmpty()) {
            return null;
        }

        return $services->sortBy('fee')->first();
    }

    public function calculateFee(DeliveryService $service, ?City $city = null, ?float $weight = null): int
    {
        $baseFee = $city ? $service->getFeeForCity($city) : $service->base_fee;

        if ($weight !== null && $service->fee_per_kg !== null) {
            return $baseFee + (int) ($weight * $service->fee_per_kg);
        }

        return $baseFee;
    }

    public function getDeliveryFeeFallback(City $city): int
    {
        return (int) ($city->delivery_fee ?? 0);
    }

    public function resolveDeliveryFee(?City $city, ?int $deliveryServiceId = null): int
    {
        if ($city === null) {
            return 0;
        }

        if ($deliveryServiceId !== null) {
            $service = DeliveryService::forCurrentTenant()->find($deliveryServiceId);
            if ($service) {
                return $this->calculateFee($service, $city);
            }
        }

        $servicesWithPricing = $this->getServicesWithPricing($city);
        if ($servicesWithPricing->isNotEmpty()) {
            return $servicesWithPricing->first()['fee'];
        }

        return $this->getDeliveryFeeFallback($city);
    }

    public function resolveDeliveryDays(?int $deliveryServiceId, ?City $city = null): array
    {
        if ($deliveryServiceId !== null) {
            $service = DeliveryService::forCurrentTenant()->find($deliveryServiceId);
            if ($service) {
                if ($city !== null) {
                    return $service->getDaysForCity($city);
                }
                return [
                    'min' => $service->min_days,
                    'max' => $service->max_days,
                ];
            }
        }

        return ['min' => null, 'max' => null];
    }

    public function formatEtaLabel(int $min, int $max): string
    {
        if ($min === $max) {
            return $min . ' day' . ($min !== 1 ? 's' : '');
        }
        return $min . '-' . $max . ' days';
    }
}
