<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('storefront_media')) {
            return;
        }

        $duplicates = DB::table('storefront_media')
            ->select('tenant_id', 'original_name', DB::raw('COUNT(*) as aggregate'))
            ->whereNotNull('original_name')
            ->groupBy('tenant_id', 'original_name')
            ->having('aggregate', '>', 1)
            ->get();

        foreach ($duplicates as $duplicate) {
            $rows = DB::table('storefront_media')
                ->where('tenant_id', $duplicate->tenant_id)
                ->where('original_name', $duplicate->original_name)
                ->orderBy('id')
                ->pluck('id');

            foreach ($rows as $index => $id) {
                if ($index === 0) {
                    continue;
                }

                DB::table('storefront_media')
                    ->where('id', $id)
                    ->update(['original_name' => $this->uniqueName((int) $duplicate->tenant_id, (string) $duplicate->original_name, 2)]);
            }
        }

        Schema::table('storefront_media', function (Blueprint $table) {
            if (!$this->hasUniqueIndex('storefront_media', 'storefront_media_tenant_original_unique')) {
                $table->unique(['tenant_id', 'original_name'], 'storefront_media_tenant_original_unique');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('storefront_media')) {
            return;
        }

        Schema::table('storefront_media', function (Blueprint $table) {
            $table->dropUnique('storefront_media_tenant_original_unique');
        });
    }

    private function uniqueName(int $tenantId, string $name, int $start): string
    {
        $base = pathinfo($name, PATHINFO_FILENAME);
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $candidate = $name;
        $suffix = $start;

        do {
            $candidate = $extension !== '' ? "{$base}-{$suffix}.{$extension}" : "{$base}-{$suffix}";
            $exists = DB::table('storefront_media')
                ->where('tenant_id', $tenantId)
                ->where('original_name', $candidate)
                ->exists();
            $suffix++;
        } while ($exists);

        return $candidate;
    }

    private function hasUniqueIndex(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$table}')");
            foreach ($indexes as $index) {
                if (($index->name ?? null) === $indexName) {
                    return true;
                }
            }
            return false;
        }

        $database = $connection->getDatabaseName();
        $result = DB::select(
            'SELECT COUNT(*) as aggregate FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $indexName]
        );

        return ($result[0]->aggregate ?? 0) > 0;
    }
};
