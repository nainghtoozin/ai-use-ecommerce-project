<?php

namespace App\Auth;

use App\Contracts\AuthenticatableIdentity;
use App\Models\Account;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

class IdentityResolver
{
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
        return false;
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
        if (! $user || ! $user instanceof AuthenticatableIdentity) {
            return IdentityContext::empty();
        }

        return new IdentityContext(
            identity: $user,
            tenantId: $user->tenant_id,
        );
    }
}
