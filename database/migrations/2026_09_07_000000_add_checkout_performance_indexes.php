<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_services', function (Blueprint $table) {
            if (!Schema::hasIndex('delivery_services', 'idx_delivery_services_tenant_active')) {
                $table->index(['tenant_id', 'is_active'], 'idx_delivery_services_tenant_active');
            }
        });

        Schema::table('packaging_options', function (Blueprint $table) {
            if (!Schema::hasIndex('packaging_options', 'idx_packaging_options_tenant_active')) {
                $table->index(['tenant_id', 'is_active'], 'idx_packaging_options_tenant_active');
            }
        });

        Schema::table('cod_rules', function (Blueprint $table) {
            if (!Schema::hasIndex('cod_rules', 'idx_cod_rules_tenant_active')) {
                $table->index(['tenant_id', 'is_active'], 'idx_cod_rules_tenant_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('delivery_services', function (Blueprint $table) {
            $table->dropIndex('idx_delivery_services_tenant_active');
        });

        Schema::table('packaging_options', function (Blueprint $table) {
            $table->dropIndex('idx_packaging_options_tenant_active');
        });

        Schema::table('cod_rules', function (Blueprint $table) {
            $table->dropIndex('idx_cod_rules_tenant_active');
        });
    }
};
