<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MerchantProfile extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_membership_id',
        'business_name',
        'tax_id',
        'business_address',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'business_address' => 'array',
            'metadata' => 'array',
        ];
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'tenant_membership_id');
    }
}
