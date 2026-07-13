# Platform Authentication Recovery Report

**Sprint**: 6.3.3-R
**Date**: 2026-07-13
**Scope**: Recovery — fix compile errors, seeder failures, and SuperAdmin redirect bug
**Reference**: `docs/platform-identity-design-lock.md`, `docs/platform-auth-separation-report.md`

---

## 1. Files Modified

| File | Change | Reason |
|---|---|---|
| `database/seeders/MembershipSeeder.php` | Fixed string interpolation `{$owners->count() - 1}` | ParseError: PHP can't use `-` inside `{$...}` in double-quoted strings |
| `database/seeders/CategorySeeder.php` | Removed `is_active` from `firstOrCreate` | Column doesn't exist in `categories` table |
| `app/Models/Account.php` | Fixed `assignRole()` to delegate global roles to Spatie | Global roles (superadmin, `tenant_id=NULL`) were silently dropped |

---

## 2. Compile Errors Fixed

### Error 1: ParseError in MembershipSeeder

```
ParseError: syntax error, unexpected token "-"

at database/seeders/MembershipSeeder.php:211
$this->command->info("    Kept owner #{$keepOwner->account_id}, cleared {$owners->count() - 1} duplicate(s).");
```

**Root cause**: PHP string interpolation `{$owners->count() - 1}` — the `-` is ambiguous in `{$...}` context (interpreted as variable variable prefix).

**Fix**: Extract to variable before interpolation.

```php
$cleared = $owners->count() - 1;
$this->command->info("    Kept owner #{$keepOwner->account_id}, cleared {$cleared} duplicate(s).");
```

### Error 2: Column not found in CategorySeeder

```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'is_active' in 'field list'
```

**Root cause**: `categories` table has no `is_active` column. Seeder was passing it to `firstOrCreate`.

**Fix**: Removed `is_active` from the create attributes.

### Error 3: SuperAdmin role not assigned (silent failure)

**Root cause**: `Account::assignRole()` override at line 561-563:

```php
$tenantId = $roleModel->tenant_id ?? Tenant::getCurrent()?->id;
if (!$tenantId) {
    return $this;  // ← silently returns without assigning
}
```

For the `superadmin` role (`tenant_id=NULL`), `$tenantId` is null, so the method returned early without calling Spatie's `assignRole()`. The superadmin role was never written to `model_has_roles`.

**Fix**: Added global role check before tenant-scoped logic:

```php
// Global roles (tenant_id = NULL) → delegate to Spatie model_has_roles
if (is_null($roleModel->tenant_id)) {
    return $this->assignSpatieRole($roleModel);
}
```

---

## 3. Seeder Fixes

### MembershipSeeder

**Line 211**: Changed from:
```php
$this->command->info("    Kept owner #{$keepOwner->account_id}, cleared {$owners->count() - 1} duplicate(s).");
```
To:
```php
$cleared = $owners->count() - 1;
$this->command->info("    Kept owner #{$keepOwner->account_id}, cleared {$cleared} duplicate(s).");
```

### CategorySeeder

Removed `'is_active' => true` from `firstOrCreate` attributes since the column doesn't exist.

### Account::assignRole()

Added global role delegation before tenant-scoped logic. Global roles (`tenant_id=NULL`) now use `assignSpatieRole()` which writes to `model_has_roles`.

---

## 4. Legacy Cleanup

No broken traits or legacy helpers found. The `HasUser` trait referenced in the task description does not exist in the codebase. All existing traits (`TenantAware`, `SyncsIdentity`, `LogsActivity`, `HasTenantScope`) are functional.

---

## 5. Runtime Trace

### SuperAdmin Login — Complete Call Chain

```
1. Browser: POST /login (Login.jsx posts to /login, not /superadmin/login)
   │
2. LoginRequest::authenticate()
   │  Guard: accounts (config('identity.use_accounts') = true)
   │  Auth::guard('accounts')->attempt(email, password)
   │  Result: authenticated
   │
3. AuthenticatedSessionController::store()
   │
   ├─ Line 33: $useAccounts = true
   ├─ Line 34: $account = Account::where('email', 'admin@shop.com')->first()
   ├─ Line 37: $account->isActive() → true (status = 'active')
   ├─ Line 55: $account->getCurrentMembership() → null (no memberships)
   │           $account->isSuperAdmin() → true (model_has_roles has superadmin)
   │           Condition: null && !true → false → DOES NOT BLOCK
   │
   ├─ Line 97: $request->authenticate()
   ├─ Line 101: $request->session()->regenerate()
   ├─ Line 103: $authenticatable = Auth::guard('accounts')->user()
   ├─ Line 105: $request->session()->forget('current_tenant_slug')
   │
   └─ Line 115: redirect()->to(LoginRedirectResolver::resolveLogin($authenticatable))
      │
      └─ LoginRedirectResolver::resolveLogin()
         ├─ Line 17: $authenticatable->isSuperAdmin() → true
         └─ Line 18: return route('superadmin.dashboard')
                     → /superadmin
      │
      └─ REDIRECT: 302 → /superadmin
   │
4. Browser: GET /superadmin
   │
   ├─ Web middleware group:
   │  ├─ IdentifyTenant::handle()
   │  │  ├─ Auth::guard('accounts')->check() → true
   │  │  ├─ Auth::shouldUse('accounts')
   │  │  ├─ auth()->user() → Account (admin@shop.com)
   │  │  ├─ $authenticatable->isSuperAdmin() → true
   │  │  └─ return $next($request) ← BYPASS, no tenant resolution
   │  │
   │  ├─ HandleInertiaRequests::share()
   │  │  ├─ $isSuperAdmin = true
   │  │  ├─ $tenant = null (line 68)
   │  │  └─ Shares: auth.user with tenant_id=null, tenant=null
   │  │
   │  └─ CheckUserStatus::handle()
   │     ├─ $authenticatable->isSuperAdmin() → true
   │     └─ return $next($request) ← early return, no tenant checks
   │
   ├─ Route middleware: auth:web,accounts → passes
   ├─ Route middleware: role:superadmin → hasRole('superadmin') → true
   │
   └─ SuperAdmin\DashboardController::index()
      └─ Renders SuperAdmin dashboard
```

### Tenant Owner Login — Complete Call Chain

```
1. Browser: POST /store/default/admin/login (StorefrontLoginController)
   │
2. StorefrontLoginController::store()
   ├─ Tenant::getCurrent() → Default Store (from storefront middleware)
   ├─ Account::where('email', 'owner@defaultstore.com')->first()
   ├─ $account->isSuperAdmin() → false → DOES NOT BLOCK
   ├─ $account->isActive() → true
   │
   ├─ LoginRequest::authenticate()
   ├─ session()->regenerate()
   │
   └─ LoginRedirectResolver::intended($authenticatable, $tenant)
      └─ resolveLogin($authenticatable, $tenant)
         ├─ isSuperAdmin() → false
         ├─ $resolvedTenant = $tenant (Default Store)
         ├─ isAdmin() → true (owner implies admin)
         └─ return route('storefront.admin.dashboard', ['store_slug' => 'default'])
            → /store/default/admin/dashboard
   │
3. Browser: GET /store/default/admin/dashboard
   └─ Renders tenant admin dashboard
```

---

## 6. Root Cause

### Problem 1: Seeder Failure

Two independent issues:

1. **PHP string interpolation bug**: `{$owners->count() - 1}` — the `-` operator is ambiguous inside `{$...}` in double-quoted strings. PHP interprets it as a variable variable prefix.

2. **Missing column**: `categories` table has no `is_active` column. The seeder was passing it without checking the schema.

### Problem 2: SuperAdmin Redirect to /store/default

**Root cause**: `Account::assignRole()` silently failed for global roles.

The method's tenant-scoped logic at line 561-563:
```php
$tenantId = $roleModel->tenant_id ?? Tenant::getCurrent()?->id;
if (!$tenantId) {
    return $this;  // ← silent failure
}
```

For the `superadmin` role (`tenant_id=NULL`), `$tenantId` was null, so the method returned early. The superadmin role was never written to `model_has_roles`.

**Consequence chain**:
1. `model_has_roles` table had no entry for the SuperAdmin account
2. `Account::isSuperAdmin()` → `hasGlobalRole('superadmin')` → queried `model_has_roles` → returned false
3. `IdentifyTenant::handle()` → `isSuperAdmin()` returned false → tried to resolve tenant
4. `Account::getCurrentMembership()` → returned null (no memberships)
5. Tenant resolution fell through to `resolveFromSession()` → found `current_tenant_slug` = 'default' from previous session
6. Tenant was set to Default Store
7. `HandleInertiaRequests` shared tenant data with frontend
8. Frontend redirected to `/store/default`

---

## 7. Redirect Fix

The fix was in `Account::assignRole()` — adding global role delegation before tenant-scoped logic:

```php
// Global roles (tenant_id = NULL) → delegate to Spatie model_has_roles
if (is_null($roleModel->tenant_id)) {
    return $this->assignSpatieRole($roleModel);
}
```

This ensures:
- Global roles (superadmin) → stored in `model_has_roles` table
- Tenant-scoped roles (admin, staff, customer) → stored in `TenantMembership.role_id`

---

## 8. Validation Results

| # | Test | Result |
|---|---|---|
| 1 | `php artisan migrate:fresh --seed` | ✅ Passes without error |
| 2 | Platform Login → /superadmin | ✅ `isSuperAdmin()=true`, redirects to `/superadmin` |
| 3 | Platform Logout → /superadmin/login | ✅ `resolveLogout()` returns `superadmin.login` route |
| 4 | Platform session contains NO tenant info | ✅ `current_tenant_slug` cleared, `IdentifyTenant` bypasses |
| 5 | SuperAdmin has NO TenantMembership | ✅ `memberships()->count() = 0` |
| 6 | SuperAdmin cannot access /store/* | ✅ `StorefrontLoginController` blocks SuperAdmin |
| 7 | Tenant Owner logs in normally | ✅ Redirects to `/store/default/admin/dashboard` |
| 8 | Customer logs in normally | ✅ Redirects to `/store/default` |

### Verification Commands

```bash
# Verify seeder passes
php artisan migrate:fresh --seed

# Verify SuperAdmin identity
php artisan tinker
> App\Models\Account::where('email', 'admin@shop.com')->first()->isSuperAdmin()
=> true
> App\Models\Account::where('email', 'admin@shop.com')->first()->memberships()->count()
=> 0
```

---

## 9. Remaining Risks

### RISK 1: Login.jsx Hardcodes POST URL

**Level**: Low
**Description**: `Login.jsx` line 15 posts to `/login` regardless of whether the user is on `/superadmin/login` or `/login`.
**Impact**: Works correctly because `AuthenticatedSessionController::store()` handles both paths. But the URL in the browser bar shows `/login` briefly during the POST.
**Mitigation**: Could update Login.jsx to post to `window.location.pathname`, but this is a UI change (out of scope).

### RISK 2: Session Residue from Previous Logins

**Level**: Low
**Description**: If a user logs in as tenant user, logs out, then logs in as SuperAdmin, the session might still have `current_tenant_slug` from the previous session.
**Impact**: The controller clears `current_tenant_slug` on line 105, and `IdentifyTenant` bypasses for SuperAdmin. No impact.
**Mitigation**: Already handled.

### RISK 3: Legacy User Model AssignRole

**Level**: Low
**Description**: `User::assignRole()` uses standard Spatie (not overridden). The User model's `isSuperAdmin()` uses `hasRole()` which checks `model_has_roles`. This works correctly.
**Impact**: None — User model is legacy and maintained for backward compatibility.
**Mitigation**: Will be removed when `users` table is dropped.

---

## 10. Manual Test Checklist

### SuperAdmin Login

```bash
# 1. Start server
php artisan serve

# 2. Open browser: http://localhost:8000/superadmin/login

# 3. Login:
#    Email: admin@shop.com
#    Password: password

# 4. Expected: Redirect to /superadmin (SuperAdmin dashboard)

# 5. Verify: No tenant data in page (no store name, no tenant sidebar)
```

### SuperAdmin Logout

```bash
# 1. From SuperAdmin dashboard, click logout

# 2. Expected: Redirect to /superadmin/login

# 3. Verify: Can access /superadmin/login (not redirected to /login)
```

### Tenant Owner Login

```bash
# 1. Open browser: http://localhost:8000/store/default/admin/login

# 2. Login:
#    Email: owner@defaultstore.com
#    Password: password

# 3. Expected: Redirect to /store/default/admin/dashboard

# 4. Verify: Tenant data visible (store name, tenant sidebar)
```

### Cross-Access Block

```bash
# 1. Try SuperAdmin on tenant login:
#    POST /store/default/login
#    Email: admin@shop.com
#    Password: password

# 2. Expected: Error "Please use the platform login page for super admin access."

# 3. Try tenant user on platform login:
#    POST /login
#    Email: owner@defaultstore.com
#    Password: password

# 4. Expected: Error "Please login through your store URL."
```

---

**END OF RECOVERY REPORT**
