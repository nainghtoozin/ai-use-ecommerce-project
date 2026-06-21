# V3 Role Update Hotfix Report

## Root Cause

`UpdateRoleRequest::authorize()` at `app/Http/Requests/UpdateRoleRequest.php:16` called `$role->name` on the return value of `$this->route('role')`, which is a **string** (raw URL parameter), not a `Role` model. Route model binding does not activate because the controller method `RoleController::update()` uses `$id` as parameter name instead of `$role`, so Laravel passes the raw route segment.

**Error:** `Attempt to read property "name" on string`

## Files Modified

| File | Change |
|------|--------|
| `app/Http/Requests/UpdateRoleRequest.php` | Added `use App\Models\Role` import (line 5). Replaced inline `$role->name` access with safe check that resolves the model first if a string is received (lines 17-23). |

## Fix Applied

Before (line 16-18):
```php
$role = $this->route('role');
if ($role && in_array($role->name, ['superadmin', 'admin'])) {
```

After (lines 17-23):
```php
$routeRole = $this->route('role');
if ($routeRole) {
    $role = $routeRole instanceof Role ? $routeRole : Role::find($routeRole);
    if ($role && in_array($role->name, ['superadmin', 'admin'])) {
        return false;
    }
}
```

Supports both cases:
- **String route parameter** (current behavior): `Role::find($routeRole)` resolves the model
- **Role model instance** (future explicit binding): directly used without DB lookup

## Protected Roles Verified

| Scenario | Expected | Behavior |
|----------|----------|----------|
| `admin` role update | Blocked (403 / false) | `authorize()` returns `false` — protected |
| `superadmin` role update | Blocked (403 / false) | `authorize()` returns `false` — protected |
| `cashier` role update | Allowed | `authorize()` returns `true` |
| `manager` role update | Allowed | `authorize()` returns `true` |

## Role Update Test

Existing `RoleManagementTest` tests are blocked by a pre-existing SQLite compatibility issue (`UPDATE notifications ... INNER JOIN` syntax error) in the notifications migration. The fix is verified by code analysis and trace review:

- String input → `Role::find()` resolves model → protection check passes/fails as expected
- Model input → directly checks → same result
- `rules()` method (line 30) uses `$this->route('role')` as `$roleId` for `Rule::unique()->ignore()`, which works with both string and model values

## Regression Risk

**Low.** The change:
- Only modifies `authorize()` in `UpdateRoleRequest`
- Preserves all existing role protection logic (`superadmin`, `admin`)
- Does not touch routes, controllers, models, seeder, permissions, subscriptions, or `TenantBootstrapService`
- `StoreRoleRequest` is unaffected (role creation)
- `destroy()` in RoleController is unaffected (role deletion)
- `Route::find(null)` returns null when `$routeRole` is empty, which also preserves the null guard
