# SaaS v2 Fix Roadmap — Remaining Known Issues

> **Last Updated:** 2026-05-31
> **Context:** Post-fix re-audit after 16+ tenant isolation fixes applied.
> **Data Isolation Status:** ✅ All critical issues resolved.
> **Remaining Issues:** Subscription/billing system incomplete.

---

## Priority Matrix

| Priority | Issue | Module | Effort | Impact |
|---|---|---|---|---|
| **P0** | `FeatureGate::DEV_MODE = true` | Plan Tiers | 1 file | All plan restrictions bypassed |
| **P0** | No payment gateway | Billing | Large | Cannot collect revenue |
| **P1** | No grace period enforcement | Subscriptions | 1 file | Tenants cut off immediately at expiry |
| **P1** | No suspension job | Subscriptions | 1 file | Expired tenants stay active |
| **P1** | No expiry notifications | Subscriptions | 2 files | Tenants not warned before suspension |
| **P2** | `EnsureTenantIsActive` unused | Routes | 1 file | No route-level tenant status check |
| **P2** | Legacy plan columns | DB Schema | 2 migrations | Dual source of truth |
| **P3** | `orWhereNull(tenant_id)` risk | TenantScope | 1 file | Shared data design risk |
| **P3** | Queue `Setting::get()` without tenant | Settings | 2 files | May read wrong tenant's setting |
| **P3** | Feature limits not enforced | Products/Staff | Multiple | Limits defined but not checked |
| **P4** | Trial → paid conversion | Subscriptions | 2 files | No flow for trial end |
| **P4** | Hourly cron frequency | Console | 1 file | 60-min expiry window |

---

## P0 — Release Blockers

### P0.1: FeatureGate DEV_MODE

**File:** `app/Services/FeatureGate.php:41`

```php
protected const DEV_MODE = true;
// TODO: Re-enable subscription restrictions after SaaS billing implementation.
```

**Problem:** All plan feature checks return `true`. A tenant on the "Free" plan can create unlimited variable products and combos. Plan tiers are functionally meaningless.

**Fix:**
1. Set `DEV_MODE = false` once payment integration is complete
2. Verify all gate checks (`canCreateProduct()`, `canUseFeature()`, etc.) work correctly
3. Add middleware/validation to enforce `product_limit`, `staff_limit`, `storage_limit`

**Dependencies:** Depends on P0.2 (payment gateway) — cannot launch paid tiers without billing.

---

### P0.2: No Payment Gateway

**Files:** Missing — no payment provider integration exists.

**Problem:** `AdminBillingController::renew()` calls `$subscription->renewFromInterval()` which extends the subscription without charging. No Stripe, Lemon Squeezy, Paddle, or any payment processing code exists.

**Fix (recommended approach):**
1. Integrate a payment provider (Lemon Squeezy recommended for Laravel SaaS)
2. Create a `PaymentService` contract/interface
3. Implement webhook handlers for `subscription_created`, `subscription_updated`, `subscription_cancelled`, `invoice.paid`, `invoice.payment_failed`
4. Update `SubscriptionExpiryService` to handle webhook-driven lifecycle
5. Add payment method collection during tenant registration/trial activation

**Estimate:** 2-4 weeks for full implementation with testing.

---

## P1 — Should Fix Before Production Launch

### P1.1: Grace Period Not Enforced

**File:** `app/Models/Subscription.php:274` — `GRACE_DAYS = 7` defined but never used.

**File:** `app/Services/SubscriptionExpiryService.php`

**Problem:** The subscription lifecycle should be:
```
expires_at reached → past_due (7 days grace) → expired (1 day) → suspended
```

But currently it's:
```
expires_at reached → expired (immediately)
```

`markAsPastDue()` exists on `Subscription` model but is **never called anywhere**.

**Fix:**
1. Update `SubscriptionExpiryService::process()`:
   - If `expires_at` passed + within grace period → set status to `past_due`
   - If `past_due` + grace period exhausted → set status to `expired`
   - If `expired` + 1 day → set status to `suspended`, update `Tenant::status`
2. Update `Subscription::markAsPastDue()` to also set `Tenant::status = 'suspended'` when transitioning to suspended

---

### P1.2: No Suspension Job

**File:** `app/Services/SubscriptionExpiryService.php`

**Problem:** When a subscription expires, `Tenant::status` remains `active`. The tenant can continue using the platform normally. No scheduled job checks for expired subscriptions and suspends tenants.

**Fix:**
1. Add `Tenant::status = 'suspended'` update in `SubscriptionExpiryService` when transitioning to `suspended`
2. Create `subscriptions:suspend-expired` Artisan command
3. Run on same hourly schedule as `subscriptions:process-expired`

---

### P1.3: No Expiry Notifications

**Files:** Missing — no notification system for subscription lifecycle events.

**Problem:** Tenants receive no notification when:
- Subscription is expiring soon (e.g., 7 days before)
- Subscription has expired
- Subscription has been suspended
- Payment failed (once payment is integrated)
- Renewal succeeded

**Fix:**
1. Create `App\Notifications\SubscriptionExpiringSoon` (with `days_left` parameter)
2. Create `App\Notifications\SubscriptionExpired`
3. Create `App\Notifications\SubscriptionSuspended`
4. Add notification dispatch in `SubscriptionExpiryService::process()`
5. Create `subscriptions:send-expiry-warnings` command that runs daily

---

## P2 — Should Fix Before Multi-Plan Launch

### P2.1: EnsureTenantIsActive Middleware Unused

**File:** `bootstrap/app.php` — Registered as `tenant.active`.
**Files:** `routes/web.php` — Never applied to any route.

**Problem:** The middleware checks `Tenant::status === 'active'` AND subscription is not expired/suspended. It's registered but unused. Currently only `TenantIsValid` middleware is applied, which only checks if the tenant exists (not their status).

**Fix:**
1. Add `->middleware(['tenant.active'])` to all tenant routes in `routes/web.php`
2. Or add to the route group that wraps tenant routes
3. Test that suspended/banned tenants are properly blocked

**Caution:** Do this AFTER implementing grace period (P1.1) and suspension (P1.2) — otherwise even slightly expired tenants will be locked out with no recourse.

---

### P2.2: Legacy Plan Columns

**Files:**
- `database/migrations/2026_05_28_000001_add_subscription_plan_id_and_expires_at_to_tenants.php`
- `database/migrations/2026_05_26_300002_add_plan_fields_to_users.php`

**Problem:** There are two sources of truth for plan/subscription data:
1. `subscriptions` table (new, canonical)
2. `tenants.subscription_plan_id`, `tenants.expires_at` (legacy)
3. `users.plan_id`, `users.plan_expires_at`, `users.plan_status`, `users.plan_started_at` (legacy)

These can diverge. For example, `SubscriptionExpiryService` updates `subscriptions.status` but does NOT update `tenants.status` or `users.plan_*`.

**Fix:**
1. Add a migration to drop legacy columns from `users` and `tenants` tables
2. Or add sync logic: whenever `subscriptions` changes, update the relevant legacy columns
3. Update any code referencing legacy columns to use `$tenant->subscription` relationship instead

---

## P3 — Technical Debt (Track for v2.1)

### P3.1: orWhereNull(tenant_id) in TenantScope

**File:** `app/Models/Scopes/TenantScope.php`

```sql
WHERE tenant_id = ? OR tenant_id IS NULL
```

**Risk:** Any record with a NULL `tenant_id` is visible to ALL tenants. If a `tenant_id` is accidentally left null (e.g., migration backfill missed a record), that record leaks across all tenants.

**Mitigation:**
- Add a database constraint: `ALTER TABLE ... ALTER COLUMN tenant_id SET NOT NULL` (for tables where null is never valid)
- For tables where null IS valid (shared reference data), document explicitly
- Add a monitoring query to detect null tenant_id records

---

### P3.2: Setting::get() in Queue Jobs

**Files:** Queue jobs that call `Setting::get('some_key')` (e.g., notification settings in `ProcessOrderNotifications`).

**Problem:** Queue jobs run in a process with no active tenant context. `Tenant::getCurrent()` returns `null`, so `Setting::get()` returns null or the most recently cached value.

**Current Mitigation:** All tenants currently have the same setting values, so this doesn't cause incorrect behavior. But if tenants differ in their settings, queue jobs will read arbitrary values.

**Fix:**
1. Pass `$tenantId` to queue jobs that need settings (same pattern as dashboard jobs)
2. Or pass the actual setting value as a constructor parameter instead of reading it in `handle()`

---

### P3.3: Feature Limits Not Enforced

**Files:** Plans define `product_limit`, `staff_limit`, `storage_limit` but no code enforces them.

**Fix:**
1. Add validation in `AdminProductController::store()` to check `product_limit` before creating a new product
2. Add middleware or validation for staff/user limits
3. Add storage quota check before file uploads
4. Use `FeatureGate::canCreateProduct()` in controllers (currently always returns true due to DEV_MODE)

---

## P4 — Nice to Have (Future)

### P4.1: Trial → Paid Conversion
- No flow exists for converting a trial subscription to a paid subscription
- No UI prompt for payment method when trial ends
- Create a conversion flow with payment provider integration

### P4.2: Hourly Cron Frequency
- `routes/console.php:12` runs `subscriptions:process-expired` hourly
- Subscriptions could remain active for up to 60 minutes past expiry
- Consider running every 5 minutes via `->everyFiveMinutes()` for premium plans

### P4.3: Product Combo Unique Index
- `product_combo_variant_unique` on `(product_id, combo_product_id, linked_variant_id)` does NOT include `tenant_id`
- Currently mitigated by FK constraints to `products` table (which is tenant-scoped)
- Consider adding `tenant_id` to the index for defense-in-depth

---

## Fix Order Recommendation

```
Phase 1 — Before Production Testing:
  ✓ (DONE) Data leak fixes (dashboard, reports)
  ✓ (DONE) Validation rules + DB indexes
  ✓ (DONE) Mass assignment security
  ─────────────────────────────────
  [YOU ARE HERE]

Phase 2 — Before Paid Multi-Plan Launch:
  1. Payment gateway integration (P0.2)
  2. DEV_MODE = false (P0.1, dependent on P0.2)
  3. Grace period (P1.1)
  4. Suspension job (P1.2)
  5. Expiry notifications (P1.3)
  6. EnsureTenantIsActive middleware (P2.1)
  7. Legacy column cleanup (P2.2)

Phase 3 — v2.1 Improvements:
  8. Feature limit enforcement (P3.3)
  9. Queue setting context (P3.2)
  10. Null tenant_id monitoring (P3.1)
  11. Trial conversion flow (P4.1)
  12. Cron frequency adjustment (P4.2)
  13. Combo index defense (P4.3)
```

---

*End of Fix Roadmap*
