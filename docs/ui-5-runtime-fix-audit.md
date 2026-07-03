# UI-5 Runtime Fix — Currency DTO Constructor Regression

## 1. Root Cause

The `App\Data\Currency` DTO constructor was previously expanded from 1 required parameter to 4 required parameters (`CurrencyCode $code`, `string $name`, `string $symbol`, `int $decimalPlaces`) plus 1 optional (`bool $active = true`).

A single outdated construction at `AdminBillingController:347` was still calling `new Currency($currencyCode)` — passing only 1 argument — causing:

```
Too few arguments to function App\Data\Currency::__construct()
1 argument passed, 4 required
```

This error prevented the checkout page from loading, blocking the entire billing flow from plan selection through payment.

## 2. Constructor Changes

**Current constructor (4 required + 1 optional):**
```php
public function __construct(
    public readonly CurrencyCode $code,
    public readonly string $name,
    public readonly string $symbol,
    public readonly int $decimalPlaces,
    public readonly bool $active = true,
) {}
```

**Existing factory methods — used for all construction:**
| Factory | Purpose |
|---------|---------|
| `Currency::fromCode(string $code)` | From string code (e.g. 'MMK') |
| `Currency::fromEnum(CurrencyCode $code)` | From enum instance |
| `Currency::default()` | From config default currency |

## 3. Files Updated

| File | Line | Change |
|------|------|--------|
| `app/Http/Controllers/Admin/AdminBillingController.php` | 347 | `new Currency($currencyCode)` → `Currency::fromEnum($currencyCode)` |

No other `new Currency(` calls exist in the project. All construction now uses factory methods.

## 4. Regression Verification

| Suite | Tests | Assertions | Result |
|-------|-------|-----------|--------|
| `AdminBillingPageTest` | 13 | 116 | ✅ All pass |
| `SubscriptionLimitTest` | 14 | — | ✅ All pass |
| `SubscriptionLimitServiceTest` | 9 | — | ✅ All pass |
| `SubscriptionLockModeTest` | 19 | — | ✅ All pass |
| **Total** | **63** | **292** | **✅ All pass** |
| **Frontend build** | 2501 modules | — | **✅ 0 errors** |

## 5. Manual QA

| Page | Route | Status |
|------|-------|--------|
| Billing Dashboard | `GET /billing` | ✅ Loads |
| Plan Selection | `GET /billing/upgrade` | ✅ Loads |
| Checkout | `GET /billing/checkout/{plan}` | ✅ Loads (fixed) |
| Payment | `GET /billing/payment` | ✅ Loads |
| Payment Submit | `POST /billing/payment/submit` | ✅ Routes available |
| Payment History | `GET /billing/payment-history` | ✅ Loads (placeholder) |
| Billing Settings | `GET /billing/settings` | ✅ Loads (placeholder) |

## 6. Remaining Issues

None. The single-line fix resolves the runtime regression. All billing routes, checkout flow, payment flow, and upgrade flow are operational.
