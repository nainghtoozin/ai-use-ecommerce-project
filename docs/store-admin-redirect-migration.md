# Store Admin Redirect Migration

> **Date:** 2026-06-05
> **Strategy:** Context-aware redirect helper — same code path, different destination based on request origin.

---

## Problem

After creating the new `/store/{store_slug}/admin/*` route group, controllers still hardcoded `redirect()->route('admin.*')`. This meant:

```
A merchant at /store/may-shop/admin/products/create
  → submits form
  → controller returns redirect()->route('admin.products.index')
  → browser goes to /admin/products  (kicked OUT of storefront context)
```

## Solution: `admin_redirect()` Helper

A single global helper function that detects the current request context and routes accordingly:

```php
function admin_redirect(string $route, mixed $parameters = [], int $status = 302, array $headers = []): RedirectResponse
{
    $storeSlug = request()->route('store_slug');

    if ($storeSlug) {
        // Request came from storefront admin context
        $route = 'storefront.' . $route;        // admin.products.index → storefront.admin.products.index
        if (!is_array($parameters)) {
            $parameters = [$parameters];
        }
        $parameters = ['store_slug' => $storeSlug] + $parameters;
    }

    return redirect()->route($route, $parameters, $status, $headers);
}
```

### Detection Mechanism

| Request Origin | `request()->route('store_slug')` | Behavior |
|---|---|---|
| `/admin/products` | `null` | Redirects to old `admin.products.index` (unchanged) |
| `/store/may-shop/admin/products` | `'may-shop'` | Redirects to `storefront.admin.products.index` with `store_slug` param |

### Parameter Handling

| Before | After | Storefront Equivalent |
|---|---|---|
| `redirect()->route('admin.orders.show', $id)` | `admin_redirect('admin.orders.show', $id)` | `storefront.admin.orders.show` with `store_slug` + `order` params |
| `redirect()->route('admin.products.index')` | `admin_redirect('admin.products.index')` | `storefront.admin.products.index` with `store_slug` param |
| `redirect()->route('admin.promotions.edit', $newPromotion->id)` | `admin_redirect('admin.promotions.edit', $newPromotion->id)` | `storefront.admin.promotions.edit` with `store_slug` + `promotion` params |

---

## Controllers Modified

**14 controllers** — all `redirect()->route('admin.*')` calls replaced with `admin_redirect()`.

| # | Controller | File | Redirects Updated | Old Route Pattern |
|---|---|---|---|---|
| 1 | `AdminOrderController` | `Admin/AdminOrderController.php` | **24** | `admin.orders.show`, `admin.orders.index` |
| 2 | `AdminProductController` | `Admin/AdminProductController.php` | **6** | `admin.products.index` |
| 3 | `AdminUserController` | `Admin/AdminUserController.php` | **10** | `admin.users.index` |
| 4 | `RoleController` | `Admin/RoleController.php` | **5** | `admin.roles.index` |
| 5 | `AdminCategoryController` | `Admin/AdminCategoryController.php` | **3** | `admin.categories.index` |
| 6 | `AdminPromotionController` | `Admin/AdminPromotionController.php` | **6** | `admin.promotions.index`, `admin.promotions.edit` |
| 7 | `AdminCouponController` | `Admin/AdminCouponController.php` | **3** | `admin.coupons.index` |
| 8 | `AdminCityController` | `Admin/AdminCityController.php` | **4** | `admin.cities.index` |
| 9 | `AdminTownshipController` | `Admin/AdminTownshipController.php` | **3** | `admin.townships.index` |
| 10 | `AdminPaymentMethodController` | `Admin/AdminPaymentMethodController.php` | **3** | `admin.payment-methods.index` |
| 11 | `AdminPromotionBannerController` | `Admin/AdminPromotionBannerController.php` | **4** | `admin.banners.index` |
| 12 | `AdminOrderOverrideController` | `Admin/AdminOrderOverrideController.php` | **4** | `admin.orders.show` |
| 13 | `AdminBillingController` | `Admin/AdminBillingController.php` | **1** | `admin.billing` |
| 14 | `AdminNotificationSettingsController` | `Admin/AdminNotificationSettingsController.php` | **1** | `admin.settings.notifications` |
| | **Total** | | **77** | |

### Controllers NOT Modified (correct as-is)

| Controller | Redirects Used | Reason |
|---|---|---|
| `AdminReportController` | `redirect()->back()` | Back to previous page — works in both contexts |
| `SettingsController` | `redirect()->back()` | Back to previous page — works in both contexts |

---

## Files Modified

| File | Action | Details |
|---|---|---|
| `bootstrap/helpers.php` | **MODIFIED** | Added `admin_redirect()` helper function (lines 65-80) |
| `app/Http/Controllers/Admin/AdminOrderController.php` | **MODIFIED** | 24 redirects updated |
| `app/Http/Controllers/Admin/AdminProductController.php` | **MODIFIED** | 6 redirects updated |
| `app/Http/Controllers/Admin/AdminUserController.php` | **MODIFIED** | 10 redirects updated |
| `app/Http/Controllers/Admin/RoleController.php` | **MODIFIED** | 5 redirects updated |
| `app/Http/Controllers/Admin/AdminCategoryController.php` | **MODIFIED** | 3 redirects updated |
| `app/Http/Controllers/Admin/AdminPromotionController.php` | **MODIFIED** | 6 redirects updated |
| `app/Http/Controllers/Admin/AdminCouponController.php` | **MODIFIED** | 3 redirects updated |
| `app/Http/Controllers/Admin/AdminCityController.php` | **MODIFIED** | 4 redirects updated |
| `app/Http/Controllers/Admin/AdminTownshipController.php` | **MODIFIED** | 3 redirects updated |
| `app/Http/Controllers/Admin/AdminPaymentMethodController.php` | **MODIFIED** | 3 redirects updated |
| `app/Http/Controllers/Admin/AdminPromotionBannerController.php` | **MODIFIED** | 4 redirects updated |
| `app/Http/Controllers/Admin/AdminOrderOverrideController.php` | **MODIFIED** | 4 redirects updated |
| `app/Http/Controllers/Admin/AdminBillingController.php` | **MODIFIED** | 1 redirect updated |
| `app/Http/Controllers/Admin/AdminNotificationSettingsController.php` | **MODIFIED** | 1 redirect updated |
| `docs/store-admin-redirect-migration.md` | **CREATED** | This document |

### Files NOT Modified

| Category | Reason |
|---|---|
| `routes/web.php` | Old routes still active, no routing changes |
| `routes/storefront-admin.php` | Already created in previous phase |
| `resources/js/*` | No React changes per requirements |
| `resources/views/admin/*` | Blade templates use `route('admin.*')` which still resolves |
| `app/Http/Middleware/*` | No middleware changes needed |

---

## Redirect Strategy

### Context-Aware Routing Flow

```
User at /store/may-shop/admin/products/create
  → submits form
  → AdminProductController@store
  → admin_redirect('admin.products.index')
  → detects store_slug = 'may-shop'
  → redirects to /store/may-shop/admin/products
  ✓ stays in storefront admin context

User at /admin/products/create
  → submits form
  → AdminProductController@store
  → admin_redirect('admin.products.index')
  → no store_slug detected
  → redirects to /admin/products
  ✓ stays in legacy admin context (unchanged)
```

### Route Name Mapping

All mapping is done by prefixing `storefront.` to the existing route name:

| Old Route Name | New Route Name (storefront context) |
|---|---|
| `admin.products.index` | `storefront.admin.products.index` |
| `admin.orders.show` | `storefront.admin.orders.show` |
| `admin.users.index` | `storefront.admin.users.index` |
| `admin.roles.index` | `storefront.admin.roles.index` |
| `admin.categories.index` | `storefront.admin.categories.index` |
| `admin.promotions.index` | `storefront.admin.promotions.index` |
| `admin.coupons.index` | `storefront.admin.coupons.index` |
| `admin.cities.index` | `storefront.admin.cities.index` |
| `admin.townships.index` | `storefront.admin.townships.index` |
| `admin.payment-methods.index` | `storefront.admin.payment-methods.index` |
| `admin.banners.index` | `storefront.admin.banners.index` |
| `admin.billing` | `storefront.admin.billing` |
| `admin.settings.notifications` | `storefront.admin.settings.notifications` |

---

## Testing Checklist

### Regression Tests

- [x] `php artisan test --filter=Storefront` — 43/43 pass
- [x] `npx vite build` — 2455 modules, 0 errors
- [x] No remaining `redirect()->route('admin.` in Admin controllers (verified with grep)

### Manual Verification

- [ ] Create product via `/store/{slug}/admin/products/create` → redirect stays in `/store/{slug}/admin/products`
- [ ] Create product via `/admin/products/create` → redirect goes to `/admin/products`
- [ ] Confirm order via `/store/{slug}/admin/orders/{id}` → redirect stays in storefront context
- [ ] Confirm order via `/admin/orders/{id}` → redirect goes to `/admin/orders/{id}`
- [ ] Create category via `/admin/categories/create` → goes to `/admin/categories`
- [ ] Create staff user via `/store/{slug}/admin/users/create` → stays in storefront context
- [ ] Edit role via `/admin/roles/{id}/edit` → goes to `/admin/roles`
- [ ] SuperAdmin impersonation → redirects to correct dashboard

### Edge Cases

- [ ] `redirect()->back()` in Reports/Settings — works in both contexts (no change needed)
- [ ] `admin_redirect()` called from a non-admin context (no `store_slug`) — falls back to old route name
- [ ] Parameter binding — single ID param, no param, and named param all handled correctly
