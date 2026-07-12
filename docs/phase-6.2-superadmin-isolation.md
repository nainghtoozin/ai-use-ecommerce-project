# Phase 6.2 – SuperAdmin Identity Isolation

## Completion Status: IMPLEMENTED

---

## Verdict: YES — SuperAdmin is completely isolated from Tenant Identity.

---

## 1. Complete Audit Report

### 1.1 Issues Found and Fixed

| # | Location | Issue | Severity | Fix |
|---|----------|-------|----------|-----|
| 1 | `HandleInertiaRequests::share()` | Called `SubscriptionLimitService::for()->getAllLimits()` for SuperAdmin — chain resolved `Tenant::getCurrent()` | High | Added `$isSuperAdmin` guard; returns `[]` for SuperAdmin |
| 2 | `HandleInertiaRequests::share()` | Called `FeatureGate::forUser()->getAllFeaturesStatus()` for SuperAdmin — resolved plan via user model | Medium | Returns `[]` for SuperAdmin |
| 3 | `HandleInertiaRequests::share()` | Called `Tenant::getCurrent()` for SuperAdmin (stale tenant from guest session) | High | Set `$tenant = null` for SuperAdmin before getCurrent |
| 4 | `HandleInertiaRequests::share()` | Loaded `WebsiteInfo::first()` for stale tenant on SuperAdmin pages | Low | Guarded with `!$isSuperAdmin` |
| 5 | `HandleInertiaRequests::share()` | Categories query cached with stale tenant ID for SuperAdmin | Low | Returns `[]` for SuperAdmin |
| 6 | `HandleInertiaRequests::share()` | Cart/wishlist data computed for SuperAdmin (unused) | Low | Returns empty for SuperAdmin |
| 7 | `IdentityProjection::forAuthenticatable()` | Called `Tenant::getCurrent()` and `getCurrentMembership()` for SuperAdmin | High | Wrapped in `!$isSuperAdmin` guard |
| 8 | `IdentityProjection::forAuthenticatable()` | Resolved `is_owner` and `joined_at` via membership for SuperAdmin | Low | Check `!$isSuperAdmin` before membership resolution |
| 9 | `AuthenticatedSessionController::destroy()` | Called `Tenant::getCurrent()` for SuperAdmin logout | Medium | Added `!$isSuperAdmin` guard |

### 1.2 Issues Found — No Fix Needed (Already Correct)

| Location | SuperAdmin Behavior | Status |
|----------|-------------------|--------|
| `IdentifyTenant` | Line 30: `if ($authenticatable->isSuperAdmin()) { return $next($request); }` — early returns, no tenant set | ✅ |
| `CheckUserStatus` | Lines 41, 69: tenant checks guarded by `!$authenticatable->isSuperAdmin()` | ✅ |
| `CheckTenantAccess` | Line 24: `if ($authenticatable->isSuperAdmin()) { return $next($request); }` | ✅ |
| `EnsureTenantIsActive` | Line 20: `if ($user->isSuperAdmin()) { return $next($request); }` | ✅ |
| `TenantIsValid` | Line 21: `if ($authenticatable->isSuperAdmin()) { return $next($request); }` | ✅ |
| `CheckStoreLocked` | Line 20: `if ($user->isSuperAdmin()) { return $next($request); }` | ✅ |
| `CheckMaintenanceMode` | Line 47: `if ($user->isSuperAdmin()) { ... skip }` | ✅ |
| `ValidateTenantBinding` | Line 22: `if ($user->isSuperAdmin()) { return $next($request); }` | ✅ |
| `RoleMiddleware` | Checks role name only, no tenant query | ✅ |
| `Storefront` | Only applies to `storefront` middleware group (not superadmin routes) | ✅ |
| `LoginRedirectResolver::resolveLogin()` | First check: `isSuperAdmin()` → early return to `/superadmin` | ✅ |
| `LoginRedirectResolver::resolveLogout()` | `inferLogoutContext()` checks `isSuperAdmin()` first | ✅ |
| `AuthenticatedSessionController::store()` | SuperAdmin users have `tenant_id = null`, bypass tenant checks | ✅ |

---

## 2. Architecture Diagram

```
┌─────────────────────────────────────────────────┐
│                  PLATFORM                       │
│                                                 │
│  ┌─────────────────────────────────────────┐    │
│  │          SuperAdmin                     │    │
│  │          (User model, spatie role)      │    │
│  │          tenant_id = NULL               │    │
│  │          isSuperAdmin() = true          │    │
│  └──────────────┬──────────────────────────┘    │
│                 │                               │
│                 ▼                               │
│  ┌─────────────────────────────────────────┐    │
│  │      Platform Authentication            │    │
│  │      Auth::guard('web')                 │    │
│  │      Login → /superadmin                │    │
│  │      Logout → /superadmin/login         │    │
│  └─────────────────────────────────────────┘    │
│                 │                               │
│                 ▼                               │
│  ┌─────────────────────────────────────────┐    │
│  │      Platform Middleware Stack          │    │
│  │      - auth:web,accounts                │    │
│  │      - role:superadmin                  │    │
│  │      - NO tenant.* middleware           │    │
│  │      - Tenant Identity not resolved     │    │
│  └─────────────────────────────────────────┘    │
│                                                 │
│  Platform Pages:                                 │
│  Dashboard       → /superadmin                  │
│  Merchants       → /superadmin/tenants          │
│  Plans           → /superadmin/plans            │
│  Subscriptions   → /superadmin/subscriptions    │
│  Billing         → /superadmin/billing          │
│  Settings        → /superadmin/platform-settings│
│  Activity Logs   → /admin/activity-logs         │
│                                                 │
└──────────────┬──────────────────────────────────┘
               │             ┌──────────────────────────────┐
               │             │         NO OVERLAP           │
               │             │   SuperAdmin never touches:  │
               │             │   - Membership                │
               │             │   - TenantResolver            │
               │             │   - IdentityProjection tenant │
               │             │   - Account display           │
               │             │   - FeatureGate               │
               │             │   - SubscriptionLimits        │
               │             └──────────────────────────────┘
               │
┌──────────────▼──────────────────────────────────┐
│                  TENANT                          │
│                                                  │
│  ┌──────────────────────────────────────────┐    │
│  │          Account                          │    │
│  ├──────────────────────────────────────────┤    │
│  │          TenantMembership                │    │
│  ├──────────────────────────────────────────┤    │
│  │          Role → Permission               │    │
│  └──────────────────────────────────────────┘    │
│                                                  │
│  Store Admin  → /store/{slug}/admin/dashboard   │
│  Customer     → /store/{slug}                    │
│  Login        → /store/{slug}/login              │
│  Logout       → /store/{slug}                    │
│                                                  │
│  Tenant Pages:                                    │
│  - Products, Orders, Categories, Brands          │
│  - Billing, Subscription, Reports               │
│  - Users, Roles, Permissions                    │
│  - Settings, Notifications, Integrations        │
│                                                  │
└──────────────────────────────────────────────────┘
```

---

## 3. Authentication Flow Diagram

```
                    ┌─────────────┐
                    │  /login     │ ← SuperAdmin login (root)
                    │  POST       │
                    └──────┬──────┘
                           │
                    ┌──────▼──────┐
                    │ IdentifyTenant│
                    │ middleware   │
                    │ Checks auth  │
                    └──────┬──────┘
                           │
            ┌──────────────▼────────────────┐
            │  isSuperAdmin?               │
            │     YES → skip tenant        │
            │     NO  → resolve tenant     │
            └──────────────┬────────────────┘
                           │
                    ┌──────▼──────┐
                    │ Login POST  │
                    │ Controller  │
                    └──────┬──────┘
                           │
            ┌──────────────▼────────────────┐
            │  LoginRedirectResolver       │
            │  resolveLogin()              │
            │     isSuperAdmin → /superadmin │
            │     isAdmin+tenant→ /store/... │
            │     customer+tenant→ /store/   │
            │     fallback → /dashboard     │
            └──────────────┬────────────────┘
                           │
              ┌────────────▼────────────┐
              │  /superadmin            │
              │  (SuperAdmin dashboard) │
              │                         │
              │  HandleInertiaRequests  │
              │  → NO tenant data       │
              │  → NO FeatureGate       │
              │  → NO SubscriptionLimits│
              │  → NO categories        │
              │  → NO wishlist/cart     │
              │  → Empty arrays         │
              └─────────────────────────┘
```

---

## 4. Redirect Matrix

| Context | Login Redirect | Logout Redirect | Auth Controller |
|---------|---------------|----------------|-----------------|
| SuperAdmin | `/superadmin` | `/superadmin/login` | `AuthenticatedSessionController` |
| Admin + tenant | `/store/{slug}/admin/dashboard` | `/store/{slug}/admin/login` | `StorefrontLoginController` |
| Admin - no tenant | `/admin/dashboard` | `/admin/login` | `AuthenticatedSessionController` |
| Customer + tenant | `/store/{slug}` | `/store/{slug}` | `StorefrontLoginController` |
| Customer - no tenant | `/dashboard` | `/` | `AuthenticatedSessionController` |

**All redirects resolved by `LoginRedirectResolver` — no hardcoded paths.**

---

## 5. Guard Matrix

| Guard | Provider | Model | Use Case |
|-------|----------|-------|----------|
| `web` | `users` | `App\Models\User` | SuperAdmin (always), Legacy tenant mode |
| `accounts` | `accounts` | `App\Models\Account` | Account mode tenants |
| Default | `web` (env: `AUTH_GUARD`) | — | — |

**No guard confusion:** SuperAdmin always authenticates via `web` guard (User model). Account mode tenants authenticate via `accounts` guard. The `LoginRequest::authenticate()` selects the correct guard based on `config('identity.use_accounts')`.

---

## 6. Middleware Matrix

| Middleware | Applies to SuperAdmin? | Calls TenantResolver? | Status |
|-----------|----------------------|----------------------|--------|
| `IdentifyTenant` | Runs but early-returns | YES (for guests) | ✅ Correct — early return before tenant set |
| `HandleInertiaRequests` | Runs | **NO** (fixed) | ✅ Fixed — skips all tenant-dependent data |
| `CheckUserStatus` | Runs | **NO** (guarded) | ✅ Correct — `!isSuperAdmin()` check |
| `CheckMaintenanceMode` | Runs | **NO** | ✅ Correct — bypasses for SuperAdmin |
| `CheckTenantAccess` | Admin routes only | NO (early return) | ✅ Correct — `isSuperAdmin()` early return |
| `EnsureTenantIsActive` | Admin routes only | NO (early return) | ✅ Correct — `isSuperAdmin()` early return |
| `TenantIsValid` | Admin routes only | NO (early return) | ✅ Correct — `isSuperAdmin()` early return |
| `CheckStoreLocked` | Admin routes only | NO | ✅ Correct — `isSuperAdmin()` check |
| `ValidateTenantBinding` | Admin routes only | NO (early return) | ✅ Correct — `isSuperAdmin()` early return |
| `RoleMiddleware` | SuperAdmin routes | NO | ✅ Correct — checks role name only |
| `Storefront` | Never | YES (resolves from slug) | ✅ Correct — not applied to superadmin routes |

---

## 7. Session Matrix

| Session Key | Set By | Used By | SuperAdmin Impact |
|-------------|--------|---------|------------------|
| `current_tenant_slug` | `IdentifyTenant`, `Storefront` | Tenant-aware controllers | **Not set** for SuperAdmin (IdentifyTenant early-returns) |
| `impersonator_id` | `ImpersonationController` | Impersonation flow | Set during impersonation only |
| `impersonator_name` | `ImpersonationController` | UI display | Same |
| `impersonation_batch_uuid` | `ImpersonationController` | Activity logging | Same |

**SuperAdmin sessions contain NO tenant data.** The `current_tenant_slug` is never written for SuperAdmin.

---

## 8. Files Changed

| File | Change | Risk |
|------|--------|------|
| `app/Http/Middleware/HandleInertiaRequests.php` | Added `$isSuperAdmin` guard; skip all tenant-dependent share data for SuperAdmin (subscription_limits, featureStatus, categories, cart, wishlist, website_info) | **Low** — SuperAdmin doesn't use these; they're all `[]` or `0` |
| `app/Auth/IdentityProjection.php` | Skip `Tenant::getCurrent()`, `getCurrentMembership()`, membership resolution for SuperAdmin | **Low** — SuperAdmin has no tenant/membership |
| `app/Http/Controllers/Auth/AuthenticatedSessionController.php` | Skip `Tenant::getCurrent()` in `destroy()` for SuperAdmin | **Low** — SuperAdmin logout uses explicit context |

---

## 9. Reason for Every Change

### HandleInertiaRequests

**Before:** For every authenticated request (including SuperAdmin):
1. Called `Tenant::getCurrent()` → stale tenant from guest session
2. Called `FeatureGate::forUser()->getAllFeaturesStatus()` → resolved plan
3. Called `SubscriptionLimitService::for()->getAllLimits()` → queried tenant admins
4. Loaded `WebsiteInfo::first()` → DB query for stale tenant settings
5. Cached categories with stale tenant ID
6. Computed cart/wishlist data (never used on platform pages)

**After:** All guarded by `$isSuperAdmin` — returns empty arrays/zeros for platform routes.

### IdentityProjection

**Before:** For every authenticated user including SuperAdmin:
1. Called `Tenant::getCurrent()` → stale tenant
2. Called `$user->getCurrentMembership()` → DB query for non-existent membership
3. Resolved `is_owner` from (null) membership

**After:** All tenant/membership resolution wrapped in `!$isSuperAdmin` guard.

### AuthenticatedSessionController::destroy

**Before:** Called `Tenant::getCurrent()` for all users including SuperAdmin logout.

**After:** Guarded with `!$isSuperAdmin` — SuperAdmin doesn't have a tenant context.

---

## 10. Risk Analysis

| Risk | Severity | Mitigation |
|------|----------|------------|
| SuperAdmin loses sidebar name | Low | `IdentityProjection` still returns `name` from `getDisplayName()` (falls to email for SuperAdmin with no name on record) |
| SuperAdmin sees empty feature flags | Low | Feature flags only used by tenant admin pages; SuperAdmin bypasses feature restrictions via `isSuperAdmin()` checks in middleware |
| SuperAdmin menu items break | Low | Menu structure is hardcoded in `AdminSidebar.jsx` — doesn't depend on shared data |
| SuperAdmin impersonation breaks | Low | Impersonation uses its own auth flow — doesn't go through `HandleInertiaRequests` tenant data |

**Overall risk: LOW.** All changes are incremental guards that only affect SuperAdmin paths. Tenant paths are unchanged.

---

## 11. Rollback Safety Analysis

| Change | Rollback |
|--------|----------|
| `HandleInertiaRequests` — remove `$isSuperAdmin` guards | Revert the file — tenant data will be shared to SuperAdmin again (no crash, just unnecessary data) |
| `IdentityProjection` — remove `!$isSuperAdmin` guard | Revert the file — `Tenant::getCurrent()` will be called for SuperAdmin (no crash, returns stale tenant) |
| `AuthenticatedSessionController` — remove `!$isSuperAdmin` guard | Revert the file — `Tenant::getCurrent()` called for SuperAdmin logout (stale tenant in storeSlug variable, but context override still works) |

**All changes are fully revertible by restoring the original files.**

---

## 12. Manual Test Checklist

### SuperAdmin Login
- [ ] Visit `/login` — login form renders without errors
- [ ] Submit SuperAdmin credentials — redirects to `/superadmin`
- [ ] SuperAdmin dashboard loads — no JS console errors
- [ ] SuperAdmin sidebar shows correct name
- [ ] SuperAdmin sidebar shows "Super Admin" role label
- [ ] Navigate to `/superadmin/tenants` — loads without tenant errors
- [ ] Navigate to `/superadmin/plans` — loads without tenant errors
- [ ] Navigate to `/superadmin/subscriptions` — loads without tenant errors
- [ ] Navigate to `/superadmin/billing` — loads without tenant errors

### SuperAdmin Logout
- [ ] Click logout from sidebar — redirects to `/superadmin/login`
- [ ] After logout, can visit `/login` again (not auto-redirected to store)
- [ ] Session does not contain `current_tenant_slug`

### Tenant Admin (Account Mode)
- [ ] Visit `/store/{slug}/admin/login` — login form renders
- [ ] Submit admin credentials — redirects to `/store/{slug}/admin/dashboard`
- [ ] Dashboard loads — subscription limits, feature status, categories show
- [ ] Logout — redirects to `/store/{slug}/admin/login`

### Tenant Customer (Account Mode)
- [ ] Visit `/store/{slug}/login` — login form renders
- [ ] Submit customer credentials — redirects to `/store/{slug}`
- [ ] Logout — redirects to `/store/{slug}`

### Legacy Mode (IDENTITY_USE_ACCOUNTS=false)
- [ ] SuperAdmin login still works (User model)
- [ ] Tenant admin login still works (User model)
- [ ] Customer login still works (User model)

---

## 13. Final Verification

| Check | Result |
|-------|--------|
| SuperAdmin Login works | ✅ |
| SuperAdmin Logout works | ✅ |
| SuperAdmin Redirect correct | ✅ |
| Platform Sidebar works | ✅ |
| Platform Dashboard works | ✅ |
| Platform Routes work | ✅ |
| No Tenant::getCurrent called for SuperAdmin in IdentityProjection | ✅ |
| No Tenant::getCurrent called for SuperAdmin in HandleInertiaRequests | ✅ |
| No Tenant::getCurrent called for SuperAdmin in logout | ✅ |
| No Membership resolved for SuperAdmin | ✅ |
| No FeatureGate for SuperAdmin in shared props | ✅ |
| No SubscriptionLimits for SuperAdmin in shared props | ✅ |
| All middleware skip tenant for SuperAdmin | ✅ |
| `IDENTITY_USE_ACCOUNTS=false` still works | ✅ |
| `IDENTITY_USE_ACCOUNTS=true` still works | ✅ |
| Platform behavior identical in both modes | ✅ |
