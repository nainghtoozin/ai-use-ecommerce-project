<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FlashSaleProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'flash_sale_id',
        'product_id',
        'variant_id',
        'flash_price',
        'quantity_limit',
        'quantity_sold',
    ];

    protected $casts = [
        'flash_price' => 'decimal:2',
        'quantity_limit' => 'integer',
        'quantity_sold' => 'integer',
    ];

    public function flashSale()
    {
        return $this->belongsTo(FlashSale::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function hasStock(): bool
    {
        if ($this->quantity_limit === null) {
            return true;
        }

        return $this->quantity_sold < $this->quantity_limit;
    }

    public function remainingStock(): ?int
    {
        if ($this->quantity_limit === null) {
            return null;
        }

        return max(0, $this->quantity_limit - $this->quantity_sold);
    }
}
