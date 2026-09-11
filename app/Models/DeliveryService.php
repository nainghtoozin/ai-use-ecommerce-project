<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryService extends Model
{
    use HasFactory, TenantAware;

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'description',
        'base_fee',
        'fee_per_kg',
        'min_days',
        'max_days',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'base_fee' => 'integer',
        'fee_per_kg' => 'integer',
        'min_days' => 'integer',
        'max_days' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function pricing(): HasMany
    {
        return $this->hasMany(DeliveryPricing::class);
    }

    public function getEtaLabelAttribute(): string
    {
        if ($this->min_days === $this->max_days) {
            return $this->min_days . ' day' . ($this->min_days !== 1 ? 's' : '');
        }
        return $this->min_days . '-' . $this->max_days . ' days';
    }

    public function getFeeForCity(City $city): int
    {
        $pricing = $this->pricing()->where('city_id', $city->id)->active()->first();

        if ($pricing && $pricing->fee !== null) {
            return $pricing->fee;
        }

        return $this->base_fee;
    }

    public function getDaysForCity(City $city): array
    {
        $pricing = $this->pricing()->where('city_id', $city->id)->active()->first();

        if ($pricing && $pricing->min_days !== null) {
            return [
                'min' => $pricing->min_days,
                'max' => $pricing->max_days ?? $pricing->min_days,
            ];
        }

        return [
            'min' => $this->min_days,
            'max' => $this->max_days,
        ];
    }
}
