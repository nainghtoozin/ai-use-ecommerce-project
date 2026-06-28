# V3 FeatureGate Standardization

## Architecture

### Single Source of Truth

`app/Services/FeatureGate.php` is the **sole authority** for boolean feature gating. All feature keys, their display labels, and upgrade hints are defined here.

### Flow Diagram

```
PlanSeeder / PlanController (CRUD)
        |
        v
   PlanFeature table   (feature_key, is_enabled, display_label, description)
        |
        v
   FeatureGate::clearCache() ----> PlanFeature::where('plan_id', $plan->id)->get()
        |                                  |
        |                                  v
        |                           Cache (plan_{id}_features)
        |                                  |
        +----------------------------------+
        |
        v
   FeatureGate::isEnabled(featureKey) ----> in_array($key, $enabled)
        |
        +---> Controller (backend)   :: FeatureGate::enabled('reports')
        +---> Inertia shared prop    :: featureStatus[key].enabled
        +---> Frontend sidebar       :: hasFeature('reports')
        +---> Product type select    :: featureStatus['variable_products'].enabled
    +---> CartController (API)   :: FeatureGate::enabled('coupons')
```

### Marketing Feature Flow

```
User requests Coupons/Promotions page
        |
        v
   Controller (AdminCouponController / AdminPromotionController)
        |
        +---> FeatureGate::enabled('coupons'|'promotions') ?
        |       |
        |       +-- YES --> continue to permission check --> render page
        |       |
        |       +-- NO  --> redirect()->back()->with('feature_locked', [
        |                       'feature' => FeatureGate::getLabelStatic($key),
        |                       'required_plan' => FeatureGate::getUpgradeHintStatic($key)
        |                   ])
        |
        v
   Frontend (FlashMessages.jsx)
        |
        +---> flash.feature_locked detected?
                |
                +---> Show Feature Unavailable modal with:
                        - Feature name
                        - Current plan name
                        - Required plan name
                        - Upgrade to {plan} button -> /admin/billing
```

### Cart/Customer Flow (Coupon/Promotion Application)

```
Customer applies coupon code on cart page
        |
        v
   CartController::applyCoupon()
        |
        +---> FeatureGate::enabled('coupons') ?
        |       |
        |       +-- YES --> validate coupon --> store in session
        |       |
        |       +-- NO  --> return JSON { success: false, message: '...' } (403)
        |
   Same flow for applyPromotion() with FeatureGate::enabled('promotions')
```

### Backend Enforcement Rules

| Controller | Feature Key | Action on Disabled |
|---|---|---|
| AdminCouponController | `coupons` | redirect back with `feature_locked` flash |
| AdminPromotionController | `promotions` | redirect back with `feature_locked` flash |
| AdminPromotionBannerController | `promotions` | redirect back with `feature_locked` flash |
| AdminPromotionReportController | `promotions` | redirect back (index) or JSON 403 (getData) |
| AdminReportController | `reports` | abort(403) |
| CartController::applyCoupon | `coupons` | JSON 403 with message |
| CartController::applyPromotion | `promotions` | JSON 403 with message |

### Frontend Gating

| Component | Method |
|---|---|
| AdminSidebar (Marketing section) | `hasFeature('coupons')`, `hasFeature('promotions')`, `hasFeature('flash_sales')` |
| FlashMessages.jsx | Detects `flash.feature_locked` and renders upgrade modal |
| Product type select | `featureStatus['variable_products'].enabled` (pre-existing) |

### Feature Keys (35+)

| Category | Keys |
|---|---|
| **Product Features** | `single_products`, `variable_products`, `combo_products`, `digital_products` |
| **Analytics** | `reports` |
| **Store Features** | `custom_domain`, `advanced_seo`, `theme_editor`, `custom_css`, `maintenance_mode` |
| **Customer Features** | `reviews`, `wishlist`, `compare` |
| **Marketing** | `coupons`, `promotions`, `flash_sales`, `gift_cards`, `loyalty_points`, `referral_system` |
| **Integrations** | `telegram_integration`, `whatsapp_integration`, `social_media_integration`, `google_analytics`, `meta_pixel`, `mailchimp_integration` |
| **AI** | `ai_product_generator`, `ai_description`, `ai_seo`, `ai_translation` |
| **Payment Gateways** | `payment_gateways_cod`, `payment_gateways_kbzpay`, `payment_gateways_wavepay`, `payment_gateways_stripe`, `payment_gateways_paypal`, `payment_gateways_manual` |

### Plan Feature Matrix (seeded)

| Plan | Description | Enabled Features |
|---|---|---|
| **Free** | For small stores to get started | `single_products`, `payment_gateways_cod`, `payment_gateways_manual`, `reviews`, `wishlist` |
| **Starter** | For growing stores | Free + `variable_products`, `digital_products`, All Integrations, `reports`, `coupons`, `promotions`, `flash_sales`, All Payments, `advanced_seo`, `maintenance_mode` |
| **Business** | For established stores | All features enabled |

## Files Modified

| File | Change |
|---|---|
| `app/Services/FeatureGate.php` | Added 30+ feature keys, labels, upgrade hints; `getAllFeatureDefinitions()`, `getLabelStatic()`; `clearCache()` clears all keys now |
| `database/seeders/PlanSeeder.php` | Full feature matrix with `PlanFeature` creation per plan |
| `app/Http/Controllers/SuperAdmin/PlanController.php` | `store()`/`update()` call `syncFeatures()`; `create()`/`edit()` pass `allFeatures` to views |
| `app/Http/Controllers/Admin/AdminReportController.php` | Added `FeatureGate::enabled('reports')` to all public methods |
| `app/Http/Controllers/Admin/AdminCouponController.php` | Added `FeatureGate::enabled('coupons')` to all public methods |
| `app/Http/Controllers/Admin/AdminPromotionController.php` | Added `FeatureGate::enabled('promotions')` to all public methods |
| `app/Http/Middleware/HandleInertiaRequests.php` | Shares `featureStatus` as an Inertia global prop |
| `resources/js/Components/AdminSidebar.jsx` | Gates Reports, Promotions links via `hasFeature()` |
| `resources/js/Pages/SuperAdmin/Plans/Create.jsx` | Dynamic feature toggles by category |
| `resources/js/Pages/SuperAdmin/Plans/Edit.jsx` | Dynamic feature toggles by category |
| `app/Http/Controllers/Admin/AdminPromotionBannerController.php` | Added `FeatureGate::enabled('promotions')` with friendly redirect |
| `app/Http/Controllers/Admin/AdminPromotionReportController.php` | Added `FeatureGate::enabled('promotions')` with friendly response |
| `app/Http/Controllers/CartController.php` | Added `FeatureGate::enabled('coupons'/'promotions')` to apply methods |
| `resources/js/Components/FlashMessages.jsx` | Added `feature_locked` modal with upgrade prompt |
| `resources/js/Components/AdminSidebar.jsx` | Added Marketing section with Coupons, Promotions, Flash Sales; gated by hasFeature() |
| `app/Http/Middleware/HandleInertiaRequests.php` | Shares `feature_locked` flash data |

## Migration Notes

1. **Backward Compatibility**: Old `analytics_enabled` and `custom_domain_enabled` columns on `plans` table are still written by PlanController but are no longer the primary enforcement path. They are retained for any code that may still read them directly.

2. **DEV_MODE**: `FeatureGate::DEV_MODE` bypasses all gating when `true`. In development all features appear enabled. Set to `false` in production.

3. **Cache**: `FeatureGate::clearCache($plan)` must be called whenever a plan's features change. This is already handled by `PlanController::syncFeatures()` and `SubscriptionController` methods.

4. **Adding New Features**: To add a new feature:
   - Add key to `FeatureGate::FEATURE_LABELS`
   - Add hint to `FeatureGate::UPGRADE_HINTS`
   - Update `PlanSeeder` with appropriate feature matrix
   - Run `php artisan db:seed --class=PlanSeeder`
   - Enforce in controllers via `FeatureGate::enabled('new_feature')`
   - Gate in frontend via `props.featureStatus?.new_feature?.enabled`

5. **Numeric Limits**: Product, staff, and storage limits remain in `SubscriptionLimitService` — they are numeric, not boolean, and are not part of FeatureGate.
