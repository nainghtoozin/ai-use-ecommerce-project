<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    use HasFactory, TenantAware;
    protected $fillable = ['name', 'description'];

    public function products() {
        return $this->hasMany(Product::class);
    }

    public function promotions()
    {
        return $this->belongsToMany(Promotion::class, 'promotion_category');
    }
}
