<?php

namespace App\Auth;

use App\Contracts\ResolvesMembership;
use App\Models\Account;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;

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

    public function queryUsersForTenant(?int $tenantId): Builder
    {
        if ($this->supportsAccount()) {
            $query = Account::with('roles');
            if ($tenantId) {
                $query->whereHas('memberships', fn($q) => $q->where('tenant_id', $tenantId));
            }
            return $query;
        }

        $query = User::with('roles');
        if ($tenantId) {
            $query->where('users.tenant_id', $tenantId);
        }
        return $query;
    }

    public function findUserForTenant(int $id, ?int $tenantId): ?Authenticatable
    {
        return $this->queryUsersForTenant($tenantId)->where('id', $id)->first();
    }

    public function getCurrentTenantId(): ?int
    {
        return $this->tenantContextResolver->tenantId();
    }

    public static function resolveTenantAdmins(int $tenantId, array $columns = ['id']): \Illuminate\Support\Collection
    {
        if (config('identity.use_accounts')) {
            return Account::whereHas('memberships', fn($q) => $q
                ->where('tenant_id', $tenantId)
                ->where(fn($q) => $q
                    ->where('is_owner', true)
                    ->orWhereHas('role', fn($r) => $r->where('name', 'admin'))
                )
            )->get($columns);
        }
        return User::role('admin')
            ->where('users.tenant_id', $tenantId)
            ->get($columns);
    }

    public static function resolveTenantOwnersAndAdmins(int $tenantId): \Illuminate\Support\Collection
    {
        if (config('identity.use_accounts')) {
            return Account::whereHas('memberships', fn($q) => $q
                ->where('tenant_id', $tenantId)
                ->where(fn($q) => $q->where('is_owner', true)
                    ->orWhereHas('role', fn($r) => $r->where('name', 'admin'))
                )
            )->pluck('id');
        }
        return User::where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->where('is_owner', true)->orWhereHas('roles', fn($r) => $r->where('name', 'admin'));
            })
            ->pluck('id');
    }

    public static function resolveSuperAdmins(): \Illuminate\Support\Collection
    {
        if (config('identity.use_accounts')) {
            return Account::whereHas('roles', fn($q) => $q->where('name', 'superadmin'))->pluck('id');
        }
        return User::role('superadmin')->pluck('id');
    }

    public static function resolveRoleCount(int $roleId, ?int $tenantId): int
    {
        if (config('identity.use_accounts')) {
            $query = Account::whereHas('memberships', fn($q) => $q
                ->where('tenant_id', $tenantId)
                ->where('role_id', $roleId)
            );
            return $query->count();
        }
        $query = User::role(
            \App\Models\Role::where('id', $roleId)->value('name')
        );
        if ($tenantId) {
            $query->where('users.tenant_id', $tenantId);
        }
        return $query->count();
    }

    public static function resolveModelClass(): string
    {
        return config('identity.use_accounts') ? Account::class : User::class;
    }
}
