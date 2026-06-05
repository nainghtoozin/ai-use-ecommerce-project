<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CustomerAddress extends Model
{
    use HasFactory, TenantAware;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'label',
        'first_name',
        'last_name',
        'phone',
        'address_line',
        'city_id',
        'township_id',
        'postal_code',
        'is_default',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function township()
    {
        return $this->belongsTo(Township::class);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
