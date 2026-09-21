<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const FK = 'orders_user_id_foreign';

    public function up(): void
    {
        if ($this->foreignKeyExists(self::FK)) {
            Schema::table('orders', function ($table) {
                $table->dropForeign(self::FK);
            });
        }
    }

    public function down(): void
    {
        if (!$this->foreignKeyExists(self::FK)) {
            Schema::table('orders', function ($table) {
                $table->foreign('user_id', self::FK)->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    private function foreignKeyExists(string $name): bool
    {
        return DB::table('information_schema.table_constraints')
            ->where('constraint_schema', DB::getDatabaseName())
            ->where('table_name', 'orders')
            ->where('constraint_name', $name)
            ->exists();
    }
};
