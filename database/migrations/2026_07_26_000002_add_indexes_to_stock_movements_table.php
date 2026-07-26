<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index(['tenant_id', 'type', 'created_at'], 'sm_tenant_type_created_index');
            $table->index(['product_id', 'created_at'], 'sm_product_created_index');
            $table->index(['tenant_id', 'adjustment_number'], 'sm_tenant_adjnum_index');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('sm_tenant_type_created_index');
            $table->dropIndex('sm_product_created_index');
            $table->dropIndex('sm_tenant_adjnum_index');
        });
    }
};
