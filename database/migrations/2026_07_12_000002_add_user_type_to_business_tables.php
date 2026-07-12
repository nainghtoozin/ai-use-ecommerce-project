<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $userClass = (new User)->getMorphClass();

        Schema::table('orders', function (Blueprint $table) {
            $table->string('user_type')->nullable()->after('user_id');
        });
        DB::table('orders')->whereNull('user_type')->update(['user_type' => $userClass]);

        Schema::table('wishlists', function (Blueprint $table) {
            $table->string('user_type')->nullable()->after('user_id');
        });
        DB::table('wishlists')->whereNull('user_type')->update(['user_type' => $userClass]);

        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->string('user_type')->nullable()->after('user_id');
        });
        DB::table('customer_addresses')->whereNull('user_type')->update(['user_type' => $userClass]);

        Schema::table('promotion_usages', function (Blueprint $table) {
            $table->string('user_type')->nullable()->after('user_id');
        });
        DB::table('promotion_usages')->whereNull('user_type')->update(['user_type' => $userClass]);

        Schema::table('telegram_integrations', function (Blueprint $table) {
            $table->string('user_type')->nullable()->after('user_id');
        });
        DB::table('telegram_integrations')->whereNull('user_type')->update(['user_type' => $userClass]);

        Schema::table('messages', function (Blueprint $table) {
            $table->string('sender_type')->nullable()->after('sender_id');
            $table->string('receiver_type')->nullable()->after('receiver_id');
        });
        DB::table('messages')->whereNull('sender_type')->update(['sender_type' => $userClass]);
        DB::table('messages')->whereNull('receiver_type')->update(['receiver_type' => $userClass]);

        Schema::table('promotions', function (Blueprint $table) {
            $table->string('created_by_type')->nullable()->after('created_by');
        });
        DB::table('promotions')->whereNull('created_by_type')->update(['created_by_type' => $userClass]);

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('impersonator_type')->nullable()->after('impersonator_id');
            $table->string('impersonated_user_type')->nullable()->after('impersonated_user_id');
        });
        DB::table('activity_logs')->whereNull('impersonator_type')->update(['impersonator_type' => $userClass]);
        DB::table('activity_logs')->whereNull('impersonated_user_type')->update(['impersonated_user_type' => $userClass]);

        Schema::table('order_override_logs', function (Blueprint $table) {
            $table->string('user_type')->nullable()->after('user_id');
        });
        DB::table('order_override_logs')->whereNull('user_type')->update(['user_type' => $userClass]);
    }

    public function down(): void
    {
        Schema::table('orders', fn(Blueprint $t) => $t->dropColumn('user_type'));
        Schema::table('wishlists', fn(Blueprint $t) => $t->dropColumn('user_type'));
        Schema::table('customer_addresses', fn(Blueprint $t) => $t->dropColumn('user_type'));
        Schema::table('promotion_usages', fn(Blueprint $t) => $t->dropColumn('user_type'));
        Schema::table('telegram_integrations', fn(Blueprint $t) => $t->dropColumn('user_type'));
        Schema::table('messages', fn(Blueprint $t) => $t->dropColumn(['sender_type', 'receiver_type']));
        Schema::table('promotions', fn(Blueprint $t) => $t->dropColumn('created_by_type'));
        Schema::table('activity_logs', fn(Blueprint $t) => $t->dropColumn(['impersonator_type', 'impersonated_user_type']));
        Schema::table('order_override_logs', fn(Blueprint $t) => $t->dropColumn('user_type'));
    }
};
