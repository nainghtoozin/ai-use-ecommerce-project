# V3-B3-5C: Subscription State Middleware Audit & Fix

## Bug 1: Suspended merchant can still reach dashboard

**Root Cause:** The `/admin/dashboard` route was defined in the **outer** route group (behind only `auth`, `role:admin`, `tenant.valid`, `tenant.binding`), NOT behind the `tenant.active` middleware. The `tenant.active` middleware (`EnsureTenantIsActive`) only ran on the **inner** operations group (products, orders, settings, reports). The dashboard was unprotected from expired/suspended subscriptions.

**Fix:** Moved `/admin/dashboard` from the outer group into the inner group behind `tenant.active` + `tenant.locked` in both `routes/web.php` and `routes/storefront-admin.php`.

## Bug 2: ERR_TOO_MANY_REDIRECTS / stale Suspended page after reactivation

**Root Cause:** The `CheckUserStatus` middleware (global, runs on ALL web routes) checked `$user->tenant->status === 'suspended'` and redirected admin users to `admin.suspended`. But the suspended page route ITSELF also goes through `CheckUserStatus`, which checks the same condition and redirects again — creating an **infinite redirect loop** (`ERR_TOO_MANY_REDIRECTS`).

After reactivation, stale state could occur if the browser cached the Inertia response, or if the redirect loop prevented the new state from loading.

**Fix:** Added a route-name exemption in `CheckUserStatus.php`: when the current route is one of `admin.suspended`, `storefront.admin.suspended`, `admin.expired`, or `storefront.admin.expired`, the middleware passes through without redirecting. This breaks the redirect loop.

## Bug 3: Suspended page rendered inside admin layout

**Root Cause:** The suspended page (`Admin/Suspended.jsx`) imported `AdminLayout` which includes sidebar, navigation menu, and dashboard shell. The route closure rendered this component directly.

**Fix:** Created standalone pages (`Standalone/Expired.jsx`, `Standalone/Suspended.jsx`) that do NOT use `AdminLayout`. They render as minimal centered cards with the store logo, status message, action buttons, and a logout link. No sidebar. No admin menu. No dashboard shell.

## Files Modified

| File | Change |
|------|--------|
| `resources/js/Pages/Standalone/Expired.jsx` | **New** — Standalone expired page (no admin layout) |
| `resources/js/Pages/Standalone/Suspended.jsx` | **New** — Standalone suspended page (no admin layout) |
| `app/Http/Middleware/EnsureTenantIsActive.php` | Changed expired fallthrough redirect from `admin.dashboard` → `admin.expired`. Added `redirectToExpired()` method. Removed `redirectToDashboard()`. |
| `app/Http/Middleware/CheckUserStatus.php` | Added route-name exemption for suspended/expired pages to prevent redirect loop. |
| `routes/web.php` | Moved `/admin/dashboard` into `tenant.active` group. Added `/admin/expired` route (Standalone/Expired). Changed `/admin/suspended` to render Standalone/Suspended. |
| `routes/storefront-admin.php` | Added `/store/{slug}/admin/dashboard` inside `tenant.active` group. Added `/store/{slug}/admin/expired` route (Standalone/Expired). Changed `/store/{slug}/admin/suspended` to render Standalone/Suspended. |

## Middleware Flow (Corrected)

```
REQUEST → web middleware group (global):
  1. IdentifyTenant → resolves/loads tenant
  2. HandleInertiaRequests → shares subscription state
  3. CheckUserStatus → 
       user suspended/banned → logout
       tenant suspended AND !on suspended/expired page → redirect to suspended page
       tenant suspended AND on suspended/expired page → pass through ✓ (new)
  4. CheckMaintenanceMode → maintenance redirect

Admin routes (/admin/*):
  Outer: auth + role:admin + tenant.valid + tenant.binding
    /admin/expired → Standalone Expired (always accessible)
    /admin/suspended → Standalone Suspended (always accessible)
    /admin/billing → Billing page (accessible for renewal)
    /admin/billing/renew → Renew action (accessible for renewal)
    
  Inner [tenant.active + tenant.locked]:
    /admin/dashboard → Dashboard (MOVED from outer group)
    /admin/products → CRUD (blocked when expired/suspended)
    /admin/orders → CRUD
    /admin/settings → Settings
    /admin/reports → Reports

    tenant.active (EnsureTenantIsActive):
      pending → Suspended page
      suspended → Suspended page
      banned/inactive → Suspended page
      free plan → pass through
      locked → pass through (tenant.locked handles mutations)
      active/trialing sub → pass through
      canceled+future expiry → pass through
      expired → Expired page ✓ (was: redirect to dashboard)
      no subscription → pass through

    tenant.locked (CheckStoreLocked):
      GET/HEAD/OPTIONS → pass through
      POST/PUT/PATCH/DELETE → redirect back with error
```

## State Diagram

```
                    ┌─────────────┐
                    │   ACTIVE    │
                    └──────┬──────┘
                           │ expires_at past
                           ▼
                    ┌─────────────┐
                    │  PAST_DUE   │  ← 7 day grace period
                    └──────┬──────┘
                           │ 7 days past
                           ▼
                    ┌─────────────┐
                    │   EXPIRED   │──→ EnsureTenantIsActive redirects to
                    └──────┬──────┘    Standalone Expired page
                           │ 1 day
                           ▼
                    ┌─────────────┐
                    │  SUSPENDED  │──→ CheckUserStatus redirects to
                    └──────┬──────┘    Standalone Suspended page
                           │
                    reactivation (renew/assign)
                           │
                           ▼
                    ┌─────────────┐
                    │   ACTIVE    │──→ Dashboard accessible
                    └─────────────┘

No redirect loops:
  Suspended page route → CheckUserStatus → passes (route exemption) → renders Suspended
  Expired page route → EnsureTenantIsActive NOT present on outer group → renders Expired
  Dashboard → EnsureTenantIsActive → expired? → redirects to Expired (not dashboard!)
```

## Regression Risk

- **Low**: All existing route names unchanged (`admin.dashboard`, `admin.billing`, `admin.suspended`, etc.)
- Dashboard route URL unchanged (`/admin/dashboard`) but now behind `tenant.active` — any user with expired/suspended subscription who was relying on dashboard access will now be redirected to the appropriate standalone page.
- `Admin/Suspended.jsx` is no longer referenced by any route but is retained for safety.
- Billing routes remain accessible when expired (outer group) so renewal flow works.
- All 99 feature tests pass across 9 test suites (SubscriptionLockMode, TrialLifecycle, SubscriptionLimitService, PlatformSettings, MerchantManagement, FeatureGate, Storefront, StorefrontRegistration, StorefrontLogin).

## Manual Test Scenarios

1. **Active → Dashboard**
   - Login as merchant with active subscription → `/admin/dashboard` renders normally.

2. **Expire → Standalone Expired Page**
   - Set subscription to `expired` → try to access any inner route → redirected to `/admin/expired` (standalone, no sidebar).

3. **Renew from Expired Page**
   - On expired page, click "Renew Subscription" → POST to `/admin/billing/renew` → subscription activated → redirect to billing → navigate to dashboard → dashboard renders.

4. **Suspend → Standalone Suspended Page**
   - Expired + cron runs → tenant suspended → try any route → redirected to `/admin/suspended` (standalone, no sidebar). No redirect loop.

5. **Reactivate → Dashboard Restored**
   - SuperAdmin assigns active subscription → merchant refreshes → `/admin/dashboard` renders.

6. **Blocked Operations While Suspended**
   - Suspended merchant tries `/admin/products` → `EnsureTenantIsActive` redirects to suspended page.
