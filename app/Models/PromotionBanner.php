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
        'title',
        'description',
        'image',
        'link',
        'is_active',
    ];

    protected $appends = [
        'image_url',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function getImageUrlAttribute(): ?string
    {
        return ImageService::url($this->image);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
