<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;
use App\Models\Traits\TenantAware;

class Role extends SpatieRole
{
    use TenantAware;

    protected $fillable = [
        'name',
        'guard_name',
        'updated_at',
        'created_at',
    ];

    public function memberships()
    {
        return $this->hasMany(TenantMembership::class);
    }
}
