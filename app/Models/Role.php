<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\Permission\Models\Role as SpatieRole;
use App\Models\Traits\TenantAware;
use Spatie\Permission\PermissionRegistrar;

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

    public function accounts(): MorphToMany
    {
        return $this->morphedByMany(
            Account::class,
            'model',
            config('permission.table_names.model_has_roles', 'model_has_roles'),
            app(PermissionRegistrar::class)->pivotRole,
            config('permission.column_names.model_morph_key', 'model_id')
        );
    }
}
