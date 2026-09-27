<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('cities', 'tenant_id')) {
            Schema::table('cities', function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            });
        }

        if (!Schema::hasColumn('townships', 'tenant_id')) {
            Schema::table('townships', function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            });
        }

        if (!Schema::hasColumn('townships', 'delivery_fee')) {
            Schema::table('townships', function (Blueprint $table) {
                $table->decimal('delivery_fee', 10, 2)->default(0)->after('postal_code');
            });
        }

        $this->dropGlobalUniques();
        $this->cloneGlobalLocationsPerTenant();
        $this->addPerTenantUniques();
    }

    public function down(): void
    {
        try {
            Schema::table('cities', function (Blueprint $table) {
                $table->dropUnique(['tenant_id', 'name']);
            });
        } catch (\Throwable $e) {
        }
        try {
            Schema::table('cities', function (Blueprint $table) {
                $table->string('name')->unique()->change();
            });
        } catch (\Throwable $e) {
        }

        try {
            Schema::table('townships', function (Blueprint $table) {
                $table->dropUnique(['tenant_id', 'city_id', 'name']);
            });
        } catch (\Throwable $e) {
        }
        try {
            Schema::table('townships', function (Blueprint $table) {
                $table->unique(['city_id', 'name']);
            });
        } catch (\Throwable $e) {
        }

        if (Schema::hasColumn('townships', 'delivery_fee')) {
            Schema::table('townships', function (Blueprint $table) {
                $table->dropColumn('delivery_fee');
            });
        }
    }

    private function cloneGlobalLocationsPerTenant(): void
    {
        $globalCities = DB::table('cities')->whereNull('tenant_id')->orderBy('id')->get();
        if ($globalCities->isEmpty()) {
            return;
        }

        $globalTownships = DB::table('townships')->whereNull('tenant_id')->orderBy('id')->get();

        $tenantIds = DB::table('tenants')->orderBy('id')->pluck('id');
        if ($tenantIds->isEmpty()) {
            return;
        }

        $this->assertNoOrphanReferences($tenantIds);

        $cityFeeById = [];
        $hasCityFee = Schema::hasColumn('cities', 'delivery_fee');
        foreach ($globalCities as $city) {
            $cityFeeById[$city->id] = $hasCityFee ? (float) ($city->delivery_fee ?? 0) : 0;
        }

        DB::transaction(function () use ($tenantIds, $globalCities, $globalTownships, $cityFeeById) {
            foreach ($tenantIds as $tenantId) {
                $cityMap = [];
                foreach ($globalCities as $city) {
                    $existingId = DB::table('cities')
                        ->where('tenant_id', $tenantId)
                        ->where('name', $city->name)
                        ->value('id');

                    if ($existingId) {
                        $cityMap[$city->id] = (int) $existingId;
                        continue;
                    }

                    $payload = [
                        'tenant_id' => $tenantId,
                        'name' => $city->name,
                        'is_active' => $city->is_active,
                        'created_at' => $city->created_at,
                        'updated_at' => $city->updated_at,
                    ];
                    if (Schema::hasColumn('cities', 'delivery_fee')) {
                        $payload['delivery_fee'] = $city->delivery_fee ?? 0;
                    }

                    $cityMap[$city->id] = (int) DB::table('cities')->insertGetId($payload);
                }

                $townshipMap = [];
                foreach ($globalTownships as $township) {
                    if (!isset($cityMap[$township->city_id])) {
                        continue;
                    }
                    $newCityId = $cityMap[$township->city_id];

                    $existingId = DB::table('townships')
                        ->where('tenant_id', $tenantId)
                        ->where('city_id', $newCityId)
                        ->where('name', $township->name)
                        ->value('id');

                    if ($existingId) {
                        $townshipMap[$township->id] = (int) $existingId;
                        continue;
                    }

                    $townshipMap[$township->id] = (int) DB::table('townships')->insertGetId([
                        'tenant_id' => $tenantId,
                        'city_id' => $newCityId,
                        'name' => $township->name,
                        'postal_code' => $township->postal_code,
                        'delivery_fee' => $cityFeeById[$township->city_id] ?? 0,
                        'is_active' => $township->is_active,
                        'created_at' => $township->created_at,
                        'updated_at' => $township->updated_at,
                    ]);
                }

                $this->remapTenantReferences($tenantId, $cityMap, $townshipMap);
            }

            DB::table('townships')->whereNull('tenant_id')->delete();
            DB::table('cities')->whereNull('tenant_id')->delete();
        });
    }

    private function assertNoOrphanReferences($tenantIds): void
    {
        $known = $tenantIds->map(fn ($id) => (int) $id)->all();

        foreach (['orders', 'customer_addresses'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            $rows = DB::table($table)
                ->whereNotNull('tenant_id')
                ->where(function ($q) {
                    $q->whereNotNull('city_id')->orWhereNotNull('township_id');
                })
                ->select('id', 'tenant_id')
                ->get();

            foreach ($rows as $row) {
                if (!in_array((int) $row->tenant_id, $known, true)) {
                    throw new \RuntimeException(
                        "Migration aborted: {$table}.id={$row->id} references tenant_id={$row->tenant_id} which does not exist. Resolve manually."
                    );
                }
            }
        }
    }

    private function remapTenantReferences(int $tenantId, array $cityMap, array $townshipMap): void
    {
        foreach (['orders', 'customer_addresses'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            foreach ($cityMap as $oldId => $newId) {
                DB::table($table)
                    ->where('tenant_id', $tenantId)
                    ->where('city_id', $oldId)
                    ->update(['city_id' => $newId]);
            }
            foreach ($townshipMap as $oldId => $newId) {
                DB::table($table)
                    ->where('tenant_id', $tenantId)
                    ->where('township_id', $oldId)
                    ->update(['township_id' => $newId]);
            }
        }

        if (Schema::hasTable('delivery_pricing') && Schema::hasTable('delivery_services')) {
            $serviceIds = DB::table('delivery_services')
                ->where('tenant_id', $tenantId)
                ->pluck('id');

            foreach ($serviceIds as $serviceId) {
                foreach ($cityMap as $oldId => $newId) {
                    DB::table('delivery_pricing')
                        ->where('delivery_service_id', $serviceId)
                        ->where('city_id', $oldId)
                        ->update(['city_id' => $newId]);
                }
            }
        }

        if (Schema::hasTable('cod_rules')) {
            $rules = DB::table('cod_rules')->where('tenant_id', $tenantId)->get(['id', 'allowed_city_ids', 'excluded_city_ids']);
            foreach ($rules as $rule) {
                $update = [];
                foreach (['allowed_city_ids', 'excluded_city_ids'] as $column) {
                    if ($rule->{$column} === null) {
                        continue;
                    }
                    $ids = json_decode($rule->{$column}, true);
                    if (!is_array($ids)) {
                        continue;
                    }
                    $remapped = [];
                    foreach ($ids as $id) {
                        $remapped[] = $cityMap[(int) $id] ?? $id;
                    }
                    $update[$column] = json_encode(array_values($remapped));
                }
                if (!empty($update)) {
                    DB::table('cod_rules')->where('id', $rule->id)->update($update);
                }
            }
        }
    }

    private function dropGlobalUniques(): void
    {
        try {
            Schema::table('cities', function (Blueprint $table) {
                $table->dropUnique(['name']);
            });
        } catch (\Throwable $e) {
        }

        try {
            Schema::table('townships', function (Blueprint $table) {
                $table->dropUnique(['city_id', 'name']);
            });
        } catch (\Throwable $e) {
        }
    }

    private function addPerTenantUniques(): void
    {
        try {
            Schema::table('cities', function (Blueprint $table) {
                $table->unique(['tenant_id', 'name']);
            });
        } catch (\Throwable $e) {
        }

        try {
            Schema::table('townships', function (Blueprint $table) {
                $table->unique(['tenant_id', 'city_id', 'name']);
            });
        } catch (\Throwable $e) {
        }
    }
};
