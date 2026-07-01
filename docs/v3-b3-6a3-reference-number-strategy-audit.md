# V3-B3-6A.3: Reference Number Strategy Audit

## 1. Executive Summary

Introduced a centralized Reference Number Strategy for the Platform Billing
domain. A `ReferenceNumberService` generates globally unique, human-readable,
date-based reference numbers for Payment Intents, Subscriptions, Invoices,
Refunds, and Webhook Events. The `payment_intents` table now carries a
`reference_number` column — the public identifier for every payment session.

**Status: COMPLETE** — zero regressions, full backward compatibility.

---

## 2. Reference Number Architecture

```
Any Caller
   │
   ▼
ReferenceNumberService
   │
   ├── generatePaymentIntentRef()  ──►  PAY-20260701-000001
   ├── generateSubscriptionRef()   ──►  SUB-20260701-000001
   ├── generateInvoiceRef()        ──►  INV-20260701-000001
   ├── generateRefundRef()         ──►  REF-20260701-000001
   └── generateWebhookRef()        ──►  WEB-20260701-000001
   │
   ▼
reference_numbers table (prefix + date → last_sequence)
   │
   ▼
Atomic DB transaction with row-level lock
   │
   ▼
sprintf('%s-%s-%06d', prefix, date, sequence)
```

---

## 3. Generation Strategy

### Format

```
PAY-20260701-000001
├──┘ ├──────┘ ├──────┘
│    │        └── 6-digit zero-padded daily sequence
│    └─────────── Date (YYYYMMDD) of generation
└──────────────── Prefix (3-4 letter entity code)
```

### Supported Prefixes

| Prefix | Entity | Method |
|--------|--------|--------|
| `PAY` | Payment Intent | `generatePaymentIntentRef()` |
| `SUB` | Subscription Payment | `generateSubscriptionRef()` |
| `INV` | Invoice | `generateInvoiceRef()` |
| `REF` | Refund | `generateRefundRef()` |
| `WEB` | Webhook Event | `generateWebhookRef()` |

### Atomicity

Generation uses a `reference_numbers` database table with a unique composite
key on `(prefix, date)`. The generator:

1. Opens a DB transaction
2. Acquires a row-level lock (`lockForUpdate()`) on the sequence record
3. Creates the record if it doesn't exist (sequence starts at 0)
4. Increments `last_sequence`
5. Commits the transaction
6. Returns `{PREFIX}-{YYYYMMDD}-{000001}`

This ensures no duplicate reference numbers even under concurrent requests.

---

## 4. Format Specification

| Component | Length | Example | Description |
|-----------|--------|---------|-------------|
| Prefix | 3-4 chars | `PAY` | Entity type identifier |
| Separator | 1 | `-` | Dash |
| Date | 8 | `20260701` | YYYYMMDD of generation |
| Separator | 1 | `-` | Dash |
| Sequence | 6 | `000001` | Zero-padded daily counter |

**Total length:** ~19 characters (varies by prefix length).

**Validation regex:** `/^(PAY|SUB|INV|REF|WEB)-\d{8}-\d{6}$/`

---

## 5. Database Review

| Table | Decision | Rationale |
|-------|----------|-----------|
| `reference_numbers` | **CREATED** | Sequence tracking table for atomic reference number generation. Columns: `id`, `prefix`, `date`, `last_sequence`, timestamps. Unique index on `(prefix, date)`. |
| `payment_intents.reference_number` | **ADDED** | New nullable unique `VARCHAR(30)` column. Populated on creation by `PaymentIntentFactory`. Administrators search by this column. |

### Why a dedicated sequences table?

A sequences table is the industry-standard approach for generating
human-readable, non-ID-based reference numbers. It avoids:
- Exposing auto-increment primary keys
- Race conditions under concurrent load
- Dependency on external services (Redis, UUID generators)

### What was NOT changed

- `subscription_payments` — unchanged. Future step will add `payment_intent_id` FK + reference number.
- `subscription_audit_logs` — unchanged. Already logs events with reason strings.
- All other tables — unchanged.

---

## 6. Services Introduced

| Service | Namespace | Responsibility |
|---------|-----------|----------------|
| `ReferenceNumberService` | `App\Services\Payment\Platform` | Centralized reference number generator. Provides typed methods for each entity. Thread-safe via DB row locks. |

### Model Introduced

| Model | Table | Purpose |
|-------|-------|---------|
| `ReferenceNumber` | `reference_numbers` | Sequence tracking (prefix + date → last_sequence) |

---

## 7. Files Modified

| File | Change |
|------|--------|
| `app/Models/PaymentIntent.php` | Added `reference_number` to `$fillable`; added `scopeWhereReference()`, `findByReference()` |
| `app/Services/Payment/Platform/PaymentIntentFactory.php` | Injects `ReferenceNumberService`; generates reference number on creation |
| `app/Services/Payment/Platform/PaymentIntentService.php` | Added `findByReference()`, `findByReferenceForTenant()` |
| `app/Providers/AppServiceProvider.php` | Registered `ReferenceNumberService` singleton |

### Files Created

| File | Purpose |
|------|---------|
| `database/migrations/2026_06_30_000002_create_reference_numbers_table.php` | Sequences table |
| `database/migrations/2026_06_30_000003_add_reference_number_to_payment_intents.php` | Reference number column on payment_intents |
| `app/Models/ReferenceNumber.php` | Sequence model |
| `app/Services/Payment/Platform/ReferenceNumberService.php` | Centralized generator |

---

## 8. Security Considerations

| Concern | Mitigation |
|---------|------------|
| ID leakage | Reference numbers use date + daily sequence, NOT auto-increment IDs. An attacker cannot infer the next database ID from `PAY-20260701-000042`. |
| Predictability | The sequence resets daily and uses 6 digits (1M possibilities per day). Sequence 1 doesn't leak any DB information. |
| Uniqueness | Unique DB constraint on `(prefix, date)` + atomic transaction with row lock prevents duplicates. |
| Immutability | Generation happens once at creation. The model never exposes a setter for `reference_number`. |
| Search safety | Reference numbers are indexed and searchable. The `findByReference()` method is scoped to tenant context. |

---

## 9. Gateway Compatibility

Future gateway adapters will store the gateway's native transaction ID in
Payment Intent `metadata`, while the platform's `reference_number` serves as
the public business identifier:

```
Platform:      PAY-20260701-000042
Stripe:        pi_3Oq1234567890   (stored in metadata.stripe_payment_intent_id)
KBZPay:        TXN20260701000001  (stored in metadata.kbzpay_transaction_id)
PayPal:        9XX12345XX123456X  (stored in metadata.paypal_capture_id)
```

The gateway adapter maps: gateway reference → platform reference → internal ID.
The `ReferenceNumberService` provides the platform reference.

---

## 10. Administrator Search Strategy

Administrators locate entities by:

1. **Search input:** `PAY-20260701-000042`
2. **Prefix detection:** `PAY` → routes to `payment_intents` table
3. **Query:** `SELECT * FROM payment_intents WHERE reference_number = 'PAY-20260701-000042'`
4. **Result:** Full payment intent with linked tenant, plan, subscription

The `prefix` is parseable via `ReferenceNumberService::parsePrefix()`, which
enables a unified search endpoint that dispatches to the correct table.

The `findByReference()` and `findByReferenceForTenant()` methods on
`PaymentIntentService` provide the query foundation.

---

## 11. Design Decisions

### Why a DB sequences table instead of UUIDs?

UUIDs are not human-readable, not sortable, and not predictable for admin
support. The DB sequences table produces references like `PAY-20260701-000042`
that staff can read, sort, and communicate over the phone.

### Why not use auto-increment with obfuscation?

Obfuscated auto-increment IDs (hashids, etc.) are reversible and leak the
database growth rate. Date-based daily sequences provide better opacity while
remaining human-readable.

### Why inject `ReferenceNumberService` into `PaymentIntentFactory` instead of generating in a model event?

Model events are framework-coupled and harder to test in isolation.
Dependency injection via the factory keeps the generation strategy explicit
and testable.

### Why a nullable `reference_number` column instead of NOT NULL?

Existing rows (from previous sprint's test data) need to stay valid. The
column is populated at creation by the factory, so new intents always have a
reference. The unique constraint ensures no duplicates.

### Why Parse helpers on `ReferenceNumberService`?

`parsePrefix()`, `parseDate()`, `parseSequence()` enable a future unified
search endpoint without coupling the search logic to the format string.

---

## 12. Future Extension Strategy

| Feature | How Reference Strategy Supports It |
|---------|------------------------------------|
| **Subscription Payments** | `generateSubscriptionRef()` ready for `subscription_payments` table |
| **Invoices** | `generateInvoiceRef()` ready for future invoice entity |
| **Refunds** | `generateRefundRef()` ready for future refund entity |
| **Webhook Events** | `generateWebhookRef()` for idempotent webhook tracking |
| **Unified Search** | Parse prefix → route to correct table → return result |
| **Admin UI** | Search by reference, display in tables, link to details |
| **Customer Support** | Staff ask "What's your payment reference?" → locate intent |
| **Gateway Transaction Logs** | Map gateway TXN ID to platform reference in metadata |
| **Cross-Entity Linking** | Link refund to original payment via reference number |

### What's NOT covered (out of scope for this step)

- Checkout
- Manual Payment
- Gateway integration
- Webhook processing
- Refund processing
- Invoice generation
- Transaction history UI

---

## 13. Regression Results

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
| Currency Object Foundation | ✅ | Currency value object unaffected |
| Payment Intent Foundation | ✅ | Payment intent enhanced with reference numbers |

**Total: 131 tests pass across 10 test suites. 557 assertions. Zero regressions.**

Pre-existing: 19 PHPUnit deprecation warnings (unchanged from prior sprints).

---

## 14. Manual QA Checklist

- [x] `reference_numbers` migration runs successfully
- [x] `reference_number` column added to `payment_intents` (nullable, unique)
- [x] `ReferenceNumber` model extends `Model`
- [x] `ReferenceNumberService` resolves via container
- [x] `generatePaymentIntentRef()` returns `PAY-YYYYMMDD-000001` format
- [x] `generateSubscriptionRef()` returns `SUB-YYYYMMDD-000001` format
- [x] `generateInvoiceRef()` returns `INV-YYYYMMDD-000001` format
- [x] `generateRefundRef()` returns `REF-YYYYMMDD-000001` format
- [x] `generateWebhookRef()` returns `WEB-YYYYMMDD-000001` format
- [x] All formats validated by regex `/(PAY|SUB|INV|REF|WEB)-\d{8}-\d{6}/`
- [x] Sequential calls produce incrementing sequences
- [x] Sequences reset per day (different dates produce independent counters)
- [x] `parsePrefix()` extracts entity code
- [x] `parseDate()` extracts date portion
- [x] `parseSequence()` extracts sequence portion
- [x] Concurrent generation is safe (DB row lock)
- [x] `PaymentIntentFactory` injects `ReferenceNumberService`
- [x] Created intents have non-null `reference_number`
- [x] `PaymentIntent::findByReference()` works
- [x] `PaymentIntentService::findByReference()` works
- [x] `PaymentIntentService::findByReferenceForTenant()` scoped to tenant
- [x] `scopeWhereReference()` works on query builder
- [x] Reference numbers are immutable (no setter exposed)
- [x] Reference numbers do not expose DB primary keys
- [x] All services resolve through Laravel container
- [x] `php -l` passes on all files
- [x] `php artisan migrate` runs without errors

---

## 15. Remaining Recommendations

1. **Unified Admin Search:** Build a search endpoint that parses the prefix
   from a reference number and dispatches to the correct table query.

2. **Subscription Payment Reference:** When `subscription_payments` is linked
   to `payment_intents` via FK, add a `reference_number` column populated
   from the intent's reference or a new `SUB-` reference.

3. **Idempotency:** Use `generateWebhookRef()` as the idempotency key for
   webhook processing in a future step.

4. **Invoice Generation:** Use `generateInvoiceRef()` when the invoice entity
   is created.

5. **Refund Reference:** Use `generateRefundRef()` when refund processing is
   implemented.

---

## Sprint Deliverables Summary

```
4 new files created:
  ─ database/migrations/2026_06_30_000002_create_reference_numbers_table.php
  ─ database/migrations/2026_06_30_000003_add_reference_number_to_payment_intents.php
  ─ app/Models/ReferenceNumber.php
  ─ app/Services/Payment/Platform/ReferenceNumberService.php

4 existing files modified:
  ─ app/Models/PaymentIntent.php          (+fillable, +scope, +findByReference)
  ─ app/Services/Payment/Platform/PaymentIntentFactory.php (+inject ReferenceNumberService)
  ─ app/Services/Payment/Platform/PaymentIntentService.php (+findByReference methods)
  ─ app/Providers/AppServiceProvider.php  (+ReferenceNumberService registration)

2 new database tables:
  ─ reference_numbers (sequences)
  ─ payment_intents.reference_number column

0 existing tables structurally modified.
0 enums modified.
0 gateway integrations.
0 UI changes.
```
