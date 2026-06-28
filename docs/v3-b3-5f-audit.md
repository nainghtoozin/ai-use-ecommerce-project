# V3-B3-5F: Marketing Feature Enforcement — Architecture Audit

## 1. Implementation Summary

All marketing features (Coupons, Promotions, Flash Sales) are now gated by FeatureGate in both backend and frontend. Future feature keys (Gift Cards, Loyalty Points, Referral System) are registered but have no implementation.

## 2. Backend Enforcement

| Controller | Feature Key | Gate Added | Response on Disabled |
|---|---|---|---|
| AdminCouponController | `coupons` | ✅ (all 7 methods) | `redirect()->back()->with('feature_locked', [...])` |
| AdminPromotionController | `promotions` | ✅ (all 9 methods) | `redirect()->back()->with('feature_locked', [...])` |
| AdminPromotionBannerController | `promotions` | ✅ (all 8 methods) | `redirect()->back()->with('feature_locked', [...])` |
| AdminPromotionReportController (index) | `promotions` | ✅ | `redirect()->back()->with('feature_locked', [...])` |
| AdminPromotionReportController (getData) | `promotions` | ✅ | JSON 403 `{success: false, message: '...'}` |
| CartController::applyCoupon | `coupons` | ✅ | JSON 403 `{success: false, message: '...'}` |
| CartController::applyPromotion | `promotions` | ✅ | JSON 403 `{success: false, message: '...'}` |

**All marketing modules require BOTH permission check AND FeatureGate check.**

## 3. FeatureGate Changes

| Change | Details |
|---|---|
| New feature keys | `gift_cards`, `loyalty_points`, `referral_system` added to FEATURE_LABELS + UPGRADE_HINTS |
| New static method | `getUpgradeHintStatic(string $key): ?string` — public access to UPGRADE_HINTS |
| Upgrade hints | `coupons` → Starter, `promotions` → Business, `flash_sales` → Business, future keys → Business |

## 4. Frontend Changes

| Component | Change |
|---|---|
| `FlashMessages.jsx` | Added `feature_locked` flash handler — renders a full-screen upgrade modal with feature name, current plan, required plan, and "Upgrade to {plan}" button linking to `/admin/billing` |
| `AdminSidebar.jsx` | Added **Marketing** section with Coupons (`hasFeature('coupons')`), Promotions (`hasFeature('promotions')`), Flash Sales (`hasFeature('flash_sales')`) links. Promotions removed from Catalog section. Added `Zap` lucide icon. |
| `HandleInertiaRequests.php` | Shares `feature_locked` flash data |

## 5. Sidebar Navigation

| Section | Link | Permission Gate | Feature Gate |
|---|---|---|---|
| **Marketing** (new) | Coupons → `/admin/coupons` | `can('coupons.view')` | `hasFeature('coupons')` |
| | Promotions → `/admin/promotions` | `can('promotions.view')` | `hasFeature('promotions')` |
| | Flash Sales → `/admin/flash-sales` | none (future) | `hasFeature('flash_sales')` |

## 6. Upgrade Experience

When a user navigates to a locked marketing feature (via URL or sidebar), the controller redirects back with `feature_locked` flash data. The `FlashMessages.jsx` component detects this and renders a modal showing:

- Feature Name
- Current Plan (from `auth.user.subscription.plan_name`)
- Required Plan (from `feature_locked.required_plan`)
- "Upgrade to {plan}" button (links to `/admin/billing`)

No user sees a 403 error page for feature-gated marketing pages.

## 7. Tests

| Test File | Tests | Pass |
|---|---|---|
| `tests/Feature/MarketingFeatureTest.php` | 11 | ✅ All pass |
| | Static: upgrade hints, feature keys, labels | ✅ |
| | FeatureGate::forPlan: Free/Starter/Business x coupons/promotions/flash_sales | ✅ |

## 8. Manual QA Matrix

| Scenario | Coupons | Promotions | Flash Sales |
|---|---|---|---|
| **Free** — FeatureGate | `enabled('coupons')` = false ✅ | `enabled('promotions')` = false ✅ | `enabled('flash_sales')` = false ✅ |
| **Free** — Sidebar | Hidden (hasFeature false) ✅ | Hidden (hasFeature false) ✅ | Hidden (hasFeature false) ✅ |
| **Starter** — FeatureGate | `enabled('coupons')` = true ✅ | `enabled('promotions')` = false ✅ | `enabled('flash_sales')` = false ✅ |
| **Starter** — Sidebar | Shown ✅ | Hidden ✅ | Hidden ✅ |
| **Business** — FeatureGate | `enabled('coupons')` = true ✅ | `enabled('promotions')` = true ✅ | `enabled('flash_sales')` = true ✅ |
| **Business** — Sidebar | Shown ✅ | Shown ✅ | Shown ✅ |

## 9. Regression Risk

- **Low**: All FeatureGate changes are additive — existing behavior is unchanged if feature is enabled.
- **Low**: The `redirect()->back()->with('feature_locked', [...])` is a new flash key; any code that reads session flash data will simply ignore the unknown key.
- **Low**: Sidebar changes (new Marketing section, Promotions moved from Catalog) are purely presentational.
- **Medium**: The `FlashMessages.jsx` component now handles a new modal — if the modal rendering has a bug, it could block the entire page. The component is isolated and only triggers on `feature_locked` flash.

## 10. Files Modified

| File | Change |
|---|---|
| `app/Services/FeatureGate.php` | Added `gift_cards`, `loyalty_points`, `referral_system` feature keys + `getUpgradeHintStatic()` |
| `app/Http/Controllers/Admin/AdminCouponController.php` | Changed abort(403) → friendly redirect with `feature_locked` flash |
| `app/Http/Controllers/Admin/AdminPromotionController.php` | Changed abort(403) → friendly redirect with `feature_locked` flash |
| `app/Http/Controllers/Admin/AdminPromotionBannerController.php` | Added `FeatureGate::enabled('promotions')` to all methods + friendly redirect |
| `app/Http/Controllers/Admin/AdminPromotionReportController.php` | Added `FeatureGate::enabled('promotions')` to both methods + friendly response |
| `app/Http/Controllers/CartController.php` | Added `FeatureGate::enabled('coupons'/'promotions')` to applyCoupon/applyPromotion |
| `app/Http/Middleware/HandleInertiaRequests.php` | Added `feature_locked` flash to shared data |
| `resources/js/Components/FlashMessages.jsx` | Added upgrade modal for `feature_locked` flash |
| `resources/js/Components/AdminSidebar.jsx` | Added Marketing section, Promotions moved from Catalog, gated by hasFeature() |
| `resources/js/Components/FeatureUpgradePrompt.jsx` | NEW — reusable upgrade prompt component (currently unused, logic in FlashMessages) |
| `tests/Feature/MarketingFeatureTest.php` | NEW — 11 tests for marketing feature enforcement |

## 11. Remaining Gaps

1. **Coupon admin JSX pages** — `Admin/Coupons/Index.jsx`, `Create.jsx`, `Edit.jsx` don't exist (controller references them but files are missing)
2. **Promotion Banner admin JSX pages** — `Admin/PromotionBanners/Index.jsx`, `Create.jsx`, `Edit.jsx` don't exist
3. **Flash Sales** — Feature key exists, but no routes, controllers, models, or frontend pages exist
4. **Gift Cards, Loyalty Points, Referral System** — Feature keys registered but no implementation
5. **AdminHeader.jsx** — Shows "Promotions" title when path includes 'promotions' — not gated
6. **AppLayout.jsx** — Admin dropdown menu has Promotions link — not gated by feature
