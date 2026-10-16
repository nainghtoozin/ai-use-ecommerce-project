<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_cleanups', function (Blueprint $table) {
            if (!Schema::hasColumn('tenant_cleanups', 'retention_ends_at')) {
                $table->timestamp('retention_ends_at')->nullable()->after('grace_ends_at');
            }
            if (!Schema::hasColumn('tenant_cleanups', 'deletion_status')) {
                $table->string('deletion_status', 20)->default('none')->after('retention_ends_at');
            }
            if (!Schema::hasColumn('tenant_cleanups', 'deleted_at')) {
                $table->timestamp('deleted_at')->nullable()->after('restricted_at');
            }
        });

        foreach (['retention_ends_at', 'deletion_status'] as $column) {
            try {
                Schema::table('tenant_cleanups', function (Blueprint $table) use ($column) {
                    $table->index($column);
                });
            } catch (\Throwable $e) {
            }
        }
    }

    public function down(): void
    {
        Schema::table('tenant_cleanups', function (Blueprint $table) {
            $indexes = ['tenant_cleanups_retention_ends_at_index', 'tenant_cleanups_deletion_status_index'];
            foreach ($indexes as $index) {
                try {
                    $table->dropIndex($index);
                } catch (\Throwable $e) {
                }
            }
            foreach (['deleted_at', 'deletion_status', 'retention_ends_at'] as $column) {
                if (Schema::hasColumn('tenant_cleanups', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
