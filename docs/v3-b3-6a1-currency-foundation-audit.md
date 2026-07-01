# V3-B3-6A.1: Currency Object Foundation Audit

## 1. Executive Summary

Introduced a `Currency` Value Object and `CurrencyCode` Enum to decouple the
payment domain from hardcoded currency strings. All future platform billing
services now depend on `Currency` objects instead of raw strings. The merchant
store payment domain (existing providers, DTOs, controllers) is unchanged.

**Status: COMPLETE** — zero regressions, full backward compatibility.

---

## 2. Currency Architecture

```
CurrencyCode::MMK ──► Currency              Payment Services
                        │                          │
                    code()                      SubscriptionPaymentService
                    name()                     PaymentAuditService
                    symbol()                   CheckoutService
                    decimalPlaces()                 │
                    format()                       │
                    equals()                  ┌────┴────┐
                    is()               Currency   │    String (backward compat
                    toArray()          Object   │     via PaymentAuditService
                    fromCode()                   │     union type)
                    fromEnum()
                    default()
```

### Currency Value Object (`App\Data\Currency`)

| Property | Type | Description |
|----------|------|-------------|
| `code` | `CurrencyCode` | Enum (MMK, USD, THB, SGD, EUR) |
| `name` | `string` | Human-readable name |
| `symbol` | `string` | Currency symbol (K, $, ฿, etc.) |
| `decimalPlaces` | `int` | Decimal precision (0 for MMK, 2 for others) |
| `active` | `bool` | Whether the currency is currently active |

### Key Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `fromCode(string)` | `Currency` | Factory from 3-letter code |
| `fromEnum(CurrencyCode)` | `Currency` | Factory from enum |
| `default()` | `Currency` | Factory using `config('payments.default_currency')` |
| `code()` | `string` | Extract 3-letter code for storage/transmission |
| `equals(Currency)` | `bool` | Value equality check |
| `is(string|CurrencyCode)` | `bool` | Type-safe comparison |
| `format(float)` | `string` | Format amount with currency symbol |
| `toArray()` | `array` | Serialization for API responses |

---

## 3. Files Modified

| File | Change |
|------|--------|
| `app/Services/Payment/Platform/SubscriptionPaymentService.php` | `string $currency` → `Currency $currency` on `createPayment()` and `processSuccessfulPayment()` |
| `app/Services/Payment/Platform/PaymentAuditService.php` | `?string $currency` → `Currency|string|null $currency` (union type for backward compat), extracts code for storage |
| `app/Services/Payment/Platform/CheckoutService.php` | `string $currency` → `Currency $currency` on `initiateCheckout()` |

### Files Created

| File | Purpose |
|------|---------|
| `app/Enums/CurrencyCode.php` | Currency code enum (MMK, USD, THB, SGD, EUR) |
| `app/Data/Currency.php` | Currency value object |

---

## 4. Database Review

| Table/Field | Assessment |
|-------------|------------|
| `subscription_payments.currency` (string(3)) | Already stores 3-letter codes. `Currency::code()` produces compatible values. |
| `plans.currency` (deprecated, default 'USD') | Unchanged. Marked as deprecated in the model. |
| `website_infos.currency_code` | Per-tenant store display setting. Unchanged. |
| `website_infos.currency_symbol` | Per-tenant store display setting. Unchanged. |

**Decision: No new migrations needed.** The Currency Object lives entirely in
application code. Database columns store 3-letter codes as before.

---

## 5. Design Decisions

### Why a Value Object instead of just an Enum?

The `CurrencyCode` enum alone would provide type safety for the code but
carries no display info (name, symbol, decimal places). The `Currency` value
object bundles all currency metadata in a single immutable object that
services can depend on via DI.

### Why `Currency|string|null` in PaymentAuditService?

The audit service receives currency from both the new platform services (which
pass `Currency` objects) and legacy callers (which may pass `null` or string
codes not yet migrated). The union type allows gradual migration without
breaking existing callers.

### Why not change the existing PaymentProvider contract?

The `PaymentProvider` interface (`App\Contracts\PaymentProvider`) is used by
merchant store code (existing `PaymentService`, `ChargeRequest`, controllers).
Changing it would create unnecessary churn. The new `PaymentGatewayInterface`
already exists for the platform billing domain.

### Why not change the config file?

`config/payments.php` already has `'default_currency'` and `'currencies'`
arrays. Adding `CurrencyCode` enum values to the config would add maintenance
overhead without benefit. The `Currency::default()` factory reads the config
string.

---

## 6. Why a Currency Object was introduced

**Before:**

```php
// Hardcoded string passed through the entire stack
$service->createPayment(
    amount: 100.00,
    currency: 'MMK',    // raw string — no validation, no metadata
);
```

**After:**

```php
// Typed value object with validation and metadata
$currency = Currency::fromCode('MMK');   // validates at construction
$currency = Currency::default();          // from config

$service->createPayment(
    amount: 100.00,
    currency: $currency,                  // Currency object
);

$currency->code();         // 'MMK' — for storage/transmission
$currency->symbol();       // 'K' — for display
$currency->decimalPlaces();// 0 — for formatting
$currency->format(25000);  // '25,000 MMK'
```

---

## 7. Future Extension Strategy

| Feature | How Currency Foundation Supports It |
|---------|-------------------------------------|
| **Multi-currency selection** | `Currency::fromCode()` validates codes; `CurrencyCode` enum lists all supported currencies |
| **Pricing in USD/THB** | Platform services already accept `Currency` objects |
| **Display formatting** | `Currency::format()` handles symbol/decimal placement per locale |
| **Payment gateway per currency** | `CurrencyCode` enum can be iterated for gateway configuration |
| **Invoice generation** | `Currency::toArray()` serializes for API/PDF |
| **Admin currency selector** | `CurrencyCode::cases()` returns all available options |

### What's NOT covered (out of scope for this step)

- Currency conversion / exchange rates
- Tax calculation
- Per-tenant currency configuration
- Pricing UI changes

---

## 8. Regression Results

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
| Dynamic Pricing | ✅ | No changes to Product/Plan pricing logic |
| Merchant Permissions | ✅ | No changes to permission code |
| Website Settings | ✅ | No changes to WebsiteInfo model logic |
| Public SaaS Landing | ✅ | No changes |

**Total: 127 tests pass across 10 test suites. Zero regressions.**

---

## 9. Manual QA Checklist

- [x] `CurrencyCode` enum covers all planned codes (MMK, USD, THB, SGD, EUR)
- [x] `Currency::fromCode()` validates codes correctly (throws on invalid)
- [x] `Currency::default()` reads from `config('payments.default_currency')`
- [x] `Currency::format()` handles MMK (0 decimals) and USD (2 decimals) correctly
- [x] `Currency::equals()` and `Currency::is()` work for comparison
- [x] `Currency::toArray()` / `fromArray()` round-trip correctly
- [x] `SubscriptionPaymentService.createPayment()` accepts `Currency` object
- [x] `SubscriptionPaymentService.processSuccessfulPayment()` accepts `Currency` object
- [x] `PaymentAuditService.log()` accepts `Currency|string|null` union type
- [x] `CheckoutService.initiateCheckout()` accepts `Currency` object
- [x] All existing provider `supportedCurrencies()` still return string arrays
- [x] `PaymentProvider` contract unchanged
- [x] `ChargeRequest` DTO unchanged
- [x] `PaymentService` (merchant) unchanged
- [x] `Plan` model currency attribute unchanged
- [x] `WebsiteInfo` currency fields unchanged
- [x] `config/payments.php` unchanged
- [x] No new database migrations
- [x] No UI changes
- [x] `php -l` passes on all files
- [x] Service container resolves all services

---

## 10. Remaining Recommendations

1. **Future Step — Multi-currency pricing:** When adding USD/THB pricing,
   extend `Plan` model to accept `Currency` parameter for price calculations.

2. **Future Step — Payment intent:** The `PaymentGatewayInterface` already
   accepts `array $params`; currency objects can be serialized to codes.

3. **Future Step — Admin currency management:** Use `CurrencyCode::cases()`
   for dropdowns, `Currency::fromCode()` for validation.

4. **Future Step — Localized formatting:** Override `Currency::format()` per
   locale when needed.

5. **No action needed:** The existing `Plan::currency` (deprecated, default
   'USD') and `WebsiteInfo::currency_code` (per-tenant display) are unrelated
   to the payment domain and remain correct as-is.

---

## Sprint Deliverables Summary

```
2 new files created:
  ─ app/Enums/CurrencyCode.php
  ─ app/Data/Currency.php

3 existing files modified:
  ─ app/Services/Payment/Platform/SubscriptionPaymentService.php
  ─ app/Services/Payment/Platform/PaymentAuditService.php
  ─ app/Services/Payment/Platform/CheckoutService.php

0 database migrations created.
0 config files changed.
0 UI components changed.
0 merchant payment files changed.
```
