<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Township;
use Illuminate\Database\Seeder;

class CityTownshipSeeder extends Seeder
{
    public function run(): void
    {
        $locations = require database_path('data/myanmar_locations.php');

        foreach ($locations as $cityData) {
            $city = City::withoutTenantScope()->firstOrCreate(
                ['tenant_id' => null, 'name' => $cityData['name']],
                [
                    'delivery_fee' => $cityData['delivery_fee'] ?? 0,
                    'is_active' => true,
                ]
            );

            foreach ($cityData['townships'] as $townshipData) {
                Township::withoutTenantScope()->firstOrCreate(
                    ['city_id' => $city->id, 'name' => $townshipData['name']],
                    [
                        'postal_code' => $townshipData['postal_code'] ?? null,
                        'is_active' => true,
                    ]
                );
            }
        }

        $this->command->info('Global cities and townships seeded successfully.');
    }
}
