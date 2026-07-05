# Merchant QA Sprint Report

**Version:** 3.0  
**Date:** 2026-07-05  
**Auditor:** QA Sprint Agent  
**Status:** Complete

---

## Executive Summary

A full-scope quality assurance audit was performed across all merchant-facing modules of the multi-tenant SaaS e-commerce platform. The audit covered the public landing page, storefront, products, cart/checkout, customer authentication, billing, admin panels, image pipeline, multi-tenant isolation, routing, authorization, and code hygiene.

**Score: 5/10 — Not Production Ready**

The platform has foundational architecture strengths (service layer, global tenant scoping via TenantAware trait, centralized image pipeline, Inertia.js reactivity) but is blocked by **3 cross-tenant data leak vulnerabilities**, **widespread hardcoded currency displays**, **broken image rendering in multiple modules**, and **two models with tenant_id columns lacking global scopes**.

**7 P0 (Production Blocking)** issues were identified — all must be resolved before production deployment.

---

## Modules Audited

| Module | Status | Issues Found |
|--------|--------|-------------|
| Public Landing Page | Audited | 6 (P2–P3) |
| Storefront (public) | Audited | 14 (P0–P3) |
| Products (list/detail/variants) | Audited | 5 (P0–P3) |
| Cart & Checkout | Audited | 8 (P0–P3) |
| Customer Authentication | Audited | 3 (P2) |
| Customer Account | Audited | 5 (P0–P3) |
| Merchant Dashboard | Audited | 2 (P1) |
| Merchant Profile | Audited | 1 (P3) |
| Merchant Website Settings | Audited | 1 (P3) |
| Merchant Currency Display | Audited | 15 (P0–P2) |
| Merchant Timezone | Audited | 0 |
| Billing / Subscription | Audited | 12 (P0–P2) |
| Payment Proof Upload | Audited | 3 (P1–P2) |
| Image Upload Pipeline | Audited | 8 (P0–P2) |
| Multi-Tenant Isolation | Audited | 7 (P0–P3) |
| Routes & Controllers | Audited | 8 (P0–P2) |
| Code Audit | Audited | 12 categories |

---

## QA Coverage

- **Image QA:** Verified all model image accessors, upload service, deletion logic, placeholder fallbacks, tenant image isolation
- **Multi-Tenant QA:** Verified product, order, customer, settings, billing, branding isolation gaps
- **Billing QA:** Verified plan display, checkout, payment proof, subscription status, currency formatting across 6+ components
- **UI/UX QA:** Verified responsive layout, empty states, loading states, forms, validation messages, typography, spacing, navigation
- **Regression QA:** Image Upload Pipeline, Currency Management, Pricing UI, Storage Backend, Dynamic URL generation
- **Code Audit:** Hardcoded URLs, currency symbols, storage paths, duplicate feature definitions, unused services, dead code, TODOs/FIXMEs, debug console logs, hardcoded $ storage paths

---

## Architecture Review

### Strengths
- **Service Layer pattern** well-established (`ImageService`, `ImageUploadService`, `SubscriptionLimitService`, `TenantBootstrapService`, etc.)
- **Tenant scoping via global `TenantScope` middleware** — clean pattern applied to most tenant-scoped models
- **Centralized `ImageService::url()`** — single point of control for image URL generation
- **Centralized `formatCurrency()`** in `currency.js` — consistent formatting when used
- **`getPlatformCurrencyConfig()` / `getCurrencyConfig()`** — proper hierarchy (platform → merchant)
- **Inertia.js** for smooth SPA-like navigation
- **Permission-based authorization** with granular checks in most admin controllers
- **`ValidateTenantBinding` middleware** provides defense-in-depth for route-model-bound resources

### Weaknesses
- **Missing `TenantAware` trait** on `PaymentTransaction` and `SubscriptionAuditLog` — financial data leaks
- **`WebsiteInfo::first()` leaks on root domain** — 4 locations expose random tenant branding to all visitors
- **No tenant isolation in image storage paths** — `/storage/*` URLs are world-readable with no tenant auth
- **Hardcoded currency across 15+ frontend components** — no adherence to `getCurrencyConfig()` or `getPlatformCurrencyConfig()`
- **Hardcoded `'USD'` / `'$'` in all billing components** — platform currency setting is ignored
- **Duplicate order creation logic** in 3 controllers — maintenance risk
- **`BillingPaymentMethodPolicy` unregistered** — policy file exists but is never bound
- **`ActivityLog` and `Role` exempt from tenant scoping** — logs and roles leak across tenants
- **3 service classes exist but are never used** — dead code
- **`FeatureGate` DEV_MODE always bypasses subscription restrictions** — TODOs indicate this was meant to be re-enabled

---

## Regression Analysis

### Image Upload Pipeline (recently implemented)
- **REGRESSION:** `ImageService::getFileSize()` returns 0 for HTTP URLs — Cloudinary storage quota never released
- **REGRESSION:** `ImageUploadService::storeToDisk()` does not check `storeAs()` return value — `false` could be stored in DB
- **REGRESSION:** Filename generation uses `uniqid()` without `$more_entropy=true` — collision risk under concurrency
- **OK:** All model image accessors correctly use `ImageService::url()`
- **OK:** Image validation rules are consistent (max 2MB, jpeg/png/jpg/webp)

### Currency Management (recently implemented)
- **REGRESSION:** SuperAdmin billing components (PlanCards, UpgradePlan, Checkout, etc.) still hardcode `{ code: 'USD', symbol: '$' }` — ignore `getPlatformCurrencyConfig()`
- **REGRESSION:** Storefront components (ProductCard, Cart, Checkout, Orders) hardcode `' MMK'` — ignore `getCurrencyConfig()`
- **REGRESSION:** `helpers.js::formatCurrency()` strips symbol and position config — only outputs `"1,000 MMK"` format
- **OK:** SuperAdmin pages (Dashboard, Plans, Tenants) correctly use `getPlatformCurrencyConfig()`

### Pricing UI (recently implemented)
- **REGRESSION:** `PricingSection.jsx` uses raw `{pc.symbol}` concatenation for effective monthly price instead of `formatCurrency()`
- **OK:** Plan feature list renders dynamically from DB via `PlanFeatureList.jsx`
- **OK:** Yearly savings calculation works correctly

### Storage Backend (recently implemented)
- **FIXED:** `FILESYSTEM_DISK` changed from `cloudinary` to `public` in `.env`
- **FIXED:** `ImageService::delete()` guarded to only use Cloudinary when default disk is cloudinary
- **OK:** `ImageService::url()` already uses `config('filesystems.default')` dynamically

### Dynamic URL Generation (recently implemented)
- **REGRESSION:** Brand model has no `logo_url` accessor — raw relative path rendered as `<img src>` in Brands index/edit
- **REGRESSION:** `PaymentScreenshotUrlAttribute` returns `string` (always truthy) — SVG placeholder shows for every order
- **OK:** Product, ProductVariant, WebsiteInfo, PaymentMethod, BillingPaymentMethod all have correct URL accessors

---

## Multi-Tenant Verification

| Tenant Boundary | Status | Verification |
|----------------|--------|-------------|
| Products (Merchant A → Merchant B) | **FAIL** | Products use `TenantAware` trait — **SECURE** |
| Orders (Merchant A → Merchant B) | **FAIL** | Orders use `TenantAware` trait — **SECURE** |
| Customers (cross-tenant) | **PASS** | Users scoped via tenant relation — **SECURE via route auth** |
| Images (direct URL access) | **FAIL** | No tenant isolation in `/storage/*` — **NOT SECURE** |
| Settings (landing page) | **FAIL** | `WebsiteInfo::first()` leaks on root domain — **NOT SECURE** |
| Payment Transactions | **FAIL** | No `TenantAware` trait — **NOT SECURE** |
| Subscription Audit Log | **FAIL** | No `TenantAware` trait — **NOT SECURE** |
| Payment Proofs | **PASS** | Accessed through PaymentIntent (TenantAware) — **SECURE** |
| Branding (logo/banner) | **FAIL** | Images in `/storage/*` have no tenant auth — **NOT SECURE** |
| Role/Activity Log | **FAIL** | Exempt from TenantScope — **NOT SECURE** |

---

## Image Upload Verification

| Check | Status | Details |
|-------|--------|---------|
| Images display correctly (local) | **PASS** | Product, variant, brand images work when using `ImageService::url()` or `assetUrl()` |
| Images display correctly (brands) | **FAIL** | Brand logo has no URL accessor — raw path used in Brands Index/Edit |
| Image URLs valid | **PASS** | `ImageService::url()` correct for local and HTTP paths |
| Compression broke rendering | **PASS** | ImageOptimizationService wraps in try/catch, no SVG/compression issues found |
| Missing image fallback | **PARTIAL** | ProductCard handles null; OrderDetailModal always shows placeholder; Brand Index has broken images |
| Tenant image isolation | **FAIL** | No tenant prefix in storage paths; direct URL access bypasses tenant auth |
| Old uploaded images display | **PASS** | HTTP URLs pass through unchanged; local paths resolve correctly |
| Storage quota released (local) | **PASS** | `releaseStorage()` called correctly for local deletions |
| Storage quota released (cloud) | **FAIL** | `getFileSize()` returns 0 for HTTP URLs — quota never released |

---

## Billing Verification

| Check | Status | Details |
|-------|--------|---------|
| Plan display | **FAIL** | All 6 billing components hardcode `{ code: 'USD', symbol: '$' }` — platform currency ignored |
| Checkout flow | **FAIL** | Price breakdown always uses `monthly_price`, ignores yearly billing cycle; hardcoded USD |
| Payment Proof upload | **PARTIAL** | File upload works; missing client-side 5MB size validation |
| Payment History | **FAIL** | Custom `formatCurrency` in PaymentHistory.jsx hardcodes `$` for non-MMK currencies |
| Subscription Status | **PASS** | Status badges, trial warnings, expiry display correct |
| Billing Dashboard | **PARTIAL** | Delegates to hardcoded-USD sub-components |
| Subscription.jsx (detail) | **FAIL** | Renders raw `subscription.price` value with no currency formatting |

---

## Currency Verification

| Context | Status | Details |
|---------|--------|---------|
| SuperAdmin pages | **PASS** | Dashboard, Plans, Tenants use `getPlatformCurrencyConfig(platform_setting)` |
| Admin Billing components (6) | **FAIL** | All hardcode `{ code: 'USD', symbol: '$', decimals: 0 }` |
| Payment.jsx | **FAIL** | Hardcoded `$` dollar signs throughout (lines 470, 548, 657-661) |
| PaymentHistory.jsx | **FAIL** | Custom wrapper hardcodes `$` for non-MMK currencies |
| Storefront/Show.jsx | **FAIL** | `const currency = ' MMK'` (line 98) + raw `toLocaleString()` concatenation |
| ProductCard.jsx | **FAIL** | Hardcoded `<span>MMK</span>` in PriceDisplay (10+ lines) |
| Cart.jsx | **FAIL** | Hardcoded `' MMK'` in all price displays (7+ locations) |
| Checkout.jsx (storefront) | **FAIL** | Hardcoded `' MMK'` in all price displays (12+ locations) |
| Orders.jsx / OrderShow.jsx | **FAIL** | Hardcoded `' MMK'` in price displays |
| Admin Dashboard | **FAIL** | `formatCurrency(amount)` without config defaults to MMK |
| Admin Product pages | **FAIL** | `formatCurrency(price)` without config defaults to MMK |
| Admin Reports (Sales, ProductSales, Payments, Promotions) | **FAIL** | All default to MMK via `helpers.js` wrapper |
| helpers.js `formatCurrency` | **FAIL** | Strips symbol and position config; always outputs `"amount MMK"` |

---

## UI/UX Review

| Check | Status | Details |
|-------|--------|---------|
| Responsive layout | **PASS** | Tailwind responsive classes used throughout |
| Empty states | **PASS** | `EmptyStoreState.jsx` exists; product/cart/order empties handled |
| Loading states | **PARTIAL** | Some components lack loading indicators (Payment.jsx, Orders.jsx) |
| Buttons | **PASS** | Consistent button styling |
| Forms | **PASS** | Form validation present in most views |
| Validation messages | **PASS** | Inertia validation errors displayed consistently |
| Success messages | **PARTIAL** | Addresses.jsx shows `flash.success` but not `flash.error` |
| Error messages | **PARTIAL** | Not all error states have user-facing messages (e.g., `console.error` used instead in hooks) |
| Typography | **PASS** | Consistent text sizing/weight |
| Spacing | **PASS** | Consistent padding/margin |
| Card consistency | **PASS** | Product cards, plan cards have consistent layout |
| Color consistency | **PASS** | Tailwind gray/indigo/emerald palette used consistently |
| Navigation flow | **PARTIAL** | Wishlist link in ShopNavbar not store-scoped; anchor link on HeroSection may cause full reload |

---

## Security Review

| Check | Status | Details |
|-------|--------|---------|
| CSRF protection | **PASS** | Web routes use `web` middleware with CSRF; webhook endpoints excluded intentionally |
| Auth on all state-changing routes | **PARTIAL** | Most POST/PUT/DELETE routes protected, but `/cart/*` is session-based (intentional) |
| Authorization defense-in-depth | **PARTIAL** | Admin controllers use `$user->can()`; SuperAdmin controllers have zero defense-in-depth |
| Tenant isolation enforcement | **FAIL** | 2 models missing tenant scopes; 3 cross-tenant data leak vectors |
| XSS vectors | **PARTIAL** | SVG allowed in website settings uploads — potential XSS if rendered unsanitized |
| Rate limiting | **FAIL** | No rate limiting on checkout, login, or registration routes |
| Password reset for storefront customers | **FAIL** | No storefront-specific password reset flow; global `/forgot-password` redirects to platform login |
| File upload validation | **PARTIAL** | No dimension validation on any image upload; no client-side max-size check on payment proof |

---

## Performance Observations

| Observation | Severity | Details |
|------------|----------|---------|
| `uniqid()` without `more_entropy` in filename generation | Medium | Collision risk under high concurrency uploads |
| TOCTOU race condition in storage limit check | Low | Check-then-increment between `assertStorageLimit` and `trackStorage` |
| `DB::table()` in admin analytics | Low | Acceptable for aggregate queries; tenant scope applied manually |
| No image dimension limits | Low | Large images stored at full resolution — no server-side resize/resample |
| `all()` calls without tenant scope in CLI commands | Medium | `WebsiteInfo::all()`, `Role::all()` in console commands could affect wrong tenants |

---

## P0 Issues

### P0-1: Cross-tenant data leak via `WebsiteInfo::first()` on root domain
**Files:**
- `app/View/Components/GuestLayout.php:15` — `WebsiteInfo::first()` without tenant context
- `app/View/Components/AppLayout.php:15` — same
- `app/Http/Middleware/HandleInertiaRequests.php:56` — same, shared to ALL Inertia pages
- `app/Http/Middleware/CheckMaintenanceMode.php:26` — `WebsiteInfo::getSettings()` → `self::first()`

**Impact:** When no tenant is set (landing page, unauthenticated users), `WebsiteInfo::first()` returns the first record from ANY tenant. Branding, name, logo, contact info from Tenant A is displayed to all visitors.

### P0-2: `PaymentTransaction` has no `TenantAware` trait — cross-tenant financial data leak
**File:** `app/Models/PaymentTransaction.php`

**Impact:** Has `tenant_id` column but no global `TenantScope`. All payment transaction queries return data across all tenants. Payment history, amounts, and gateway data are visible cross-tenant.

### P0-3: `SubscriptionAuditLog` has no `TenantAware` trait — cross-tenant audit log leak
**File:** `app/Models/SubscriptionAuditLog.php`

**Impact:** Same as P0-2. Subscription status changes, plan changes, and billing events leak across tenants.

### P0-4: Image storage paths not tenant-isolated — cross-tenant image access
**Files:**
- `app/Services/ImageUploadService.php:57` — `$file->storeAs($folder, $filename, $disk)` without tenant prefix
- `app/Services/ImageService.php:176` — `asset('storage/' . $path)` generates URLs without tenant scope

**Impact:** Any user can access any tenant's uploaded images by guessing the filename at `/storage/{folder}/{filename}`. No middleware, no authentication on the storage path.

### P0-5: All billing components hardcode `{ code: 'USD', symbol: '$' }` — ignores platform currency
**Files:**
- `resources/js/Pages/Admin/Billing/Checkout.jsx` (lines 148, 155, 300, 316, 329, 334)
- `resources/js/Components/Billing/PlanCards.jsx` (lines 48, 65, 73)
- `resources/js/Pages/Admin/Billing/UpgradePlan.jsx` (lines 97, 122, 130)
- `resources/js/Components/Billing/UpgradeDialog.jsx` (line 142)
- `resources/js/Components/Billing/CurrentPlanCard.jsx` (line 24)
- `resources/js/Components/Billing/SubscriptionSummaryCard.jsx` (line 16)
- `resources/js/Pages/Admin/Billing/Payment.jsx` (lines 470, 548, 657-661)

**Impact:** Platform currency setting is completely ignored in billing. If SuperAdmin sets currency to MMK/THB/SGD, all billing pages still show `$`.

### P0-6: All storefront components hardcode `' MMK'` — ignores merchant's currency setting
**Files:**
- `resources/js/Pages/Storefront/Show.jsx:98` — `const currency = ' MMK'`
- `resources/js/Components/ProductCard.jsx` (10+ locations) — hardcoded `<span>MMK</span>`
- `resources/js/Pages/Storefront/Cart.jsx` (7+ locations) — hardcoded `' MMK'`
- `resources/js/Pages/Storefront/Checkout.jsx` (12+ locations) — hardcoded `' MMK'`
- `resources/js/Pages/Storefront/Orders.jsx` (line 75) — hardcoded `' MMK'`
- `resources/js/Pages/Storefront/OrderShow.jsx` (5+ locations) — hardcoded `' MMK'`

**Impact:** If merchant sets their store currency to anything other than MMK (e.g., USD, THB), ALL customer-facing prices display the wrong currency code. Incorrect pricing may lead to financial losses.

### P0-7: Brand model has no `logo_url` accessor — brand logo images are broken
**Files:**
- `app/Models/Brand.php` — missing `getLogoUrlAttribute()` accessor
- `resources/js/Pages/Admin/Brands/Index.jsx:83` — uses `brand.logo` raw relative path as `<img src>`
- `resources/js/Pages/Admin/Brands/Edit.jsx:58` — same

**Impact:** Brand logos rendered as `<img src="brands/abc.jpg">` which is a relative URL path, not from `/storage/`. All brand logos on merchant admin pages display as broken images.

---

## P1 Issues

### P1-1: Checkout.jsx price breakdown ignores yearly billing cycle
**File:** `resources/js/Pages/Admin/Billing/Checkout.jsx` (lines 298-330)

**Impact:** Subtotal/total always use `selectedPlan.monthly_price` even when `intent?.billing_cycle === 'yearly'`. Yearly subscribers see incorrect monthly pricing on the checkout confirmation page.

### P1-2: `ImageService::getFileSize()` returns 0 for HTTP URLs (Cloudinary)
**File:** `app/Services/ImageService.php:116`

**Impact:** When Cloudinary images are deleted via `delete()`, `getFileSize()` returns 0, so `releaseStorage()` is never called. Storage quota leaks on every Cloudinary image deletion.

### P1-3: `AdminProductController::bulkDestroy()` skips gallery_images and seo_image deletion
**File:** `app/Http/Controllers/Admin/AdminProductController.php` (lines 713-714)

**Impact:** Bulk product deletion does not delete `gallery_images` or `seo_image` files. Orphaned files accumulate on disk (storage leak).

### P1-4: Tenant deletion service uses raw DB — images never deleted
**File:** `app/Services/TenantDeletionService.php:229`

**Impact:** Raw `DB::table('products')->where('tenant_id', ...)->delete()` bypasses Eloquent events. All product photos, brand logos, website images, etc. remain on disk forever when a tenant is deleted.

### P1-5: `BillingPaymentMethodPolicy` is unregistered
**File:** `app/Policies/BillingPaymentMethodPolicy.php` (exists but not registered in `AppServiceProvider.php`)

**Impact:** Any controller calling `$this->authorize()` for billing payment methods will receive an HTTP 403 because the policy is never registered via `Gate::policy()`.

### P1-6: Order creation logic duplicated in 3 controllers
**Files:**
- `app/Http/Controllers/OrderController.php:store()` (165 lines)
- `app/Http/Controllers/StorefrontCheckoutController.php:store()` (164 lines)
- `app/Http/Controllers/Client/ClientOrderController.php:store()` (180 lines)

**Impact:** Three separate implementations of order creation logic. Bug fixes must be applied in all three places. Security vulnerabilities in one affect all three.

### P1-7: `AdminCouponController` and `AdminPromotionController` list queries lack tenant scope
**Files:**
- `app/Http/Controllers/Admin/AdminCouponController.php:35` — `Coupon::withCount(...)->latest()->paginate(15)` — no tenant scope
- `app/Http/Controllers/Admin/AdminPromotionController.php:30` — same pattern
- `app/Http/Controllers/Admin/AdminPromotionController.php:243` — `search()` also unscoped

**Impact:** These load coupons/promotions across ALL tenants. The `ValidateTenantBinding` middleware doesn't protect index/search routes (no route model binding).

### P1-8: `FeatureGate::DEV_MODE = false` bypass enables all features regardless of plan
**File:** `app/Services/FeatureGate.php` (lines 180-185, 274-278, 299-303, 384-389)

**Impact:** 4 TODO comments indicate subscription restrictions should be re-enabled but `DEV_MODE = false` keeps the bypass active. Free plan merchants can use all features without upgrading.

### P1-9: Duplicate feature definitions across 7+ files
**Files:** `FeatureGate.php`, `PublicLandingController.php`, `AdminBillingController.php`, `Plans/Edit.jsx`, `Plans/Create.jsx`, `PlanSeeder.php`, `ProductType.php`

**Impact:** Feature key lists (`single_products`, `custom_domain`, etc.) are copied across files. Adding a new feature requires updating 7+ locations. Drift risk.

### P1-10: Order accessor returns `string` (always truthy) — SVG placeholder shown for all orders
**Files:**
- `app/Models/Order.php:264-267` — `getPaymentScreenshotUrlAttribute()` returns `string`, never null
- `resources/js/Components/OrderDetailModal.jsx:431` — `{screenshotUrl && (` always true

**Impact:** Every order without a payment screenshot shows the SVG "No Image" placeholder in the payment screenshot area, confusing order processors.

---

## P2 Issues

### P2-1: `ImageUploadService::storeAs()` return value not checked
**File:** `app/Services/ImageUploadService.php:57`

**Risk:** `$file->storeAs()` can return `false` on failure. The `false` value gets stored in the database and logged as `'path' => false`.

### P2-2: `uniqid()` without `$more_entropy=true` in filename generation
**File:** `app/Services/ImageUploadService.php:52`

**Risk:** Collision possible under high-concurrency uploads (multiple requests in same microsecond). Should use `Str::uuid()` or `uniqid('', true)`.

### P2-3: `PaymentHistory.jsx` custom `formatCurrency` hardcodes `$` for non-MMK
**File:** `resources/js/Pages/Admin/Billing/PaymentHistory.jsx:81-89`

**Impact:** Non-MMK currencies display `$` symbol instead of their correct symbol (e.g., THB shows `$` instead of `฿`).

### P2-4: `helpers.js::formatCurrency()` strips symbol and position
**File:** `resources/js/Utils/helpers.js:16-19`

**Impact:** Admin Dashboard, Product pages, and Reports all call `formatCurrency(amount)` without config, getting `"1000 MMK"` with no symbol — ignores the merchant's configured currency entirely.

### P2-5: `Subscription.jsx` renders raw price with no formatting
**File:** `resources/js/Pages/Admin/Billing/Subscription.jsx:54`

**Impact:** `{subscription.price || 'N/A'}` displays raw number like "29.99" with no currency symbol.

### P2-6: `Checkout.jsx` (admin billing) `uploading` state never reset on success
**File:** `resources/js/Pages/Admin/Billing/Checkout.jsx:210`

**Impact:** After successful payment submission, `uploading` remains `true` forever, showing a persistent progress bar. No UX feedback for the user.

### P2-7: No client-side file size validation on payment proof upload
**File:** `resources/js/Pages/Admin/Billing/Payment.jsx:177`

**Impact:** UI says "Max 5MB" but there is no client-side file size check. Large files are submitted to server and rejected only after upload completes.

### P2-8: Storefront wishlist link not store-scoped
**File:** `resources/js/Components/ShopNavbar.jsx:122`

**Impact:** `<Link href="/wishlist">` navigates to platform-level `/wishlist` instead of store-scoped path. Customer may see wrong wishlist data.

### P2-9: SuperAdmin controllers lack defense-in-depth authorization
**Files:** All 10+ controllers in `app/Http/Controllers/SuperAdmin/`

**Impact:** Zero internal `$user->can()` or `$this->authorize()` checks. If route middleware were misconfigured, all SuperAdmin operations are exposed.

### P2-10: `WebsiteInfo.php` default `currency_symbol` is `'K'` instead of `'Ks'`
**File:** `app/Models/WebsiteInfo.php:114`

**Impact:** New WebsiteInfo records default to `'K'` as currency symbol, inconsistent with `'Ks'` used everywhere else.

### P2-11: Orphaned QR preview modals in Storefront and Checkout
**Files:**
- `resources/js/Pages/Storefront/Show.jsx:788-800` — dead modal for QR preview
- `resources/js/Pages/Storefront/Checkout.jsx:788-800` — same dead modal

**Impact:** 25+ lines of dead JSX code with unreachable modal. `qrPreview` state is never populated.

### P2-12: `Role` and `ActivityLog` exempt from tenant scoping
**File:** `app/Models/Scopes/TenantScope.php:16-17`

**Impact:** `Role::all()` and `ActivityLog::all()` return data across all tenants. Roles leak permission structures; activity logs leak all user actions.

### P2-13: No rate limiting on checkout, login, or registration routes
**Impact:** Susceptible to brute force attacks on login and abuse of checkout endpoint.

### P2-14: `formatCurrency` default decimals opinionated (0 for MMK, 2 for others)
**File:** `resources/js/Utils/currency.js:39`

**Impact:** JPY would incorrectly show 2 decimal places; platform currency setting's decimal_places is ignored when calling `formatCurrency` without explicit decimals.

### P2-15: Dashboard `formatMoney` defaults to MMK
**Files:**
- `resources/js/Pages/Admin/Dashboard.jsx:48`
- `resources/js/Pages/Admin/Products/Show.jsx:42`
- `resources/js/Components/ProductForm/SidebarSection.jsx:42`
- `resources/js/Pages/Admin/Promotions/Reports.jsx:19`

**Impact:** All these call `formatCurrency(amount)` without config, defaulting to MMK regardless of merchant's currency setting.

---

## P3 Issues

### P3-1: Hardcoded marketing copy on landing page
**Files:** `HeroSection.jsx:17-30`, `PricingSection.jsx:36-39`

**Details:** "Launch your online store in minutes", "Built for Myanmar Merchants" — not localizable without code changes.

### P3-2: Anchor link on HeroSection may cause full page reload
**File:** `resources/js/Components/PublicLanding/HeroSection.jsx:41`

**Details:** `href="/#pricing"` may trigger full navigation in Inertia SPA instead of smooth scroll.

### P3-3: `PromotionBanner` and `User` models lack image URL accessors
**Files:** `app/Models/PromotionBanner.php`, `app/Models/User.php`

**Details:** `promotion.image` and `user.profile_image` have no URL accessor. Frontend receives raw paths.

### P3-4: `PlatformSetting` model lacks URL accessors for logo/favicon
**File:** `app/Models/PlatformSetting.php`

**Details:** No `site_logo_url` or `favicon_url` accessors. Frontend uses `assetUrl()` to compensate.

### P3-5: Debug `console.log` statements in production JS code
**Files:** `public/js/checkout.js:23,257,261`, `public/js/orders.js:77`

**Details:** Console logs in compiled JS reveal debugging information to users.

### P3-6: `console.error()` in hooks (useCart, useWishlist, usePusherNotifications) exposed to users
**Files:** Multiple hook files in `resources/js/Hooks/`

**Details:** Error messages logged to console may contain sensitive data. Should use a silent error handler.

### P3-7: Fragile pagination label replacement in Orders
**File:** `resources/js/Pages/Storefront/Orders.jsx:104`

**Details:** `.replace('Previous', '\u2190')` breaks if backend changes locale or label format.

### P3-8: `Storefront/Login.jsx` forgot-password link not store-scoped
**File:** `resources/js/Pages/Storefront/Login.jsx:87`

**Details:** Links to global `/forgot-password` which redirects to platform login, confusing storefront customers.

### P3-9: SVG/ICO allowed in website settings uploads — potential XSS
**File:** `app/Http/Requests/Admin/UpdateWebsiteSettingsRequest.php`

**Details:** SVG files can contain `<script>` tags. If rendered unsanitized, this is an XSS vector.

### P3-10: No dimension validation on image uploads
**Files:** All form request image validation rules

**Details:** No `dimensions:min_width=...,max_width=...` constraints. Large images uploaded at full resolution.

### P3-11: `CurrencyFormatter` service class is unused
**File:** `app/Services/CurrencyFormatter.php`

**Details:** Zero imports. Dead code.

### P3-12: `BillingPaymentMethodService` service class is unused
**File:** `app/Services/BillingPaymentMethodService.php`

**Details:** Zero imports. Dead code.

### P3-13: `WebhookDispatcher` service class is unused
**File:** `app/Services/Payment/Platform/WebhookDispatcher.php`

**Details:** Zero imports. Dead code.

### P3-14: Tunisian phone placeholder in checkout
**File:** `resources/views/client/cart/checkout.blade.php:67`

**Details:** `placeholder="+216 XX XXX XXX"` is a Tunisian phone format. Should match the Myanmar/local market.

### P3-15: `PaymentScreenshotUrlAttribute` returns `string` instead of `?string`
**File:** `app/Models/Order.php:264`

**Details:** Typehint should be `?string` to indicate the value can be empty/placeholder.

### P3-16: SuperAdmin Dashboard uses currency fallback `'MMK'` 
**File:** `resources/js/Pages/SuperAdmin/Financial/Index.jsx:419-426`

**Details:** `fmt(stats.*, 'MMK')` (5 occurrences) — falls back to hardcoded MMK instead of using platform currency config.

### P3-17: `useCurrency.js` hook is available but no billing component uses it
**File:** `resources/js/Hooks/useCurrency.js`

**Details:** The hook correctly resolves merchant-level currency but remains unused by billing components.

---

## Recommendations

### Immediate (Pre-Production)

1. **Add `TenantAware` trait to `PaymentTransaction` and `SubscriptionAuditLog`** — Fixes P0-2 and P0-3
2. **Replace all `WebsiteInfo::first()` calls** with tenant-aware query that returns null when no tenant context — Fixes P0-1
3. **Add `tenant_id` prefix to image storage paths** (e.g., `{tenant_id}/{folder}/{filename}`) — Fixes P0-4
4. **Replace all hardcoded `{ code: 'USD', symbol: '$' }` in billing components** with `getPlatformCurrencyConfig(platform_setting)` — Fixes P0-5
5. **Replace all hardcoded `' MMK'` in storefront components** with `formatCurrency()` using `getCurrencyConfig(platform_setting, website_info)` — Fixes P0-6
6. **Add `getLogoUrlAttribute()` accessor to Brand model** and update Brands Index/Edit to use `assetUrl(brand.logo)` — Fixes P0-7

### Short-Term (First Week of Production)

7. **Fix Checkout.jsx to respect `intent?.billing_cycle`** for price breakdown — P1-1
8. **Fix `ImageService::getFileSize()` for HTTP URLs** — use Content-Length header or DB-stored file size — P1-2
9. **Fix `bulkDestroy()` to clean up gallery and SEO images** — P1-3
10. **Register `BillingPaymentMethodPolicy`** in `AuthServiceProvider` — P1-5
11. **Fix `Order::getPaymentScreenshotUrlAttribute()` to return `?string`** and update OrderDetailModal to check raw field — P1-10
12. **Fix `helpers.js::formatCurrency()` to accept and forward config** — P2-4
13. **Fix `Subscription.jsx` price formatting** — P2-5
14. **Fix Checkout.jsx uploading state not resetting** — P2-6
15. **Fix ShopNavbar wishlist link to be store-scoped** — P2-8

### Medium-Term

16. **Extract duplicate order creation logic into a shared `OrderService`** — P1-6
17. **Consolidate all feature definitions into single `FeatureRegistry`** — P1-9
18. **Re-enable `FeatureGate` subscription restrictions** by setting `DEV_MODE = false` and implementing enforcement — P1-8
19. **Add storage-level tenant isolation middleware** for `/storage/*` path — P0-4 follow-up
20. **Add rate limiting** to login, checkout, and registration routes — P2-13
21. **Add client-side file size validation** to payment proof upload — P2-7
22. **Remove dead code** (QR preview modals, unused services) — P2-11, P3-11/12/13
23. **Fix WebsiteInfo default currency_symbol** — P2-10

### Long-Term

24. **Add `image_url` accessors** to remaining models (PromotionBanner, User, PlatformSetting, Tenant) — P3-3/4
25. **Implement storefront password reset flow** — Security review finding
26. **Replace image filename generation with UUIDs** — P2-2
27. **Add image dimension validation** to form requests — P3-10
28. **Remove `console.*` debug statements** — P3-5/6
29. **Implement proper error logging service** to replace `console.error()` in hooks

---

## Manual QA Checklist

### Landing Page
- [ ] Verify pricing toggle shows correct monthly/yearly prices
- [ ] Verify plan features render dynamically from DB
- [ ] Verify CTA button navigates to Create Store page
- [ ] Verify responsive layout at 320px, 768px, 1024px, 1440px

### Storefront
- [ ] Verify store logo renders correctly (use `assetUrl()`)
- [ ] Verify banner images render correctly
- [ ] Verify theme/branding colors apply to storefront
- [ ] Verify navigation links are scoped to current store

### Products
- [ ] Create product with photo1, photo2, gallery images (3+ images), SEO image
- [ ] Verify all images display on product detail page
- [ ] Create product variant with image — verify display
- [ ] Verify stock badge shows correct status (In Stock / Out of Stock)
- [ ] Verify related products display
- [ ] Verify brand and category display

### Product Images — Edge Cases
- [ ] Verify product with NO photos shows placeholder image
- [ ] Upload a very large image file (>2MB) — verify validation error
- [ ] Upload a non-image file (PDF) — verify validation error
- [ ] Update product photo — verify old photo is deleted from storage
- [ ] Delete product — verify all associated images are deleted from storage
- [ ] Verify brand logo displays correctly in brand list and edit pages

### Cart
- [ ] Add product to cart as guest user
- [ ] Add product to cart as logged-in customer
- [ ] Update quantity in cart
- [ ] Remove item from cart
- [ ] Verify cart persists across page navigations
- [ ] Verify cart shows correct item count in navbar

### Checkout (Storefront)
- [ ] Complete checkout as guest user
- [ ] Complete checkout as logged-in customer
- [ ] Apply valid coupon code — verify discount
- [ ] Apply expired coupon — verify error message
- [ ] Upload payment proof during checkout
- [ ] Verify order confirmation page shows correct total

### Customer Authentication
- [ ] Register new customer account
- [ ] Login with valid credentials
- [ ] Login with invalid credentials — verify error message
- [ ] Forgot password flow (if implemented)
- [ ] Logout — verify session cleared

### Customer Account
- [ ] View order history
- [ ] View single order details
- [ ] Upload payment proof for order
- [ ] Verify prices display with correct currency

### Merchant Dashboard
- [ ] Verify all stat cards load
- [ ] Verify product list shows correct data
- [ ] Verify orders list shows correct data
- [ ] Verify revenue/analytics charts render

### Merchant Website Settings
- [ ] Update store name — verify reflected on storefront
- [ ] Upload/update store logo — verify displays on storefront
- [ ] Upload/update store banner — verify displays on storefront
- [ ] Change currency — verify all prices use new currency on storefront
- [ ] Change timezone — verify order timestamps use new timezone

### Billing
- [ ] View current plan details
- [ ] View available plans with correct pricing
- [ ] Start upgrade flow — verify checkout shows correct plan info
- [ ] Verify currency matches platform setting
- [ ] Upload payment proof for billing
- [ ] View payment history
- [ ] Verify subscription status displays correctly

### Multi-Tenant
- [ ] Create Tenant A with 2 products, 1 order, custom logo
- [ ] Create Tenant B with different products, orders, logo
- [ ] Verify Tenant A admin cannot see Tenant B products/orders/customers
- [ ] Verify Tenant B admin cannot see Tenant A products/orders/customers
- [ ] Verify direct URL access to Tenant A's image while logged into Tenant B returns broken/denied

### Image Regression
- [ ] Upload product image — verify correct path stored in DB
- [ ] Verify image URL resolves correctly in browser
- [ ] Delete product — verify image file removed from disk
- [ ] Update product photo1 — verify old file removed, new file stored
- [ ] Bulk delete products — verify ALL associated images removed

---

## Production Readiness Score

| Category | Score | Notes |
|----------|-------|-------|
| Multi-Tenant Isolation | 3/10 | 3 critical data leaks, 2 models missing scopes |
| Currency Display | 2/10 | 15+ components hardcode USD/MMK; platform setting ignored |
| Image Pipeline | 6/10 | Missing accessors, storage quota leak, collision risk |
| Billing System | 4/10 | Hardcoded USD, yearly billing ignored, policy unregistered |
| Storefront UI | 5/10 | Multiple hardcoded currency, broken wishlist link |
| Auth & Authorization | 6/10 | Missing rate limiting, storefront password reset, SuperAdmin defense |
| Code Quality | 5/10 | Duplicate order logic, dead code, TODOs, debug logs |
| Security | 4/10 | Cross-tenant leaks, SVG XSS vector, no rate limiting |

**Overall: 5/10 — Not Production Ready**

**7 P0 issues** must be resolved before production deployment. All P0 fixes are contained to model traits, frontend currency calls, and a single model accessor — no database migrations or architectural changes required.
