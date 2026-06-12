# Step 2: Storefront Isolation Report

**Date:** 2026-06-12
**Scope:** Split shared `HeroSection` into `HomepageHero` and `StorefrontHero`. Eliminate cross-context component sharing.

---

## Shared Components Audit

All components in `resources/js/Components/Storefront/` were audited for cross-context usage.

| # | Component | Before Step 2 | After Step 2 |
|---|-----------|---------------|--------------|
| 1 | `HeroSection.jsx` | SHARED — imported by Storefront | SPLIT → replaced by StorefrontHero + HomepageHero |
| 2 | `StorefrontHero.jsx` | (did not exist) | STOREFRONT ONLY — created new |
| 3 | `HomepageHero.jsx` | (did not exist) | HOMEPAGE ONLY — created new |
| 4 | `EmptyStoreState.jsx` | STOREFRONT ONLY | STOREFRONT ONLY (unchanged) |
| 5 | `FeaturedCategories.jsx` | HOMEPAGE ONLY (after Step 1) | ORPHANED — no consumer |
| 6 | `FeaturedProducts.jsx` | HOMEPAGE ONLY (after Step 1) | ORPHANED — no consumer |
| 7 | `PromotionBanner.jsx` | HOMEPAGE ONLY (after Step 1) | ORPHANED — no consumer |
| 8 | `StoreFeatures.jsx` | HOMEPAGE ONLY (after Step 1) | ORPHANED — no consumer |

---

## Components Separated

### `HeroSection` → `StorefrontHero` + `HomepageHero`

**Old file:** `Components/Storefront/HeroSection.jsx` (84 lines)
**Deleted:** Yes

**New files:**

| Component | File | Lines | Purpose | Imports |
|-----------|------|-------|---------|---------|
| `StorefrontHero` | `Components/Storefront/StorefrontHero.jsx` | 78 | Storefront hero: store name/logo, image carousel, CTAs pointing to store sections | `useState`, `useEffect`, `assetUrl` |
| `HomepageHero` | `Components/Storefront/HomepageHero.jsx` | 46 | Platform hero: site logo, "Launch Your Online Store" headline, Create Store / Merchant Login CTAs | `Link`, `assetUrl` |

### Key Differences Between Split Components

| Aspect | `StorefrontHero` | `HomepageHero` |
|--------|-----------------|----------------|
| **Props** | `{ store, websiteInfo }` | `{ websiteInfo }` |
| **Tenant dependency** | Requires `store.slug` for CTA hrefs | Zero tenant dependency |
| **Carousel** | Yes — banner images from `website_info.hero_images_urls` | No carousel |
| **Background** | Gradient card (`rounded-2xl`, `max-h-[260px]`) | Full-width gradient section (`py-20 sm:py-28 lg:py-36`) |
| **CTAs** | "Shop Now" → `/store/{slug}#products-section`<br>"Browse Categories" → `/store/{slug}#` | "Create Your Store" → `/create-store`<br>"Merchant Login" → `/login` |
| **Layout** | Left content + right image carousel (desktop) | Centered content, stacked CTAs |
| **Shared state** | None (removed `usePage` dependency) | None (stateless) |

---

## Files Changed

| File | Change |
|------|--------|
| `resources/js/Components/Storefront/StorefrontHero.jsx` | **CREATED** — extracted storefront hero from old `HeroSection.jsx`. Removed `usePage` dependency. Simplified CTA hrefs to always use `store.slug` (no null guard needed — storefront always has tenant). |
| `resources/js/Components/Storefront/HomepageHero.jsx` | **CREATED** — extracted platform landing hero section from `Client/Products/Index.jsx` inline JSX. Wraps logo, headline, tagline, and CTAs into reusable component with `{ websiteInfo }` prop. |
| `resources/js/Components/Storefront/HeroSection.jsx` | **DELETED** — no longer used. |
| `resources/js/Pages/Storefront/Index.jsx` | **MODIFIED** — import changed from `HeroSection` to `StorefrontHero`. Usage changed from `<HeroSection store={tenant} ...>` to `<StorefrontHero store={tenant} ...>`. |
| `resources/js/Pages/Client/Products/Index.jsx` | **MODIFIED** — import changed from inline logic to `HomepageHero`. Removed `assetUrl` import (no longer needed at page level). Removed inline hero JSX (55 lines → 1 line). |

---

## Remaining Shared Components

After separation, these components remain shared between homepage and storefront:

| Component | File | Why Still Shared |
|-----------|------|------------------|
| `ShopLayout` | `Layouts/ShopLayout.jsx` | Structural layout shell — renders Navbar, children, Footer. No tenant-specific logic. |
| `ShopNavbar` | `Components/ShopNavbar.jsx` | Already handles both tenant contexts with null guards. Shows different buttons per context. |
| `ShopFooter` | `Components/ShopFooter.jsx` | Reads only `website_info` (platform-level). No tenant dependency. |
| `FlashMessages` | `Components/FlashMessages.jsx` | Purely functional — reads flash data from shared Inertia props. |
| `ProductCard` | `Components/ProductCard.jsx` | Pure presentational component — receives product data as props. |
| `useCart` | `Hooks/useCart.js` | Session-based cart hook. No tenant dependency. |

These remaining shared components are infrastructure-level (layout, navigation, utilities) and do not carry storefront visual logic. They are classified as **SAFE TO SHARE** in the architecture audit.

---

## Orphaned Components (No Consumer)

These components in `Components/Storefront/` are no longer imported by any page after Step 1 and Step 2:

| Component | File | Status |
|-----------|------|--------|
| `FeaturedCategories` | `Components/Storefront/FeaturedCategories.jsx` | Orphaned — was homepage-only, removed in Step 1 |
| `FeaturedProducts` | `Components/Storefront/FeaturedProducts.jsx` | Orphaned — was homepage-only, removed in Step 1 |
| `PromotionBanner` | `Components/Storefront/PromotionBanner.jsx` | Orphaned — was homepage-only, removed in Step 1 |
| `StoreFeatures` | `Components/Storefront/StoreFeatures.jsx` | Orphaned — was homepage-only, removed in Step 1 |

These are candidates for deletion in a future cleanup step.

---

## Verification

| Check | Result |
|-------|--------|
| Vite production build | ✓ Pass (2465 modules, 0 errors) |
| Storefront tests (43) | ✓ All pass |
| No storefront-only pages import `HomepageHero` | ✓ Verified — only `Client/Products/Index` imports it |
| No homepage pages import `StorefrontHero` | ✓ Verified — only `Storefront/Index` imports it |
| `HeroSection.jsx` deleted | ✓ Verified — file no longer exists |
| No references to `HeroSection` remain in codebase | ✓ Verified — grep shows 0 matches |

---

## Component Import Map (Final)

```
Pages/Client/Products/Index.jsx (HOMEPAGE)
  → Components/Storefront/HomepageHero.jsx      [HOMEPAGE ONLY]
  → Layouts/ShopLayout.jsx                       [SHARED]

Pages/Storefront/Index.jsx (STOREFRONT)
  → Components/Storefront/StorefrontHero.jsx     [STOREFRONT ONLY]
  → Components/Storefront/EmptyStoreState.jsx    [STOREFRONT ONLY]
  → Layouts/ShopLayout.jsx                       [SHARED]
```

No cross-context hero component sharing remains. Changing storefront hero no longer affects homepage, and vice versa.
