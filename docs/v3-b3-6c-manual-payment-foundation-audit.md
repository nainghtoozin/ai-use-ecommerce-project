# V3-B3-6C Manual Payment Foundation — Audit

## 1. Executive Summary

Implemented the Manual Payment workflow as the first supported payment method for Platform Billing. The architecture introduces a generic Payment Evidence system, an immutable Admin Review trail, and a recoverable REJECTED state — all without modifying any existing subscription, tenant, or store logic.

All 6 foundation layers (Payment Intent, Currency, Reference Number, Idempotency, Checkout, Manual Payment) are now connected into a complete horizontal slice from merchant checkout → evidence upload → admin review → approval/rejection → resubmit.

## 2. Manual Payment Flow

```
Merchant                          System                          Admin
─────────                        ──────                          ─────
  │                                │                                │
  ├─ Checkout ───────────────────► │                                │
  │                                ├─ PaymentIntent::create(DRAFT)  │
  │                                ├─ markPending(PENDING)          │
  │                                ├─ markWaitingPayment()          │
  │                                │    ┌──────────────────────┐    │
  │◄───────────────────────────────┤    │  reference_number    │    │
  │                                │    │  = PAY-20260701-0001 │    │
  │                                │    │  gateway = manual    │    │
  │  Upload Evidence               │    └──────────────────────┘    │
  │  (screenshot/receipt/          │                                │
  │   bank_ref/txn_number/note)    │                                │
  │                                │                                │
  ├─ confirmPayment ──────────────►│                                │
  │                                ├─ markWaitingReview()           │
  │                                │                                │
  │                                │    ┌──────────────────────┐    │
  │                                │    │  status =            │    │
  │                                │    │  waiting_review      │    │
  │                                │    └──────────────────────┘    │
  │                                │                                │
  │                                │◄──── getPendingReviews() ─────┤
  │                                │◄──── Review Evidence ─────────┤
  │                                │                                │
  │                                │         ┌──────────────┐      │
  │                                │◄──Approve│ review record│─────┤
  │                                ├─ approve│ reviewer_id  │      │
  │                                ├─ markAsPaid() └──────────────┘│
  │                                ├─ complete()                   │
  │                                ├─ PaymentReview::create(action=approved)
  │                                ├─ dispatch(PaymentIntentCompleted)
  │                                │                                │
  │            OR                  │         ┌──────────────┐      │
  │                                │◄──Reject│ review record│─────┤
  │                                ├─ reject │ reason=...   │      │
  │                                │  (REJECTED state) └──────────────┘
  │                                ├─ PaymentReview::create(action=rejected)
  │                                ├─ dispatch(PaymentIntentRejected)
  │                                │                                │
  ├─ resubmitPayment (after fix)──►│                                │
  │                                ├─ markWaitingPayment()          │
  │                                ├─ (rejection_reason cleared)    │
```

## 3. Payment Evidence Architecture

Generic evidence model supporting multiple types via `type` discriminator:

| Type               | file_path | note  | metadata           |
|--------------------|-----------|-------|--------------------|
| screenshot         | required  | optional | -                |
| receipt            | required  | optional | -                |
| bank_reference     | optional  | required | bank_name, account |
| transaction_number | optional  | required | -                |
| merchant_note      | -         | required | -                |

- `PaymentEvidence` model belongs to `PaymentIntent`
- `PaymentEvidenceService` provides CRUD with `store()`, `getForIntent()`, `remove()`, `hasEvidence()`, `count()`
- No screenshot-specific coupling — evidence types are extensible strings
- No file upload implementation — `file_path` stores the reference for future upload handling

## 4. Admin Review Flow

### PaymentReviewService

| Method                  | Purpose                              |
|-------------------------|--------------------------------------|
| `approve(intent, reviewerId, reviewerName)` | Records review + approves payment → COMPLETED |
| `reject(intent, reason, reviewerId, reviewerName)` | Records review + rejects payment → REJECTED |
| `getPendingReviews()`   | All intents in `waiting_review`       |
| `getReviewHistory(intent)` | All reviews for a specific intent   |
| `getRecentReviews(limit)` | Recent review activity              |
| `getRejectedIntents()`  | All intents in `rejected` state       |

### PaymentReview Model

- Immutable audit record per review action
- Stores: `payment_intent_id`, `action` (approved/rejected), `reviewer_id`, `reviewer_name`, `reason`, `metadata`
- Scopes: `approved()`, `rejected()`
- Each `approve()`/`reject()` creates a new PaymentReview row — never updated, only inserted

## 5. Files Modified

### Modified
| File | Change |
|------|--------|
| `app/Enums/Payment/TransactionStatus.php` | Added `REJECTED` case; updated `isPending()`, `canTransitionTo()` with REJECTED→WAITING_PAYMENT path |
| `app/Models/PaymentIntent.php` | Added `rejected_at` to fillable/casts; added `evidences()`, `reviews()`, `latestReview()` relationships; added `isRejected()`, `markAsRejected()`, `scopeWhereRejected()`, `scopeWherePendingReview()` |
| `app/Services/Payment/Platform/PaymentIntentService.php` | Added `reject()` method with dispatch; added `getPendingReviewIntents()`, `getRejectedIntents()` queries; updated `expireOverdue()` to include REJECTED |
| `app/Services/Payment/Platform/ManualPaymentService.php` | Changed `rejectPayment()` to REJECTED state (only from WAITING_REVIEW); added `cancelPayment()` for pending intents; added `resubmitPayment()` for REJECTED→WAITING_PAYMENT |
| `app/Providers/AppServiceProvider.php` | Registered `PaymentEvidenceService`, `PaymentReviewService` |

### New
| File | Purpose |
|------|---------|
| `database/migrations/2026_06_30_000005_create_payment_evidences_table.php` | Evidence storage table |
| `database/migrations/2026_06_30_000006_create_payment_reviews_table.php` | Admin review audit trail |
| `database/migrations/2026_06_30_000007_add_rejected_at_to_payment_intents.php` | `rejected_at` timestamp on intents |
| `app/Models/PaymentEvidence.php` | Evidence model |
| `app/Models/PaymentReview.php` | Review/audit model |
| `app/Services/Payment/Platform/PaymentEvidenceService.php` | Evidence CRUD service |
| `app/Services/Payment/Platform/PaymentReviewService.php` | Admin review service |
| `app/Events/Payments/PaymentIntentRejected.php` | Event dispatched on rejection |
| `tests/Feature/ManualPaymentFoundationTest.php` | 22 tests for evidence, review, resubmit flow |
| `docs/v3-b3-6c-manual-payment-foundation-audit.md` | This document |

## 6. Database Review

### New Tables
- **`payment_evidences`**: `id`, `payment_intent_id` (FK), `type`, `file_path` (nullable), `note` (nullable), `metadata` (JSON), timestamps
- **`payment_reviews`**: `id`, `payment_intent_id` (FK), `action`, `reviewer_id` (nullable), `reviewer_name` (nullable), `reason` (nullable), `metadata` (JSON), timestamps

### Modified Tables
- **`payment_intents`**: Added `rejected_at` (timestamp, nullable, after `cancelled_at`)

No existing table, column, or index was removed. Zero migrations modified (only added).

## 7. Regression Results

| Test Suite | Tests | Status |
|-----------|-------|--------|
| ManualPaymentServiceTest | 11 | PASS |
| ManualPaymentFoundationTest | 22 | PASS |
| AdminBillingPageTest | 13 | PASS |
| StorefrontCartCheckoutTest | 15 | PASS |
| SubscriptionLockModeTest | 21 | PASS |
| SubscriptionLimitTest | 22 | PASS |
| SubscriptionLimitServiceTest | 7 | PASS |
| TrialLifecycleTest | 14 | PASS |
| **Total** | **125** | **PASS** |

Pre-existing unrelated failures (StorefrontCustomerTest: 2, RefreshDatabase tests: ~110) remain unchanged — not caused by payment changes.

## 8. Manual QA Checklist

- [x] Merchant can initiate checkout → PaymentIntent at WAITING_PAYMENT
- [x] Merchant can upload multiple evidence types per intent
- [x] Merchant can confirm payment → transitions to WAITING_REVIEW
- [x] Guard prevents duplicate confirmPayment calls
- [x] Admin can view all pending reviews
- [x] Admin can approve → intent completes through APPROVED→PAID→COMPLETED
- [x] Guard prevents duplicate approvePayment calls
- [x] Admin can reject with reason → intent goes to REJECTED
- [x] Rejected intent stores rejection_reason in metadata
- [x] PaymentReview record created for every approve/reject action
- [x] Merchant can resubmit rejected intent → transitions back to WAITING_PAYMENT
- [x] Resubmit clears rejection_reason from metadata
- [x] Merchant can cancel their own pending intent → CANCELLED
- [x] Cancel fails on terminal intents
- [x] Reject fails from non-review states (WAITING_PAYMENT, COMPLETED)
- [x] PendingReview scope excludes approved/rejected/cancelled intents
- [x] Rejected scope returns only REJECTED intents
- [x] Review history returns all reviews for an intent chronologically
- [x] Full lifecycle (initiate→confirm→reject→resubmit→confirm→approve) succeeds
- [x] Evidence removal works
- [x] Evidence count/check works
- [ ] Evidence file upload (not implemented — `file_path` stored as string, actual upload handled by future UI)
- [ ] Subscription activation on approve (not implemented — `PaymentIntentCompleted` event dispatched for future listener)
- [ ] Notification on review action (not implemented — architecture-ready via events)

## 9. Remaining Recommendations

1. **Subscribe listener to `PaymentIntentCompleted`** to activate/renew subscriptions when a manual payment is approved
2. **Subscribe listener to `PaymentIntentRejected`** to send rejection notification to merchant
3. **Build admin UI** using `PaymentReviewService::getPendingReviews()`, `getRejectedIntents()`, `getRecentReviews()`
4. **Build merchant evidence upload UI** using `PaymentEvidenceService::store()` with file storage
5. **Register scheduler command** for `PaymentIntentService::expireOverdue()` to auto-expire stale intents
6. **Add `payment_intent_id` FK + `reference_number`** to `subscription_payments` when linking completed intents to payment records
7. **Integrate real gateways** (Stripe, KBZPay, etc.) implementing `PaymentGatewayInterface` — the Checkout + Intent architecture is ready
8. **Default expiry** for intents (config: `payments.intent_expiry_minutes`)
