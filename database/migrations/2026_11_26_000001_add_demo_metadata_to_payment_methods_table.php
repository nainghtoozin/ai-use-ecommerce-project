<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_methods', 'is_demo')) {
                $table->boolean('is_demo')->default(false)->after('is_active');
            }
            if (!Schema::hasColumn('payment_methods', 'logo_path')) {
                $table->string('logo_path')->nullable()->after('is_demo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            if (Schema::hasColumn('payment_methods', 'logo_path')) {
                $table->dropColumn('logo_path');
            }
            if (Schema::hasColumn('payment_methods', 'is_demo')) {
                $table->dropColumn('is_demo');
            }
        });
    }
};
