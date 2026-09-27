<?php

namespace App\Services;

use App\Models\City;
use App\Models\Tenant;
use App\Models\Township;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class LocationService
{
    public function getActiveCities(): Collection
    {
        return City::active()
            ->with(['townships' => fn($q) => $q->active()])
            ->orderBy('name')
            ->get();
    }

    public function getTownshipsByCity(int $cityId): Collection
    {
        return Township::where('city_id', $cityId)
            ->active()
            ->orderBy('name')
            ->get();
    }

    public function getCityById(int $id): ?City
    {
        return City::with('townships')->find($id);
    }

    public function createCity(array $data): City
    {
        $city = City::create($data);
        City::forgetLocationCache();
        return $city;
    }

    public function updateCity(City $city, array $data): City
    {
        $city->update($data);
        City::forgetLocationCache();
        return $city->fresh();
    }

    public function deleteCity(City $city): bool
    {
        $result = $city->delete();
        City::forgetLocationCache();
        return $result;
    }

    public function toggleCityActive(City $city): City
    {
        $city->is_active = !$city->is_active;
        $city->save();
        City::forgetLocationCache();
        return $city;
    }

    public function createTownship(array $data): Township
    {
        $this->assertCityBelongsToCurrentTenant((int) ($data['city_id'] ?? 0));
        $township = Township::create($data);
        City::forgetLocationCache();
        return $township;
    }

    public function updateTownship(Township $township, array $data): Township
    {
        if (isset($data['city_id'])) {
            $this->assertCityBelongsToCurrentTenant((int) $data['city_id']);
        }
        $township->update($data);
        City::forgetLocationCache();
        return $township->fresh();
    }

    public function deleteTownship(Township $township): bool
    {
        $result = $township->delete();
        City::forgetLocationCache();
        return $result;
    }

    public function toggleTownshipActive(Township $township): Township
    {
        $township->is_active = !$township->is_active;
        $township->save();
        City::forgetLocationCache();
        return $township;
    }

    private function assertCityBelongsToCurrentTenant(int $cityId): void
    {
        if (!City::whereKey($cityId)->exists()) {
            throw new \InvalidArgumentException('The selected city is invalid for the current tenant.');
        }
    }

    public function bulkSetCityActive(array $ids, bool $active): array
    {
        $ids = $this->cleanIds($ids);
        $affected = City::whereIn('id', $ids)->update(['is_active' => $active]);
        City::forgetLocationCache();
        return ['affected' => $affected, 'invalid' => count($ids) - $affected];
    }

    public function bulkSetTownshipActive(array $ids, bool $active): array
    {
        $ids = $this->cleanIds($ids);
        $affected = Township::whereIn('id', $ids)->update(['is_active' => $active]);
        City::forgetLocationCache();
        return ['affected' => $affected, 'invalid' => count($ids) - $affected];
    }

    public function bulkDeleteTownships(array $ids): array
    {
        $ids = $this->cleanIds($ids);
        $affected = Township::whereIn('id', $ids)->delete();
        City::forgetLocationCache();
        return ['affected' => $affected, 'invalid' => count($ids) - $affected];
    }

    public function setTownshipFees(array $ids, float $fee): array
    {
        $ids = $this->cleanIds($ids);
        $affected = Township::whereIn('id', $ids)->update(['delivery_fee' => $fee]);
        City::forgetLocationCache();
        return ['affected' => $affected, 'invalid' => count($ids) - $affected];
    }

    private function cleanIds(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', $ids),
            fn ($id) => $id > 0
        )));
    }

    public function resolveTenantLocation(?int $cityId, ?int $townshipId, ?Tenant $tenant = null): array
    {
        $tenant ??= Tenant::getCurrent();

        if ($tenant && Tenant::getCurrent()?->id !== $tenant->id) {
            Tenant::setCurrent($tenant);
        }

        $city = null;
        if ($cityId) {
            $city = City::active()->find($cityId);
            if (!$city) {
                throw ValidationException::withMessages([
                    'city_id' => 'The selected city is invalid or unavailable.',
                ]);
            }
        }

        $township = null;
        if ($townshipId) {
            $township = Township::active()->find($townshipId);
            if (!$township) {
                throw ValidationException::withMessages([
                    'township_id' => 'The selected township is invalid or unavailable.',
                ]);
            }

            if ($city && (int) $township->city_id !== (int) $city->id) {
                throw ValidationException::withMessages([
                    'township_id' => 'The selected township is not valid for the chosen city.',
                ]);
            }

            $city ??= $township->city && $township->city->is_active ? $township->city : null;
            if (!$city) {
                throw ValidationException::withMessages([
                    'city_id' => 'The selected city is invalid or unavailable.',
                ]);
            }
        }

        return ['city' => $city, 'township' => $township];
    }
}
