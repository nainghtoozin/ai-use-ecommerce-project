# Step 3: Admin Route Cleanup Report

**Date:** 2026-06-12  
**Scope:** Full audit of the dual `/admin/*` and `/store/{slug}/admin/*` route architecture.

---

## 1. Admin Route Architecture Overview

Two parallel admin route groups serve the same 25 controllers with identical URI structures:

| Group | Prefix | Name prefix | File | Lines |
|-------|--------|-------------|------|-------|
| Standalone | `/admin/*` | `admin.*` | `routes/web.php` | 259–445 |
| Storefront-scoped | `/store/{store_slug}/admin/*` | `storefront.admin.*` | `routes/storefront-admin.php` | 51–244 |

Both groups cover the same CRUD operations (products, orders, categories, brands, units, promotions, banners, coupons, payment-methods, cities, townships, users, roles, permissions, activity-logs, reports, chat, notifications, settings, billing).

---

## 2. Middleware Comparison

### `/admin/*` (standalone)

```
auth → role:admin → tenant.valid → tenant.binding
                                    └── tenant.active (operations only)
```

### `/store/{store_slug}/admin/*` (storefront-scoped)

```
storefront → auth → role:admin → tenant.valid → tenant.access → tenant.binding
                                                                    └── tenant.active (operations only)
```

### Key Differences

| Middleware | /admin/* | /store/{slug}/admin/* | Purpose |
|-----------|----------|----------------------|---------|
| `storefront` | ❌ | ✅ | Resolves tenant from `{store_slug}` URL parameter via `StoreResolver` (1h cache) |
| `tenant.access` | ❌ | ✅ | Cross-tenant guard — ensures `user.tenant_id === current tenant id`; logs out mismatched users |
| `tenant.valid` | ✅ | ✅ | Structural check — `tenant_id` exists and `Tenant` record found |
| `tenant.binding` | ✅ | ✅ | Validates all route model bindings match current tenant |
| `tenant.active` | ✅ (inner) | ✅ (inner) | Health check — status + subscription expiry |

### Tenant Resolution Path

- **Standalone `/admin/*`**: `IdentifyTenant` global middleware → `auth()->user()->tenant_id` → `Tenant::find()` 
- **Storefront `/store/{slug}/admin/*`**: `Storefront` middleware → URL `{store_slug}` → `StoreResolver::resolve()` → cached 1h

The `IdentifyTenant` global middleware (registered in `bootstrap/app.php:47`) runs on all web requests and sets `current.tenant`. When accessing `/store/{slug}/admin/*`, the `Storefront` middleware **overrides** whatever `IdentifyTenant` resolved.

---

## 3. Duplicate Routes (Complete Inventory)

Every route in the standalone group has an identical counterpart in the storefront group. All use the same controller class and method.

### Account Routes (outside `tenant.active`)

| URI | Standalone Name | Storefront Name | Controller |
|-----|----------------|-----------------|------------|
| `/dashboard` | `admin.dashboard` | `storefront.admin.dashboard` | `AdminController@index` |
| `/billing` | `admin.billing` | `storefront.admin.billing` | `AdminBillingController@index` |
| `/billing/renew` | `admin.billing.renew` | `storefront.admin.billing.renew` | `AdminBillingController@renew` |
| `/suspended` | `admin.suspended` | `storefront.admin.suspended` | Closure → `Inertia::render('Admin/Suspended')` |

### CRUD Resources (inside `tenant.active`)

| Resource | Standalone Name Prefix | Storefront Name Prefix | Controller |
|----------|----------------------|----------------------|------------|
| Products | `admin.products.*` | `storefront.admin.products.*` | `AdminProductController` |
| Orders | `admin.orders.*` | `storefront.admin.orders.*` | `AdminOrderController` |
| Categories | `admin.categories.*` | `storefront.admin.categories.*` | `AdminCategoryController` |
| Brands | `admin.brands.*` | `storefront.admin.brands.*` | `AdminBrandController` |
| Units | `admin.units.*` | `storefront.admin.units.*` | `AdminUnitController` |
| Promotions | `admin.promotions.*` | `storefront.admin.promotions.*` | `AdminPromotionController` |
| Banners | `admin.banners.*` | `storefront.admin.banners.*` | `AdminPromotionBannerController` |
| Coupons | `admin.coupons.*` | `storefront.admin.coupons.*` | `AdminCouponController` |
| Payment Methods | `admin.payment-methods.*` | `storefront.admin.payment-methods.*` | `AdminPaymentMethodController` |
| Cities | `admin.cities.*` | `storefront.admin.cities.*` | `AdminCityController` |
| Townships | `admin.townships.*` | `storefront.admin.townships.*` | `AdminTownshipController` |
| Users | `admin.users.*` | `storefront.admin.users.*` | `AdminUserController` |
| Roles | `admin.roles.*` | `storefront.admin.roles.*` | `RoleController` |
| Permissions | `admin.permissions.index` | `storefront.admin.permissions.index` | `PermissionController` |
| Activity Logs | `admin.activity-logs.*` | `storefront.admin.activity-logs.*` | `ActivityLogController` |
| Reports | `admin.reports.*` | `storefront.admin.reports.*` | `AdminReportController` |
| Promotion Reports | `admin.promotions.reports.*` | `storefront.admin.promotions.reports.*` | `AdminPromotionReportController` |
| Chat | `admin.chat.*` | `storefront.admin.chat.*` | `ChatController` |
| Notifications | `admin.notifications.admin` | `storefront.admin.notifications.admin` | `NotificationController` |
| Website Info | `admin.website-info.*` | `storefront.admin.website-info.*` | `SettingsController` |
| Notification Settings | `admin.settings.notifications.*` | `storefront.admin.settings.notifications.*` | `AdminNotificationSettingsController` |
| Telegram Integration | `admin.settings.telegram-integration` | `storefront.admin.settings.telegram-integration` | `TelegramIntegrationController` |

**Total: ~80 route pairs (160 registrations) for the same 25 controllers.**

---

## 4. Route Conflicts

### Conflict 1: `admin.login` Name Collision

Two routes share the name `admin.login`:

| URI | File | Line | Explicit `->name()` | Middleware |
|-----|------|------|---------------------|------------|
| `/store/{slug}/admin/login` | `web.php` | 132 | ❌ (inherits `storefront.admin.login` from group) | `storefront`, `tenant.binding` |
| `/admin/login` | `web.php` | 166–167 | ✅ `->name('admin.login')` | `guest` |

The second registration (`web.php:166`) explicitly calls `->name('admin.login')`, overriding any implicit naming. This means:
- `route('admin.login')` always resolves to `/admin/login` (SuperAdmin login page)
- `route('storefront.admin.login')` resolves to `/store/{slug}/admin/login` (store-scoped admin login)

**Risk**: Low — the two names are distinct. But any code referencing `route('admin.login')` expecting a store-scoped URL will get the wrong page.

### Conflict 2: `admin.suspended` Redirect in Middleware

The `EnsureTenantIsActive` middleware (`app/Http/Middleware/EnsureTenantIsActive.php`) always redirects to `route('admin.suspended')` regardless of context:

```
Line 30: return redirect()->route('admin.suspended')
Line 36: return redirect()->route('admin.suspended');
Line 41: return redirect()->route('admin.suspended')
Line 67: return redirect()->route('admin.dashboard')
```

When a user is on `/store/may/admin/products` and their subscription expires, they get redirected to `/admin/suspended` (standalone) instead of `/store/may/admin/suspended` (storefront-scoped). This breaks the store context.

**Risk**: HIGH — users lose store context on subscription expiry/suspension.

---

## 5. Route Generation Risks

### Risk 1: `adminUrl()` Relies on `window.location`

```js
function detectStoreSlug() {
    if (typeof window === 'undefined') return null;
    const match = window.location.pathname.match(/^\/store\/([^/]+)\//);
    return match ? match[1] : null;
}
```

- If a user bookmarks `/admin/dashboard`, every generated URL will be standalone (no store slug).
- If a user navigates directly to `/admin/products`, the store slug is never detected.
- SSR/SSG contexts (`window === undefined`) always get standalone URLs.

**Risk**: MEDIUM — URLs are context-dependent and non-deterministic.

### Risk 2: 43 Hardcoded `/admin` Paths (Pre-Phase 1)

Before Phase 1 of this report, 43 raw `/admin/...` strings existed across 8 files that **completely bypass** `adminUrl()`:

| File | Lines | Pattern |
|------|-------|---------|
| `Roles/Index.jsx` | 16, 26, 56, 102, 106 | `router.get('/admin/roles')`, `router.delete(\`/admin/roles/${role.id}\`)`, etc. |
| `Roles/Show.jsx` | 7, 30, 90 | `router.delete(\`/admin/roles/${role.id}\`)`, `<Link href="/admin/roles/...">` |
| `Roles/Edit.jsx` | 13, 126 | `put(\`/admin/roles/${role.id}\`)`, `<Link href="/admin/roles">` |
| `Roles/Create.jsx` | 15, 128 | `post('/admin/roles')`, `<Link href="/admin/roles">` |
| `Notifications/Index.jsx` | 166 | `<Link href="/admin/orders">` |
| `Billing/Index.jsx` | 22 | `router.post('/admin/billing/renew')` |
| `Permissions/Index.jsx` | 10 | `router.get('/admin/permissions')` |

**Phase 1 (completed)** fixed all 43 of these. See section 11.

### Risk 3: AdminSidebar.jsx + AppLayout.jsx Hardcoded hrefs

- **AdminSidebar.jsx**: 24+ raw `/admin/...` href strings in `menuSections`. These ARE passed through `adminUrl()` at render time (wrapped in `href={adminUrl(item.href)}`), so they work in both contexts. But the raw strings remain hardcoded.
- **AppLayout.jsx**: 10 raw `/admin/...` href strings in `adminMenu` array. Same pattern — wrapped in `adminUrl()` at render time.

**Risk**: LOW (wrapped) / MEDIUM (maintenance burden if the canonical pattern changes).

### Risk 4: Sidebar `isActive()` Correctly Matches Both Patterns

The `AdminSidebar.jsx` `isActive()` function tests both raw href and `adminUrl(href)`:
```js
const candidates = [href, adminUrl(href)];
return candidates.some(candidate => { ... });
```

This is correct for detecting the active menu item regardless of context.

---

## 6. Backend Redirect Risks

### Risk 1: `admin_redirect()` Depends on Request Context

```php
function admin_redirect(string $route, ...) {
    $storeSlug = request()->route('store_slug');
    if ($storeSlug) {
        $route = 'storefront.' . $route;
        $parameters['store_slug'] = $storeSlug;
    }
    return redirect()->route($route, $parameters, ...);
}
```

- In queued jobs, CLI commands, or testing without a request → `request()->route('store_slug')` returns `null` → always generates standalone `/admin/*` URLs
- The route name must exist in the `admin.*` naming convention for this to work
- Parameters must be compatible with both `admin.*` and `storefront.admin.*` route signatures (i.e., `store_slug` must be prepended)

**Usage across controllers**: ~100+ calls in 15 controllers.

### Risk 2: Middleware Redirects to Wrong Pattern

| Middleware | File | Line | Redirect Target | Should Be (when in storefront) |
|-----------|------|------|----------------|------------------------------|
| `EnsureTenantIsActive` | `app/Http/Middleware/EnsureTenantIsActive.php` | 30, 36, 41 | `route('admin.suspended')` | `route('storefront.admin.suspended')` |
| `EnsureTenantIsActive` | same | 67 | `route('admin.dashboard')` | `route('storefront.admin.dashboard')` |
| `CheckUserStatus` | `app/Http/Middleware/CheckUserStatus.php` | 36 | `route('admin.suspended')` | `route('storefront.admin.suspended')` |
| `SubscriptionIsActive` | `app/Http/Middleware/SubscriptionIsActive.php` | 30, 56 | `route('admin.dashboard')` | `route('storefront.admin.dashboard')` |

All four middleware classes unconditionally redirect to the standalone `admin.*` route names, even when the user is accessing a storefront-scoped admin URL.

**Risk**: HIGH — users lose store context on any tenant health check redirect.

---

## 7. Logout Risks

### AuthenticatedSessionController@destroy

```php
$storeSlug = $request->input('store_slug') ?: ($tenant ? $tenant->slug : null);
$context = $request->input('context');

if (!$context) {
    $referrer = $request->header('referer');
    if ($referrer) {
        if ($isSuperAdmin && str_contains($referrer, '/superadmin/')) $context = 'superadmin';
        elseif ($storeSlug && str_contains($referrer, "/store/{$storeSlug}/admin/")) $context = 'admin';
        elseif ($storeSlug && str_contains($referrer, "/store/{$storeSlug}/")) $context = 'storefront';
    }
}
```

**Risks:**
1. **Referrer header is unreliable** — can be blocked by CSP, spoofed, or missing entirely
2. **Store slug from POST** — frontend must explicitly send `store_slug` in the POST body; if omitted, context detection falls back to heuristics
3. **`admin` context logout without store_slug** → redirects to `route('admin.login')` (SuperAdmin page), not a store-specific page
4. **Fallback for non-SuperAdmin with no store slug** → `redirect('/')` (homepage), which is correct but fragile

### Frontend Logout POST

Both `AdminSidebar.jsx` and `AppLayout.jsx` send the store slug:
```js
router.post('/logout', {
    context: isSuperAdmin ? 'superadmin' : 'admin',
    store_slug: storeSlug,
});
```

This is correct — the store slug comes from `tenant?.slug` in the Inertia page props.

---

## 8. Controller Usage Summary

| # | Controller | Methods Used | admin_redirect() calls |
|---|-----------|-------------|----------------------|
| 1 | `AdminController` | `index` | 0 |
| 2 | `AdminBillingController` | `index`, `renew` | 1 |
| 3 | `AdminProductController` | `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `search`, `typeSelect`, `bulkDestroy`, `bulkActivate`, `bulkDeactivate` | 6 |
| 4 | `AdminOrderController` | `index`, `show`, `confirm`, `process`, `ship`, `deliver`, `cancel`, `verifyPayment`, `rejectPayment`, `markAsPaid`, `destroy`, `search` | 24 |
| 5 | `AdminOrderOverrideController` | `overrideOrderStatus`, `overridePaymentStatus` | 4 |
| 6 | `AdminCategoryController` | `index`, `create`, `store`, `edit`, `update`, `destroy`, `search` | 3 |
| 7 | `AdminBrandController` | `index`, `create`, `store`, `edit`, `update`, `destroy`, `search` | 3 |
| 8 | `AdminUnitController` | `index`, `create`, `store`, `edit`, `update`, `destroy`, `search` | 3 |
| 9 | `AdminPromotionController` | `index`, `create`, `store`, `edit`, `update`, `destroy`, `toggle`, `duplicate`, `search` | 5 |
| 10 | `AdminPromotionBannerController` | `index`, `create`, `store`, `edit`, `update`, `destroy`, `search` | 4 |
| 11 | `AdminCouponController` | `index`, `create`, `store`, `edit`, `update`, `destroy`, `search` | 3 |
| 12 | `AdminPaymentMethodController` | `index`, `create`, `store`, `edit`, `update`, `destroy`, `toggle` | 3 |
| 13 | `AdminCityController` | `index`, `create`, `store`, `edit`, `update`, `destroy`, `toggle`, `importMyanmar` | 4 |
| 14 | `AdminTownshipController` | `index`, `create`, `store`, `edit`, `update`, `destroy`, `toggle` | 3 |
| 15 | `AdminUserController` | `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `suspend`, `ban`, `activate` | 9 |
| 16 | `RoleController` | `index`, `create`, `store`, `show`, `edit`, `update`, `destroy` | 5 |
| 17 | `PermissionController` | `index` | 0 |
| 18 | `ActivityLogController` | `index`, `show` | 0 |
| 19 | `AdminReportController` | `sales`, `clearCache`, `orderDetails`, `productSales`, `payments`, `verifyPayment`, `rejectPayment` | 0 |
| 20 | `AdminPromotionReportController` | `index`, `getData` | 0 |
| 21 | `ChatController` | `getAdminUsers`, `fetchMessages`, `sendMessage`, `markAsRead`, `typing` | 0 |
| 22 | `NotificationController` | `adminPage` | 0 |
| 23 | `SettingsController` | `edit`, `update` | 0 |
| 24 | `AdminNotificationSettingsController` | `edit`, `update` | 1 |
| 25 | `TelegramIntegrationController` | `edit` | 0 |

**Total `admin_redirect()` calls: ~100+** across 15 controllers.

---

## 9. Compatibility Risks Summary

| ID | Risk | Severity | Description |
|----|------|----------|-------------|
| R1 | **Route name collision (`admin.login`)** | MEDIUM | `route('admin.login')` resolves to `/admin/login` (SuperAdmin) not to `/store/{slug}/admin/login`. |
| R2 | **Middleware redirects to wrong pattern** | HIGH | `EnsureTenantIsActive`, `CheckUserStatus`, `SubscriptionIsActive` all redirect to `admin.*` routes, breaking store context. |
| R3 | **Hardcoded `/admin` paths (pre-Phase 1)** | HIGH (fixed) | 43 paths in 7 files bypassed `adminUrl()`. **All now fixed.** |
| R4 | **`adminUrl()` context dependency** | MEDIUM | Reads store slug from `window.location`. Bookmarks, SSR, or direct navigation produce wrong URLs. |
| R5 | **`admin_redirect()` request dependency** | MEDIUM | Fails/wrong in queued jobs, CLI, or testing without request context. |
| R6 | **Dual route maintenance burden** | MEDIUM | ~160 route registrations for the same 25 controllers. Any new admin feature requires two files. |
| R7 | **`IdentifyTenant` vs `Storefront` override** | MEDIUM | Two different tenant resolution paths. `Storefront` overrides `IdentifyTenant` for storefront admin. |
| R8 | **Logout referrer heuristic** | LOW | Context detection relies on `Referer` header which can be missing/spoofed. |
| R9 | **Missing `whereNumber` constraints on standalone** | LOW | Storefront group has `->whereNumber(...)` on route model bindings; standalone group doesn't. |

---

## 10. Current State

```
                         ┌─────────────────────────────────────┐
                         │         IdentifyTenant (global)      │
                         │  (auth()->user()->tenant_id fallback)│
                         └──────────┬──────────────────────────┘
                                    │ sets current.tenant
                                    ▼
              ┌──────────────────────────────────────────┐
              │           /admin/*  (web.php:259)         │
              │  auth → role:admin → tenant.valid        │
              │                    → tenant.binding       │
              │         ┌─── tenant.active (inner) ───┐   │
              │         │   products, orders, ...      │   │
              │         └─────────────────────────────┘   │
              └──────────────────────────────────────────┘
                                    │
                                    ▼
              ┌──────────────────────────────────────────┐
              │   /store/{store_slug}/admin/*  (SFP:51)   │
              │  storefront → auth → role:admin          │
              │  → tenant.valid → tenant.access           │
              │  → tenant.binding                         │
              │         ┌─── tenant.active (inner) ───┐   │
              │         │   products, orders, ...      │   │
              │         └─────────────────────────────┘   │
              └──────────────────────────────────────────┘
                                    │
                                    ▼
              ┌──────────────────────────────────────────┐
              │     Both groups → same 25 Controllers     │
              │     Controllers use admin_redirect()       │
              │     Frontend uses adminUrl()              │
              └──────────────────────────────────────────┘
```

---

## 11. Phase 1 Completed: Hardcoded Paths Fixed

All 43 hardcoded `/admin` paths identified in the initial audit have been wrapped with `adminUrl()`:

| File | Paths Fixed |
|------|-------------|
| `Roles/Index.jsx` | 5 (search GET, delete, create link, view link, edit link) |
| `Roles/Show.jsx` | 3 (delete, edit link, back link) |
| `Roles/Edit.jsx` | 2 (PUT form, cancel link) |
| `Roles/Create.jsx` | 2 (POST form, cancel link) |
| `Notifications/Index.jsx` | 1 (view orders link) |
| `Billing/Index.jsx` | 1 (renew POST) |
| `Permissions/Index.jsx` | 1 (search GET) |

**Verification**: Build passes (2465 modules, 0 errors). All 43 storefront tests pass.

---

## 12. Recommended State

```
                         ┌─────────────────────────────────────┐
                         │        /store/{slug}/admin/*         │
                         │       (single canonical pattern)     │
                         │                                     │
                         │  storefront → auth → role:admin     │
                         │  → tenant.valid → tenant.access      │
                         │  → tenant.binding                    │
                         │       ┌── tenant.active (inner) ──┐  │
                         │       │  products, orders, ...    │  │
                         │       └──────────────────────────┘  │
                         └─────────────────────────────────────┘
                                     │
                                     ▼
                         ┌─────────────────────────────────────┐
                         │     Same 25 Controllers (unchanged)  │
                         │     admin_redirect() → always        │
                         │       storefront.admin.* routes      │
                         │     adminUrl() → always              │
                         │       /store/{slug}/admin/...        │
                         └─────────────────────────────────────┘
                                     ▲
                         ┌───────────┴───────────┐
                         │   Legacy /admin/*       │
                         │   (redirect middleware) │
                         │   GET → 302 to canonical│
                         │   POST → deprecation    │
                         │          warning header │
                         └───────────────────────┘
```

**Key changes:**
1. `/store/{slug}/admin/*` becomes **the only canonical admin route**
2. `/admin/*` becomes a **compatibility redirect** (Phase 3)
3. All middleware redirects use `storefront.admin.*` route names
4. `adminUrl()` generates `/store/{slug}/admin/...` unconditionally
5. `admin_redirect()` always generates `storefront.admin.*` routes

---

## 13. Migration Plan

### Phase 1: Fix Hardcoded Frontend References ✅ DONE

Replace all 43 hardcoded `/admin` paths in Roles (4 files), Notifications, Billing, and Permissions with `adminUrl()` calls.

### Phase 2: Standardize Backend Redirects (SHORT TERM)

1. Fix `EnsureTenantIsActive` middleware — detect store context and redirect to `storefront.admin.suspended` or `storefront.admin.dashboard` appropriately
2. Fix `CheckUserStatus` middleware — detect store context for suspension redirects
3. Fix `SubscriptionIsActive` middleware — detect store context for expiry redirects
4. Update `AuthenticatedSessionController@store` — always use `storefront.admin.dashboard` when tenant context is available
5. Update `AuthenticatedSessionController@destroy` — use session store slug instead of referrer header

### Phase 3: Add Deprecation Layer for `/admin/*` (MEDIUM TERM)

1. Wrap the `/admin/*` route group in a middleware that detects the user's tenant and redirects to the canonical `/store/{slug}/admin/*` URL
2. Remove the redundant route definitions — keep only a catch-all redirect:

```php
Route::prefix('admin')->middleware(['auth', 'role:admin'])->group(function () {
    Route::any('/{any?}', function ($any = '') {
        $storeSlug = auth()->user()->tenant?->slug ?? session('store_slug');
        if ($storeSlug) {
            return redirect()->permanent("/store/{$storeSlug}/admin/{$any}");
        }
        abort(403, 'No store context available for legacy admin routes.');
    })->where('any', '.*');
});
```

### Phase 4: Remove Legacy Routes (LONG TERM)

1. After verifying all traffic uses `/store/{slug}/admin/*` for 30+ days
2. Delete the `/admin/*` route group from `web.php`
3. Remove the standalone fallback from `admin_redirect()`
4. Remove standalone fallback from `adminUrl()`
5. Update `AdminSidebar.jsx` and `AppLayout.jsx` href strings to use store URLs directly

### Phase 5: Admin Layout Consolidation (LONG TERM)

1. `AdminSidebar.jsx` and `AppLayout.jsx` serve overlapping admin nav — consolidate into one
2. Standardize all admin pages to use a single layout

---

## 14. Migration Verification Checklist

| Check | Criteria |
|-------|----------|
| ✅ Build | `npm run build` passes with 0 errors (2465 modules) |
| ✅ Tests | `php artisan test --filter=Storefront` passes (43/43) |
| 🔲 Phase 2 | All middleware redirects use `route('storefront.admin.*')` |
| 🔲 Phase 3 | `/admin/*` GET requests redirect to `/store/{slug}/admin/*` |
| 🔲 Phase 3 | `/admin/*` POST/PUT/DELETE emit deprecation headers |
| 🔲 Phase 4 | No code references `admin.*` route names |
| 🔲 Phase 4 | `adminUrl()` always generates storefront-scoped URLs |
| 🔲 Phase 5 | Single admin layout in use |

---

## 15. Key Files

| File | Role |
|------|------|
| `routes/web.php` (lines 259–445) | Standalone `/admin/*` route group |
| `routes/storefront-admin.php` (lines 51–244) | Storefront `/store/{slug}/admin/*` route group |
| `bootstrap/helpers.php` (lines 65–81) | `admin_redirect()` helper definition |
| `resources/js/Utils/adminUrl.js` | `adminUrl()` frontend URL helper |
| `app/Http/Middleware/EnsureTenantIsActive.php` | `tenant.active` middleware (redirects to wrong pattern) |
| `app/Http/Middleware/CheckUserStatus.php` | Global user status middleware (redirects to wrong pattern) |
| `app/Http/Middleware/SubscriptionIsActive.php` | Subscription check middleware (redirects to wrong pattern) |
| `app/Http/Middleware/CheckTenantAccess.php` | `tenant.access` - cross-tenant guard (unique to storefront) |
| `app/Http/Middleware/Storefront.php` | Storefront tenant resolver (unique to storefront) |
| `app/Http/Middleware/IdentifyTenant.php` | Global tenant resolver (used by standalone) |
| `app/Http/Middleware/TenantIsValid.php` | `tenant.valid` - structural tenant check |
| `app/Http/Middleware/ValidateTenantBinding.php` | `tenant.binding` - route model binding validation |
| `resources/js/Components/AdminSidebar.jsx` | Admin sidebar navigation (24 hrefs via adminUrl) |
| `resources/js/Layouts/AppLayout.jsx` | Admin layout (10 hrefs via adminUrl) |
| `app/Http/Controllers/Auth/AuthenticatedSessionController.php` | Login/logout redirects |
| `app/Http/Controllers/StorefrontLoginController.php` | Storefront login redirects |
| `app/Http/Controllers/SuperAdmin/ImpersonationController.php` | Impersonation redirects |
| `bootstrap/app.php` | Middleware registration |
