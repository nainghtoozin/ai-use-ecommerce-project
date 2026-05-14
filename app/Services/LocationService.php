<?php

namespace App\Services;

use App\Models\City;
use App\Models\Township;
use Illuminate\Support\Collection;

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
        return City::create($data);
    }

    public function updateCity(City $city, array $data): City
    {
        $city->update($data);
        return $city->fresh();
    }

    public function deleteCity(City $city): bool
    {
        return $city->delete();
    }

    public function toggleCityActive(City $city): City
    {
        $city->is_active = !$city->is_active;
        $city->save();
        return $city;
    }

    public function createTownship(array $data): Township
    {
        return Township::create($data);
    }

    public function updateTownship(Township $township, array $data): Township
    {
        $township->update($data);
        return $township->fresh();
    }

    public function deleteTownship(Township $township): bool
    {
        return $township->delete();
    }

    public function toggleTownshipActive(Township $township): Township
    {
        $township->is_active = !$township->is_active;
        $township->save();
        return $township;
    }
}
