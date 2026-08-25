<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;

class StorefrontHomepageSection extends Model
{
    use TenantAware;

    protected $fillable = [
        'tenant_id', 'storefront_id', 'type', 'variant', 'enabled',
        'desktop_visible', 'mobile_visible', 'position', 'configuration',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'desktop_visible' => 'boolean',
        'mobile_visible' => 'boolean',
        'position' => 'integer',
        'configuration' => 'array',
    ];

    public function storefront()
    {
        return $this->belongsTo(Storefront::class);
    }
}
