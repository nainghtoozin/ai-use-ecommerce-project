<?php

namespace App\Models;

use App\Services\ImageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentEvidence extends Model
{
    protected $table = 'payment_evidences';

    protected $fillable = [
        'payment_intent_id',
        'type',
        'sender_name',
        'sender_account',
        'transaction_reference',
        'transferred_amount',
        'transfer_date',
        'file_path',
        'note',
        'metadata',
    ];

    protected $appends = [
        'file_path_url',
    ];

    protected $casts = [
        'metadata' => 'array',
        'transferred_amount' => 'decimal:2',
        'transfer_date' => 'date',
    ];

    public function getFilePathUrlAttribute(): ?string
    {
        return ImageService::url($this->file_path);
    }

    public function paymentIntent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class);
    }
}
