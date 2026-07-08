<?php

namespace App\Auth;

use App\Contracts\ResolvesMembership;
use App\Models\Account;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

class IdentityResolver
{
    public function __construct(
        private readonly ResolvesMembership $membershipResolver,
        private readonly TenantContextResolver $tenantContextResolver,
    ) {}

    public function resolveFromAuth(?Authenticatable $authenticatable): ?Authenticatable
    {
        return $authenticatable;
    }

    public function resolveFromCredentials(array $credentials): ?Authenticatable
    {
        $user = (new User)->resolveRouteBinding($credentials['email'] ?? null, 'email');

        if (! $user instanceof Authenticatable) {
            return null;
        }

        if (password_verify($credentials['password'] ?? '', $user->getAuthPassword())) {
            return $user;
        }

        return null;
    }

    public function supportsAccount(): bool
    {
        return config('identity.use_accounts', false);
    }

    public function getCurrentModelClass(): string
    {
        return User::class;
    }

    public function getFutureModelClass(): string
    {
        return Account::class;
    }

    public function createContextFromCurrentUser(?User $user): IdentityContext
    {
        if ($user === null) {
            return IdentityContext::empty();
        }

        $membership = $this->membershipResolver->resolve($user);

        if ($membership !== null) {
            return new IdentityContext(
                identity: $user,
                membership: $membership,
            );
        }

        return new IdentityContext(
            identity: $user,
            tenantId: $user->tenant_id,
        );
    }

    public function createContextFromIdentity(?Authenticatable $identity): IdentityContext
    {
        if ($identity === null) {
            return IdentityContext::empty();
        }

        $membership = $this->membershipResolver->resolve($identity);
        $tenant = $this->tenantContextResolver->fromAuthenticatable($identity);

        if ($membership !== null) {
            return new IdentityContext(
                identity: $identity,
                membership: $membership,
            );
        }

        return new IdentityContext(
            identity: $identity,
            tenantId: $tenant?->id,
        );
    }
}
