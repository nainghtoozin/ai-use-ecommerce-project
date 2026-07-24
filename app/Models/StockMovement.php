<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    use TenantAware;

    const TYPE_OPENING_STOCK = 'opening_stock';
    const TYPE_PURCHASE = 'purchase';
    const TYPE_SALE = 'sale';
    const TYPE_RETURN = 'return';
    const TYPE_ADJUSTMENT = 'adjustment';
    const TYPE_TRANSFER = 'transfer';

    protected $fillable = [
        'tenant_id',
        'product_id',
        'product_variant_id',
        'warehouse_id',
        'type',
        'quantity',
        'unit_price',
        'reference_type',
        'reference_id',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function isInbound(): bool
    {
        return $this->quantity > 0;
    }

    public function isOutbound(): bool
    {
        return $this->quantity < 0;
    }
}
