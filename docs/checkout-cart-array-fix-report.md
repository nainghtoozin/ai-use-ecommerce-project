# Checkout Cart Data Type Bug Fix

## Root Cause

`StorefrontCheckoutController::filterCartByTenant()` builds the cart items array using session cart keys as array keys:

```php
$items[$cartKey] = [...];
```

PHP session cart keys are non-sequential strings (e.g. product IDs or hashes). When this associative array is serialized to JSON by Inertia, `json_encode` produces a **JavaScript Object** (`{key1: {...}, key2: {...}}`) instead of a **JavaScript Array** (`[{...}, {...}]`).

JavaScript Objects do not have a `.reduce()` method, causing the runtime error:

```
TypeError: reduce is not a function
```

## Contrast

`CheckoutController::getCartItems()` (used by the non-storefront `/checkout` route) builds items with sequential indices:

```php
$items[] = [...];
```

This correctly serializes as a JSON Array. The bug only affects the storefront checkout flow.

## Files Modified

### 1. `app/Http/Controllers/StorefrontCheckoutController.php`

**Variable causing crash:** `$cartItems` (Inertia prop)

**Before (line 88):**
```php
'cartItems' => $cartItems,
```

**After:**
```php
'cartItems' => array_values($cartItems),
```

`array_values()` strips the non-sequential keys and re-indexes the array to `[0, 1, 2, ...]`, ensuring JSON serialization produces a proper Array.

### 2. `resources/js/Pages/Storefront/Checkout.jsx`

**Variable causing crash:** `cartItems` (prop destructured from Inertia)

**Before (line 165):**
```js
const totalItems = cartItems?.reduce((s, i) => s + i.quantity, 0) || 0;
```

**After:**
```js
const totalItems = Array.isArray(cartItems) ? cartItems.reduce((s, i) => s + i.quantity, 0) : 0;
```

Defensive guard ensures no crash even if the controller fix is missed or data is manipulated client-side.

## Other `.reduce()` calls in the same file

| Line | Variable | Fixed? | Notes |
|------|----------|--------|-------|
| 165 | `cartItems` | ✅ | `Array.isArray` guard added |
| 489 | `cartItems.map(...)` | ✅ | Dead code after line 200 `!cartItems?.length` guard, but controller fix prevents object scenario |

## Related Vulnerabilities (not fixed)

- `resources/js/Pages/Storefront/Cart.jsx:343` — `cartItems.reduce(...)` (no optional chaining, no guard)
- `resources/js/Pages/Client/Cart/Index.jsx:385` — `cartItems.reduce(...)` (same pattern)

These pages initialize `cartItems` from props with `|| []` fallback, so null/undefined props are safe. However, if the Inertia prop is an Object (same root cause), they would also crash. These are less likely to be triggered because the Cart controller presumably uses indexed arrays.

## Verification Result

- ✅ Vite build: 2465 modules, 0 errors
- ✅ Storefront tests: 43/43 pass, 182 assertions
- ✅ Controller fix: `array_values($cartItems)` ensures JSON Array serialization
- ✅ Frontend guard: `Array.isArray(cartItems)` prevents runtime crash
- ✅ No checkout logic, payment logic, or tenant logic modified
