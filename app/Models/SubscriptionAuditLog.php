<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionAuditLog extends Model
{
    protected $fillable = [
        'subscription_id',
        'tenant_id',
        'event',
        'actor_type',
        'actor_id',
        'old_plan_id',
        'new_plan_id',
        'old_status',
        'new_status',
        'reason',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function oldPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'old_plan_id');
    }

    public function newPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'new_plan_id');
    }

    public function actor()
    {
        return $this->morphTo();
    }
}
