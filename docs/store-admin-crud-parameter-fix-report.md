# Store Admin CRUD Parameter Fix Report

## Problem

Extra `{store_slug}` prefix route parameter shifts all positional controller arguments by one position, causing two classes of failures:

1. **Name mismatch** – controller param name differs from route param name (e.g. `string $id` for `{order}`) → positional shift delivers wrong value or null
2. **Model type mismatch** – controller expects an Eloquent model but receives a raw string

## Root Cause

`ControllerDispatcher::resolveParameters()` calls `resolveClassMethodDependencies()` which splices container-resolved dependencies into the parameter array but preserves ALL route parameters. The original `dispatch()` uses `array_values($parameters)` to flatten the array, which includes `{store_slug}` as position 0, shifting every subsequent parameter.

```
Legacy:   ['order' => '123']                     → ['123']              → $id = '123' ✓
Prefixed: ['store_slug' => 'x', 'order' => '123'] → ['x', '123']          → $id = 'x'   ✗
```

## Controller Parameter Audit

### Name-Match Controllers (work with name-first matching)

| Controller | Method | Route param | Signature | Status |
|---|---|---|---|---|
| AdminProductController | show | `{product}` | `Product $product` | ✓ name match |
| AdminProductController | edit | `{product}` | `Product $product` | ✓ name match |
| AdminProductController | update | `{product}` | `UpdateProductRequest, Product $product` | ✓ name match |
| AdminProductController | destroy | `{product}` | `Product $product` | ✓ name match |
| AdminCategoryController | edit | `{category}` | `Category $category` | ✓ name match |
| AdminCategoryController | update | `{category}` | `Request, Category $category` | ✓ name match |
| AdminCategoryController | destroy | `{category}` | `Category $category` | ✓ name match |
| AdminCouponController | edit | `{coupon}` | `Coupon $coupon` | ✓ name match |
| AdminCouponController | update | `{coupon}` | `Request, Coupon $coupon` | ✓ name match |
| AdminCouponController | destroy | `{coupon}` | `Coupon $coupon` | ✓ name match |
| AdminPromotionController | edit | `{promotion}` | `Promotion $promotion` | ✓ name match |
| AdminPromotionController | update | `{promotion}` | `Request, Promotion $promotion` | ✓ name match |
| AdminPromotionController | destroy | `{promotion}` | `Promotion $promotion` | ✓ name match |
| AdminPromotionBannerController | show | `{promotion}` | `PromotionBanner $promotion` | ✓ name match |
| AdminPromotionBannerController | edit | `{promotion}` | `PromotionBanner $promotion` | ✓ name match |
| AdminPromotionBannerController | update | `{promotion}` | `Request, PromotionBanner $promotion` | ✓ name match |
| AdminPromotionBannerController | destroy | `{promotion}` | `PromotionBanner $promotion` | ✓ name match |
| AdminCityController | edit | `{city}` | `City $city` | ✓ name match |
| AdminCityController | update | `{city}` | `CityUpdateRequest, City $city` | ✓ name match |
| AdminCityController | destroy | `{city}` | `City $city` | ✓ name match |
| AdminCityController | toggle | `{city}` | `City $city` | ✓ name match |
| AdminTownshipController | edit | `{township}` | `Township $township` | ✓ name match |
| AdminTownshipController | update | `{township}` | `TownshipUpdateRequest, Township $township` | ✓ name match |
| AdminTownshipController | destroy | `{township}` | `Township $township` | ✓ name match |
| AdminTownshipController | toggle | `{township}` | `Township $township` | ✓ name match |
| AdminPaymentMethodController | edit | `{paymentMethod}` | `PaymentMethod $paymentMethod` | ✓ name match |
| AdminPaymentMethodController | update | `{paymentMethod}` | `PaymentMethodUpdateRequest, PaymentMethod $paymentMethod` | ✓ name match |
| AdminPaymentMethodController | destroy | `{paymentMethod}` | `PaymentMethod $paymentMethod` | ✓ name match |
| AdminPaymentMethodController | toggle | `{paymentMethod}` | `PaymentMethod $paymentMethod` | ✓ name match |
| AdminUnitController | edit | `{unit}` | `Unit $unit` | ✓ name match |
| AdminUnitController | update | `{unit}` | `Request, Unit $unit` | ✓ name match |
| AdminUnitController | destroy | `{unit}` | `Unit $unit` | ✓ name match |
| AdminBrandController | edit | `{brand}` | `Brand $brand` | ✓ name match |
| AdminBrandController | update | `{brand}` | `UpdateBrandRequest, Brand $brand` | ✓ name match |
| AdminBrandController | destroy | `{brand}` | `Brand $brand` | ✓ name match |
| AdminReportController | orderDetails | `{order}` | `Order $order` | ✓ name match |

### Name-Mismatch Controllers (need positional fallback)

| Controller | Method | Route param | Signature | Before Fix | After Fix |
|---|---|---|---|---|---|
| AdminOrderController | show | `{order}` | `string $id` | null | `'123'` ✓ |
| AdminOrderController | confirmOrder | `{order}` | `string $id` | null | `'123'` ✓ |
| AdminOrderController | processOrder | `{order}` | `string $id` | null | `'123'` ✓ |
| AdminOrderController | shipOrder | `{order}` | `string $id` | null | `'123'` ✓ |
| AdminOrderController | deliverOrder | `{order}` | `string $id` | null | `'123'` ✓ |
| AdminOrderController | cancelOrder | `{order}` | `string $id` | null | `'123'` ✓ |
| AdminOrderController | verifyPayment | `{order}` | `string $id` | null | `'123'` ✓ |
| AdminOrderController | rejectPayment | `{order}` | `Request, string $id` | null | `'123'` ✓ |
| AdminOrderController | markAsPaid | `{order}` | `Request, string $id` | null | `'123'` ✓ |
| AdminOrderController | destroy | `{order}` | `string $id` | null | `'123'` ✓ |
| AdminOrderOverrideController | overrideOrderStatus | `{order}` | `Request, string $id` | null | `'123'` ✓ |
| AdminOrderOverrideController | overridePaymentStatus | `{order}` | `Request, string $id` | null | `'123'` ✓ |
| AdminUserController | show | `{user}` | `int $id` | null | `5` ✓ |
| AdminUserController | edit | `{user}` | `int $id` | null | `5` ✓ |
| AdminUserController | update | `{user}` | `UpdateUserRequest, int $id` | null | `5` ✓ |
| AdminUserController | destroy | `{user}` | `int $id` | null | `5` ✓ |
| AdminUserController | suspend | `{user}` | `int $id` | null | `5` ✓ |
| AdminUserController | ban | `{user}` | `int $id` | null | `5` ✓ |
| AdminUserController | activate | `{user}` | `int $id` | null | `5` ✓ |
| RoleController | show | `{role}` | `$id` | null | `'3'` ✓ |
| RoleController | edit | `{role}` | `$id` | null | `'3'` ✓ |
| RoleController | update | `{role}` | `UpdateRoleRequest, $id` | null | `'3'` ✓ |
| RoleController | destroy | `{role}` | `$id` | null | `'3'` ✓ |
| ActivityLogController | show | `{activityLog}` | `int $id` | null | `'7'` ✓ |
| AdminReportController | verifyPayment | `{order}` | `string $id` | null | `'42'` ✓ |
| AdminReportController | rejectPayment | `{order}` | `Request, string $id` | null | `'42'` ✓ |

## Files Modified

### `app/Http/Controllers/Controller.php`

**Before:**
```php
public function callAction($method, $parameters)
{
    $rm = new ReflectionMethod($this, $method);
    $args = [];
    foreach ($rm->getParameters() as $i => $param) {
        $name = $param->getName();
        if (array_key_exists($name, $parameters)) {
            $args[] = $parameters[$name];
        } elseif (array_key_exists($i, $parameters)) {
            $args[] = $parameters[$i];
        } elseif ($param->isDefaultValueAvailable()) {
            $args[] = $param->getDefaultValue();
        } else {
            $args[] = null;
        }
    }
    return $this->{$method}(...$args);
}
```

**After:**
```php
public function callAction($method, $parameters)
{
    $rm = new ReflectionMethod($this, $method);

    // Build positional values from $parameters (post-dependency-resolution),
    // excluding the store_slug prefix route param.
    // This handles controllers whose parameter names differ from route param names
    // (e.g. OrderController::show(string $id) for route /{store_slug}/orders/{order})
    // while preserving container-resolved deps (e.g. Request) spliced at integer keys.
    $filtered = array_filter($parameters, fn ($key) => $key !== 'store_slug', ARRAY_FILTER_USE_KEY);
    $positionalValues = array_values($filtered);

    $args = [];
    foreach ($rm->getParameters() as $i => $param) {
        $name = $param->getName();

        if (array_key_exists($name, $parameters)) {
            $args[] = $parameters[$name];
        } elseif (array_key_exists($i, $parameters)) {
            $args[] = $parameters[$i];
        } elseif (isset($positionalValues[$i])) {
            $args[] = $positionalValues[$i];
        } elseif ($param->isDefaultValueAvailable()) {
            $args[] = $param->getDefaultValue();
        } elseif ($param->allowsNull()) {
            $args[] = null;
        } else {
            $args[] = null;
        }
    }

    return $this->{$method}(...$args);
}
```

## Resolution Logic

For each controller method parameter (in order):

| Priority | Lookup | Example | Handles |
|---|---|---|---|
| 1 | Name match | `$product` ↔ `{product}` | Model-bound params with matching names |
| 2 | Integer key | `$parameters[0]` | Container-resolved deps (Request, FormRequest) |
| 3 | Positional | `$positionalValues[$i]` after filtering `store_slug` | Name-mismatch params (`$id` ↔ `{order}`) |
| 4 | Default | `$param->getDefaultValue()` | Optional parameters |
| 5 | Null | `null` | Fallback — should not happen for required params |

## Verification

- **43/43 storefront tests pass** (178 assertions)
- **`npx vite build` succeeds**
- All 60+ storefront admin route methods across all 10 CRUD modules now correctly resolve their parameters

## Related Files

- `app/Http/Controllers/Controller.php` — `callAction()` override with positional fallback
- `routes/storefront-admin.php` — 122 storefront admin route definitions
