<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TeamInvitation extends Model
{
    protected $fillable = [
        'tenant_id',
        'role_id',
        'invited_by',
        'email',
        'token',
        'status',
        'invited_at',
        'accepted_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'invited_at' => 'datetime',
            'accepted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'invited_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' && !$this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function isRevoked(): bool
    {
        return $this->status === 'revoked';
    }

    public function markAccepted(): void
    {
        $this->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);
    }

    public function markRevoked(): void
    {
        $this->update(['status' => 'revoked']);
    }

    public function markExpired(): void
    {
        $this->update(['status' => 'expired']);
    }

    public static function generateToken(): string
    {
        return Str::random(64);
    }

    public function getAcceptUrl(): string
    {
        return route('storefront.team.invite.show', [
            'store_slug' => $this->tenant->slug,
            'token' => $this->token,
        ]);
    }
}
