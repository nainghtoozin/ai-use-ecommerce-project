<?php

use App\Models\StorefrontNavigation;
use App\Services\StorefrontConfigurationResolver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('storefront_navigations') || !Schema::hasTable('storefront_navigation_items')) {
            return;
        }

        StorefrontNavigation::withoutTenantScope()
            ->orderBy('id')
            ->chunk(100, function ($navigations) {
                foreach ($navigations as $navigation) {
                    StorefrontConfigurationResolver::ensureSupportItem($navigation);
                }
            });
    }

    public function down(): void
    {
        if (!Schema::hasTable('storefront_navigation_items')) {
            return;
        }

        \App\Models\StorefrontNavigationItem::withoutTenantScope()
            ->where('key', 'support')
            ->where('group', 'header')
            ->where('path', '/support')
            ->delete();
    }
};
