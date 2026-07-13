# Platform Authentication Separation Report

**Sprint**: 6.3.3
**Date**: 2026-07-12
**Scope**: Platform Authentication separation from Tenant Authentication
**Reference**: `docs/platform-identity-design-lock.md`

---

## 1. Files Modified

| File | Change | Impact |
|---|---|---|
| `app/Http/Middleware/IdentifyTenant.php` | Moved SuperAdmin bypass before role loading | Performance: SuperAdmin requests skip unnecessary `roles` query |
| `app/Http/Middleware/CheckUserStatus.php` | Added early SuperAdmin return with proper `/superadmin/login` redirect | Security: SuperAdmin suspended/banned redirects to platform login, not tenant login |
| `app/Http/Middleware/CheckMaintenanceMode.php` | Added explicit SuperAdmin bypass comment | Documentation: Clarifies platform identity bypass |

### Files Verified (No Changes Needed)

| File | Reason |
|---|---|
| `app/Http/Controllers/Auth/AuthenticatedSessionController.php` | Already handles SuperAdmin correctly |
| `app/Http/Controllers/StorefrontLoginController.php` | Already blocks SuperAdmin from tenant login |
| `app/Http/Middleware/HandleInertiaRequests.php` | Already sets tenant=null for SuperAdmin |
| `app/Http/Middleware/EnsureTenantIsActive.php` | Already bypasses for SuperAdmin |
| `app/Http/Middleware/TenantIsValid.php` | Already bypasses for SuperAdmin |
| `app/Http/Middleware/CheckTenantAccess.php` | Already bypasses for SuperAdmin |
| `app/Http/Middleware/CheckStoreLocked.php` | Already bypasses for SuperAdmin |
| `app/Http/Middleware/ValidateTenantBinding.php` | Already bypasses for SuperAdmin |
| `app/Http/Middleware/RoleMiddleware.php` | Uses hasRole() which bypasses for SuperAdmin |
| `app/Auth/LoginRedirectResolver.php` | Already handles SuperAdmin redirects |
| `app/Auth/IdentityProjection.php` | Already skips tenant/membership for SuperAdmin |
| `app/Models/Account.php` | Already returns "Super Admin" for display name |
| `app/Http/Requests/Auth/LoginRequest.php` | Guard-agnostic — works for both platform and tenant |

---

## 2. Authentication Flow

### Platform Login Flow (SuperAdmin)

```
1. SuperAdmin visits /superadmin/login
   └─ Route: superadmin.login
   └─ Controller: AuthenticatedSessionController::create()
   └─ Renders: Auth/Login (guest middleware)

2. SuperAdmin submits credentials
   └─ Controller: AuthenticatedSessionController::store()
   └─ Account::where('email', ...)->first()
   └─ Check: account->isActive()
   └─ Check: account->getCurrentMembership() && !account->isSuperAdmin()
      └─ BLOCKS tenant users from platform login
   └─ LoginRequest::authenticate()
      └─ Auth::guard('accounts')->attempt()
   └─ Session::regenerate()
   └─ Session::forget('current_tenant_slug')  ← clears tenant context
   └─ LoginRedirectResolver::resolveLogin()
      └─ Returns: /superadmin (superadmin.dashboard)

3. SuperAdmin is now authenticated
   └─ Guard: accounts
   └─ Session: no tenant_slug
   └─ Tenant: null (IdentifyTenant bypasses)
```

### Tenant Login Flow (Owner/Staff/Customer)

```
1. User visits /store/{slug}/login or /store/{slug}/admin/login
   └─ Route: storefront.login or storefront.admin.login
   └─ Controller: StorefrontLoginController::create()
   └─ Middleware: storefront (resolves tenant from URL)
   └─ Renders: Storefront/Login

2. User submits credentials
   └─ Controller: StorefrontLoginController::store()
   └─ Check: account->isSuperAdmin() → BLOCKS SuperAdmin from tenant login
   └─ Check: account->isActive()
   └─ LoginRequest::authenticate()
      └─ Auth::guard('accounts')->attempt()
   └─ Session::regenerate()
   └─ LoginRedirectResolver::intended()
      └─ Owner/Admin → /store/{slug}/admin/dashboard
      └─ Customer → /store/{slug}

3. User is now authenticated
   └─ Guard: accounts
   └─ Session: current_tenant_slug = {slug}
   └─ Tenant: resolved by IdentifyTenant middleware
```

---

## 3. Session Flow

### Platform Session (SuperAdmin)

```
Session contents:
  - auth: accounts guard authenticated
  - current_tenant_slug: NULL (cleared on login)
  - No tenant context

Middleware processing:
  1. IdentifyTenant: SuperAdmin bypass → no tenant resolution
  2. CheckUserStatus: SuperAdmin early return → no tenant checks
  3. CheckMaintenanceMode: SuperAdmin bypass → no maintenance block
  4. HandleInertiaRequests: tenant=null, cart=empty, categories=[]
```

### Tenant Session (Owner/Staff/Customer)

```
Session contents:
  - auth: accounts guard authenticated
  - current_tenant_slug: {slug} (set on login)

Middleware processing:
  1. IdentifyTenant: resolves tenant from membership or session
  2. CheckUserStatus: checks account + tenant status
  3. CheckMaintenanceMode: checks tenant maintenance settings
  4. HandleInertiaRequests: tenant={...}, cart=loaded, categories=loaded
```

---

## 4. Redirect Flow

### Login Redirects

| Identity | Login Route | Post-Login Redirect | Guard |
|---|---|---|---|
| **SuperAdmin** | `/superadmin/login` | `/superadmin` | `accounts` |
| **Owner** | `/store/{slug}/admin/login` | `/store/{slug}/admin/dashboard` | `accounts` |
| **Admin/Staff** | `/store/{slug}/admin/login` | `/store/{slug}/admin/dashboard` | `accounts` |
| **Customer** | `/store/{slug}/login` | `/store/{slug}` or intended URL | `accounts` |

### Logout Redirects

| Identity | Logout Source | Post-Logout Redirect |
|---|---|---|
| **SuperAdmin** | `/superadmin/*` | `/superadmin/login` |
| **Owner** | `/store/{slug}/admin/*` | `/store/{slug}/admin/login` |
| **Customer** | `/store/{slug}/*` | `/store/{slug}` |

### Suspension/Ban Redirects

| Identity | Condition | Redirect |
|---|---|---|
| **SuperAdmin** | Account suspended/banned | `/superadmin/login` |
| **Tenant User** | Account suspended/banned | `/login` |
| **Tenant User** | Tenant suspended | `/store/{slug}/admin/suspended` or `/login` |

---

## 5. Middleware Bypass Matrix

| Middleware | SuperAdmin Bypass | Line | Mechanism |
|---|---|---|---|
| `IdentifyTenant` | ✅ | 30-32 | `isSuperAdmin()` → early return |
| `CheckUserStatus` | ✅ | 29-44 | `isSuperAdmin()` → early return with `/superadmin/login` redirect |
| `CheckMaintenanceMode` | ✅ | 47-49 | `isSuperAdmin()` → early return |
| `HandleInertiaRequests` | ✅ | 37, 67-68 | `isSuperAdmin()` → tenant=null |
| `EnsureTenantIsActive` | ✅ | 20-22 | `isSuperAdmin()` → early return |
| `TenantIsValid` | ✅ | 21-23 | `isSuperAdmin()` → early return |
| `CheckTenantAccess` | ✅ | 24-26 | `isSuperAdmin()` → early return |
| `CheckStoreLocked` | ✅ | 20-22 | `isSuperAdmin()` → early return |
| `ValidateTenantBinding` | ✅ | 22-24 | `isSuperAdmin()` → early return |
| `RoleMiddleware` | ✅ | 20-21 | `hasRole()` → SuperAdmin always true |

---

## 6. Controller-Level Guards

### AuthenticatedSessionController (Platform Login)

```php
// Line 55-58: Blocks tenant users from platform login
if ($account->getCurrentMembership() && !$account->isSuperAdmin()) {
    return back()->withErrors([
        'email' => 'Please login through your store URL.',
    ]);
}
```

### StorefrontLoginController (Tenant Login)

```php
// Line 51-55: Blocks SuperAdmin from tenant login
if ($account->isSuperAdmin()) {
    return back()->withErrors([
        'email' => 'Please use the platform login page for super admin access.',
    ]);
}
```

---

## 7. Remaining Risks

### RISK 1: LoginRedirectResolver::resolveTenant() Queries for SuperAdmin

**Level**: Low
**Description**: `LoginRedirectResolver::resolveTenant()` queries TenantMembership and Tenant for Account users. For SuperAdmin, this is unnecessary because `resolveLogin()` returns early with `route('superadmin.dashboard')`.
**Impact**: One extra query on login for SuperAdmin (membership lookup returns null).
**Mitigation**: Could add early SuperAdmin return in `resolveTenant()`, but not critical.

### RISK 2: Dual Guard Support (`web` + `accounts`)

**Level**: Low
**Description**: Both `web` and `accounts` guards are active. The `config('identity.use_accounts')` flag determines which is primary.
**Impact**: Both guards check authentication on every request.
**Mitigation**: Correct behavior — flag controls which guard is used for authentication.

### RISK 3: Legacy User Model Still Present

**Level**: Low
**Description**: `User` model and `web` guard still exist for backward compatibility.
**Impact**: Some code paths branch on `instanceof User` vs `instanceof Account`.
**Mitigation**: Will be removed when `users` table is dropped in Phase 7.

### RISK 4: Session Contains Both user_id and account_id

**Level**: Low
**Description**: Sessions table has both `user_id` and `account_id` columns.
**Impact**: Only one is populated per session based on guard.
**Mitigation**: Correct behavior — guard determines which column is used.

---

## 8. Manual Test Checklist

### Platform Login

```bash
# 1. Visit SuperAdmin login page
GET /superadmin/login
Expected: Login page renders

# 2. Login as SuperAdmin
POST /superadmin/login
  email: admin@shop.com
  password: password
Expected: Redirect to /superadmin

# 3. Verify session has no tenant context
GET /superadmin
Expected: Dashboard renders, no tenant data in session

# 4. Verify SuperAdmin cannot access tenant login
POST /store/default/login
  email: admin@shop.com
  password: password
Expected: Error "Please use the platform login page for super admin access."
```

### Platform Logout

```bash
# 1. Login as SuperAdmin
POST /superadmin/login
  email: admin@shop.com
  password: password

# 2. Logout from SuperAdmin
POST /logout
Expected: Redirect to /superadmin/login

# 3. Verify session destroyed
GET /superadmin
Expected: Redirect to /superadmin/login (not authenticated)
```

### Tenant Login

```bash
# 1. Visit tenant login page
GET /store/default/login
Expected: Login page renders with tenant info

# 2. Login as tenant owner
POST /store/default/login
  email: owner@defaultstore.com
  password: password
Expected: Redirect to /store/default/admin/dashboard

# 3. Verify session has tenant context
GET /store/default/admin/dashboard
Expected: Dashboard renders with tenant data

# 4. Verify tenant user cannot access SuperAdmin
GET /superadmin
Expected: 403 Unauthorized
```

### Tenant Logout

```bash
# 1. Login as tenant owner
POST /store/default/login
  email: owner@defaultstore.com
  password: password

# 2. Logout from tenant
POST /logout
  store_slug: default
Expected: Redirect to /store/default/admin/login

# 3. Verify session destroyed
GET /store/default/admin/dashboard
Expected: Redirect to login (not authenticated)
```

### Suspension/Ban Handling

```bash
# 1. Login as SuperAdmin
POST /superadmin/login
  email: admin@shop.com
  password: password

# 2. Suspend SuperAdmin account (via database)
UPDATE accounts SET status='suspended' WHERE email='admin@shop.com'

# 3. Refresh SuperAdmin page
GET /superadmin
Expected: Redirect to /superadmin/login with error "Your account has been suspended."

# 4. Verify logout
GET /superadmin
Expected: Redirect to /superadmin/login (not authenticated)
```

---

## 9. Design Lock Compliance Matrix

| Design Lock Rule | Implementation | Status |
|---|---|---|
| SuperAdmin authenticates without Tenant context | `IdentifyTenant` bypasses for SuperAdmin | ✅ |
| SuperAdmin never resolves TenantMembership | `IdentifyTenant` returns early, `IdentityProjection` skips membership | ✅ |
| SuperAdmin never resolves Default Store | No Default Store fallback for SuperAdmin | ✅ |
| SuperAdmin never redirects to any Store | `LoginRedirectResolver` returns `/superadmin` | ✅ |
| Platform session contains no tenant context | `current_tenant_slug` cleared on login | ✅ |
| Platform logout destroys session only | `AuthenticatedSessionController::destroy()` invalidates session | ✅ |
| Platform logout redirects to /superadmin/login | `LoginRedirectResolver::resolveLogout()` returns `superadmin.login` | ✅ |
| Owner/Staff/Customer use TenantMembership | `StorefrontLoginController` validates membership | ✅ |
| Owner redirects to /store/{slug}/admin/dashboard | `LoginRedirectResolver::resolveLogin()` returns admin dashboard | ✅ |
| Customer redirects to /store/{slug} | `LoginRedirectResolver::resolveLogin()` returns storefront | ✅ |

---

**END OF SEPARATION REPORT**
