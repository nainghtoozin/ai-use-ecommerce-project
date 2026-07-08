<?php

namespace App\Contracts;

use App\Auth\IdentityContext;

interface ResolvesAuthorization
{
    public function can(string $ability, mixed ...$arguments): bool;

    public function canAny(iterable $abilities, mixed ...$arguments): bool;

    public function hasRole(string $role): bool;

    public function canViaIdentityContext(IdentityContext $context, string $ability): bool;
}