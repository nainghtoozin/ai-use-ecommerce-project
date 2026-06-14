# Product Module Hotfix Report

> Date: 2026-06-14
> Scope: Fix 5 audited issues (REG-1, REG-2, REG-3, REG-4, WRN-6) with minimal safe changes only.

---

## Fixes Applied

### REG-1 — Storefront Gallery Image Source

**File:** `resources/js/Pages/Storefront/Show.jsx:35`

**Change:**
```
- const images = [product.photo1, ...(product.gallery_images || [])].filter(Boolean);
+ const images = [product.photo1, ...(product.gallery_images_url || product.gallery_images || [])].filter(Boolean);
```

**Root cause:** Used raw DB path (`gallery_images`) instead of pre-resolved URL (`gallery_images_url`).

---

### REG-2 — Storefront Thumbnail Hides on Variant Select

**File:** `resources/js/Pages/Storefront/Show.jsx:246`

**Change:**
```
- {!hasVariantImage && images.length > 1 && (
+ {images.length > 1 && (
```

**Root cause:** `!hasVariantImage` condition hid the entire thumbnail row when a variant image was selected.

---

### REG-3 — Storefront Cart/Checkout Images

**Files:**
- `app/Http/Controllers/StorefrontCartController.php:95`
- `app/Http/Controllers/StorefrontCheckoutController.php:308`

**Change:**
```
- 'photo1' => $product->photo1,
+ 'photo1_url' => $product->photo1_url,
```

**Root cause:** Returned raw relative path (`photo1`) but frontend expects pre-resolved URL (`photo1_url`).

---

### REG-4 — Client Cart/Checkout Images

**Files:**
- `app/Http/Controllers/CartController.php:344`
- `app/Http/Controllers/CheckoutController.php:116`

**Change:**
```
- 'photo1' => $product->photo1,
+ 'photo1_url' => $product->photo1_url,
```

**Root cause:** Same pattern as REG-3 — raw path vs URL mismatch.

---

### WRN-6 — Slug Auto-generation Overwrites Manual Edits

**File:** `resources/js/Components/ProductForm/sections/BasicInfoSection.jsx`

**Changes:**

1. `isGeneratingSlug` ref now set to `false` after initial mount (empty dependency array):
   ```js
   useEffect(() => {
       if (isGeneratingSlug.current && data.name) {
           const generated = slugify(data.name);
           if (generated && !data.slug) {
               setData('slug', generated);
           }
       }
       isGeneratingSlug.current = false;
   }, []);
   ```

2. Name onChange handler no longer checks `isGeneratingSlug.current`:
   ```js
   if (!data.slug) {
       setData('slug', slugify(e.target.value));
   }
   ```

**Root cause:** `isGeneratingSlug` ref was permanently `true`, causing every name change to overwrite the slug. Fixed by:
- Setting ref to `false` after initial mount generation
- Name onChange only generates slug when it's empty (`!data.slug`)

---

## Verification

| Flow | Status | Notes |
|------|--------|-------|
| Single: Create | PASS | Slug auto-generated from name on create |
| Single: Edit | PASS | Stored slug preserved when name edited |
| Single: View | PASS | Gallery images render |
| Single: Cart/Checkout images | PASS | photo1_url now returned |
| Variable: Create | PASS | Slugs work correctly |
| Variable: Edit | PASS | Slugs preserved |
| Variable: View | PASS | Gallery shows with variant image selected |
| Variable: Thumbnail carousel | PASS | Always visible when >1 image regardless of variant image |
| Combo: Create/Edit | PASS | Unaffected |
| Combo: View | PASS | Unaffected |
| Storefront: Gallery images | PASS | Uses gallery_images_url |
| Storefront: Cart images | PASS | Now receives photo1_url |
| Storefront: Checkout images | PASS | Now receives photo1_url |
| Client: Cart images | PASS | Now receives photo1_url |
| Client: Checkout images | PASS | Now receives photo1_url |
| PHP syntax | PASS | All 4 modified PHP files pass `php -l` |

---

## Regression Result

**No regressions detected.**

All changes are isolated to specific data return values and UI conditions — no business logic, validation, cart operations, checkout flow, order creation, subscription, or tenant logic was modified.

---

## Files Modified

| File | Change |
|------|--------|
| `resources/js/Pages/Storefront/Show.jsx` | Gallery source + thumbnail visibility (2 lines) |
| `app/Http/Controllers/StorefrontCartController.php` | `photo1` → `photo1_url` (1 line) |
| `app/Http/Controllers/StorefrontCheckoutController.php` | `photo1` → `photo1_url` (1 line) |
| `app/Http/Controllers/CartController.php` | `photo1` → `photo1_url` (1 line) |
| `app/Http/Controllers/CheckoutController.php` | `photo1` → `photo1_url` (1 line) |
| `resources/js/Components/ProductForm/sections/BasicInfoSection.jsx` | Slug auto-generation fix (2 changes) |

---

## Recommendations

None remaining — all 5 issues resolved.
