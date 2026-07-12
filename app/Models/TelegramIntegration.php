<?php

namespace App\Models;

use App\Models\Traits\HasUser;
use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Casts\Encrypted;

class TelegramIntegration extends Model
{
    use HasFactory, TenantAware, HasUser;

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
        'personal_chat_id',
        'personal_chat_username',
        'personal_chat_title',
        'personal_verified_at',
        'group_chat_id',
        'group_chat_title',
        'group_chat_username',
        'group_chat_type',
        'group_verified_at',
        'default_destination',
        'order_destination',
        'payment_destination',
        'inventory_destination',
        'system_destination',
        'marketing_destination',
        'manual_destination',
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
            'personal_verified_at' => 'datetime',
            'group_verified_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->morphTo();
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

    public function scopePersonalVerified($query)
    {
        return $query->whereNotNull('personal_verified_at');
    }

    public function scopeGroupVerified($query)
    {
        return $query->whereNotNull('group_verified_at');
    }

    public function scopeAnyVerified($query)
    {
        return $query->where(function ($q) {
            $q->where('verification_status', 'verified')
              ->orWhereNotNull('personal_verified_at')
              ->orWhereNotNull('group_verified_at');
        });
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    public function isPersonalVerified(): bool
    {
        return $this->personal_verified_at !== null;
    }

    public function isGroupVerified(): bool
    {
        return $this->group_verified_at !== null;
    }

    public function isAnyVerified(): bool
    {
        return $this->isVerified() || $this->isPersonalVerified() || $this->isGroupVerified();
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

    public function getPersonalStatusLabel(): string
    {
        return $this->isPersonalVerified() ? 'Personal Connected' : 'Not Connected';
    }

    public function getGroupStatusLabel(): string
    {
        if ($this->isGroupVerified()) {
            return match ($this->group_chat_type) {
                'supergroup' => 'Group Connected',
                default => 'Group Connected',
            };
        }

        return 'Not Connected';
    }

    public function getGroupStatusBadge(): string
    {
        if ($this->isGroupVerified()) {
            return 'connected';
        }

        return 'not_connected';
    }

    public function getEffectiveChatId(): ?string
    {
        return $this->group_chat_id ?? $this->personal_chat_id ?? $this->chat_id;
    }

    public function markVerified(): void
    {
        $this->verification_status = 'verified';
        $this->last_verified_at = now();
        $this->save();
    }

    public function markPersonalVerified(): void
    {
        $this->personal_chat_id = $this->personal_chat_id ?? $this->chat_id;
        $this->personal_verified_at = now();
        $this->save();
    }

    public function markGroupVerified(): void
    {
        $this->group_verified_at = now();
        $this->save();
    }

    public function markFailed(): void
    {
        $this->verification_status = 'failed';
        $this->save();
    }

    public function getDestinationForCategory(string $category): string
    {
        $column = $category . '_destination';

        if (isset($this->{$column}) && $this->{$column} !== null) {
            return $this->{$column};
        }

        return $this->default_destination ?? 'personal';
    }

    public function getCategoryDestinations(): array
    {
        $categories = ['order', 'payment', 'inventory', 'system', 'marketing', 'manual'];
        $result = [];

        foreach ($categories as $category) {
            $result[$category] = $this->getDestinationForCategory($category);
        }

        return $result;
    }

    public function disconnectGroup(): void
    {
        $this->group_chat_id = null;
        $this->group_chat_title = null;
        $this->group_chat_username = null;
        $this->group_chat_type = null;
        $this->group_verified_at = null;
        $this->save();
    }
}
