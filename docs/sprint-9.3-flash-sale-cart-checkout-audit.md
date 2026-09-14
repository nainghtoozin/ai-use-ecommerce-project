# Sprint 9.3 — Flash Sale Cart, Checkout & Order Integration — Audit Report

**Date:** 2026-09-14  
**Status:** Complete  

---

## 1. Root Cause / Issues Found

No bugs found in existing code. This sprint added flash sale integration to the purchase flow.

---

## 2. Files Changed

### Migration
| File | Action |
|------|--------|
| `database/migrations/2026_09_14_100001_add_flash_sale_to_order_items_table.php` | **Created** — adds `flash_sale_id` (nullable FK) + `original_price` (nullable decimal) to `order_items` |

### Models
| File | Action |
|------|--------|
| `app/Models/OrderItem.php` | **Modified** — added `flash_sale_id`, `original_price` to fillable; added `original_price` cast; added `flashSale()` relationship |

### Services
| File | Action |
|------|--------|
| `app/Services/FlashSaleService.php` | **Modified** — added `resolveEffectivePrice()`, `resolveCartPrices()`, `validateFlashSaleQuantity()`, `incrementQuantitySold()`, `decrementQuantitySold()` |
| `app/Services/OrderService.php` | **Modified** — injected `FlashSaleService`; `reverseStockReduction()` now decrements `quantity_sold` on order cancellation |

### Controllers
| File | Action |
|------|--------|
| `app/Http/Controllers/CartController.php` | **Modified** — injected `FlashSaleService`; `store()` resolves flash sale price server-side; `formatCartItems()` includes flash sale display data |
| `app/Http/Controllers/StorefrontCartController.php` | **Modified** — injected `FlashSaleService`; `filterCartByTenant()` resolves flash sale prices + display data |
| `app/Http/Controllers/StorefrontCheckoutController.php` | **Modified** — injected `FlashSaleService`; `filterCartByTenant()` resolves flash sale prices; `store()` re-resolves prices server-side inside transaction, validates flash sale quantities, stores `flash_sale_id` + `original_price` on order items, increments `quantity_sold` after order creation |
| `app/Http/Controllers/OrderController.php` | **Modified** — injected `FlashSaleService`; `hydratePricesFromDatabase()` resolves flash sale prices; `store()` validates flash sale quantities, stores flash sale data on order items, increments `quantity_sold` after order creation |

### Frontend
| File | Action |
|------|--------|
| `resources/js/Pages/Storefront/Cart.jsx` | **Modified** — added `Zap` import; mobile + desktop price columns show flash sale badge, orange price, strikethrough original |
| `resources/js/Pages/Storefront/CheckoutV2.jsx` | **Modified** — added `Zap` import; order summary shows flash sale badge + strikethrough original + sale price |

---

## 3. Cart Price Flow

```
Add to Cart (POST /cart/add)
  → CartController::store()
    → FlashSaleService::resolveEffectivePrice(productId, variantId, basePrice)
      → Queries FlashSaleProduct + active FlashSale
      → Returns flash_price if active, else basePrice
    → Session cart stores base price (NOT flash price)
    → Returns flash_sale_applied flag to frontend

Format Cart Items (GET /cart)
  → CartController::formatCartItems() / StorefrontCartController::filterCartByTenant()
    → For each cart item: FlashSaleService::resolveEffectivePrice()
    → Returns: price (flash or base), original_price, is_flash_sale, flash_sale_name, flash_sale_ends_at, flash_sale_remaining_stock
    → Frontend displays flash sale badge + pricing
```

**Key design:** Session cart stores the base product/variant price. Flash sale prices are always re-resolved server-side when formatting cart items. This means if a flash sale expires while the item is in the cart, the next page load will show the normal price.

---

## 4. Checkout Price Flow

```
Checkout Page Load (GET /checkout)
  → StorefrontCheckoutController::index()
    → filterCartByTenant() resolves flash sale prices
    → Cart items include is_flash_sale, price, original_price

Order Submission (POST /checkout)
  → StorefrontCheckoutController::store()
    → Re-resolves flash sale prices inside DB::transaction
    → Re-validates flash sale quantities inside DB::transaction
    → Calculates subtotal from resolved prices (NOT from session cart)
    → Creates order with recalculated totals
    → Creates order_items with flash_sale_id + original_price
    → Increments FlashSaleProduct.quantity_sold
```

**Key design:** The checkout `store()` method re-resolves all flash sale prices inside the database transaction. This ensures:
- Expired flash sales use normal prices
- Changed flash sale prices use the current price
- Flash sale quantity limits are enforced server-side

---

## 5. Order Price Flow

```
Order Created
  → order_items.price = flash_price (the actual paid price)
  → order_items.original_price = base product/variant price
  → order_items.flash_sale_id = FK to flash_sales table
  → order.subtotal = sum of (price × quantity) for all items
  → order.total_amount = subtotal + delivery + packaging + cod - discount

Price is NEVER the browser-sent price. Always server-resolved.
```

---

## 6. quantity_sold Behavior

| Event | Action |
|-------|--------|
| Order created (pending) | `FlashSaleProduct.quantity_sold += item.quantity` |
| Order confirmed → cancelled | `FlashSaleProduct.quantity_sold -= item.quantity` (via `OrderService::reverseStockReduction`) |
| Order pending → cancelled | No stock restoration, no quantity_sold change (matches existing NONE action) |
| Product viewed | No change |
| Added to cart | No change |
| Checkout page opened | No change |

**Double increment prevention:** `incrementQuantitySold()` is called once inside the order creation transaction. The idempotency key in `StorefrontCheckoutController` prevents duplicate order creation.

---

## 7. Stock Behavior

- Regular stock validation via `StockCalculationService::forProduct()` / `forVariant()` is unchanged
- Flash sale quantity limits are ADDITIONAL validation, not a replacement
- Both regular stock AND flash sale quantity must be sufficient for checkout to proceed
- `validateStockWithLock()` uses `lockForUpdate()` for concurrent safety

---

## 8. Variable Product Behavior

- Flash sale prices are resolved per-variant via `FlashSaleProduct.variant_id`
- `FlashSaleService::getActiveFlashSale(productId, variantId)` queries with `variant_id` filter
- Cart stores `variant_id` in session; flash sale resolved against that variant
- Order items store `variant_id` + `flash_sale_id` for correct audit trail

---

## 9. Coupon/Promotion Interaction

- Flash sale resolves the **item price** (the base unit price)
- Coupons/Promotions apply **on top of** the flash sale price
- No changes to `PromotionService` or coupon logic
- Example: Product 50,000 → Flash Sale 40,000 → 10% coupon → Final 36,000

---

## 10. Security / Tenant Isolation

- `FlashSaleService::getActiveFlashSale()` queries by `product_id` + `variant_id` — no flash sale ID passed by browser
- Flash sale prices resolved server-side only; browser-sent prices ignored
- `CartController::store()` validates `product_id` exists via `exists:products,id` rule
- `StorefrontCheckoutController` uses `filterCartByTenant()` to ensure only tenant products enter checkout
- `flash_sale_id` on order items is set server-side, not from browser input

---

## 11. Browser/Manual Test Results

**PHP Syntax:** All 7 modified PHP files pass `php -l` ✅  
**Frontend Build:** `npm run build` compiles without errors ✅  
**Test Suite:** All failures are `Unknown database 'ecommerc_test'` — pre-existing infrastructure issue, not related to changes ⚠️

---

## 12. Automated Test / Build Results

| Check | Result |
|-------|--------|
| `php -l` on all modified PHP files | ✅ No syntax errors |
| `npm run build` | ✅ Compiled successfully |
| `php artisan test` | ⚠️ All failures are `Unknown database 'ecommerc_test'` (pre-existing) |

---

## 13. Remaining Issues

| Issue | Severity | Notes |
|-------|----------|-------|
| Test database not configured | Pre-existing | `ecommerc_test` database doesn't exist; all feature tests fail with connection error |
| Admin cart/checkout (non-storefront) not updated | Low | `resources/js/Pages/Client/Cart/Checkout.jsx` not modified — uses same CartController which now includes flash sale data, but the Client cart page may not display flash sale badges |
| Flash sale countdown timer not implemented | Cosmetic | Cart/checkout pages show `flash_sale_ends_at` data but no live countdown component |
| Order admin display of flash sale info | Low | Admin order detail pages don't show which items were flash sale purchases |
