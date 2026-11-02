<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('merchant_recovery_claims')) {
            return;
        }

        if (!Schema::hasColumn('merchant_recovery_claims', 'mode')) {
            Schema::table('merchant_recovery_claims', function (Blueprint $table) {
                $table->string('mode', 32)->default('new_store')->after('archive_id');
            });
        }

        try {
            Schema::table('merchant_recovery_claims', function (Blueprint $table) {
                $table->dropUnique(['archive_id']);
            });
        } catch (\Throwable $exception) {
        }

        try {
            Schema::table('merchant_recovery_claims', function (Blueprint $table) {
                $table->unique(['archive_id', 'mode']);
            });
        } catch (\Throwable $exception) {
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('merchant_recovery_claims')) {
            return;
        }

        try {
            Schema::table('merchant_recovery_claims', function (Blueprint $table) {
                $table->dropUnique(['archive_id', 'mode']);
            });
        } catch (\Throwable $exception) {
        }

        if (Schema::hasColumn('merchant_recovery_claims', 'mode')) {
            Schema::table('merchant_recovery_claims', function (Blueprint $table) {
                $table->dropColumn('mode');
            });
        }

        try {
            Schema::table('merchant_recovery_claims', function (Blueprint $table) {
                $table->unique('archive_id');
            });
        } catch (\Throwable $exception) {
        }
    }
};
