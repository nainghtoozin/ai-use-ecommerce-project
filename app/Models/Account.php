<?php

namespace App\Models;

use App\Contracts\HasSubscription;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\Traits\LogsActivity;
use App\Services\ImageService;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;

class Account extends Authenticatable implements MustVerifyEmailContract, HasSubscription
{
    use HasFactory, SoftDeletes, Notifiable, MustVerifyEmail, HasRoles, LogsActivity;

    private ?TenantMembership $resolvedMembership = null;

    private bool $membershipCached = false;

    protected $guard_name = 'web';

    const ROLE_CUSTOMER = 'customer';
    const ROLE_ADMIN = 'admin';
    const ROLE_SUPERADMIN = 'superadmin';

    const STATUS_ACTIVE = 'active';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_BANNED = 'banned';
    const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'email',
        'password',
        'email_verified_at',
        'remember_token',
        'profile_image',
        'status',
        'notification_preferences',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'profile_image_url',
        'name',
        'is_owner',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'notification_preferences' => 'array',
            'last_login_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(self::ROLE_ADMIN) || $this->hasRole(self::ROLE_SUPERADMIN);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasGlobalRole(self::ROLE_SUPERADMIN);
    }

    public function isCustomer(): bool
    {
        return $this->hasRole(self::ROLE_CUSTOMER);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function isBanned(): bool
    {
        return $this->status === self::STATUS_BANNED;
    }

    public function isInactive(): bool
    {
        return $this->status === self::STATUS_INACTIVE;
    }

    public function getProfileImageUrlAttribute(): ?string
    {
        return ImageService::url($this->profile_image);
    }

    public function getNameAttribute(): string
    {
        return $this->getDisplayName();
    }

    public function getDisplayName(): string
    {
        if (!config('identity.use_accounts')) {
            return $this->email;
        }

        $membership = $this->getCurrentMembership();
        if ($membership) {
            $merchantProfile = $membership->merchantProfile;
            if ($merchantProfile && $merchantProfile->business_name) {
                return $merchantProfile->business_name;
            }

            $customerProfile = $membership->customerProfile;
            if ($customerProfile && $customerProfile->name) {
                return $customerProfile->name;
            }
        }

        return $this->email;
    }

    public function getRoleLabel(): string
    {
        if (!config('identity.use_accounts')) {
            return $this->email;
        }

        if ($this->isSuperAdmin()) {
            return 'Super Admin';
        }

        $membership = $this->getCurrentMembership();
        if (!$membership) {
            return '';
        }

        if ($membership->is_owner) {
            return 'Owner';
        }

        $roleName = $membership->role?->name;
        if (!$roleName) {
            return '';
        }

        return match ($roleName) {
            'superadmin' => 'Super Admin',
            'admin' => 'Admin',
            'customer' => 'Customer',
            'staff' => 'Staff',
            default => str($roleName)->title(),
        };
    }

    public function isOwner(?int $tenantId = null): bool
    {
        $tenantId ??= \App\Models\Tenant::getCurrent()?->id;
        if (!$tenantId) {
            return false;
        }
        return $this->memberships()
            ->where('tenant_id', $tenantId)
            ->where('is_owner', true)
            ->exists();
    }

    public function getIsOwnerAttribute(): bool
    {
        return $this->isOwner();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function wantsNotification(string $type): bool
    {
        $prefs = $this->notification_preferences ?? [];

        if (empty($prefs)) {
            return true;
        }

        return $prefs[$type] ?? true;
    }

    public function getAllowedNotificationTypes(): array
    {
        $types = [
            'order_placed',
            'order_status_changed',
            'payment_verified',
            'payment_rejected',
            'new_message',
            'notification_sound',
        ];

        if ($this->isAdmin()) {
            $types = array_merge($types, [
                'new_order',
                'payment_proof_uploaded',
                'low_stock',
                'order_cancelled',
            ]);
        }

        return $types;
    }

    public function markLogin(string $ip): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ]);
    }

    public function getActivePlan(): ?Plan
    {
        if ($this->isSuperAdmin()) {
            return null;
        }

        $membership = $this->memberships()->with('tenant.subscription.plan')->first();

        if ($membership && $membership->tenant) {
            $subscription = $membership->tenant->subscription;
            return $subscription?->plan ?? Plan::free();
        }

        return Plan::free();
    }

    public function sendPasswordResetNotification($token): void
    {
        $membership = $this->memberships()->with('tenant')->first();

        if ($membership && $membership->tenant) {
            $slug = $membership->tenant->slug;
            ResetPasswordNotification::$createUrlCallback = function ($notifiable, $token) use ($slug) {
                return url("/store/{$slug}/reset-password/{$token}");
            };
        }

        $this->notify(new ResetPasswordNotification($token));

        ResetPasswordNotification::$createUrlCallback = null;
    }

    /*
     * ---------------------------------------------------------------
     * Membership-Scoped Authorization (Account Mode fix)
     * ---------------------------------------------------------------
     * Overrides Spatie's global role resolution to resolve roles
     * through the current TenantMembership instead of model_has_roles.
     * This prevents cross-tenant permission leakage (SECURITY).
     *
     * Key principle:
     *   Account → TenantMembership → Role → Permission
     *
     * SuperAdmin is always global (system-wide access).
     * Owner implicitly includes admin role.
     * ---------------------------------------------------------------
     */

    /**
     * Check if this Account has a global role in model_has_roles.
     * Used only for superadmin escalation — never membership-scoped.
     */
    private function hasGlobalRole(string $roleName): bool
    {
        return DB::table('model_has_roles')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('model_has_roles.model_id', $this->id)
            ->where('model_has_roles.model_type', static::class)
            ->where('roles.name', $roleName)
            ->exists();
    }

    /**
     * Resolve the current TenantMembership for this request's tenant context.
     * Result is cached per request to avoid repeated queries.
     */
    public function getCurrentMembership(): ?TenantMembership
    {
        if (!config('identity.use_accounts')) {
            return null;
        }

        if ($this->membershipCached) {
            return $this->resolvedMembership;
        }

        $this->membershipCached = true;

        $tenantId = Tenant::getCurrent()?->id;
        if (!$tenantId) {
            return null;
        }

        $this->resolvedMembership = $this->memberships()
            ->where('tenant_id', $tenantId)
            ->first();

        return $this->resolvedMembership;
    }

    /*
     * ---------------------------------------------------------------
     * Override Spatie's hasRole() to resolve through membership.
     * SuperAdmin bypass (always global).
     */
    public function hasRole($roles, string $guard = null): bool
    {
        if (!config('identity.use_accounts')) {
            return $this->checkSpatieGlobalRoles($roles);
        }

        if ($this->hasGlobalRole(self::ROLE_SUPERADMIN)) {
            return true;
        }

        $membership = $this->getCurrentMembership();
        if (!$membership) {
            return false;
        }

        $roleNames = [];
        if ($membership->role) {
            $roleNames[] = $membership->role->name;
        }
        if ($membership->is_owner) {
            $roleNames[] = self::ROLE_ADMIN;
        }

        $normalized = $this->normalizeRolesArg($roles);

        foreach ($normalized as $role) {
            if (in_array($role, $roleNames)) {
                return true;
            }
        }

        return false;
    }

    /*
     * ---------------------------------------------------------------
     * Override Spatie's hasPermissionTo() — resolve through membership.
     * SuperAdmin bypass.
     */
    public function hasPermissionTo($permission, $guardName = null): bool
    {
        if (!config('identity.use_accounts')) {
            return $this->checkSpatieGlobalPermission($permission);
        }

        if ($this->hasGlobalRole(self::ROLE_SUPERADMIN)) {
            return true;
        }

        $permissionName = $permission instanceof \Spatie\Permission\Contracts\Permission
            ? $permission->name
            : (string) $permission;

        $membership = $this->getCurrentMembership();
        if (!$membership) {
            return false;
        }

        return $membership->hasPermission($permissionName);
    }

    /*
     * ---------------------------------------------------------------
     * Override getRoleNames() — return only membership role name(s).
     */
    public function getRoleNames(): Collection
    {
        if (!config('identity.use_accounts')) {
            return $this->loadSpatieRoles()->pluck('name');
        }

        if ($this->hasGlobalRole(self::ROLE_SUPERADMIN)) {
            return collect([self::ROLE_SUPERADMIN]);
        }

        $membership = $this->getCurrentMembership();
        if (!$membership) {
            return collect();
        }

        $names = [];
        if ($membership->role) {
            $names[] = $membership->role->name;
        }
        if ($membership->is_owner && !in_array(self::ROLE_ADMIN, $names)) {
            $names[] = self::ROLE_ADMIN;
        }

        return collect($names);
    }

    /*
     * ---------------------------------------------------------------
     * Override getAllPermissions() — return only membership role permissions.
     */
    public function getAllPermissions(): Collection
    {
        if (!config('identity.use_accounts')) {
            return $this->loadSpatiePermissions();
        }

        if ($this->hasGlobalRole(self::ROLE_SUPERADMIN)) {
            return Permission::all();
        }

        $membership = $this->getCurrentMembership();
        if (!$membership) {
            return collect();
        }

        if ($membership->is_owner) {
            return Permission::all();
        }

        return $membership->role?->permissions ?? collect();
    }

    /*
     * ---------------------------------------------------------------
     * roles() relationship — delegates to Spatie's standard morphToMany.
     * Do NOT override: Spatie's scopeRole() depends on this being the
     * raw model_has_roles relationship for query builder operations.
     * Instance-level scoping is handled by hasRole()/hasPermissionTo().
     */

    /*
     * ---------------------------------------------------------------
     * Override assignRole() — update TenantMembership role_id.
     */
    public function assignRole(...$roles): self
    {
        if (!config('identity.use_accounts')) {
            return $this->assignSpatieRole(...$roles);
        }

        $role = $roles[0] ?? null;
        if (!$role) {
            return $this;
        }

        if ($role instanceof Role) {
            $roleModel = $role;
        } elseif (is_string($role)) {
            // Scope to current tenant to avoid picking the global template role
            $tenantId = Tenant::getCurrent()?->id;
            $roleQuery = Role::where('name', $role);
            if ($tenantId) {
                $roleQuery->where('tenant_id', $tenantId);
            }
            $roleModel = $roleQuery->first() ?? Role::where('name', $role)->first();
        } else {
            return $this;
        }

        if (!$roleModel) {
            return $this;
        }

        $tenantId = $roleModel->tenant_id ?? Tenant::getCurrent()?->id;
        if (!$tenantId) {
            return $this;
        }

        $membership = $this->memberships()
            ->where('tenant_id', $tenantId)
            ->first();

        if ($membership) {
            $membership->update(['role_id' => $roleModel->id]);
        }

        return $this;
    }

    /*
     * ---------------------------------------------------------------
     * Override syncRoles() — update TenantMembership role_id.
     */
    public function syncRoles(...$roles): self
    {
        if (!config('identity.use_accounts')) {
            return $this->syncSpatieRoles(...$roles);
        }

        $roleNames = [];
        if (!empty($roles)) {
            $first = $roles[0];
            if (is_array($first)) {
                $roleNames = $first;
            } elseif (is_string($first)) {
                $roleNames = [$first];
            } elseif ($first instanceof Role) {
                $roleNames = [$first->name];
            }
        }

        $roleName = $roleNames[0] ?? null;
        if ($roleName) {
            $tenantId = Tenant::getCurrent()?->id;
            $roleQuery = Role::where('name', $roleName);
            if ($tenantId) {
                $roleQuery->where('tenant_id', $tenantId);
            }
            $roleModel = $roleQuery->first() ?? Role::where('name', $roleName)->first();
            if ($roleModel) {
                $this->assignRole($roleModel);
            }
        }

        return $this;
    }

    /* ---- internal helpers ---- */

    private function checkSpatieGlobalRoles($roles): bool
    {
        $spatieRoles = $this->loadSpatieRoles();
        $normalized = $this->normalizeRolesArg($roles);

        foreach ($normalized as $role) {
            if ($spatieRoles->contains('name', $role)) {
                return true;
            }
        }

        return false;
    }

    private function checkSpatieGlobalPermission($permission): bool
    {
        $permissionName = $permission instanceof \Spatie\Permission\Contracts\Permission
            ? $permission->name
            : (string) $permission;

        return $this->loadSpatiePermissions()
            ->contains('name', $permissionName);
    }

    private function loadSpatieRoles(): Collection
    {
        return $this->morphToMany(
            config('permission.models.role'),
            'model',
            config('permission.table_names.model_has_roles'),
            config('permission.column_names.model_morph_key'),
            'role_id'
        )->get();
    }

    private function loadSpatiePermissions(): Collection
    {
        $roles = $this->loadSpatieRoles();
        $roles->loadMissing('permissions');
        return $roles->flatMap(fn ($role) => $role->permissions)->sort()->values();
    }

    private function normalizeRolesArg($roles): array
    {
        if ($roles instanceof \Spatie\Permission\Contracts\Role) {
            return [$roles->name];
        }

        if ($roles instanceof Collection) {
            return $roles->pluck('name')->toArray();
        }

        if (is_numeric($roles)) {
            $r = Role::find((int) $roles);
            return $r ? [$r->name] : [];
        }

        if (is_string($roles)) {
            return str_contains($roles, '|') ? explode('|', $roles) : [$roles];
        }

        if (is_array($roles)) {
            return $roles;
        }

        return (array) $roles;
    }

    private function assignSpatieRole(...$roles): self
    {
        $roleClass = config('permission.models.role');
        $roleModels = collect($roles)->flatten()->map(function ($role) use ($roleClass) {
            if (!$role instanceof $roleClass) {
                return app($roleClass)->findOrCreate($role);
            }
            return $role;
        });

        $this->morphToMany(
            config('permission.models.role'),
            'model',
            config('permission.table_names.model_has_roles'),
            config('permission.column_names.model_morph_key'),
            'role_id'
        )->syncWithoutDetaching($roleModels);

        return $this;
    }

    private function syncSpatieRoles(...$roles): self
    {
        $roleClass = config('permission.models.role');
        $roleModels = collect($roles)->flatten()->map(function ($role) use ($roleClass) {
            if (!$role instanceof $roleClass) {
                return app($roleClass)->findOrCreate($role);
            }
            return $role;
        });

        $this->morphToMany(
            config('permission.models.role'),
            'model',
            config('permission.table_names.model_has_roles'),
            config('permission.column_names.model_morph_key'),
            'role_id'
        )->sync($roleModels);

        return $this;
    }
}
