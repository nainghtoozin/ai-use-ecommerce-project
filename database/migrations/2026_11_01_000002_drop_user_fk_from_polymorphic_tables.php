<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'customer_addresses',
        'wishlists',
        'order_override_logs',
        'promotion_usages',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            $foreignKeys = DB::table('information_schema.table_constraints')
                ->where('constraint_schema', DB::getDatabaseName())
                ->where('table_name', $table)
                ->where('constraint_type', 'FOREIGN KEY')
                ->pluck('constraint_name')
                ->all();

            foreach ($foreignKeys as $foreignKey) {
                if (str_ends_with($foreignKey, '_user_id_foreign')) {
                    Schema::table($table, function ($blueprint) use ($foreignKey): void {
                        $blueprint->dropForeign($foreignKey);
                    });
                }
            }
        }
    }

    public function down(): void
    {
        // Re-adding these constraints is only safe for legacy user-only data.
        // Account-mode rows store account IDs, so this rollback may fail when
        // such rows exist.
        $definitions = [
            'customer_addresses' => ['user_id', 'users', 'cascade'],
            'wishlists' => ['user_id', 'users', 'cascade'],
            'order_override_logs' => ['user_id', 'users', 'cascade'],
            'promotion_usages' => ['user_id', 'users', 'null'],
        ];

        foreach ($definitions as $table => [$column, $references, $onDelete]) {
            if ($this->foreignKeyExists($table, $table . '_' . $column . '_foreign')) {
                continue;
            }

            Schema::table($table, function ($blueprint) use ($column, $references, $onDelete): void {
                $foreign = $blueprint->foreign($column)->references('id')->on($references);

                if ($onDelete === 'cascade') {
                    $foreign->cascadeOnDelete();
                } else {
                    $foreign->nullOnDelete();
                }
            });
        }
    }

    private function foreignKeyExists(string $table, string $name): bool
    {
        return DB::table('information_schema.table_constraints')
            ->where('constraint_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('constraint_name', $name)
            ->exists();
    }
};
