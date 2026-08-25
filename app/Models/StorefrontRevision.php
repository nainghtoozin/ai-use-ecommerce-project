<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;

class StorefrontRevision extends Model
{
    use TenantAware;

    protected $fillable = [
        'tenant_id', 'storefront_id', 'revision_number', 'status', 'configuration',
        'created_by_type', 'created_by_id', 'published_at', 'published_by_type', 'published_by_id',
    ];

    protected $casts = [
        'configuration' => 'array',
        'revision_number' => 'integer',
        'published_at' => 'datetime',
    ];

    public function storefront()
    {
        return $this->belongsTo(Storefront::class);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
