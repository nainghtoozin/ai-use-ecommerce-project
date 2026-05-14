<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Promotion extends Model
{
    use HasFactory;

    const TYPE_PERCENTAGE = 'percentage';
    const TYPE_FIXED = 'fixed';
    const TYPE_FREE_SHIPPING = 'free_shipping';

    const APPLIES_ALL = 'all';
    const APPLIES_PRODUCTS = 'products';
    const APPLIES_CATEGORIES = 'categories';

    protected $fillable = [
        'name',
        'code',
        'description',
        'type',
        'value',
        'max_discount_amount',
        'minimum_order_amount',
        'starts_at',
        'ends_at',
        'usage_limit',
        'usage_count',
        'per_customer_limit',
        'is_active',
        'is_automatic',
        'applies_to',
        'priority',
        'stackable',
        'created_by',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'minimum_order_amount' => 'decimal:2',
        'usage_limit' => 'integer',
        'usage_count' => 'integer',
        'per_customer_limit' => 'integer',
        'is_active' => 'boolean',
        'is_automatic' => 'boolean',
        'stackable' => 'boolean',
        'priority' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'promotion_product');
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'promotion_category');
    }

    public function usages()
    {
        return $this->hasMany(PromotionUsage::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query->active()
            ->where(function (Builder $q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->where(function (Builder $q) {
                $q->whereNull('usage_limit')->orWhereColumn('usage_count', '<', 'usage_limit');
            });
    }

    public function scopeAutomatic(Builder $query): Builder
    {
        return $query->where('is_automatic', true);
    }

    public function scopeManual(Builder $query): Builder
    {
        return $query->where('is_automatic', false)
            ->whereNotNull('code');
    }

    public function isCurrentlyActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->starts_at && now()->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at && now()->gt($this->ends_at)) {
            return false;
        }

        return true;
    }

    public function isExpired(): bool
    {
        if (!$this->ends_at) {
            return false;
        }

        return now()->gt($this->ends_at);
    }

    public function hasReachedUsageLimit(): bool
    {
        if ($this->usage_limit === null) {
            return false;
        }

        return $this->usage_count >= $this->usage_limit;
    }

    public function canBeUsedBy(User $user): bool
    {
        if ($this->per_customer_limit === null) {
            return true;
        }

        $count = $this->usages()
            ->where('user_id', $user->id)
            ->count();

        return $count < $this->per_customer_limit;
    }

    public function appliesToCart(array $cart): bool
    {
        if (empty($cart)) {
            return false;
        }

        if (!$this->isCurrentlyActive()) {
            return false;
        }

        if ($this->hasReachedUsageLimit()) {
            return false;
        }

        $subtotal = $this->calculateCartSubtotal($cart);

        if ($this->minimum_order_amount !== null && $subtotal < (float) $this->minimum_order_amount) {
            return false;
        }

        if ($this->applies_to === self::APPLIES_PRODUCTS) {
            if ($this->products()->count() === 0) {
                return false;
            }

            $cartProductIds = collect($cart)->pluck('id')->filter()->toArray();
            $assignedProductIds = $this->products()->pluck('products.id')->toArray();

            if (empty(array_intersect($cartProductIds, $assignedProductIds))) {
                return false;
            }
        }

        if ($this->applies_to === self::APPLIES_CATEGORIES) {
            if ($this->categories()->count() === 0) {
                return false;
            }

            $cartProductIds = collect($cart)->pluck('id')->filter()->toArray();
            if (empty($cartProductIds)) {
                return false;
            }

            $cartCategoryIds = Product::whereIn('id', $cartProductIds)
                ->pluck('category_id')
                ->unique()
                ->toArray();

            $assignedCategoryIds = $this->categories()->pluck('categories.id')->toArray();

            if (empty(array_intersect($cartCategoryIds, $assignedCategoryIds))) {
                return false;
            }
        }

        return true;
    }

    public function calculateDiscount(array $cart, ?float $deliveryFee = 0): float
    {
        $subtotal = $this->calculateCartSubtotal($cart);

        switch ($this->type) {
            case self::TYPE_PERCENTAGE:
                $discount = $subtotal * ((float) $this->value / 100);
                if ($this->max_discount_amount !== null) {
                    $discount = min($discount, (float) $this->max_discount_amount);
                }
                return round(max($discount, 0), 2);

            case self::TYPE_FIXED:
                return round(min(max((float) $this->value, 0), $subtotal), 2);

            case self::TYPE_FREE_SHIPPING:
                return round(max((float) ($deliveryFee ?? 0), 0), 2);

            default:
                return 0;
        }
    }

    public function recordUsage(Order $order, ?User $user, float $amount): PromotionUsage
    {
        $usage = $this->usages()->create([
            'user_id' => $user?->id,
            'order_id' => $order->id,
            'discount_amount' => $amount,
            'used_at' => now(),
        ]);

        $this->increment('usage_count');

        return $usage;
    }

    public static function findByCode(string $code): ?self
    {
        return static::where('code', $code)->first();
    }

    public static function generateCode(int $length = 8): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $characters[random_int(0, strlen($characters) - 1)];
            }
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public function validateForUsage(?User $user = null, array $cart = [], ?float $deliveryFee = 0): array
    {
        $errors = [];

        if (!$this->isCurrentlyActive()) {
            if ($this->isExpired()) {
                $errors[] = 'This promotion has expired.';
            } else {
                $errors[] = 'This promotion is not currently active.';
            }
        }

        if ($this->hasReachedUsageLimit()) {
            $errors[] = 'This promotion has reached its usage limit.';
        }

        if ($user && !$this->canBeUsedBy($user)) {
            $errors[] = 'You have already used this promotion the maximum number of times.';
        }

        if (!empty($cart) && !$this->appliesToCart($cart)) {
            if ($this->minimum_order_amount !== null) {
                $subtotal = $this->calculateCartSubtotal($cart);
                if ($subtotal < (float) $this->minimum_order_amount) {
                    $errors[] = 'Minimum order amount of ' . number_format((float) $this->minimum_order_amount, 2) . ' is required.';
                }
            } else {
                $errors[] = 'This promotion does not apply to your cart contents.';
            }
        }

        return $errors;
    }

    private function calculateCartSubtotal(array $cart): float
    {
        return (float) collect($cart)->sum(function ($item) {
            return ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
        });
    }
}
