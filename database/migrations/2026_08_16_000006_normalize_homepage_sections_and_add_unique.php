<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_TYPE_MAP = [
        'product_discovery' => 'featured_products',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('storefront_homepage_sections')) {
            return;
        }

        $this->migrateLegacyTypes();
        $this->deduplicateTypes();

        if (!$this->hasUniqueIndex()) {
            Schema::table('storefront_homepage_sections', function (Blueprint $table) {
                $table->unique(['storefront_id', 'type'], 'storefront_homepage_sections_storefront_type_unique');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('storefront_homepage_sections')) {
            return;
        }

        if ($this->hasUniqueIndex()) {
            Schema::table('storefront_homepage_sections', function (Blueprint $table) {
                $table->dropUnique('storefront_homepage_sections_storefront_type_unique');
            });
        }
    }

    private function migrateLegacyTypes(): void
    {
        foreach (self::LEGACY_TYPE_MAP as $legacy => $canonical) {
            $rows = DB::table('storefront_homepage_sections')->where('type', $legacy)->orderBy('id')->get();

            foreach ($rows as $row) {
                $target = DB::table('storefront_homepage_sections')
                    ->where('storefront_id', $row->storefront_id)
                    ->where('type', $canonical)
                    ->where('id', '!=', $row->id)
                    ->first();

                if ($target) {
                    DB::table('storefront_homepage_sections')->where('id', $target->id)->update([
                        'enabled' => (int) ($target->enabled || $row->enabled),
                        'desktop_visible' => (int) ($target->desktop_visible && $row->desktop_visible),
                        'mobile_visible' => (int) ($target->mobile_visible && $row->mobile_visible),
                        'position' => min((int) $target->position, (int) $row->position),
                        'variant' => $target->variant !== 'default' ? $target->variant : $row->variant,
                        'configuration' => json_encode($this->mergeConfiguration(
                            $this->decode($target->configuration),
                            $this->decode($row->configuration),
                        )),
                    ]);
                    DB::table('storefront_homepage_sections')->where('id', $row->id)->delete();
                } else {
                    DB::table('storefront_homepage_sections')->where('id', $row->id)->update(['type' => $canonical]);
                }
            }
        }
    }

    private function deduplicateTypes(): void
    {
        $groups = DB::table('storefront_homepage_sections')
            ->select('storefront_id', 'type', DB::raw('COUNT(*) as total'))
            ->groupBy('storefront_id', 'type')
            ->having('total', '>', 1)
            ->get();

        foreach ($groups as $group) {
            $rows = DB::table('storefront_homepage_sections')
                ->where('storefront_id', $group->storefront_id)
                ->where('type', $group->type)
                ->orderBy('id')
                ->get();

            $canonical = $rows->first();

            foreach ($rows->slice(1) as $duplicate) {
                DB::table('storefront_homepage_sections')->where('id', $canonical->id)->update([
                    'enabled' => (int) ($canonical->enabled || $duplicate->enabled),
                    'desktop_visible' => (int) ($canonical->desktop_visible && $duplicate->desktop_visible),
                    'mobile_visible' => (int) ($canonical->mobile_visible && $duplicate->mobile_visible),
                    'position' => min((int) $canonical->position, (int) $duplicate->position),
                    'variant' => $canonical->variant !== 'default' ? $canonical->variant : $duplicate->variant,
                    'configuration' => json_encode($this->mergeConfiguration(
                        $this->decode($canonical->configuration),
                        $this->decode($duplicate->configuration),
                    )),
                ]);
                DB::table('storefront_homepage_sections')->where('id', $duplicate->id)->delete();
            }
        }
    }

    private function mergeConfiguration(array $primary, array $secondary): array
    {
        foreach ($secondary as $key => $value) {
            if (!array_key_exists($key, $primary) || $primary[$key] === null || $primary[$key] === '' || $primary[$key] === []) {
                $primary[$key] = $value;
            }
        }

        return $primary;
    }

    private function decode($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function hasUniqueIndex(): bool
    {
        $indexName = 'storefront_homepage_sections_storefront_type_unique';
        $tableName = 'storefront_homepage_sections';

        $connection = Schema::getConnection();
        $driver = $connection->getConfig('driver');

        if ($driver === 'sqlite') {
            $result = DB::select("SELECT name FROM sqlite_master WHERE type = 'index' AND tbl_name = ? AND name = ?", [$tableName, $indexName]);
        } else {
            $result = DB::select(
                "SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?",
                [$tableName, $indexName]
            );
        }

        return count($result) > 0;
    }
};
