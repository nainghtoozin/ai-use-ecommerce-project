<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentReview extends Model
{
    protected $table = 'payment_reviews';

    protected $fillable = [
        'payment_intent_id',
        'action',
        'reviewer_id',
        'reviewer_name',
        'reason',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function paymentIntent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class);
    }

    public function scopeApproved($query)
    {
        return $query->where('action', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('action', 'rejected');
    }
}
