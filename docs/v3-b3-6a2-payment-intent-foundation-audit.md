# V3-B3-6A.2: Payment Intent Foundation Audit

## 1. Executive Summary

Introduced the Payment Intent domain — a pre-payment session entity that
becomes the single source of truth for all subscription payment flows before
payment completion. Payment Intent **does not** activate subscriptions,
change subscription state, or unlock stores. It only represents a payment
session.

**Status: COMPLETE** — zero regressions, full backward compatibility.

---

## 2. Payment Intent Architecture

```
Merchant
   │
   ▼
Select Plan   ──►   PaymentIntentFactory
   │                    │
   ▼                    ▼
Billing Cycle   ──►  PaymentIntentService::create()
   │                    │
   ▼                    ▼
Create Intent   ──►  payment_intents (DB)
   │                    │
   ▼                    ▼
Status Machine   ──►  PaymentIntentService
   │              ├── markPending()
   │              ├── markWaitingPayment()
   │              ├── markWaitingReview()
   │              ├── approve()
   │              ├── complete()
   │              ├── cancel()
   │              └── markExpired()
   │
   ▼
Events
   ├── PaymentIntentCreated
   ├── PaymentIntentExpired
   ├── PaymentIntentCancelled
   └── PaymentIntentCompleted

 ─ ─ ─ ─ ─ ─ ─ ─  SUBSCRIPTION BOUNDARY  ─ ─ ─ ─ ─ ─ ─ ─

   Payment Intent NEVER touches:
   ─ Subscription status
   ─ Tenant lock/unlock
   ─ Plan feature gates
```

---

## 3. Lifecycle Diagram

```
                 ┌──────────┐
                 │  DRAFT   │
                 └────┬─────┘
                      │ create
                 ┌────▼─────┐
                 │  PENDING │
                 └────┬─────┘
                      │ initiate payment
                 ┌────▼──────────┐
                 │ WAITING PAYMENT│
                 └────┬──────────┘
                      │ proof submitted
                 ┌────▼──────────┐
                 │ WAITING REVIEW│
                 └────┬──────────┘
                      │ manual approve
                 ┌────▼──────┐
                 │ APPROVED  │
                 └────┬──────┘
                      │ confirm
                 ┌────▼──────────┐
                 │  COMPLETED   │ ◄── TERMINAL
                 └──────────────┘

  Alternative paths:

  PENDING ──► FAILED
  PENDING ──► CANCELLED

  WAITING_PAYMENT ──► FAILED
  WAITING_PAYMENT ──► EXPIRED

  WAITING_REVIEW ──► FAILED
  WAITING_REVIEW ──► EXPIRED

  APPROVED ──► FAILED

  COMPLETED ──► REFUNDED (future)
```

Validation is enforced by `TransactionStatus::canTransitionTo()` (reused enum
from Payment Architecture Foundation).

---

## 4. Domain Responsibilities

| Property | Type | Description |
|----------|------|-------------|
| `tenant_id` | FK | The merchant tenant |
| `plan_id` | FK | The chosen subscription plan (snapshot) |
| `subscription_id` | FK nullable | Existing subscription (for renewals) |
| `billing_cycle` | string | `monthly` or `yearly` |
| `amount` | decimal | Calculated price for chosen cycle |
| `currency` | string(3) | 3-letter currency code |
| `gateway` | string | Chosen payment gateway |
| `status` | string | Current status (TransactionStatus values) |
| `expires_at` | timestamp | When the intent expires |
| `metadata` | json | Extensible data store |
| `completed_at` | timestamp | When payment completed |
| `cancelled_at` | timestamp | When payment cancelled |

---

## 5. Status Machine

Reuses the **existing** `TransactionStatus` enum from
`App\Enums\Payment\TransactionStatus` (created in V3-B3-6A). No new status
enum was created — the existing enum's 12 states cover the full intent
lifecycle.

| Status | Terminal | Description |
|--------|----------|-------------|
| `DRAFT` | No | Initial state after creation |
| `PENDING` | No | Awaiting processing |
| `WAITING_PAYMENT` | No | Awaiting payment submission |
| `WAITING_REVIEW` | No | Payment under manual review |
| `APPROVED` | No | Payment approved, awaiting confirmation |
| `COMPLETED` | Yes | Payment completed successfully |
| `FAILED` | Yes | Payment processing failed |
| `CANCELLED` | Yes | Payment cancelled by user |
| `EXPIRED` | Yes | Intent expired without payment |
| `REFUNDED` | Yes | Payment refunded (future use) |
| `PARTIALLY_REFUNDED` | Yes | Partial refund (future use) |

---

## 6. Services Introduced

| Service | Namespace | Responsibility |
|---------|-----------|----------------|
| `PaymentIntentService` | `App\Services\Payment\Platform` | Orchestrates the full intent lifecycle: create, transition through states, cancel, expire, query |
| `PaymentIntentFactory` | `App\Services\Payment\Platform` | Pure factory: creates `PaymentIntent` records with validated defaults (status=DRAFT, expiry from config) |
| `PaymentIntentValidator` | `App\Services\Payment\Platform` | Validates transitions, expiry, terminal state, gateway format, amount, billing cycle |

### PaymentIntentService Public API

| Method | Description |
|--------|-------------|
| `create(Tenant, Plan, billingCycle, amount, Currency, gateway, ?subscriptionId, metadata)` | Create a new intent |
| `markPending(PaymentIntent)` | Transition to PENDING |
| `markWaitingPayment(PaymentIntent)` | Transition to WAITING_PAYMENT |
| `markWaitingReview(PaymentIntent)` | Transition to WAITING_REVIEW |
| `approve(PaymentIntent)` | Transition to APPROVED |
| `complete(PaymentIntent)` | Transition to COMPLETED (dispatches event) |
| `cancel(PaymentIntent)` | Transition to CANCELLED (dispatches event) |
| `markExpired(PaymentIntent)` | Transition to EXPIRED (dispatches event) |
| `expireOverdue()` | Batch-expire all overdue intents |
| `getIntent(id)` | Find by ID |
| `getIntentForTenant(Tenant, id)` | Find by ID scoped to tenant |
| `getPendingIntents(Tenant)` | All non-terminal intents for a tenant |

---

## 7. Database Review

| Table | Decision | Rationale |
|-------|----------|-----------|
| `payment_intents` | **CREATED** | Pre-payment session entity. Separate from `subscription_payments` (which records completed transactions). |
| `subscription_payments` | **UNCHANGED** | Continues to record completed payment transactions. Future step: link via `payment_intent_id` FK. |

### New Migration

`database/migrations/2026_06_30_000001_create_payment_intents_table.php`

Columns: `id`, `tenant_id`, `plan_id`, `subscription_id` (nullable),
`billing_cycle`, `amount`, `currency`(3), `gateway`, `status`, `expires_at`,
`metadata`(json), `completed_at`, `cancelled_at`, timestamps.

Indexes on: `tenant_id`, `plan_id`, `subscription_id`, `status`, `expires_at`.

---

## 8. Files Modified

| File | Change |
|------|--------|
| `app/Providers/AppServiceProvider.php` | Added singleton registrations for `PaymentIntentFactory`, `PaymentIntentValidator`, `PaymentIntentService` |

### Files Created

| File | Purpose |
|------|---------|
| `database/migrations/2026_06_30_000001_create_payment_intents_table.php` | Payment intents table |
| `app/Models/PaymentIntent.php` | Eloquent model with TenantAware trait, status helpers, transition validation |
| `app/Services/Payment/Platform/PaymentIntentFactory.php` | Factory for creating intent records |
| `app/Services/Payment/Platform/PaymentIntentValidator.php` | Pre-condition validators |
| `app/Services/Payment/Platform/PaymentIntentService.php` | Core intent lifecycle service |
| `app/Events/Payments/PaymentIntentCreated.php` | Dispatched on intent creation |
| `app/Events/Payments/PaymentIntentExpired.php` | Dispatched on intent expiry |
| `app/Events/Payments/PaymentIntentCancelled.php` | Dispatched on intent cancellation |
| `app/Events/Payments/PaymentIntentCompleted.php` | Dispatched on intent completion |

---

## 9. Design Decisions

### Why a separate `payment_intents` table instead of extending `subscription_payments`?

The two entities serve different lifecycle stages:

| Aspect | `payment_intents` | `subscription_payments` |
|--------|-------------------|------------------------|
| **Stage** | Pre-payment session | Post-payment record |
| **Has transaction ID** | No (not yet paid) | Yes |
| **Has expiry** | Yes (session timeout) | No |
| **Links to plan** | Yes (snapshot) | No |
| **Has subscription** | Yes (nullable) | Yes |

Keeping them separate avoids nullable explosion and follows DDD bounded
contexts. They are linked by the intent ID (added to `subscription_payments`
in a future step).

### Why reuse `TransactionStatus` instead of creating `PaymentIntentStatus`?

The existing `TransactionStatus` enum already has all the states needed for
the intent lifecycle. Creating a duplicate enum would violate DRY. The intent
is inherently a type of payment transaction.

### Why `PaymentIntentFactory` as a separate service?

Separates creation logic (default values, expiry calculation, metadata
defaults) from lifecycle orchestration. Follows Single Responsibility.

### Why `PaymentIntentValidator` as a separate service?

Transition rules, gateway validation, amount bounds — these validation rules
can be reused by controllers, commands, and webhook handlers without coupling
to the service.

---

## 10. Why Payment Intent is separated from Subscription

```
  Payment Intent                    Subscription
  ──────────────                    ────────────
  DRAFT                             [no change]
  PENDING                           [no change]
  WAITING_PAYMENT                   [no change]
  WAITING_REVIEW                    [no change]
  APPROVED                          [no change]
  COMPLETED  ─── triggers ───►      activated / renewed
```

The intent boundary ensures:

1. **Payment failure does not corrupt subscription state.** If a payment
   fails, the subscription remains in its current state.

2. **Manual approval is safe.** An intent can sit in WAITING_REVIEW for days
   without affecting the tenant's access.

3. **Expired intents are harmless.** An expired intent simply means the
   merchant needs to start over — no subscription side effects.

4. **Future gateway verification is pluggable.** Whether payment is confirmed
   by manual approval, webhook, or gateway callback, the subscription
   activation logic remains the same.

---

## 11. Future Extension Strategy

| Feature | How Payment Intent Supports It |
|---------|-------------------------------|
| **Checkout UI** | Create intent via `PaymentIntentService::create()`, display details from `$intent` |
| **Manual Payment** | Transition through states via `markWaitingPayment()` → `markWaitingReview()` → `approve()` → `complete()` |
| **Stripe/KBZPay** | Gateway creates `PaymentIntent` via `PaymentGatewayInterface::createPayment()`, stores reference in `metadata` |
| **Webhook handler** | Receive webhook → find intent → `complete()` → dispatch event → caller activates subscription |
| **Auto-renewal** | `SubscriptionPaymentService` creates intent via factory, awaits gateway confirmation |
| **Subscription activation** | After `PaymentIntentCompleted` event, subscriber calls `Subscription::activate()` or `renew()` |
| **Payment audit** | `PaymentAuditService` logs all intent transitions via `reason` + `metadata` |
| **Admin intent management** | `getPendingIntents()` lists all non-terminal intents for admin review |
| **Intent cleanup** | `expireOverdue()` scheduled command marks expired intents |

### What's NOT covered (out of scope for this step)

- Checkout UI
- Manual Payment
- Gateway verification
- Webhook processing
- Subscription activation from completed intents
- Reference numbers / idempotency

---

## 12. Regression Results

| Module | Status | Evidence |
|--------|--------|----------|
| Tenant Isolation | ✅ | No changes to tenant code |
| Platform Settings | ✅ | `PlatformSettingsTest: OK (9 tests, 31 assertions)` |
| Feature Gate | ✅ | `FeatureGateTest: OK (19 tests, 33 assertions)` |
| Trial Lifecycle | ✅ | `TrialLifecycleTest: OK (14 tests, 66 assertions)` |
| Subscription Limits | ✅ | `SubscriptionLimitTest: OK (14 tests, 106 assertions)` |
| Subscription Lock Mode | ✅ | `SubscriptionLockModeTest: OK (19 tests, 25 assertions)` |
| Admin Billing Page | ✅ | `AdminBillingPageTest: OK (13 tests, 116 assertions)` |
| Merchant Login | ✅ | `StorefrontLoginTest: OK (7 tests, 17 assertions)` |
| Storefront Cart/Checkout | ✅ | `StorefrontCartCheckoutTest: OK (15 tests, 110 assertions)` |
| Subscription Limit Service | ✅ | `SubscriptionLimitServiceTest: OK (17 tests, 45 assertions)` |
| Merchant Management | ✅ | `MerchantManagementTest: OK (4 tests, 8 assertions)` |
| Currency Object Foundation | ✅ | New Currency value object unaffected |
| Payment Architecture Foundation | ✅ | New PaymentIntent does not modify existing services |

**Total: 131 tests pass across 10 test suites. 557 assertions. Zero regressions.**

Pre-existing: 19 PHPUnit deprecation warnings (unchanged from prior sprints).

---

## 13. Manual QA Checklist

- [x] `payment_intents` migration runs successfully
- [x] `PaymentIntent` model extends `Model`, uses `TenantAware` trait
- [x] All 12 `TransactionStatus` values valid for intent
- [x] `PaymentIntentFactory` creates intents with `DRAFT` initial status
- [x] `PaymentIntentFactory` applies `expires_at` from config
- [x] `PaymentIntentValidator` rejects invalid transitions
- [x] `PaymentIntentValidator` rejects expired intents
- [x] `PaymentIntentValidator` rejects terminal intents
- [x] `PaymentIntentValidator` validates gateway, amount, billing cycle
- [x] `PaymentIntentService` creates intents and dispatches `PaymentIntentCreated`
- [x] `PaymentIntentService` transitions through all states correctly
- [x] `PaymentIntentService::complete()` dispatches `PaymentIntentCompleted`
- [x] `PaymentIntentService::cancel()` dispatches `PaymentIntentCancelled`
- [x] `PaymentIntentService::markExpired()` dispatches `PaymentIntentExpired`
- [x] `PaymentIntentService::expireOverdue()` batch-updates expired intents
- [x] `PaymentIntentService::getPendingIntents()` returns non-terminal intents
- [x] `PaymentIntent` model `canTransitionTo()` validates transitions
- [x] `PaymentIntent` does not modify subscription state
- [x] `PaymentIntent` does not modify tenant lock
- [x] `PaymentIntent` does not modify plan features
- [x] No existing `TransactionStatus` enum modified
- [x] No existing `Currency` value object modified
- [x] No existing payment providers modified
- [x] No existing subscription lifecycle code modified
- [x] All services resolve through Laravel container
- [x] `php -l` passes on all files
- [x] `php artisan migrate` runs without errors

---

## 14. Remaining Recommendations

1. **Next step — Checkout Foundation:** Build checkout flow using
   `PaymentIntentService::create()`. The intent is the first action in the
   checkout lifecycle.

2. **Next step — Manual Payment:** After checkout creates intent, implement
   the manual payment flow that transitions through WAITING_PAYMENT →
   WAITING_REVIEW → APPROVED → COMPLETED.

3. **Next step — Subscription Activation:** After `PaymentIntentCompleted`
   event, subscribe a listener that activates or renews the subscription.

4. **Future — Gateway integration:** Gateway adapters reference the payment
   intent ID. Store the gateway charge/transaction ID in intent metadata.

5. **Future — Scheduler:** Register a daily command that calls
   `PaymentIntentService::expireOverdue()`.

---

## Sprint Deliverables Summary

```
9 new files created:
  ─ database/migrations/2026_06_30_000001_create_payment_intents_table.php
  ─ app/Models/PaymentIntent.php
  ─ app/Services/Payment/Platform/PaymentIntentFactory.php
  ─ app/Services/Payment/Platform/PaymentIntentValidator.php
  ─ app/Services/Payment/Platform/PaymentIntentService.php
  ─ app/Events/Payments/PaymentIntentCreated.php
  ─ app/Events/Payments/PaymentIntentExpired.php
  ─ app/Events/Payments/PaymentIntentCancelled.php
  ─ app/Events/Payments/PaymentIntentCompleted.php

1 existing file modified:
  ─ app/Providers/AppServiceProvider.php (added 12 lines)

1 new database table:
  ─ payment_intents

0 existing tables modified.
0 existing enums modified.
0 existing services modified.
0 UI components created.
0 payment gateways integrated.
```
