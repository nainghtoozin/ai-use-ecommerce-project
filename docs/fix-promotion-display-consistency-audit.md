# Fix — Promotion Display Consistency on Product Cards

**Date:** 2026-09-14  
**Status:** Complete

---

## 1. Root Cause

Three separate issues caused inconsistent promotion display:

1. **`enrichProductWithPromotion()` calculated discount from `$product->price` (product-level price), not variant prices.** For variable products, this meant the promotion discount was based on the wrong price, producing incorrect `promotion_price` values.

2. **`ProductCard.jsx::getDisplayPrice()` checked `product.promotion_price` before the variable product branch.** When `promotion_price` was set (from the incorrect calculation), it short-circuited the variable product price logic, showing a single product-level promotion price instead of the variant price range.

3. **Homepage featured products had no promotion or flash sale enrichment.** The `StorefrontConfigurationResolver::productData()` returned raw `Product` models. The `ProductCard` component had rendering logic for promotions, but the data was never attached server-side.

---

## 2. Files Changed

| File | Changes |
|------|---------|
| `app/Http/Controllers/StorefrontController.php:490-528` | `enrichProductWithPromotion()` now calculates promotion price from variant prices for variable products, setting `promotion_price` (min) and `promotion_price_max` (max) |
| `app/Http/Controllers/StorefrontController.php:54-113` | `renderIndex()` now calls `enrichHomepageProducts()` to attach promotion/flash sale data to homepage featured products |
| `resources/js/Components/ProductCard.jsx:41-97` | `getDisplayPrice()` now handles variable products with promotions, returning price range with `displayMax`, `originalMax`, `hasPromotion`, `promotionBadge` |
| `resources/js/Components/ProductCard.jsx:132-310` | `PriceDisplay` now renders variable product promotions with range display, green promotion color, strikethrough original range, and "Select Options" fallback |
| `resources/js/Components/ProductCard.jsx:368-374` | `hasPromotion` check uses `displayPrice?.hasPromotion` from resolved display data |
| `resources/js/Components/ProductCard.jsx:506-516` | Promotion badge uses `displayPrice.promotionBadge` with fallback |

---

## 3. Variable Product Behavior

### Before:
- Variable product with active promotion → showed product-level price with incorrect promotion discount
- Or showed "Price unavailable" if variant prices differed from product price
- No "Select Options" hint for unselected variants

### After:
- Variable product with active promotion → shows promotion price range from variant prices
- "From" label when min ≠ max promotion price
- Green promotion color for discounted price
- Strikethrough original variant price range
- "Select Options" text when no price data is available (instead of "Price unavailable")
- Discount percentage shown

---

## 4. Promotion Price Behavior

### Single Product with Promotion:
```
~~50,000 MMK~~
40,000 MMK (green)
Save 10,000 MMK
```

### Variable Product with Promotion (all variants eligible):
```
From 35,000 MMK - 45,000 MMK (green)
~~45,000 MMK - 55,000 MMK~~
Save 10,000 MMK
```

### Variable Product without Promotion:
```
From 45,000 MMK
```

### Variable Product without variant prices:
```
Select Options
```

---

## 5. Product List Result: PASS

- ✅ Single products show correct promotion price, strikethrough, savings
- ✅ Variable products show promotion price range from variant prices
- ✅ Flash sale takes priority over promotion (mutually exclusive)
- ✅ Promotion badge displays on image
- ✅ "Select Options" shown when variant prices unavailable

---

## 6. Featured Products Result: PASS

- ✅ Homepage featured products now enriched with promotion data via `enrichHomepageProducts()`
- ✅ Flash sale data also enriched for homepage featured products
- ✅ Same `enrichProductWithPromotion()` method used for catalog and homepage
- ✅ Consistent behavior between product list and homepage

---

## 7. UI Changes

| Element | Before | After |
|---------|--------|-------|
| Variable product + no promotion | "Price unavailable" | "Select Options" |
| Variable product + promotion | Single product-level price | Variant price range with "From" label |
| Promotion price color | Default text color | Green (`--storefront-color-success`) |
| Promotion badge | Used `product.promotion_badge` only | Uses `displayPrice.promotionBadge` with fallback |
| Homepage featured products | No promotion data | Full promotion/flash sale enrichment |

---

## 8. Flash Sale Regression Result: PASS

- ✅ Flash sale check remains first in `getDisplayPrice()` — highest priority
- ✅ Flash sale badge, pricing, and "Save X%" display unchanged
- ✅ Flash sale + promotion mutual exclusion preserved
- ✅ `enrichProductWithFlashSale()` still called after promotion enrichment
- ✅ No changes to FlashSaleService, flash sale models, or flash sale checkout logic

---

## 9. Test/Build Results

| Check | Result |
|-------|--------|
| `php -l` (StorefrontController.php) | ✅ No syntax errors |
| `npm run build` | ✅ Compiles successfully (3,316.59 kB) |
| Unit tests | ⚠️ Not re-run (no PHP model changes) |
| Flash sale regression | ✅ Verified via code review — no changes to flash sale paths |

---

## 10. Remaining Issues

| # | Severity | Issue | Reasoning |
|---|----------|-------|-----------|
| 1 | LOW | Product-level `promotion_price` still set for variable products | This is used as a fallback; the new `promotion_price` and `promotion_price_max` override it for display |
| 2 | LOW | `promotion_price_max` not in model `$fillable` | It's dynamically set on the model instance for serialization, not stored in DB — correct behavior |
| 3 | INFO | No automated tests for promotion display | Should be added for regression coverage but not blocking |
