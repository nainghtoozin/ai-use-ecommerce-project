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
                'delivery_fee' => $city->delivery_fee,
                'townships' => $city->townships->map(fn($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'postal_code' => $t->postal_code,
                ])->toArray(),
            ]);

        return response()->json(['cities' => $cities]);
    }

    public function getTownships(int $cityId): JsonResponse
    {
        $townships = Township::where('city_id', $cityId)
            ->active()
            ->orderBy('name')
            ->get()
            ->map(fn($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'postal_code' => $t->postal_code,
            ]);

        return response()->json(['townships' => $townships]);
    }

    public function getDeliveryFee(int $cityId): JsonResponse
    {
        $city = City::find($cityId);
        
        if (!$city) {
            return response()->json(['error' => 'City not found'], 404);
        }

        return response()->json([
            'delivery_fee' => $city->delivery_fee,
            'city_name' => $city->name,
        ]);
    }
}
