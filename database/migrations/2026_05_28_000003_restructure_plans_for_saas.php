<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->decimal('monthly_price', 10, 2)->nullable()->after('description');
            $table->decimal('yearly_price', 10, 2)->nullable()->after('monthly_price');
            $table->unsignedInteger('product_limit')->nullable()->after('yearly_price');
            $table->unsignedInteger('staff_limit')->nullable()->after('product_limit');
            $table->unsignedInteger('storage_limit')->nullable()->after('staff_limit');
            $table->boolean('analytics_enabled')->default(false)->after('storage_limit');
            $table->boolean('custom_domain_enabled')->default(false)->after('analytics_enabled');
            $table->string('status', 20)->default('active')->after('custom_domain_enabled');

            $table->index('status', 'plans_status_index');
        });

        // Backfill: copy existing price data into new columns (safe no-op if empty)
        DB::table('plans')
            ->whereNull('monthly_price')
            ->update([
                'monthly_price' => DB::raw('price'),
                'yearly_price' => DB::raw('price * 10'),
            ]);
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropIndex('plans_status_index');
            $table->dropColumn([
                'monthly_price',
                'yearly_price',
                'product_limit',
                'staff_limit',
                'storage_limit',
                'analytics_enabled',
                'custom_domain_enabled',
                'status',
            ]);
        });
    }
};
