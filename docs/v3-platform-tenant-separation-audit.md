# V3-1: Platform & Tenant Separation Audit

## Status: Completed (Read-Only Audit)

---

## 1. Executive Summary

The current architecture has **no physical separation** between Platform (SuperAdmin) and Tenant (Merchant Admin) layers. Both layers share the same `AdminLayout`, `AdminSidebar`, and root-level controllers (Chat, Notifications, Profile). Superadmin access is granted by `role:superadmin` middleware on route groups; tenant admin access is granted by `role:admin` middleware. There is no `admin.access` permission. The `ImpersonationController` already supports basic "Login As Merchant" but lacks session isolation and permission limiting. No `TenantBootstrapService` exists — bootstrap logic is duplicated across 3 controllers. An `is_owner` flag exists on the User model but has no dedicated middleware or protection layer.

**56 controllers total:** 5 platform (SuperAdmin/), 22 tenant (Admin/), 29 mixed or public.

---

## 2. Route Classification

### Total Routes: ~200 (including name-prefixed groups)

### PLATFORM Routes (SuperAdmin) — ~20 routes

| Prefix | Middleware | Routes | Purpose |
|--------|-----------|--------|---------|
| `/superadmin` | `auth`, `role:superadmin` | dashboard | Platform overview |
| `/superadmin/tenants` | `auth`, `role:superadmin` | CRUD + toggle-status, destroy | Merchant management |
| `/superadmin/plans` | `auth`, `role:superadmin` | CRUD | Subscription plans |
| `/superadmin/subscriptions` | `auth`, `role:superadmin` | index, show, assign, change-plan, renew, cancel, suspend, activate | Subscription management |
| `/superadmin/impersonate/{user}` | `auth`, `role:superadmin` | start | Login As Merchant |
| `/superadmin/impersonate/leave` | `auth` | leave | Exit impersonation |
| `/superadmin/run-migrate` | `auth`, `role:superadmin` | run-migrate | Platform utility |
| `/superadmin/login` | `guest` | login page | Superadmin login |

### TENANT Routes (Merchant Admin) — ~110 routes

| Prefix | Middleware | Routes | Purpose |
|--------|-----------|--------|---------|
| `/admin/dashboard` | `auth`, `role:admin`, `tenant.valid`, `tenant.binding` | dashboard | Tenant dashboard |
| `/admin/billing` | same as above | billing, renew | Tenant billing |
| `/admin/*` (operations) | above + `tenant.active` | products, orders, categories, brands, units, payment-methods, cities, townships, coupons, promotions, banners, reports, users, roles, permissions, activity-logs, notifications, settings, website-info, telegram-integration, chat | Tenant operations |
| `/store/{slug}/admin/*` | `storefront`, `auth`, `role:admin`, `tenant.valid`, `tenant.access`, `tenant.binding` [+ `tenant.active`] | Same CRUD as above but scoped to store slug | Tenant admin via storefront |

### MIXED Routes — ~10 routes

| URI | Middleware | Users | Issue |
|-----|-----------|-------|-------|
| `/chat/*` | `auth` | Admin + Customer | Shared controller |
| `/notifications/*` | `auth` | Admin + Customer | Shared controller |
| `/profile/*` | `auth` | All authenticated | Shared controller |
| `/admin/activity-logs` | `auth`, `role:admin` | Admin + Superadmin (bypass in RoleMiddleware) | Superadmin can access tenant routes |
| `/admin/website-info/edit` | `auth`, `role:admin` | Admin + Superadmin | Platform setting in tenant section |
| `/admin/roles/*` | `auth`, `role:admin` | Admin + Superadmin | Superadmin sees global roles in tenant UI |

### PUBLIC Routes — ~60 routes

| Prefix | Purpose |
|--------|---------|
| `/` | Landing page |
| `/products` | Public product listing |
| `/cart/*` | Shopping cart |
| `/checkout` | Checkout |
| `/client/*` | Client pages (about, contact, FAQ, etc.) |
| `/store/{slug}/*` | Storefront (products, cart, checkout, customer account) |
| `/register`, `/login`, `/forgot-password` | Authentication |

---

## 3. Controller Classification

### PLATFORM Controllers (5) — `app/Http/Controllers/SuperAdmin/`

| Controller | Routes | Purpose | Tenant Scope? |
|-----------|--------|---------|--------------|
| `DashboardController` | 1 | Platform dashboard stats | No |
| `TenantController` | 9 | Merchant CRUD, status toggle | No (manages tenants) |
| `PlanController` | 6 | Subscription plan CRUD | No |
| `SubscriptionController` | 9 | Subscription lifecycle | No (manages all) |
| `ImpersonationController` | 2 | Login As Merchant start/leave | No |

### TENANT Controllers (22) — `app/Http/Controllers/Admin/`

| Controller | Routes | Purpose | Platform Scope? |
|-----------|--------|---------|----------------|
| `AdminController` | 1 | Tenant dashboard | Redirects superadmin to /superadmin |
| `AdminBillingController` | 2 | Tenant billing/renew | No |
| `AdminProductController` | 13 | Product CRUD | No |
| `AdminCategoryController` | 6 | Category CRUD | No |
| `AdminBrandController` | 6 | Brand CRUD | No |
| `AdminUnitController` | 6 | Unit CRUD | No |
| `AdminOrderController` | 13 | Order management + status transitions | No |
| `AdminOrderOverrideController` | 2 | Override status/payment | Checked via permissions |
| `AdminPaymentMethodController` | 6 | Payment method CRUD | No |
| `AdminCouponController` | 8 | Coupon CRUD | No |
| `AdminPromotionController` | 9 | Promotion CRUD + toggle/duplicate/reports | No |
| `AdminPromotionBannerController` | 6 | Banner CRUD | No |
| `AdminPromotionReportController` | 2 | Promotion reports | No |
| `AdminReportController` | 5 | Sales/product/payment reports | No |
| `AdminCityController` | 6 | City CRUD + toggle + import | No |
| `AdminTownshipController` | 6 | Township CRUD + toggle | No |
| `AdminUserController` | 9 | Tenant user management | No |
| `RoleController` | 6 | Role CRUD | Query filters by tenant |
| `PermissionController` | 5 | Permission CRUD | Query filters by tenant |
| `SettingsController` | 1 (or more) | Settings management | No |
| `AdminNotificationSettingsController` | 2 | Notification prefs | No |
| `ActivityLogController` | 2 | Activity logs | Filtered by tenant |

### MIXED Controllers (16 root) — `app/Http/Controllers/`

| Controller | Routes | Users | Risk | Separation Needed? |
|-----------|--------|-------|------|-------------------|
| `CartController` | 5 | Customer + Guest | None | Low |
| `ChatController` | 6 | Admin + Customer | **Medium** — admin sees all chats | Refactor: split AdminChat |
| `CheckoutController` | 2 | Customer + Guest | None | Low |
| `CreateStoreController` | 3 | Guest | None | Low |
| `NotificationController` | 8 | Admin + Customer | Low | Low (already filtered) |
| `OrderController` | 7 | Customer | None | Low |
| `ProfileController` | 3 | All authenticated | Low | Low |
| `StorefrontController` | 8 | Customer + Guest | None | Low |
| `StorefrontCartController` | 6 | Customer + Guest | None | Low |
| `StorefrontCheckoutController` | 2 | Customer | None | Low |
| `StorefrontCustomerController` | 7 | Customer | None | Low |
| `StorefrontLoginController` | 4 | Guest | None | Low |
| `TelegramIntegrationController` | 6 | Admin | **Low** | Could split into Admin |
| `TelegramWebhookController` | 1 | Public webhook | None | Low |
| `WishlistController` | 6 | Customer | None | Low |

### AUTH Controllers (9) — `app/Http/Controllers/Auth/`

Controllers for login, registration, password reset, email verification. Used by all user types. The `AuthenticatedSessionController` handles both admin and customer login context. Not separation-critical but creates complexity in `store()` redirect logic.

### CLIENT Controllers (3)

| Controller | Routes | Purpose | Platform/Tenant |
|-----------|--------|---------|-----------------|
| `ClientController` | ~5 | Public product browsing | Neither |
| `ClientOrderController` | 2 | Customer order history | Customer |
| `StaticPagesController` | 5 | About, Contact, FAQ, Privacy, Terms | Neither |

### API Controller (1)

| Controller | Routes | Purpose |
|-----------|--------|---------|
| `Api\LocationController` | 2 | Public location/township data |

---

## 4. Authentication Analysis

### Authentication Flows

| Flow | Route | Controller | Authentication | Session | Isolation |
|------|-------|-----------|---------------|---------|-----------|
| Superadmin Login | `/superadmin/login` | Auth\AuthenticatedSessionController | `credentials` → `role:superadmin` check | Standard `web` guard | Guest-only route, no isolation |
| Tenant Admin Login | `/store/{slug}/admin/login` | StorefrontLoginController | `credentials` → `role:admin` check | Standard `web` guard | Store-scoped via storefront middleware |
| Customer Login | `/store/{slug}/login` | StorefrontLoginController | `credentials` → `role:customer` | Standard `web` guard | Store-scoped |
| Platform Login | `/login` | Auth\AuthenticatedSessionController | Standard Laravel auth | Standard `web` guard | No role restriction |
| Store Register | `/store/{slug}/register` | StorefrontController | Creates customer user | Standard `web` guard | Store-scoped |

### Critical Observations

1. **No separate auth guard** — All users (superadmin, admin, customer) authenticate through the same `web` guard. Separation is enforced by route middleware (`role:superadmin`, `role:admin`) and redirect logic in `AuthenticatedSessionController`.

2. **`AuthenticatedSessionController` handles all logins** — Line 31 checks: "Tenant users (customers and admins) must login through their store URL." Redirects to storefront admin login if a tenant user tries to login through `/login`. This creates complex conditional logic.

3. **Impersonation uses session-based switching** — `ImpersonationController::start()` logs out the superadmin and logs in as the target user. The original superadmin ID is stored in `session('impersonator_id')`. On `leave()`, it logs back in as the superadmin. This works but lacks: separate session namespace, permission scoping, activity audit trail per action.

4. **No MFA/2FA** — No multi-factor authentication for either platform or tenant layer.

5. **Shared password reset** — All users share the same password reset flow (`/forgot-password`). No distinction between platform and tenant password resets.

---

## 5. Authorization Analysis

### Authorization Mechanisms

| Mechanism | Scope | Configuration |
|-----------|-------|---------------|
| **RoleMiddleware** | Route group | Checks `auth`, then `hasRole($role)`. Superadmin bypass for `admin` routes. Custom roles with permissions also pass admin check. |
| **Permission checks** | Controller | `auth()->user()->can('permission.name')` — 77 permissions, checked per controller action |
| **Gates/ Policies** | Model | `UserPolicy`, `CustomerOrderPolicy`, `CustomerAddressPolicy` — registered in `AppServiceProvider` |
| **TenantScope** | Model | Global scope on all tenant-aware models filters by `tenant_id` |

### Mixed Permissions Analysis

| Permission | Used By Platform? | Used By Tenant? | Correct? |
|-----------|------------------|-----------------|----------|
| `users.view` | No (superadmin has own tenant mgmt) | Yes | ✅ Correct |
| `roles.view` | No | Yes | ✅ Correct |
| `products.view` | No | Yes | ✅ Correct |
| `dashboard.view` | No (superadmin has own dashboard) | Yes | ✅ Correct |
| `billing.view` | No | Yes | ✅ Correct |
| `settings.website` | **Yes** (superadmin edits via /admin ) | Yes | ⚠️ Mixed — superadmin accesses tenant route |
| `settings.edit` | No | Yes | ✅ Correct |

### Authorization Gaps

1. **`admin.access` permission does not exist** — Admin panel access is gated by `role:admin` middleware, not by a permission. Custom roles are allowed in if they hold any permission, but there is no explicit "access admin panel" gate.

2. **No platform-specific permission set** — Superadmin routes use `role:superadmin` middleware, not permissions. There are no `platform.tenants.view`, `platform.plans.manage`, etc., permissions.

3. **RoleMiddleware bypass** — Any user with ANY permission can access the admin panel (line: `if ($role === 'admin' && $user->getAllPermissions()->isNotEmpty())`). This means a customer who has been granted a single permission (e.g., `orders.view-own`) can access admin routes.

---

## 6. Role Architecture Analysis

### Current Role Classification

| Role | Type | Scope | Protection | Purpose |
|------|------|-------|-----------|---------|
| `superadmin` | Platform | Global (tenant_id=NULL) | Protected (V2-C) | Platform administrator |
| `admin` | Tenant | Per-tenant (tenant_id=T) | Protected (V2-C) | Merchant admin |
| `customer` | Tenant | Per-tenant (tenant_id=T) | Editable (V2-C) | Store customer |
| `admins` | Tenant | T2 only (naming error) | NOT protected | Should be `admin` |
| `Manager` | Tenant | T2 only (capitalized) | NOT protected | Custom role, case issue |
| `Managers` | Tenant | T15 only (plural+capital) | NOT protected | Custom role, case issue |

### Desired V3 Architecture

| Role | Layer | Scope | Protection | Notes |
|------|-------|-------|-----------|-------|
| `superadmin` | Platform | Global | Protected | Already exists |
| `owner` | Tenant | Per-tenant | Protected | New — distinct from admin, owns the store |
| `admin` | Tenant | Per-tenant | Protected | Merchant staff with full access |
| `manager` | Tenant | Per-tenant | Standardized | Rename from `Manager`/`Managers` |
| `staff` | Tenant | Per-tenant | Standardized | New — limited access |
| `customer` | Tenant | Per-tenant | Editable | Already exists |

### Migration Impact

| Change | Impact | Complexity |
|--------|--------|-----------|
| Rename `admins` → `admin` (T2) | Changes role name, FK intact | Low |
| Rename `Manager` → `manager` | Case change, convention | Low |
| Rename `Managers` → `manager` | Case + plural fix | Low |
| Add `owner` role | New role, needs bootstrap | Medium |
| Add `staff` role | New role, needs permissions | Medium |
| Create `admin.access` permission | New permission, needs middleware | High (affects RoleMiddleware) |

---

## 7. Settings Analysis

### Current Settings Architecture

| Setting Module | Controller | Layer | Platform? | Tenant? | Issue |
|---------------|-----------|-------|-----------|---------|-------|
| **Website Info** | `SettingsController` | `/admin/website-info/edit` | **Yes** (via superadmin navbar) | **Yes** | Shared — superadmin uses tenant route |
| **Notification Settings** | `AdminNotificationSettingsController` | `/admin/settings/notifications` | No | Yes | ✅ Correct |
| **Telegram Integration** | (via settings page) | `/admin/settings/telegram-integration` | No | Yes | ✅ Correct |
| **Payment Methods** | `AdminPaymentMethodController` | `/admin/payment-methods` | No | Yes | ✅ Correct |
| **SEO/Settings** | (in Website Info) | `/admin/website-info/edit` | No | Yes | Bundled with website info |

### Settings Classification

| Setting | Platform-Controlled? | Tenant-Controlled? | V3 Recommendation |
|---------|---------------------|-------------------|-------------------|
| Site name | Yes (global default) | Yes (per-tenant) | Platform default, tenant override |
| Branding | Yes (global default) | Yes (per-tenant) | Platform default, tenant override |
| Contact info | Yes (global) | Yes (per-tenant) | Separate platform vs tenant |
| SEO meta | No | Yes | Keep tenant-only |
| Social links | No | Yes | Keep tenant-only |
| Payment methods | No | Yes | Keep tenant-only |
| Telegram bot | No | Yes | Keep tenant-only |
| Notification prefs | No | Yes | Keep tenant-only |
| Plans | Yes | No | Keep platform-only |
| Subscription settings | Yes | No | Keep platform-only |

**Critical Issue:** The `WebsiteInfo` model has a single global record (id=1). There is no per-tenant settings model. The `WebsiteInfoSeeder` creates one global record, and both superadmin and tenant admin edit the SAME record through `/admin/website-info/edit`. This is a **data isolation failure** — tenant A's settings overwrite tenant B's.

---

## 8. Subscription Analysis

### Current Architecture

| Component | Model | Controller | Layer | Data Scope |
|-----------|-------|-----------|-------|-----------|
| Plans | `Plan` | `SuperAdmin\PlanController` | Platform | Global (no tenant_id) |
| Subscriptions | `Subscription` | `SuperAdmin\SubscriptionController` | Platform | Per-tenant (has tenant_id) |
| Billing (tenant) | Subscription | `Admin\AdminBillingController` | Tenant | Read-only for tenant |

### Data Flow

```
Platform Layer:
  SuperAdmin → PlanController → Creates/Edits Plans
  SuperAdmin → SubscriptionController → Assigns/Changes/Cancels Subscriptions

Tenant Layer:
  Merchant → AdminBillingController → Views subscription, renews (requests)
  Merchant → Dashboard → Sees subscription status

CreateStoreController:
  Creates tenant → Assigns Free plan → Creates pending subscription

TenantController (superadmin):
  Creates tenant → Assigns selected plan → Creates active subscription
```

### Observations

1. **Plans are platform-owned** ✅ — Only superadmin can CRUD plans
2. **Subscriptions are platform-managed** ✅ — Only superadmin can assign/change/cancel
3. **Tenant billing is read-only with renew requests** ✅ — Tenant sees status, requests renewal
4. **Billing renewal lacks payment integration** — `AdminBillingController::renew()` currently sets status to active without payment processing
5. **No trial management** — `trial_ends_at` column exists on subscriptions but is not consistently used

---

## 9. Navigation Analysis

### AdminSidebar.jsx — Current Menu Classification

#### Superadmin Menu Items (when `is_superadmin`)

| Section | Item | Route | Permission | Layer |
|---------|------|-------|-----------|-------|
| Main | Dashboard | `/superadmin` | none | ✅ Platform |
| Merchant Mgmt | Merchants | `/superadmin/tenants` | none | ✅ Platform |
| Subscription Mgmt | Plans | `/superadmin/plans` | none | ✅ Platform |
| Subscription Mgmt | Subscriptions | `/superadmin/subscriptions` | none | ✅ Platform |
| System Mgmt | Website Info | `/admin/website-info/edit` | `settings.website` | ❌ Tenant route |
| Logs | Activity Logs | `/admin/activity-logs` | `activity-logs.view` | ❌ Tenant route |

#### Tenant Menu Items (when NOT `is_superadmin`)

| Section | Item | Route | Permission | Layer |
|---------|------|-------|-----------|-------|
| Main | Dashboard | `/admin/dashboard` | `dashboard.view` | ✅ Tenant |
| Main | Billing | `/admin/billing` | `billing.view` | ✅ Tenant |
| Catalog | Products | `/admin/products` | `products.view` | ✅ Tenant |
| Catalog | Categories | `/admin/categories` | `categories.view` | ✅ Tenant |
| Catalog | Brands | `/admin/brands` | `brands.view` | ✅ Tenant |
| Catalog | Units | `/admin/units` | `units.view` | ✅ Tenant |
| Catalog | Promotions | `/admin/promotions` | `promotions.view` | ✅ Tenant |
| Orders | Orders | `/admin/orders` | `orders.view` | ✅ Tenant |
| Orders | Payment Methods | `/admin/payment-methods` | `payments.view` | ✅ Tenant |
| Reports | Sales Report | `/admin/reports/sales` | `reports.view` | ✅ Tenant |
| Reports | Product Sales | `/admin/reports/product-sales` | `reports.products` | ✅ Tenant |
| Reports | Payments | `/admin/reports/payments` | `reports.payments` | ✅ Tenant |
| Locations | Cities | `/admin/cities` | `cities.view` | ✅ Tenant |
| Locations | Townships | `/admin/townships` | `townships.view` | ✅ Tenant |
| System | Users | `/admin/users` | `users.view` | ✅ Tenant |
| System | Roles & Permissions | `/admin/roles` | `roles.view` | ✅ Tenant |
| System | Activity Logs | `/admin/activity-logs` | `activity-logs.view` | ✅ Tenant |
| System | Notifications | `/admin/notifications` | none | ✅ Tenant |
| Config | Website Info | `/admin/website-info/edit` | `settings.website` | ✅ Tenant |
| Config | Notification Settings | `/admin/settings/notifications` | `settings.notifications` | ✅ Tenant |
| Config | Telegram Integration | `/admin/settings/telegram-integration` | `settings.telegram` | ✅ Tenant |
| Config | Settings | (none defined) | `settings.view` | ✅ Tenant |

### Navigation Issues

1. **Website Info is shared** — Superadmin sees "Website Info" under "System Management" and navigates to `/admin/website-info/edit`, the same route as tenants. This is a **tenant route in the platform menu**.

2. **Activity Logs is shared** — Superadmin navigates to `/admin/activity-logs` which is the tenant route. Superadmin sees ALL activity logs (not filtered by tenant).

3. **No platform-specific settings section** — Platform settings (site-wide defaults, platform branding, etc.) have no dedicated section in the superadmin sidebar.

4. **Billing visible to all tenant admins** — Billing menu item is gated by `billing.view` permission, but permission is likely assigned to all admin roles. Should be owner-only in V3.

---

## 10. Login As Merchant Readiness

### Current Implementation

**ImpersonationController** already exists with:
- `start(User $user)` — validates, stores session, logs in as user
- `leave()` — restores superadmin session
- Activity logging (start + stop events)
- Validation (cannot impersonate superadmin, suspended users, non-admin users, inactive tenants)
- Session regeneration on transitions
- Impersonation banner in `AdminHeader` (amber-colored warning bar)
- `is_impersonating` flag in `HandleInertiaRequests` shared data

### Gaps for V3

| Requirement | Status | Gap |
|-------------|--------|-----|
| Superadmin → Login As Merchant | ✅ Implemented | None |
| Session isolation | ⚠️ Partial | Uses same session, only `impersonator_id` stored |
| Permission limiting during impersonation | ❌ Missing | Superadmin retains ALL permissions while impersonating |
| Audit trail per action | ⚠️ Partial | Only start/stop logged, not individual actions |
| Cannot impersonate superadmin | ✅ Implemented | Hard check in `start()` |
| Exit impersonation | ✅ Implemented | `leave()` method + session cleanup |
| Navigation limitations | ❌ Missing | Impersonated user can navigate to superadmin routes |
| View store as merchant | ⚠️ Partial | Redirects to merchant dashboard, but no store preview mode |

### Required Changes for V3

| Change | Files | Complexity |
|--------|-------|-----------|
| Add `impersonator_id` to permission check override | `RoleMiddleware`, `HandleInertiaRequests` | Medium |
| Restrict superadmin routes during impersonation | `RoleMiddleware` (superadmin check bypass) | Low |
| Add impersonation session namespace | `ImpersonationController` | Low |
| Add `ImpersonatesUsers` trait or middleware | New middleware | Low |
| Add `disablePlatformAccess` on impersonation session start | `ImpersonationController::start()` | Low |

---

## 11. Tenant Bootstrap Readiness

### Current Flow (Public Store Creation)

```
CreateStoreController::store()
  1. Create Tenant (status: pending)
  2. Assign Free Plan → Create Subscription (status: pending)
  3. Create tenant-scoped admin + customer roles (clone from global templates)
  4. Create owner User (is_owner=true)
  5. Assign tenant-specific admin role to owner
  6. Sync all permissions to owner
  7. Dispatch Registered event

ActivateTenantOnVerified listener (on Verified event)
  1. Activate tenant (status: pending → active)
  2. Activate subscription (status: pending → active)
  3. Send WelcomeOwner notification
```

### Current Flow (SuperAdmin Tenant Creation)

```
TenantController::store()
  1. Create Tenant (status: configurable, default active)
  2. Assign Plan → Create Subscription
  3. Create tenant-scoped admin + customer roles (clone from global templates)
  4. Optionally create admin user (owner) with all permissions
```

### Missing Bootstrap Items

| Bootstrap Item | Current State | Should Be |
|---------------|--------------|-----------|
| Tenant-scoped roles (admin + customer) | ✅ Created | ✅ Keep |
| Owner user creation | ✅ Created | ✅ Keep |
| Subscription creation | ✅ Created | ✅ Keep |
| Default categories | ❌ Missing | Create per tenant |
| Default payment methods | ❌ Missing | Create per tenant |
| Default brands | ❌ Missing | Create per tenant |
| Default units | ❌ Missing | Create per tenant |
| Default website settings | ❌ Missing | Create per tenant |
| Default shipping settings | ❌ Missing | Create per tenant |
| Owner role (separate from admin) | ❌ Missing | Create if V3 adds owner role |
| Initial activity log entry | ❌ Missing | Log store creation |

### Refactor Complexity

| Component | Current | Target | Complexity | Files |
|-----------|---------|--------|-----------|-------|
| Role creation | Inline in 3 controllers | `TenantBootstrapService::bootstrap()` | Medium | 3 controllers → 1 service |
| Default data creation | Missing | `TenantBootstrapService::createDefaults()` | Medium | New service methods |
| Activation logic | Listener | Keep as listener (event-driven) | Low | No change |
| Plan assignment | Inline in 2 controllers | `TenantBootstrapService::assignPlan()` | Low | 2 controllers → 1 service |

---

## 12. Version 3 Readiness Matrix

| V3 Feature | Readiness | Critical Gaps | Dependencies | Complexity |
|-----------|-----------|--------------|--------------|-----------|
| **1. Superadmin Isolation** | ⚠️ Partial | Shared AdminLayout, no platform-specific middleware, platform routes sharing tenant routes | 1. Separate AdminLayout for superadmin 2. Add platform permission set 3. Move Website Info + Activity Logs to platform | **High** |
| **2. Login As Merchant** | ✅ Near-complete | Missing permission limiting, session isolation, route restriction | 1. Add middleware override during impersonation 2. Add session namespace | **Low** |
| **3. TenantBootstrapService** | ❌ Not started | Inline bootstrap in 3 controllers, 6 missing default data items | 1. Create service class 2. Extract from 3 controllers 3. Add default data creation methods | **Medium** |
| **4. Owner Protection** | ⚠️ Partial | `is_owner` flag exists but no middleware, no protection layer, no `owner` role | 1. Create `owner` role 2. Add `EnsureOwner` middleware 3. Protect owner-only actions | **Medium** |
| **5. Manager Role** | ❌ Not started | Exists ad-hoc as `Manager`/`Managers` with naming issues, no standard permission set | 1. Define standard `manager` role 2. Rename existing variants 3. Add to bootstrap | **Low** |
| **6. Staff Role** | ❌ Not started | Does not exist in any form | 1. Define `staff` role with limited permissions 2. Add to bootstrap 3. Add to sidebar | **Low** |
| **7. admin.access** | ❌ Not started | RoleMiddleware bypass allows ANY permission holder into admin panel | 1. Create permission 2. Update RoleMiddleware 3. Seed to admin role | **Medium** |

### Readiness Summary

| Area | Readiness |
|------|-----------|
| Route separation (platform vs tenant) | ⚠️ Partial — same prefix groups but shared `admin` routes |
| Controller separation | ✅ Controllers are physically separated (Admin/ vs SuperAdmin/) |
| Auth isolation | ❌ All users share `web` guard |
| Nav/Layout separation | ❌ Same AdminLayout + AdminSidebar |
| Settings separation | ❌ WebsiteInfo is a single global record |
| Permission architecture | ⚠️ 77 permissions exist but no platform-specific set |
| Role architecture | ⚠️ Protected roles work but naming issues exist |
| Tenant bootstrap | ❌ No service, 6 missing data items |
| Impersonation | ✅ Near-complete |
| Owner protection | ⚠️ Flag exists, no role or middleware |

---

## 13. Migration Impact Analysis

### Feature 1: Superadmin Isolation — HIGH impact

| Aspect | Files Affected |
|--------|---------------|
| Routes | ~20 platform routes → new `/platform/*` prefix recommendation |
| Controllers | 5 SuperAdmin controllers — logic may need refactoring |
| Middleware | New `role:superadmin` or `platform` middleware may be needed |
| AdminLayout | Split into `PlatformLayout.jsx` + `SuperAdminSidebar.jsx` OR add conditional rendering |
| Nav/Sidebar | Separate superadmin nav from tenant nav in AdminSidebar.jsx |
| Permissions | Add `platform.*` permission set (optional, not critical) |
| Website Info | Create `platform_settings` model or add tenant_id to website_infos |
| Activity Logs | Add platform-scoped activity logs or separate table |

### Feature 2: Login As Merchant — LOW impact

| Aspect | Files Affected |
|--------|---------------|
| ImpersonationController | Minor additions (session namespace) |
| RoleMiddleware | Add `is_impersonating` bypass check |
| HandleInertiaRequests | Already has `is_impersonating` flag — no change needed |
| AdminHeader | Already shows impersonation banner — no change needed |

### Feature 3: TenantBootstrapService — MEDIUM impact

| Aspect | Files Affected |
|--------|---------------|
| New file | `app/Services/TenantBootstrapService.php` |
| CreateStoreController | Extract bootstrap logic → call service |
| TenantController | Extract bootstrap logic → call service |
| SyncTenantRoles | Optionally update to use service |
| Seeders | Update to use service for default data |

### Feature 4: Owner Protection — MEDIUM impact

| Aspect | Files Affected |
|--------|---------------|
| RoleSeeder | Add `owner` role with full admin permissions |
| TenantBootstrapService | Create owner role during bootstrap |
| New middleware | `EnsureOwner` checks `$user->is_owner` |
| AdminBilllingController | Restrict renew to owner |
| AdminUserController | Restrict admin user management to owner |
| Sidebar | Optionally show billing only to owner |

### Feature 5: Manager Role — LOW impact

| Aspect | Files Affected |
|--------|---------------|
| PermissionSeeder | No change (existing permissions cover manager needs) |
| TenantBootstrapService | Add manager role creation (optional) |
| Rename command | Rename `Manager` → `manager`, `Managers` → `manager` |
| RoleSeeder (if kept) | Add manager role template |

### Feature 6: Staff Role — LOW impact

| Aspect | Files Affected |
|--------|---------------|
| Permission definitions | Define staff-appropriate permissions subset |
| TenantBootstrapService | Add staff role creation (optional) |
| Sidebar | Staff sees limited menu items |

### Feature 7: admin.access — MEDIUM impact

| Aspect | Files Affected |
|--------|---------------|
| PermissionSeeder | Add `admin.access` permission |
| RoleMiddleware | Add `admin.access` check alongside `hasRole('admin')` |
| AdminBootstrap | Seed `admin.access` to admin role |
| User model | May need helper method |
| CreateStoreController | Sync `admin.access` to tenant admin roles |
| HandleInertiaRequests | May need to expose `admin.access` for frontend |

---

## 14. Risk Analysis

| Risk | Severity | Feature | Impact | Mitigation |
|------|----------|---------|--------|-----------|
| **Permission loss during split** | **HIGH** | Superadmin Isolation | If platform and tenant permission sets are separated, existing admin users may lose access | Add migration to reassign permissions; test on staging |
| **Admin panel lockout** | **HIGH** | admin.access | If `admin.access` is required but not assigned, all admins lose access | Add `admin.access` to ALL existing admin roles BEFORE enforcing check in middleware |
| **Cross-tenant data exposure** | **HIGH** | Settings separation | WebsiteInfo is a single global record, both tenants edit same data | Create per-tenant settings BEFORE splitting |
| **Impersonation session leak** | **MEDIUM** | Login As Merchant | Permission override during impersonation could allow unintended actions | Add `impersonator_id` override in permission checks |
| **Owner role migration** | **MEDIUM** | Owner Protection | Adding owner role may conflict with existing `is_owner` flag | Ensure `is_owner` flag and `owner` role are synchronized |
| **Role rename breaks code** | **LOW** | Manager/Staff | Renaming `admins` → `admin` could affect code referencing role ID | All code references roles by name string, not ID. Low risk. |
| **Bootstrap service migration** | **LOW** | TenantBootstrapService | Extracting bootstrap logic may miss edge cases in existing controllers | Keep old inline logic as fallback during transition; compare results |

---

## 15. Final Recommendations

### Immediate (No Code Changes):

1. Document that `WebsiteInfo` is a single global record shared by all tenants (data isolation gap)
2. Document that superadmin accesses tenant routes for Website Info and Activity Logs (separation gap)
3. Document that `admin.access` permission does not exist and admin panel access is role-based

### Short-Term (V3 Sprint 1):

4. **Create `admin.access` permission** — Seed to all existing admin roles before enforcing in RoleMiddleware
5. **Fix website settings isolation** — Add `tenant_id` to `website_infos` table or create `tenant_settings` model
6. **Create `TenantBootstrapService`** — Extract role creation from 3 controllers, add default data creation

### Medium-Term (V3 Sprint 2):

7. **Implement Login As Merchant permission limiting** — Add `impersonator_id` override in permission checks
8. **Add Owner Protection** — Create `owner` role, add `EnsureOwner` middleware, restrict billing/owner-only actions
9. **Standardize role naming** — Rename `admins` → `admin`, `Manager` → `manager`, `Managers` → `manager`

### Long-Term (V3 Sprint 3):

10. **Split AdminLayout** — Create dedicated `PlatformLayout` for superadmin with separate navigation
11. **Add platform permission set** — Create `platform.*` permissions for granular superadmin access control
12. **Add Manager + Staff roles** — Standard role definitions with limited permissions
13. **Separate auth guard** — Consider `platform` guard for superadmin vs `web` guard for tenant users
