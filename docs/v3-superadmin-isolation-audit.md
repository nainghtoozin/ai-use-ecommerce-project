# V3-B1: SuperAdmin Isolation Audit Report

**Date:** 2026-06-21
**Scope:** Read-only audit of authentication, routes, permissions, menus, impersonation, controllers, and security layers.

---

## Executive Summary

This audit evaluated whether the SuperAdmin and tenant-admin domains are properly isolated across 7 areas. The architecture is sound overall — tenant data is isolated via a global `TenantScope`, route groups are separated by middleware, and role-based access controls prevent cross-domain access.

**3 CRITICAL/HIGH findings** were identified:

1. **[HIGH] `AdminUserController::getTenantFilter()` returns nullable** — when `Tenant::getCurrent()` returns `null`, no tenant scoping is applied, exposing all users across all tenants.
2. **[HIGH] Over-reliance on global scope in admin controllers** — 6 of 7 admin controllers (Categories, Products, Orders, Brands, Units, Payment Methods) rely 100% on the `TenantAware` global scope with no explicit `->where('tenant_id', ...)` fallback.
3. **[MEDIUM] Product/Variant ID enumeration** — Revealing error messages in `AdminProductController::validateComboItems()` allow probing for the existence of product/variant IDs across tenants.

---

## Part 1: Authentication Flow

### Overview

| Component | File | Purpose |
|---|---|---|
| `IdentifyTenant` middleware | `app/Http/Middleware/IdentifyTenant.php` | Sets `current.tenant` in the container |
| `TenantAware` trait | `app/Models/Traits/TenantAware.php` | Global scope + auto-set tenant_id on create |
| `TenantScope` | `app/Models/Scopes/TenantScope.php` | Adds WHERE tenant_id = ? to all queries |
| `Tenant::getCurrent()` | `app/Models/Tenant.php:119` | Reads `current.tenant` from container |

### How It Works

1. `IdentifyTenant` runs on every web request.
2. For authenticated users:
   - **SuperAdmin** → skips immediately (line 20-22). No tenant context is set.
   - **Non-SuperAdmin** → resolves tenant from `$user->tenant_id` → sets `current.tenant`.
3. For unauthenticated/guest users:
   - Resolves tenant from (a) subdomain, (b) `X-Tenant` header, (c) session, (d) default tenant.
4. `TenantScope::apply()` checks `Tenant::getCurrent()` — if truthy, adds `WHERE table.tenant_id = ?`.

### Exempt Models

- `Role` — exempt from global scope (roles can be system-level or tenant-level)
- `ActivityLog` — exempt from global scope (superadmin needs to see all logs)

### CLI Bypass

`TenantScope::isMigrationOrSeed()` bypasses the global scope during `php artisan db:seed`, `migrate`, etc. This is necessary for seeding but means seeders must manually handle `tenant_id`.

### Finding: Race condition risk

If `IdentifyTenant` fails, is misconfigured, or is removed from the middleware stack, `Tenant::getCurrent()` returns `null`, and the global scope applies **no filter**. Every admin controller that relies solely on the global scope would expose all tenant data simultaneously.

**Severity: Medium** (defense-in-depth would still require explicit scoping)

---

## Part 2: Route Analysis

### Route Groups

| Prefix | Middleware | Controller Namespace | Purpose |
|---|---|---|---|
| `/superadmin/*` | `auth, role:superadmin` | `SuperAdmin\*` | Platform administration |
| `/admin/*` | `auth, role:admin, tenant.valid, tenant.active, tenant.binding` | `Admin\*` | Tenant administration |
| `/store/{store_slug}/admin/*` | `storefront, auth, role:admin, tenant.valid, tenant.access, tenant.active, tenant.binding` | `Admin\*` | Multi-tenant storefront admin |
| `/` (guest) | web | `Auth\*` | Registration, login |

### Route List (SuperAdmin)

| Method | URI | Controller | Feature |
|---|---|---|---|
| GET | `/superadmin/dashboard` | `DashboardController@index` | Stats overview |
| GET | `/superadmin/tenants` | `TenantController@index` | List tenants |
| GET | `/superadmin/tenants/create` | `TenantController@create` | Create tenant form |
| POST | `/superadmin/tenants` | `TenantController@store` | Create tenant |
| GET | `/superadmin/tenants/{tenant}` | `TenantController@show` | View tenant detail |
| GET | `/superadmin/tenants/{tenant}/edit` | `TenantController@edit` | Edit tenant form |
| PUT | `/superadmin/tenants/{tenant}` | `TenantController@update` | Update tenant |
| DELETE | `/superadmin/tenants/{tenant}` | `TenantController@destroy` | Delete tenant |
| PATCH | `/superadmin/tenants/{tenant}/toggle-status` | `TenantController@toggleStatus` | Activate/suspend |
| GET | `/superadmin/plans` | `PlanController@index` | List plans |
| GET | `/superadmin/plans/create` | `PlanController@create` | Create plan form |
| POST | `/superadmin/plans` | `PlanController@store` | Create plan |
| GET | `/superadmin/plans/{plan}/edit` | `PlanController@edit` | Edit plan form |
| PUT | `/superadmin/plans/{plan}` | `PlanController@update` | Update plan |
| DELETE | `/superadmin/plans/{plan}` | `PlanController@destroy` | Delete plan |
| GET | `/superadmin/subscriptions` | `SubscriptionController@index` | List subscriptions |
| GET | `/superadmin/subscriptions/{subscription}` | `SubscriptionController@show` | View subscription |
| DELETE | `/superadmin/subscriptions/{subscription}` | `SubscriptionController@destroy` | Delete subscription |
| POST | `/superadmin/impersonate/{user}` | `ImpersonationController@start` | Start impersonation |
| POST | `/superadmin/impersonate/leave` | `ImpersonationController@leave` | Stop impersonation |

### Route List (Admin / Tenant)

| Method | URI | Controller | Feature |
|---|---|---|---|
| GET | `/admin/dashboard` | `AdminDashboardController@index` | Dashboard |
| GET | `/admin/users` | `AdminUserController@index` | List users |
| GET/POST | `/admin/users/create` | `AdminUserController@create/store` | Create user |
| GET/PUT | `/admin/users/{user}/edit` | `AdminUserController@edit/update` | Edit user |
| DELETE | `/admin/users/{user}` | `AdminUserController@destroy` | Delete user |
| PATCH | `/admin/users/{id}/suspend` | `AdminUserController@suspend` | Suspend user |
| PATCH | `/admin/users/{id}/ban` | `AdminUserController@ban` | Ban user |
| PATCH | `/admin/users/{id}/activate` | `AdminUserController@activate` | Activate user |
| GET | `/admin/categories` | `AdminCategoryController@index` | List categories |
| GET/POST | `/admin/categories/create` | `AdminCategoryController@create/store` | Create category |
| GET/PUT | `/admin/categories/{category}/edit` | `AdminCategoryController@edit/update` | Edit category |
| DELETE | `/admin/categories/{category}` | `AdminCategoryController@destroy` | Delete category |
| GET | `/admin/products` | `AdminProductController@index` | List products |
| GET/POST | `/admin/products/create` | `AdminProductController@create/store` | Create product |
| GET/PUT | `/admin/products/{product}/edit` | `AdminProductController@edit/update` | Edit product |
| DELETE | `/admin/products/{product}` | `AdminProductController@destroy` | Delete product |
| POST | `/admin/products/bulk-destroy` | `AdminProductController@bulkDestroy` | Bulk delete |
| POST | `/admin/products/bulk-activate` | `AdminProductController@bulkActivate` | Bulk activate |
| POST | `/admin/products/bulk-deactivate` | `AdminProductController@bulkDeactivate` | Bulk deactivate |
| GET | `/admin/orders` | `AdminOrderController@index` | List orders |
| PATCH | `/admin/orders/{order}/status` | `AdminOrderController@updateOrderStatus` | Update status |
| DELETE | `/admin/orders/{order}` | `AdminOrderController@destroy` | Delete order |
| GET | `/admin/brands` | `AdminBrandController@index` | List brands |
| GET/POST | `/admin/brands/create` | `AdminBrandController@create/store` | Create brand |
| GET/PUT | `/admin/brands/{brand}/edit` | `AdminBrandController@edit/update` | Edit brand |
| DELETE | `/admin/brands/{brand}` | `AdminBrandController@destroy` | Delete brand |
| GET | `/admin/units` | `AdminUnitController@index` | List units |
| GET/POST | `/admin/units/create` | `AdminUnitController@create/store` | Create unit |
| GET/PUT | `/admin/units/{unit}/edit` | `AdminUnitController@edit/update` | Edit unit |
| DELETE | `/admin/units/{unit}` | `AdminUnitController@destroy` | Delete unit |
| GET | `/admin/payment-methods` | `AdminPaymentMethodController@index` | List payment methods |
| GET/POST | `/admin/payment-methods/create` | `AdminPaymentMethodController@create/store` | Create method |
| GET/PUT | `/admin/payment-methods/{paymentMethod}/edit` | `AdminPaymentMethodController@edit/update` | Edit method |
| DELETE | `/admin/payment-methods/{paymentMethod}` | `AdminPaymentMethodController@destroy` | Delete method |

### Finding: No route overlap

SuperAdmin routes (`/superadmin/*`) and admin routes (`/admin/*`, `/store/{store_slug}/admin/*`) have completely separate URI prefixes and middleware stacks. A user without the `superadmin` role can never access `/superadmin/*` routes. **No overlap risk.**

### Middleware Stack Verification

- `/superadmin/*` — `web, auth, role:superadmin` (correct — no tenant middleware applied)
- `/admin/*` — `web, auth, role:admin, tenant.valid, tenant.active, tenant.binding` (correct — full tenant isolation)
- `/store/{store_slug}/admin/*` — `storefront, auth, role:admin, tenant.valid, tenant.access, tenant.active, tenant.binding` (correct — validates user belongs to the store's tenant)

---

## Part 3: Permission System

### Permission Architecture

Permissions are stored in the `permissions` table and follow the `resource.action` naming convention:

| Resource | Actions |
|---|---|
| `users` | `view`, `create`, `update`, `delete`, `suspend`, `ban`, `activate`, `assign-roles` |
| `categories` | `view`, `create`, `update`, `delete` |
| `products` | `view`, `create`, `update`, `delete` |
| `orders` | `view`, `update-status` |
| `brands` | `view`, `create`, `update`, `delete` |
| `units` | `view`, `create`, `update`, `delete` |
| `payments` | `view`, `create`, `update`, `delete` |
| `roles` | `view`, `create`, `update`, `delete` |

### Role Definitions

| Role | Scope | Permissions |
|---|---|---|
| `superadmin` | Global (no tenant_id) | All permissions (via gate bypass, not explicit) |
| `admin` (owner) | Tenant-scoped (tenant_id set) | All tenant permissions via `admin` role |
| `admin` (staff) | Tenant-scoped (tenant_id set) | Subset of permissions via custom roles |
| `customer` | Tenant-scoped (tenant_id set) | No admin permissions (frontend only) |

### Permission Checking

Controllers use Laravel's `can()` helper: `$user->can('users.view')`. SuperAdmin bypasses all permission checks via the `isSuperAdmin()` method.

### Seeders

| Seeder | Purpose |
|---|---|
| `PermissionSeeder` | Creates all permission records (idempotent via `firstOrCreate`) |
| `RoleAndPermissionSeeder` | Creates `superadmin` role (once via `firstOrCreate`), creates `admin` role per tenant, assigns all permissions to `admin` |

### Finding: Tenant-scoped permissions work correctly

Roles and permissions are not tenant-scoped by the global scope (Role is exempt), but `RoleAndPermissionSeeder` explicitly sets `tenant_id` on `admin` roles. The `superadmin` role has `tenant_id = null` (global). This is correct.

---

## Part 4: Menu System

### Menu Configuration (Inertia)

Menus are defined in `HandleInertiaRequests` and shared with the frontend via `Inertia::share()`.

### SuperAdmin Menu

Shared only when `auth()->user()->isSuperAdmin()`. Contains:
- Dashboard
- Tenants
- Plans
- Subscriptions

### Admin Menu

Shared for non-SuperAdmin authenticated users. Contains:
- Dashboard
- Users (if can `users.view`)
- Products (if can `products.view`)
- Orders (if can `orders.view`)
- Categories (if can `categories.view`)
- Brands (if can `brands.view`)
- Units (if can `units.view`)
- Payment Methods (if can `payments.view`)
- Roles (if can `roles.view`)

### Finding: No cross-domain menu leakage

The condition for the SuperAdmin menu is `auth()->user()->isSuperAdmin()`. A tenant admin can never see SuperAdmin menu items because `isSuperAdmin()` returns `false` for tenant-scoped users.

**Severity: None** — properly isolated.

---

## Part 5: Impersonation System

### Controller: `ImpersonationController`

**Start Impersonation** (`POST /superadmin/impersonate/{user}`):
- Validates: impersonator is SuperAdmin
- Validates: target is not SuperAdmin
- Validates: target has `tenant_id`
- Validates: no active impersonation session
- Validates: target is not suspended/banned/inactive
- Validates: target has `admin` role
- Validates: target's tenant is active
- Stores session data: `impersonator_id`, `impersonator_name`, `impersonation_batch_uuid`
- Logs activity with full context
- Regenerates session
- Redirects to tenant admin dashboard

**Leave Impersonation** (`POST /superadmin/impersonate/leave`):
- Pulls session data
- Validates impersonator still exists in DB
- Logs activity
- Logs out, logs back in as impersonator
- Regenerates session

### Finding: Session regeneration timing

`session()->regenerate()` is called AFTER `auth()->login($user)` on both `start()` and `leave()`. This is acceptable in Laravel — the old session data is discarded and a new session ID is issued. The `impersonator_id` is stored via `session()->put()` before regeneration, which means it's correctly persisted in the new session (Laravel moves flash data/session data to the new session during regeneration).

### Finding: No re-validation middleware

After impersonation starts, there is no middleware that validates:
- The impersonator is still a SuperAdmin (e.g., demoted while impersonating)
- The target user still exists
- The target user's tenant is still active

A determined attacker could exploit this during an active impersonation session if the impersonator's status changes.

**Severity: Low** (requires concurrent status change during active impersonation)

---

## Part 6: Controller & Tenant Scoping Analysis

### SuperAdmin Controllers

| Controller | Permission Checks | Tenant Scoping | Risk |
|---|---|---|---|
| `TenantController` | None (role:superadmin) | N/A (Tenant model, no scope) | None |
| `PlanController` | None (role:superadmin) | N/A (Plan model, no scope) | None |
| `SubscriptionController` | None (role:superadmin) | N/A (scope skipped for superadmin) | None |
| `DashboardController` | None (role:superadmin) | N/A (aggregates all tenants) | None |
| `ImpersonationController` | `isSuperAdmin()` in code | N/A | None |

All SuperAdmin controllers are protected exclusively by the `role:superadmin` middleware. No authorization gates are used. If a user is incorrectly granted the `superadmin` role, they have unrestricted access.

**Severity: Low** (role assignment is a separate operational concern)

### Admin Controllers (Tenant-Scoped)

#### `AdminUserController`

| Method | Permission Check | Tenant Scoping | Pattern |
|---|---|---|---|
| `index()` | `users.view` | `getTenantFilter()` | `$this->getTenantFilter()` |
| `create()` | `users.create` | `getTenantFilter()` | `$this->getTenantFilter()` |
| `store()` | `users.create` | implicit via user creation | User model's `creating` event |
| `edit()` | `users.update` | `getTenantFilter()` | `$this->getTenantFilter()` |
| `update()` | `users.update` | `getTenantFilter()` | `$this->getTenantFilter()` |
| `destroy()` | `users.delete` | `getTenantFilter()` | `$this->getTenantFilter()` |
| `suspend()` | `users.suspend` | `Tenant::getCurrent()` | Inline `when()` |
| `ban()` | `users.ban` | `Tenant::getCurrent()` | Inline `when()` |
| `activate()` | `users.activate` | `Tenant::getCurrent()` | Inline `when()` |

**CRITICAL FINDING:** `getTenantFilter()` (line 43-49) returns `Tenant::getCurrent()` for non-superadmins. If `Tenant::getCurrent()` returns `null` (middleware failure, edge case), `getTenantFilter()` returns `null`, which is treated as falsy by `when()`. **No tenant filter is applied**, and ALL users across ALL tenants are returned.

Also: `suspend()`, `ban()`, `activate()` use `Tenant::getCurrent()` directly instead of `getTenantFilter()`, creating an inconsistency.

**Severity: HIGH**

#### `AdminCategoryController`, `AdminBrandController`, `AdminUnitController`, `AdminPaymentMethodController`

| Controller | Scoping Method | Defense Layers |
|---|---|---|
| `AdminCategoryController` | Global scope only | `TenantAware` + `ValidateTenantBinding` |
| `AdminBrandController` | Global scope only (via service) | `TenantAware` + `ValidateTenantBinding` |
| `AdminUnitController` | Global scope only (via service) | `TenantAware` + `ValidateTenantBinding` |
| `AdminPaymentMethodController` | Global scope only | `TenantAware` + `ValidateTenantBinding` |

All four rely 100% on the `TenantAware` global scope. The `ValidateTenantBinding` middleware protects route-model-bound parameters (show, edit, update, destroy) but NOT index/list operations or service-layer queries.

**Severity: MEDIUM** (high if global scope fails)

#### `AdminProductController`

| Method | Scoping Method | Defense |
|---|---|---|
| All CRUD | Global scope only | `TenantAware` + `ValidateTenantBinding` |
| Bulk operations | Global scope only | `TenantAware` (no binding middleware) |
| `validateComboItems()` | Global scope | `Product::find()`, `ProductVariant::find()` |

**MEDIUM FINDING:** `validateComboItems()` at line 888 and 913 uses revealing error messages: `'Product #' . $productId . ' does not exist.'`. Because the global scope filters by current tenant, a request with a product ID belonging to another tenant returns "does not exist". This allows ID enumeration: an attacker can probe whether a given product/variant ID exists in the system.

**Severity: MEDIUM**

#### `AdminOrderController`

| Method | Scoping Method | Defense |
|---|---|---|
| `index()` | Global scope only | `TenantAware` |
| `show()` | Global scope only | `TenantAware` + `ValidateTenantBinding` |
| `updateOrderStatus()` | Global scope only | `TenantAware` + `ValidateTenantBinding` |
| `destroy()` | Global scope only | `TenantAware` + `ValidateTenantBinding` |

No explicit `where('tenant_id', ...)` in any query. Complete reliance on global scope.

**Severity: HIGH** (no explicit scoping, orders are sensitive financial data)

---

## Part 7: Security Verification

### Cross-Tenant Data Leak Prevention

| Layer | Mechanism | Effectiveness |
|---|---|---|
| Route middleware | Separate prefix + role check for `/superadmin/*` vs `/admin/*` | Full |
| Global scope | `TenantScope` auto-filters all `TenantAware` models | Full (when `Tenant::getCurrent()` is set) |
| Binding middleware | `ValidateTenantBinding` checks route-model bound params against `current.tenant` | Full (for bound models only) |
| Manual scoping | `AdminUserController` uses `getTenantFilter()` | Partial (null fallback bug) |
| Permission system | `can()` checks in controllers | Full (permissions verified per model) |

### Privilege Escalation Prevention

| Attack Vector | Defense | Status |
|---|---|---|
| Tenant admin accessing `/superadmin/*` | `role:superadmin` middleware rejects | Protected |
| SuperAdmin accessing `/admin/*` | `role:admin` middleware allows (bypass for superadmin) | Intended |
| Guest user accessing admin | `auth` middleware rejects | Protected |
| Customer user accessing admin | `role:admin` middleware rejects | Protected |

### Defense-in-Depth Gaps

1. **AdminUserController** — `getTenantFilter()` can return `null` (HIGH severity)
2. **No explicit tenant scoping** in 6 of 7 admin controllers (MEDIUM-HIGH severity)
3. **No re-validation middleware** for impersonation sessions (LOW severity)
4. **No authorization gates/policies** on SuperAdmin controllers (LOW severity)
5. **Product/Variant ID enumeration** via reveal message (MEDIUM severity)

---

## Final Recommendations

### Critical (Fix Immediately)

1. **Fix `AdminUserController::getTenantFilter()`** — Always return the user's `tenant_id` from `auth()->user()->tenant_id` instead of relying on `Tenant::getCurrent()`.

### High Priority (Fix Soon)

2. **Add explicit tenant scoping fallback** to `AdminOrderController` — Add `->where('tenant_id', auth()->user()->tenant_id)` to all queries. This is the most sensitive controller (financial/order data).
3. **Add explicit tenant scoping fallback** to `AdminCategoryController`, `AdminProductController`, `AdminBrandController`, `AdminUnitController`, `AdminPaymentMethodController` — defense-in-depth against global scope failure.

### Medium Priority (Fix When Convenient)

4. **Replace revealing error messages** in `AdminProductController::validateComboItems()` with generic messages to prevent ID enumeration.
5. **Add `can()` gates** to destructive SuperAdmin operations (e.g., `TenantController::destroy()`).

### Low Priority (Consider)

6. **Add impersonation re-validation middleware** to verify impersonator is still a SuperAdmin on each request.
7. **Standardize scoping patterns** across all admin controllers (some use `getTenantFilter()`, some use `Tenant::getCurrent()` inline).
