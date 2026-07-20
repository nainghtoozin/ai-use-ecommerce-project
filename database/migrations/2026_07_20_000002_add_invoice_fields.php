<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('billing_interval')->nullable()->after('plan_id');
            $table->decimal('subtotal', 12, 2)->nullable()->after('amount');
            $table->decimal('tax', 12, 2)->nullable()->after('subtotal');
            $table->decimal('total', 12, 2)->nullable()->after('tax');
            $table->json('line_items')->nullable()->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['billing_interval', 'subtotal', 'tax', 'total', 'line_items']);
        });
    }
};
