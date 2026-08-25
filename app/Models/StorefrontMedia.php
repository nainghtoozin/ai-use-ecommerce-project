<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use App\Services\ImageService;
use Illuminate\Database\Eloquent\Model;

class StorefrontMedia extends Model
{
    use TenantAware;

    protected $fillable = [
        'tenant_id', 'storefront_id', 'key', 'path', 'original_name', 'mime_type', 'size', 'alt_text', 'metadata', 'is_visible',
    ];

    protected $casts = ['metadata' => 'array', 'is_visible' => 'boolean', 'size' => 'integer'];

    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        return ImageService::url($this->path);
    }

    public function storefront()
    {
        return $this->belongsTo(Storefront::class);
    }
}
