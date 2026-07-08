<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StaffProfile extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_membership_id',
        'position',
        'department',
    ];

    public function membership(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'tenant_membership_id');
    }
}
