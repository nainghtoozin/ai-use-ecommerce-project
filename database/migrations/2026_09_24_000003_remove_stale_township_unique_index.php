<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('townships')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            try {
                Schema::table('townships', function (Blueprint $table) {
                    $table->dropUnique(['city_id', 'name']);
                });
            } catch (\Throwable $e) {
            }

            return;
        }

        $database = DB::getDatabaseName();

        $indexNames = DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', 'townships')
            ->distinct()
            ->pluck('index_name');

        if (!$indexNames->contains('townships_city_id_name_unique')) {
            return;
        }

        $hasCityIdIndex = $indexNames->contains(fn ($name) => $name !== 'townships_city_id_name_unique'
            && DB::table('information_schema.statistics')
                ->where('table_schema', $database)
                ->where('table_name', 'townships')
                ->where('index_name', $name)
                ->where('seq_in_index', 1)
                ->where('column_name', 'city_id')
                ->exists());

        if (!$hasCityIdIndex) {
            DB::statement('ALTER TABLE `townships` ADD INDEX `townships_city_id_index` (`city_id`)');
        }

        DB::statement('ALTER TABLE `townships` DROP INDEX `townships_city_id_name_unique`');
    }

    public function down(): void
    {
    }
};
