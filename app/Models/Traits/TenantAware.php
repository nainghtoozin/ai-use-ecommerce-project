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

    /**
     * Whether this model should include records with NULL tenant_id
     * as fallback (shared global records visible to all tenants).
     *
     * Override in the model class and return true for shared reference data
     * that should be accessible across all tenants (e.g., system settings).
     */
    public static function allowsNullTenantFallback(): bool
    {
        return false;
    }
}
