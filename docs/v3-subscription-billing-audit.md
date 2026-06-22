# V3-B3: Subscription & Billing Audit Report

**Date:** 2026-06-21
**Scope:** Read-only audit of plans, subscriptions, billing, feature gating, payment flow, upgrade/downgrade, expiry, and SuperAdmin tools.

---

## Executive Summary

The subscription architecture is well-designed for its current stage: plans are defined with resource limits and feature toggles, subscriptions track full lifecycle statuses, expiry processing is automated, and both SuperAdmin and merchant-facing billing UIs exist. However, **billing/payment integration is completely absent** — no payment gateway, no invoice generation, no automatic renewal billing. The application has a `DEV_MODE` flag in `FeatureGate` that bypasses all plan restrictions. The system is **pre-billing** — ready for integration but not yet live.

**Overall Risk Level: Medium-High** (missing payment integration is the single critical gap)

---

## Part 1: Plan Audit

### Plan Model (`Plan.php`)

| Property | Details |
|---|---|
| Columns | `name`, `slug`, `description`, `monthly_price`, `yearly_price`, `product_limit`, `staff_limit`, `storage_limit`, `analytics_enabled`, `custom_domain_enabled`, `status` (active/inactive/deprecated) |
| Legacy compat | `price`, `currency`, `interval`, `is_default`, `is_active`, `sort_order` — kept for backward compatibility |
| Null = unlimited | `product_limit = null` means unlimited products (not cast to int) |
| Scopes | `active()`, `inactive()`, `deprecated()`, `default()` (slug=free), `ordered()` (by price) |

### Seeded Plans

| Plan | Monthly Price | Yearly Price | Product Limit | Staff Limit | Storage | Features |
|---|---|---|---|---|---|---|
| **Free** | $0 | $0 | null (unlimited) | null (unlimited) | null (unlimited) | `single_products` only |
| **Starter** | $9.99 | — | null (unlimited) | null (unlimited) | null (unlimited) | `single_products`, `variable_products` |
| **Business** | $29.99 | — | null (unlimited) | null (unlimited) | null (unlimited) | `single_products`, `variable_products`, `combo_products` |

**Finding:** All three plans have `product_limit = null`, `staff_limit = null`, and `storage_limit = null`. Despite the SaaS migration adding these limit columns, **no limits are actually enforced** — every plan has unlimited resources. The limits infrastructure exists and works (SubscriptionLimitService, assertions), but no plan defines any caps.

### Plan Features (`PlanFeature`)

| Feature Key | Free | Starter | Business |
|---|---|---|---|
| `single_products` | ✅ | ✅ | ✅ |
| `variable_products` | ❌ | ✅ | ✅ |
| `combo_products` | ❌ | ❌ | ✅ |

Feature gating works via `Plan->hasFeature()` and `FeatureGate`. However, `FeatureGate::DEV_MODE = true` bypasses all checks.

### Default Plan

`Plan::free()` returns the plan with `slug = 'free'` and `status = 'active'`. `Plan::defaultPlan()` aliases `free()`. This is used in `User::getActivePlan()` as fallback when no subscription exists.

### Plan Assignment

Plans are assigned to tenants via:
1. **Tenant creation** (`CreateStoreController`, `SuperAdmin\TenantController.store`) — `TenantBootstrapService` creates a subscription with `Plan::free()` by default, or a specified plan.
2. **SuperAdmin subscription management** (`SubscriptionController.assign`, `SubscriptionController.changePlan`) — explicit plan assignment or plan change.
3. **Tenant `settings` JSON** — `plan_id` is stored in `tenants.settings` JSON column for quick reference.

### Finding: No yearly pricing seeded

The `PlanSeeder` only sets `price` (monthly), not `monthly_price` and `yearly_price` columns. The migration `restructure_plans_for_saas` added these columns, but the seeder was never updated to populate them with yearly pricing. This means `yearly_price = null` for all seeded plans. The `Plan` model's `getPrice('yearly')` returns `null`.

---

## Part 2: Subscription Audit

### Subscription Model (`Subscription.php`)

| Property | Details |
|---|---|
| Columns | `tenant_id`, `plan_id`, `billing_interval` (monthly/yearly), `status`, `starts_at`, `expires_at`, `trial_ends_at`, `cancelled_at`, `suspended_at`, `notes` |
| Statuses | `trialing` → `active` → `past_due` → `expired` → `suspended` |
| Lifecycle transitions | `markAsPastDue()`, `markAsExpired()`, `suspend()`, `activate()`, `markAsCanceled()`, `renew()`, `renewFromInterval()`, `cancelImmediately()` |
| Scopes | `inGoodStanding()` (trialing/active), `expiringSoon($days)`, `overdue()`, `needsProcessing()` |
| Constants | `GRACE_DAYS = 7` |

### Subscription Creation

| Trigger | Method | Notes |
|---|---|---|
| Tenant bootstrap | `TenantBootstrapService::createSubscription()` | Sets `starts_at = now()`, `expires_at = plan->calculateExpiryDate()` or null for free plan |
| SuperAdmin assign | `SubscriptionController::assign()` | Validates no existing active subscription, sets from form |
| Initial registration | `Auth\RegisteredUserController` → `CreateStoreController` | Bootstrap flow via `TenantBootstrapService` |

### Subscription Update / Change Plan

`SubscriptionController::changePlan()`:
- Validates plan exists
- Checks for downgrade warnings (product/staff limit exceedances)
- Preserves existing `expires_at` if future
- Adds note with plan change history
- Does NOT prorate billing or generate invoices

### Subscription Expiration

**Two automated commands:**
1. `subscriptions:process-expired` — runs every 5 minutes.
   - Active → Past Due (grace period starts, notification sent)
   - Past Due → Expired (grace ended at 7 days)
   - Expired → Suspended (1 day after expiry, tenant deactivated)
   - Trial → Expired (trial ended)
2. `subscriptions:send-expiry-warnings` — runs daily at 08:00.
   - Sends warnings at 7, 3, and 1 day before expiry
   - Uses `SubscriptionExpiringSoon` notification

### Subscription Cancellation

`SubscriptionController::cancel()`:
- If future `expires_at` exists → status becomes `canceled`, access continues until expiry
- If no future expiry → access ends immediately
- Does NOT process refunds (no payment integration)

### Finding: Cancellation does not remove access for canceled-yet-future-expiry

When a subscription is canceled with a future `expires_at`, the merchant retains full access until that date. This is the intended design, but `EnsureTenantIsActive` and `SubscriptionIsActive` middleware correctly allow this (line 53: `$subscription->isCanceled() && $subscription->expires_at && $subscription->expires_at->isFuture()`). This is correct.

---

## Part 3: Tenant Relationship

### Tenant ↔ Subscription

```
Tenant (1) ──→ Subscription (many)
                ↓
              Plan (1)
```

| Relationship | Method | Purpose |
|---|---|---|
| Latest active | `Tenant->subscription()` | `hasOne` with `latestOfMany()` — used for billing UI, middleware checks |
| Active or trialing | `Tenant->activeSubscription()` | Scope-filtered `latestOfMany` for good-standing checks |
| All historical | `Tenant->subscriptions()` | `hasMany` — used for subscription history on SuperAdmin UI |
| Legacy FK | `Tenant->subscriptionPlan()` | `belongsTo(Plan)` via deprecated `subscription_plan_id` |

### Tenant ↔ Plan

| Path | Deprecated? | Used? |
|---|---|---|
| `Tenant->subscription->plan` | No | Primary path via Subscription model |
| `Tenant->subscriptionPlan` (FK) | Yes | Column exists but not actively used |
| `Tenant->settings['plan_id']` | Partial | JSON column used by SuperAdmin TenantController for plan selection |

### Finding: Legacy `subscription_plan_id` on `tenants` table

The `tenants` table has a `subscription_plan_id` FK column and `expires_at` column from migration `2026_05_28_000001`. These are deprecated by the `subscriptions` table (migration `2026_05_28_000004`). The backfill migration copies data from legacy columns to the new subscriptions table. But the legacy columns are still present and could cause data inconsistency if not kept in sync.

### Finding: `hasActiveSubscription()` vs `isInGoodStanding()`

- `Tenant->hasActiveSubscription()` checks `activeSubscription()->exists()` (trialing or active)
- `Subscription->isInGoodStanding()` checks `in_array($this->status, ['trialing', 'active'])`
- These are equivalent but accessed differently — consistent, no data mismatch risk.

---

## Part 4: Feature Gating

### FeatureGate Service

| Method | Purpose |
|---|---|
| `enabled($featureKey)` | Check if feature is enabled for current user (static) |
| `forUser($user)->isEnabled($key)` | Check feature for specific user |
| `forPlan($plan)->isEnabled($key)` | Check feature for specific plan |
| `require($featureKey)` | Throws exception if disabled |
| `getUpgradeHint($featureKey)` | Get required plan slug for locked feature |
| `typeEnabled($type)` | Check product type via feature mapping |

### DEV_MODE Bypass

```php
protected const DEV_MODE = true;
```

The `TODO` comment states: *"Re-enable subscription restrictions after SaaS billing implementation."* When `DEV_MODE = true`:
- `isEnabled()` returns `true` for ALL feature keys
- `require()` never throws
- `getEnabledFeatures()` returns all features
- `getAllFeaturesStatus()` returns everything as enabled

**Severity: Critical** — no feature restrictions are actually enforced in production.

### SubscriptionLimitService

| Method | Purpose | Enforced? |
|---|---|---|
| `canCreateProduct()` | Check product limit | **Yes** — called in `AdminProductController::store()` |
| `canCreateStaff()` | Check staff limit | **Yes** — called in `AdminUserController::store()` |
| `canUpload($bytes)` | Check storage limit | **Yes** — called in `ImageService` |

Limit enforcement works independently of `FeatureGate`. However, since no plan defines actual limits (all `null` = unlimited), these checks always pass.

### Upgrade Modal

`ProductType/UpgradeModal.jsx` is a frontend component that shows plan comparison when a user tries to use a locked feature. Since `DEV_MODE = true`, this modal is never triggered.

### Finding: FeatureGate cached per plan — not per user

The cache key is `feature_{$plan->id}_{$featureKey}`. If two tenants share the same plan, feature checks are shared via cache. This is correct — features are plan-level, not tenant-level.

---

## Part 5: Payment Flow

### Current Payment Architecture

**No payment gateway integration exists.** The system handles:

| Concern | Implemented? | Details |
|---|---|---|
| Plan pricing defined | ✅ | `monthly_price`, `yearly_price` on Plan model |
| Subscription lifecycle | ✅ | Create, renew, cancel, suspend, expire |
| Merchant self-service renew | ✅ | `AdminBillingController::renew()` with `billing.renew` permission |
| Payment gateway | ❌ | No Stripe, PayPal, or any payment integration |
| Invoice generation | ❌ | No invoices or receipts |
| Automatic billing | ❌ | No recurring charge on renewal |
| Payment webhooks | ❌ | No webhook handlers for payment confirmation |
| Trial period handling | ✅ | `trial_ends_at`, `trialing` status, trial→expired transition |
| Grace period | ✅ | 7-day grace (active→past_due→expired) |
| Suspension | ✅ | Tenant deactivated after grace |

### Future Billing Readiness

The architecture is **ready for payment integration**:

| What Exists | What's Missing |
|---|---|
| All lifecycle methods (`renew`, `renewFromInterval`, `cancel`) | Payment processor calls before status transitions |
| `billedPrice()` helper on Subscription | Actual charge execution |
| Pricing on Plan model ($0, $9.99, $29.99) | Subscription creation with payment method |
| Merchant billing page (Billing/Index.jsx) | Payment method management UI |
| SuperAdmin subscription management | Revenue reporting, invoice viewing |

### Finding: `renew()` and `renewFromInterval()` have no payment check

The merchant-facing `AdminBillingController::renew()` and SuperAdmin `SubscriptionController::renew()` / `renewFromInterval()` do not charge any payment. Renewal is a simple status/date update with no financial transaction. This is by design for the pre-billing phase — but it means **all renewals are free**.

---

## Part 6: Upgrade/Downgrade Flow

### Upgrade Flow (Free → Starter → Business)

| Step | Implemented? | Notes |
|---|---|---|
| SuperAdmin triggers change | ✅ | `SubscriptionController::changePlan()` |
| Plan comparison UI | ✅ | `UpgradeModal.jsx` shows plan tiers |
| Feature unlock | ✅ | `FeatureGate` would check new plan's features |
| Downgrade warnings | ✅ | `checkDowngradeWarnings()` warns if limits exceeded |
| Price proration | ❌ | No invoice generation for partial periods |
| Automatic charge | ❌ | No payment taken on upgrade |
| Tenant notification | ✅ | Notification sent via renew flow |

### Downgrade Flow (Business → Starter → Free)

| Step | Implemented? | Notes |
|---|---|---|
| SuperAdmin triggers change | ✅ | Same `changePlan()` method |
| Limit exceedance warnings | ✅ | Shows excess product/staff counts |
| Force removal of excess items | ❌ | **Nothing** prevents or auto-handles excess products/staff |
| Feature lock | ✅ | `FeatureGate` would gate features (if DEV_MODE = false) |
| Price proration | ❌ | No credit for remaining time |

### Finding: No enforcement of plan limits on downgrade

When downgrading from Business → Free, if the tenant has 50 products and 10 staff, the system shows warnings but does NOT:
- Block the downgrade
- Require the tenant to delete excess items
- Auto-disable features that are no longer available
- Prevent continued use of excess resources

The `SubscriptionLimitService` checks are at the **point of creation** only (when adding a new product or staff member). Existing resources are never audited or capped.

---

## Part 7: Expired Subscription Flow

### Lifecycle Timeline

```
Day 0: expires_at reached
       → Status: active → past_due
       → Grace period begins (7 days)
       → Notification: SubscriptionExpired

Day 1-6: past_due
       → Merchant can still access admin (middleware allows past_due)
       → Renew button available on billing page

Day 7: past_due → expired
       → 7-day grace period ends
       → Admin operations blocked (middleware redirects to dashboard)
       → Billing page still accessible (outside tenant.active middleware)
       → Dashboard still accessible (outside tenant.active middleware)

Day 8: expired → suspended
       → 1 day after expiry status
       → Tenant status set to 'suspended'
       → All admin access blocked
       → Notification: SubscriptionSuspended
       → Can only contact support to restore
```

### What's Blocked vs Allowed

| Resource | After Expiry | After Suspension |
|---|---|---|
| Dashboard | ✅ Accessible | ❌ Blocked |
| Billing page | ✅ Accessible | ❌ Blocked |
| Products/Orders | ❌ Blocked | ❌ Blocked |
| Settings | ❌ Blocked | ❌ Blocked |
| Renew button | ✅ Available | ❌ "Contact support" message |
| Frontend store | ✅ Still active | ✅ Still active |

### Finding: `suspended` subscription vs `suspended` tenant status

The `SubscriptionExpiryService` sets BOTH `subscription.status = 'suspended'` AND `tenant.status = 'suspended'`. These are two separate models with two separate `suspended` statuses. They are always set together, but there's no DB constraint ensuring they stay in sync. A SuperAdmin could manually suspend a tenant without suspending the subscription, or vice versa.

### Finding: Frontend store NOT blocked when subscription expires

The subscription system only blocks **admin operations**. The customer-facing storefront remains fully functional even when a tenant's subscription is expired or suspended. There is no middleware or check on frontend routes for subscription status. Products can still be browsed, added to cart, and checked out.

**Severity: Medium** — by design for now, but a tenant could indefinitely offer free services without paying.

---

## Part 8: SuperAdmin Billing Tools

### Plan Management (`PlanController`)

| Feature | Implemented? | Notes |
|---|---|---|
| List plans with search/filter | ✅ | Index page with status filter |
| Create plan | ✅ | Full form with limits, pricing, features |
| Edit plan | ✅ | Same form as create |
| Delete plan | ✅ | Blocks deletion if active subscriptions or is free plan |
| Plan feature management | ❌ | No UI for PlanFeature toggles — must edit seeder or DB directly |

### Subscription Management (`SubscriptionController`)

| Feature | Implemented? | Notes |
|---|---|---|
| List subscriptions with stats | ✅ | Active, past_due, expired counts + expiring soon |
| View subscription detail | ✅ | Plan, usage, history, users, downgrade warnings |
| Assign subscription | ✅ | To any tenant |
| Change plan | ✅ | With downgrade warnings |
| Renew (custom date) | ✅ | Manual date entry |
| Renew (by interval) | ✅ | Auto-calculated from billing cycle |
| Cancel | ✅ | With optional reason |
| Suspend / Activate | ✅ | Toggles subscription and tenant status |
| Revenue reporting | ❌ | No dashboard revenue from subscriptions |

### Finding: No Revenue Data

The SuperAdmin dashboard (`DashboardController`) shows revenue from `orders` table (product sales), but has **no subscription revenue reporting**. There is no way to see MRR (Monthly Recurring Revenue), churn rate, or subscription revenue breakdown.

---

## Part 9: Risk Analysis

### Critical Gaps

| # | Gap | Severity | Impact |
|---|---|---|---|
| 1 | **No payment gateway** — no Stripe/PayPal integration, no automatic billing, no invoice generation | **Critical** | System cannot charge for subscriptions |
| 2 | **FeatureGate DEV_MODE = true** — all feature restrictions bypassed | **Critical** | Free plan users get all features |
| 3 | **No plan limits defined** — product_limit, staff_limit, storage_limit are null for all plans | **High** | SubscriptionLimitService passes every check |

### Missing Billing Logic

| Feature | Status | Notes |
|---|---|---|
| Payment method collection | ❌ | No credit card/PayPal on registration |
| Recurring billing | ❌ | No cron job for monthly charges |
| Invoice/receipt generation | ❌ | No billing history for merchants |
| Proration on upgrade/downgrade | ❌ | Full price charged regardless of timing |
| Refund processing | ❌ | No cancellation refunds |
| Dunning (failed payment recovery) | ❌ | No retry logic for failed charges |

### Missing Renewal Logic

| Feature | Status | Notes |
|---|---|---|
| Auto-renewal charge | ❌ | `renewFromInterval()` just updates dates |
| Expiry warning emails | ✅ | Database notifications at 7/3/1 days |
| Final notice before suspension | ❌ | No "last day before suspension" warning |

### Missing Upgrade/Downgrade Logic

| Feature | Status | Notes |
|---|---|---|
| Upgrade charge (difference prorated) | ❌ | No charge on upgrade |
| Downgrade credit | ❌ | No credit for remaining time |
| Excess resource handling | ❌ | No forced cleanup on downgrade |

### Missing Expiration Handling

| Feature | Status | Notes |
|---|---|---|
| Frontend store block on expiry | ❌ | Store stays live |
| Data retention policy | ❌ | What happens to tenant data after prolonged suspension? |
| Re-activation data restore | ✅ | `activate()` recalculates remaining time |

---

## Part 10: V3 Roadmap Recommendations

### Immediate Fixes (Pre-Billing)

| Priority | Fix | Effort |
|---|---|---|
| 1 | Define real plan limits in seeder (e.g., Free: 10 products, 2 staff, 100MB) | Low |
| 2 | Update `PlanSeeder` to populate `monthly_price` and `yearly_price` columns | Low |
| 3 | Set `FeatureGate::DEV_MODE = false` once limits are defined | Low |
| 4 | Remove legacy `subscription_plan_id` and `expires_at` columns from `tenants` table | Medium |

### Future Billing Architecture

| Phase | Features | Effort |
|---|---|---|
| **Phase 1: Payment Gateway** | Stripe integration, payment method on registration, one-time charge | High |
| **Phase 2: Auto-Renewal** | Recurring charges, dunning emails, failed payment handling | High |
| **Phase 3: Billing UI** | Invoice history, payment method management, usage-based billing | Medium |
| **Phase 4: Revenue Ops** | MRR dashboard, churn analytics, subscription revenue reports | Medium |

### Recommended Integration Points

| Location | Hook | Purpose |
|---|---|---|
| `Subscription::renew()` | Before date update | Charge payment before extending |
| `Subscription::renewFromInterval()` | Before date update | Same — charge before extending |
| `SubscriptionController::changePlan()` | Before plan change | Charge upgrade / issue credit |
| `SubscriptionController::cancel()` | Before status change | Process refund if applicable |
| `TenantBootstrapService::bootstrap()` | After tenant creation | Collect initial payment |
| `AdminBillingController::renew()` | Before renewal | Merchant self-service payment |
