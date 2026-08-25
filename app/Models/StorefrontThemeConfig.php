<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;

class StorefrontThemeConfig extends Model
{
    use TenantAware;

    protected $fillable = ['tenant_id', 'storefront_id', 'theme_id', 'configuration'];

    protected $casts = ['configuration' => 'array'];

    public function storefront()
    {
        return $this->belongsTo(Storefront::class);
    }

    public function theme()
    {
        return $this->belongsTo(Theme::class);
    }
}
