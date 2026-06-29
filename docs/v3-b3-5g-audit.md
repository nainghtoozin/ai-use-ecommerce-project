# V3-B3-5G: Numeric Limit Expansion — Audit

## Objective
Add 10 new numeric limit columns to the `plans` table, extend `SubscriptionLimitService` with a unified limit API, update Plan CRUD, enforce on marketing controllers, and expose usage data to the frontend.

## Files Modified

| File | Change |
|---|---|
| `database/migrations/2026_06_29_000003_add_numeric_limits_to_plans_table.php` | **New** — adds 10 nullable `unsignedInteger` columns |
| `app/Models/Plan.php` | Added `$fillable` entries for 10 new columns; added `isUnlimited()` and `limitValue()` helpers |
| `app/Services/SubscriptionLimitService.php` | Added unified API (`maximum`, `currentUsage`, `remaining`, `checkLimit`, `assertCanCreate`, `getUsage`, `getAllLimits`); added counting methods for orders, coupons, promotions, flash sales, branches, warehouses, POS devices; added `LIMIT_LABELS` constant |
| `app/Http/Controllers/SuperAdmin/PlanController.php` | Added merge/normalize + validation rules for all 10 new limit fields in both `store()` and `update()` |
| `database/seeders/PlanSeeder.php` | Added realistic default values for Free, Starter, and Business plans |
| `resources/js/Pages/SuperAdmin/Plans/Create.jsx` | Added form fields for all 10 new limits in the Limits section |
| `resources/js/Pages/SuperAdmin/Plans/Edit.jsx` | Added form fields for all 10 new limits in the Limits section |
| `app/Http/Controllers/Admin/AdminCouponController.php` | Added `coupon_limit` enforcement in `store()` |
| `app/Http/Controllers/Admin/AdminPromotionController.php` | Added `promotion_limit` enforcement in `store()` and `duplicate()` |
| `app/Http/Controllers/Admin/AdminPromotionBannerController.php` | Added `flash_sale_limit` enforcement in `store()` |
| `app/Http/Middleware/HandleInertiaRequests.php` | Shares `subscription_limits` (all limits with usage) to Inertia |
| `tests/Feature/SubscriptionLimitTest.php` | **New** — 14 tests covering finite/unlimited limits, `checkLimit`, `remaining`, `currentUsage`, `getUsage`, `getAllLimits`, `assertCanCreate`, `LIMIT_LABELS` |
| `docs/v3-numeric-limit-architecture.md` | **New** — architecture documentation |
| `docs/v3-b3-5g-audit.md` | **New** — this audit file |

## New Columns Added to `plans` Table

| Column | Type | Default | Convention |
|---|---|---|---|
| `orders_monthly_limit` | `unsignedInteger, nullable` | — | null=unlimited |
| `coupon_limit` | `unsignedInteger, nullable` | — | null=unlimited |
| `promotion_limit` | `unsignedInteger, nullable` | — | null=unlimited |
| `flash_sale_limit` | `unsignedInteger, nullable` | — | null=unlimited |
| `api_request_limit` | `unsignedInteger, nullable` | — | null=unlimited |
| `image_limit` | `unsignedInteger, nullable` | — | null=unlimited |
| `image_max_size_kb` | `unsignedInteger, nullable` | — | null=unlimited |
| `branch_limit` | `unsignedInteger, nullable` | — | null=unlimited |
| `warehouse_limit` | `unsignedInteger, nullable` | — | null=unlimited |
| `pos_device_limit` | `unsignedInteger, nullable` | — | null=unlimited |

## Unified API Surface

```php
SubscriptionLimitService::for()->maximum($key)          // ?int
SubscriptionLimitService::for()->currentUsage($key)     // int
SubscriptionLimitService::for()->remaining($key)        // int
SubscriptionLimitService::for()->checkLimit($key)       // bool
SubscriptionLimitService::for()->assertCanCreate($key)  // void (throws)
SubscriptionLimitService::for()->getUsage($key)         // array
SubscriptionLimitService::for()->getAllLimits()         // array
```

## Enforcement Status

| Controller | Method | Limit Key | Pattern |
|---|---|---|---|
| AdminCouponController | store | `coupon_limit` | redirect back with `error` |
| AdminPromotionController | store | `promotion_limit` | redirect back with `error` |
| AdminPromotionController | duplicate | `promotion_limit` | redirect back with `error` |
| AdminPromotionBannerController | store | `flash_sale_limit` | redirect back with `error` |

## Test Results

```
Tests:  14 passed (106 assertions)
```

## Backward Compatibility

- All new columns are nullable — existing plans in the database will have `NULL` (unlimited) for all new limits.
- The existing legacy methods (`canCreateProduct()`, `canCreateStaff()`, `canUpload()`, etc.) remain unchanged.
- The `getAllUsage()` legacy method still returns only products/staff/storage.
- Shared Inertia data `subscription_limits` is only present for authenticated users.

## Open Items / Future Work

- Image limit enforcement when uploading product images (per-product context needed)
- `api_request_limit` rate-limiting middleware
- Branch, warehouse, POS device enforcement when those features are built
- Frontend usage progress bars in admin dashboard
- Admin notifications when approaching limits (80% threshold)
