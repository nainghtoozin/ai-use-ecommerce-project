<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Relations\HasMany;

interface HasMemberships
{
    public function memberships(): HasMany;
}
