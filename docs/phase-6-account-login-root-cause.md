# Phase 6 — Account Login Root Cause Analysis

## Authentication Trace

| Step | Value |
|------|-------|
| IDENTITY_USE_ACCOUNTS | `true` |
| Selected guard | `accounts` |
| Selected provider | `accounts` |
| Provider model class | `App\Models\Account` |
| Submitted email | `admin@shop.com` |
| Account found? | **no** |
| Password hash exists? | N/A (no Account record) |
| Hash::check() | N/A (no Account record) |
| Auth::guard()->attempt() | `false` |
| Why attempt() failed | `EloquentUserProvider::retrieveByCredentials()` returned `null` — no `Account` record with that email in the `accounts` table |
| Validation exception source | `LoginRequest::authenticate()` line 36 — `throw ValidationException` |

## Debug Evidence

### Auth guard comparison

| Credentials | `web` guard (Legacy mode) | `accounts` guard (Account mode) |
|---|---|---|
| `admin@shop.com` / `password` | ✅ PASS (User exists) | ❌ FAIL (no Account) |
| `myat@gmail.com` / `password` | N/A (no User) | ✅ PASS (Account exists) |

The `web` guard uses `App\Models\User` (table `users`). The `accounts` guard uses `App\Models\Account` (table `accounts`). These are completely different tables.

### Database state after `php artisan db:seed --class=RoleAndPermissionSeeder`

```
=== Users ===
  id=199 email=admin@shop.com status=active roles=superadmin

=== Accounts ===
  id=1   email=myat@gmail.com  status=active roles=admin
```

The `RoleAndPermissionSeeder` creates a `User` (`admin@shop.com`) but **never creates a matching `Account`**. When `IDENTITY_USE_ACCOUNTS=true`, the `accounts` guard queries the `accounts` table, finds nothing for `admin@shop.com`, and `attempt()` returns `false`.

### Other seeders with the same problem

- `UserSeeder` (called from `DemoDataSeeder`) creates 10 `User` records but no `Account` records.

## Root Cause

**Seeders create `User` records but never create matching `Account` records.**

The `RoleAndPermissionSeeder::run()` creates a Super Admin `User` via `User::updateOrCreate()` at line 151, but the corresponding `Account` is never inserted into the `accounts` table. When `IDENTITY_USE_ACCOUNTS=true`, the authentication flow in `LoginRequest::authenticate()` uses `Auth::guard('accounts')`, which delegates to the `accounts` provider (model `App\Models\Account`, table `accounts`). Since no `Account` record exists for that email, `retrieveByCredentials()` returns `null`, `attempt()` returns `false`, and `ValidationException` is thrown with the message `"These credentials do not match our records."`

## Files Modified

### `database/seeders/RoleAndPermissionSeeder.php`

- **Added** `use App\Models\Account;` import
- **Added** lines 164–178: After creating the Super Admin `User`, if `config('identity.use_accounts')` is true, also create/update an `Account` record with the same email, password, and roles.

### `database/seeders/UserSeeder.php`

- **Added** `use App\Models\Account;` import
- **Added** lines 41–54: After creating each customer `User`, if `config('identity.use_accounts')` is true, also create/update an `Account` record with the same email, password, and roles.

## Verification

```bash
# 1. Run the seeder
php artisan db:seed --class=RoleAndPermissionSeeder

# 2. Test login — both accounts now work with the accounts guard
$ php artisan tinker
>>> Auth::guard('accounts')->attempt(['email' => 'admin@shop.com', 'password' => 'password']);
=> true
>>> Auth::guard('accounts')->attempt(['email' => 'myat@gmail.com', 'password' => 'password']);
=> true
```

Both accounts now authenticate successfully via the `accounts` guard. No changes to the authentication logic, controllers, middleware, or configuration were required. The fix is purely a seeder completeness issue.
