<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;

class StorefrontDesignToken extends Model
{
    use TenantAware;

    protected $fillable = ['tenant_id', 'storefront_id', 'tokens'];

    protected $casts = ['tokens' => 'array'];

    public function storefront()
    {
        return $this->belongsTo(Storefront::class);
    }
}
