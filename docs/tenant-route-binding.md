# Tenant Route Model Binding Security

**Date:** 2026-06-09  
**Scope:** `ValidateTenantBinding` middleware — validates that all route-model-bound Eloquent models belong to the current tenant

---

## Problem

Route model binding in Laravel runs inside the `SubstituteBindings` middleware, which executes **before** tenant context is set by `IdentifyTenant` (appended global middleware) or `Storefront` (route-group middleware). The existing `TenantScope` global scope calls `Tenant::getCurrent()`, which returns `null` at binding time — so no tenant filter is applied, and models are resolved globally.

### Middleware Execution Order

```
Global web middleware stack:
  1. EncryptCookies
  2. AddQueuedCookiesToResponse
  3. StartSession
  4. ShareErrorsFromSession
  5. ValidateCsrfToken
  6. SubstituteBindings        ← Model resolved here, Tenant::getCurrent() = null
     ─────────────────────────────────────────────────────
  7. IdentifyTenant            ← current.tenant set here
  8. HandleInertiaRequests
  9. CheckUserStatus
 10. CheckMaintenanceMode

Route-group middleware (storefront):
 11. storefront                ← current.tenant set from URL slug
 12. tenant.binding            ← Validates models against current tenant
```

The `TenantScope` global scope is effective for **direct queries** (controller-level `Product::where(...)`) but not for **route model binding**.

---

## Solution: `ValidateTenantBinding` Middleware

**File:** `app/Http/Middleware/ValidateTenantBinding.php`

A cross-cutting middleware that runs **after** tenant context is established and validates all bound Eloquent model `tenant_id` values against the current tenant.

### Middleware Flow

```
Request → ValidateTenantBinding::handle()
  │
  ├─ Current tenant resolved? ──NO──→ next middleware
  │
  ├─ User is SuperAdmin? ──YES──→ next middleware (bypass)
  │
  ├─ For each route parameter:
  │    │
  │    ├─ Is an Eloquent Model? ──NO──→ skip
  │    │
  │    ├─ Has non-null tenant_id? ──NO──→ skip (shared record)
  │    │
  │    ├─ tenant_id === current.tenant.id? ──YES──→ next parameter
  │    │
  │    └─ MISMATCH: abort(404)
  │
  └─ All parameters validated → next middleware
```

### Key behaviors

- **SuperAdmin bypass**: all checks skipped for SuperAdmin users
- **Null tenant_id**: models with `tenant_id = null` are skipped (shared global records, SuperAdmin users, system Plans)
- **Non-Eloquent parameters**: primitive route params (IDs, slugs) are skipped
- **404 response**: on mismatch, returns a generic 404 (does not reveal why)

---

## Routes Protected

Three middleware chains include `tenant.binding`:

### Storefront Outer Group (public + customer routes)

**File:** `routes/web.php:97`

```php
Route::prefix('store/{store_slug}')->name('storefront.')
    ->middleware(['storefront', 'tenant.binding'])
    ->group(function () {
```

Protected bindings:
- `GET /store/{slug}/products/{product}` → `Product`

### Storefront Customer Routes (inside outer group)

**File:** `routes/web.php:120`

```php
Route::middleware(['auth', 'tenant.access'])->prefix('customer')->name('customer.')->group(function () {
```

Protected bindings:
- `GET /store/{slug}/customer/orders/{order}` → `Order`
- `POST /store/{slug}/customer/orders/{order}/cancel` → `Order`
- `POST /store/{slug}/customer/orders/{order}/upload-payment` → `Order`
- `PUT /store/{slug}/customer/addresses/{address}` → `CustomerAddress`
- `DELETE /store/{slug}/customer/addresses/{address}` → `CustomerAddress`

### Storefront Admin Routes

**File:** `routes/storefront-admin.php:52`

```php
Route::prefix('store/{store_slug}/admin')->name('storefront.admin.')
    ->middleware(['storefront', 'auth', 'role:admin', 'tenant.valid', 'tenant.access', 'tenant.binding'])
```

Protected bindings (all 122 admin routes):
- `{product}` → Product, `{order}` → Order, `{category}` → Category
- `{brand}` → Brand, `{unit}` → Unit, `{city}` → City, `{township}` → Township
- `{paymentMethod}` → PaymentMethod, `{coupon}` → Coupon, `{promotion}` → Promotion
- `{user}` → User, `{role}` → Role, `{activityLog}` → ActivityLog

### Legacy Admin Routes

**File:** `routes/web.php:235`

```php
Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin', 'tenant.valid', 'tenant.binding'])
```

Protected bindings (same as storefront admin, accessed via `/admin/*`):
- Products, Orders, Categories, Brands, Units, Cities, Townships
- Payment methods, Coupons, Promotions, Banners
- Users, Roles, Activity logs

---

## Middleware Layering Comparison

| Middleware | Alias | What it checks | Failure action |
|-----------|-------|----------------|----------------|
| `Storefront` | `storefront` | URL slug → valid tenant | `abort(404)` |
| `IdentifyTenant` | *(global)* | Authenticated user → tenant | Sets `current.tenant` or falls back to subdomain/header/session/default |
| `TenantIsValid` | `tenant.valid` | `$user->tenant_id` exists + record | Logout + redirect to root login |
| `CheckTenantAccess` | `tenant.access` | `$user->tenant_id === currentTenant()->id` | Logout + redirect to store login |
| **`ValidateTenantBinding`** | **`tenant.binding`** | **Model `tenant_id` === currentTenant()->id** | **`abort(404)`** |
| `EnsureTenantIsActive` | `tenant.active` | Tenant status + subscription expiry | Redirect to dashboard / suspended page |

---

## Audit Results

### Models with `tenant_id` (all checked by middleware)

| Model | Column | Uses `TenantAware` | `TenantScope` exempt | Middleware checks |
|-------|--------|-------------------|---------------------|-------------------|
| Product | `tenant_id` | Yes | No | ✅ |
| Category | `tenant_id` | Yes | No | ✅ |
| Order | `tenant_id` | Yes | No | ✅ |
| Coupon | `tenant_id` | Yes | No | ✅ |
| Promotion | `tenant_id` | Yes | No | ✅ |
| Brand | `tenant_id` | Yes | No | ✅ |
| Unit | `tenant_id` | Yes | No | ✅ |
| PaymentMethod | `tenant_id` | Yes | No | ✅ |
| City | `tenant_id` | Yes | No | ✅ |
| Township | `tenant_id` | Yes | No | ✅ |
| CustomerAddress | `tenant_id` | Yes | No | ✅ |
| User | `tenant_id` | Yes | No | ✅ |
| Role | `tenant_id` | Yes | **Yes** (exempt) | ✅ (exemption only affects global scope, not middleware) |
| ActivityLog | `tenant_id` | Yes | **Yes** (exempt) | ✅ (exemption only affects global scope, not middleware) |
| Tenant | *(none)* | No | N/A | Skipped (no `tenant_id`) |
| Plan | *(none)* | No | N/A | Skipped (no `tenant_id`) |

### Routes without model binding (no impact from middleware)

Routes that don't have bound model parameters are unaffected — middleware passes through.

---

## Files Changed

| File | Change |
|------|--------|
| `app/Http/Middleware/ValidateTenantBinding.php` | **Created** — new middleware class |
| `bootstrap/app.php` | Registered `tenant.binding` alias + import |
| `routes/web.php` | Added `tenant.binding` to storefront outer group and legacy admin group |
| `routes/storefront-admin.php` | Added `tenant.binding` to storefront admin group middleware chain |

## Verification

- `php artisan test --filter=Storefront` — **43/43 pass** (all storefront tests)
- `php artisan test --filter=MerchantManagement` — **4/4 pass**
- `php artisan route:list` — all routes resolve with middleware chain intact
