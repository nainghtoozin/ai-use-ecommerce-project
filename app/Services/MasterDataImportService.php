<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MasterDataImportService
{
    /**
     * Import default categories into the given tenant.
     *
     * @return array{imported: int, skipped: int}
     */
    public function importCategories(int $tenantId): array
    {
        $defaults = config('master_data.categories', []);
        $imported = 0;
        $skipped = 0;

        DB::transaction(function () use ($defaults, $tenantId, &$imported, &$skipped) {
            foreach ($defaults as $item) {
                $exists = Category::withoutTenantScope()
                    ->where('tenant_id', $tenantId)
                    ->where('name', $item['name'])
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                Category::withoutTenantScope()->create([
                    'tenant_id' => $tenantId,
                    'name' => $item['name'],
                    'description' => $item['description'] ?? '',
                ]);

                $imported++;
            }
        });

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * Import default brands into the given tenant.
     *
     * @return array{imported: int, skipped: int}
     */
    public function importBrands(int $tenantId): array
    {
        $defaults = config('master_data.brands', []);
        $imported = 0;
        $skipped = 0;

        DB::transaction(function () use ($defaults, $tenantId, &$imported, &$skipped) {
            foreach ($defaults as $item) {
                $exists = Brand::withoutTenantScope()
                    ->where('tenant_id', $tenantId)
                    ->where('name', $item['name'])
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                Brand::withoutTenantScope()->create([
                    'tenant_id' => $tenantId,
                    'name' => $item['name'],
                    'slug' => Str::slug($item['name']),
                    'description' => $item['description'] ?? '',
                    'is_active' => true,
                ]);

                $imported++;
            }
        });

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * Import default units into the given tenant.
     *
     * @return array{imported: int, skipped: int}
     */
    public function importUnits(int $tenantId): array
    {
        $defaults = config('master_data.units', []);
        $imported = 0;
        $skipped = 0;

        DB::transaction(function () use ($defaults, $tenantId, &$imported, &$skipped) {
            foreach ($defaults as $item) {
                $exists = Unit::withoutTenantScope()
                    ->where('tenant_id', $tenantId)
                    ->where('name', $item['name'])
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                Unit::withoutTenantScope()->create([
                    'tenant_id' => $tenantId,
                    'name' => $item['name'],
                    'short_name' => $item['short_name'] ?? '',
                    'description' => $item['description'] ?? '',
                    'is_active' => true,
                ]);

                $imported++;
            }
        });

        return ['imported' => $imported, 'skipped' => $skipped];
    }
}
