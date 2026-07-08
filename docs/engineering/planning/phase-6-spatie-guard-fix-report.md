# Phase 6 — Spatie GuardDoesNotMatch Fix Report

## Error

```
Spatie\Permission\Exceptions\GuardDoesNotMatch
The given role or permission should use guard `accounts` instead of `web`
```

Occurred in `TenantBootstrapService::assignOwnerRole()` when calling `$account->assignRole($role)`.

## Root Cause

Spatie Permission v6 resolves the guard name for a model through `Guard::getNames()`. When neither a `guardName()` method nor a `$guard_name` class property exists on the model, it falls through to `getConfigAuthGuards()`, which inspects all configured auth guards to find one whose **provider model** matches the model class.

```php
// vendor/spatie/laravel-permission/src/Guard.php

// Step 1-3: check guardName() method, guard_name attribute, guard_name property
if ($guardName) {
    return collect($guardName);
}

// Step 4: match auth config providers to model class (THE BUG)
return self::getConfigAuthGuards($class);
```

Since the `accounts` auth guard has `provider => accounts` → `model => App\Models\Account`, Spatie resolves the Account model's guard as `'accounts'`. Meanwhile, all Spatie roles were created via `TenantBootstrapService::createRoles()` with `guard_name = 'web'`, producing the mismatch.

### Resolution path for Account (before fix)

| Step | Check | Result |
|------|-------|--------|
| 1 | `method_exists($account, 'guardName')` | false |
| 2 | `$account->getAttributeValue('guard_name')` | null (no DB column) |
| 3 | `(new ReflectionClass)->getDefaultProperties()['guard_name']` | null (no property) |
| 4 | `getConfigAuthGuards(Account::class)` | `['accounts']` → **BUG** |

## Fix

**File:** `app/Models/Account.php:20`

Added `protected $guard_name = 'web';` property to the Account model.

This causes Spatie's reflection check (step 3) to return `'web'`, short-circuiting before the auth config lookup. The Account model now resolves to the `web` guard for all Spatie operations (`assignRole`, `hasRole`, `hasPermissionTo`, `can`, etc.).

### Resolution path for Account (after fix)

| Step | Check | Result |
|------|-------|--------|
| 1 | `method_exists($account, 'guardName')` | false |
| 2 | `$account->getAttributeValue('guard_name')` | null (no DB column) |
| 3 | `(new ReflectionClass)->getDefaultProperties()['guard_name']` | `'web'` ← **MATCH** |
| 4 | (not reached) | — |

## Architecture Alignment

| Principle | Status |
|-----------|--------|
| Spatie remains the ONLY authorization system | ✓ — No new authorization system introduced |
| Do NOT duplicate roles/permissions | ✓ — Single set with `guard_name='web'` is shared |
| Account authentication via `accounts` guard | ✓ — Auth continues using `Auth::guard('accounts')` |
| Account authorization via Spatie `web` guard | ✓ — Roles/permissions checked against `guard_name='web'` |
| No schema changes | ✓ — Single property addition, no migration |

## Validation

| Command | Result |
|---------|--------|
| `php artisan optimize` | Config, events, routes, views cached successfully |
| `php artisan about` | Spatie Permissions v6.25.0 detected |
| `php artisan route:list` | 471 routes registered |
