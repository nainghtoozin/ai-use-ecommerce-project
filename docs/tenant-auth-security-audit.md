# Tenant-Aware Authentication & Access Control Security Audit

**Date:** 2026-06-09  
**Scope:** Auth routes, logout flows, redirect logic, session handling, access control  
**Methodology:** Code review (no code modification)  

---

## A. Authentication Routes

### 1. `/login` (legacy, guest middleware)
**File:** `routes/auth.php:20-23` → `app/Http/Controllers/Auth/AuthenticatedSessionController.php`

**Current Behavior:**
- GET renders `Auth/Login` (Inertia)
- POST via `AuthenticatedSessionController::store()` — authenticates ANY user with valid credentials
- No tenant-scoping validation on the login endpoint itself

**Vulnerabilities:**

| # | Issue | Severity | Details |
|---|-------|----------|---------|
| **V1** | **Root login allows tenant user authentication** | **HIGH** | `AuthenticatedSessionController::store()` (`AuthenticatedSessionController.php:28`) does not validate that the authenticating user belongs to any specific tenant. A tenant admin or customer with valid credentials can authenticate through the legacy `/login` without any tenant context. |
| **V2** | **Admin redirect uses `Tenant::getCurrent()` which may resolve to `default`** | **MEDIUM** | After login, if `$user->isAdmin()`, the controller calls `Tenant::getCurrent()` (`AuthenticatedSessionController.php:69`). If `IdentifyTenant` middleware set the default tenant (slug `default`), the redirect goes to `/store/default/admin/dashboard` which is a 404 (no `default` tenant has this route). The user is left on a broken page. |
| **V3** | **No tenant-cross-check on admin login redirect** | **HIGH** | `AuthenticatedSessionController::store()` line 69-73: The tenant used for redirect comes from `Tenant::getCurrent()`, which may be set via subdomain, header, session, or default — NOT from the user's own `tenant_id`. A tenant admin logging in from the root domain could be redirected to a *different* tenant's admin dashboard. |

### 2. `/register` (legacy, guest middleware)
**File:** `routes/auth.php:15-18` → `app/Http/Controllers/Auth/RegisteredUserController.php`

**Current Behavior:**
- GET renders `Storefront/Register` only if `Tenant::getCurrent()` exists (line 26), otherwise redirects to `/login`
- POST requires tenant context to register

**Vulnerability:**

| # | Issue | Severity | Details |
|---|-------|----------|---------|
| **V4** | **Root register redirect bypass creates broken flow** | **LOW** | Lines 26-29: If no tenant is detected, redirects to `/login` with flash message. The registration *create* route doesn't render the legacy `Auth/Register` page — it redirects. A user visiting `/register` from root domain who has no tenant context gets bounced to login. The *intended* client registration flow is unclear. |

### 3. `/store/{slug}/login` (storefront, storefront middleware)
**File:** `routes/web.php:107-108` → `app/Http/Controllers/StorefrontLoginController.php`

**Current Behavior:**
- Tenant-scoped via `storefront` middleware
- `StorefrontLoginController::store()` verifies `$user->tenant_id !== null && $user->tenant_id !== $tenant->id` (line 69)
- Auto-assigns `tenant_id` for legacy users with null tenant_id (line 76-78)

**Vulnerabilities:**

| # | Issue | Severity | Details |
|---|-------|----------|---------|
| **V5** | **Auto-assign creates permanent cross-tenant binding** | **MEDIUM** | Line 76-78: If a user with `tenant_id === null` logs in via Store A, they become permanently bound to Store A. If they originally registered via `/register` (which now requires tenant context), this is unlikely, but any admin user created before tenant support will have `tenant_id === null` and could be auto-assigned to whichever store they first log into. |
| **V6** | **SuperAdmin can authenticate from storefront login** | **LOW** | `StorefrontLoginController::store()` line 95-97: After login, if `$user->isAdmin()` (which returns true for SuperAdmin), redirects to `storefront.admin.dashboard`. A SuperAdmin logging in from `/store/acme/login` would reach `/store/acme/admin/dashboard` — the tenant's admin dashboard, not the SuperAdmin dashboard. This is misleading but not a security violation since SuperAdmin can access any tenant. |

### 4. `/store/{slug}/register` (storefront, storefront middleware)
**File:** `routes/web.php:103-104` → `app/Http/Controllers/Auth/RegisteredUserController.php`

**Current Behavior:**
- Requires tenant context (line 48-51)
- Assigns `tenant_id` from current tenant (line 64)
- Registers user with `customer` role (line 67-82)

**No vulnerabilities found** — properly scoped to tenant.

### 5. `/store/{slug}/admin/login` — **MISSING ROUTE** (CRITICAL VULNERABILITY)

| # | Issue | Severity | Details |
|---|-------|----------|---------|
| **V7** | **Store admin login route does NOT exist** | **CRITICAL** | The `AuthenticatedSessionController::destroy()` logout method redirects admin context to `/store/{$storeSlug}/admin/login` (line 118). **No route handles this URL pattern.** The `admin_redirect()` helper and `adminUrl()` utility may produce links to `/store/{slug}/admin/login` which return **404 Not Found**. |

### 6. `/superadmin/login` — **MISSING ROUTE** (CRITICAL VULNERABILITY)

| # | Issue | Severity | Details |
|---|-------|----------|---------|
| **V8** | **SuperAdmin login route does NOT exist** | **CRITICAL** | The `AuthenticatedSessionController::destroy()` logout method redirects superadmin context to `/superadmin/login` (lines 117, 127). **No route handles `/superadmin/login`.** Any SuperAdmin who logs out will hit a **404 Not Found** page. |

---

## B. Logout Routes

### 1. `POST /logout`
**File:** `routes/auth.php:57-58` → `app/Http/Controllers/Auth/AuthenticatedSessionController::destroy()`

**Current Behavior:**
- Reads `context` and `store_slug` from POST body
- Falls back to referrer header parsing, then user role
- Destroys session, then redirects based on context
- Frontend sends: `router.post('/logout', { context: 'storefront' | 'admin' | '', store_slug: slug })`

**Vulnerabilities:**

| # | Issue | Severity | Details |
|---|-------|----------|---------|
| **V9** | **Admin logout → 404 Not Found** | **CRITICAL** | When `context` is `'admin'` with valid `store_slug`, redirects to `/store/{slug}/admin/login` (line 118). **No route handles this URL.** |
| **V10** | **Admin logout (no store_slug) → 404 Not Found** | **CRITICAL** | When `context` is `'admin'` without `store_slug` (line 118 fallback), redirects to `/admin/login`. **No route handles `/admin/login`.** |
| **V11** | **SuperAdmin logout → 404 Not Found** | **CRITICAL** | When `context` is `'superadmin'` (line 117) or fallback for SuperAdmin (line 127), redirects to `/superadmin/login`. **No route handles this URL.** |
| **V12** | **Referrer-based context detection is unreliable** | **MEDIUM** | Lines 100-109: Falls back to parsing `Referer` header. If the referrer is missing (privacy tools, direct navigation, Incognito) or spoofed, context detection fails and falls back to `fallbackLogoutRedirect()`. |
| **V13** | **Session invalidated before redirect** | **INFO** | Lines 112-114: Session is invalidated and token regenerated before the redirect. This is correct but means any flash data in the session is lost. The current approach doesn't use `->with()` for these redirects. |
| **V14** | **AppLayout sends blank store_slug** | **MEDIUM** | `AppLayout.jsx:54`: `router.post('/logout', { context: auth?.user?.is_admin ? 'admin' : '', store_slug: '' })` — sends empty string for `store_slug`. This falls through to line 118's fallback `redirect('/admin/login')` which is a 404. |

### 2. Storefront Logout (ShopNavbar)
**File:** `resources/js/Components/ShopNavbar.jsx:73`

**Current Behavior:**
- `router.post('/logout', { context: storeSlug ? 'storefront' : '', store_slug: storeSlug })`
- Matches `'storefront'` context → redirects to `/store/{slug}` or `/`

**Vulnerability:**

| # | Issue | Severity | Details |
|---|-------|----------|---------|
| **V15** | **Unused context fallback sends to root domain** | **LOW** | If `storeSlug` is undefined/null on a storefront page (shouldn't happen in normal flow), `context` becomes `''` and the `default` match in the controller sends to `redirect('/')` via `fallbackLogoutRedirect()`. |

### 3. Admin Logout (AdminSidebar)
**File:** `resources/js/Components/AdminSidebar.jsx:201`

**Current Behavior:**
- `router.post('/logout', { context: 'admin', store_slug: storeSlug })`
- Matches `'admin'` context → redirects to `/store/{slug}/admin/login` or `/admin/login`

**Vulnerabilities:** Same as V9, V10 above.

### 4. AppLayout Logout (legacy admin/client)
**File:** `resources/js/Layouts/AppLayout.jsx:54`

**Current Behavior:**
- `router.post('/logout', { context: auth?.user?.is_admin ? 'admin' : '', store_slug: '' })` — sends empty string for both context variants

**Vulnerabilities:**Same as V14 above.

---

## C. Redirect Logic

### Login Success Redirects

| Route | User Type | Redirect Target | Issue |
|-------|-----------|-----------------|-------|
| `/login` POST | Admin | `storefront.admin.dashboard` with tenant from `Tenant::getCurrent()` | May redirect to wrong tenant (V3) |
| `/login` POST | SuperAdmin | Same as admin (`isAdmin()` returns true) | SuperAdmin sent to tenant dashboard (V6) |
| `/login` POST | Customer | `client.dashboard` (root `/dashboard`) | Correct |
| `/store/{slug}/login` POST | Admin/SuperAdmin | `storefront.admin.dashboard` with tenant slug | Correct |
| `/store/{slug}/login` POST | Customer | `storefront.index` with tenant slug | Correct |
| `/register` POST | Any | `storefront.admin.dashboard` if admin, else `storefront.index` | Admin role not assigned at registration (customer only), so this branch is dead code |
| `/store/{slug}/register` POST | Any (customer role) | `storefront.index` | Correct |

| # | Issue | Severity | Details |
|---|-------|----------|---------|
| **V16** | **Login redirect for admin uses potentially wrong tenant** | **HIGH** | `AuthenticatedSessionController::store()` line 69 uses `Tenant::getCurrent()` which may not match the user's own `tenant_id`. A user whose `tenant_id = 1` (Store A) logging in from a page where `IdentifyTenant` resolved to Store B will be redirected to Store B's admin dashboard. Once there, `TenantIsValid` middleware (which checks user's `tenant_id`) may force-logout if the user doesn't belong to Store B. |

### Logout Success Redirects

| Context | Redirect Target | Exists? |
|---------|----------------|---------|
| `superadmin` | `/superadmin/login` | **NO — 404** |
| `admin` + store_slug | `/store/{slug}/admin/login` | **NO — 404** |
| `admin` (no slug) | `/admin/login` | **NO — 404** |
| `storefront` + slug | `/store/{slug}` | Yes |
| `storefront` (no slug) | `/` | Yes |
| fallback (superadmin) | `/superadmin/login` | **NO — 404** |
| fallback (has slug) | `/store/{slug}` | Yes |
| fallback (neither) | `/` | Yes |

### Intended Redirects
- `StorefrontLoginController::store()` uses `redirect()->intended()` (lines 96, 99)
- `RegisteredUserController::store()` uses `redirect()->intended()` (line 89)
- `AuthenticatedSessionController::store()` does NOT use `redirect()->intended()`

| # | Issue | Severity | Details |
|---|-------|----------|---------|
| **V17** | **Root login ignores intended URL** | **LOW** | `AuthenticatedSessionController::store()` uses explicit `redirect()->route()` instead of `redirect()->intended()`. Users redirected to `/login` after an auth filter won't return to their original destination. |

---

## D. Session Handling

### 1. Tenant Session Resolution
**File:** `app/Http/Middleware/IdentifyTenant.php`

**Resolution Priority:**
1. Authenticated user's `tenant_id` (via `User::$tenant` relationship)
2. Subdomain (via `resolveFromSubdomain()`)
3. `X-Tenant` header (via `resolveFromHeader()`)
4. Session `current_tenant_slug` (via `resolveFromSession()`)
5. Default tenant (slug `default`)

**Middleware Order in `bootstrap/app.php`:**
```
web group order:
1. \Illuminate\Session\Middleware\StartSession
2. \Illuminate\View\Middleware\ShareErrorsFromSession
3. ... (other Laravel web middleware)
4. IdentifyTenant          ← Step 4
5. HandleInertiaRequests   ← Step 5
6. CheckUserStatus         ← Step 6
7. CheckMaintenanceMode    ← Step 7
```

| # | Issue | Severity | Details |
|---|-------|----------|---------|
| **V18** | **Tenant session persists across tenants** | **MEDIUM** | `IdentifyTenant.php:82-89`: `resolveFromSession()` reads `current_tenant_slug` from the session. If a user visits Store A (which sets `current_tenant_slug = 'store-a'` in session), then navigates directly to a URL on Store B, the session slug may still point to Store A. The session is NOT cleared on tenant change. However, Storefront middleware (`Storefront.php`) always resolves from URL and overrides, so this is mitigated for storefront-routed pages. |
| **V19** | **Session tenant survives storefront admin page transitions** | **LOW** | After visiting `/store/acme/admin/dashboard`, the session retains `current_tenant_slug = acme`. Leaving the admin and visiting the root domain, `IdentifyTenant` will first try to resolve from the authenticated user (which is correct), falling back to subdomain, header, then session — which still has `acme`. The `Storefront` middleware mitigates this for `store/*` routes by always using URL-based resolution. |

### 2. Impersonation Session
**File:** `app/Http/Controllers/SuperAdmin/ImpersonationController.php`

**Session Keys:** `impersonator_id`, `impersonator_name`, `impersonation_batch_uuid`

**Behavior:**
- `start()`: Checks user is SuperAdmin, target is not SuperAdmin, target has tenant, target is active, target has admin role, target tenant is active → stores session keys → logs out impersonator → logs in as target → regenerates session
- `leave()`: Reads keys → logs out target → logs back in as impersonator → regenerates session
- `HandleInertiaRequests::share()`: Detects impersonation via `session()->has('impersonator_id') && !$user->isSuperAdmin()` — sets `is_impersonating` flag

| # | Issue | Severity | Details |
|---|-------|----------|---------|
| **V20** | **Impersonation does not respect tenant.active middleware routes** | **MEDIUM** | When impersonating an admin user whose tenant's subscription has expired, `EnsureTenantIsActive` middleware blocks operations routes. The SuperAdmin's ability to bypass this is lost during impersonation since `$user->isSuperAdmin()` now returns false. The impersonator would be stuck on the dashboard with no way to access operations. The leave route (`/superadmin/impersonate/leave`) is outside the `tenant.active` group, so leaving is still possible. |
| **V21** | **Imporsonation session persists after tenant suspension** | **LOW** | The `start()` method checks `$user->tenant->status !== 'active'` at line 49, but does NOT check subscription status. If the tenant's subscription expires between the time impersonation starts and the impersonator tries to access operations routes, the `EnsureTenantIsActive` middleware blocks them. |

### 3. Admin vs Customer Session
- Both use the same `web` guard (`Auth::guard('web')`)
- Distinction is role-based (Spatie permissions)
- Admin routes use `role:admin` middleware
- Customer routes use `auth` middleware only

**No session-based separation vulnerability** — admin and customer use the same guard correctly. The role middleware gates access.

### 4. CSRF/Session Fixation

| # | Issue | Severity | Details |
|---|-------|----------|---------|
| **V22** | **Session regeneration on login is correct** | ✅ OK | Both `AuthenticatedSessionController::store()` and `StorefrontLoginController::store()` call `$request->session()->regenerate()` after authentication. |
| **V23** | **Session regeneration on logout is correct** | ✅ OK | `AuthenticatedSessionController::destroy()` calls both `invalidate()` and `regenerateToken()`. |
| **V24** | **CSRF tokens excluded** | ✅ OK | Only `webhooks/telegram/*` excluded from CSRF validation (`bootstrap/app.php:52-54`). All auth routes are CSRF-protected. |

---

## E. Access Control Verification

### E1. Can a tenant user login from root `/login`?

| # | Issue | Severity | Details |
|---|-------|----------|---------|
| **V25 (same as V1)** | **YES — tenant user CAN authenticate from root `/login`** | **HIGH** | `AuthenticatedSessionController::store()` (`AuthenticatedSessionController.php:28-76`) has NO tenant-scoping. Any user with valid email+password can authenticate. If the user has `tenant_id` and the tenant exists, they proceed. If admin, they're redirected to `Tenant::getCurrent()`'s dashboard (which may belong to a different tenant). If customer, they're redirected to `/dashboard` (client area). |

**Proof:**
```php
// AuthenticatedSessionController.php:26-76
// No tenant_id check at all — just:
// 1. Check user status (active/suspended/banned)
// 2. Check tenant status (suspended)
// 3. authenticate() via Laravel
// 4. Redirect based on role
// NEVER checks: "does this user belong to the current tenant?"
```

### E2. Can a tenant admin login from root `/login`?

| # | Issue | Severity | Details |
|---|-------|----------|---------|
| **V26 (same as V1/V25)** | **YES — tenant admin CAN authenticate from root `/login`** | **HIGH** | Same as E1. The admin role does not gate the login — only the redirect destination differs. |

### E3. Can a tenant user access another tenant?

| # | Issue | Severity | Details |
|---|-------|----------|---------|
| **V27** | **Route model binding runs BEFORE tenant filter** | **HIGH** | Laravel's `SubstituteBindings` middleware runs in the `web` middleware group (step 6 in Laravel's stack), BEFORE `IdentifyTenant` (step 4 in custom stack — but this depends on exact position). On `storefront-admin` routes, middleware order is: `storefront` loads tenant from URL → `auth` → `role:admin` → `tenant.valid`. The `storefront` middleware resolves the tenant BEFORE route binding runs (because `Storefront` middleware is specified in the route group's `$middleware` array). **However**, the `SubstituteBindings` middleware (built-in) runs at the global web group level, not in the route group. The `Storefront` middleware in the route group executes *during* the route group processing, which is AFTER global middleware. So the binding order is actually: Global `SubstituteBindings` (runs URL→model binding first) → then route-group middlewares (`storefront`, `auth`, etc.). This means `{product}` in `/store/{store_slug}/admin/products/{product}` is resolved globally BEFORE tenant context is set. |
| **V28** | **Products, Orders, Users are not tenant-scoped by default** | **HIGH** | The route model binding for `{product}`, `{order}`, `{user}` resolves globally. The controller methods (e.g., `AdminProductController::show(Product $product)`) receive the globally-resolved model. Only if the controller manually scopes to tenant does the isolation work. Controllers like `AdminProductController` may use global `Product::find()`, which could return a product from a different tenant. **Mitigation:** The `Controller::callAction()` override does NOT add tenant scoping — it only fixes parameter resolution. |
| **V29** | **No cross-tenant verification middleware exists** | **HIGH** | `CheckTenantAccess` middleware does not exist (file not found). The `CheckTenantAccess` middleware alias is not registered in `bootstrap/app.php`. Only `tenant.valid` (TenantIsValid) and `tenant.active` (EnsureTenantIsActive) exist, and they check user <-> tenant relationship, but NOT that the URL-scoped tenant matches the user's tenant. |

### E4. Can a tenant admin access another tenant?

| # | Issue | Severity | Details |
|---|-------|----------|---------|
| **V30 (same as V29)** | **POSSIBLE — no cross-tenant guard** | **HIGH** | A tenant admin from Store A who knows Store B's slug could navigate to `/store/store-b/admin/dashboard`. The `storefront` middleware resolves Store B as the tenant. The `role:admin` middleware passes (the user has admin role). The `tenant.valid` middleware checks `$user->tenant_id` is not empty and the tenant record exists — but it gets `$user->tenant` which is the user's OWN tenant (Store A), not the URL's tenant (Store B). Since `$user->tenant` exists (Store A), the middleware passes. The user is now operating under Store B's tenant context but with their own data. |

**Proof (TenantIsValid middleware):**
```php
// TenantIsValid.php:22-34
// Checks: empty tenant_id? Yes → logout
// Checks: $user->tenant exists? Yes → pass
// NEVER compares $user->tenant_id with the current route's tenant
```

### E5. Can a SuperAdmin accidentally become a tenant user?

| # | Issue | Severity | Details |
|---|-------|----------|---------|
| **V31** | **SuperAdmin is treated as admin on storefront admin routes** | **LOW** | `RoleMiddleware.php:19-21`: SuperAdmin bypasses the `admin` role check and is allowed through. This is deliberate and correct. However, when impersonating a tenant admin (V20), the SuperAdmin loses all SuperAdmin privileges and is subject to tenant subscription/status restrictions. |
| **V32** | **SuperAdmin login from `/store/{slug}/login` redirects to tenant dashboard** | **LOW** | `StorefrontLoginController::store()` line 95: `$user->isAdmin()` returns true for SuperAdmin → redirects to `storefront.admin.dashboard`. The SuperAdmin ends up on a tenant's admin dashboard instead of their own SuperAdmin dashboard. This is confusing but not a security violation. |

---

## F. Missing Routes Summary

| Route Pattern | Purpose | Status | Impact |
|--------------|---------|--------|--------|
| `/superadmin/login` | SuperAdmin login page | **NOT DEFINED** | SuperAdmin logout → 404 |
| `/admin/login` | Legacy admin login page | **NOT DEFINED** | Admin logout without store_slug → 404 |
| `/store/{slug}/admin/login` | Storefront admin login page | **NOT DEFINED** | Store admin logout → 404 |

---

## G. Vulnerability Summary

### CRITICAL (4)
| ID | Finding | File |
|----|---------|------|
| V7 | `/store/{slug}/admin/login` route does not exist | `routes/web.php`, `routes/storefront-admin.php` |
| V8 | `/superadmin/login` route does not exist | `routes/web.php`, `routes/superadmin.php` (doesn't exist) |
| V9 | Admin logout → `/store/{slug}/admin/login` → 404 | `AuthenticatedSessionController.php:118` |
| V10 | Admin logout (no slug) → `/admin/login` → 404 | `AuthenticatedSessionController.php:118` |
| V11 | SuperAdmin logout → `/superadmin/login` → 404 | `AuthenticatedSessionController.php:117,127` |

### HIGH (5)
| ID | Finding | File |
|----|---------|------|
| V1/V25 | Root `/login` allows any tenant user to authenticate | `AuthenticatedSessionController.php:28-76` |
| V3/V16 | Admin login redirect uses wrong tenant context | `AuthenticatedSessionController.php:69-73` |
| V27 | Route model binding runs before tenant context set | Global middleware ordering |
| V28 | Bound models (Product, Order, User) not tenant-scoped | Controller method signatures |
| V29/V30 | No cross-tenant verification middleware | `CheckTenantAccess.php` doesn't exist |

### MEDIUM (4)
| ID | Finding | File |
|----|---------|------|
| V2 | Admin redirect to `/store/default/admin/dashboard` (404) | `AuthenticatedSessionController.php:69-73` |
| V5 | Auto-assign tenant_id at login creates permanent binding | `StorefrontLoginController.php:76-78` |
| V12 | Referrer-based context detection unreliable | `AuthenticatedSessionController.php:100-109` |
| V14/V4 | AppLayout sends empty store_slug in logout | `AppLayout.jsx:54` |
| V18/V19 | Tenant session persists across tenant switches | `IdentifyTenant.php:82-89` |
| V20 | Impersonation blocks due to subscription expiry | `ImpersonationController.php` |

### LOW (5)
| ID | Finding | File |
|----|---------|------|
| V4 | Root register redirects without showing form | `RegisteredUserController.php:26-29` |
| V6/V32 | SuperAdmin from storefront login → tenant dashboard | `StorefrontLoginController.php:95` |
| V17 | Root login ignores intended URL | `AuthenticatedSessionController.php:68-76` |
| V21 | Impersonation doesn't check subscription status | `ImpersonationController.php:49` |
| V31 | SuperAdmin loses bypass during impersonation | Operational limitation |

---

## H. Required Fixes

### Critical Priority
1. **Create missing login routes:**
   - Add `GET /superadmin/login` → renders SuperAdmin login page
   - Add `GET /admin/login` → renders admin login page (for legacy fallback)
   - Add `GET /store/{slug}/admin/login` → renders storefront admin login page

2. **Fix logout redirects** in `AuthenticatedSessionController::destroy()`:
   - Change all three 404-producing redirects to working alternatives (homepage, storefront index)
   - OR implement the missing routes above

### High Priority
3. **Add tenant-verification to root `/login`:**
   - `AuthenticatedSessionController::store()` must verify that the authenticating user's `tenant_id` matches the current tenant context (or that the user is a SuperAdmin, or that no tenant context is active and the user has no tenant_id)

4. **Add cross-tenant verification middleware:**
   - Implement `CheckTenantAccess` middleware that compares `$user->tenant_id` with `Tenant::getCurrent()->id`
   - Add it to the storefront admin middleware chain (after `role:admin`, before `tenant.valid`)

5. **Add tenant scoping to route model binding:**
   - Override route binding in `RouteServiceProvider` or add global scope to models
   - OR add `where('tenant_id', tenantId())` scopes in each controller method

### Medium Priority
6. **Fix admin redirect destination in `AuthenticatedSessionController::store()`:**
   - Use `$user->tenant` instead of `Tenant::getCurrent()` for redirect
   - If `$user->tenant` is null, redirect to appropriate non-tenant admin page

7. **Remove auto-assign of `tenant_id` at login** in `StorefrontLoginController::store()` — or gate it behind explicit user consent

8. **Fix session tenant persistence:**
   - Clear `current_tenant_slug` from session when `IdentifyTenant` resolves via URL/subdomain and it differs from the session value

### Low Priority
9. **Add `redirect()->intended()` to root login** for proper post-auth redirect flow

10. **Add subscription status check to impersonation start** to prevent impersonation of users with expired subscriptions

11. **Fix AppLayout logout** to send proper `store_slug` value (null instead of empty string)

---

## I. Affected Files Summary

| File | Vulnerabilities |
|------|----------------|
| `routes/auth.php` | V7, V8 (routes not defined here but needed) |
| `routes/web.php` | V7, V8 (routes not defined) |
| `routes/storefront-admin.php` | V7 (admin login route missing) |
| `app/Http/Controllers/Auth/AuthenticatedSessionController.php` | V1, V2, V3, V9, V10, V11, V12, V16, V17, V25, V26 |
| `app/Http/Controllers/StorefrontLoginController.php` | V5, V6, V32 |
| `app/Http/Controllers/Auth/RegisteredUserController.php` | V4 |
| `app/Http/Controllers/SuperAdmin/ImpersonationController.php` | V20, V21 |
| `app/Http/Middleware/IdentifyTenant.php` | V18, V19 |
| `app/Http/Middleware/Storefront.php` | V27 (partial mitigation) |
| `app/Http/Middleware/TenantIsValid.php` | V29, V30 (doesn't cross-check) |
| `app/Http/Middleware/RoleMiddleware.php` | V31 (by design) |
| `bootstrap/app.php` | V29 (CheckTenantAccess not registered) |
| `resources/js/Components/AdminSidebar.jsx` | V9, V10 |
| `resources/js/Layouts/AppLayout.jsx` | V14 |
| `resources/js/Components/ShopNavbar.jsx` | V15 |
| `resources/js/Pages/Auth/Login.jsx` | No vulnerabilities |
| `resources/js/Pages/Storefront/Login.jsx` | No vulnerabilities |
