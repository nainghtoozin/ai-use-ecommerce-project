<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_owner')->default(false)->after('email_verified_at');
            $table->index('is_owner', 'users_is_owner_index');
        });

        $this->backfillOwners();
    }

    private function backfillOwners(): void
    {
        $tenants = DB::table('tenants')->pluck('id');

        foreach ($tenants as $tenantId) {
            $firstAdmin = DB::table('users')
                ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->where('users.tenant_id', $tenantId)
                ->where('roles.name', 'admin')
                ->where('model_has_roles.model_type', 'App\Models\User')
                ->orderBy('users.created_at', 'asc')
                ->select('users.id')
                ->first();

            if ($firstAdmin) {
                DB::table('users')
                    ->where('id', $firstAdmin->id)
                    ->update(['is_owner' => true]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_is_owner_index');
            $table->dropColumn('is_owner');
        });
    }
};
