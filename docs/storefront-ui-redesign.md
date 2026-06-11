# Storefront UI/UX Redesign (v2)

## Goal

Unify the global homepage (`/`) and store homepage (`/store/{slug}`) under a shared component architecture, providing a modern SaaS ecommerce storefront experience.

## Changes

### New Shared Components (`resources/js/Components/Storefront/`)

| Component | File | Purpose |
|-----------|------|---------|
| **HeroSection** | `HeroSection.jsx` | Full-width hero with store logo, name, description, CTAs, banner image. Fallbacks for missing logo/banner. |
| **FeaturedCategories** | `FeaturedCategories.jsx` | Responsive grid (2-6 cols) of categories with images and product counts. Hidden if no categories exist. |
| **FeaturedProducts** | `FeaturedProducts.jsx` | Product grid (2-4 cols) with configurable title/subtitle. Used for latest, featured, and bestseller sections. |
| **PromotionBanner** | `PromotionBanner.jsx` | Auto-rotating carousel for active promotion banners. Hidden if none active. |
| **StoreFeatures** | `StoreFeatures.jsx` | Feature cards (Fast Delivery, Secure Payment, Easy Returns, Quality Products). |
| **EmptyStoreState** | `EmptyStoreState.jsx` | Friendly onboarding state when store has no products. |
| **FooterSection** | `FooterSection.jsx` | Footer with links, contact info, socials. |

### Files Modified

| File | Change |
|------|--------|
| `resources/js/Pages/Storefront/Index.jsx` | Refactored to use shared components. Hero replaced with `HeroSection`. Added `FeaturedCategories`, `FeaturedProducts` (3 sections), `PromotionBanner`, `StoreFeatures`, `EmptyStoreState`. Search/filter area preserved. |
| `resources/js/Pages/Client/Products/Index.jsx` | Refactored identically to use shared components. Now visually consistent with storefront. Hero, categories, products, features all from shared components. |
| `app/Http/Controllers/StorefrontController.php` | Added data: `featuredCategories` (top 6 by count), `latestProducts` (8 newest), `featuredProducts` (8 with images), `bestsellerProducts` (8 by order count), `promotionBanners`, `hasProducts`. |
| `app/Http/Controllers/Client/ClientController.php` | Same additions as StorefrontController. |

### Files Created

| File | Purpose |
|------|---------|
| `resources/js/Components/Storefront/HeroSection.jsx` | Shared hero component |
| `resources/js/Components/Storefront/FeaturedCategories.jsx` | Category grid |
| `resources/js/Components/Storefront/FeaturedProducts.jsx` | Product section wrapper |
| `resources/js/Components/Storefront/PromotionBanner.jsx` | Promotion carousel |
| `resources/js/Components/Storefront/StoreFeatures.jsx` | Feature cards |
| `resources/js/Components/Storefront/EmptyStoreState.jsx` | Empty state |
| `resources/js/Components/Storefront/FooterSection.jsx` | Footer |

## Before/After

### Before (old)
```
Global Homepage (/)                    Store Homepage (/store/{slug})
─────────────────────────────          ─────────────────────────────
Hero: custom section with              Hero: simple gradient banner
  hero_title, hero_subtitle,             with store name + description
  hero button text/link                
                                        No featured sections
Promotion banner carousel              
                                        Product search + filter + grid
Product search + filter + grid         
                                        No features section
Default fallback banner                
                                        No empty state
```

### After (new)
```
Global Homepage (/)                    Store Homepage (/store/{slug})
─────────────────────────────          ─────────────────────────────
HeroSection (shared)                   HeroSection (shared)
  - Logo / Store Name                    - Logo / Store Name
  - Description                          - Description
  - Shop Now / Browse Categories         - Shop Now / Browse Categories
  - Banner image                         - Banner image

PromotionBanner (shared)               PromotionBanner (shared)
  - Auto-rotating carousel               - Same component

FeaturedCategories (shared)            FeaturedCategories (shared)
  - Top 6 categories with images         - Same grid

FeaturedProducts (latest)              FeaturedProducts (latest)
  - 8 newest products                    - Same

FeaturedProducts (featured)            FeaturedProducts (featured)
  - 8 products with images               - Same

FeaturedProducts (best sellers)        FeaturedProducts (best sellers)
  - 8 most ordered products              - Same

StoreFeatures (shared)                 StoreFeatures (shared)
  - Fast Delivery, Secure Payment,       - Same cards
    Easy Returns, Quality Products      

Product search + filter + grid         Product search + filter + grid
                                       EmptyStoreState (if no products)
```

## Responsive Design

| Breakpoint | Grid Columns | Layout Behavior |
|------------|-------------|-----------------|
| 320px | 2 cols (products) | Stack hero text, logo scales, no overflow |
| 375px | 2 cols | Same as 320px |
| 425px | 2 cols | Buttons start wrapping properly |
| 768px | 3 cols | Hero side image appears, 3-category row |
| 1024px | 4 cols | Full desktop layout, hero image visible |
| 1440px | 4 cols | Maximum container width, centered |

## Performance

- **Lazy loading:** Category images, product images, and hero banners use `loading="lazy"` (hero main image uses `loading="eager"` for LCP).
- **Deferred data:** `latestProducts`, `featuredProducts`, `bestsellerProducts` use `Inertia::defer()` for lazy hydration.
- **No duplicate queries:** Featured data queries are separate from main product pagination — avoids N+1.
- **Efficient pagination:** Main product list still uses `Inertia::scroll()` (InfiniteScroll).

## Manual QA Checklist

### Visual Consistency
- [ ] Global homepage and store homepage use same hero design
- [ ] Same typography, spacing, button styles across both
- [ ] Category grid is identical component on both pages
- [ ] Product sections use identical FeaturedProducts component
- [ ] Features section is identical on both
- [ ] Footer via ShopLayout is same for both

### HeroSection
- [ ] Logo displays correctly
- [ ] Store name is prominent
- [ ] Description shows
- [ ] "Shop Now" CTA navigates to products page
- [ ] "Browse Categories" CTA navigates to products page with category filter
- [ ] Banner image fills background
- [ ] Fallback: no logo → no logo shown (graceful)
- [ ] Fallback: no banner → gradient background only

### FeaturedCategories
- [ ] Shows max 6 categories
- [ ] Each card shows name and product count
- [ ] Click navigates to filtered products page
- [ ] Hidden when no categories exist
- [ ] Responsive grid (2 on mobile, 6 on desktop)

### FeaturedProducts
- [ ] Latest Products section shows newest 8
- [ ] Featured Products section shows 8 with images
- [ ] Best Sellers section shows 8 by order count
- [ ] "Add to Cart" works from product cards
- [ ] Hidden when no matching products
- [ ] Responsive grid (2 mobile, 3 tablet, 4 desktop)

### PromotionBanner
- [ ] Auto-rotates every 5s when multiple banners
- [ ] Previous/next arrows work
- [ ] Dots navigation works
- [ ] Hidden when no active banners
- [ ] Click navigates to banner link

### StoreFeatures
- [ ] 4 feature cards display correctly
- [ ] Responsive (1 col mobile, 2 tablet, 4 desktop)

### EmptyStoreState
- [ ] Shows store name
- [ ] "Preparing products" message
- [ ] Only shown when product count is 0
- [ ] Not shown when search has no results (shows "no products found" instead)

### Search & Filter
- [ ] Search input works with debounce
- [ ] Category filter works
- [ ] Sort options work
- [ ] Active filters show correctly
- [ ] "Clear all" resets to default
- [ ] Loading skeletons appear during search

### Responsive
- [ ] No horizontal scroll at any breakpoint
- [ ] Hero text never overflows
- [ ] Buttons wrap correctly on mobile
- [ ] Logo scales properly
- [ ] Product grid: 2 cols mobile, 3 tablet, 4 desktop
- [ ] Search bar stacks on mobile
- [ ] Category filter stacks on mobile
- [ ] No layout shift during load

## Potential Breaking Changes

1. **Hero data source change:** The global homepage previously used `website_info.hero_title`, `website_info.hero_subtitle`, `website_info.hero_button_text`, `website_info.hero_button_link` for its hero. These are now replaced by the `HeroSection` component which uses `store.name` (from tenant data) and `website_info.site_description` / `site_tagline`. The old hero fields from Website Info are no longer displayed on the global homepage hero.

2. **Removed fallback banner:** The global homepage previously showed a fallback gradient banner ("Special Offers") when no hero_title or banners existed. This is now removed — the HeroSection always shows with the store name.

3. **Lazy/deferred data:** `latestProducts`, `featuredProducts`, `bestsellerProducts` use `Inertia::defer()` — they load after the initial page render. If any component depends on synchronous data, it may render empty initially.

4. **Category data shape:** `featuredCategories` uses `withCount('products')` — the shape includes `products_count` which is used in the UI. If category models use a different relationship name for products, this will be 0.

5. **Bestseller sort:** Uses `orderBy('order_items_count', 'desc')` which requires `withCount('orderItems')`. If the Product model's `orderItems()` relationship doesn't exist or returns unexpected results, the section may be incorrect.
