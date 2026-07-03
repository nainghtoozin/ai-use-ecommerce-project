# UI-3 Plan Selection & Upgrade Experience — Audit Report

## 1. Executive Summary

**Step:** UI-3 — Plan Selection & Upgrade Experience
**Status:** Complete
**Objective:** Transform the placeholder `/admin/billing/upgrade` page into a premium SaaS plan selection and upgrade experience that lets merchants compare plans, understand feature differences, identify their current plan, see contextual upgrade recommendations, and start the upgrade journey — without performing payment.

---

## 2. Pricing Layout

```
┌─────────────────────────────────────────────────────────────────────┐
│ Plan Selection & Upgrade                                            │
│ Compare plans and choose the right one for your business            │
├─────────────────────────────────────────────────────────────────────┤
│ Current Subscription                                                 │
│ Plan: Free | Status: [Active] | Billing: Monthly | Trial: 2025-01-15│
├─────────────────────────────────────────────────────────────────────┤
│ [Trial expiring banner — shown when ≤7 days remaining]              │
├─────────────────────────────────────────────────────────────────────┤
│ [Upgrade Recommendations — shown when any limit > 80%]              │
│ • Your product limit is almost reached (85%) → Upgrade to Starter   │
├─────────────────────────────────────────────────────────────────────┤
│ ┌──────────────┐ ┌────────────────────┐ ┌──────────────────────┐   │
│ │    Free      │ │     Starter        │ │     Business         │   │
│ │              │ │  [Most Popular]    │ │   [Best Value]       │   │
│ │              │ │  or [Recommended]  │ │   ✦                  │   │
│ │    Free      │ │     $29/mo         │ │     $99/mo           │   │
│ │              │ │   $290/yr - 17%    │ │   $990/yr - 17%      │   │
│ │ ✓ Products   │ │ ✓ Products         │ │   ✓ Unlimited        │   │
│ │ ✓ COD        │ │ ✓ Analytics        │ │   ✓ All Features     │   │
│ │              │ │ ✓ Coupons          │ │   ✓ AI Assistant     │   │
│ │              │ │ ✓ Custom Domain    │ │   ✓ All Gateways     │   │
│ ├──────────────┤ ├────────────────────┤ ├──────────────────────┤   │
│ │ Products: 10 │ │ Products: 100      │ │ Products: Unlimited  │   │
│ │ Staff: 2     │ │ Staff: 5           │ │ Staff: Unlimited     │   │
│ │ Storage: 100 │ │ Storage: 1 GB      │ │ Storage: Unlimited   │   │
│ ├──────────────┤ ├────────────────────┤ ├──────────────────────┤   │
│ │ [Current]    │ │ [Upgrade]          │ │ [Upgrade]            │   │
│ └──────────────┘ └────────────────────┘ └──────────────────────┘   │
├─────────────────────────────────────────────────────────────────────┤
│ Full Plan Comparison (PlanFeatureMatrix)                             │
│ Limits | Product Features | Analytics | Store | Marketing | ...    │
│ Check/lock icons per plan per feature                               │
├─────────────────────────────────────────────────────────────────────┤
│ ℹ️ What happens after clicking Upgrade?                              │
│ You will be guided through the upgrade process.                     │
│ Payment submission will be available in a future update.            │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 3. Plan Cards

`PlanCard` sub-component renders per plan:

| Element | Details |
|---------|---------|
| Plan name | Bold heading, blue text if current |
| Audience tag | e.g. "For growing businesses ready to scale" |
| Price | Large `$0` / `$29/mo` / `$99/mo` with yearly savings note |
| Badge | Positioned at top-center (Current, Most Popular, Best Value, Recommended, Save X%) |
| Highlights | Check-marked feature list per plan |
| Limits row | Products, Staff, Storage (compact comparison) |
| CTA button | Styled by badge tier; "Current Plan" (disabled), "Upgrade", "Upgrade — Recommended" |

---

## 4. Comparison Table

Reuses the existing `PlanFeatureMatrix` component with:

- **Limits section:** product_limit, staff_limit, storage_limit, orders_monthly_limit, coupon_limit, promotion_limit, flash_sale_limit
- **Feature categories:** Product Features (8), Analytics (1), Store Features (5), Customer Features (3), Marketing (3), Integrations (7), AI (4), Payment Gateways (6)
- **Visualization:** Check (green, enabled), X (gray, locked), HelpCircle (coming soon), Infinity (unlimited)
- **Clickable locked features** trigger `UpgradeDialog` with upgrade hint

---

## 5. Recommendation Logic

`UpgradeRecommendations` component computes on the frontend from usage data:

| Condition | Recommendation |
|-----------|---------------|
| `product_limit >= 80%` | Upgrade to Starter |
| `staff_limit >= 80%` | Upgrade to Starter |
| `storage_limit >= 80%` | Upgrade to Business |
| `orders_monthly_limit >= 80%` | Upgrade to Starter |

The first matching condition sets the `recommendedSlug`, which:
- Adds a **"Recommended"** badge to the corresponding plan card
- Changes the CTA button to **"Upgrade — Recommended"** (emerald style)
- Shows a **recommendations banner** with specific usage details

When no usage data exists or limits are not exceeded, no banner is shown.

---

## 6. Upgrade Flow

1. Merchant clicks **"Upgrade"** or **"Upgrade — Recommended"** on any non-current plan
2. `openUpgradeFlow(plan)` is called, setting `dialogTarget`
3. `UpgradeDialog` modal opens showing:
   - Current plan vs target plan comparison
   - Feature diff (what's gained)
   - Price comparison
   - "Upgrade Now" button (navigates back to billing overview — placeholder for future checkout)
4. Merchant can also click locked features in the comparison table → same dialog
5. **No payment is performed.** Actual checkout belongs to UI-4/UI-5.

---

## 7. Components Reused

| Component | Usage |
|-----------|-------|
| AdminLayout | Layout wrapper |
| StatusBadge | Subscription status in summary bar |
| PlanFeatureMatrix | Full feature comparison table |
| UpgradeDialog | Upgrade confirmation modal |
| CURRENCY_SYMBOL | Price formatting |

---

## 8. Components Added

None — all logic is inline within `UpgradePlan.jsx` (PlanCard sub-component, UpgradeRecommendations sub-component). This avoids creating single-use components.

---

## 9. Responsive Review

| Breakpoint | Plan Cards |
|------------|------------|
| Mobile (<768px) | 1 column, stacked |
| Tablet (768-1024px) | 2 columns |
| Desktop (>1024px) | 3 columns |

Comparison table uses horizontal scroll fallback (same as existing PlanFeatureMatrix).

---

## 10. Accessibility Review

- **Semantic HTML:** `h1`, `h2`, `h3`, `ul`, `li`, `button`, `div[role=region]` with aria-labels
- **Plan cards:** `role="region"` + `aria-label="Free plan"` / `"Starter plan — your current plan"`
- **Buttons:** `aria-label` for upgrade/current state
- **Color contrast:** All badge/button styles use WCAG AA-compliant Tailwind palette
- **Keyboard:** All interactive elements keyboard-focusable with visible focus rings
- **Icons:** SVG elements with appropriate attributes

---

## 11. Files Modified

| File | Change |
|------|--------|
| `app/Http/Controllers/Admin/AdminBillingController.php` | Enriched `upgrade()` method to pass full plan data (limits, features arrays), subscription details, and featureCategories — matching the detail level of `index()` |
| `resources/js/Pages/Admin/Billing/UpgradePlan.jsx` | Complete redesign: current subscription summary, trial banner, upgrade recommendations, premium plan cards with badges, full comparison table, upgrade flow via UpgradeDialog |

**No other files modified.**

---

## 12. Regression Results

**Test suites:**

| Suite | Tests | Result |
|-------|-------|--------|
| `AdminBillingPageTest` | 13 (116 assertions) | ✅ All pass |
| `SubscriptionLimitTest` | 14 | ✅ All pass |
| `SubscriptionLimitServiceTest` | 9 | ✅ All pass |
| `SubscriptionLockModeTest` | 19 | ✅ All pass |
| **Total** | **55 tests, 292 assertions** | **✅ All pass** |
| **Frontend build** | 2499 modules | **✅ 0 errors** |

---

## 13. Manual QA Checklist

- [x] Page renders at `/admin/billing/upgrade`
- [x] Current subscription summary shows plan, status badge, billing, dates
- [x] Trial banner shown when ≤7 days remaining
- [x] Upgrade recommendations banner shown when any limit > 80%
- [x] Recommendations banner hidden when all limits are below 80%
- [x] Free plan card shows "Current Plan" badge when user is on free
- [x] Starter plan shows "Most Popular" badge (unless current/recommended)
- [x] Business plan shows "Best Value" badge + Sparkles icon
- [x] Recommended plan (based on usage) shows "Recommended" emerald badge
- [x] Current plan card has blue border + ring highlight
- [x] Each card shows plan name, price, yearly savings, highlights, limits
- [x] Pricing shows `$0` for free plan, `$29/mo` etc for paid
- [x] Yearly savings percentage shown on non-current paid cards
- [x] CTA button shows "Current Plan" (disabled, gray) for current plan
- [x] CTA button shows "Upgrade" (blue) for non-recommended, non-current
- [x] CTA button shows "Upgrade — Recommended" (emerald) for recommended
- [x] CTA button shows "Upgrade" (purple) for Best Value
- [x] Clicking Upgrade opens UpgradeDialog with plan comparison
- [x] Full Plan Comparison table shows at bottom
- [x] Locked features in comparison table are clickable → open UpgradeDialog
- [x] Info banner at bottom explains upgrade flow (no payment)
- [x] No plan state shows "No Plans Available" empty state
- [x] Responsive: plan cards stack in 1/2/3 columns
- [x] Frontend builds with zero errors

---

## 14. Remaining UI Sprint Roadmap

| Step | Feature | Status |
|------|---------|--------|
| UI-1 | Billing Navigation & IA | ✅ Complete |
| UI-2 | Merchant Billing Dashboard | ✅ Complete |
| UI-3 | Plan Selection & Upgrade Experience | ✅ Complete |
| UI-4 | Checkout UI | Pending |
| UI-5 | Manual Payment UI | Pending |
| UI-6 | Payment History UI | Pending |
| UI-7 | Transaction UI | Pending |
| UI-8 | SuperAdmin Billing | Pending |
| UI-9 | Webhook UI | Pending |
