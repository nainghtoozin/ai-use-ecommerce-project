<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use App\Services\OrderWorkflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory, TenantAware;

    const PAYMENT_STATUS_PENDING = 'pending';
    const PAYMENT_STATUS_PAID = 'paid';
    const PAYMENT_STATUS_FAILED = 'failed';
    const PAYMENT_STATUS_REFUNDED = 'refunded';

    const ORDER_STATUS_PENDING = 'pending';
    const ORDER_STATUS_CONFIRMED = 'confirmed';
    const ORDER_STATUS_PROCESSING = 'processing';
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
        'payer_name',
        'payment_screenshot',
        'payment_proof',
        'transaction_id',
        'subtotal',
        'total_amount',
        'delivery_fee',
        'discount_amount',
        'promotion_id',
        'promotion_code',
        'paid_amount',
        'payment_status',
        'order_status',
        'payment_verified_at',
        'rejection_reason',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'payment_verified_at' => 'datetime',
    ];

    protected $appends = [
        'can_cancel',
        'can_confirm',
        'can_process',
        'can_ship',
        'can_deliver',
        'can_mark_as_paid',
        'can_verify_payment',
        'can_approve_payment',
        'can_reject_payment',
        'is_payment_amount_correct',
        'items_total',
        'total_payable',
        'discount_display',
        'payment_screenshot_url',
        'payment_proof_url',
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

    public function coupons()
    {
        return $this->belongsToMany(Coupon::class, 'order_coupon')
            ->withPivot(['code', 'type', 'discount_amount'])
            ->withTimestamps();
    }

    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }

    public function canCancel(): bool
    {
        return in_array($this->order_status, [
            self::ORDER_STATUS_PENDING,
            self::ORDER_STATUS_CONFIRMED,
        ]);
    }

    public function canConfirm(): bool
    {
        return app(OrderWorkflow::class)->canConfirmOrder($this);
    }

    public function canProcess(): bool
    {
        return app(OrderWorkflow::class)->canProcessOrder($this);
    }

    public function canShip(): bool
    {
        return app(OrderWorkflow::class)->canShipOrder($this);
    }

    public function canDeliver(): bool
    {
        return app(OrderWorkflow::class)->canDeliverOrder($this);
    }

    public function canVerifyPayment(): bool
    {
        return in_array($this->payment_status, [self::PAYMENT_STATUS_PENDING, self::PAYMENT_STATUS_PAID]);
    }

    public function canApprovePayment(): bool
    {
        return in_array($this->payment_status, [self::PAYMENT_STATUS_PENDING, self::PAYMENT_STATUS_PAID]);
    }

    public function canRejectPayment(): bool
    {
        return in_array($this->payment_status, [self::PAYMENT_STATUS_PENDING, self::PAYMENT_STATUS_PAID]);
    }

    public function canMarkAsPaid(): bool
    {
        return $this->payment_status === self::PAYMENT_STATUS_PENDING;
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

    public function getDiscountDisplayAttribute(): string
    {
        if ((float) $this->discount_amount <= 0) {
            return '';
        }

        if ($this->promotion_code) {
            return 'Promotion "' . $this->promotion_code . '"';
        }

        $coupon = $this->coupons()->first();
        if ($coupon && $coupon->pivot->code && $coupon->pivot->code !== 'AUTO') {
            return 'Coupon "' . $coupon->pivot->code . '"';
        }

        return 'Discount';
    }

    public function getItemsTotalAttribute(): float
    {
        return $this->items->sum(function ($item) {
            return $item->price * $item->quantity;
        });
    }

    public function getCanCancelAttribute(): bool
    {
        return $this->canCancel();
    }

    public function getCanConfirmAttribute(): bool
    {
        return $this->canConfirm();
    }

    public function getCanProcessAttribute(): bool
    {
        return $this->canProcess();
    }

    public function getCanShipAttribute(): bool
    {
        return $this->canShip();
    }

    public function getCanDeliverAttribute(): bool
    {
        return $this->canDeliver();
    }

    public function getCanMarkAsPaidAttribute(): bool
    {
        return $this->canMarkAsPaid();
    }

    public function getCanVerifyPaymentAttribute(): bool
    {
        return $this->canVerifyPayment();
    }

    public function getCanApprovePaymentAttribute(): bool
    {
        return $this->canApprovePayment();
    }

    public function getCanRejectPaymentAttribute(): bool
    {
        return $this->canRejectPayment();
    }

    public function getIsPaymentAmountCorrectAttribute(): bool
    {
        return $this->isPaymentAmountCorrect();
    }

    public function getPaymentScreenshotUrlAttribute(): string
    {
        return image_url($this->payment_screenshot);
    }

    public function getPaymentProofUrlAttribute(): string
    {
        return image_url($this->payment_proof);
    }
}
