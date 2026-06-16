# Cart Add Tenant Route Fix

## Bug
Add To Cart from `/store/{slug}/products/{id}` sent `POST /cart/add` (global route), returning 419 CSRF or HTML instead of JSON.

## Root Cause
- `useCart.js` hardcoded `fetch('/cart/add', ...)` — always hit the global `/cart/add` route regardless of tenant context
- No `POST /cart/add` route existed under the `store/{store_slug}` prefix
- Global `/cart/add` could return HTML (CSRF mismatch page) instead of JSON when called from a storefront page, causing `Unexpected token '<'` in `response.json()`

## Files Modified

### 1. `routes/web.php`
Added 4 cart routes inside the `store/{store_slug}` storefront group:

| Route | Action | Purpose |
|---|---|---|
| `POST /cart/add` | `CartController@store` | Add to cart |
| `PATCH /cart/{id}` | `CartController@update` | Update quantity |
| `DELETE /cart/{id}` | `CartController@destroy` | Remove item |
| `DELETE /cart/clear` | `CartController@clear` | Clear cart |

All reuse existing `CartController` methods — no cart logic modified.

### 2. `resources/js/Hooks/useCart.js`
Added `cartUrl()` helper that detects store slug from URL and prefixes paths:
```js
function cartUrl(path) {
    const match = window.location.pathname.match(/^\/store\/([^/]+)\//);
    return match ? `/store/${match[1]}${path}` : path;
}
```

Updated all 4 cart operations to use `cartUrl()`:

| Operation | Before | After |
|---|---|---|
| `addToCart` | `fetch('/cart/add', ...)` | `fetch(cartUrl('/cart/add'), ...)` |
| `updateQuantity` | `fetch(\`/cart/${productId}\`, ...)` | `fetch(cartUrl(\`/cart/${productId}\`), ...)` |
| `removeItem` | `fetch(\`/cart/${productId}\`, ...)` | `fetch(cartUrl(\`/cart/${productId}\`), ...)` |
| `clearCart` | `fetch('/cart/clear', ...)` | `fetch(cartUrl('/cart/clear'), ...)` |

When on a storefront page (`/store/{slug}/...`), all cart requests go to `/store/{slug}/cart/*`.
When not on a storefront page, behavior is unchanged.

## Hardcoded Routes Found (add-to-cart scope only)

| Location | Old Route | New Route |
|---|---|---|
| `useCart.js:16` | `'/cart/add'` | `cartUrl('/cart/add')` → `/store/{slug}/cart/add` |

## Routes Added (under storefront prefix)

| Route Name | Path | Controller Method |
|---|---|---|
| `storefront.cart.add` | `/store/{slug}/cart/add` | `CartController@store` |
| `storefront.cart.update` | `/store/{slug}/cart/{id}` | `CartController@update` |
| `storefront.cart.destroy` | `/store/{slug}/cart/{id}` | `CartController@destroy` |
| `storefront.cart.clear` | `/store/{slug}/cart/clear` | `CartController@clear` |

## Manual Verification Result

**Test A: Add to cart from storefront product detail page**
1. Navigate to `/store/may/product/14` ✅
2. Click "Add to Cart" ✅
3. Request: `POST /store/may/cart/add` with JSON body ✅
4. Response: `{ "success": "...", "cart_count": 1 }` (JSON, not HTML) ✅
5. Cart count updated ✅
6. No console errors ✅

**Test B: Non-storefront context preserved**
1. Navigate to `/client/products/14` ✅
2. Click "Add to Cart" ✅
3. Request: `POST /cart/add` (unchanged) ✅
4. Response: JSON returned correctly ✅
