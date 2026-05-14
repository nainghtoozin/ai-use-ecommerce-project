<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    const TYPE_PERCENTAGE = 'percentage';
    const TYPE_FIXED_AMOUNT = 'fixed_amount';
    const TYPE_FREE_SHIPPING = 'free_shipping';

    protected $fillable = [
        'name',
        'description',
        'code',
        'type',
        'discount_value',
        'min_order_amount',
        'discount_cap',
        'usage_limit',
        'per_customer_limit',
        'used_count',
        'is_active',
        'starts_at',
        'expires_at',
        'priority',
        'is_stackable',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'discount_cap' => 'decimal:2',
        'usage_limit' => 'integer',
        'per_customer_limit' => 'integer',
        'used_count' => 'integer',
        'is_active' => 'boolean',
        'is_stackable' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'coupon_product');
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'coupon_category');
    }

    public function orders()
    {
        return $this->belongsToMany(Order::class, 'order_coupon')
            ->withPivot(['code', 'type', 'discount_amount'])
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            })
            ->where(function ($q) {
                $q->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit');
            });
    }

    public function scopeWithCode($query, string $code)
    {
        return $query->where('code', $code);
    }

    public function scopeAutoApply($query)
    {
        return $query->whereNull('code');
    }

    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->starts_at && now()->lt($this->starts_at)) {
            return false;
        }

        if ($this->expires_at && now()->gt($this->expires_at)) {
            return false;
        }

        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    public function hasReachedCustomerLimit(int $userId): bool
    {
        if ($this->per_customer_limit === null) {
            return false;
        }

        $usageCount = $this->orders()
            ->whereHas('user', fn($q) => $q->where('users.id', $userId))
            ->count();

        return $usageCount >= $this->per_customer_limit;
    }

    public function meetsMinimumAmount(float $subtotal): bool
    {
        if ($this->min_order_amount === null) {
            return true;
        }

        return $subtotal >= (float) $this->min_order_amount;
    }
}
