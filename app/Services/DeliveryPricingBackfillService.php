<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DeliveryPricingBackfillService
{
    public function run(): array
    {
        $stats = [
            'source_rows' => 0,
            'township_rows_created' => 0,
            'township_rows_skipped' => 0,
            'repaired_city_rows' => 0,
            'skipped_tenant_mismatch' => 0,
        ];

        if (!Schema::hasTable('delivery_pricing')
            || !Schema::hasTable('delivery_services')
            || !Schema::hasTable('cities')
            || !Schema::hasTable('townships')
            || !Schema::hasColumn('delivery_pricing', 'township_id')
            || !Schema::hasColumn('delivery_pricing', 'city_id')
        ) {
            return $stats;
        }

        $stats['repaired_city_rows'] = $this->repairKnownMissingCityRow();

        $sources = DB::table('delivery_pricing as dp')
            ->join('delivery_services as ds', 'ds.id', '=', 'dp.delivery_service_id')
            ->join('cities as c', 'c.id', '=', 'dp.city_id')
            ->whereNotNull('dp.city_id')
            ->whereNull('dp.township_id')
            ->select(
                'dp.id as pricing_id',
                'dp.delivery_service_id',
                'dp.city_id',
                'dp.min_days',
                'dp.max_days',
                'dp.is_active',
                'ds.tenant_id as service_tenant_id',
                'c.tenant_id as city_tenant_id'
            )
            ->orderBy('dp.id')
            ->get();

        $stats['source_rows'] = $sources->count();

        foreach ($sources as $source) {
            if ((int) $source->service_tenant_id !== (int) $source->city_tenant_id) {
                $stats['skipped_tenant_mismatch']++;
                continue;
            }

            $townshipIds = DB::table('townships')
                ->where('city_id', $source->city_id)
                ->where('tenant_id', $source->service_tenant_id)
                ->orderBy('id')
                ->pluck('id');

            foreach ($townshipIds as $townshipId) {
                $exists = DB::table('delivery_pricing')
                    ->where('delivery_service_id', $source->delivery_service_id)
                    ->where('township_id', $townshipId)
                    ->exists();

                if ($exists) {
                    $stats['township_rows_skipped']++;
                    continue;
                }

                DB::table('delivery_pricing')->insert([
                    'delivery_service_id' => $source->delivery_service_id,
                    'city_id' => null,
                    'township_id' => $townshipId,
                    'fee' => null,
                    'min_days' => $source->min_days,
                    'max_days' => $source->max_days,
                    'is_active' => $source->is_active,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $stats['township_rows_created']++;
            }
        }

        return $stats;
    }

    private function repairKnownMissingCityRow(): int
    {
        $repaired = 0;

        $services = DB::table('delivery_services')
            ->where('code', 'economy')
            ->orderBy('id')
            ->get(['id', 'tenant_id', 'min_days', 'max_days']);

        foreach ($services as $service) {
            $cityId = DB::table('cities')
                ->where('tenant_id', $service->tenant_id)
                ->where('name', 'Yangon')
                ->orderBy('id')
                ->value('id');

            if (!$cityId) {
                continue;
            }

            $exists = DB::table('delivery_pricing')
                ->where('delivery_service_id', $service->id)
                ->where('city_id', $cityId)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('delivery_pricing')->insert([
                'delivery_service_id' => $service->id,
                'city_id' => $cityId,
                'township_id' => null,
                'fee' => 1000,
                'min_days' => $service->min_days ?? 4,
                'max_days' => $service->max_days ?? 7,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $repaired++;
        }

        return $repaired;
    }
}
