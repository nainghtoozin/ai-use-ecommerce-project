<?php

namespace App\Services;

use App\Models\DeliveryPricing;
use App\Models\DeliveryService;
use App\Models\Township;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DeliveryFeeService
{
    public function getAvailableServices(?Township $township = null): Collection
    {
        $query = DeliveryService::forCurrentTenant()
            ->active()
            ->ordered();

        if ($township !== null) {
            $query->whereHas('pricing', fn ($q) => $q
                ->where('township_id', $township->id)
                ->where('is_active', true));
        }

        if (!$query->exists()) {
            return collect();
        }

        return $query->get();
    }

    public function getServicesWithPricing(?Township $township = null): Collection
    {
        $services = $this->getAvailableServices($township);

        if ($services->isEmpty()) {
            return collect();
        }

        if ($township === null) {
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
        $pricingByService = DeliveryPricing::where('township_id', $township->id)
            ->whereIn('delivery_service_id', $serviceIds)
            ->where('is_active', true)
            ->get()
            ->keyBy('delivery_service_id');

        return $services->map(function ($service) use ($pricingByService) {
            $pricing = $pricingByService->get($service->id);

            if ($pricing && $pricing->min_days !== null) {
                $minDays = $pricing->min_days;
                $maxDays = $pricing->max_days ?? $pricing->min_days;
            } else {
                $minDays = $service->min_days;
                $maxDays = $service->max_days;
            }

            return [
                'service' => $service,
                'fee' => $service->base_fee,
                'eta_min' => $minDays,
                'eta_max' => $maxDays,
                'eta_label' => $this->formatEtaLabel($minDays, $maxDays),
            ];
        });
    }

    public function calculateFee(DeliveryService $service, ?float $weight = null): int
    {
        $baseFee = $service->base_fee;

        if ($weight !== null && $service->fee_per_kg !== null) {
            return $baseFee + (int) ($weight * $service->fee_per_kg);
        }

        return $baseFee;
    }

    public function getTownshipFee(?Township $township): int
    {
        return $township ? (int) ($township->delivery_fee ?? 0) : 0;
    }

    public function resolveAvailableService(?int $deliveryServiceId, ?Township $township): ?DeliveryService
    {
        if ($deliveryServiceId === null) {
            return null;
        }

        $service = DeliveryService::forCurrentTenant()->find($deliveryServiceId);

        if (!$service) {
            throw ValidationException::withMessages([
                'delivery_service_id' => 'The selected delivery service is invalid.',
            ]);
        }

        if (!$service->is_active) {
            throw ValidationException::withMessages([
                'delivery_service_id' => 'The selected delivery service is not available.',
            ]);
        }

        if ($township !== null) {
            if ((int) $service->tenant_id !== (int) $township->tenant_id) {
                throw ValidationException::withMessages([
                    'delivery_service_id' => 'The selected delivery service is not available for this location.',
                ]);
            }

            $mapped = DeliveryPricing::where('delivery_service_id', $service->id)
                ->where('township_id', $township->id)
                ->where('is_active', true)
                ->exists();

            if (!$mapped) {
                throw ValidationException::withMessages([
                    'delivery_service_id' => 'The selected delivery service is not available for this township.',
                ]);
            }
        }

        return $service;
    }

    public function resolveDeliveryBreakdown(?Township $township, ?int $deliveryServiceId = null): array
    {
        $townshipFee = $this->getTownshipFee($township);
        $serviceFee = null;

        if ($township !== null && $deliveryServiceId !== null) {
            $service = DeliveryService::forCurrentTenant()->find($deliveryServiceId);
            if ($service) {
                if ((int) $service->tenant_id !== (int) $township->tenant_id) {
                    throw new \InvalidArgumentException('Delivery service and township belong to different tenants.');
                }
                $serviceFee = $this->calculateFee($service);
            }
        }

        return [
            'township_fee' => $township ? $townshipFee : null,
            'service_fee' => $serviceFee,
            'service_fallback' => false,
            'fee' => $townshipFee + ($serviceFee ?? 0),
        ];
    }

    public function resolveDeliveryFee(?Township $township, ?int $deliveryServiceId = null): int
    {
        return $this->resolveDeliveryBreakdown($township, $deliveryServiceId)['fee'];
    }

    public function resolveDeliveryDays(?int $deliveryServiceId, ?Township $township = null): array
    {
        if ($deliveryServiceId !== null) {
            $service = DeliveryService::forCurrentTenant()->find($deliveryServiceId);
            if ($service) {
                if ($township !== null) {
                    return $service->getDaysForTownship($township);
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
