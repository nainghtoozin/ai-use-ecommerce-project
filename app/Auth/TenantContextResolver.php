<?php

namespace App\Auth;

use App\Models\Tenant;
use App\Models\Account;
use App\Models\TenantMembership;
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

        if ($identity instanceof Account) {
            return TenantMembership::where('account_id', $identity->getAuthIdentifier())
                ->where('is_owner', true)
                ->with('tenant')
                ->first()?->tenant
                ?? TenantMembership::where('account_id', $identity->getAuthIdentifier())
                    ->with('tenant')
                    ->first()?->tenant;
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
