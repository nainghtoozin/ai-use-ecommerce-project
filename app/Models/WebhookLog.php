<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookLog extends Model
{
    protected $fillable = [
        'gateway',
        'event_type',
        'gateway_event_id',
        'gateway_reference',
        'payment_intent_id',
        'status',
        'request_headers',
        'request_payload',
        'failure_reason',
        'verified_at',
        'processed_at',
    ];

    protected $casts = [
        'request_headers' => 'array',
        'request_payload' => 'array',
        'verified_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function paymentIntent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class);
    }

    public function markAsVerified(): void
    {
        $this->update(['verified_at' => now()]);
    }
}
