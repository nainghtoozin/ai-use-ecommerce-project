<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use App\Services\ImageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Brand extends Model
{
    use HasFactory, TenantAware;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'logo',
        'banner',
        'featured',
        'sort_order',
        'is_active',
    ];

    protected $appends = [
        'logo_url',
        'banner_url',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'featured' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function getLogoUrlAttribute(): ?string
    {
        return ImageService::url($this->logo);
    }

    public function getBannerUrlAttribute(): ?string
    {
        return ImageService::url($this->banner);
    }

    protected static function booted()
    {
        static::creating(function ($brand) {
            if (empty($brand->slug)) {
                $brand->slug = static::generateUniqueSlug($brand->tenant_id, $brand->name, $brand->id);
            }
        });

        static::updating(function ($brand) {
            if ($brand->isDirty('slug') && empty($brand->slug)) {
                $brand->slug = static::generateUniqueSlug($brand->tenant_id, $brand->name, $brand->id);
            }
        });
    }

    private static function generateUniqueSlug(?int $tenantId, string $name, ?int $excludeId = null): string
    {
        $baseSlug = \Illuminate\Support\Str::slug($name);
        if (empty($baseSlug)) {
            $baseSlug = 'brand';
        }

        $slug = $baseSlug;
        $counter = 1;

        $query = static::withoutGlobalScope(\App\Models\Scopes\TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        while ($query->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
            $query = static::withoutGlobalScope(\App\Models\Scopes\TenantScope::class)
                ->where('tenant_id', $tenantId)
                ->where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
        }

        return $slug;
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('featured', true);
    }

    public function scopeSorted($query)
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
    }
}
