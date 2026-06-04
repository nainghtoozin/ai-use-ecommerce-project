<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

class StoreResolver
{
    public function resolve(string $storeSlug): ?Tenant
    {
        return Cache::remember("store_resolver.{$storeSlug}", 3600, function () use ($storeSlug) {
            return Tenant::where('slug', $storeSlug)->first();
        });
    }

    public function clearCache(?string $storeSlug = null): void
    {
        if ($storeSlug) {
            Cache::forget("store_resolver.{$storeSlug}");
        }
    }
}
