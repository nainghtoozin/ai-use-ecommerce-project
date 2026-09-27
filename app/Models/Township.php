<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Township extends Model
{
    use HasFactory, TenantAware;

    protected $fillable = [
        'tenant_id',
        'city_id',
        'name',
        'postal_code',
        'delivery_fee',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'delivery_fee' => 'float',
    ];

    protected static function booted(): void
    {
        static::creating(function (Township $township) {
            if (empty($township->tenant_id)) {
                throw new \InvalidArgumentException('A tenant is required to create a township.');
            }
        });

        static::saving(function (Township $township) {
            if (empty($township->city_id)) {
                return;
            }

            $city = City::withoutTenantScope()->find($township->city_id);

            if (!$city) {
                throw new \InvalidArgumentException('The selected city is invalid.');
            }

            if (!empty($township->tenant_id) && (int) $city->tenant_id !== (int) $township->tenant_id) {
                throw new \InvalidArgumentException('The selected city does not belong to the current tenant.');
            }

            if (empty($township->tenant_id) && !empty($city->tenant_id)) {
                $township->tenant_id = $city->tenant_id;
            }
        });
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function getByCity(int $cityId)
    {
        return static::where('city_id', $cityId)->active()->orderBy('name')->get();
    }
}
