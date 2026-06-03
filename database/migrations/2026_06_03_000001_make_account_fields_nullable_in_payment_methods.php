<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_methods')) {
            Schema::table('payment_methods', function (Blueprint $table) {
                if (Schema::hasColumn('payment_methods', 'account_name')) {
                    DB::statement('ALTER TABLE payment_methods MODIFY account_name VARCHAR(255) NULL');
                }
                if (Schema::hasColumn('payment_methods', 'account_number')) {
                    DB::statement('ALTER TABLE payment_methods MODIFY account_number VARCHAR(255) NULL');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payment_methods')) {
            // Set any existing null values to empty string before reverting
            DB::table('payment_methods')
                ->whereNull('account_name')
                ->update(['account_name' => '']);

            DB::table('payment_methods')
                ->whereNull('account_number')
                ->update(['account_number' => '']);

            Schema::table('payment_methods', function (Blueprint $table) {
                if (Schema::hasColumn('payment_methods', 'account_name')) {
                    DB::statement('ALTER TABLE payment_methods MODIFY account_name VARCHAR(255) NOT NULL');
                }
                if (Schema::hasColumn('payment_methods', 'account_number')) {
                    DB::statement('ALTER TABLE payment_methods MODIFY account_number VARCHAR(255) NOT NULL');
                }
            });
        }
    }
};
