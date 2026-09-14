<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FlashSale extends Model
{
    use HasFactory, TenantAware;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'discount_type',
        'discount_value',
        'max_discount_amount',
        'starts_at',
        'ends_at',
        'is_active',
        'priority',
        'usage_limit',
        'usage_count',
        'created_by',
        'created_by_type',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'priority' => 'integer',
        'usage_limit' => 'integer',
        'usage_count' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'flash_sale_products')
            ->withPivot(['id', 'flash_price', 'quantity_limit', 'quantity_sold', 'variant_id']);
    }

    public function flashSaleProducts()
    {
        return $this->hasMany(FlashSaleProduct::class);
    }

    public function creator(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo('creator', 'created_by_type', 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeCurrentlyActive(Builder $query): Builder
    {
        return $query->active()
            ->where(function (Builder $q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
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
        return $this->ends_at && now()->gt($this->ends_at);
    }

    public function hasReachedUsageLimit(): bool
    {
        return $this->usage_limit !== null && $this->usage_count >= $this->usage_limit;
    }

    public function getFlashPriceForProduct(int $productId, ?int $variantId = null): ?float
    {
        $pivot = $this->flashSaleProducts()
            ->where('product_id', $productId)
            ->when($variantId, fn($q) => $q->where('variant_id', $variantId), fn($q) => $q->whereNull('variant_id'))
            ->first();

        return $pivot?->flash_price;
    }

    protected static function booted(): void
    {
        static::creating(function (self $flashSale) {
            if ($flashSale->created_by && !$flashSale->created_by_type) {
                $flashSale->created_by_type = auth()->user()?->getMorphClass() ?? (new User)->getMorphClass();
            }
        });
    }
}
