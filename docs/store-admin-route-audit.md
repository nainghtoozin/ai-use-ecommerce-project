# Store Admin Route Architecture Audit

> **Date:** 2026-06-05
> **Scope:** All route files, middleware, sidebar/menu components, redirects, and frontend route references.

---

## Target Architecture

| Role | URL Prefix | Named Route Prefix |
|---|---|---|
| SuperAdmin | `/superadmin/*` | `superadmin.*` |
| Merchant (Store Admin) | `/store/{store_slug}/admin/*` | **`storefront.admin.*`** (TBD) |
| Customer | `/store/{store_slug}/*` | `storefront.*` |

---

## Audit Verdict Summary

| Area | Status | Details |
|---|---|---|
| SuperAdmin routes | **PASS** | Already at `/superadmin/*` |
| Storefront routes | **PASS** | Already at `/store/{store_slug}/*` (recently fixed) |
| Merchant routes | **FAIL** | Currently at `/admin/*` — need to move to `/store/{store_slug}/admin/*` |
| Auth routes | **WARNING** | Global `/login`, `/register` exist alongside storefront equivalents |
| Route file structure | **WARNING** | All routes in single `web.php` — no `admin.php` file |
| Middleware stack | **WARNING** | `tenant.valid` + `tenant.active` need to work within storefront context |
| Sidebar menus | **FAIL** | All use hardcoded `/admin/...` URLs |
| Named route redirects | **FAIL** | 77 `admin.*` redirects in controllers |
| Frontend hardcoded URLs | **FAIL** | 40+ React pages use `/admin/...` strings |
| Blade admin views | **FAIL** | 10+ Blade templates use `route('admin.*')` |
| ziggy.js | **FAIL** | No `admin.*` routes defined at all |

---

## 1. All Current Route Groups

### 1.1 SuperAdmin Routes (`/superadmin/*`) — PASS

**File:** `routes/web.php:395-431`

Already matches target architecture. Middleware: `['auth', 'role:superadmin']`.

| Named Route | URL |
|---|---|
| `superadmin.dashboard` | `/superadmin` |
| `superadmin.tenants.*` | `/superadmin/tenants` (CRUD + toggle-status) |
| `superadmin.plans.*` | `/superadmin/plans` (CRUD) |
| `superadmin.subscriptions.*` | `/superadmin/subscriptions` (manage) |
| `superadmin.impersonate.start` | `/superadmin/impersonate/{user}` |
| `superadmin.impersonate.leave` | `/superadmin/impersonate/leave` |

### 1.2 Merchant Admin Routes (`/admin/*`) — FAIL

**File:** `routes/web.php:215-383`

Currently at `/admin/*` — must move to `/store/{store_slug}/admin/*`.
Middleware: `['auth', 'role:admin', 'tenant.valid']` + inner `['tenant.active']`.

**Full inventory (~60 routes):**

**Account (outside tenant.active):**
`admin.dashboard`, `admin.billing`, `admin.billing.renew`, `admin.suspended`

**Catalog:**
`admin.products.*` (index, create, store, show, edit, update, destroy, type-select, search, bulk-*)
`admin.categories.*` (index, create, store, edit, update, destroy, search)
`admin.promotions.*` (index, create, store, edit, update, destroy, toggle, duplicate, search)
`admin.banners.*` (index, create, store, edit, update, destroy, search)

**Orders:**
`admin.orders.*` (index, show, confirm, process, ship, deliver, cancel, verify-payment, reject-payment, mark-as-paid, override-status, override-payment, destroy, search)
`admin.payment-methods.*` (index, create, store, edit, update, destroy, toggle)

**Reports:**
`admin.reports.sales`, `admin.reports.sales.clear-cache`, `admin.reports.sales.order-details`
`admin.reports.product-sales`, `admin.reports.payments`
`admin.reports.payments.verify`, `admin.reports.payments.reject`

**Locations:**
`admin.cities.*`, `admin.townships.*`, `admin.locations.import-myanmar`

**System:**
`admin.users.*`, `admin.roles.*`, `admin.permissions.index`
`admin.activity-logs.*`, `admin.notifications.admin`
`admin.website-info.*`, `admin.settings.notifications`, `admin.settings.telegram-integration`
`admin.coupons.*`

**Chat (shared route name with global):**
`admin.chat.users`, `admin.chat.messages`, `admin.chat.send`, `admin.chat.read`, `admin.chat.typing`

### 1.3 Customer Storefront Routes (`/store/{store_slug}/*`) — PASS

**File:** `routes/web.php:94-124`

Already matches target architecture. Middleware: `['storefront']`.

**Public:**
`storefront.index`, `storefront.products`, `storefront.products.show`
`storefront.register` (GET/POST), `storefront.login` (GET/POST)
`storefront.cart`, `storefront.checkout`, `storefront.checkout.store`

**Authenticated (middleware: `auth`, prefix: `customer`):**
`storefront.customer.account`
`storefront.customer.orders`, `storefront.customer.orders.show`
`storefront.customer.orders.cancel`, `storefront.customer.orders.upload-payment`
`storefront.customer.addresses` (CRUD)

### 1.4 Global Auth Routes — WARNING

**File:** `routes/auth.php:1-59`

| Named Route | URL | Notes |
|---|---|---|
| `register` | `/register` | Global — conflicts with storefront.register |
| `login` | `/login` | Global — conflicts with storefront.login |
| `password.request` | `/forgot-password` | Global only |
| `password.reset` | `/reset-password/{token}` | Global only |
| `verification.*` | `/verify-email/*` | Global only |
| `password.confirm` | `/confirm-password` | Global only |
| `password.update` | `/password` | Global only |
| `logout` | `/logout` | Global |

**Issue:** No storefront-specific password reset, email verification, or confirm-password routes exist.

### 1.5 Global Authenticated Routes — WARNING

**File:** `routes/web.php:129-192`

| Named Route | URL | Notes |
|---|---|---|
| `profile.edit` | `/profile` | Global |
| `orders.index` | `/orders` | Duplicated by `client.orders.index` |
| `orders.show` | `/orders/{order}` | Duplicated by `client.orders.show` |
| `client.orders.index` | `/client/orders` | Alias for `orders.index` |
| `client.orders.show` | `/client/orders/{order}` | Alias for `orders.show` |
| `orders.cancel` | `/orders/{order}/cancel` | Global — duplicates storefront action |
| `orders.upload-payment` | `/orders/{order}/upload-payment` | Global — duplicates storefront action |
| `orders.confirm-payment` | `/orders/{order}/confirm-payment` | Global |
| `checkout.store` | `/checkout` | Global (POST) |

### 1.6 Global Cart Routes — WARNING

**File:** `routes/web.php:64-70`

| Named Route | URL |
|---|---|
| `cart.index` | `/cart` |
| `cart.store` | `/cart/add` |
| `cart.update` | `/cart/{id}` |
| `cart.destroy` | `/cart/{id}` |
| `cart.clear` | `/cart/clear` |
| `cart.apply-coupon` | `/cart/apply-coupon` |
| `cart.remove-coupon` | `/cart/remove-coupon` |
| `cart.apply-promotion` | `/cart/apply-promotion` |
| `cart.remove-promotion` | `/cart/remove-promotion` |

**Issue:** No tenant scoping. Session-based cart is shared across tenants.

---

## 2. Middleware Architecture

### 2.1 Current Middleware Stack for Merchant Admin (`/admin/*`)

```
web (global)
  ├── IdentifyTenant            → resolves tenant from user.tenant_id
  ├── HandleInertiaRequests     → shares data to Inertia
  ├── CheckUserStatus           → blocks suspended/banned users
  └── CheckMaintenanceMode      → blocks when maintenance on
        │
        auth                     → requires login
        role:admin               → requires 'admin' role (superadmin bypasses)
        tenant.valid             → validates user has a valid tenant (TenantIsValid)
              │
              tenant.active      → checks subscription status (EnsureTenantIsActive)
```

### 2.2 Essential Middleware Changes for `/store/{store_slug}/admin/*`

The new merchant admin needs a **composed middleware** that includes:
- `storefront` — to resolve tenant from URL slug
- `auth` — authentication
- `role:admin` — role check
- `tenant.valid` — tenant existence check
- `tenant.active` — subscription health

**Key difference from current `/admin/*`:** Tenant is resolved from the URL `store_slug` parameter (via `Storefront` middleware) rather than from the user's `tenant_id` (via `IdentifyTenant`). The `IdentifyTenant` global middleware will still run first and may set a conflicting `current.tenant` — needs resolution strategy.

### 2.3 Existing Middleware Summary

| Middleware | Class | Current Usage |
|---|---|---|
| `storefront` | `Storefront.php` | Public storefront only |
| `tenant.valid` | `TenantIsValid.php` | Merchant admin + global (via IdentifyTenant) |
| `tenant.active` | `EnsureTenantIsActive.php` | Merchant admin (inner group) |
| `role` | `RoleMiddleware.php` | SuperAdmin + Merchant admin |
| `check.status` | `CheckUserStatus.php` | Global web group |
| `maintenance` | `CheckMaintenanceMode.php` | Global web group |

---

## 3. Sidebar/Menu Dependencies

All menu components must be updated to use `/store/{store_slug}/admin/...` URLs.

### 3.1 React `AdminSidebar.jsx` (Primary) — FAIL

**File:** `resources/js/Components/AdminSidebar.jsx`

Uses **~22 hardcoded `/admin/...` URLs** + **5 hardcoded `/superadmin/...` URLs**.

All `href` values are plain strings inside a `menuSections` array. No named routes are used.
Active detection uses string prefix matching (`url.startsWith(hrefPath + '/')`).

### 3.2 React `AppLayout.jsx` (Legacy/Older) — FAIL

**File:** `resources/js/Layouts/AppLayout.jsx`

Two hardcoded menus:
- `adminMenu` — 10 hardcoded `/admin/...` URLs
- `clientMenu` — 5 hardcoded paths (`/`, `/client/dashboard`, `/cart`, `/orders`, `/checkout`)

No named routes. Active detection via string prefix matching.

### 3.3 Blade `sidebar.blade.php` — WARNING

**File:** `resources/views/admin/partials/sidebar.blade.php`

Uses Laravel named routes (`route('admin.*')`) — easiest to migrate since route names can be aliased.

### 3.4 Blade `navbar.blade.php` — WARNING

**File:** `resources/views/admin/partials/navbar.blade.php`

References `route('admin.chat.users')`, `route('profile.edit')`.

### 3.5 Blade `admin.blade.php` Layout — WARNING

**File:** `resources/views/admin/layouts/admin.blade.php`

Includes `admin.partials.sidebar` and `admin.partials.navbar`.

---

## 4. Redirect Dependencies

### 4.1 Controllers Redirecting to `admin.*` Routes (77 occurrences)

Controllers that must be updated to use the new `/store/{store_slug}/admin/...` route names:

| Controller | Route(s) Referenced | Count |
|---|---|---|
| `AdminOrderController` | `admin.orders.show`, `admin.orders.index` | 24 |
| `AdminOrderOverrideController` | `admin.orders.show` | 4 |
| `AdminUserController` | `admin.users.index` | 10 |
| `AdminProductController` | `admin.products.index` | 6 |
| `AdminCategoryController` | `admin.categories.index` | 3 |
| `AdminPromotionController` | `admin.promotions.index`, `admin.promotions.edit` | 5 |
| `AdminPromotionBannerController` | `admin.banners.index` | 4 |
| `AdminCouponController` | `admin.coupons.index` | 3 |
| `AdminBillingController` | `admin.billing` | 1 |
| `AdminCityController` | `admin.cities.index` | 4 |
| `AdminTownshipController` | `admin.townships.index` | 3 |
| `AdminPaymentMethodController` | `admin.payment-methods.index` | 3 |
| `AdminNotificationSettingsController` | `admin.settings.notifications` | 1 |
| `RoleController` | `admin.roles.index` | 5 |
| `SettingsController` | (back) | 1 |

### 4.2 Redirects Requiring Updated Logic — WARNING

These redirects need condition awareness (are we in storefront admin context?):

| File | Current Redirect | Context Problem |
|---|---|---|
| `AuthenticatedSessionController.php:69` | `admin.dashboard` | After global login — need to check if user has a tenant |
| `RegisteredUserController.php:89` | `admin.dashboard` | After storefront registration for admin |
| `StorefrontLoginController.php:96` | `admin.dashboard` | After storefront login for admin |
| `ImpersonationController.php:85` | `admin.dashboard` | After impersonation |
| `EnsureTenantIsActive.php:52` | `admin.suspended` | Subscription expired redirect |
| `EnsureTenantIsActive.php:60` | `admin.dashboard` | Suspended tenant redirect |

### 4.3 Controllers Redirecting to `storefront.*` Routes — PASS

Already correct:
- `StorefrontLoginController` → `storefront.index`
- `StorefrontCustomerController` → `storefront.customer.*`
- `StorefrontCheckoutController` → `storefront.*`
- `RegisteredUserController` → `storefront.index` (for customer registrations)

---

## 5. Frontend Hardcoded URL Inventory

### 5.1 React Pages Using `/admin/...` Hardcoded URLs — FAIL

All pages under `resources/js/Pages/Admin/` use hardcoded `/admin/...` strings for links and redirects:

| Page | Hardcoded URLs |
|---|---|
| `Dashboard.jsx` | `/admin/billing`, `/admin/orders`, `/admin/products/{id}/edit` |
| `Products/Index.jsx` | `/admin/products/type-select` |
| `Products/Show.jsx` | `/admin/products`, `/admin/products/{id}/edit` |
| `Orders/Index.jsx` | `/admin/orders/{id}` |
| `Orders/Show.jsx` | `/admin/orders` |
| `Users/Index.jsx` | `/admin/users/create`, `/admin/users/{id}`, `/admin/users/{id}/edit` |
| `Users/Create.jsx` | `/admin/users` |
| `Users/Edit.jsx` | `/admin/users` |
| `Users/Show.jsx` | `/admin/users/{id}/edit`, `/admin/users` |
| `Categories/Index.jsx` | `/admin/categories/create` |
| `Categories/Create.jsx` | `/admin/categories` |
| `Categories/Edit.jsx` | `/admin/categories` |
| `Promotions/Index.jsx` | `/admin/promotions/create` |
| `Promotions/Create.jsx` | `/admin/promotions` |
| `Promotions/Edit.jsx` | `/admin/promotions` |
| `PaymentMethods/Index.jsx` | `/admin/payment-methods/create`, `/admin/payment-methods/{id}/edit` |
| `PaymentMethods/Create.jsx` | `/admin/payment-methods` |
| `PaymentMethods/Edit.jsx` | `/admin/payment-methods` |
| `Cities/Index.jsx` | `/admin/cities/create`, `/admin/cities/{id}/edit` |
| `Cities/Create.jsx` | `/admin/cities` |
| `Cities/Edit.jsx` | `/admin/cities` |
| `Townships/Index.jsx` | `/admin/townships/create`, `/admin/townships/{id}/edit` |
| `Townships/Create.jsx` | `/admin/townships` |
| `Townships/Edit.jsx` | `/admin/townships` |
| `Roles/Index.jsx` | `/admin/roles/create`, `/admin/roles/{id}`, `/admin/roles/{id}/edit` |
| `Roles/Create.jsx` | `/admin/roles` |
| `Roles/Edit.jsx` | `/admin/roles` |
| `Roles/Show.jsx` | `/admin/roles/{id}/edit`, `/admin/roles` |
| `ActivityLogs/Show.jsx` | `/admin/activity-logs` |
| `Notifications/Index.jsx` | `/admin/orders` |
| `Reports/Payments.jsx` | `/admin/orders/{id}` |

### 5.2 Components Using `/admin/...` Hardcoded URLs — FAIL

| Component | Hardcoded URLs |
|---|---|
| `AdminSidebar.jsx` | 22 `/admin/...` + 5 `/superadmin/...` |
| `AppLayout.jsx` | 10 `/admin/...` in adminMenu |
| `ShopNavbar.jsx` | (no `/admin/...` — PASS) |
| `NotificationBell.jsx` | `/admin/notifications`, `/admin/orders` |
| `ShopFooter.jsx` | `/client/contact`, `/client/faq`, `/client/about`, `/client/privacy`, `/client/terms` |

### 5.3 Blade Templates Using `route('admin.*')` — WARNING

| Template | Route Names |
|---|---|
| `sidebar.blade.php` | `admin.dashboard`, `admin.categories.index`, `admin.products.index`, `admin.banners.index`, `admin.promotions.index`, `admin.orders.index`, `admin.payment-methods.index`, `admin.cities.index`, `admin.townships.index`, `admin.website-info.edit`, `admin.settings.edit` |
| `admin/orders/show.blade.php` | `admin.orders.verify-payment`, `admin.orders.confirm`, `admin.orders.process`, `admin.orders.ship`, `admin.orders.deliver`, `admin.orders.cancel`, `admin.orders.destroy`, `admin.orders.index`, `admin.orders.reject-payment`, `admin.orders.override-status`, `admin.orders.override-payment` |
| `admin/orders/index.blade.php` | `admin.orders.index`, `admin.orders.show` |

---

## 6. ziggy.js Route Map — FAIL

**File:** `resources/js/ziggy.js`

Only 18 route patterns defined — all storefront or a few global routes.
**No `admin.*` routes exist in ziggy.js.**

Routes missing from ziggy.js:

```
All ~60 admin.* routes
All ~15 superadmin.* routes
All auth routes (login, register, password.*, verification.*)
All cart.* routes
All orders.* routes (except cancel, upload-payment, confirm-payment)
All client.* routes
All profile.* routes
All chat.* routes
All notification.* routes
All wishlist.* routes
```

---

## 7. Migration Impact Assessment

### Scale of Work
| Category | Items to Change |
|---|---|
| Route definitions (web.php) | Move ~60 admin routes to storefront admin group |
| PHP Controllers | ~77 `redirect()->route('admin.*')` calls → new prefix |
| Middleware | Compose `storefront` + `auth` + `role:admin` + `tenant.*` |
| Blade templates | ~15 `route('admin.*')` calls in 3+ files |
| React `AdminSidebar.jsx` | 22 hardcoded `/admin/...` → dynamic/storefront-aware |
| React `AppLayout.jsx` | 10 `/admin/...` + 5 client paths |
| React Admin Pages | 30+ files with hardcoded `/admin/...` back-links |
| `NotificationBell.jsx` | 2 hardcoded `/admin/...` |
| `ziggy.js` | Add all admin route patterns (or switch to full Ziggy) |
| Notifications | 5 notification classes reference `admin.billing`/`admin.suspended` |
| Tests | Test assertions referencing `admin.*` routes |

### Complexity Rating: **HIGH**

The fact that all React admin pages use **hardcoded string URLs** rather than named routes means there is no single translation point. Each `href="/admin/products"` must be individually changed to a tenant-aware equivalent.

### Recommended Approach

1. **Define** new route group under `/store/{store_slug}/admin/...` with appropriate composed middleware
2. **Alias** old `admin.*` route names to the new URLs (dual-run) so Blade templates work unchanged
3. **Create** a `storeUrl(path)` or `adminUrl(path)` frontend helper similar to the one in `ShopNavbar.jsx`
4. **Migrate** React components incrementally — start with `AdminSidebar.jsx` and `AppLayout.jsx`
5. **Update** ziggy.js with new route patterns
6. **Remove** old `/admin/*` routes after all references migrated
7. **Update** notification redirects to use tenant-aware URLs

---

## 8. Key Observations

1. **Two separate admin UIs exist:** The React `AdminLayout.jsx` (Inertia SPA) is the modern interface. The Blade templates (`admin.blade.php`) are legacy/fallback. Both serve the same routes.

2. **Cart is session-based and cross-tenant:** The global `CartController` stores cart in the session with no tenant isolation. Only the storefront cart (`StorefrontCartController`) filters by tenant. This means a customer could add items at one store then see them at another.

3. **Auth routes have no storefront equivalents for password reset or email verification:** Customers on storefronts cannot reset passwords or verify emails within the tenant context.

4. **RoleMiddleware accepts superadmin for admin routes:** Line 24-26 of `RoleMiddleware.php` allows superadmins through `role:admin` checks — important to preserve when migrating.

5. **`AppLayout.jsx` still widely used:** It renders the admin sidebar for admin users and a top navbar for non-admin users. It has its own inline menu definitions separate from `AdminSidebar.jsx`.

6. **IdentifyTenant runs globally and may conflict:** It sets `current.tenant` based on `user.tenant_id` before the storefront middleware runs. When both `/admin` and `/store/{slug}/admin` resolve different tenants, there could be a conflict.
