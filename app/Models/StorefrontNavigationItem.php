<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;

class StorefrontNavigationItem extends Model
{
    use TenantAware;

    protected $fillable = [
        'tenant_id', 'navigation_id', 'key', 'label', 'path', 'icon', 'enabled', 'position', 'group',
    ];

    protected $casts = ['enabled' => 'boolean', 'position' => 'integer'];

    public function navigation()
    {
        return $this->belongsTo(StorefrontNavigation::class);
    }
}
