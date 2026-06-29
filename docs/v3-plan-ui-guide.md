# Plan Comparison UI & Upgrade Experience Guide

## Overview

The plan comparison UI provides merchants with a comprehensive view of their current subscription, available plans, feature comparisons, and upgrade paths. Built entirely from backend data — no hardcoded prices, limits, or features.

---

## Component Hierarchy

```
Pages/Admin/Billing/Index.jsx
├── CurrentPlanCard
│   └── UsageProgressBar (x6)
├── PlanCards
├── PlanFeatureMatrix
└── UpgradeDialog (modal)
```

---

## Data Flow

```
Backend (AdminBillingController)
  ├── subscription   → Current subscription details + plan info
  ├── usage          → getAllLimits() from SubscriptionLimitService
  ├── plans          → All active plans with features & limits
  ├── featureCategories → Grouped feature definitions
  ├── allFeatureDefs → getAllFeatureDefinitions() from FeatureGate
  └── auditLogs      → Subscription audit history

Frontend (Inertia props)
  └── Components render from props, no additional API calls
```

### Controller Data Shape

```php
'subscription' => [
    'status', 'plan' => ['id', 'name', 'slug', 'description',
        'monthly_price', 'yearly_price', 'yearly_savings_percent',
        'limits' => ['product_limit', 'staff_limit', ...]],
    'billing_interval', 'price', 'starts_at', 'expires_at',
    'trial_ends_at', 'trial_days_remaining', 'on_trial',
    'days_until_expiry', 'days_since_expiry',
]
'usage' => SubscriptionLimitService::for()->getAllLimits()
'plans' => [
    ['id', 'name', 'slug', 'description', 'monthly_price',
     'yearly_price', 'is_current', 'yearly_savings_percent',
     'limits' => [...],
     'features' => [['key', 'enabled'], ...]]
]
'featureCategories' => [
    ['label' => 'Product Features', 'features' => [
        ['key' => 'single_products', 'label' => 'Standard Products', ...]
    ]]
]
'allFeatureDefs' => FeatureGate::getAllFeatureDefinitions()
```

---

## Component Guide

### CurrentPlanCard
- **Input**: `subscription`, `usage` (from `getAllLimits()`)
- **Displays**: Plan name, status badge, price, trial info, 6 usage progress bars
- **Status badges**: Active (green), Trialing (blue), Past Due (amber), Expired (red), Canceled (gray), Suspended (yellow)
- **Trial**: Blue info box with days remaining and end date
- **Expired states**: Red warning box with days since expiry
- **Billing summary**: Billing cycle, start date, expiry date, trial end date

### UsageProgressBar
- **Input**: `label`, `current`, `limit`, `isUnlimited`, `format`
- **Color coding**: <70% blue, 70-89% amber, 90%+ red
- **Unlimited**: Gradient bar showing unlimited
- **ARIA**: `role="progressbar"` with `aria-valuenow/min/max`

### PlanCards
- **Input**: `plans`, `onUpgrade` callback
- Three-column responsive grid of plan pricing cards
- Current plan: Blue border with "Current Plan" badge
- Non-current: Shows yearly savings badge if applicable
- Business plan: Sparkle icon for "Best value"
- Feature highlights are hardcoded per plan (static, not from DB)

### PlanFeatureMatrix
- **Input**: `plans`, `featureCategories`, `allFeatureDefs`, `onLockedFeatureClick`
- Sections: Limits (numeric rows), then feature categories
- Icons: Check (green) = enabled, X (gray) = locked, Infinity (blue) = unlimited, Question mark (gray) = coming soon
- Locked feature cells are clickable → triggers `onLockedFeatureClick`
- "Coming Soon" badge for future features (gift_cards, loyalty_points, referral_system)

### UpgradeDialog
- **Input**: `isOpen`, `onClose`, `currentPlan`, `targetPlan`, `featureKey`, `allFeatureDefs`
- **Keyboard**: Escape to close, Tab trapping, auto-focus on open
- Shows current vs target plan comparison
- Lists features gained by upgrading (up to 5)
- Shows monthly price
- **Upgrade button**: Navigates to billing page (placeholder for future upgrade endpoint)
- **ARIA**: `role="dialog"`, `aria-modal="true"`, `aria-label`

---

## Upgrade Flow

1. Merchant clicks a locked feature in the feature matrix
2. `UpgradeDialog` opens with:
   - Feature name that triggered the dialog
   - Current plan vs target plan display
   - Gained features list
   - Monthly price comparison
   - "Upgrade Now" CTA
3. CTA navigates to billing page (future: direct to upgrade endpoint)

---

## Feature Matrix — Feature Categories

| Category | Features |
|---|---|
| Product Features | single_products, variable_products, combo_products, digital_products |
| Analytics | reports |
| Store Features | custom_domain, advanced_seo, theme_editor, custom_css, maintenance_mode |
| Customer Features | reviews, wishlist, compare |
| Marketing | coupons, promotions, flash_sales |
| Integrations | telegram_integration, whatsapp_integration, social_media_integration, google_analytics, meta_pixel, mailchimp_integration |
| AI | ai_product_generator, ai_description, ai_seo, ai_translation |
| Payment Gateways | payment_gateways_cod, payment_gateways_kbzpay, payment_gateways_wavepay, payment_gateways_stripe, payment_gateways_paypal, payment_gateways_manual |

---

## Plan Defaults (from PlanSeeder)

| Limit | Free | Starter | Business |
|---|---|---|---|
| Products | 10 | 100 | Unlimited |
| Staff | 2 | 5 | Unlimited |
| Storage | 100 MB | 1 GB | Unlimited |
| Monthly Orders | 50 | 500 | Unlimited |
| Coupons | 5 | 20 | Unlimited |
| Promotions | 3 | 10 | Unlimited |
| Flash Sales | 1 | 5 | Unlimited |
| API Requests | 1,000 | 10,000 | Unlimited |
| Images/Product | 5 | 10 | Unlimited |
| Max Image Size | 2 MB | 5 MB | 10 MB |
| Branches | 1 | 3 | Unlimited |
| Warehouses | 1 | 2 | Unlimited |
| POS Devices | 1 | 3 | Unlimited |

---

## Responsive Behavior

| Breakpoint | Layout |
|---|---|
| Desktop (≥1024px) | Full width, 3-column plan cards, full feature table |
| Tablet (768-1023px) | 2-column plan cards, scrollable feature table |
| Mobile (<768px) | Stacked plan cards, horizontal scroll feature table, compact usage bars |

---

## Accessibility

- All interactive elements have `aria-label`
- Feature matrix cells use `role="table"`, `scope="col"`, `scope="row"`
- UpgradeDialog has focus trapping, Escape key close, `aria-modal`
- UsageProgressBar has `role="progressbar"` with `aria-valuenow/min/max`
- Color is not the only indicator (icons + text labels)
- Sufficient color contrast ratios used throughout
