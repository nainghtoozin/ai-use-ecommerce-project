# Fix: Users Page Tenant Isolation Bug

## Bug

`AdminUserController::index()` and all other methods in that controller were tied to the Legacy `User` model's `users.tenant_id` column. In Account mode (`IDENTITY_USE_ACCOUNTS=true`):

1. `getTenantFilter()` returned `auth()->user()->tenant_id` — `Account` has **no** `tenant_id` column, so it returned `null`.
2. The `->when(null, ...)` clause was skipped entirely — **all users across all tenants were exposed**.
3. Even with a working filter, the query was against `users` table (Legacy data), not `accounts` through `tenant_memberships`.

## Root Cause

The controller had no mode awareness. It assumed `auth()->user()` is always a `User` model with a `tenant_id` column.

## Fix

### Files Modified

1. **`app/Models/Account.php`**
   - Added `LogsActivity` trait
   - Added `name` attribute accessor (returns `$this->email` as fallback — Account table has no `name` column)
   - Added `isOwner(?int $tenantId = null)` method (checks membership `is_owner` flag)
   - Added `is_owner` attribute accessor (`getIsOwnerAttribute()`)
   - Added `is_owner` to `$appends` for serialization

2. **`app/Auth/IdentityResolver.php`**
   - Added `queryUsersForTenant(?int $tenantId): Builder` — returns mode-aware query builder:
     - Account mode: `Account::with('roles')->whereHas('memberships', ...)`
     - Legacy mode: `User::with('roles')->where('users.tenant_id', ...)`
   - Added `findUserForTenant(int $id, ?int $tenantId): ?Authenticatable`
   - Added `getCurrentTenantId(): ?int` — delegates to `TenantContextResolver`

3. **`app/Http/Controllers/Admin/AdminUserController.php`** (full rewrite)
   - `__construct()` — injects `IdentityResolver`
   - `getTenantFilter()` — Account mode: uses `IdentityResolver::getCurrentTenantId()`; Legacy mode: uses `auth()->user()->tenant_id` (unchanged)
   - `protectOwner()` — handles Account model with `isOwner($tenantId)`
   - `index()` — uses `IdentityResolver::queryUsersForTenant()`
   - `show()` — uses `IdentityResolver::findUserForTenant()`
   - `create()` — no query change (only lists roles)
   - `store()` — Account mode: creates `Account` + `TenantMembership` instead of `User`
   - `edit()` — uses `IdentityResolver::findUserForTenant()`
   - `update()` — uses `IdentityResolver::findUserForTenant()`; handles `allow_cod` only for `User` (no such column on `Account`)
   - `destroy()` — uses `IdentityResolver::findUserForTenant()`; superadmin/admin count queries are mode-aware
   - `suspend()`, `ban()`, `activate()` — uses `IdentityResolver::findUserForTenant()`; status constants are mode-aware

### Data Flow (Account Mode)

```
Tenant::getCurrent()
  → TenantContextResolver::tenantId()
    → IdentityResolver::getCurrentTenantId()
      → AdminUserController::getTenantFilter()

IdentityResolver::queryUsersForTenant($tenantId)
  → Account::with('roles')
      ->whereHas('memberships', fn($q) => $q->where('tenant_id', $tenantId))
```

### Testing

1. **Legacy Mode** (`IDENTITY_USE_ACCOUNTS=false`):
   - Users page shows users from `users` table with `tenant_id` filter — unchanged
   - Create/edit/delete/suspend/ban/activate all use `User` — unchanged

2. **Account Mode** (`IDENTITY_USE_ACCOUNTS=true`):
   - Users page shows accounts from `accounts` table through `tenant_memberships`
   - Each account includes `name` (email fallback), `is_owner` (from membership), `roles` (from Spatie)
   - Accounts from other tenants are excluded
   - Create: creates `Account` + `TenantMembership`
   - Edit: updates `Account` fields (email, password, status, profile_image, roles)
   - Delete: deletes `Account` (cascades memberships)
   - Status toggle: updates `Account.status`

### Known Limitations

1. **`name` field**: Account table has no `name` column. The `name` accessor returns `email` as fallback. The admin user creation form still sends a `name` field but it's ignored in Account mode.
2. **`allow_cod`**: Account has no `allow_cod` column (Legacy `User` feature). The update form ignores this field in Account mode.
3. **`SubscriptionLimitService::staffCount()`** still queries `User` model — may undercount staff in Account mode.
4. **Role counts in `RoleController`** still query `User` model — doesn't affect Users page isolation.
5. **Notifications, broadcasts, and chat** still query `User` — tracked in separate issues.
