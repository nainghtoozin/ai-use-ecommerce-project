<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('name');
            $table->string('slug')->nullable()->after('display_name');
            $table->string('branch')->nullable()->after('bank_name');
            $table->text('instructions')->nullable()->after('account_number');
            $table->string('currency', 3)->nullable()->after('instructions');
            $table->integer('sort_order')->default(0)->after('is_active');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn(['display_name', 'slug', 'branch', 'instructions', 'currency', 'sort_order']);
            $table->dropSoftDeletes();
        });
    }
};
