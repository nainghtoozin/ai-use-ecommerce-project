<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Receipt extends Model
{
    use TenantAware;

    protected $fillable = [
        'tenant_id', 'invoice_id', 'payment_intent_id', 'receipt_number',
        'amount', 'currency', 'paid_at', 'details',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'details' => 'array',
        ];
    }

    public static function generateNumber(): string
    {
        $prefix = 'REC-' . now()->format('Y') . '-';
        $last = static::where('receipt_number', 'like', $prefix . '%')
            ->orderByDesc('receipt_number')
            ->value('receipt_number');

        return $prefix . str_pad((string) (($last ? (int) Str::after($last, $prefix) : 0) + 1), 5, '0', STR_PAD_LEFT);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function paymentIntent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class);
    }
}
