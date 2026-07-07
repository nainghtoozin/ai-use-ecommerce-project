<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->foreignId('tenant_id')
                ->nullable()
                ->after('id')
                ->constrained('tenants')
                ->nullOnDelete();

            $table->index('tenant_id', 'notifications_tenant_id_index');
        });

        // Assign existing notifications to the notifiable user's tenant
        $defaultTenantId = DB::table('tenants')->where('slug', 'default')->value('id');
        if ($defaultTenantId) {
            DB::statement(
                "UPDATE notifications n
                 INNER JOIN users u ON u.id = n.notifiable_id
                  AND n.notifiable_type = ?
                 SET n.tenant_id = u.tenant_id
                 WHERE n.tenant_id IS NULL",
                ['App\\Models\\User']
            );

            // Any remaining orphaned notifications go to the default tenant
            DB::table('notifications')
                ->whereNull('tenant_id')
                ->update(['tenant_id' => $defaultTenantId]);
        }
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex('notifications_tenant_id_index');
            $table->dropColumn('tenant_id');
        });
    }
};
