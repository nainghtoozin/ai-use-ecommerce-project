# Role Permission 403 Audit

## Bug
A user assigned the "Manager" role with all permissions receives 403 on `/store/{slug}/admin/units`.

## Root Cause
**Classification: D — Hardcoded role restriction**

`app/Http/Middleware/RoleMiddleware.php:23` calls `$user->hasRole('admin')` which checks for the **exact role name** `"admin"`. A user with role `"Manager"` does not match, so `hasRole('admin')` returns `false`, triggering `abort(403)` on line 24.

### Execution flow:
1. Route `/store/{slug}/admin/units` applies middleware `role:admin` (defined at `routes/storefront-admin.php:54`)
2. `RoleMiddleware::handle()` is invoked with `$role = 'admin'`
3. Line 19: `$role === 'admin' && $user->hasRole('superadmin')` → `false` (user is not superadmin)
4. Line 23: `!$user->hasRole('admin')` → `!false` → `true` (user has role "Manager", not "admin")
5. Line 24: `abort(403, 'Unauthorized')` executes

Permission checks (`->can()`, Gate, Policy) are never reached because the `role:admin` middleware blocks the request **before** the controller executes.

## Affected Files
- `app/Http/Middleware/RoleMiddleware.php:23-24` — the 403 originates here
- `routes/storefront-admin.php:54` — applies `role:admin` middleware to all storefront admin routes
- `routes/web.php:263` — applies `role:admin` middleware to all legacy admin routes
- `app/Models/User.php:55` — `isAdmin()` checks `hasRole('admin')` (same strict match)

## Affected Routes
ALL routes under these middleware groups will 403 for any non-`admin` role name:
- `/store/{slug}/admin/*` (all ~250 routes)
- `/admin/*` (all ~200 routes)

## Detailed Findings

### 1. RoleMiddleware (`app/Http/Middleware/RoleMiddleware.php:23`)
```php
if (!$user->hasRole($role)) {     // exact role name match
    abort(403, 'Unauthorized');
}
```
Uses Spatie's `hasRole()` which matches the **role name string exactly**. The `Manager` role name does not equal `admin`.

### 2. Controller (`AdminUnitController.php`)
**No** authorization checks inside the controller. `AdminUnitController` is clean — no `can()`, no `hasRole()`, no `Gate`, no `Policy`. The 403 is entirely from the middleware layer.

### 3. Permission Names
`units.view` exists in the database and is assigned to the Manager role. Permission names are correct.

### 4. Route Permissions
No individual permission check is applied to the unit routes. The only authorization layer is `role:admin` middleware.

### 5. Tenant Isolation
Not a factor. The 403 occurs in `RoleMiddleware` which runs **before** any tenant middleware (`tenant.valid`, `tenant.access`, `tenant.binding`). The tenant context is irrelevant to this bug.

## Why Having All Permissions Does Not Help
The `role:admin` middleware checks **role name**, not permissions. Spatie's `hasRole('admin')` checks if the user's assigned role's `name` column equals `"admin"`. A user with role `"Manager"` who has every permission in the system still fails `hasRole('admin')`.

## Recommended Fix
Modify `RoleMiddleware` to use a more flexible authorization check instead of an exact role name match:

**Option A (Recommended):** Check `isAdmin()` instead of exact role name:
```php
if (!$user->isAdmin()) {
    abort(403, 'Unauthorized');
}
```
This works because `User::isAdmin()` already checks for both `admin` and `superadmin` roles (line 55: `$this->hasRole('admin') || $this->hasRole('superadmin')`). More maintainable long-term.

**Option B:** Accept multiple role names via pipe syntax (`role:admin|manager`):
Parse the `$role` parameter for `|` separators and check `$user->hasAnyRole($roles)`.

**Option C:** Change middleware to check for permissions instead:
```php
// Instead of role name, check for a permission that all admins must have
if (!$user->can('dashboard.view')) {
    abort(403, 'Unauthorized');
}
```

## Risk Level
**HIGH** — Affects ALL admin routes (storefront admin + legacy admin). Any custom role name (Manager, Staff, Editor, etc.) will fail the `role:admin` middleware even with all permissions assigned.

## Additional Hardcoded `hasRole('admin')` Checks
These checks would also fail for a "Manager" user if reached:
| File | Line | Purpose |
|---|---|---|
| `app/Models/User.php` | 55 | `isAdmin()` method definition |
| `app/Http/Controllers/Admin/AdminProductController.php` | 646, 700, 727 | Product operations |
| `app/Http/Controllers/Admin/AdminUserController.php` | 276 | User management |
| `app/Http/Middleware/CheckUserStatus.php` | 35 | Status checks |
| `app/Http/Controllers/SuperAdmin/ImpersonationController.php` | 45 | Impersonation target |
