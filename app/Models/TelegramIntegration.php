<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Casts\Encrypted;

class TelegramIntegration extends Model
{
    use HasFactory, TenantAware;

    protected $fillable = [
        'user_id',
        'bot_name',
        'bot_username',
        'bot_token',
        'chat_id',
        'parse_mode',
        'is_enabled',
        'webhook_secret',
        'last_verified_at',
        'verification_status',
        'chat_type',
        'group_title',
        'chat_username',
    ];

    protected $hidden = [
        'bot_token',
    ];

    protected function casts(): array
    {
        return [
            'bot_token' => Encrypted::class,
            'is_enabled' => 'boolean',
            'last_verified_at' => 'datetime',
            'verification_status' => 'string',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeVerified($query)
    {
        return $query->where('verification_status', 'verified');
    }

    public function scopePendingVerification($query)
    {
        return $query->where('verification_status', 'pending_verification');
    }

    public function scopeFailed($query)
    {
        return $query->where('verification_status', 'failed');
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    public function isEnabled(): bool
    {
        return (bool) $this->is_enabled;
    }

    public function getParseMode(): string
    {
        return $this->parse_mode ?? 'HTML';
    }

    public function getVerificationStatusLabel(): string
    {
        return match ($this->verification_status) {
            'pending_verification' => 'Pending Verification',
            'verified' => 'Verified',
            'failed' => 'Failed',
            default => 'Unknown',
        };
    }

    public function getChatTypeLabel(): string
    {
        return match ($this->chat_type) {
            'private' => 'Private Chat',
            'group' => 'Group',
            'supergroup' => 'Supergroup',
            default => 'Unknown',
        };
    }

    public function markVerified(): void
    {
        $this->verification_status = 'verified';
        $this->last_verified_at = now();
        $this->save();
    }

    public function markFailed(): void
    {
        $this->verification_status = 'failed';
        $this->save();
    }
}
