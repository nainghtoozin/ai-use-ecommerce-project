# V3-B3-5D: Plan Feature Coverage Audit

## Coverage Summary

| Category | Total | Implemented | Partial | Missing | Deprecated |
|----------|-------|-------------|---------|---------|------------|
| **Limits** | 9 | 3 | 1 | 5 | 0 |
| **Product Features** | 8 | 3 | 0 | 5 | 0 |
| **Store Features** | 5 | 0 | 1 | 4 | 0 |
| **Marketing** | 5 | 0 | 0 | 5 | 0 |
| **Analytics** | 5 | 0 | 1 | 4 | 0 |
| **Integrations** | 7 | 0 | 0 | 7 | 0 |
| **AI** | 4 | 0 | 0 | 4 | 0 |
| **Payments** | 6 | 0 | 0 | 6 | 0 |
| **Total** | **49** | **6 (12%)** | **3 (6%)** | **40 (82%)** | **0** |

---

## 1. Limits

| Feature | Status | Plan Column | FeatureGate Key | Backend Enforcement | UI Enforcement |
|---------|--------|-------------|-----------------|-------------------|----------------|
| Products | ✅ Implemented | `product_limit` | — | `SubscriptionLimitService::assertCanCreateProduct()` in `AdminProductController::store()` | Billing page shows usage |
| Staff | ✅ Implemented | `staff_limit` | — | `SubscriptionLimitService::assertCanCreateStaff()` in `AdminUserController::store()` | Billing page shows usage |
| Storage | ✅ Implemented | `storage_limit` | — | `ImageService::assertStorageLimit()` via `SubscriptionLimitService::assertCanUpload()` | Billing page shows usage |
| Orders | ❌ Missing | — | — | — | — |
| API Requests | ❌ Missing | — | — | — | — |
| Images | ⚠️ Partial | (covered by storage) | — | Indirect via storage limit | — |
| Coupons | ❌ Missing | — | — | — | — |
| Promotions | ❌ Missing | — | — | — | — |
| Flash Sales | ❌ Missing | — | — | — | — |

**Lines referenced:**
- `app/Http/Controllers/Admin/AdminProductController.php:232`
- `app/Http/Controllers/Admin/AdminUserController.php:130`
- `app/Services/ImageService.php:112`
- `app/Services/SubscriptionLimitService.php:104-181`

## 2. Product Features

| Feature | Status | FeatureGate Key | Backend Enforcement | UI Enforcement |
|---------|--------|----------------|-------------------|----------------|
| Single Product | ✅ Implemented | `single_products` | `ProductType::isAvailable()` → `FeatureGate::enabled()` | `ProductTypeSelector.jsx` locks/unlocks cards |
| Variable Product | ✅ Implemented | `variable_products` | `ProductType::isAvailable()` → `FeatureGate::enabled()` | `ProductTypeSelector.jsx` locks/unlocks cards |
| Combo Product | ✅ Implemented | `combo_products` | `ProductType::isAvailable()` → `FeatureGate::enabled()` | `ProductTypeSelector.jsx` locks/unlocks cards |
| Digital Product | ❌ Missing | — | — | — |
| Reviews | ❌ Missing | — | — | — |
| Wishlist | ❌ Missing | — | — | — |
| Compare | ❌ Missing | — | — | — |

**Lines referenced:**
- `app/Enums/ProductType.php:104-148`
- `resources/js/Components/ProductType/ProductTypeSelector.jsx`
- `resources/js/Components/ProductType/UpgradeModal.jsx`

## 3. Store Features

| Feature | Status | Plan Column | FeatureGate Key | Backend Enforcement | UI Enforcement |
|---------|--------|-------------|-----------------|-------------------|----------------|
| Custom Domain | ⚠️ Partial | `custom_domain_enabled` | — | ❌ **No enforcement** — column never checked | ❌ **No UI gating** |
| Theme Editor | ❌ Missing | — | — | — | — |
| Advanced SEO | ❌ Missing | — | — | — | — |
| Custom CSS | ❌ Missing | — | — | — | — |
| Maintenance Mode | ❌ Missing | — | — | — | — |

**Lines referenced:**
- `app/Models/Plan.php:22` — `$fillable` includes `custom_domain_enabled`
- No controller, middleware, or view references `custom_domain_enabled`

## 4. Marketing

| Feature | Status | Plan Column | FeatureGate Key | Backend Enforcement | UI Enforcement |
|---------|--------|-------------|-----------------|-------------------|----------------|
| Coupons | ❌ Missing | — | — | — | — |
| Promotions | ❌ Missing | — | — | — | — |
| Flash Sale | ❌ Missing | — | — | — | — |
| Loyalty | ❌ Missing | — | — | — | — |
| Referral | ❌ Missing | — | — | — | — |

**Note:** Coupons and Promotions have full CRUD controllers, routes, and views — none are plan-gated.

**Lines referenced:**
- `routes/web.php:366-393` — Coupon CRUD routes in inner group (behind `tenant.active` but no plan check)
- `routes/web.php:340-352` — Promotion CRUD routes in inner group

## 5. Analytics

| Feature | Status | Plan Column | FeatureGate Key | Backend Enforcement | UI Enforcement |
|---------|--------|-------------|-----------------|-------------------|----------------|
| Dashboard | ❌ Missing | — | — | — | — |
| Reports | ⚠️ Partial | `analytics_enabled` | — | ❌ **No enforcement** — column never checked | ❌ **No UI gating** |
| Charts | ⚠️ Partial | (via reports) | — | ❌ No enforcement | — |
| Excel Export | ❌ Missing | — | — | — | — |
| PDF Export | ❌ Missing | — | — | — | — |

**Lines referenced:**
- `app/Models/Plan.php:23` — `$fillable` includes `analytics_enabled`
- `routes/web.php:354-363` — Report routes in inner group (no plan check)

## 6. Integrations

| Feature | Status | Plan Column | FeatureGate Key | Backend Enforcement | UI Enforcement |
|---------|--------|-------------|-----------------|-------------------|----------------|
| Telegram | ❌ Missing | — | — | — | — |
| TikTok | ❌ Missing | — | — | — | — |
| Facebook | ❌ Missing | — | — | — | — |
| Google Analytics | ❌ Missing | — | — | — | — |
| Meta Pixel | ❌ Missing | — | — | — | — |
| Mailchimp | ❌ Missing | — | — | — | — |
| WhatsApp | ❌ Missing | — | — | — | — |

## 7. AI

| Feature | Status | Plan Column | FeatureGate Key | Backend Enforcement | UI Enforcement |
|---------|--------|-------------|-----------------|-------------------|----------------|
| AI Product Generator | ❌ Missing | — | — | — | — |
| AI Description | ❌ Missing | — | — | — | — |
| AI SEO | ❌ Missing | — | — | — | — |
| AI Translation | ❌ Missing | — | — | — | — |

## 8. Payments

| Feature | Status | Plan Column | FeatureGate Key | Backend Enforcement | UI Enforcement |
|---------|--------|-------------|-----------------|-------------------|----------------|
| Cash (COD) | ❌ Missing | — | — | — | — |
| KBZPay | ❌ Missing | — | — | — | — |
| WavePay | ❌ Missing | — | — | — | — |
| Stripe | ❌ Missing | — | — | — | — |
| PayPal | ❌ Missing | — | — | — | — |
| Manual Transfer | ❌ Missing | — | — | — | — |

**Note:** Payment gateways are stubs from V3-B3-6 (payment gateway preparation). No plan gating exists yet.

---

## Missing Features

### Critical (core merchant operations, no gate at all)
1. **Order limit** — no `order_limit` column, no `orders` feature key, zero enforcement
2. **Coupon limit** — no `coupon_limit`, no `coupons` feature key
3. **Promotion limit** — no `promotion_limit`, no `promotions` feature key
4. **Coupon/Promotion access** — entire coupon/promotion module has zero plan gating

### High (Plan column exists but zero enforcement)
5. **Custom domain** — `custom_domain_enabled` column on Plan, never read in any controller/view
6. **Analytics/Reports** — `analytics_enabled` column on Plan, never read in any report controller/view

### Medium (planned or obvious features)
7. **Reviews** — no feature key, no gating
8. **Wishlist** — controlled by `WebsiteInfo.enable_wishlist` only (not plan-gated)
9. **Compare** — controlled by `WebsiteInfo.enable_compare` only (not plan-gated)
10. **Maintenance Mode** — controlled by `WebsiteInfo.maintenance_mode` only (not plan-gated)
11. **Theme Editor** — no plan column or feature key
12. **Advanced SEO** — no plan column or feature key
13. **Custom CSS** — no plan column or feature key
14. **All Integrations** (Telegram, TikTok, Facebook, GA, Pixel, Mailchimp, WhatsApp) — no plan gating

---

## Architecture Risks

### 1. Two parallel feature gating systems
**Plan columns** (`analytics_enabled`, `custom_domain_enabled`) AND **`plan_features` table** with `FeatureGate` service. Product types use `PlanFeature/FeatureGate`, while analytics and custom domain use raw boolean columns. No consistent pattern.

### 2. No backend enforcement for boolean Plan columns
`analytics_enabled` and `custom_domain_enabled` exist as Plan columns but are NEVER checked anywhere — no middleware, no controller gate, no FeatureGate key. A merchant on any plan can access all reports and custom domain features.

### 3. Coupon/Promotion modules are fully exposed
Full CRUD controllers, routes, views, and database tables for coupons and promotions — all accessible to every tenant regardless of plan. No gating at any layer.

### 4. `FeatureGate` has hardcoded upgrade hints
`FeatureGate::UPGRADE_HINTS` maps feature keys to plan slugs like `'Starter'` and `'Business'`. These are hardcoded strings, not read from the database. Any plan renamed or restructured requires code changes.

### 5. Deprecated Plan columns create confusion
`price`, `currency`, `interval`, `is_default`, `is_active`, `sort_order` remain on the Plan model with fillable/casts/attributes. New developers might use these instead of the correct `monthly_price`, `yearly_price`, `status` columns.

### 6. Legacy `users.plan_id` column
Migration `2026_05_26_300002` added `plan_id` to users table. The modern system uses `Tenant → Subscription → Plan`, but the legacy column could be accidentally used.

### 7. Frontend hardcodes plan pricing
`UpgradeModal.jsx` has hardcoded prices: "Free ($0)", "Starter ($9/mo)", "Business ($29/mo)". Any price change requires a frontend deploy.

---

## Recommendations

### Features to add as PlanFeature + FeatureGate keys
| Feature Key | Type | Suggested Default |
|-------------|------|-------------------|
| `reviews` | boolean | Free=false, Starter=true, Business=true |
| `coupons` | boolean | Free=false, Starter=true, Business=true |
| `promotions` | boolean | Free=false, Starter=false, Business=true |
| `flash_sales` | boolean | Free=false, Starter=false, Business=true |
| `telegram_integration` | boolean | Free=false, Starter=true, Business=true |
| `ai_product_generator` | boolean | Free=false, Starter=false, Business=true |
| `ai_description` | boolean | Free=false, Starter=false, Business=true |
| `ai_seo` | boolean | Free=false, Starter=false, Business=true |
| `ai_translation` | boolean | Free=false, Starter=false, Business=true |

### Features to add as numeric Plan columns + SubscriptionLimitService
| Column | Type | Suggested Defaults (Free/Starter/Business) |
|--------|------|------------------------------------------|
| `order_limit` | unsignedInteger, nullable | 50 / 500 / null |
| `coupon_limit` | unsignedInteger, nullable | 0 / 20 / 100 |
| `promotion_limit` | unsignedInteger, nullable | 0 / 5 / 20 |
| `image_limit` | unsignedInteger, nullable | null (storage already covers this) |

### Features with existing Plan columns that need enforcement
| Column | Action |
|--------|--------|
| `analytics_enabled` | Add FeatureGate key `reports`, enforce in `ReportController`, gate sidebar link |
| `custom_domain_enabled` | Add FeatureGate key `custom_domain`, enforce before custom domain setup |

### Features to remove/deprecate
| Item | Reason |
|------|--------|
| `price`, `currency`, `interval`, `is_default`, `is_active`, `sort_order` on Plan | Deprecated compat columns — replace all usages with `monthly_price`, `yearly_price`, `status` |
| `users.plan_id` column | Legacy — modern path uses Tenant→Subscription→Plan |
| Hardcoded prices in `UpgradeModal.jsx` | Should read from Plan model API endpoint instead |

### Features to merge
| Merge Into | From |
|------------|------|
| `FeatureGate` system | All boolean plan columns (`analytics_enabled`, `custom_domain_enabled`) should become `PlanFeature` entries, not separate columns |
| `analytics_enabled` | Excel Export, PDF Export, Charts, Reports should all be gated by a single `reports` or `analytics` feature key |
| `SubscriptionLimitService` | All numeric limits (products, staff, storage, orders, coupons, promotions) use the same pattern |

### Features that should become limits instead of booleans
| Feature | Current | Should be |
|---------|---------|-----------|
| Coupons | Uncontrolled | `coupon_limit` numeric column |
| Promotions | Uncontrolled | `promotion_limit` numeric column |

---

## Recommended Plan Matrix

| Feature | Free | Starter | Business |
|---------|------|---------|----------|
| **Product Limit** | 10 | 100 | Unlimited |
| **Staff Limit** | 2 | 5 | Unlimited |
| **Storage Limit** | 100 MB | 1 GB | Unlimited |
| **Order Limit** | 50/mo | 500/mo | Unlimited |
| **Coupon Limit** | 0 | 20 | 100 |
| **Promotion Limit** | 0 | 5 | 20 |
| **Single Products** | ✅ | ✅ | ✅ |
| **Variable Products** | ❌ | ✅ | ✅ |
| **Combo Products** | ❌ | ❌ | ✅ |
| **Digital Products** | ❌ | ❌ | ✅ |
| **Reviews** | ❌ | ✅ | ✅ |
| **Coupons** | ❌ | ✅ | ✅ |
| **Promotions** | ❌ | ❌ | ✅ |
| **Flash Sales** | ❌ | ❌ | ✅ |
| **Analytics/Reports** | ❌ | ✅ | ✅ |
| **Custom Domain** | ❌ | ✅ | ✅ |
| **Advanced SEO** | ❌ | ✅ | ✅ |
| **Custom CSS** | ❌ | ❌ | ✅ |
| **Theme Editor** | ❌ | ❌ | ✅ |
| **Telegram** | ❌ | ✅ | ✅ |
| **AI Features** | ❌ | ❌ | ✅ |
| **Payment Gateways** | COD only | All gateways | All gateways |

---

## Regression Risk

No code was modified. This is a read-only audit of `docs/v3-plan-feature-coverage-audit.md`. Zero regression risk.

Risk for future implementation:
- **Low**: Adding `order_limit`, `coupon_limit`, `promotion_limit` columns is additive — no existing code uses them
- **Medium**: Adding enforcement for `analytics_enabled`, `custom_domain_enabled` would change behavior for tenants on plans that have these disabled
- **Medium**: Removing deprecated Plan columns requires migration plan for all code that references them
- **Low**: Converting hardcoded prices to API-driven requires frontend changes but is backward compatible

## Source Files Referenced

| File | Purpose |
|------|---------|
| `app/Models/Plan.php` | Plan model with limits, features, helpers |
| `app/Services/FeatureGate.php` | Centralized feature access control |
| `app/Services/SubscriptionLimitService.php` | Numeric limit enforcement |
| `app/Enums/ProductType.php` | Product type feature checks |
| `app/Http/Controllers/Admin/AdminProductController.php` | Product CRUD with limit enforcement |
| `app/Http/Controllers/Admin/AdminUserController.php` | Staff creation with limit enforcement |
| `app/Http/Controllers/Admin/AdminBillingController.php` | Billing page with usage data |
| `app/Services/ImageService.php` | Upload storage limit enforcement |
| `database/seeders/PlanSeeder.php` | 3 default plan definitions |
| `database/migrations/2026_05_26_300001_*` | Creates plans and plan_features tables |
| `database/migrations/2026_05_28_000003_*` | Adds SaaS columns to plans |
| `resources/js/Components/ProductType/UpgradeModal.jsx` | Hardcoded plan pricing |
| `routes/web.php` | Route definitions for all modules |
