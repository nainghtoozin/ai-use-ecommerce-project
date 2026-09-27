<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Township;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    public function getCities(): JsonResponse
    {
        $cities = City::active()
            ->with(['townships' => fn($q) => $q->active()->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->map(fn($city) => [
                'id' => $city->id,
                'name' => $city->name,
                'townships' => $city->townships->map(fn($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'postal_code' => $t->postal_code,
                    'delivery_fee' => $t->delivery_fee,
                ])->toArray(),
            ]);

        return response()->json(['cities' => $cities]);
    }

    public function getTownships(int $cityId): JsonResponse
    {
        $city = City::active()->find($cityId);
        if (!$city) {
            return response()->json(['townships' => []]);
        }

        $townships = Township::where('city_id', $city->id)
            ->active()
            ->orderBy('name')
            ->get()
            ->map(fn($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'postal_code' => $t->postal_code,
                'delivery_fee' => $t->delivery_fee,
            ]);

        return response()->json(['townships' => $townships]);
    }

    public function getDeliveryFee(int $cityId): JsonResponse
    {
        $city = City::active()->find($cityId);

        if (!$city) {
            return response()->json(['error' => 'City not found'], 404);
        }

        return response()->json([
            'delivery_fee' => null,
            'city_name' => $city->name,
            'townships' => Township::where('city_id', $city->id)
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'delivery_fee']),
        ]);
    }

    public function getTownshipDeliveryFee(int $townshipId): JsonResponse
    {
        $township = Township::active()->find($townshipId);

        if (!$township) {
            return response()->json(['error' => 'Township not found'], 404);
        }

        return response()->json([
            'delivery_fee' => $township->delivery_fee,
            'township_name' => $township->name,
            'city_id' => $township->city_id,
        ]);
    }
}
