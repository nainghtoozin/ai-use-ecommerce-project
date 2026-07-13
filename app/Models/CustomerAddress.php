<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use App\Models\Traits\HasUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CustomerAddress extends Model
{
    use HasFactory, TenantAware, HasUser;

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

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function township()
    {
        return $this->belongsTo(Township::class);
    }
}
