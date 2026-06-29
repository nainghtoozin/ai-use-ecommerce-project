# V3 Public Landing Page — Developer Guide

**Step:** V3-B3-5J — Public SaaS Landing & Dynamic Pricing

---

## Architecture

```
Route: GET / → PublicLandingController@index
                │
                ▼
          Inertia: Public/Landing.jsx
                │
          ┌─────┼─────────┬──────────┬──────────┐
          ▼     ▼         ▼          ▼          ▼
    HeroSection  Benefits  Features  Pricing  FeatureMatrix
    (static)     (static)  (static)  (dynamic)  (dynamic)
                                        │            │
                                        ▼            ▼
                                    Plan model  FeatureGate
                                    + limits    + categories
```

### Three-System Separation

The application has three independent experiences. This landing page lives exclusively in **System 1**:

| System | Route | Purpose | Entry |
|--------|-------|---------|-------|
| **Public SaaS Website** | `/` | Marketing, pricing, registration | `PublicLandingController` |
| **Merchant Dashboard** | `/admin` | Store management, billing | `Admin/*` controllers |
| **Merchant Storefront** | `/store/{slug}` | Customer shopping | `Storefront*` controllers |

**No storefront logic exists in the landing page.** The three systems remain completely separated.

---

## Component Hierarchy

```
Pages/Public/Landing.jsx                — Main page (Head, sections, layout)
  Layouts/PlatformLayout.jsx            — Shared layout (navbar + footer)
    Components/PlatformNavbar.jsx        — Sticky nav with auth state
    Components/PlatformFooter.jsx        — Dynamic footer
  Components/PublicLanding/
    HeroSection.jsx                     — Headline, CTAs, trust badges
    BenefitsSection.jsx                 — "Why Choose Us" grid (6 items)
    FeaturesSection.jsx                 — Core features grid (9 items)
    PricingSection.jsx                  — Dynamic pricing cards
    FeatureComparisonMatrix.jsx         — Full feature table
    FaqSection.jsx                      — Accordion (8 items)
    FinalCtaSection.jsx                 — Bottom CTA
```

---

## Data Sources

### Global (shared via `HandleInertiaRequests`)

| Prop | Source | Usage |
|------|--------|-------|
| `platform_setting` | `PlatformSetting::current()` | Site name, logo, support email |
| `auth` | Laravel Auth | Login state, current plan badge |
| `flash` | Session | Success/error messages |

### Controller (from `PublicLandingController@index`)

| Prop | Source | Usage |
|------|--------|-------|
| `plans` | `Plan::active()->ordered()->get()` | Pricing cards, comparison matrix |
| `featureCategories` | Hardcoded categories + `FeatureGate::getAllFeatureDefinitions()` | Comparison matrix rows |
| `allFeatureDefs` | `FeatureGate::getAllFeatureDefinitions()` | Feature labels, hints |

### Plan Data Structure

Each plan object in `plans` array:
```js
{
    id: number,
    name: string,
    slug: string,              // 'free' | 'starter' | 'business'
    description: string|null,
    monthly_price: number|null,
    yearly_price: number|null,
    yearly_savings_percent: number,
    limits: {
        product_limit: number|null,        // null = unlimited
        staff_limit: number|null,
        storage_limit: number|null,
        orders_monthly_limit: number|null,
        coupon_limit: number|null,
        promotion_limit: number|null,
        flash_sale_limit: number|null,
        api_request_limit: number|null,
        image_limit: number|null,
        image_max_size_kb: number|null,
        branch_limit: number|null,
        warehouse_limit: number|null,
        pos_device_limit: number|null,
    },
    features: [
        { key: string, enabled: boolean },
        // ...
    ],
}
```

### Feature Category Structure

```js
[
    {
        label: string,           // 'Product Features', 'Marketing', etc.
        features: [
            {
                key: string,     // 'single_products', 'coupons', etc.
                label: string,   // 'Standard Products', 'Coupons & Discounts', etc.
                upgrade_hint: string|null,  // 'Upgrade to Starter' etc.
            },
        ],
    },
]
```

---

## Dynamic Pricing Flow

```
User visits landing page
  → PublicLandingController@index
    → Plan::active()->ordered()->get()
    → Maps each plan to { name, slug, price, limits, features }
    → Passes to Public/Landing.jsx as `plans` prop
  → PricingSection renders:
    → Monthly/Yearly toggle (client-side state)
    → Pricing cards for each plan
    → "Current Plan" badge if logged in and on that plan
    → CTA links: /register (free) or /create-store (paid)
```

**Yearly pricing logic** (in `PricingSection.jsx`):
- If `isYearly` AND `plan.yearly_price` exists → display yearly price
- Show effective monthly price: `yearly_price / 12`
- Yearly toggle is client-side only (no server round-trip)

---

## Feature Matrix Flow

```
Same controller pass as pricing
  → featureCategories + plans → FeatureComparisonMatrix
  → Renders full comparison table:
    Header row: plan names
    Limits section: numeric limits per plan
    Feature sections: grouped boolean features
    Icons: Check/Cross/Infinity/HelpCircle based on status
```

**Icon states:**
| Condition | Icon | Meaning |
|-----------|------|---------|
| `enabled === true` | ✅ Green check | Feature available |
| `enabled === false` | ❌ Gray cross | Not available |
| `isComingSoon(key)` | ❓ Gray help | Coming soon |
| `limit === null` | ♾️ Blue infinity | Unlimited |

---

## Branding Flow

```
PlatformSetting model (database)
  → Cache::rememberForever('platform_settings')
  → HandleInertiaRequests shares as `platform_setting`
  → PlatformNavbar / PlatformFooter / Landing page read:
    • platform_setting.site_name    → site name
    • platform_setting.site_logo    → logo URL
    • platform_setting.support_email → footer email
  → No branding hardcoded anywhere
```

---

## Future Extension Guide

### Adding a New Landing Page Section

1. Create component in `resources/js/Components/PublicLanding/`
2. Add data to `PublicLandingController@index` if needed
3. Import and render in `resources/js/Pages/Public/Landing.jsx`

### Adding a New Plan

Plans are automatically loaded from the database. If a new plan is created via superadmin:

1. Create the plan in `plans` table (via superadmin UI or seeder)
2. Add a `highlightFeatures` entry in `PricingSection.jsx` for the plan slug
3. The plan will appear in pricing cards and feature matrix automatically

### Adding a New Feature Category

1. Add the category to `$featureCategories` array in `PublicLandingController@index` and `AdminBillingController@index`
2. Add feature keys to the category's `keys` array
3. Ensure feature keys exist in `FeatureGate::FEATURE_LABELS`
4. The category appears in both landing page matrix and billing matrix

### Converting FAQ to Database-Driven

The FAQ section currently uses static content. To make it dynamic:

1. Create a `Faq` model with `question`, `answer`, `sort_order`, `is_active` columns
2. Add `Faq::active()->ordered()->get()` in controller
3. Pass `faqs` prop to `Public/Landing.jsx`
4. Replace static array in `FaqSection.jsx` with props-based rendering

### Making Hero Content Editable

1. Add `hero_headline`, `hero_subtitle`, `hero_cta_text` columns to `PlatformSetting`
2. Add fields to superadmin platform settings form
3. Pass to `Public/Landing.jsx` via controller or shared props
4. Replace hardcoded text in `HeroSection.jsx`

---

## Key Files

| File | Role |
|------|------|
| `app/Http/Controllers/PublicLandingController.php` | Serves all landing page data |
| `routes/web.php` (line 43) | Route definition |
| `resources/js/Pages/Public/Landing.jsx` | Main page component |
| `resources/js/Components/PublicLanding/*.jsx` | Section components (7 files) |
| `resources/js/Components/PlatformNavbar.jsx` | Navigation (updated links) |
| `resources/js/Components/PlatformFooter.jsx` | Footer (updated links) |
| `resources/js/Layouts/PlatformLayout.jsx` | Layout wrapper |
| `resources/js/Utils/currency.js` | Currency symbol utility |

---

## Tests

| Test | Command |
|------|---------|
| Related feature tests | `php artisan test --testsuite=Feature --filter="SubscriptionLimitTest\|AdminBillingPageTest\|TrialLifecycleTest"` |
| Full suite | `php artisan test` |
| Frontend build | `npx vite build` |
