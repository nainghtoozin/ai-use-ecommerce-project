# V3-B3-6D Transaction Foundation — Audit

## 1. Executive Summary

Implemented the permanent Transaction Layer for Platform Billing — the historical source of truth after a Payment Intent progresses. Introduced four new architectural domains: Transaction (financial record), Ledger (accounting entries), Timeline (chronological event log), and Comments (immutable review conversation). The layer is gateway-agnostic and supports Manual Payment today while being architected for future Stripe, KBZPay, AyaPay, WavePay, and PayPal integration.

All 154 relevant tests pass with zero regressions across payment, checkout, subscription, and admin test suites.

## 2. Transaction Architecture

### PaymentTransaction Model

A permanent financial record created when a Payment Intent completes. Represents the business event, not the payment session.

| Column | Type | Purpose |
|--------|------|---------|
| `payment_intent_id` | FK unique | Links to the source Payment Intent (one-to-one) |
| `transaction_number` | string unique | Generated reference (TXN-YYYYMMDD-NNNNNN) |
| `tenant_id` | FK | Merchant tenant |
| `plan_id` | FK | Plan purchased |
| `subscription_id` | FK nullable | Future: links to subscription |
| `amount` | decimal | Transaction amount |
| `currency` | string(3) | ISO 4217 currency code |
| `gateway` | string | Gateway used (manual, stripe, kpay, etc.) |
| `status` | string | completed, refunded, partially_refunded |
| `gateway_reference` | string nullable | Future: external gateway ID (Stripe pi_xxx, KBZ txn_id) |
| `metadata` | JSON | Flexible extension |

### Transaction Flow

```
Payment Intent → Waiting Review → Admin Approve → PaymentIntentCompleted
                                                       ↓
                                            CreateTransactionFromCompletedIntent (listener)
                                                       ↓
                                            PaymentTransaction::create()
                                            LedgerService::record('payment_completed')
                                            PaymentTimelineService::record('completed')
```

### PaymentTransactionService

| Method | Purpose |
|--------|---------|
| `createFromCompletedIntent(intent)` | Creates a transaction from a completed Payment Intent |
| `findByTransactionNumber(number)` | Lookup by transaction reference |
| `findByIntent(intent)` | Get transaction for a specific intent |
| `getForTenant(tenant)` | All transactions for a merchant |
| `search(referenceNumber, gateway, status)` | Admin search by any combination |

## 3. Financial Ledger Design

### LedgerEntry Model

Generic accounting entry supporting future financial events.

| Column | Type | Purpose |
|--------|------|---------|
| `transaction_id` | FK nullable | Links to PaymentTransaction |
| `payment_intent_id` | FK nullable | Links directly to PaymentIntent |
| `type` | string | Event type (payment_completed, refund, chargeback, etc.) |
| `amount` | decimal | Financial amount |
| `currency` | string(3) | ISO 4217 |
| `description` | text nullable | Human-readable description |
| `metadata` | JSON | Flexible extension |
| `recorded_at` | timestamp | When the financial event occurred |

### LedgerService

| Method | Purpose |
|--------|---------|
| `record(type, amount, currency, transaction, intent, description, metadata)` | Record a new ledger entry |
| `getForTransaction(transaction)` | All entries for a transaction |
| `getForIntent(intent)` | All entries for an intent |
| `getByType(type)` | All entries of a specific type |

### Supported Future Event Types

The `type` field supports future events without schema changes:

- `payment_created`
- `payment_approved`
- `payment_completed`
- `payment_rejected`
- `subscription_activated`
- `subscription_renewed`
- `refund`
- `chargeback`
- `gateway_settlement`

## 4. Timeline Architecture

### PaymentTimelineEvent Model

Append-only chronological event log for a Payment Intent. Never overwrites history.

| Column | Type | Purpose |
|--------|------|---------|
| `payment_intent_id` | FK | Intent this event belongs to |
| `type` | string | Event type |
| `description` | text nullable | Human-readable description |
| `metadata` | JSON | Event-specific data |
| `occurred_at` | timestamp | When the event occurred (not when recorded) |

### Event Types

- `created` — Payment intent created
- `evidence_uploaded` — Merchant uploaded evidence
- `comment_added` — Admin/merchant/system comment
- `rejected` — Payment rejected by admin
- `resubmitted` — Merchant resubmitted after rejection
- `completed` — Payment completed (transaction created)
- `cancelled` — Payment cancelled
- `expired` — Payment expired

### Recording Strategy

Events are recorded through two mechanisms:

1. **Event-driven** (via `PaymentTimelineEventSubscriber`):
   - `PaymentIntentCreated` → `created`
   - `PaymentIntentCancelled` → `cancelled`
   - `PaymentIntentExpired` → `expired`
   - `PaymentIntentRejected` → `rejected`

2. **Service-level** (directly in service methods):
   - `PaymentEvidenceService::store()` → `evidence_uploaded`
   - `PaymentCommentService::addComment()` → `comment_added`
   - `ManualPaymentService::resubmitPayment()` → `resubmitted`
   - `CreateTransactionFromCompletedIntent` listener → `completed`

### PaymentTimelineService

| Method | Purpose |
|--------|---------|
| `record(intent, type, description, metadata, occurredAt)` | Record a timeline event |
| `getForIntent(intent)` | All events for an intent, chronologically |
| `getByType(intent, type)` | Events of a specific type for an intent |

## 5. Review Comment Architecture

### PaymentComment Model

Immutable conversation records belonging to the payment review lifecycle. Not a chat system.

| Column | Type | Purpose |
|--------|------|---------|
| `payment_intent_id` | FK | Intent this comment belongs to |
| `author_type` | string | admin, merchant, or system |
| `author_id` | bigint nullable | User ID (null for system) |
| `author_name` | string | Display name |
| `body` | text | Comment content |
| `metadata` | JSON | Flexible extension |

### Supported Author Types

- **admin** — Platform admin reviewing the payment
- **merchant** — Merchant replying to admin feedback
- **system** — Automated system comment

### PaymentCommentService

| Method | Purpose |
|--------|---------|
| `addComment(intent, authorType, authorId, authorName, body, metadata)` | Add an immutable comment + record timeline event |
| `getForIntent(intent)` | All comments for an intent, chronologically |

## 6. Services Introduced

| Service | File | Purpose |
|---------|------|---------|
| `PaymentTransactionService` | `app/Services/Payment/Platform/PaymentTransactionService.php` | Transaction CRUD, search |
| `PaymentTimelineService` | `app/Services/Payment/Platform/PaymentTimelineService.php` | Timeline event recording |
| `PaymentCommentService` | `app/Services/Payment/Platform/PaymentCommentService.php` | Review comment management |
| `LedgerService` | `app/Services/Payment/Platform/LedgerService.php` | Financial ledger entries |

## 7. Database Review

### New Tables (4)

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `payment_transactions` | Financial records after payment completion | `payment_intent_id` (unique), `transaction_number` (unique), `amount`, `currency`, `gateway`, `status`, `gateway_reference` |
| `payment_timeline_events` | Chronological event log | `payment_intent_id`, `type`, `description`, `occurred_at` |
| `payment_comments` | Immutable review conversation | `payment_intent_id`, `author_type`, `author_id`, `author_name`, `body` |
| `ledger_entries` | Financial accounting entries | `transaction_id`, `payment_intent_id`, `type`, `amount`, `currency`, `recorded_at` |

### Existing Tables Modified

None. All 4 tables are additions.

## 8. Files Modified

| File | Change |
|------|--------|
| `app/Providers/EventServiceProvider.php` | Registered `CreateTransactionFromCompletedIntent` listener + `PaymentTimelineEventSubscriber` |
| `app/Providers/AppServiceProvider.php` | Registered `PaymentTransactionService`, `PaymentTimelineService`, `PaymentCommentService`, `LedgerService`; updated `ManualPaymentService` DI |
| `app/Models/PaymentIntent.php` | Added `transaction()`, `timelineEvents()`, `comments()` HasMany relationships |
| `app/Services/Payment/Platform/ReferenceNumberService.php` | Added `PREFIX_TRANSACTION = 'TXN'` and `generateTransactionRef()` |
| `app/Services/Payment/Platform/ManualPaymentService.php` | Added `PaymentTimelineService` dependency; records `resubmitted` timeline event |
| `app/Services/Payment/Platform/PaymentEvidenceService.php` | Added `PaymentTimelineService` dependency; records `evidence_uploaded` timeline event |
| `tests/Feature/ManualPaymentServiceTest.php` | Added minimal schema for `payment_transactions`, `payment_timeline_events`, `ledger_entries` |
| `tests/Feature/ManualPaymentFoundationTest.php` | Added minimal schema for `payment_transactions`, `payment_timeline_events`, `ledger_entries` |

## 9. Design Decisions

1. **Transaction is separate from Payment Intent** — Intent represents the payment session/state machine; Transaction represents the permanent financial record. Different lifetimes, different concerns.

2. **Transaction created via listener** — `CreateTransactionFromCompletedIntent` listens to `PaymentIntentCompleted`. This decouples transaction creation from the approval flow and prevents double-creation via idempotency check.

3. **Timeline uses both listeners and service-level recording** — Events that already have dispatchers (created, cancelled, rejected, expired) are handled by `PaymentTimelineEventSubscriber`. Actions without events (evidence_uploaded, comment_added, resubmitted) are recorded directly in service methods.

4. **Comments are immutable but not backed by events** — Adding a comment records a timeline event of type `comment_added`. The comment itself is never updated or deleted.

5. **Comments are separate from timeline** — Comments carry rich author data (type, id, name, body) while timeline events are lightweight log entries with type + description. This keeps the timeline queryable and the comments searchable.

6. **`occurred_at` vs `created_at`** — Timeline events store both `occurred_at` (when the actual event happened) and `created_at` (Laravel default). This distinguishes event timing from record creation timing.

7. **`gateway_reference` on transactions** — Empty column ready for future gateway IDs (Stripe `pi_xxx`, KBZ transaction ID, etc.) without requiring migration.

8. **Ledger is type-driven, not schema-driven** — New financial event types (refund, chargeback, settlement) require no schema changes — just a new `type` string value.

## 10. Gateway Compatibility

Future gateways attach external references to the Transaction via `gateway_reference`:

| Gateway | External Reference | Column |
|---------|-------------------|--------|
| Manual Payment | (internal reference number) | `transaction_number` |
| Stripe | `pi_xxx` PaymentIntent ID | `gateway_reference` |
| KBZPay | KBZ Transaction ID | `gateway_reference` |
| AyaPay | AyaPay Reference Number | `gateway_reference` |
| WavePay | WavePay Transaction ID | `gateway_reference` |
| PayPal | PayPal Capture ID | `gateway_reference` |

Each gateway integration will create the same `PaymentTransaction` and `LedgerEntry` structures — never duplicating transaction history.

## 11. Future Reporting Strategy

The Ledger + Transaction architecture supports future financial reporting:

- **Revenue by period**: `LedgerEntry::where('type', 'payment_completed')->whereBetween('recorded_at', ...)->sum('amount')`
- **Revenue by gateway**: `LedgerEntry::where('type', 'payment_completed')->whereIn('transaction_id', $gatewayTransactions)->sum(...)`
- **Refund rate**: Compare `payment_completed` vs `refund` ledger entries
- **Merchant transaction history**: `PaymentTransactionService::getForTenant($tenant)`
- **Failed vs successful payments**: Timeline event types + Transaction status

## 12. Regression Results

| Test Suite | Tests | Status |
|-----------|-------|--------|
| TransactionFoundationTest | 29 | PASS |
| ManualPaymentServiceTest | 11 | PASS |
| ManualPaymentFoundationTest | 22 | PASS |
| AdminBillingPageTest | 13 | PASS |
| StorefrontCartCheckoutTest | 15 | PASS |
| SubscriptionLockModeTest | 21 | PASS |
| SubscriptionLimitTest | 22 | PASS |
| SubscriptionLimitServiceTest | 7 | PASS |
| TrialLifecycleTest | 14 | PASS |
| **Total** | **154** | **PASS** |

Pre-existing: 19 PHPUnit deprecation warnings from subscription tests (unchanged).

## 13. Manual QA Checklist

- [x] Transaction created when Payment Intent completes
- [x] Transaction has unique `transaction_number` with TXN prefix
- [x] Transaction stores amount, currency, gateway from source intent
- [x] Transaction NOT created on rejection or cancellation
- [x] Transaction idempotent — only one per intent
- [x] Search by transaction_number finds correct transaction
- [x] Search by gateway returns filtered results
- [x] Merchant's transactions visible via `getForTenant`
- [x] Timeline records `created` event on intent creation
- [x] Timeline records `completed` event on payment completion
- [x] Timeline records `rejected` event on admin rejection
- [x] Timeline records `resubmitted` event on merchant resubmit
- [x] Timeline records `evidence_uploaded` on evidence store
- [x] Timeline records `cancelled` on payment cancellation
- [x] Timeline events are chronological by `occurred_at`
- [x] Timeline events filterable by type
- [x] Admin comment stores author_type, author_id, author_name, body
- [x] Merchant comment stores correct author info
- [x] System comment stores correct author info (null author_id)
- [x] Comment metadata saved correctly
- [x] Comment triggers `comment_added` timeline event
- [x] Comments retrievable per intent in chronological order
- [x] Ledger entry created on payment completion
- [x] Ledger entry stores amount, currency, type, description
- [x] Ledger entry linked to transaction and payment intent
- [x] Ledger entries filterable by type
- [x] Full integrated lifecycle: created→evidence→rejected→comment→evidence→resubmitted→completed with transaction + ledger + timeline + comments
- [ ] Gateway reference population (future gateway integration)
- [ ] Subscription activation on completion (future sprint step)
- [ ] Expired timeline event (requires intent to expire naturally)

## 14. Remaining Recommendations

1. **Subscribe listener to `PaymentIntentCompleted`** for subscription activation (notify subscription system when a transaction is created)
2. **Build admin search UI** using `PaymentTransactionService::search()`, `PaymentTimelineService::getForIntent()`, `PaymentCommentService::getForIntent()`, `LedgerService::getForIntent()`
3. **Build merchant transaction history UI** using `PaymentTransactionService::getForTenant()`
4. **Add refund ledger entries** when refund functionality is implemented (use `LedgerService::record()` with type `refund`)
5. **Populate `gateway_reference`** on transaction when integrating real gateways
6. **Add `payment_intent_id` FK + `transaction_number`** to `subscription_payments` when linking
7. **Register scheduler command** for `PaymentIntentService::expireOverdue()` to trigger expired timeline events
8. **Build admin comment UI** using `PaymentCommentService::addComment()` + `getForIntent()`
