<?php

use App\Models\StorefrontRevision;
use App\Services\StorefrontConfigurationResolver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('storefront_revisions')) {
            return;
        }

        StorefrontRevision::withoutTenantScope()
            ->orderBy('id')
            ->chunk(100, function ($revisions) {
                foreach ($revisions as $revision) {
                    StorefrontConfigurationResolver::backfillSupportInRevision($revision);
                }
            });
    }

    public function down(): void
    {
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
                    $filtered = array_values(array_filter(
                        $configuration['navigation']['items'],
                        fn ($item) => is_array($item) && ($item['key'] ?? null) !== 'support' && ($item['path'] ?? null) !== '/support'
                    ));
                    if (count($filtered) === count($configuration['navigation']['items'])) {
                        continue;
                    }
                    $configuration['navigation']['items'] = $filtered;
                    $revision->configuration = $configuration;
                    $revision->save();
                }
            });
    }
};
