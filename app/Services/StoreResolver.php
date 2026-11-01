<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

class StoreResolver
{
    public function resolve(string $storeSlug): ?Tenant
    {
        $tenantId = Cache::remember("store_resolver.{$storeSlug}", 3600, function () use ($storeSlug) {
            return Tenant::where('slug', $storeSlug)->value('id');
        });

        if (!$tenantId) {
            return null;
        }

        return Tenant::find($tenantId);
    }

    public function clearCache(?string $storeSlug = null): void
    {
        if ($storeSlug) {
            Cache::forget("store_resolver.{$storeSlug}");
        }
    }
}
