<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryPricing extends Model
{
    use HasFactory;

    protected $table = 'delivery_pricing';

    protected $fillable = [
        'delivery_service_id',
        'city_id',
        'fee',
        'min_days',
        'max_days',
        'is_active',
    ];

    protected $casts = [
        'fee' => 'integer',
        'min_days' => 'integer',
        'max_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function deliveryService(): BelongsTo
    {
        return $this->belongsTo(DeliveryService::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
