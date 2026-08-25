<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;

class StorefrontContent extends Model
{
    use TenantAware;

    protected $fillable = ['tenant_id', 'storefront_id', 'labels'];

    protected $casts = ['labels' => 'array'];

    public function storefront()
    {
        return $this->belongsTo(Storefront::class);
    }
}
