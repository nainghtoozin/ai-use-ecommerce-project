<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;

    const PAYMENT_STATUS_UNPAID = 'unpaid';
    const PAYMENT_STATUS_PAID = 'paid';
    const PAYMENT_STATUS_VERIFIED = 'verified';
    const PAYMENT_STATUS_REJECTED = 'rejected';

    const ORDER_STATUS_PENDING = 'pending';
    const ORDER_STATUS_VERIFIED = 'verified';
    const ORDER_STATUS_REJECTED = 'rejected';
    const ORDER_STATUS_CONFIRMED = 'confirmed';
    const ORDER_STATUS_SHIPPED = 'shipped';
    const ORDER_STATUS_DELIVERED = 'delivered';
    const ORDER_STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'customer_name',
        'first_name',
        'last_name',
        'phone',
        'email',
        'address',
        'city_id',
        'township_id',
        'postal_code',
        'notes',
        'payment_method_id',
        'payment_proof',
        'transaction_id',
        'subtotal',
        'total_amount',
        'delivery_fee',
        'paid_amount',
        'payment_status',
        'order_status',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function payment()
    {
        return $this->paymentMethod();
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function township()
    {
        return $this->belongsTo(Township::class);
    }

    public function canCancel(): bool
    {
        return in_array($this->order_status, [self::ORDER_STATUS_PENDING, self::ORDER_STATUS_CONFIRMED]);
    }

    public function canConfirm(): bool
    {
        return in_array($this->order_status, [self::ORDER_STATUS_PENDING, self::ORDER_STATUS_VERIFIED]);
    }

    public function canShip(): bool
    {
        return $this->order_status === self::ORDER_STATUS_CONFIRMED;
    }

    public function canDeliver(): bool
    {
        return $this->order_status === self::ORDER_STATUS_SHIPPED;
    }

    public function canVerifyPayment(): bool
    {
        return in_array($this->payment_status, [self::PAYMENT_STATUS_PAID]);
    }

    public function canApprovePayment(): bool
    {
        return in_array($this->payment_status, [self::PAYMENT_STATUS_PAID]);
    }

    public function canRejectPayment(): bool
    {
        return in_array($this->payment_status, [self::PAYMENT_STATUS_PAID]);
    }

    public function canMarkAsPaid(): bool
    {
        return $this->payment_status === self::PAYMENT_STATUS_UNPAID;
    }

    public function isPaymentAmountCorrect(): bool
    {
        if ($this->paid_amount === null) {
            return false;
        }

        return (float) $this->paid_amount >= $this->total_payable;
    }

    public function getTotalPayableAttribute(): float
    {
        return (float) $this->total_amount;
    }

    public function getItemsTotalAttribute(): float
    {
        return $this->items->sum(function ($item) {
            return $item->price * $item->quantity;
        });
    }
}
