<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('tenants', 'store_category_id')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->foreignId('store_category_id')
                    ->nullable()
                    ->after('status')
                    ->constrained('store_categories')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tenants', 'store_category_id')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropForeign(['store_category_id']);
                $table->dropColumn('store_category_id');
            });
        }
    }
};
