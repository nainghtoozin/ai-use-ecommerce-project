# Membership-Scoped Authorization Fix

## Security Bug

After implementing Existing Account Reuse, Account roles were resolving **globally** through Spatie's `model_has_roles` table — which has no tenant isolation. An Account with `admin` role in Tenant A retained `admin` permissions when accessing Tenant B as `customer`.

### Root Cause

```
Spatie Permission tables (GLOBAL, no tenant_id):
  model_has_roles: { role_id: 1 (admin), model_id: 123, model_type: Account }
  role_has_permissions: { role_id: 1, permission_id: 42 }

Every auth path resolves roles from this global table:
  auth()->user()->hasRole('admin')       → model_has_roles → GLOBAL
  auth()->user()->can('products.create') → model_has_roles → role_has_permissions → GLOBAL
  RoleMiddleware::handle()               → $user->hasRole() → GLOBAL
  HandleInertiaRequests::share()         → getAllPermissions() → GLOBAL
```

### Expected Architecture

```
Account
  ↓
TenantMembership  ←  has role_id + is_owner
  ↓
Role              ←  scoped to tenant (tenant_id column)
  ↓
Permission        ←  role_has_permissions
```

## Fix Applied

### 1. Account Model — Override Spatie's Global Methods

**File:** `app/Models/Account.php`

All 6 Spatie entry points overridden to resolve through `TenantMembership`:

| Method | Behavior in Account Mode |
|--------|--------------------------|
| `hasRole()` | Checks `getCurrentMembership()?->role->name`. Owner implicitly has `admin`. SuperAdmin bypass (global). |
| `hasPermissionTo()` | Calls `$membership->hasPermission($ability)`. Owner has all. SuperAdmin bypass. |
| `getRoleNames()` | Returns `[$membership->role->name]` (+ `admin` if owner). |
| `getAllPermissions()` | Returns `$membership->role->permissions`. Owner returns all. |
| `assignRole()` | Updates `TenantMembership.role_id` instead of writing to global `model_has_roles`. |
| `syncRoles()` | Delegates to `assignRole()` for membership update. |

**Resolution order for every check:**

```
1. SuperAdmin?          →  Check model_has_roles globally
                           (hasGlobalRole('superadmin'))
2. Current tenant?      →  Resolve TenantMembership
3. Owner?               →  Grant all (implicit admin)
4. Default              →  Check membership->role->name / permissions
```

**Key design decisions:**

- `isSuperAdmin()` queries `model_has_roles` directly — **never membership-scoped**.
- `roles()` relationship is **NOT overridden** — Spatie's `scopeRole()` (`Account::role('admin')`) relies on the raw `model_has_roles` relationship for query builder operations.
- `getCurrentMembership()` caches per-request on the Account model.

### 2. IdentityResolver — Membership-Scoped Static Helpers

**File:** `app/Auth/IdentityResolver.php`

Replaced `Account::role('admin')` (Spatie global scope) with membership-join queries:

```php
// BEFORE (global):
Account::role('admin')
    ->whereHas('memberships', fn($q) => $q->where('tenant_id', $tenantId))
    ->get();

// AFTER (membership-scoped):
Account::whereHas('memberships', fn($q) => $q
    ->where('tenant_id', $tenantId)
    ->where(fn($q) => $q
        ->where('is_owner', true)
        ->orWhereHas('role', fn($r) => $r->where('name', 'admin'))
    )
)->get();
```

Changes applied to: `resolveTenantAdmins()`, `resolveTenantOwnersAndAdmins()`, `resolveRoleCount()`.

### 3. CurrentRoleResolver — Account Type Check

**File:** `app/Auth/CurrentRoleResolver.php`

Added explicit `Account` type check to ensure `$identity->getRoleNames()` (membership-scoped override) is called for Accounts.

### 4. HandleInertiaRequests — Scoped Frontend Data

**File:** `app/Http/Middleware/HandleInertiaRequests.php`

- `tenant_id` for Account now returns `Tenant::getCurrent()?->id` (previously `null`).
- `getRoleNames()`, `getAllPermissions()`, `isAdmin()`, `isSuperAdmin()` all use Account overrides — automatically membership-scoped.

### 5. AdminUserController — Role Management Fix

**File:** `app/Http/Controllers/Admin/AdminUserController.php`

- Uses `$user->getRoleNames()` instead of `$user->roles->pluck('name')` for reading current role. `getRoleNames()` is membership-scoped; `roles` (Spatie relationship) is global.
- `syncRoles()` and `assignRole()` write path fixed by Account overrides to update `TenantMembership.role_id`.

## What Did NOT Need Changes

- **`RoleMiddleware`** (`app/Http/Middleware/RoleMiddleware.php`) — Uses `$user->hasRole()` and `$user->getAllPermissions()` which are overridden on Account. No code change needed.
- **`CheckUserStatus`** (`app/Http/Middleware/CheckUserStatus.php`) — Lines 51, 83 call `$authenticatable->hasRole('admin')` which now checks membership. No code change needed.
- **All 80+ controllers** using `auth()->user()->can('...')` — Gate delegates to `hasPermissionTo()` which is overridden. No code change needed.
- **All policies** — `UserPolicy`, `CustomerOrderPolicy`, `CustomerAddressPolicy`, `BillingPaymentMethodPolicy`. Gate checks go through `hasPermissionTo()` override. No code change needed.
- **All Blade directives** (`@can`, `@role`, `@hasrole`) — Delegate to Gate which uses `hasPermissionTo()`. No code change needed.
- **`config/permission.php`** — `teams` feature remains disabled (`false`). The fix works through override methods, not Spatie's team feature.

## Files Modified

| File | Change |
|------|--------|
| `app/Models/Account.php` | +6 method overrides, +`getCurrentMembership()`, +`hasGlobalRole()`, +helpers |
| `app/Auth/IdentityResolver.php` | Replaced `Account::role()` with membership-join queries in 3 static helpers |
| `app/Auth/CurrentRoleResolver.php` | Added explicit `Account` type-check in `resolveAll()` and `hasRole()` |
| `app/Http/Middleware/HandleInertiaRequests.php` | `tenant_id` now uses `Tenant::getCurrent()?->id` for Account |
| `app/Http/Controllers/Admin/AdminUserController.php` | Use `getRoleNames()` instead of `roles->pluck('name')` |

## Verification Checklist

| Scenario | Expected | Automatic |
|----------|----------|-----------|
| Account = Owner in Tenant A → Tenant B as Customer | No admin permissions in Tenant B. | `hasRole('admin')` → false, `can(...)` → false |
| Account = customer in Tenant B | Only customer permissions. | `getRoleNames()` → `['customer']` |
| Account = SuperAdmin (any tenant) | All permissions everywhere. | `hasGlobalRole('superadmin')` → true → bypass |
| Account = Owner in Tenant A | All permissions in Tenant A. | `is_owner → true` → `hasPermissionTo()` → true |
| User model (Legacy mode) | Unchanged. Spatie global resolution. | `config('identity.use_accounts')` → false → fallback |
| Admin creates user with role | `syncRoles()` updates `TenantMembership.role_id`. | `assignRole()` override |
| Admin edits user role | `getRoleNames()` shows current membership role. | `getRoleNames()` override |
| Route `role:admin` middleware | Membership-scoped check. | `hasRole()` override |
| Route `role:superadmin` middleware | Global check (bypass membership). | `hasGlobalRole()` in override |

## Remaining Work (Next Phase)

- **Admin user management UI**: When an admin changes a user's role via `AdminUserController::update()`, the `syncRoles()` now updates `TenantMembership.role_id`. But the global `model_has_roles` entry remains stale. For Accounts, global `model_has_roles` should be cleaned up on role change to prevent confusion if a fallback path is ever reached.
- **Tenant deletion**: `TenantDeletionService::deleteModelHasRoles()` only cleans up `model_type = User` entries. For Account mode, it should also handle accounts.
- **`TenantBootstrapService::assignOwnerPermissions()`**: Type-hinted for `User` only. If used in Account mode, it would fail.
- **ChatController, TelegramIntegrationController, PromotionService**: Still query `User` model directly. These are deep customer-flow integrations that need separate migration.
