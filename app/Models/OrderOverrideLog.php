<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderOverrideLog extends Model
{
    use TenantAware;

    protected $fillable = [
        'order_id',
        'user_id',
        'field',
        'old_value',
        'new_value',
        'reason',
        'tenant_id',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
