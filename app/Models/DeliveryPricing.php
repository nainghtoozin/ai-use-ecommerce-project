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
        'township_id',
        'min_days',
        'max_days',
        'is_active',
    ];

    protected $casts = [
        'min_days' => 'integer',
        'max_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function deliveryService(): BelongsTo
    {
        return $this->belongsTo(DeliveryService::class);
    }

    public function township(): BelongsTo
    {
        return $this->belongsTo(Township::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
