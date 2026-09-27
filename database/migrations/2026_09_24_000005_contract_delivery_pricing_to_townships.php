<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('delivery_pricing')) {
            return;
        }

        if (Schema::hasColumn('delivery_pricing', 'city_id')) {
            $this->assertExpansionComplete();

            DB::table('delivery_pricing')->whereNotNull('city_id')->delete();
        }

        try {
            Schema::table('delivery_pricing', function (Blueprint $table) {
                $table->dropUnique('delivery_pricing_unique');
            });
        } catch (\Throwable $e) {
        }

        if (Schema::hasColumn('delivery_pricing', 'city_id')) {
            Schema::table('delivery_pricing', function (Blueprint $table) {
                $table->dropConstrainedForeignId('city_id');
            });
        }

        if (Schema::hasColumn('delivery_pricing', 'fee')) {
            Schema::table('delivery_pricing', function (Blueprint $table) {
                $table->dropColumn('fee');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('delivery_pricing') && !Schema::hasColumn('delivery_pricing', 'fee')) {
            Schema::table('delivery_pricing', function (Blueprint $table) {
                $table->unsignedInteger('fee')->nullable()->after('township_id');
            });
        }

        if (Schema::hasTable('delivery_pricing') && !Schema::hasColumn('delivery_pricing', 'city_id')) {
            Schema::table('delivery_pricing', function (Blueprint $table) {
                $table->foreignId('city_id')->nullable()->after('delivery_service_id')->constrained()->cascadeOnDelete();
            });

            Schema::table('delivery_pricing', function (Blueprint $table) {
                $table->unique(['delivery_service_id', 'city_id'], 'delivery_pricing_unique');
            });
        }
    }

    private function assertExpansionComplete(): void
    {
        $incomplete = DB::table('delivery_pricing as dp')
            ->join('delivery_services as ds', 'ds.id', '=', 'dp.delivery_service_id')
            ->join('cities as c', 'c.id', '=', 'dp.city_id')
            ->whereNotNull('dp.city_id')
            ->whereNull('dp.township_id')
            ->whereRaw('ds.tenant_id <> c.tenant_id')
            ->count();

        if ($incomplete > 0) {
            throw new \RuntimeException(
                "Migration aborted: {$incomplete} legacy pricing row(s) reference a city from another tenant. Resolve manually."
            );
        }

        $unexpanded = DB::table('delivery_pricing as dp')
            ->join('townships as ref', 'ref.city_id', '=', 'dp.city_id')
            ->join('delivery_services as ds', 'ds.id', '=', 'dp.delivery_service_id')
            ->whereNotNull('dp.city_id')
            ->whereNull('dp.township_id')
            ->whereColumn('ref.tenant_id', 'ds.tenant_id')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('delivery_pricing as dp2')
                    ->whereColumn('dp2.delivery_service_id', 'dp.delivery_service_id')
                    ->whereColumn('dp2.township_id', 'ref.id');
            })
            ->count();

        if ($unexpanded > 0) {
            throw new \RuntimeException(
                "Migration aborted: {$unexpanded} legacy pricing row(s) lack township expansion. Run the Step 1 backfill first."
            );
        }
    }
};
