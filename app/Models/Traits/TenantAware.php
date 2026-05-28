<?php

namespace App\Models\Traits;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;

trait TenantAware
{
    public static function bootTenantAware(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($model) {
            if (! array_key_exists('tenant_id', $model->getAttributes())) {
                $tenant = Tenant::getCurrent();
                if ($tenant) {
                    $model->tenant_id = $tenant->id;
                }
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeForCurrentTenant($query)
    {
        $tenant = Tenant::getCurrent();
        if ($tenant) {
            $query->where($this->getTable() . '.tenant_id', $tenant->id);
        }
        return $query;
    }

    public function scopeAllTenants($query)
    {
        return $query;
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where($this->getTable() . '.tenant_id', $tenantId);
    }
}
