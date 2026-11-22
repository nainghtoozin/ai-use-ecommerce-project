<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AfterSalesHistory extends Model
{
    use TenantAware;

    protected $table = 'after_sales_histories';

    protected $fillable = [
        'tenant_id',
        'subject_type',
        'subject_id',
        'actor_type',
        'actor_id',
        'actor_role',
        'action',
        'from_status',
        'to_status',
        'note',
        'is_customer_visible',
    ];

    protected $casts = [
        'is_customer_visible' => 'boolean',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }
}
