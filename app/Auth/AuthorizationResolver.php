<?php

namespace App\Auth;

use App\Contracts\ResolvesAuthorization;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;

class AuthorizationResolver implements ResolvesAuthorization
{
    public function __construct(
        private readonly CurrentRoleResolver $roleResolver,
    ) {}

    public function can(string $ability, mixed ...$arguments): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        return $user->can($ability, ...$arguments);
    }

    public function canAny(iterable $abilities, mixed ...$arguments): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        foreach ($abilities as $ability) {
            if ($user->can($ability, ...$arguments)) {
                return true;
            }
        }

        return false;
    }

    public function hasRole(string $role): bool
    {
        return $this->roleResolver->hasRole($role);
    }

    public function canViaIdentityContext(IdentityContext $context, string $ability): bool
    {
        $identity = $context->getIdentity();

        if ($identity === null) {
            return false;
        }

        if ($identity instanceof User) {
            return $identity->can($ability);
        }

        if (method_exists($identity, 'can')) {
            return $identity->can($ability);
        }

        return $this->resolveViaMembership($context, $ability);
    }

    public function canViaMembership(TenantMembership $membership, string $ability): bool
    {
        return $membership->hasPermission($ability);
    }

    public function canForIdentity(?Authenticatable $identity, string $ability): bool
    {
        if ($identity === null) {
            return false;
        }

        if ($identity instanceof User) {
            return $identity->can($ability);
        }

        if (method_exists($identity, 'can')) {
            return $identity->can($ability);
        }

        return false;
    }

    private function resolveViaMembership(IdentityContext $context, string $ability): bool
    {
        $membership = $context->getMembership();

        if ($membership === null) {
            return false;
        }

        return $membership->hasPermission($ability);
    }
}