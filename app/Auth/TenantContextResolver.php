<?php

namespace App\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

class TenantContextResolver
{
    public function current(): ?Tenant
    {
        return Tenant::getCurrent();
    }

    public function fromAuthenticatable(Authenticatable $identity): ?Tenant
    {
        if ($identity instanceof User) {
            return $identity->tenant;
        }

        if (method_exists($identity, 'tenant')) {
            return $identity->tenant()->first();
        }

        return null;
    }

    public function tenantId(): ?int
    {
        return $this->current()?->id;
    }

    public function slug(): ?string
    {
        return $this->current()?->slug;
    }
}