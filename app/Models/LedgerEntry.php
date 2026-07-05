<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerEntry extends Model
{
    protected $fillable = [
        'transaction_id',
        'payment_intent_id',
        'type',
        'amount',
        'currency',
        'description',
        'metadata',
        'recorded_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'recorded_at' => 'datetime',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'transaction_id');
    }

    public function paymentIntent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class);
    }
}
