<?php

namespace App\Auth;

use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class CurrentRoleResolver
{
    public const ROLE_PRIORITY = [
        'superadmin' => 0,
        'admin' => 1,
        'customer' => 2,
    ];

    public function resolve(?Authenticatable $identity = null): ?string
    {
        $identity ??= Auth::user();

        if ($identity === null) {
            return null;
        }

        $roles = $this->resolveAll($identity);

        if ($roles->isEmpty()) {
            return null;
        }

        return $roles->first();
    }

    public function resolveAll(?Authenticatable $identity = null): Collection
    {
        $identity ??= Auth::user();

        if ($identity === null) {
            return collect();
        }

        if ($identity instanceof User) {
            return $identity->getRoleNames();
        }

        if (method_exists($identity, 'getRoleNames')) {
            return $identity->getRoleNames();
        }

        return collect();
    }

    public function hasRole(string $role, ?Authenticatable $identity = null): bool
    {
        $identity ??= Auth::user();

        if ($identity === null) {
            return false;
        }

        if ($identity instanceof User) {
            return $identity->hasRole($role);
        }

        if (method_exists($identity, 'hasRole')) {
            return $identity->hasRole($role);
        }

        return false;
    }

    public function resolveFromMembership(TenantMembership $membership): ?string
    {
        return $membership->role?->name;
    }

    public function isSuperAdmin(?Authenticatable $identity = null): bool
    {
        return $this->hasRole('superadmin', $identity);
    }

    public function isAdmin(?Authenticatable $identity = null): bool
    {
        return $this->hasRole('admin', $identity) || $this->hasRole('superadmin', $identity);
    }

    public function isCustomer(?Authenticatable $identity = null): bool
    {
        return $this->hasRole('customer', $identity);
    }
}