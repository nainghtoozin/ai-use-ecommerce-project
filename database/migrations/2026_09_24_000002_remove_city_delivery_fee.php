<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('cities', 'delivery_fee')) {
            Schema::table('cities', function (Blueprint $table) {
                $table->dropColumn('delivery_fee');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('cities', 'delivery_fee')) {
            Schema::table('cities', function (Blueprint $table) {
                $table->decimal('delivery_fee', 10, 2)->default(0)->after('name');
            });
        }
    }
};
