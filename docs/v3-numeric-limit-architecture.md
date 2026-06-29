# Numeric Limit Architecture

## Overview

Numeric limits control resource consumption per plan (e.g., max products, monthly orders, coupons). They live as nullable `unsignedInteger` columns on the `plans` table where:

- **`NULL`** = unlimited
- **`0`** = zero (disabled)
- **positive integer** = finite cap

The `SubscriptionLimitService` provides a unified API for reading limits, checking current usage, and enforcing caps.

---

## Limit Catalog

| Column | Label | Granularity | Scope |
|---|---|---|---|
| `product_limit` | Products | total | tenant |
| `staff_limit` | Staff Accounts | total | tenant |
| `storage_limit` | Storage (MB) | total | tenant |
| `orders_monthly_limit` | Monthly Orders | monthly (calendar) | tenant |
| `coupon_limit` | Coupons | total | tenant |
| `promotion_limit` | Promotions | total | tenant |
| `flash_sale_limit` | Flash Sales | total | tenant |
| `api_request_limit` | API Requests | — | tenant (reserved) |
| `image_limit` | Images per Product | per product | tenant |
| `image_max_size_kb` | Max Image Size (KB) | per file | tenant |
| `branch_limit` | Branches | total | tenant (reserved) |
| `warehouse_limit` | Warehouses | total | tenant (reserved) |
| `pos_device_limit` | POS Devices | total | tenant (reserved) |

---

## Backend API (`SubscriptionLimitService`)

### Unified Methods

```php
$limits = SubscriptionLimitService::for();

// Plan's maximum (null = unlimited, 0 = disabled)
$limits->maximum('coupon_limit');       // ?int

// Current usage count
$limits->currentUsage('coupon_limit');  // int

// Remaining (PHP_INT_MAX if unlimited)
$limits->remaining('coupon_limit');     // int

// Can create one more?
$limits->checkLimit('coupon_limit');    // bool

// Throws RuntimeException with upgrade message
$limits->assertCanCreate('coupon_limit');

// Full usage array for frontend
$limits->getUsage('coupon_limit');
// Returns: ['current' => int, 'limit' => ?int, 'remaining' => int, 'is_unlimited' => bool, 'percent' => int]

// All limits
$limits->getAllLimits();
```

### Legacy Methods (unchanged)

```php
$limits->canCreateProduct();
$limits->assertCanCreateProduct();
$limits->canCreateStaff();
$limits->canUpload($fileSizeBytes);
$limits->getProductUsage();
$limits->getStaffUsage();
$limits->getStorageUsage();
$limits->getAllUsage();
```

### Counting Strategy

Simple counting limits query the relevant model scoped to the current tenant:

| Key | Model/Query |
|---|---|
| `product_limit` | `Product::withoutTenantScope()->where('tenant_id', ...)` |
| `staff_limit` | `$tenant->users()->whereHas('roles', admin)` |
| `storage_limit` | `$tenant->used_storage_bytes` |
| `orders_monthly_limit` | `Order::whereMonth(created_at, now())->whereYear(...)` |
| `coupon_limit` | `Coupon::withoutTenantScope()->where('tenant_id', ...)` |
| `promotion_limit` | `Promotion::withoutTenantScope()->where('tenant_id', ...)` |
| `flash_sale_limit` | `PromotionBanner::withoutTenantScope()->where('tenant_id', ...)` |
| `branch_limit` | Returns 0 (model not yet implemented) |
| `warehouse_limit` | Returns 0 (model not yet implemented) |
| `pos_device_limit` | Returns 0 (model not yet implemented) |

---

## Frontend Integration

Usage data is shared to all Inertia pages via `HandleInertiaRequests`:

```js
// Shared prop: subscription_limits
{
  product_limit:       { current: 5, limit: 100, remaining: 95, is_unlimited: false, percent: 5 },
  staff_limit:         { current: 2, limit: 10, remaining: 8, is_unlimited: false, percent: 20 },
  storage_limit:       { current: 50, limit: 1024, remaining: 974, is_unlimited: false, percent: 5 },
  orders_monthly_limit:{ current: 10, limit: 500, remaining: 490, is_unlimited: false, percent: 2 },
  coupon_limit:        { current: 1, limit: 20, remaining: 19, is_unlimited: false, percent: 5 },
  promotion_limit:     { current: 0, limit: 10, remaining: 10, is_unlimited: false, percent: 0 },
  flash_sale_limit:    { current: 0, limit: 5, remaining: 5, is_unlimited: false, percent: 0 },
  branch_limit:        { current: 0, limit: 3, remaining: 3, is_unlimited: false, percent: 0 },
  warehouse_limit:     { current: 0, limit: 2, remaining: 2, is_unlimited: false, percent: 0 },
  pos_device_limit:    { current: 0, limit: 3, remaining: 3, is_unlimited: false, percent: 0 },
}
```

Available as `page.props.subscription_limits` in any Inertia page component.

---

## Enforcement Pattern

Limits are enforced at the controller level, after the FeatureGate check and permission check:

```php
$limitService = SubscriptionLimitService::for();
if (!$limitService->checkLimit('coupon_limit')) {
    return redirect()->back()->with('error',
        'Coupon limit reached. Please upgrade your plan to create more coupons.');
}
```

Currently enforced in:
- `AdminCouponController::store()` — `coupon_limit`
- `AdminPromotionController::store()` — `promotion_limit`
- `AdminPromotionController::duplicate()` — `promotion_limit`
- `AdminPromotionBannerController::store()` — `flash_sale_limit`

---

## Plan Defaults

| Limit | Free | Starter | Business |
|---|---|---|---|
| product_limit | 10 | 100 | unlimited |
| staff_limit | 2 | 5 | unlimited |
| storage_limit (MB) | 100 | 1024 | unlimited |
| orders_monthly_limit | 50 | 500 | unlimited |
| coupon_limit | 5 | 20 | unlimited |
| promotion_limit | 3 | 10 | unlimited |
| flash_sale_limit | 1 | 5 | unlimited |
| api_request_limit | 1000 | 10000 | unlimited |
| image_limit | 5 | 10 | unlimited |
| image_max_size_kb | 2048 | 5120 | 10240 |
| branch_limit | 1 | 3 | unlimited |
| warehouse_limit | 1 | 2 | unlimited |
| pos_device_limit | 1 | 3 | unlimited |

---

## Future Work

- Image limit enforcement per-product when uploading product images
- API request rate limiting middleware (`api_request_limit`)
- Branch, warehouse, POS device limit enforcement when those features are built
- Frontend progress bars showing `current / max` in admin dashboard
- Admin notification when approaching limits (e.g., 80% threshold)
