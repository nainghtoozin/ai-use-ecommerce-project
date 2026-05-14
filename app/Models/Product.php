<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'description', 'price', 'base_price', 'category_id',
        'stock', 'photo1', 'photo2'
    ];

    protected $casts = [
        'price' => 'float',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function promotions()
    {
        return $this->belongsToMany(Promotion::class, 'promotion_product');
    }

    public function getPhoto1UrlAttribute()
    {
        return image_url($this->photo1);
    }

    public function getPhoto2UrlAttribute()
    {
        return image_url($this->photo2);
    }
}
