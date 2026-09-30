<?php

use App\Models\StorefrontNavigationItem;
use App\Models\StorefrontRevision;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('storefront_navigation_items')) {
            StorefrontNavigationItem::withoutTenantScope()
                ->where('key', 'support')
                ->where('label', 'Support')
                ->update(['label' => 'Help & Support']);
        }

        if (!Schema::hasTable('storefront_revisions')) {
            return;
        }

        StorefrontRevision::withoutTenantScope()
            ->orderBy('id')
            ->chunk(100, function ($revisions) {
                foreach ($revisions as $revision) {
                    $configuration = $revision->configuration;
                    if (!is_array($configuration) || !isset($configuration['navigation']['items']) || !is_array($configuration['navigation']['items'])) {
                        continue;
                    }
                    $changed = false;
                    foreach ($configuration['navigation']['items'] as &$item) {
                        if (is_array($item) && ($item['key'] ?? null) === 'support' && ($item['label'] ?? null) === 'Support') {
                            $item['label'] = 'Help & Support';
                            $changed = true;
                        }
                    }
                    unset($item);
                    if ($changed) {
                        $revision->configuration = $configuration;
                        $revision->save();
                    }
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasTable('storefront_navigation_items')) {
            StorefrontNavigationItem::withoutTenantScope()
                ->where('key', 'support')
                ->where('label', 'Help & Support')
                ->update(['label' => 'Support']);
        }
    }
};
