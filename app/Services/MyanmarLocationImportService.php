<?php

namespace App\Services;

use App\Models\City;
use App\Models\Tenant;
use App\Models\Township;
use Illuminate\Support\Facades\DB;

class MyanmarLocationImportService
{
    public function import(?Tenant $tenant = null): array
    {
        $tenant ??= Tenant::getCurrent();

        if (!$tenant) {
            throw new \RuntimeException('Myanmar location import requires a current tenant.');
        }

        $locations = collect(require database_path('data/myanmar_locations.php'));

        $stats = [
            'cities_created' => 0,
            'cities_skipped' => 0,
            'townships_created' => 0,
            'townships_skipped' => 0,
        ];

        DB::transaction(function () use ($locations, $tenant, &$stats) {
            foreach ($locations as $item) {
                $city = City::withoutTenantScope()
                    ->where('tenant_id', $tenant->id)
                    ->where('name', $item['name'])
                    ->first();

                if (!$city) {
                    $city = new City([
                        'name' => $item['name'],
                        'is_active' => true,
                    ]);
                    $city->tenant_id = $tenant->id;
                    $city->save();
                    $stats['cities_created']++;
                } else {
                    $stats['cities_skipped']++;
                }

                foreach ($item['townships'] as $townshipData) {
                    $name = is_array($townshipData) ? $townshipData['name'] : $townshipData;
                    $postalCode = is_array($townshipData) ? ($townshipData['postal_code'] ?? null) : null;

                    $exists = Township::withoutTenantScope()
                        ->where('tenant_id', $tenant->id)
                        ->where('city_id', $city->id)
                        ->where('name', $name)
                        ->exists();

                    if (!$exists) {
                        $township = new Township([
                            'city_id' => $city->id,
                            'name' => $name,
                            'postal_code' => $postalCode,
                            'delivery_fee' => $item['delivery_fee'] ?? 0,
                            'is_active' => true,
                        ]);
                        $township->tenant_id = $tenant->id;
                        $township->save();
                        $stats['townships_created']++;
                    } else {
                        if ($postalCode) {
                            Township::withoutTenantScope()
                                ->where('tenant_id', $tenant->id)
                                ->where('city_id', $city->id)
                                ->where('name', $name)
                                ->whereNull('postal_code')
                                ->update(['postal_code' => $postalCode]);
                        }
                        $stats['townships_skipped']++;
                    }
                }
            }
        });

        City::forgetLocationCacheFor($tenant->id);

        return $stats;
    }
}
