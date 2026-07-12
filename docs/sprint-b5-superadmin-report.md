# Sprint B.5 — SuperAdmin Isolation Report

**Goal:** Platform Admin must never become Tenant Admin. SuperAdmin operates exclusively at `/superadmin/*` with no tenant context.

---

## Changes Made

### 1. `app/Http/Middleware/IdentifyTenant.php` — Remove Default Store Fallback

**What changed:** Removed `Tenant::getDefault()` fallback from the tenant resolution chain.

```diff
- $tenant = $this->resolveFromSubdomain($request)
-     ?? $this->resolveFromHeader($request)
-     ?? $this->resolveFromSession($request)
-     ?? Tenant::getDefault();  // ← removed
+ $tenant = $this->resolveFromSubdomain($request)
+     ?? $this->resolveFromHeader($request)
+     ?? $this->resolveFromSession($request);
```

**Why:** The default store (tenant ID = 1) was a migration artifact that gave every platform-level request a tenant context. This meant SuperAdmin requests to `/superadmin/*` would implicitly resolve to store #1, defeating tenant isolation.

**Effect:**
- SuperAdmin early return (line 30) still bypasses all tenant resolution
- Guest visitors to platform URLs now get `null` tenant — no accidental store context
- Authenticated non-SuperAdmin users still resolve their tenant via session slug, subdomain, header, or membership

---

### 2. `app/Http/Middleware/RoleMiddleware.php` — Remove SuperAdmin Bypass

**What changed:** Removed the unconditional SuperAdmin bypass gate that allowed SuperAdmin to pass all `role:admin` middleware checks.

```diff
 public function handle(Request $request, Closure $next, $role)
 {
     // ... auth check ...

-    // SuperAdmin bypasses all role checks
-    if ($user->isSuperAdmin()) {
-        return $next($request);
-    }

     // Exact role name match
     if ($user->hasRole($role)) {
         return $next($request);
     }

     // For admin routes: allow any user who holds permissions via a custom role.
     if ($role === 'admin' && $user->getAllPermissions()->isNotEmpty()) {
         return $next($request);
     }

     abort(403, 'Unauthorized');
 }
```

**Why:** The bypass allowed SuperAdmin to access tenant admin routes (`/store/{slug}/admin/*` and `/admin/*`) without impersonation. A SuperAdmin logging in at a store URL would get admin dashboard access, mixing platform and tenant concerns.

**Effect:**
- SuperAdmin can only reach routes with explicit `role:superadmin` middleware (i.e., `/superadmin/*`)
- To manage a tenant store, SuperAdmin must impersonate a store admin (via `/superadmin/impersonate`)
- Tenant admin routes remain accessible to users with `admin` role or appropriate permissions

**Route protection:**
| Route Group | Middleware | SuperAdmin Access |
|---|---|---|
| `/superadmin/*` (line 485) | `auth:web,accounts`, `role:superadmin` | ✅ Direct |
| `/admin/*` (line ~470) | `auth:web,accounts`, `role:admin`, `tenant.valid` | ❌ Must impersonate |
| `/store/{slug}/admin/*` | `auth:web,accounts`, `role:admin`, `tenant.access` | ❌ Must impersonate |

---

### 3. `app/Http/Controllers/StorefrontLoginController.php` — Block SuperAdmin Login

**What changed:** Added SuperAdmin detection and rejection at the top of both authentication branches (`useAccounts` and legacy).

```php
// useAccounts mode (line 51):
if ($account->isSuperAdmin()) {
    return back()->withErrors([
        'email' => 'Please use the platform login page for super admin access.',
    ])->onlyInput('email');
}

// Legacy mode (line 78):
if ($user->isSuperAdmin()) {
    return back()->withErrors([
        'email' => 'Please use the platform login page for super admin access.',
    ])->onlyInput('email');
}
```

Also simplified the conditional structure — removed redundant `!$user->isSuperAdmin()` guards on account status checks, since SuperAdmin is already rejected before reaching them.

**Why:** Without this guard, a SuperAdmin could log in at a store URL (e.g., `/store/acme/login`). Even though `IdentifyTenant` would return early and `RoleMiddleware` would now reject the request, the login itself could succeed and create a confusing session. The block prevents the login attempt entirely.

**Effect:**
- SuperAdmin email at any store login URL → "Please use the platform login page"
- Merchant/Customer login behavior unchanged
- Redirect to platform login (`/superadmin/login`) is the user's responsibility

---

### 4. `app/Http/Controllers/Auth/AuthenticatedSessionController.php` — Platform Login Isolation

#### 4a. Block tenant users from platform login (Account mode)

**What changed:** Added `getCurrentMembership()` check in the `useAccounts` branch.

```php
if ($account->getCurrentMembership() && !$account->isSuperAdmin()) {
    return back()->withErrors([
        'email' => 'Please login through your store URL.',
    ])->onlyInput('email');
}
```

**Why:** In Account mode, tenant membership is stored on `TenantMembership` rather than `User.tenant_id`. The platform login needed an equivalent guard to reject users who belong to a tenant.

**Effect:**
- Account-mode users with a `TenantMembership` are blocked from platform login
- SuperAdmin (no membership) can still log in
- Customers/Merchants with memberships → "Please login through your store URL"

#### 4b. Clear stale tenant session on platform login

```php
$request->session()->forget('current_tenant_slug');
```

**Why:** After impersonation or a prior tenant session, the session may carry a stale `current_tenant_slug`. If a SuperAdmin then logs in at the platform URL, the stale slug could cause `IdentifyTenant::resolveFromSession()` to set an incorrect tenant context.

**Effect:**
- Platform login always starts with a clean tenant session
- No residual tenant context from impersonation, prior store visits, or session reuse

#### 4c. Legacy mode restructuring

Reorganized the legacy `User` branch to avoid deeply nested `if ($user)` conditionals and ensure status checks are clear and consistent.

---

### 5. `app/Auth/LoginRedirectResolver.php` — No Changes Needed

The resolver already handled SuperAdmin correctly:

| Context | Redirect |
|---|---|
| SuperAdmin login | `route('superadmin.dashboard')` (line 17) |
| SuperAdmin logout | `route('superadmin.login')` via `inferLogoutContext()` (line 170) |
| Impersonation leave | `route('superadmin.dashboard')` (line 126) |
| Fallback logout | `route('superadmin.login')` (line 187) |

---

## Verification Matrix

| Scenario | Expected | Status |
|---|---|---|
| SuperAdmin logs in at `/superadmin/login` | Redirects to `/superadmin`, no tenant context | ✅ |
| SuperAdmin logs in at `/store/acme/login` | "Please use the platform login page" | ✅ |
| SuperAdmin accesses `/admin/dashboard` directly | 403 Forbidden (no `role:admin`) | ✅ |
| SuperAdmin accesses `/superadmin/tenants` | Success (has `role:superadmin`) | ✅ |
| Merchant logs in at `/store/acme/admin/login` | Redirects to `/store/acme/admin/dashboard` | ✅ |
| Merchant logs in at `/superadmin/login` | "Please login through your store URL" | ✅ |
| Customer logs in at `/store/acme/login` | Redirects to `/store/acme` | ✅ |
| Customer logs in at `/superadmin/login` | "Please login through your store URL" | ✅ |
| Platform login with stale `current_tenant_slug` | Slug cleared, no tenant context set | ✅ |
| Store login with stale `current_tenant_slug` | Slug preserved, tenant resolved correctly | ✅ |
| SuperAdmin logout | Redirects to `/superadmin/login` | ✅ |
| Merchant logout from store | Redirects to store login | ✅ |

---

## Files Modified

| File | Changes |
|---|---|
| `app/Http/Middleware/IdentifyTenant.php` | Removed `Tenant::getDefault()` fallback from tenant resolution |
| `app/Http/Middleware/RoleMiddleware.php` | Removed SuperAdmin bypass for `role:admin` routes |
| `app/Http/Controllers/StorefrontLoginController.php` | Added SuperAdmin block on store login; simplified conditionals |
| `app/Http/Controllers/Auth/AuthenticatedSessionController.php` | Added membership check (Account mode); cleared stale tenant session; restructured legacy branch |

---

## Remaining Concerns

None. The three isolation layers are complete:

1. **Middleware layer** — `IdentifyTenant` refuses to set tenant for platform requests; `RoleMiddleware` refuses SuperAdmin on tenant admin routes
2. **Controller layer** — Store login blocks SuperAdmin; platform login blocks tenant users
3. **Session layer** — Stale tenant context is cleared on platform login

SuperAdmin is now fully isolated at `/superadmin/*` and must use impersonation to interact with tenant stores.
