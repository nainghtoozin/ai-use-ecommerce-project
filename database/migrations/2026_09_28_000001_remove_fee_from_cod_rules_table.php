<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cod_rules', function (Blueprint $table) {
            $table->dropColumn(['cod_fee', 'apply_cod_fee_to_total']);
        });
    }

    public function down(): void
    {
        Schema::table('cod_rules', function (Blueprint $table) {
            $table->decimal('cod_fee', 8, 2)->default(0);
            $table->boolean('apply_cod_fee_to_total')->default(true);
        });
    }
};
