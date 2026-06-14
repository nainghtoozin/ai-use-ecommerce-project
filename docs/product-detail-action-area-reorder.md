# Product Detail Page Action Area Reorder

## Goal
Reorder Product Detail Page sections so that the action area (variant options, quantity selector, add to cart) appears above Product Description and Product Specifications — putting purchase actions before reading content.

## Changes

### Client Show.jsx (`resources/js/Pages/Client/Products/Show.jsx`)
**Before** (right column order):
1. Product Information Card
2. Short Description
3. Product Specifications
4. Product Description
5. Variable Options / Bundle Includes
6. Add to Cart

**After** (right column order):
1. Product Information Card
2. Short Description
3. **Variable Options / Bundle Includes** ← moved up
4. **Add to Cart** ← moved up
5. Product Description ← moved down
6. Product Specifications ← moved down

### Storefront Show.jsx (`resources/js/Pages/Storefront/Show.jsx`)
**Before** (right column order):
1. Product Information Card
2. Short Description
3. Product Specifications
4. Product Description
5. Variant Options / Combo Includes / Add to Cart (wrapped)

**After** (right column order):
1. Product Information Card
2. Short Description
3. **Variant Options / Combo Includes / Add to Cart** ← moved up
4. Product Description ← moved down
5. Product Specifications ← moved down

## New Layout (both pages)

```
Product Information Card  (category, brand, name, price, stock)
Short Description
─────────────────────────────────
Variant Options / Bundle Includes
Selected Variant Summary (variable only)
Quantity Selector
[Add to Cart Button]
─────────────────────────────────
Product Description
Product Specifications
─────────────────────────────────
Bundle Details (combo only, below)
```

## What Was NOT Modified
- Product logic (is_variable, is_combo checks)
- Variant selection logic (optionKeys, optionValues, selectedVariant)
- Bundle selection logic (combo_summary)
- Cart payload / Add to Cart handlers
- Stock logic (availableStock, stockStatus)
- Pricing logic (displayPrice, originalPrice, promotions)
- SEO meta tags
- Sticky Mobile Add to Cart bar
- Combo View Detail section (below main area)
- Gallery section

## Verification
- Build: 2467 modules transformed, 0 errors
- No business logic files touched — only UI section reordering
- All product types (Single, Variable, Combo) maintain correct section ordering per specification
