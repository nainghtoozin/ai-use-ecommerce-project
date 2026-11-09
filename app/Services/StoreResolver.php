<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

class StoreResolver
{
    public function resolve(string $storeSlug): ?Tenant
    {
        $cacheKey = "store_resolver.{$storeSlug}";

        $tenantId = Cache::get($cacheKey);

        if ($tenantId === null) {
            $tenantId = Tenant::where('slug', $storeSlug)->value('id');
            if ($tenantId) {
                Cache::put($cacheKey, $tenantId, 3600);
            } else {
                Cache::forget($cacheKey);
            }
        }

        if (!$tenantId) {
            return null;
        }

        $tenant = Tenant::find($tenantId);

        if (!$tenant || $tenant->slug !== $storeSlug) {
            Cache::forget($cacheKey);
            $tenantId = Tenant::where('slug', $storeSlug)->value('id');
            if ($tenantId) {
                Cache::put($cacheKey, $tenantId, 3600);
                $tenant = Tenant::find($tenantId);
            } else {
                return null;
            }
        }

        return $tenant;
    }

    public function clearCache(?string $storeSlug = null): void
    {
        if ($storeSlug) {
            Cache::forget("store_resolver.{$storeSlug}");
        }
    }
}
