<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotion_banners', function (Blueprint $table) {
            $table->foreignId('storefront_media_id')->nullable()->after('image')->constrained('storefront_media')->nullOnDelete();
            $table->string('cta_label', 100)->nullable()->after('link');
            $table->timestamp('starts_at')->nullable()->after('is_active');
            $table->timestamp('ends_at')->nullable()->after('starts_at');
            $table->unsignedInteger('position')->default(0)->after('ends_at');
            $table->boolean('desktop_visible')->default(true)->after('position');
            $table->boolean('mobile_visible')->default(true)->after('desktop_visible');
            $table->index(['tenant_id', 'is_active', 'starts_at', 'ends_at', 'position'], 'promotion_banners_storefront_schedule_idx');
        });
    }

    public function down(): void
    {
        Schema::table('promotion_banners', function (Blueprint $table) {
            $table->dropForeign(['storefront_media_id']);
            $table->dropIndex('promotion_banners_storefront_schedule_idx');
            $table->dropColumn(['storefront_media_id', 'cta_label', 'starts_at', 'ends_at', 'position', 'desktop_visible', 'mobile_visible']);
        });
    }
};
