<?php

namespace App\Auth;

use App\Contracts\ResolvesMembership;
use App\Models\Account;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

class MembershipResolver implements ResolvesMembership
{
    public function __construct(
        private readonly TenantContextResolver $tenantContextResolver,
    ) {}

    public function resolve(?Authenticatable $identity = null): ?TenantMembership
    {
        if ($identity === null) {
            return null;
        }

        $tenant = $this->tenantContextResolver->current();

        if ($tenant === null) {
            return null;
        }

        return $this->resolveForIdentityAndTenant($identity, $tenant);
    }

    public function resolveForIdentityAndTenant(Authenticatable $identity, Tenant $tenant): ?TenantMembership
    {
        if (! config('identity.use_accounts')) {
            return null;
        }

        $email = $this->resolveEmail($identity);

        if ($email === null) {
            return null;
        }

        $account = Account::where('email', $email)->first();

        if ($account === null) {
            return null;
        }

        return $account->memberships()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->first();
    }

    public function resolveForAccount(Account $account, ?Tenant $tenant = null): ?TenantMembership
    {
        $tenant ??= $this->tenantContextResolver->current();

        if ($tenant === null) {
            return null;
        }

        return $account->memberships()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->first();
    }

    private function resolveEmail(Authenticatable $identity): ?string
    {
        if ($identity instanceof User) {
            return $identity->email;
        }

        if (method_exists($identity, 'getEmail')) {
            return $identity->getEmail();
        }

        return $identity->email ?? null;
    }
}