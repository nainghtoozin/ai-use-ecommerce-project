<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MerchantEmailCampaign extends Model
{
    protected $fillable = [
        'created_by',
        'subject',
        'email_type',
        'message',
        'recipient_mode',
        'recipient_count',
        'status',
        'previous_status',
        'reply_to',
        'filters',
        'scheduled_at',
        'started_at',
        'completed_at',
        'archived_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function recipients(): HasMany
    {
        return $this->hasMany(MerchantEmailRecipient::class, 'campaign_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MerchantEmailAttachment::class, 'campaign_id');
    }
}
