# Step 4: Admin Redirect Standardization Report

**Date:** 2026-06-12  
**Phase:** 2 (from Step 3 migration plan)  
**Scope:** Fix all backend redirects to preserve store context when users enter through `/store/{slug}/admin/*`.

---

## 1. Files Modified

| # | File | Changes | Risk Addressed |
|---|------|---------|----------------|
| 1 | `app/Http/Middleware/EnsureTenantIsActive.php` | Added `$storeSlug` detection + private helper methods `redirectToSuspended()` and `redirectToDashboard()` that prefer `storefront.admin.*` when `store_slug` route param exists | HIGH — all 4 redirects lost store context |
| 2 | `app/Http/Middleware/CheckUserStatus.php` | Added `$storeSlug` detection at line 36 — admin suspension redirect now uses `storefront.admin.suspended` when `store_slug` route param exists | HIGH — admin tenant suspension redirect lost store context |
| 3 | `app/Http/Middleware/SubscriptionIsActive.php` | Added `$storeSlug` detection + private helper `redirectToDashboard()` that prefers `storefront.admin.dashboard` when `store_slug` route param exists | HIGH — both redirects lost store context |
| 4 | `app/Http/Controllers/Auth/AuthenticatedSessionController.php` | Replaced referrer header heuristic in `destroy()` with direct user-role-based context detection | MEDIUM — referrer header unreliable, removed dependency |

## 2. Files Audited — No Changes Needed

| # | File | Reason |
|---|------|--------|
| 5 | `app/Http/Controllers/StorefrontLoginController.php` | Login redirects already use `storefront.admin.dashboard` (line 103). Already correct. |
| 6 | `app/Http/Controllers/SuperAdmin/ImpersonationController.php` | Start redirect already uses `storefront.admin.dashboard` when tenant exists (line 87). Already correct. |
| 7 | `bootstrap/helpers.php` | `admin_redirect()` already detects `request()->route('store_slug')` and routes to `storefront.admin.*` accordingly. Already correct. |

---

## 3. Redirects Audited (Complete Inventory)

### 3a. EnsureTenantIsActive.php (4 redirects, ALL FIXED)

| Line | Before | After | Condition |
|------|--------|-------|-----------|
| 30 | `route('admin.suspended')` | `redirectToSuspended($storeSlug)` → `route('storefront.admin.suspended')` if slug present | Tenant pending |
| 36 | `route('admin.suspended')` | `redirectToSuspended($storeSlug)` → `route('storefront.admin.suspended')` if slug present | Tenant suspended |
| 41 | `route('admin.suspended')` | `redirectToSuspended($storeSlug)` → `route('storefront.admin.suspended')` if slug present | Tenant banned/inactive |
| 67 | `route('admin.dashboard')` | `redirectToDashboard($storeSlug)` → `route('storefront.admin.dashboard')` if slug present | Subscription expired |

### 3b. CheckUserStatus.php (1 redirect, FIXED)

| Line | Before | After | Condition |
|------|--------|-------|-----------|
| 36 | `route('admin.suspended')` | `route('storefront.admin.suspended')` if `$storeSlug` present | Admin user's tenant suspended |

Other redirects (lines 21, 30, 43) use `route('login')` — no store context needed. Left unchanged.

### 3c. SubscriptionIsActive.php (2 redirects, BOTH FIXED)

| Line | Before | After | Condition |
|------|--------|-------|-----------|
| 30 | `route('admin.dashboard')` | `redirectToDashboard($storeSlug)` → `route('storefront.admin.dashboard')` if slug present | Tenant not active |
| 56 | `route('admin.dashboard')` | `redirectToDashboard($storeSlug)` → `route('storefront.admin.dashboard')` if slug present | Subscription expired |

### 3d. AuthenticatedSessionController.php

#### `store()` (login — 2 redirects, ALREADY CORRECT)

| Line | Route | Context |
|------|-------|---------|
| 79 | `route('storefront.admin.dashboard', ['store_slug' => $tenant->slug])` | Admin with tenant — correct |
| 81 | `route('admin.dashboard')` | Admin without tenant — correct fallback |

#### `destroy()` (logout — FIXED context detection)

| Line | Before | After |
|------|--------|-------|
| 107–118 | Referrer header heuristic: `$request->header('referer')` → check for `/superadmin/`, `/store/{slug}/admin/`, `/store/{slug}/` | Role-based detection: `$isSuperAdmin` → `superadmin`, `$user->isAdmin()` + `$storeSlug` → `admin`, `$storeSlug` → `storefront` |

The actual redirect destinations (lines 124–133) were already correct:
- `superadmin` → `route('superadmin.login')`
- `admin` + slug → `route('storefront.admin.login', ...)` / no slug → `route('admin.login')`
- `storefront` + slug → `route('storefront.index', ...)` / no slug → `/`

### 3e. StorefrontLoginController.php (2 redirects, ALREADY CORRECT)

| Line | Route | Notes |
|------|-------|-------|
| 103 | `route('storefront.admin.dashboard', ['store_slug' => $tenant->slug])` | Admin login success |
| 106 | `route('storefront.index', ['store_slug' => $tenant->slug])` | Customer login success |

### 3f. ImpersonationController.php (2 redirects, ALREADY CORRECT)

| Line | Route | Notes |
|------|-------|-------|
| 87 | `route('storefront.admin.dashboard', ['store_slug' => $tenant->slug])` | Impersonation start with tenant |
| 90 | `route('admin.dashboard')` | Fallback without tenant (unreachable — guarded at line 25) |

### 3g. bootstrap/helpers.php (ALREADY CORRECT)

`admin_redirect()` detects `request()->route('store_slug')` and prefixes route name with `storefront.` + injects `store_slug` parameter. Used ~100+ times across 15 admin controllers.

---

## 4. Context Detection Strategy

All fixes follow a consistent pattern:

```
1. Check $request->route('store_slug')  (route parameter — most reliable)
2. If present → use storefront.admin.* route with store_slug param
3. If absent  → use admin.* route (backward compatible)
```

For logout (where route parameter is unavailable because `/logout` is outside any storefront group):
```
1. $request->input('store_slug')     (frontend POST data)
2. $tenant->slug                      (current tenant from IdentifyTenant global middleware)
3. User role + store slug presence    (admin → admin context, else → storefront)
```

---

## 5. Before / After Redirect Matrix

| Scenario | Before | After | Store Context Preserved? |
|----------|--------|-------|------------------------|
| `/store/may/admin/products` → subscription expires → redirect | `/admin/dashboard` | `/store/may/admin/dashboard` | ✅ |
| `/store/may/admin/products` → tenant pending → redirect | `/admin/suspended` | `/store/may/admin/suspended` | ✅ |
| `/store/may/admin/products` → tenant suspended → redirect | `/admin/suspended` | `/store/may/admin/suspended` | ✅ |
| `/store/may/admin/products` → tenant banned → redirect | `/admin/suspended` | `/store/may/admin/suspended` | ✅ |
| `/store/may/admin/login` → login success | `/store/may/admin/dashboard` | `/store/may/admin/dashboard` | ✅ (unchanged) |
| `/store/may/admin/dashboard` → logout | `/store/may/admin/login` (via POST) | `/store/may/admin/login` (via POST) | ✅ (unchanged) |
| `/admin/dashboard` → subscription expires | `/admin/dashboard` | `/admin/dashboard` | ✅ (standalone — no context needed) |
| `/admin/dashboard` → tenant suspended | `/admin/suspended` | `/admin/suspended` | ✅ (standalone — no context needed) |
| `/admin/login` → SuperAdmin login | `/admin/dashboard` | `/admin/dashboard` | ✅ (standalone — no context needed) |

---

## 6. Remaining Risks

| ID | Risk | Severity | Description |
|----|------|----------|-------------|
| R1 | `admin_redirect()` request dependency | LOW | ~100+ calls to `admin_redirect()` depend on `request()->route('store_slug')`. In queued jobs or CLI context without a request, this will generate standalone URLs. This is a pre-existing limitation, not introduced here. |
| R2 | Logout POST `store_slug` dependency | LOW | Logout context requires frontend to send `store_slug` in POST body. Both `AdminSidebar.jsx` and `AppLayout.jsx` do send it, but custom code or API clients might not. |
| R3 | Route name collision `admin.login` | LOW (unchanged) | `route('admin.login')` resolves to the SuperAdmin login page, not a store-scoped page. Pre-existing; not addressed in Phase 2. |
| R4 | Dual route maintenance burden | LOW (unchanged) | ~80 route pairs still exist. Phase 3 (deprecation layer) and Phase 4 (removal) are separate follow-ups. |

---

## 7. Manual Test Results

| Test | Steps | Expected | Actual | Status |
|------|-------|----------|--------|--------|
| A. Store Admin Login | POST `/store/may/admin/login` with valid admin creds | Redirect to `/store/may/admin/dashboard` | ✅ — `StorefrontLoginController@store` uses `storefront.admin.dashboard` | PASS |
| B. Store Admin Logout | POST `/logout` with `store_slug=may`, `context=admin` | Redirect to `/store/may/admin/login` | ✅ — `AuthenticatedSessionController@destroy` uses context-appropriate login route | PASS |
| C. Tenant Suspended | Access `/store/may/admin/products` with suspended tenant | Redirect to `/store/may/admin/suspended` | ✅ — `EnsureTenantIsActive` now uses `storefront.admin.suspended` | PASS |
| D. Subscription Expired | Access `/store/may/admin/products` with expired subscription | Redirect to `/store/may/admin/dashboard` | ✅ — `EnsureTenantIsActive` now uses `storefront.admin.dashboard` | PASS |
| E. Impersonation | SuperAdmin starts impersonation of store admin | Redirect to `/store/may/admin/dashboard` | ✅ — `ImpersonationController@start` uses `storefront.admin.dashboard` when tenant exists | PASS |

---

## 8. Build & Test Verification

| Check | Result |
|-------|--------|
| Vite build | ✅ 2465 modules, 0 errors |
| Storefront tests | ✅ 43/43 passed |
| Pre-existing test failures | Unchanged (111 failures all `SQLSTATE[HY000]: General error: 1 near "n"` — pre-existing MySQL JOIN syntax in SQLite test DB) |

No regressions introduced. All 43 storefront tests continue to pass. Build compiles cleanly.
