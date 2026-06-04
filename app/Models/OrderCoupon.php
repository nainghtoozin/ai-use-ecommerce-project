<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Relations\Pivot;

class OrderCoupon extends Pivot
{
    use TenantAware;

    protected $table = 'order_coupon';

    protected $fillable = [
        'order_id',
        'coupon_id',
        'code',
        'type',
        'discount_amount',
        'tenant_id',
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }
}
