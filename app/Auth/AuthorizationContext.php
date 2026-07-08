<?php

namespace App\Auth;

use App\Contracts\ResolvesAuthorization;
use App\Models\TenantMembership;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

class AuthorizationContext
{
    private ?Authenticatable $identity;

    private ?TenantMembership $membership;

    private ?int $tenantId;

    private ?string $activeRole;

    private Collection $roles;

    private ?ResolvesAuthorization $authorizationResolver;

    public function __construct(
        ?Authenticatable $identity = null,
        ?TenantMembership $membership = null,
        ?int $tenantId = null,
        ?string $activeRole = null,
        ?Collection $roles = null,
        ?ResolvesAuthorization $authorizationResolver = null,
    ) {
        $this->identity = $identity;
        $this->membership = $membership;
        $this->tenantId = $tenantId;
        $this->activeRole = $activeRole;
        $this->roles = $roles ?? collect();
        $this->authorizationResolver = $authorizationResolver;
    }

    public static function fromIdentityContext(
        IdentityContext $context,
        CurrentRoleResolver $roleResolver,
        ?ResolvesAuthorization $authorizationResolver = null,
    ): self {
        $identity = $context->getIdentity();

        if ($identity === null) {
            return new self(
                authorizationResolver: $authorizationResolver,
            );
        }

        $roles = $roleResolver->resolveAll($identity);
        $activeRole = $roleResolver->resolve($identity);

        return new self(
            identity: $identity,
            membership: $context->getMembership(),
            tenantId: $context->getTenantId(),
            activeRole: $activeRole,
            roles: $roles,
            authorizationResolver: $authorizationResolver,
        );
    }

    public function isAuthenticated(): bool
    {
        return $this->identity !== null;
    }

    public function getIdentity(): ?Authenticatable
    {
        return $this->identity;
    }

    public function getMembership(): ?TenantMembership
    {
        return $this->membership;
    }

    public function getTenantId(): ?int
    {
        return $this->tenantId;
    }

    public function getActiveRole(): ?string
    {
        return $this->activeRole;
    }

    public function getRoles(): Collection
    {
        return $this->roles;
    }

    public function hasRole(string $role): bool
    {
        return $this->roles->contains($role);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('superadmin');
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin') || $this->hasRole('superadmin');
    }

    public function isCustomer(): bool
    {
        return $this->hasRole('customer');
    }

    public function can(string $ability, mixed ...$arguments): bool
    {
        if ($this->authorizationResolver === null) {
            return false;
        }

        return $this->authorizationResolver->can($ability, ...$arguments);
    }

    public function canAny(iterable $abilities, mixed ...$arguments): bool
    {
        if ($this->authorizationResolver === null) {
            return false;
        }

        return $this->authorizationResolver->canAny($abilities, ...$arguments);
    }

    public function withIdentity(?Authenticatable $identity): static
    {
        $clone = clone $this;
        $clone->identity = $identity;

        return $clone;
    }

    public function withMembership(?TenantMembership $membership): static
    {
        $clone = clone $this;
        $clone->membership = $membership;

        if ($membership) {
            $clone->tenantId = $membership->tenant_id;
        }

        return $clone;
    }

    public function withActiveRole(?string $activeRole): static
    {
        $clone = clone $this;
        $clone->activeRole = $activeRole;

        return $clone;
    }

    public static function empty(): self
    {
        return new self(null, null, null, null, collect(), null);
    }
}