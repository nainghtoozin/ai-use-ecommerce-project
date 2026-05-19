<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory;

    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'name', 'description', 'price', 'base_price', 'category_id',
        'stock', 'photo1', 'photo2', 'status'
    ];

    protected $casts = [
        'price' => 'float',
    ];

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
    ];

    protected $appends = ['photo1_url', 'photo2_url'];

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeInactive($query)
    {
        return $query->where('status', self::STATUS_INACTIVE);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function promotions()
    {
        return $this->belongsToMany(Promotion::class, 'promotion_product');
    }

    public function getPhoto1UrlAttribute(): ?string
    {
        if (empty($this->photo1)) {
            return null;
        }

        if (str_starts_with($this->photo1, 'http://') || str_starts_with($this->photo1, 'https://')) {
            return $this->photo1;
        }

        return asset('storage/' . $this->photo1);
    }

    public function getPhoto2UrlAttribute(): ?string
    {
        if (empty($this->photo2)) {
            return null;
        }

        if (str_starts_with($this->photo2, 'http://') || str_starts_with($this->photo2, 'https://')) {
            return $this->photo2;
        }

        return asset('storage/' . $this->photo2);
    }
}
