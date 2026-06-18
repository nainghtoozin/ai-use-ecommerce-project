# Controller Hardening Audit Report

## Status: Pre-fix audit complete

## Controllers Audited (22 total)

All files under `app/Http/Controllers/Admin/` were read and analyzed for authorization coverage.

| # | Controller | Total Methods | Protected | Missing | Status |
|---|---|---|---|---|---|
| 1 | ActivityLogController | 2 | 2 | 0 | All protected |
| 2 | AdminBillingController | 2 | 0 | **2** | **MISSING** |
| 3 | AdminBrandController | 7 | 7 | 0 | All protected |
| 4 | AdminCategoryController | 7 | 7 | 0 | All protected |
| 5 | AdminCityController | 8 | 8 | 0 | All protected |
| 6 | **AdminController** | **1** (index) | **0** | **1** | **MISSING** |
| 7 | AdminCouponController | 7 | 7 | 0 | All protected |
| 8 | AdminNotificationSettingsController | 2 | 2 | 0 | All protected |
| 9 | AdminOrderController | 13 | 13 | 0 | All protected |
| 10 | AdminOrderOverrideController | 2 | 2 | 0 | All protected |
| 11 | AdminPaymentMethodController | 7 | 7 (poor granularity) | 0 | All protected (see notes) |
| 12 | AdminProductController | 13 | 13 | 0 | All protected |
| 13 | AdminPromotionBannerController | 7 | 7 | 0 | All protected (show() is redirect) |
| 14 | AdminPromotionController | 9 | 9 | 0 | All protected |
| 15 | AdminPromotionReportController | 2 | 2 | 0 | All protected |
| 16 | AdminReportController | 7 | 7 | 0 | All protected |
| 17 | **AdminTownshipController** | **7** | **6** | **1** | **MISSING** |
| 18 | AdminUnitController | 7 | 7 | 0 | All protected |
| 19 | AdminUserController | 10 | 10 | 0 | All protected |
| 20 | PermissionController | 6 | 6 | 0 | All protected |
| 21 | RoleController | 7 | 7 | 0 | All protected |
| 22 | SettingsController | 2 | 2 | 0 | All protected |

## Summary Statistics

| Metric | Value |
|---|---|
| Total controllers | 22 |
| Fully protected | 19 (86%) |
| With missing authorization | **3 (14%)** |
| Total public methods | ~123 |
| Methods with authorization | ~119 |
| Methods WITHOUT authorization | **4** |

## Existing Protections Found

All protected controllers use the same pattern:
```php
if (!auth()->user()->can('permission.name')) {
    abort(403, 'Unauthorized');
}
```

No controller uses `$this->authorize()`, `Gate::authorize()`, `abort_unless()`, or constructor middleware for authorization.

## Missing Protections Found

### CRITICAL: 3 files, 4 methods

| # | Controller | Method | Lines | Risk | Data Exposed |
|---|---|---|---|---|---|
| 1 | **AdminBillingController** | `index()` | 12-50 | **HIGH** | Subscription details, plan info, billing interval, pricing, usage data |
| 2 | **AdminBillingController** | `renew()` | 52-78 | **HIGH** | Self-service subscription renewal — anyone can renew an expired subscription |
| 3 | **AdminController** | `index()` | 16-89 | **MEDIUM** | Dashboard: recent orders, inventory summary, low stock, sales stats, payment breakdown |
| 4 | **AdminTownshipController** | `edit()` | 66-73 | **LOW-MEDIUM** | Township edit form (update() is protected, so cannot save without permission). **Now fixed with `townships.update`.** |

### MODERATE: Poor Granularity (1 file)

| # | Controller | Issue |
|---|---|---|
| 5 | **AdminPaymentMethodController** | All 7 methods use `payments.view`. No distinct `payments.create/update/delete` exist in DB. Anyone with `payments.view` can create, edit, delete, and toggle payment methods. |

### LOW: Informational (2 files)

| # | Controller | Issue |
|---|---|---|
| 6 | **AdminController::showLogin()** | No auth check — login page before authentication. **Intentional.** |
| 7 | **AdminPromotionBannerController::show()** | No auth check — performs only a redirect. **Low risk.** |

## Changes Applied

| # | Controller | Method | Fix Applied | Permission Used |
|---|---|---|---|---|
| 1 | AdminTownshipController | `edit()` | Added `auth()->user()->can('townships.update')` check | `townships.update` (already seeded) |

## Not Applied — Documented as Gaps

| # | Controller | Method | Reason Not Fixed |
|---|---|---|---|
| 1 | **AdminController** | `index()` (dashboard) | No `dashboard.view` permission exists in DB. **Recommendation needed.** Currently accessible to all authenticated admin users. |
| 2 | **AdminBillingController** | `index()` | No `billing.view` permission exists in DB. |
| 3 | **AdminBillingController** | `renew()` | No `billing.renew` (or similar) permission exists in DB. |
| 4 | **AdminPaymentMethodController** | granularity | All 7 methods use `payments.view`. DB lacks `payments.create/update/delete`. Needs seeding + controller update. |

## Regression Risks

| Change | Risk Level | Mitigation |
|---|---|---|
| `AdminTownshipController::edit()` + `townships.update` | **Low** | Pattern identical to `update()` method on line 77 which already uses the same permission. No route changes. |
| Remaining gaps (Dashboard, Billing) | **None** | No changes applied — current behavior preserved. |
