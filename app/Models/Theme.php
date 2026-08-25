<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Theme extends Model
{
    protected $fillable = ['slug', 'name', 'version', 'default_tokens', 'is_active'];

    protected $casts = [
        'default_tokens' => 'array',
        'is_active' => 'boolean',
    ];

    public function storefronts()
    {
        return $this->hasMany(Storefront::class);
    }
}
