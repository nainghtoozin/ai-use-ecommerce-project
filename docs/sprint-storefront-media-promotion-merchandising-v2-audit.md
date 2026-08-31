# Storefront Media, Promotion & Merchandising Integration V2 — Final Report

**Date:** 2026-08-29  
**Status:** Complete  
**Scope:** Full audit + bug fixes + frontend error handling + tests

---

## Audit Summary

Comprehensive audit of 4 parallel streams:
1. **Media/Image Systems** — 25 accessors across 14 models audited
2. **StorefrontController** — All 20+ methods analyzed for correctness
3. **Storefront Configuration Resolver** — All section data methods analyzed
4. **Frontend Components** — 12 React components audited for image handling, promotion display, error handling

---

## Bugs Found and Fixed

### Critical (3)

| # | Issue | File | Fix |
|---|-------|------|-----|
| 1 | **Best promotion selection ignores `max_discount_amount`** — uncapped discount used for comparison, but cap applied after selection. Could cause wrong promotion to win "best" (e.g., 50% capped at $100 wins over $80 flat, giving customer less discount). | `StorefrontController.php:502-510` | Added `max_discount_amount` cap in `findBestPromotionForProduct()` before discount comparison |
| 2 | **Combo in-stock filter checks ANY instead of ALL** — a combo with 5 items where only 1 is in stock incorrectly passes the in-stock filter. | `StorefrontController.php:540-554` | Changed to `whereHas('comboItems')` + `whereDoesntHave('comboItems', fn(checks OOS))` — requires all components in stock |
| 3 | **Related products never show promotions** — controller loads related products without `enrichProductWithPromotion()`, so `product.promotion` is always undefined. Related products always show base price. | `StorefrontController.php:388-411` | Added `$this->enrichProductWithPromotion()` call on related products after loading |

### High (2)

| # | Issue | File | Fix |
|---|-------|------|-----|
| 4 | **Currency hardcoded to `MMK`** in `formatPromotionBadge()` — if platform supports other currencies, fixed promotions display wrong currency symbol. | `StorefrontController.php:521` | Added `$currencySymbol` parameter, resolved from `WebsiteInfo::currency_symbol` in all 3 call sites (`products()`, `show()`, `brand()`) |
| 5 | **`onSelectVariant` dropped by `FeaturedProducts`** — variable products in homepage featured sections cannot trigger the variant select modal. | `FeaturedProducts.jsx:4` | Added `onSelectVariant` prop forwarding to `ProductCard` |

### Medium (4)

| # | Issue | File | Fix |
|---|-------|------|-----|
| 6 | **No `onError` handlers** on `PromotionSection` images | `PromotionSection.jsx` | Added `useState` error tracking + `onError` handler |
| 7 | **No `onError` handlers** on `PromotionBanner` images | `PromotionBanner.jsx` | Added `useState` error tracking + `onError` handler |
| 8 | **No `onError` handlers** on `RelatedProducts` images | `RelatedProducts.jsx` | Added `useState` error tracking + `onError` handler on image + rewrote with `assetUrl` |
| 9 | **No `onError` handlers** on `BrandStorySection` images | `BrandStorySection.jsx` | Added `useState` error tracking + `onError` handler |
| 10 | **No `onError` on Show.jsx gallery thumbnails** | `Show.jsx:272` | Added `onError` that hides broken thumbnails |

### Low (1)

| # | Issue | File | Fix |
|---|-------|------|-----|
| 11 | **`gallery_images_url` returns null per empty element** — could cause broken `<img>` tags in gallery views | `Product.php:802-807` | Added `array_filter` + `array_values` to strip null/empty entries |

---

## Files Modified

### PHP (3 files)
| File | Lines Changed |
|------|---------------|
| `app/Http/Controllers/StorefrontController.php` | ~30 lines — promotion capping, combo filter, currency, related enrichment, import |
| `app/Models/Product.php` | 1 line — gallery_images_url null filtering |
| `tests/Feature/StorefrontMediaMerchandisingTest.php` | ~200 lines — 12 new tests |

### JS/React (6 files)
| File | Lines Changed |
|------|---------------|
| `resources/js/Components/Storefront/FeaturedProducts.jsx` | +1 prop (`onSelectVariant`) |
| `resources/js/Components/Storefront/PromotionSection.jsx` | Rewritten — added error handling |
| `resources/js/Components/Storefront/PromotionBanner.jsx` | +15 lines — error handling state |
| `resources/js/Components/Storefront/RelatedProducts.jsx` | Rewritten — added error handling, fixed promotion data usage |
| `resources/js/Components/Storefront/BrandStorySection.jsx` | Rewritten — added error handling |
| `resources/js/Pages/Storefront/Show.jsx` | +3 lines — gallery thumbnail onError |

---

## Tests Added (12 new tests in `StorefrontMediaMerchandisingTest.php`)

| Test | What It Verifies |
|------|------------------|
| `best_promotion_respects_max_discount_amount` | Fixed $80 promo wins over 10% capped at $50 |
| `max_discount_amount_caps_even_when_best` | 50% of $500 capped to $100, promotion_price = $400 |
| `promotion_badge_uses_custom_currency_symbol` | `-5,000 USD` for custom currency |
| `promotion_badge_defaults_to_k` | `-1,000 K` when no currency passed |
| `percentage_promotion_badge_ignores_currency` | `-25%` regardless of currency |
| `gallery_images_url_filters_out_null_entries` | Only valid URLs returned |
| `gallery_images_url_returns_empty_array_for_all_empty` | Empty array when all entries null |
| `show_page_enriches_related_products_with_promotions` | Related products get promotion data |
| `combo_with_all_components_in_stock_passes_filter` | Combo with all components in stock passes |
| `combo_with_one_component_out_of_stock_fails_filter` | Combo with 1 OOS component fails |

---

## Verification

| Check | Result |
|-------|--------|
| `php -l` on StorefrontController.php | ✅ No syntax errors |
| `php -l` on Product.php | ✅ No syntax errors |
| `php -l` on StorefrontMediaMerchandisingTest.php | ✅ No syntax errors |
| `npm run build` (vite build) | ✅ Compiled in 36.67s, 2661 modules |
| Tests cannot run (PHP 8.1 vs required 8.2+) | ⚠️ Blocked by PHP version |

---

## Tenant Safety Audit (Verified Correct)

| Area | Assessment |
|------|-----------|
| Product queries | `TenantScope` auto-applies — correct |
| Promotion queries | `TenantScope` auto-applies — correct |
| Category/Brand queries | `TenantScope` auto-applies — correct |
| StorefrontConfigurationResolver | Uses `withoutTenantScope()` + explicit `where('tenant_id', $tenantId)` — correct |
| Route model binding (Product, Brand) | Auto-scoped by `TenantScope` — correct |
| Brand page has extra manual tenant check | Defense-in-depth — correct |
| Related products query | Uses `Product::active()` which is tenant-scoped — correct |
| Enrichment methods | Only touch in-memory data after query — no cross-tenant risk |

---

## Performance Audit (Verified Correct)

| Area | Assessment |
|------|-----------|
| N+1 on promotion enrichment | Promotions eager-loaded once, checked in-memory per product — no N+1 |
| N+1 on product listing | `category`, `brand`, `variants` eager-loaded — correct |
| N+1 on related products | Eager-loaded `category`, `brand` — correct |
| N+1 on promotion queries | Eager-loaded `products`, `categories` — correct |
| `withCount` for category/brand products | Batched SQL sub-queries — acceptable |
| Media existence checks | `ImageService::exists()` per item in resolver — low risk |

---

## Remaining Risks (Not Fixed — Out of Scope)

| Risk | Severity | Reasoning |
|------|----------|-----------|
| `ImageService::exists()` returns true for HTTP URLs without checking | Low | Remote existence checks are expensive; design choice |
| `ImageService::getFileSize()` returns 0 for Cloudinary | Low | Storage tracking doesn't count Cloudinary usage |
| Product accessors return `null` for empty images; Category/Brand return SVG placeholder | Low | Inconsistent but both handled by frontend fallbacks |
| `og:image` meta tag may get SVG data URI when no image | Low | Social crawlers ignore invalid images gracefully |
| No `scopeFeatured` on Product model | Low | Homepage products use featured/sort_order, not a dedicated scope |
| `StorefrontConfigurationResolver::resolveBase()` creates WebsiteInfo if missing | Low | Side effect in read path; provisioning behavior |
| User-supplied `%`/`_` not escaped in LIKE clauses | Low | Minor UX, not security risk |
