<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('tenants', 'store_url')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('store_url')->nullable()->after('domain');
            });

            DB::table('tenants')->whereNull('store_url')->update([
                'store_url' => DB::raw("CONCAT('/store/', slug)"),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('store_url');
        });
    }
};
