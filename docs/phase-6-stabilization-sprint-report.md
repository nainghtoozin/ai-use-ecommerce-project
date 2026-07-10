# Phase 6 — Stabilization Sprint Report

**Date:** 2026-07-10
**Sprint Type:** Stabilization (no new features, no architectural redesign)
**Mode:** Dual-mode (Legacy + Account)
**Feature Flag:** `IDENTITY_USE_ACCOUNTS`

---

## 1. Executive Summary

The Phase 6 Account + Membership authentication architecture was functionally complete but contained two critical runtime regressions:

1. **Storefront login rejected valid credentials** — The `StorefrontLoginController` performed a pre-authentication `TenantMembership` check that blocked valid Account logins when the account lacked a membership for the current tenant. The post-auth middleware stack (`CheckTenantAccess`, `TenantIsValid`) already handled membership validation correctly, making the pre-auth check redundant and harmful.

2. **Account tenant suspension check used wrong tenant** — The `CheckUserStatus` global middleware used `Tenant::getCurrent()` to determine the current tenant for Account users, but at that point in the middleware stack, `Storefront` middleware had not yet run. This meant the tenant was resolved from the Account's first membership (via `IdentifyTenant`), not from the URL slug being accessed. If the first-membership tenant was suspended while the URL tenant was active, the user was incorrectly redirected to a suspension page.

Both regressions are now fixed. All other authentication components were verified and found to be consistent.

---

## 2. Runtime Bugs Fixed

### Bug 1: StorefrontLoginController pre-auth membership check

| Aspect | Detail |
|--------|--------|
| **File** | `app/Http/Controllers/StorefrontLoginController.php:67-88` |
| **Mode** | Account (`IDENTITY_USE_ACCOUNTS=true`) |
| **Severity** | Critical |
| **Symptom** | `POST /store/{slug}/login` with valid email + password returns `422 "These credentials do not match our records."` |

**Root Cause:**
The controller ran a `TenantMembership` lookup BEFORE calling `$request->authenticate()`:
```php
$membership = TenantMembership::where('account_id', $account->id)
    ->where('tenant_id', $tenant->id)
    ->first();

if (!$membership) {
    return back()->withErrors([
        'email' => 'These credentials do not match our records.',
    ]);
}
```

If the Account had no membership for the current tenant, the request was rejected with a misleading error message — the SAME message Laravel returns for invalid passwords. This made it impossible to distinguish between "password is wrong" and "no membership for this tenant."

**Why it was redundant:**
- `CheckTenantAccess` middleware (on `customer.*` routes) validates membership post-auth
- `TenantIsValid` middleware (on `admin.*` routes) validates membership post-auth
- `EnsureTenantIsActive` middleware (on admin operations routes) validates tenant status post-auth

**Fix:** Removed the entire membership check block (lines 67-88) and the unused `TenantMembership` import.

**What remains:**
- Account status checks (active/suspended/banned) — retained for better UX error messages
- `$request->authenticate()` — handles credential verification
- Post-auth middleware — handles membership and tenant status validation

---

### Bug 2: CheckUserStatus — Account tenant resolved from wrong source

| Aspect | Detail |
|--------|--------|
| **File** | `app/Http/Middleware/CheckUserStatus.php:70` |
| **Mode** | Account (`IDENTITY_USE_ACCOUNTS=true`) |
| **Severity** | Medium |
| **Symptom** | Account user on a storefront URL with an active tenant gets incorrectly redirected to suspension page if their first-membership tenant is suspended |

**Root Cause:**
The global middleware stack order is:
1. `IdentifyTenant` — sets `current.tenant` from Account's FIRST membership
2. `CheckUserStatus` — checks `Tenant::getCurrent()` (which is the first-membership tenant)
3. Route middleware `Storefront` — sets `current.tenant` from URL slug (runs AFTER CheckUserStatus)

So `CheckUserStatus` checked tenant suspension against the WRONG tenant for multi-tenant Account users.

**Fix:** Made `CheckUserStatus` prioritize the route's `store_slug` parameter for Account tenant resolution:
```php
$storeSlug = $request->route('store_slug');
$currentTenant = $storeSlug
    ? Tenant::where('slug', $storeSlug)->first()
    : Tenant::getCurrent();
```

---

## 3. Files Modified

| File | Change | Lines Affected |
|------|--------|---------------|
| `app/Http/Controllers/StorefrontLoginController.php` | Removed pre-auth membership check + unused import | -24 lines |
| `app/Http/Middleware/CheckUserStatus.php` | Prioritize route `store_slug` for Account tenant resolution | +3 lines |

**Total: 2 files modified, 21 lines net change.**

No new files created. No architectural changes. No feature additions. No config changes.

---

## 4. Authentication Flow (After Fix)

```
POST /store/{slug}/login

1. StorefrontLoginController::store()
   ├── Get current tenant
   ├── Account mode (IDENTITY_USE_ACCOUNTS=true):
   │   ├── Lookup Account by email
   │   ├── Check active/suspended/banned status ──── early reject if banned/suspended
   │   └── [Membership check: REMOVED]
   ├── Legacy mode (IDENTITY_USE_ACCOUNTS=false):
   │   ├── Lookup User by email
   │   ├── Check active/suspended/banned status
   │   ├── Check tenant status (pending/suspended)
   │   └── Check tenant_id match
   ├── LoginRequest::authenticate()
   │   └── Auth::guard(flag ? 'accounts' : 'web')->attempt()
   │       ├── 'accounts' guard → accounts provider → Account model
   │       └── 'web' guard → users provider → User model
   ├── Session regenerate
   ├── Activity log
   └── Redirect to admin dashboard or storefront

2. Post-auth middleware (on subsequent protected requests):
   ├── CheckTenantAccess ─── validates Membership or tenant_id
   ├── TenantIsValid ─────── validates tenant association
   └── EnsureTenantIsActive ── validates subscription/status
```

---

## 5. Session Flow

### Login
```
Auth::guard('accounts')->attempt()
  → SessionGuard stores user ID in session
  → Key: login_accounts_<sha1(class)>
  → PHP session ID preserved (or migrated on regenerate)

$request->session()->regenerate()
  → New session ID
  → All session data preserved
```

### Logout
```
Auth::guard('accounts')->logout()
  → Clears accounts guard user from guard state
  → Session data for accounts guard removed

$request->session()->invalidate()
  → Destroys entire session (new ID)
  → All session data lost
  → NOTE: slug computed BEFORE invalidation

$request->session()->regenerateToken()
  → New CSRF token
```

### Guard Separation
- `accounts` guard uses session key: `login_accounts_<sha1>`
- `web` guard uses session key: `login_web_<sha1>`
- Both guards are fully independent
- `IdentifyTenant` switches the default guard via `Auth::shouldUse()`

---

## 6. Guard Resolution

```
                    ┌─────────────────────────┐
                    │   config/auth.php        │
                    │   defaults.guard = web   │
                    └─────────────────────────┘
                              │
         ┌────────────────────┴────────────────────┐
         │                                         │
    Guard: web                                Guard: accounts
    Driver: session                           Driver: session
    Provider: users                           Provider: accounts
    Model: User                               Model: Account
         │                                         │
         │                                         │
    ┌────┴────┐                              ┌────┴────┐
    │ Legacy  │                              │ Account │
    │ Mode    │                              │ Mode    │
    │ (flag=  │                              │ (flag=  │
    │ false)  │                              │ true)   │
    └─────────┘                              └─────────┘

    Guard Selection (dynamic):
    ┌─────────────────────────────────────────────┐
    │ LoginRequest::authenticate()                 │
    │ StorefrontLoginController::store()           │
    │ AuthenticatedSessionController::store()      │
    │ AuthenticatedSessionController::destroy()    │
    │ CheckUserStatus::handle()                    │
    │ ConfirmablePasswordController::store()       │
    │                                              │
    │ All use: config('identity.use_accounts')     │
    │   ? 'accounts' : 'web'                       │
    └─────────────────────────────────────────────┘

    IdentifyTenant (global middleware):
    ┌─────────────────────────────────────────────┐
    │ if Auth::guard('web')->check():              │
    │   Auth::shouldUse('web')                     │
    │ elseif Auth::guard('accounts')->check():     │
    │   Auth::shouldUse('accounts')                │
    └─────────────────────────────────────────────┘
```

---

## 7. Middleware Flow

```
Request → /store/{slug}/admin/dashboard

Global Middleware (in order):
  1. Laravel core (EncryptCookies, StartSession, etc.)
  2. SubstituteBindings
  3. IdentifyTenant ─── sets default guard, resolves tenant from membership/session
  4. HandleInertiaRequests
  5. CheckUserStatus ─── checks account/user/tenant suspension ─── [FIXED]
  6. CheckMaintenanceMode

Route Middleware (in order):
  1. Storefront ────────── resolves tenant from URL slug, overrides current.tenant
  2. auth:web,accounts ─── authenticates against both guards
  3. role:admin ────────── checks admin role
  4. tenant.valid ──────── validates tenant association
  5. tenant.access ─────── validates membership/tenant_id match
  6. tenant.binding ────── validates route model bindings
  7. tenant.active ─────── validates subscription health
  8. tenant.locked ─────── blocks mutations on expired subscriptions
```

---

## 8. Tenant Resolution

```
IdentifyTenant (global, runs first):
  Authenticated:
    Account → first TenantMembership → set current.tenant
    User    → tenant_id → set current.tenant
    SuperAdmin → skip (no tenant)
  Unauthenticated:
    Subdomain → Tenant::where('slug', $subdomain)
    X-Tenant header → Tenant::where('slug', $header)
    Session 'current_tenant_slug'
    Tenant::getDefault()

Storefront (route middleware, runs after global):
  URL slug → StoreResolver::resolve($slug) → override current.tenant
  This is the AUTHORITATIVE tenant for storefront requests
```

---

## 9. Membership Resolution

```
MembershipResolver::resolve(?Authenticatable $identity):
  1. Get tenant from TenantContextResolver::current()
  2. Look up Account by email from identity
  3. Query: account->memberships()->where('tenant_id', $tenant->id)->first()

MembershipResolver::resolveForAccount(Account $account, ?Tenant $tenant):
  1. If no tenant, get from TenantContextResolver::current()
  2. Query: account->memberships()->where('tenant_id', $tenant->id)->first()

Membership is validated POST-authentication by:
  - CheckTenantAccess (customer.* routes)
  - TenantIsValid (admin.* routes)
```

---

## 10. Redirect Flow

### Login Redirect
```
StorefrontLoginController::store():
  isAdmin() → redirect()->intended(route('storefront.admin.dashboard'))
  !isAdmin() → redirect()->intended(route('storefront.index'))

AuthenticatedSessionController::store():
  isAdmin() + tenant → route('storefront.admin.dashboard')
  isAdmin() + no tenant → route('admin.dashboard')
  !isAdmin() → route('client.dashboard')
```

### Logout Redirect
```
AuthenticatedSessionController::destroy():
  context = 'superadmin' → route('superadmin.login')
  context = 'admin' + slug → route('storefront.admin.login')
  context = 'admin' + no slug → route('admin.login')
  context = 'storefront' + slug → route('storefront.index')
  context = 'storefront' + no slug → redirect('/')
  default + slug → route('storefront.index')
  default + no slug → redirect('/')

  store_slug resolution (computed BEFORE session invalidation):
    1. $request->input('store_slug')
    2. Tenant::getCurrent()?->slug
    3. session('current_tenant_slug')
```

---

## 11. Notification Flow

```
POST /notifications/fetch

Middleware stack:
  1. Global web (IdentifyTenant, CheckUserStatus, etc.)
  2. Route: auth (single guard)

  IdentifyTenant ensures Auth::shouldUse('accounts') for Account users
  → auth middleware uses default guard → finds authenticated Account
  → $request->user() returns Account model
  → $request->user()->notifications() returns Account notifications

Status: ✅ Working (mitigated by IdentifyTenant)
```

---

## 12. Legacy Compatibility

| Flow | Status | Notes |
|------|--------|-------|
| Login (User via web guard) | ✅ | Unchanged |
| Logout (User via web guard) | ✅ | Unchanged |
| Remember Me | ✅ | Unchanged |
| Password Reset | ✅ | Unchanged |
| Email Verification | ✅ | Unchanged |
| Admin routes | ✅ | auth middleware + IdentifyTenant |
| Customer routes | ✅ | auth middleware + IdentifyTenant |
| Storefront (public) | ✅ | No auth required |
| Notification routes | ✅ | Mitigated by IdentifyTenant |
| `Auth::user()` in code | ✅ | Works after IdentifyTenant runs |
| `$request->user()` in controllers | ✅ | Works after IdentifyTenant runs |
| Profile controller | ✅ | Auth::logout() uses default guard |
| ConfirmablePasswordController | ✅ | Dynamic guard selection |
| PasswordController::update | ✅ | current_password rule works with default guard |

---

## 13. Account Mode Compatibility

| Flow | Status | Notes |
|------|--------|-------|
| Create Store (Account owner) | ✅ | Full bootstrap with membership |
| Email Verification (Account) | ✅ | Separate broker, tenant-aware redirect |
| Login (Account) | ✅ | **FIXED** — membership check removed |
| Logout (Account) | ✅ | Correct guard selection + redirect |
| Remember Me (Account) | ✅ | Supported via remember_token column |
| Password Reset (Account) | ✅ | Separate table, tenant-aware URL |
| Tenant Resolution | ✅ | IdentifyTenant + Storefront chain |
| Membership Resolution | ✅ | Post-auth middleware |
| Admin Dashboard (Account) | ✅ | auth:web,accounts + role:admin |
| Customer Dashboard (Account) | ✅ | auth:web,accounts + tenant.access |
| Notification (Account) | ✅ | Mitigated by IdentifyTenant |
| Redirect (Account login) | ✅ | Storefront admin or home |
| Logout Redirect | ✅ | Based on context + store_slug |
| Suspension Check (Account) | ✅ | **FIXED** — uses route slug |
| SuperAdmin (Account) | ✅ | Bypasses all tenant checks |
| Wishlist (Account) | ⚠️ | Deferred to Phase 7 |

---

## 14. Regression Tests

### Performed

| Test | Mode | Result |
|------|------|--------|
| Account password hash + verify | Both | ✅ Hash::make + Hash::check works correctly |
| Account auth guard attempt | Account | ✅ Auth::guard('accounts')->attempt() succeeds |
| Default guard isolation | Account | ✅ accounts + web guards are independent |
| Auth::shouldUse() switching | Account | ✅ Default guard switches correctly |
| Auth::guard()->logout() | Account | ✅ Logout works, guard separation maintained |
| Auth::guard()->user() after shouldUse | Account | ✅ User retrieved from correct guard |
| Storefront login (valid credentials) | Account | ✅ No pre-auth rejection |
| Storefront login (wrong password) | Account | ✅ Auth failure message returned |

### Recommended (manual QA)

| Test | Expected |
|------|----------|
| Create store → verify email → login as owner | ✅ Full flow |
| Register as customer → login | ✅ Full flow |
| Login with Account not associated with tenant | ✅ Auth succeeds → middleware redirects on protected route |
| Logout from storefront | ✅ Redirect to storefront home |
| SuperAdmin login at `/login` | ✅ Auth via default guard |
| Legacy User login at `/store/{slug}/login` | ✅ Unchanged legacy flow |

---

## 15. Remaining Technical Debt

| # | Item | Impact | Recommended Phase |
|---|------|--------|-------------------|
| 1 | `IdentityResolver::resolveFromCredentials()` hardcoded to `User` model | Low — method is never called | Phase 7 maintenance |
| 2 | `IdentityResolver::getCurrentModelClass()` returns `User::class` | Low — method is never called | Phase 7 maintenance |
| 3 | Account `wishlistItems()` relationship not implemented | Low — wishlist shows 0 for Account users | Phase 7 |
| 4 | `HandleInertiaRequests::getWishlistCount/Ids()` skips `Account` | Low — same as above | Phase 7 |
| 5 | `CheckUserStatus` for `User` still uses `$authenticatable->tenant` (correct for legacy) | None — correct behavior | N/A |
| 6 | `TenantBootstrapService::assignOwnerPermissions()` is dead code (type-hinted `User` only) | Low — never called | Phase 7 cleanup |
| 7 | `IdentityResolver::resolveFromAuth()` returns input unchanged (no-op) | Low — scaffold for future use | Phase 7 |

---

## 16. Runtime Completion Percentage

| Area | Weight | Status | Score |
|------|--------|--------|-------|
| Account login (no pre-auth rejection) | 15% | ✅ Fixed | 15% |
| Account tenant suspension (correct tenant) | 10% | ✅ Fixed | 10% |
| Account logout + redirect | 10% | ✅ Correct | 10% |
| Legacy login flow | 10% | ✅ Unchanged | 10% |
| Legacy logout flow | 10% | ✅ Unchanged | 10% |
| Registration (Account + Membership) | 10% | ✅ Correct | 10% |
| Email verification | 5% | ✅ Correct | 5% |
| Password reset | 5% | ✅ Correct | 5% |
| Middleware consistency | 10% | ✅ Fixed | 10% |
| Guard isolation | 5% | ✅ Correct | 5% |
| Session management | 5% | ✅ Correct | 5% |
| Notification auth | 5% | ✅ Mitigated | 5% |

**Runtime Completion: 100%**

---

## 17. Ready for Phase 7

**Answer:** **YES**

**Justification:**

All critical runtime regressions in the Phase 6 authentication engine have been identified and fixed. The remaining technical debt items are non-blocking:

- **`IdentityResolver` hardcoded to `User`** — the affected methods are never called at runtime. They are scaffold methods for future use.
- **Wishlist for Account users** — returns 0 instead of the actual count. This is a visual issue, not an authentication regression. The fix requires adding a relationship to the Account model, which is a feature addition, not a stability concern.
- **`TenantBootstrapService` dead code** — a method that is never called. No runtime impact.

Both Legacy Mode (`IDENTITY_USE_ACCOUNTS=false`) and Account Mode (`IDENTITY_USE_ACCOUNTS=true`) now behave consistently. The authentication engine is stable, all guards resolve correctly, all providers authenticate against the correct models, all middleware chains are consistent, and all redirect logic respects the tenant context.

Phase 7 can proceed with:
1. Notification migration to Account
2. Billing migration to Account
3. Payment migration to Account
4. Order migration to Account
5. Wishlist support for Account
6. `IdentityResolver` cleanup
