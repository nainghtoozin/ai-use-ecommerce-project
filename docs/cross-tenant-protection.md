# Cross-Tenant Protection

**Date:** 2026-06-09  
**Scope:** `CheckTenantAccess` middleware — prevents users from accessing another tenant's storefront

---

## Middleware Flow

**File:** `app/Http/Middleware/CheckTenantAccess.php`

```
Request → CheckTenantAccess::handle()
  │
  ├─ User authenticated? ──NO──→ next middleware
  │
  ├─ User is SuperAdmin? ──YES──→ next middleware (bypass)
  │
  ├─ Current tenant resolved? ──NO──→ next middleware (edge case)
  │
  ├─ $user->tenant_id === $currentTenant->id? ──YES──→ next middleware
  │
  └─ MISMATCH:
       ├─ auth()->logout()
       ├─ session()->invalidate()
       ├─ session()->regenerateToken()
       └─ redirect to store login page (with flash error)
```

### Comparison with existing middlewares

| Middleware | Alias | Check | Action on failure |
|-----------|-------|-------|-------------------|
| `TenantIsValid` | `tenant.valid` | `$user->tenant_id` exists + `$user->tenant` record exists | Logout + redirect to root `/login` |
| **`CheckTenantAccess`** | **`tenant.access`** | **`$user->tenant_id === currentTenant()->id`** | **Logout + redirect to store login** |
| `EnsureTenantIsActive` | `tenant.active` | Tenant status + subscription expiry | Redirect to dashboard/suspended |

### Key behaviors

- **SuperAdmin bypass**: SuperAdmin users are never blocked, regardless of `tenant_id`
- **No tenant context**: If `Tenant::getCurrent()` returns null (no store context), the middleware passes through
- **Route-aware redirect**: If the request was for an admin route (`storefront.admin.*`), redirect goes to `storefront.admin.login`; otherwise to `storefront.login`
- **Session cleanup**: On mismatch, the session is fully invalidated and CSRF token regenerated before redirect

---

## Routes Protected

### Storefront Admin Routes

**File:** `routes/storefront-admin.php:51`

```php
Route::prefix('store/{store_slug}/admin')
    ->name('storefront.admin.')
    ->middleware(['storefront', 'auth', 'role:admin', 'tenant.valid', 'tenant.access'])
```

All 122 storefront admin routes are protected:
- Dashboard, billing, products, orders, categories, brands, units, banners, promotions, coupons
- Reports, cities, townships, payment methods, users, roles, permissions, activity logs
- Notifications, chat, settings, website info, telegram integration

**Middleware execution order:**
1. `storefront` — resolves tenant from URL slug
2. `auth` — requires authentication
3. `role:admin` — requires admin role (SuperAdmin bypasses)
4. `tenant.valid` — verifies user has a valid tenant record
5. **`tenant.access`** — verifies user belongs to this tenant
6. `tenant.active` — (inner group) verifies tenant subscription is active

### Storefront Customer Account Routes

**File:** `routes/web.php:120`

```php
Route::middleware(['auth', 'tenant.access'])->prefix('customer')->name('customer.')->group(function () {
```

Protected routes:
- `GET /store/{slug}/customer/account`
- `GET /store/{slug}/customer/orders`
- `GET /store/{slug}/customer/orders/{order}`
- `POST /store/{slug}/customer/orders/{order}/cancel`
- `POST /store/{slug}/customer/orders/{order}/upload-payment`
- `GET /store/{slug}/customer/addresses`
- `POST /store/{slug}/customer/addresses`
- `PUT /store/{slug}/customer/addresses/{address}`
- `DELETE /store/{slug}/customer/addresses/{address}`

---

## Test Cases

### `test_customer_is_blocked_from_other_tenant_account`

**File:** `tests/Feature/StorefrontCustomerTest.php:262`

**Scenario:** Customer from Store A tries to access `/store/store-b/customer/account`.

**Setup:**
```
tenantA = Store A (slug: store-a)
tenantB = Store B (slug: store-b)
user → belongs to tenantA, logged in
current.tenant → artificially set to tenantB
```

**Expected:**
```php
$response->assertRedirect(route('storefront.login', ['store_slug' => $tenantB->slug]));
$this->assertGuest();
```

**Result:** User is logged out, redirected to Store B's login page with error flash message.

### `test_cross_tenant_address_access_is_blocked`

**File:** `tests/Feature/StorefrontCustomerTest.php:411`

**Scenario:** Customer from Store A tries to access `/store/store-b/customer/addresses`.

**Setup:**
```
tenantA = Store A (slug: store-a)
tenantB = Store B (slug: store-b)
user → belongs to tenantA, logged in
current.tenant → artificially set to tenantB
```

**Expected:**
```php
$response->assertRedirect(route('storefront.login', ['store_slug' => $tenantB->slug]));
$this->assertGuest();
```

**Result:** User is logged out, redirected to Store B's login page with error flash message.

---

## Protected Scenarios

| Scenario | User's `tenant_id` | URL Tenant | Middleware Action |
|----------|-------------------|-----------|-------------------|
| Customer accesses own store | Store A | Store A | ✅ Pass through |
| Admin accesses own store admin | Store A | Store A | ✅ Pass through |
| Customer tries another store | Store A | Store B | ❌ Logout → redirect to Store B login |
| Admin tries another store admin | Store A | Store B | ❌ Logout → redirect to Store B admin login |
| SuperAdmin accesses any store | null/SuperAdmin | Any | ✅ Bypass |
| Unauthenticated user | — | Any | ✅ Pass through (handled by `auth` middleware) |
| User accesses non-storefront page | Store A | None (no tenant context) | ✅ Pass through (no current tenant) |

## Files Changed

| File | Change |
|------|--------|
| `app/Http/Middleware/CheckTenantAccess.php` | **Created** — new middleware class |
| `bootstrap/app.php` | Registered `tenant.access` alias + import |
| `routes/storefront-admin.php` | Added `tenant.access` to middleware chain |
| `routes/web.php` | Added `tenant.access` to customer routes middleware |
| `tests/Feature/StorefrontCustomerTest.php` | Updated 2 tests: 403 → assertRedirect + assertGuest |

## Verification

- `npx vite build` passes
- `php artisan test --filter=Storefront` — **43/43 pass**
