<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class City extends Model
{
    use HasFactory, TenantAware;

    protected $fillable = [
        'tenant_id',
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (City $city) {
            if (empty($city->tenant_id)) {
                throw new \InvalidArgumentException('A tenant is required to create a city.');
            }
        });
    }

    public function townships(): HasMany
    {
        return $this->hasMany(Township::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function cacheKey(): string
    {
        $tenantId = tenant()?->id ?? 'global';

        return "tenant:{$tenantId}:active_cities_with_townships";
    }

    public static function getActiveWithTownships()
    {
        return Cache::remember(static::cacheKey(), 3600, function () {
            return static::active()
                ->with(['townships' => fn($q) => $q->active()->orderBy('name')])
                ->orderBy('name')
                ->get();
        });
    }

    public static function forgetLocationCache(): void
    {
        Cache::forget(static::cacheKey());
    }

    public static function forgetLocationCacheFor(int $tenantId): void
    {
        Cache::forget("tenant:{$tenantId}:active_cities_with_townships");
    }
}
