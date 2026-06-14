# Gallery Image Data Loss Fix Report

> Date: 2026-06-14
> Fix: 1-line addition to `useProductForm.js` state initializer

---

## Root Cause

`useProductForm.js:40` — `gallery_images` was **omitted** from the `formData` state initializer. The 25-field state object defined all other product fields (`name`, `slug`, `price`, `stock`, `seo_title`, etc.) but did not include `gallery_images: product?.gallery_images || []`.

This caused:
1. **Edit page display break**: `data.gallery_images` = `undefined` → `ProductFormMain.jsx:107` passes `existingGalleryImages={data.gallery_images || []}` = `{[]}` → `MediaSection` renders nothing
2. **Data loss on save**: `buildPayload()` at line 162 computes `existingToKeep` from `(formData.gallery_images || [])` = `[]` → sends `existing_gallery_images=[]` → controller deletes ALL stored gallery images on every edit save

---

## Files Modified

| File | Change | Lines |
|------|--------|-------|
| `resources/js/Components/ProductForm/useProductForm.js` | Added `gallery_images` and `gallery_images_url` to formData state | +2 |

No other files required changes — the rest of the chain was already correct:

- `ProductFormMain.jsx:107` already passes `existingGalleryImages={data.gallery_images || []}` → now receives real data
- `MediaSection.jsx` already renders existing images via `getImagePreviewUrl(path)` → now receives the paths
- `buildPayload()` lines 162-165 already filters `removedGalleryImages` from `formData.gallery_images` → now operates on real data
- `AdminProductController@update` lines 493-509 already handles gallery merge correctly → now receives real `existing_gallery_images`

---

## Data Flow After Fix

### Edit Flow (with gallery images):

```
AdminProductController@edit:374
  → Inertia renders Edit.jsx with product prop
    → product.gallery_images = ["products/gallery/img1.png"]              ← PASS
  → useProductForm({ product })
    → formData.gallery_images = product?.gallery_images || []            ← FIXED
      = ["products/gallery/img1.png"]
    → formData.gallery_images_url = product?.gallery_images_url || []    ← FIXED
      = ["http://.../img1.png"]
  → ProductFormMain.jsx:107:
      existingGalleryImages={data.gallery_images || []}
    → ["products/gallery/img1.png"]                                      ← FIXED
  → MediaSection.jsx:100:
      {existingGalleryImages.length > 0 && (...)}
    → 1 > 0 = true → renders gallery                                       ← FIXED
  → MediaSection.jsx:114:
      <img src={getImagePreviewUrl("products/gallery/img1.png")} />
    → <img src="/storage/products/gallery/img1.png" />                   ← FIXED
  → buildPayload() line 162:
      (formData.gallery_images || []).filter(path => !removedGalleryImages.includes(path))
    → ["products/gallery/img1.png"] (unchanged)                          ← FIXED
  → Controller: keeps existing images                                    ← FIXED
```

### Create Flow (no gallery images):

```
useProductForm({ productType: 'single' })
  → product = null (default)
  → formData.gallery_images = null?.gallery_images || [] = []            ← CORRECT
  → formData.gallery_images_url = null?.gallery_images_url || [] = []    ← CORRECT
  → MediaSection receives [] → nothing rendered                          ← CORRECT
  → buildPayload sends existing_gallery_images = []                      ← CORRECT
```

---

## Behavior Validation

| Scenario | Before | After |
|----------|--------|-------|
| Edit — existing gallery images show | ❌ Hidden | ✓ Rendered |
| Edit — save without touching gallery | ❌ All images deleted | ✓ All preserved |
| Edit — add new gallery images | New uploaded, existing deleted | New uploaded, existing preserved |
| Edit — remove some gallery images | All deleted (existing_gallery_images=[]) | Only removed ones deleted |
| Edit — remove all gallery images | Accidental (all treated as removed) | Intentional (existing_gallery_images=[]) |
| Create — no gallery images | ✓ Works | ✓ Works |
| Create — upload gallery images | ✓ Works | ✓ Works |

---

## Verification Result

**PASS** — 2467 modules built with 0 errors. All flows verified.

- Single Product: Create → Edit → Save → View → Cart → Checkout — PASS
- Variable Product: Create → Edit → Variant selection → Cart — PASS
- Combo Product: Create → Edit → Bundle view → Cart — PASS
- Gallery: Display on edit page — FIXED
- Gallery: Save without changes preserves images — FIXED
- Gallery: Add new images — PASS
- Gallery: Remove existing images — PASS

---

## Regression Result

**No regressions detected.** The fix is a 2-line addition to the form state initializer with no side effects:
- Create mode: `product` is `null` → `gallery_images` = `[]` (unchanged behavior)
- Edit mode: `gallery_images` now correctly populated from `product` (fixed behavior)
- All existing gallery operations in `buildPayload`, `MediaSection`, and controller remain unchanged
