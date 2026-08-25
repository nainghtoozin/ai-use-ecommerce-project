<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;

class StorefrontNavigation extends Model
{
    use TenantAware;

    protected $fillable = ['tenant_id', 'storefront_id', 'settings'];

    protected $casts = ['settings' => 'array'];

    public function storefront()
    {
        return $this->belongsTo(Storefront::class);
    }

    public function items()
    {
        return $this->hasMany(StorefrontNavigationItem::class, 'navigation_id')->orderBy('position');
    }
}
