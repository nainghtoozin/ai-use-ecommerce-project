<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantMembership extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'account_id',
        'tenant_id',
        'role_id',
        'is_owner',
        'status',
        'invited_by',
        'invited_at',
        'joined_at',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_owner' => 'boolean',
            'is_default' => 'boolean',
            'invited_at' => 'datetime',
            'joined_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function customerProfile(): HasOne
    {
        return $this->hasOne(CustomerProfile::class);
    }

    public function staffProfile(): HasOne
    {
        return $this->hasOne(StaffProfile::class);
    }

    public function merchantProfile(): HasOne
    {
        return $this->hasOne(MerchantProfile::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isOwner(): bool
    {
        return (bool) $this->is_owner;
    }

    public function hasPermission(string $ability): bool
    {
        if ($this->is_owner) {
            return true;
        }

        return $this->role->hasPermissionTo($ability);
    }
}
