<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory, TenantAware;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'parent_id',
        'is_active',
        'image',
        'featured',
        'sort_order',
    ];

    protected $appends = [
        'image_url',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'featured' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function ($category) {
            if (empty($category->slug)) {
                $category->slug = static::generateUniqueSlug($category->tenant_id, $category->name, $category->id);
            }
        });

        static::updating(function ($category) {
            if ($category->isDirty('slug') && empty($category->slug)) {
                $category->slug = static::generateUniqueSlug($category->tenant_id, $category->name, $category->id);
            }
        });
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function promotions()
    {
        return $this->belongsToMany(Promotion::class, 'promotion_category');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeWithParent($query)
    {
        return $query->whereNotNull('parent_id');
    }

    public function scopeRootOnly($query)
    {
        return $query->whereNull('parent_id');
    }

    public function getSlugUrlAttribute(): ?string
    {
        return $this->slug ? url('/store/' . tenant()?->slug . '/category/' . $this->slug) : null;
    }

    public function getImageUrlAttribute(): ?string
    {
        return \App\Services\ImageService::url($this->image);
    }

    public function scopeFeatured($query)
    {
        return $query->where('featured', true);
    }

    public function scopeSorted($query)
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
    }

    public function hasCircularReference(int $potentialParentId): bool
    {
        if ($potentialParentId === $this->id) {
            return true;
        }

        $parent = $this->parent;
        while ($parent) {
            if ($parent->id === $potentialParentId) {
                return true;
            }
            $parent = $parent->parent;
        }

        return false;
    }

    private static function generateUniqueSlug(?int $tenantId, string $name, ?int $excludeId = null): string
    {
        $baseSlug = Str::slug($name);
        if (empty($baseSlug)) {
            $baseSlug = 'category';
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
}
