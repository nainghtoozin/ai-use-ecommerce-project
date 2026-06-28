# V3-B3-1: Subscription & Billing Architecture Audit

**Date:** 2026-06-28
**Scope:** Read-only audit of all Plan, Subscription, FeatureGate, LimitService, middleware, controllers, routes, policies, seeders, and React components. Supersedes `v3-subscription-billing-audit.md`.

---

## Executive Summary

The subscription architecture has a **solid structural foundation** but is not production-ready. Feature restrictions are completely bypassed (`DEV_MODE=true`), billing/payment integration is absent, and plan limits are seeded as `null` (unlimited). The system is functionally in a **pre-revenue state** — ready for Stripe/PayPal integration but entirely missing the payment layer.

**Overall Production Readiness: 3/10** — structural skeleton exists; revenue-critical paths are all stubs.

---

## Architecture Scores

| Subsystem | Score | Rationale |
|-----------|-------|-----------|
| Plan Architecture | 7/10 | Clean model, relationships, pricing helpers, null-as-unlimited pattern. Deprecated compat columns add noise. No plan versioning/grandfathering. |
| Subscription Lifecycle | 7/10 | Full state machine (trialing→active→past_due→expired→suspended), grace periods, automated expiry processing. Missing auto-renewal, payment retry, dunning, trial-enforcement on create. |
| Feature Gates | 3/10 | `FeatureGate::DEV_MODE=true` bypasses ALL checks. Only 3 feature keys defined. `PlanFeature` table is relational with no numeric value column — can only express enabled/disabled. |
| Billing Readiness | 1/10 | No payment gateway (Stripe/PayPal/Paddle). No invoice system. No tax support. No webhooks. `renew()` and `renewFromInterval()` update dates only — no charge. |
| Scalability | 6/10 | TenantScope isolation, caching on feature checks, bulk cron processing. No rate limiting on billing API. No queue for webhook processing. |
| Security | 6/10 | SuperAdmin-controlled plan/subscription CRUD. Middleware correctly gates expired/suspended tenants. No subscription change audit trail (notes field is basic). |

---

## 1. Plan Architecture (`app/Models/Plan.php`)

### Current State

| Property | Details |
|----------|---------|
| Columns | `name`, `slug`, `description`, `monthly_price`, `yearly_price`, `product_limit`, `staff_limit`, `storage_limit`, `analytics_enabled`, `custom_domain_enabled`, `status` (active/inactive/deprecated) |
| Deprecated compat | `price`, `currency`, `interval`, `is_default`, `is_active`, `sort_order` — columns exist but are not actively used in new code |
| Null = unlimited | `product_limit=null` → unlimited; integer cast is intentionally avoided (line 35-36) |
| Features | `hasMany(PlanFeature)` — boolean `is_enabled` per feature key |
| Pricing helpers | `getPrice($interval)`, `calculateExpiryDate()`, `defaultInterval()`, `yearlySavingsPercent()`, `hasPaid()`, `isFree()` |

### Seeded Plans (`database/seeders/PlanSeeder.php`)

| Plan | monthly_price | yearly_price | product_limit | staff_limit | storage_limit | Features |
|------|:---:|:---:|:---:|:---:|:---:|---|
| **Free** | $0 | $0 | null | null | null | `single_products` |
| **Starter** | $9.99 | $99.99 | null | null | null | `single_products`, `variable_products` |
| **Business** | $29.99 | $299.99 | null | null | null | `single_products`, `variable_products`, `combo_products` |

### Critical Finding #1: No plan limits are defined

`product_limit`, `staff_limit`, and `storage_limit` are `null` for ALL three seeded plans. The `SubscriptionLimitService` infrastructure exists and works correctly, but because every plan returns `null` (interpreted as unlimited), every check passes. This means:
- A Free-plan tenant can create unlimited products and staff
- `assertCanCreateProduct()` never throws
- `assertCanCreateStaff()` never throws
- `assertCanUpload()` never throws

### Finding: `hasPaid()` uses `monthly_price == 0` — Free plan is paid-adjacent

`Plan::isFree()` (line 149-152) checks `slug === 'free' || $this->monthly_price === null || $this->monthly_price == 0`. This means a plan with `monthly_price = 0` but `slug !== 'free'` would be treated as free. The middleware `SubscriptionIsActive` and `EnsureTenantIsActive` both skip subscription checks for free plans (line 38 of both files). This means if a paid plan is accidentally created with `monthly_price = 0`, its subscribers bypass ALL subscription gating.

### Duplicate price paths

`Subscription::billedPrice()` (line 36-39 of Subscription.php) calls `$this->plan?->getPriceForInterval($this->billing_interval)`, which reads `monthly_price`/`yearly_price`. There is no `plan_prices` relationship on `Plan` despite the migration creating a `plan_prices` table. Two pricing storage mechanisms exist; only the main columns are actually used.

---

## 2. Subscription Lifecycle (`app/Models/Subscription.php`)

### State Machine

```
trialing ──→ active ──→ past_due ──→ expired ──→ suspended
                ↑            │
                └────────────┘ (renew/markAsActive)
```

Transitions implemented:
- `markAsPastDue()`: active → past_due
- `markAsExpired()`: past_due → expired
- `suspend()`: any → suspended (sets `suspended_at`)
- `activate()`: suspended → active (preserves remaining time via seconds calculation, lines 181-189)
- `markAsCanceled()`: any → canceled
- `renew()`: sets new `expires_at`, status → active, reactivates suspended tenant
- `renewFromInterval()`: adds one billing cycle to current/future `expires_at`
- `cancelImmediately()`: sets status = expired, `expires_at = now()`

### Automated Expiry Processing (`app/Console/Commands/ProcessExpiredSubscriptions.php`)

Runs every 5 minutes via:
```
active → past_due  (when expires_at reached, 7-day grace starts)
past_due → expired (7 days after expires_at)
expired → suspended (1 day after, tenant.status also set to 'suspended')
trial → expired (trial_ends_at reached)
```

Separate command `subscriptions:send-expiry-warnings` runs daily at 08:00, warns at 7/3/1 days.

### Finding: `billedPrice()` uses deprecated pricing path

`Subscription::billedPrice()` (line 36-39) calls `$this->plan?->getPriceForInterval()`, which reads Plan's `monthly_price`/`yearly_price` columns. There is no fallback to the `plan_prices` table.

### Finding: `Subscription::create()` in `TenantBootstrapService` hardcodes `pending` status

`TenantBootstrapService::createSubscription()` (line 197-225) defaults to status `'pending'`. When status is `'pending'`, both `starts_at` and `expires_at` are set to `null` (lines 209-211). The subscription is created BEFORE the tenant session is active — there is no `Tenant::setCurrent()` call in the bootstrap flow. The `expires_at` is only set when the subscription status is `'active'` (line 210 condition).

### Finding: No trial enforcement on tenant creation

`createSubscription()` accepts a `$status` parameter (default `'pending'`), not `'trialing'`. To create a trialing subscription, the caller must explicitly pass `'trialing'` as the status. The `CreateStoreController` does NOT do this — it leaves the status as `'pending'` (which becomes `'active'` after email verification). There is no automatic trial-period assignment.

---

## 3. Feature Gating (`app/Services/FeatureGate.php`)

### Current State

| Method | What it checks | In Production? |
|--------|---------------|:---:|
| `isEnabled($key)` | `PlanFeature::where('feature_key', $key)->where('is_enabled', true)->exists()` | ❌ Always returns true |
| `typeEnabled($type)` | Maps type→key, then calls `isEnabled()` | ❌ Always returns true |
| `require($key)` | Throws `InvalidArgumentException` if disabled | ❌ Never throws |
| `getEnabledFeatures()` | Returns all features from plan | ❌ Returns ALL feature keys |
| `getAllFeaturesStatus()` | Returns status array per feature | ❌ All `enabled: true` |

### DEV_MODE bypass

`FeatureGate::DEV_MODE = true` (line 41) causes ALL methods to short-circuit before checking the database:
```php
protected const DEV_MODE = true;
// TODO: Re-enable subscription restrictions after SaaS billing implementation.
```

The TODO comment at line 38-40 explicitly acknowledges this is temporary. When `DEV_MODE = true`:
- `isEnabled()` returns `true` immediately (line 110-112)
- `require()` returns immediately without throwing (line 282-284)
- `getEnabledFeatures()` returns all keys from `FEATURE_LABELS` (line 171-172)
- `getAllFeaturesStatus()` returns everything as enabled (lines 196-208)

### Finding: Only 3 feature keys defined

Only `single_products`, `variable_products`, and `combo_products` exist as planned keys. `digital_products`, `subscription_products`, and `booking_products` are mentioned in docblocks (lines 24-26) but have no `FEATURE_TYPE_MAP` entries, no `UPGRADE_HINTS`, and no `PlanFeature` records in the seeder.

### Finding: Cache scoped per-plan but never invalidated on plan change

Cache key pattern: `feature_{$plan->id}_{$featureKey}`. `FeatureGate::clearCache()` is called in `TenantBootstrapService::createSubscription()` (line 222) and also available for explicit calls, but is NOT called by `SubscriptionController::changePlan()` or `SubscriptionController::assign()`. If `DEV_MODE` is ever set to `false`, plan changes may serve stale feature checks until 5-minute cache TTL expires.

---

## 4. Plan Limit Enforcement (`app/Services/SubscriptionLimitService.php`)

| Method | Enforced by | In Production? |
|--------|------------|:---:|
| `canCreateProduct()` → `assertCanCreateProduct()` | AdminProductController::store() | ❌ Always returns true |
| `canCreateStaff()` → `assertCanCreateStaff()` | AdminUserController::store() | ❌ Always returns true |
| `canUpload($bytes)` → `assertCanUpload($bytes)` | ImageService | ❌ Always returns true |

All three methods return `true` because `$plan->hasUnlimitedProducts()` etc. returns `true` when the limit is `null` (all seeded plans). The infrastructure correctly handles `null` as unlimited vs. `0` as zero-cap, but no plan actually sets any numeric limit.

### Finding: No downgrade enforcement

`SubscriptionController::changePlan()` (line 148) runs `checkDowngradeWarnings()` which produces warnings (e.g., "Product limit exceeded by current count") but does NOT block the downgrade. After downgrade, existing products/staff exceeding the new plan's limits are NOT:
- Blocked from continued operation
- Requiring deletion
- Flagged with a cap
- Scheduled for cleanup

---

## 5. Payment & Billing (`app/Http/Controllers/Admin/AdminBillingController.php`)

### Merchant Self-Service

| Feature | Implemented? | Details |
|---------|:---:|---|
| View subscription & usage | ✅ | `AdminBillingController::index()` returns plan details, usage, dates |
| Renew | ✅ | `AdminBillingController::renew()` calls `$subscription->renewFromInterval()` |
| Payment method management | ❌ | No payment method collection UI |
| Invoice/receipt history | ❌ | No invoices at all |
| Download invoice | ❌ | No invoice system |
| Cancel subscription | ❌ | No merchant-facing cancel endpoint (SuperAdmin only via `SubscriptionController::cancel()`) |
| Change plan | ❌ | No merchant-facing plan change (SuperAdmin only) |

### Finding: `renew()` charges nothing

`AdminBillingController::renew()` (line 56-86) simply calls `$subscription->renewFromInterval()`, which:
1. Checks if plan is free → no-op if so
2. Calculates new expiry date from billing interval
3. Updates `status='active'`, `expires_at`, `notes`
4. Sends notification

There is zero payment processing. No Stripe charge, no invoice, no receipt. This is the single most critical gap.

### Finding: Duplicate billing routes

Two sets of billing routes exist:
- `admin.billing` → `AdminBillingController` (merchant-facing, in `routes/web.php` line ~268)
- `storefront.admin.billing` → via store_slug prefix (duplicate routing for storefront context)

Both serve essentially the same `Admin/Billing/Index.jsx` component. The duplication is dead weight — only one routing pattern is actually used depending on whether the tenant is accessed via store slug.

---

## 6. SuperAdmin Tools (`app/Http/Controllers/SuperAdmin/SubscriptionController.php`)

| Action | Endpoint | Details |
|--------|----------|---------|
| List subscriptions | `GET /superadmin/subscriptions` | Paginated, status-filtered, search by tenant name/slug/email |
| Show subscription | `GET /superadmin/subscriptions/{subscription}` | Plan, history, usage, users, plans list for change |
| Assign subscription | `POST /superadmin/subscriptions/assign` | Creates new subscription for tenant |
| Change plan | `PUT /superadmin/subscriptions/{subscription}/change-plan` | With downgrade warnings |
| Renew (custom date) | `PUT /superadmin/subscriptions/{subscription}/renew` | Manual date input |
| Renew (by interval) | `PUT /superadmin/subscriptions/{subscription}/renew-from-interval` | Auto-calculated |
| Cancel | `PUT /superadmin/subscriptions/{subscription}/cancel` | With optional reason |
| Suspend | `PUT /superadmin/subscriptions/{subscription}/suspend` | Sets both sub + tenant status |
| Activate | `PUT /superadmin/subscriptions/{subscription}/activate` | Restores remaining time |

### Finding: No revenue reporting

The SuperAdmin dashboard shows revenue from product orders but has zero subscription/LTV financial data. No MRR, ARPU, churn rate, or subscription revenue breakdown exists anywhere.

### Finding: Route model binding on `Subscription` uses `id` only

`SubscriptionController` uses route model binding with `Subscription $subscription` (the `id`). Since `Subscription` uses `TenantAware` trait (which applies a `TenantScope` global scope filtering by `tenant_id`), the SuperAdmin must pass a subscription ID that exists in the current tenant context. However, SuperAdmins are not scoped to a tenant, so `TenantScope` may or may not filter depending on implementation. This could cause SuperAdmin subscription management to fail for subscriptions belonging to different tenants than the current session's tenant.

---

## 7. Middleware Chain

### `EnsureTenantIsActive` → `SubscriptionIsActive`

Both are registered in `tenant.active` middleware group. `EnsureTenantIsActive` runs first (checks tenant.status), then `SubscriptionIsActive` (checks subscription state).

### Behavior matrix (`EnsureTenantIsActive` + `SubscriptionIsActive`):

| Tenant Status | Sub Status | EnsureTenantIsActive | SubscriptionIsActive |
|:---:|:---:|:---|:---|
| active | trialing/active | ✅ Pass | ✅ Pass |
| active | past_due | ✅ Pass | ✅ Pass |
| active | expired (grace) | ✅ Pass | ⛔ Redirect to dashboard |
| active | canceled (future expiry) | ✅ Pass | ✅ Pass |
| active | canceled (no future) | ✅ Pass | ⛔ Redirect to dashboard |
| active | suspended | ✅ Pass | ⛔ "Account suspended" |
| pending | any | ⛔ "Verify email" | — (never reached) |
| suspended | any | ⛔ Redirect to suspended | — (never reached) |

### Front-end store: NO subscription checks

Neither middleware runs on frontend routes (`client:*`). The customer-facing store remains fully functional regardless of subscription status. Products are browsable, orders are creatable, checkout works — even for expired or suspended subscriptions. This is intentional but a revenue risk for paid-tier tenants.

---

## 8. Seed Data Analysis (`database/seeders/PlanSeeder.php`)

The seeder creates 3 plans, each with a specific featureset and zero numeric limits:

```php
'product_limit' => null,
'staff_limit' => null,
'storage_limit' => null,
```

The `plan_features` table only has three feature keys seeded:
- `single_products` → enabled for all 3 plans
- `variable_products` → enabled for Starter + Business
- `combo_products` → enabled for Business only

`yearly_price` IS now populated ($99.99 for Starter, $299.99 for Business) — this was fixed since the prior audit.

---

## 9. Critical Gaps (Priority Order)

| # | Gap | Location | Severity | Fix |
|---|-----|----------|:--------:|-----|
| 1 | **No payment gateway** | Missing entirely | **Critical** | Integrate Stripe/PayPal; charge on renewal and plan change |
| 2 | **DEV_MODE bypass** | `FeatureGate.php:41` | **Critical** | Set to `false` after limits are defined |
| 3 | **No plan limits** | `PlanSeeder` sets all to null | **Critical** | Set product_limit, staff_limit, storage_limit in seeder |
| 4 | **Free plan bypasses subscription gating** | `SubscriptionIsActive.php:38`, `EnsureTenantIsActive.php:49` | **High** | Remove free-plan exception in middleware (FeatureGate handles limits) |
| 5 | **No trial enforcement on creation** | `TenantBootstrapService.php:199-211` | **High** | Default to `trialing` status with `trial_ends_at` |
| 6 | **Downgrade doesn't block or enforce limits** | `SubscriptionController.php:148-154` | **High** | Add enforcement (require deletion or block) |
| 7 | **`billedPrice()` uses deprecated columns** | `Subscription.php:36-39` | **Medium** | Migrate to plan_prices table |
| 8 | **No subscription audit trail** | Notes field only | **Medium** | Add SubscriptionEvent/SubscriptionLog model |
| 9 | **Front store not gated by subscription** | No middleware on client routes | **Medium** | Add subscription check (conditional, design decision) |
| 10 | **Route model binding conflict with TenantAware** | `SubscriptionController.php` | **Medium** | Verify SuperAdmin tenant context handling |
| 11 | **Duplicate billing routes** | `routes/web.php` | **Low** | Consolidate to single pattern |
| 12 | **Plancache not invalidated on plan change** | `FeatureGate::clearCache()` not called in changePlan/assign | **Low** | Add cache invalidation |

---

## 10. Priority Fix Roadmap

### Phase 0 — Pre-Billing (This Sprint)

| Task | File(s) | Effort |
|------|---------|:------:|
| 1. Set real plan limits in seeder (10/2/100MB for Free, 50/5/1GB for Starter, unlimited/unlimited/unlimited for Business) | `database/seeders/PlanSeeder.php` | 10 min |
| 2. Verify `SubscriptionLimitService` works with non-null limits (write test) | `app/Services/SubscriptionLimitService.php` | 30 min |
| 3. Set `FeatureGate::DEV_MODE = false` | `app/Services/FeatureGate.php:41` | 1 min |
| 4. Add plan change cache invalidation | `SubscriptionController.php` → call `FeatureGate::clearCache()` | 5 min |
| 5. Verify middleware still works for free plan (should not gate, FeatureGate handles limits) | `SubscriptionIsActive.php`, `EnsureTenantIsActive.php` | 15 min |

### Phase 1 — Payment Integration

| Task | Effort |
|------|:------:|
| Stripe integration (laravel/cashier) | High |
| Payment method collection on registration | Medium |
| One-time charge on subscription creation | Medium |
| Auto-renewal charge in `renewFromInterval()` | Medium |
| Webhook handler for payment confirmation | Medium |

### Phase 2 — Billing Operations

| Task | Effort |
|------|:------:|
| Invoice/receipt generation | Medium |
| Merchant billing history UI | Medium |
| Dunning (failed payment retry) | Medium |
| Tax handling (VAT/GST) | Medium |
| Coupon/discount system | Medium |

### Phase 3 — Revenue Intelligence

| Task | Effort |
|------|:------:|
| MRR dashboard widget | Medium |
| Churn analytics | Medium |
| Subscription revenue reports | Medium |
| Plan change proration | Medium |

---

## 11. Recommended Pre-Production Ordering

1. **Set plan limits in seeder** — unlocks the entire limit-enforcement chain
2. **Set DEV_MODE = false** — activates feature gating
3. **Verify all limit checks work end-to-end** — test every `assertCan*` path
4. **Add cache invalidation on plan change** — prevent stale feature data
5. **Add trial period to tenant bootstrap** — default to trialing with `trial_ends_at`
6. **Remove free-plan bypass in middleware** — FeatureGate handles free plans correctly
7. **Integrate payment gateway** — the critical business requirement

---

## 12. Regression Risk

| Change | Risk | Mitigation |
|--------|:----:|------------|
| Setting DEV_MODE=false | **High** — existing tenants may lose access to features they're already using | Communicate with existing tenants; provide grace period |
| Setting non-null plan limits | **Medium** — existing tenants with >10 products on Free plan will hit limits | Migration: cap existing resources or assign them to appropriate plan |
| Adding trial on bootstrap | **Low** — new tenants only; existing tenants unaffected | Controlled by seeder change |
| Cache invalidation | **Low** — stale cache was harmless with DEV_MODE=true | Test with DEV_MODE=false |
| Stripe integration | **High** — financial transactions, compliance (PCI) | Phased rollout, webhook testing, sandbox-first |
