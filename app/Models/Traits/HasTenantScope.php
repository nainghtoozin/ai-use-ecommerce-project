<?php

namespace App\Models\Traits;

use App\Models\Tenant;

trait HasTenantScope
{
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

    public static function bootHasTenantScope(): void
    {
        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
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
}
