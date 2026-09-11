<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CodRule extends Model
{
    use HasFactory, TenantAware;

    protected $fillable = [
        'tenant_id',
        'name',
        'is_active',
        'min_order_amount',
        'max_order_amount',
        'allowed_city_ids',
        'excluded_city_ids',
        'cod_fee',
        'apply_cod_fee_to_total',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'min_order_amount' => 'decimal:2',
        'max_order_amount' => 'decimal:2',
        'allowed_city_ids' => 'array',
        'excluded_city_ids' => 'array',
        'cod_fee' => 'decimal:2',
        'apply_cod_fee_to_total' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function allowedCities(): BelongsToMany
    {
        return City::whereIn('id', $this->allowed_city_ids ?? [])->get()->map(function ($city) {
            $city->pivot = ['type' => 'allowed'];
            return $city;
        })->keyBy('id');
    }

    public function excludedCities(): BelongsToMany
    {
        return City::whereIn('id', $this->excluded_city_ids ?? [])->get()->map(function ($city) {
            $city->pivot = ['type' => 'excluded'];
            return $city;
        })->keyBy('id');
    }

    public function isAmountEligible(float $orderAmount): bool
    {
        if ($this->min_order_amount !== null && $orderAmount < (float) $this->min_order_amount) {
            return false;
        }

        if ($this->max_order_amount !== null && $orderAmount > (float) $this->max_order_amount) {
            return false;
        }

        return true;
    }

    public function isCityEligible(?int $cityId): bool
    {
        if ($cityId === null) {
            return true;
        }

        if (!empty($this->excluded_city_ids) && in_array($cityId, $this->excluded_city_ids)) {
            return false;
        }

        if (!empty($this->allowed_city_ids) && !in_array($cityId, $this->allowed_city_ids)) {
            return false;
        }

        return true;
    }

    public function isEligible(float $orderAmount, ?int $cityId): bool
    {
        return $this->isAmountEligible($orderAmount) && $this->isCityEligible($cityId);
    }
}
