<?php

namespace App\Contracts;

use App\Models\Tenant;
use App\Models\TenantMembership;
use Illuminate\Contracts\Auth\Authenticatable;

interface ResolvesMembership
{
    public function resolve(?Authenticatable $identity = null): ?TenantMembership;

    public function resolveForIdentityAndTenant(Authenticatable $identity, Tenant $tenant): ?TenantMembership;
}