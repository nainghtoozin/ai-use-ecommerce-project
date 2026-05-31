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
        'name',
        'delivery_fee',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'delivery_fee' => 'float',
    ];

    public function townships(): HasMany
    {
        return $this->hasMany(Township::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function getActiveWithTownships()
    {
        $suffix = tenant()?->id ?? 'global';
        return Cache::remember('active_cities_with_townships_' . $suffix, 3600, function () {
            return static::active()
                ->with(['townships' => fn($q) => $q->active()])
                ->orderBy('name')
                ->get();
        });
    }
}
