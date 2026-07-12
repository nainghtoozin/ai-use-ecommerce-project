<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Message extends Model
{
    use HasFactory, TenantAware;

    protected $fillable = [
        'sender_id',
        'sender_type',
        'receiver_id',
        'receiver_type',
        'message',
        'is_read',
        'reply_to_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $message) {
            if ($message->sender_id && !$message->sender_type) {
                $message->sender_type = auth()->user()?->getMorphClass() ?? (new User)->getMorphClass();
            }
            if ($message->receiver_id && !$message->receiver_type) {
                $message->receiver_type = auth()->user()?->getMorphClass() ?? (new User)->getMorphClass();
            }
        });
    }

    public function sender(): MorphTo
    {
        return $this->morphTo();
    }

    public function receiver(): MorphTo
    {
        return $this->morphTo();
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'reply_to_id');
    }

    public function replies(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Message::class, 'reply_to_id');
    }

    public function scopeConversation($query, int $userId1, int $userId2)
    {
        return $query->where(function ($q) use ($userId1, $userId2) {
            $q->where('sender_id', $userId1)->where('receiver_id', $userId2);
        })->orWhere(function ($q) use ($userId1, $userId2) {
            $q->where('sender_id', $userId2)->where('receiver_id', $userId1);
        });
    }

    public function scopeRecent($query, int $limit = 50)
    {
        return $query->orderBy('created_at', 'asc')->limit($limit);
    }

    public function scopeUnreadFor($query, int $userId)
    {
        return $query->where('receiver_id', $userId)->where('is_read', false);
    }

    public function markAsRead(): bool
    {
        return $this->update(['is_read' => true]) > 0;
    }
}