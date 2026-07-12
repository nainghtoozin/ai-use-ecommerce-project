# Fix: Wrong Spatie Model Import in Account.php

## Root Cause

`app/Models/Account.php` imported `use App\Models\Permission;` but no `App\Models\Permission` model exists in this project. The project uses Spatie's permission package directly: `Spatie\Permission\Models\Permission`.

The `Permission::all()` calls in `getAllPermissions()` resolved to the non-existent `App\Models\Permission`, causing:

```
Class "App\Models\Permission" not found
App\Models\Account::getAllPermissions()
app\Http\Middleware\HandleInertiaRequests.php:share()
```

## Incorrect Import

| File | Line | Incorrect Import |
|------|------|------------------|
| `app/Models/Account.php` | 8 | `use App\Models\Permission;` |

## Note on Role

`App\Models\Role` exists (extends `Spatie\Permission\Models\Role`) and is correctly imported at line 7. All `Role::where()` / `Role::find()` references in Account.php resolve to the correct custom model.

## Files Modified

**`app/Models/Account.php`** — 1 change:

```
- use App\Models\Permission;
+ use Spatie\Permission\Models\Permission;
```

Line 8: Changed the import to use the Spatie-provided model.

## Affected Code Paths (No Other Changes Needed)

- `Account::getAllPermissions()` (lines 444, 453) — now calls `Spatie\Permission\Models\Permission::all()`
- `HandleInertiaRequests::share()` calls `$authenticatable->getAllPermissions()` — fixed transitively
- `RoleMiddleware` calls `$user->getAllPermissions()` — fixed transitively
- `IdentityResolver`, `CurrentRoleResolver`, `AuthorizationResolver` — no Permission imports needed; these use Spatie's contracts (`\Spatie\Permission\Contracts\Permission`) which are already fully qualified at usage sites

## Validation

- `php -l app/Models/Account.php` — no syntax errors
- `Permission::class` resolves to `Spatie\Permission\Models\Permission`
- `Role::class` resolves to `App\Models\Role` (still correct)
- Merchant dashboard no longer throws `Class "App\Models\Permission" not found`
- `getAllPermissions()` returns the full Spatie permission set for SuperAdmin and Owner
- `getRoleNames()` unaffected (uses membership path)
- Sidebar renders without runtime exception
- Legacy Mode and Account Mode both work
