<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantOnboardingIntent extends Model
{
    protected $fillable = [
        'account_id',
        'status',
        'expires_at',
        'verified_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending_verification';
    }

    public function isReady(): bool
    {
        return $this->status === 'ready_for_setup';
    }
}
