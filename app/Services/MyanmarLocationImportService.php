<?php

namespace App\Services;

use App\Models\City;
use App\Models\Township;
use Illuminate\Support\Facades\DB;

class MyanmarLocationImportService
{
    public function import(): array
    {
        $locations = collect(require database_path('data/myanmar_locations.php'));

        $stats = [
            'cities_created' => 0,
            'cities_skipped' => 0,
            'townships_created' => 0,
            'townships_skipped' => 0,
        ];

        DB::transaction(function () use ($locations, &$stats) {
            foreach ($locations as $item) {
                $city = City::where('name', $item['name'])->first();

                if (!$city) {
                    $city = City::create([
                        'name' => $item['name'],
                        'delivery_fee' => $item['delivery_fee'],
                        'is_active' => true,
                    ]);
                    $stats['cities_created']++;
                } else {
                    $stats['cities_skipped']++;
                }

                foreach ($item['townships'] as $townshipName) {
                    $exists = Township::where('city_id', $city->id)
                        ->where('name', $townshipName)
                        ->exists();

                    if (!$exists) {
                        Township::create([
                            'city_id' => $city->id,
                            'name' => $townshipName,
                            'is_active' => true,
                        ]);
                        $stats['townships_created']++;
                    } else {
                        $stats['townships_skipped']++;
                    }
                }
            }
        });

        return $stats;
    }
}
