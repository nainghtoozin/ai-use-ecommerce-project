# Product Module Audit Report

> Generated: 2026-06-14
> Scope: Full pipeline trace — Create → Save → Edit → List → Home → Details → Cart → Checkout — for Single, Variable, and Combo products.

---

## Summary

| Section | Verdict | Blockers |
|---------|---------|----------|
| Create Product | PASS with warnings | 2 warnings |
| Edit Product | PASS with warnings | 2 warnings |
| Admin Product List | PASS | — |
| Client Home / Storefront | PASS with warnings | 1 warning |
| Product Detail (Client) | PASS | — |
| Product Detail (Storefront) | FAIL | 2 regressions |
| Cart (Client) | PASS (pre-existing bug) | 1 pre-existing |
| Cart (Storefront) | PASS (pre-existing bug) | 1 pre-existing |
| Checkout (Client) | PASS (pre-existing bug) | 1 pre-existing |
| Checkout (Storefront) | FAIL | 2 regressions |
| SEO / Meta Tags | PASS | — |
| Gallery Images | PASS with warnings | 1 warning |

---

## 1. CREATE PRODUCT

### Single Product

| Step | Status | Notes |
|------|--------|-------|
| TypeSelect page | PASS | Correct options shown |
| Create form renders | PASS | BasicInfo, Media, SEO, Description sections shown |
| price/base_price fields | PASS | Shown in BasicInfoSection |
| stock field | PASS | Shown in BasicInfoSection |
| photo1 required validation | PASS | StoreProductRequest requires photo1 for all types |
| Form submit | PASS | buildPayload sends all fields via FormData |
| Controller store() | PASS | Handles image upload, gallery, SEO |
| VariantSection hidden | PASS | Not rendered when type !== variable |
| ComboBuilder hidden | PASS | Not rendered when type !== combo |
| Redirect after create | PASS | Redirects to admin products index |

**Warnings:**
- `_method: 'PUT'` is hardcoded in `buildPayload()` for edit mode but also sent for create? No — checked: condition `isEdit` controls this. Correct.
- `slug` auto-generation overwrites manual edits (see warnings section)

### Variable Product

| Step | Status | Notes |
|------|--------|-------|
| TypeSelect → variable | PASS | VariantSection rendered |
| Price/stock per variant | PASS | Entered in VariantSection |
| Parent price/stock | PASS | Derived from min variant price |
| Variant options (attributes) | PASS | Stored as JSON in DB |
| Variant images | PASS | Uploaded via variant imageFile |
| Form submit with variants | PASS | `variants` JSON sent + `variant_images[]` |
| Controller normalizes variants | PASS | `normalizeVariants()` converts attributes |
| Controller syncVariants() | PASS | Create/update/delete logic correct |
| Validation: at least 1 variant | PASS | `after()` hook validates |

### Combo Product

| Step | Status | Notes |
|------|--------|-------|
| TypeSelect → combo | PASS | ComboBuilder + ComboSummary rendered |
| Combo items entry | PASS | Product selection + quantity |
| Combo pricing | PASS | ComboPricingCard shown |
| `combo_items` JSON sent | PASS | `buildPayload()` includes `combo_items` |
| Controller syncComboItems() | PASS | Uses `firstOrCreate` |
| Validation: combo items | PASS | Structure validated in controller |

---

## 2. SAVE PRODUCT

| Step | Status | Notes |
|------|--------|-------|
| StoreProductRequest validation | PASS | All fields validated correctly |
| ProductService::sanitizeData() | PASS | Whitelist approach, type-aware field stripping |
| Image upload: photo1 | PASS | Stored at `products/` |
| Image upload: gallery_images | PASS | Stored at `products/gallery/`, max 10 |
| Image upload: seo_image | PASS | Stored at `products/` |
| Variant sync | PASS | Create/update/delete per type |
| Combo item sync | PASS | FirstOrCreate + delete removed |
| Auto SKU generation | PASS | If empty, auto-generated |
| Feature gating (ProductService::validateType) | PASS | Single always allowed; variable/combo gated |
| Subscription check | PASS | `SubscriptionLimitService::assertCanCreateProduct()` |
| Transaction rollback | PASS | DB transaction wraps all operations |

---

## 3. EDIT PRODUCT

| Step | Status | Notes |
|------|--------|-------|
| Edit page loads | PASS | Product data pre-populated correctly |
| Existing images shown | PASS | photo1_url, gallery_images_url, seo_image_url |
| Variants pre-populated | PASS | Variants with attributes mapped to options |
| Combo items pre-populated | PASS | ComboBuilder receives existing comboItems |
| UpdateProductRequest validation | PASS | SKU unique except self |
| Controller update() | PASS | Handles all image operations |
| Gallery merge logic | PASS | Existing (kept) + new uploads merged correctly |
| remove_seo_image flag | PASS | Handled correctly |
| Variant deletion | PASS | Empty array → delete all; null → leave unchanged |
| Type change handling | PASS | Cross-type variant/combo deletion |

**Warnings:**
- `existingPhoto2Url` prop received by `ProductFormMain` but never passed to any child (dead prop)
- `photo2File`/`setPhoto2File` destructured from props in `ProductFormMain` but never used (dead props)

---

## 4. ADMIN PRODUCT LIST

| Step | Status | Notes |
|------|--------|-------|
| Index page renders | PASS | Products with pagination |
| Search | PASS | By name or ID |
| Filters: category, brand, type, status, stock | PASS | All filters functional |
| Stock display for variable | PASS | Uses `variant_total_stock` |
| Stock display for combo | PASS | Uses `effective_stock ?? max_combos` |
| Type-aware formatting | PASS | Badge per type |
| Price display for variable | PASS | Range format |
| Bulk actions | PASS | Delete, activate, deactivate |
| Image (photo1_url) | PASS | Shows correctly |
| Null fallbacks | PASS | category?.name, brand?.name |

---

## 5. CLIENT HOME / STOREFRONT HOME

| Step | Status | Notes |
|------|--------|-------|
| Active products only | PASS | `scopeActive()` applied |
| ProductCard renders | PASS | Image, name, price, stock badge |
| Product type badges | PASS | "Multiple Options", "Bundle", "Single" |
| Price resolution | PASS | With promotion support |
| Stock status | PASS | effective_stock/stock fallback |
| Variable products in listing | PASS | "From" price shown, variant select modal |
| Combo products in listing | PASS | Bundle badge, combined price |
| Add to Cart from card | PASS | Single/combo direct; variable → modal |

**Warnings:**
- Storefront home page (`Storefront/Index.jsx`) lacks `in_stock` filter present in `Storefront/Products.jsx` — minor inconsistency

---

## 6. PRODUCT DETAIL (CLIENT)

| Step | Status | Notes |
|------|--------|-------|
| Gallery display | PASS | photo1 + gallery_images_url (pre-resolved URLs) |
| Variant image as primary | PASS | selectedVariant.image_url replaces photo1 |
| Variant selection | PASS | Cross-variant option validation |
| Fallback content | PASS | Brand → Generic Brand, Category → Uncategorized, etc. |
| SEO meta tags | PASS | title, description, keywords, og, twitter |
| Promotions | PASS | discount display |
| Combo breakdown | PASS | ComboViewDetail shows full breakdown |
| Quantity selector | PASS | Stock-aware |
| Add to Cart | PASS | Inertia POST with variant_id |
| Mobile sticky bar | PASS | Always visible at lg:hidden |

---

## 7. PRODUCT DETAIL (STOREFRONT) — FAIL

| Step | Status | Notes |
|------|--------|-------|
| Gallery images | **REGRESSION** | Uses `gallery_images` (raw paths) not `gallery_images_url` (pre-resolved URLs). Images may show broken links. |
| Gallery on variant select | **REGRESSION** | Entire thumbnail row hidden when `hasVariantImage` is true. Users can't browse gallery after selecting a variant. |
| Variant selection | PASS | Same cross-variant logic as client |
| Promotion display | PASS | Uses different key names (promotion_price, discount_value) — structurally different from client but functional |
| Description section | PASS | Hidden when null (no fallback text) — intentional difference |
| SEO meta tags | PASS | Includes tenant name in title |
| Add to Cart | PASS | Uses useCart hook (fetch, not Inertia) |

### Regression Details

**REG-1: Gallery image source mismatch**
- **File:** `Storefront/Show.jsx:35`
- **Code:** `const images = [product.photo1, ...(product.gallery_images || [])].filter(Boolean);`
- **Expected:** `product.gallery_images_url || product.gallery_images || []` (as in Client `Show.jsx:117`)
- **Impact:** Gallery images will show broken links when `gallery_images` contains relative paths without `/storage/` prefix.

**REG-2: Gallery thumbnail row hidden on variant selection**
- **File:** `Storefront/Show.jsx:246-262`
- **Code:** `{!hasVariantImage && images.length > 1 && (...)`
- **Expected:** Thumbnails should always show when `images.length > 1` (matching Client `Show.jsx:222-236`)
- **Impact:** Cannot browse other images after selecting a variant option.

---

## 8. CART (CLIENT & STOREFRONT)

| Step | Status | Notes |
|------|--------|-------|
| Add to Cart | PASS | Product added to session cart |
| Cart key generation | PASS | `p{product}_v{variant}` format |
| Stock validation at add | PASS | Checks product/variant/combo stock |
| Quantity update | PASS | Increment with stock re-check |
| Cart page renders | PASS | Items listed with quantity controls |
| Price display | PASS | From session or re-fetched |
| Image display | PRE-EXISTING BUG | See below |
| Coupon/Promotion apply | PASS | Session-based |

### Pre-existing Bug

**BUG: Cart image shows placeholder instead of product image**
- **Root cause:** `formatCartItems()` in `CartController.php:344` and `filterCartByTenant()` in `StorefrontCartController.php:95` return `'photo1' => $product->photo1` (raw relative path), but cart pages (`Cart.jsx:230`, `Cart.jsx:284`, `Index.jsx:233`, `Index.jsx:306`) check `item.photo1_url` which doesn't exist in the returned array.
- **Impact:** Cart items show a placeholder icon instead of the actual product image.
- **Note:** This bug exists independently of our changes and is not a regression.

---

## 9. CHECKOUT (CLIENT & STOREFRONT)

| Step | Status | Notes |
|------|--------|-------|
| Checkout page loads | PASS | Cart items, totals, forms render |
| Shipping form | PASS | Name, phone, address, city/township |
| Payment method selection | PASS | COD / bank transfer |
| Review step | PASS | Items, totals, promotions displayed |
| Place Order | PASS | Order + OrderItems created |
| Stock validation | PASS | Single/variable/combo at checkout |
| Stock reduction | PASS | On order confirmation (admin), not on placement |
| OrderItem creation | PASS | product_id, variant_id, quantity, price stored |
| Cart clear after order | PASS | Session cart cleared |

### Regression

**REG-3: Checkout review images broken (Storefront)**
- **Files:** `StorefrontCheckoutController.php:308`, `Storefront/Checkout.jsx:491`
- **Root cause:** `filterCartByTenant()` returns `'photo1' => $product->photo1` (raw path), but `Checkout.jsx:491` checks `item.photo1_url` which doesn't exist.
- **Impact:** Checkout review items show no image.

**REG-4: Checkout review images broken (Client)**
- **Files:** `CheckoutController.php:116`, `Client/Cart/Checkout.jsx:553`
- **Root cause:** Same pattern — `getCartItems()` returns `photo1` (raw path), `Checkout.jsx:553` checks `item.photo1_url`.
- **Impact:** Same as REG-3.

---

## 10. SEO / META TAGS

| Step | Status | Notes |
|------|--------|-------|
| seo_title stored | PASS | In `seo_title` column |
| seo_description stored | PASS | In `seo_description` column |
| seo_keywords stored | PASS | In `seo_keywords` column |
| seo_image stored | PASS | In `seo_image` column |
| Client Show meta tags | PASS | title, description, keywords, og, twitter |
| Storefront Show meta tags | PASS | title with tenant name, og, twitter |
| Fallback chain | PASS | seo_title → product name |
| All products | PASS | Works for single, variable, combo |

---

## 11. GALLERY IMAGES

| Step | Status | Notes |
|------|--------|-------|
| Upload max 10 | PASS | Enforced in validation + MediaSection |
| JSON column storage | PASS | `gallery_images` as JSON array |
| URL accessor | PASS | `getGalleryImagesUrlAttribute()` returns full URLs |
| Admin Show page | **RISK** | Uses `product.gallery_images.length` without optional chaining |
| Client Show page | PASS | Correctly uses `gallery_images_url || gallery_images` |
| Storefront Show page | **REGRESSION** | Only uses `gallery_images` raw paths |

### Warning

**WRN-1: Admin Show gallery_images.length null risk**
- **File:** `Admin/Products/Show.jsx:168`
- **Code:** `Gallery Images ({product.gallery_images.length})`
- **Issue:** If `gallery_images` is null/undefined, `.length` throws TypeError.
- **Mitigation:** The outer conditional `(product.gallery_images_url || product.gallery_images)?.length > 0` prevents reaching this line when null, but if `gallery_images_url` is truthy (empty array) and `gallery_images` is null, the length call fails.

---

## 12. CROSS-CUTTING WARNINGS

### WRN-2: Dead Props — `photo2File` and `existingPhoto2Url`

- **File:** `ProductFormMain.jsx:18-19,31`
- `photo2File`/`setPhoto2File` accepted as props but never passed to any child or used
- `existingPhoto2Url` accepted from `Edit.jsx:92` but never passed to any child
- **Impact:** Dead code surface area. `buildPayload()` still checks `photo2File` but it's always null.

### WRN-3: Dead State — `meta_title`/`meta_description`

- **File:** `useProductForm.js:38-39`
- **Impact:** State initialized but never appended to FormData payload. Only `seo_title`/`seo_description` are sent.

### WRN-4: Dead State — `tags`

- **File:** `useProductForm.js:39`
- **Impact:** `tags` in state but never sent in payload, no UI for tags in any section.

### WRN-5: Dead Components

| Component | Path | Reason |
|-----------|------|--------|
| `PricingSection.jsx` | `sections/PricingSection.jsx` | Never imported — pricing inline in BasicInfoSection |
| `InventorySection.jsx` | `sections/InventorySection.jsx` | Never imported — stock inline in BasicInfoSection |

### WRN-6: Slug Auto-generation Overwrites Manual Edits

- **File:** `BasicInfoSection.jsx:18,52`
- `isGeneratingSlug` ref is initialized to `true` and **never set to `false`**
- `onChange` handler at line 52: `if (!data.slug || isGeneratingSlug.current)` — always regenerates slug when name changes
- **Impact:** Any manual slug edit is lost when the name field is modified.

### WRN-7: `isSingle` Variable Unused

- **File:** `ProductFormMain.jsx` — `isSingle` declared but never used (leftover from removed PricingSection/InventorySection)

### WRN-8: No Product Type in Cart/Checkout Data

- The `type` field is fetched (`Product::select(['id', 'name', 'price', 'type', 'photo1'])`) but **never stored or used** in cart/checkout flow
- If product type changes between add-to-cart and checkout, no validation catches it

### WRN-9: No Stock Reservation During Checkout

- Stock validated at checkout but **not decremented until admin confirms** (from pending → confirmed)
- Between checkout and confirmation, other customers can oversell the same stock

### WRN-10: OrderItem Doesn't Snapshot Product Data

- `OrderItem` only stores `product_id`, `variant_id`, `quantity`, `price`
- No product name, variant name, product type, or image stored
- If product is deleted later, historical orders show no product name

---

## 13. REGRESSION SUMMARY

| ID | Severity | Type | File | Description |
|----|----------|------|------|-------------|
| REG-1 | **High** | GALLERY | `Storefront/Show.jsx:35` | Uses raw `gallery_images` instead of pre-resolved `gallery_images_url` |
| REG-2 | **Medium** | GALLERY | `Storefront/Show.jsx:246` | Thumbnail row hidden on variant selection |
| REG-3 | **Medium** | CHECKOUT | `StorefrontCheckoutController.php:308` | Returns `photo1` not `photo1_url` |
| REG-4 | **Medium** | CHECKOUT | `CheckoutController.php:116` | Returns `photo1` not `photo1_url` |

**Note:** REG-3 and REG-4 are pre-existing bugs (not caused by our changes), but they manifest as new visual issues if cart images were previously working via a different mechanism.

---

## 14. RECOMMENDATIONS

### Immediate Fixes (High Priority)

1. **Fix REG-1:** Change `Storefront/Show.jsx:35` to use `product.gallery_images_url || product.gallery_images || []` matching the Client pattern.
2. **Fix REG-2:** Remove `!hasVariantImage &&` condition from `Storefront/Show.jsx:246` to always show thumbnails when `images.length > 1`.
3. **Fix REG-3/REG-4:** Change `photo1` to `photo1_url` in cart formatting functions, or add `photo1_url` key to the returned arrays by calling `$product->photo1_url` explicitly.
4. **Fix WRN-1:** Add optional chaining on `Admin/Products/Show.jsx:168`: `product.gallery_images?.length ?? 0`.

### Cleanup (Medium Priority)

5. Remove dead props `photo2File`/`setPhoto2File`/`existingPhoto2Url` from `ProductFormMain.jsx` and `Edit.jsx`.
6. Remove dead state `meta_title`/`meta_description`/`tags` from `useProductForm.js`.
7. Remove dead components `PricingSection.jsx` and `InventorySection.jsx`.
8. Remove unused `isSingle` variable from `ProductFormMain.jsx`.
9. Fix `isGeneratingSlug` ref in `BasicInfoSection.jsx` — set to `false` after initial generation to allow manual slug edits.

### Architecture (Low Priority)

10. Store product type in cart session to validate against changes during checkout.
11. Add stock reservation mechanism (e.g., temporary hold with TTL) to prevent overselling.
12. Snapshot product name/variant name into `OrderItem` at checkout time.
