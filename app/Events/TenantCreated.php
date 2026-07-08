<?php

namespace App\Events;

use App\Models\Tenant;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;

class TenantCreated
{
    use Dispatchable;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly Authenticatable $owner,
    ) {}
}
