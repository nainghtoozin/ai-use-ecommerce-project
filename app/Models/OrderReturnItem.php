<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderReturnItem extends Model
{
    use TenantAware;

    protected $table = 'order_return_items';

    protected $fillable = [
        'tenant_id',
        'order_return_id',
        'order_item_id',
        'quantity',
        'received_quantity',
        'condition',
        'restock',
        'inspection_note',
        'stock_restored_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'received_quantity' => 'integer',
        'restock' => 'boolean',
        'stock_restored_at' => 'datetime',
    ];

    public const CONDITION_PENDING = 'pending';
    public const CONDITION_RESELLABLE = 'resellable';
    public const CONDITION_DAMAGED = 'damaged';

    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
