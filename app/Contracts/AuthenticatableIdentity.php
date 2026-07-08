<?php

namespace App\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface AuthenticatableIdentity extends Identity, Authenticatable
{
    public function getAuthIdentifierName(): string;

    public function getAuthIdentifier(): mixed;

    public function getAuthPassword(): string;

    public function getRememberToken(): string;

    public function setRememberToken($value): void;

    public function getRememberTokenName(): string;
}
