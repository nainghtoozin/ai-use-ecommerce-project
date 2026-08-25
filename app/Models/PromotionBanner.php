<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use App\Services\ImageService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromotionBanner extends Model
{
    use HasFactory, TenantAware;

    protected $table = 'promotion_banners';

    protected $fillable = [
        'tenant_id',
        'title',
        'description',
        'image',
        'storefront_media_id',
        'link',
        'cta_label',
        'is_active',
        'starts_at',
        'ends_at',
        'position',
        'desktop_visible',
        'mobile_visible',
    ];

    protected $appends = [
        'image_url',
        'is_currently_visible',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'position' => 'integer',
        'desktop_visible' => 'boolean',
        'mobile_visible' => 'boolean',
    ];

    public function getImageUrlAttribute(): ?string
    {
        if ($this->relationLoaded('storefrontMedia') && $this->storefrontMedia) {
            return $this->storefrontMedia->url;
        }

        return ImageService::url($this->image);
    }

    public function storefrontMedia()
    {
        return $this->belongsTo(StorefrontMedia::class, 'storefront_media_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCurrentlyVisible($query)
    {
        return $query->active()
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }

    public function isCurrentlyVisible(): bool
    {
        return $this->is_active
            && (!$this->starts_at || $this->starts_at->lte(now()))
            && (!$this->ends_at || $this->ends_at->gte(now()));
    }

    public function getIsCurrentlyVisibleAttribute(): bool
    {
        return $this->isCurrentlyVisible();
    }
}
