<?php

namespace App\Auth;

use App\Models\TenantMembership;
use Illuminate\Contracts\Auth\Authenticatable;

class IdentityContext
{
    private ?Authenticatable $identity;

    private ?TenantMembership $membership;

    private ?int $tenantId;

    public function __construct(
        ?Authenticatable $identity = null,
        ?TenantMembership $membership = null,
        ?int $tenantId = null,
    ) {
        $this->identity = $identity;
        $this->membership = $membership;
        $this->tenantId = $tenantId;
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

    public function getId(): mixed
    {
        return $this->identity?->getAuthIdentifier();
    }

    public function getEmail(): ?string
    {
        if ($this->identity === null) {
            return null;
        }

        if (method_exists($this->identity, 'getEmail')) {
            return $this->identity->getEmail();
        }

        return $this->identity->email ?? null;
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

    public function withTenantId(?int $tenantId): static
    {
        $clone = clone $this;
        $clone->tenantId = $tenantId;

        return $clone;
    }

    public static function empty(): self
    {
        return new self(null, null, null);
    }
}
