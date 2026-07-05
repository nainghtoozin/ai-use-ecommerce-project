<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'monthly_price',
        'yearly_price',
        'product_limit',
        'staff_limit',
        'storage_limit',
        'analytics_enabled',
        'custom_domain_enabled',
        'orders_monthly_limit',
        'coupon_limit',
        'promotion_limit',
        'flash_sale_limit',
        'api_request_limit',
        'image_limit',
        'image_max_size_kb',
        'branch_limit',
        'warehouse_limit',
        'pos_device_limit',
        'status',
        // Deprecated compat columns — kept for backward compat
        'price',
        'currency',
        'interval',
        'is_default',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'monthly_price' => 'decimal:2',
        'yearly_price' => 'decimal:2',
        // NOT casting to integer — null means unlimited, 0 means zero.
        // The integer cast would convert DB NULL → 0, losing the distinction.
        'analytics_enabled' => 'boolean',
        'custom_domain_enabled' => 'boolean',
        // Deprecated compat
        'price' => 'float',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $attributes = [
        'status' => 'active',
        'analytics_enabled' => false,
        'custom_domain_enabled' => false,
        'currency' => 'USD',
        'interval' => 'monthly',
        'is_default' => false,
        'is_active' => true,
        'sort_order' => 0,
    ];

    /* ── Scopes ── */

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    public function scopeDeprecated($query)
    {
        return $query->where('status', 'deprecated');
    }

    public function scopeDefault($query)
    {
        return $query->where('slug', 'free');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('monthly_price')->orderBy('id');
    }

    /* ── Relationships ── */

    public function features()
    {
        return $this->hasMany(PlanFeature::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    /* ── Feature checks ── */

    public function hasFeature(string $featureKey): bool
    {
        return $this->features()
            ->where('feature_key', $featureKey)
            ->where('is_enabled', true)
            ->exists();
    }

    public function getEnabledFeatures(): array
    {
        return $this->features()
            ->where('is_enabled', true)
            ->pluck('feature_key')
            ->toArray();
    }

    /* ── Limit helpers ── */

    public function productLimit(): ?int
    {
        return $this->product_limit;
    }

    public function staffLimit(): ?int
    {
        return $this->staff_limit;
    }

    public function storageLimitMb(): ?int
    {
        return $this->storage_limit;
    }

    public function hasUnlimitedProducts(): bool
    {
        return $this->product_limit === null;
    }

    public function hasUnlimitedStaff(): bool
    {
        return $this->staff_limit === null;
    }

    public function isUnlimited(string $column): bool
    {
        return $this->{$column} === null;
    }

    public function limitValue(string $column): ?int
    {
        return $this->{$column};
    }

    /* ── Plan lookup helpers ── */

    public function isFree(): bool
    {
        if ($this->slug === 'free') {
            return true;
        }
        $monthlyIsFree = $this->monthly_price === null || $this->monthly_price == 0;
        $yearlyIsFree = $this->yearly_price === null || $this->yearly_price == 0;
        return $monthlyIsFree && $yearlyIsFree;
    }

    public function isPaid(): bool
    {
        return !$this->isFree();
    }

    public static function free(): ?self
    {
        return static::where('slug', 'free')
            ->where('status', 'active')
            ->first();
    }

    public static function defaultPlan(): ?self
    {
        return static::free();
    }

    /* ── Billing period helpers ── */

    public function calculateExpiryDate(\Carbon\Carbon $from = null, string $interval = 'monthly'): ?\Carbon\Carbon
    {
        $from = $from ?? now();
        if ($this->isFree()) {
            return null;
        }
        return match ($interval) {
            'monthly' => $from->copy()->addMonth()->endOfDay(),
            'yearly' => $from->copy()->addYear()->endOfDay(),
            default => null,
        };
    }

    public function defaultInterval(): string
    {
        if ($this->isFree()) {
            return 'monthly';
        }
        if ($this->monthly_price && $this->monthly_price > 0) {
            return 'monthly';
        }
        if ($this->yearly_price && $this->yearly_price > 0) {
            return 'yearly';
        }
        return 'monthly';
    }

    /* ── Price helpers ── */

    public function getPrice(string $interval = 'monthly'): ?float
    {
        return $interval === 'yearly' ? $this->yearly_price : $this->monthly_price;
    }

    public function getPriceForInterval(string $interval): ?float
    {
        return match ($interval) {
            'monthly' => $this->monthly_price,
            'yearly' => $this->yearly_price,
            default => null,
        };
    }

    public function yearlySavingsPercent(): ?float
    {
        if (!$this->monthly_price || !$this->yearly_price || $this->monthly_price <= 0) {
            return null;
        }
        $annualMonthly = $this->monthly_price * 12;
        if ($annualMonthly <= 0) {
            return null;
        }
        return round((($annualMonthly - $this->yearly_price) / $annualMonthly) * 100, 1);
    }

    public function getCalculatedYearlyPriceAttribute(): ?float
    {
        if ($this->monthly_price === null || $this->monthly_price <= 0) {
            return null;
        }
        return round($this->monthly_price * 12, 2);
    }
}
