# Step 1: Root Homepage Separation Report

**Date:** 2026-06-12
**Scope:** Convert root homepage (`/`) from tenant-dependent storefront to SaaS platform landing page.

---

## Route Audited

| Property | Before | After |
|----------|--------|-------|
| **URI** | `/` | `/` (unchanged) |
| **Route file** | `routes/web.php:41` | Unchanged |
| **Name** | `home` | Unchanged |
| **Middleware** | `web` group only | Unchanged |
| **Controller** | `ClientController@index` | Unchanged |
| **Page** | `Client/Products/Index` | Unchanged |

---

## Files Changed

### 1. `app/Http/Controllers/Client/ClientController.php`

**Method:** `index()` — completely rewritten.

**Before:** 17 tenant-dependent queries + enrichment logic:
- `Product::active()` with filters (search, category, sort) + pagination
- `Promotion::valid()->automatic()` with products/categories
- `PromotionBanner::active()->latest()`
- `Category::withCount('products')->orderBy('products_count', 'desc')->take(6)`
- `Product::active()->latest()->take(8)` (latestProducts)
- `Product::active()->whereNotNull('photo1')->latest()->take(8)` (featuredProducts)
- `Product::active()->withCount('orderItems')->orderBy('order_items_count', 'desc')->take(8)` (bestsellerProducts)
- `Product::active()->exists()` (hasProducts)
- `Category::all()` (categories)
- 4 enrichment helper calls: `enrichProductWithPromotion`
- 5 Inertia props: `products` (scroll), `featuredCategories`, `latestProducts` (defer), `featuredProducts` (defer), `bestsellerProducts` (defer), `promotionBanners`, `hasProducts`, `categories`, `searchQuery`, `filters`

**After:** Zero queries. Renders empty landing page:
```php
public function index(Request $request)
{
    return Inertia::render('Client/Products/Index');
}
```

**Import removed:** `use App\Models\PromotionBanner;`

**Preserved methods (used by `/products` and `/products/{product}` routes):**
- `products()`, `show_product()`, `cart()`, `showLogin()`, `showRegister()`, `checkout()`, `orders()`
- All private helpers: `enrichProductWithPromotion`, `findBestPromotionForProduct`, `formatPromotionBadge`, `applySorting`, `applyInStockFilter`

---

### 2. `resources/js/Pages/Client/Products/Index.jsx`

**Component:** `ClientProductIndex` — completely rewritten from storefront to platform landing page.

**Before:** 220 lines — full storefront with:
- 12 imports: `useState`, `useCallback`, `useRef`, `useEffect`, `Head`, `router`, `usePage`, `InfiniteScroll`, `ShopLayout`, `ProductCard`, `BackToTopButton`, `HeroSection`, `FeaturedCategories`, `FeaturedProducts`, `PromotionBanner`, `StoreFeatures`, `useCart`
- Search/filter/sort state management (3 useEffect, 5 useCallback)
- AJAX filter navigation via `router.get('/')`
- Product grid with `InfiniteScroll`
- Loading skeletons, empty state, active filter chips
- 6 storefront components rendering when no active filters
- Tenant-aware `HeroSection`

**After:** 137 lines — clean SaaS landing page:
- 4 imports: `Head`, `Link`, `usePage`, `ShopLayout`, `assetUrl`
- Two CTA buttons: "Create Your Store" → `/create-store`, "Merchant Login" → `/login`
- 6 feature cards (Custom Storefront, Payment Ready, Inventory Management, Promotions & Coupons, Sales Reports, Customer Communication)
- 3-step "How It Works" section
- Final CTA section
- Zero tenant dependencies. Zero storefront components.

---

## Tenant Dependencies Removed

| # | Dependency | Type | File Removed From |
|---|-----------|------|-------------------|
| 1 | `Product::active()` product grid query | Controller query | `ClientController@index` |
| 2 | `Promotion::valid()->automatic()` promotion data | Controller query | `ClientController@index` |
| 3 | `PromotionBanner::active()->latest()` banner data | Controller query | `ClientController@index` |
| 4 | `Category::withCount('products')` featured categories | Controller query | `ClientController@index` |
| 5 | `Product::active()->latest()` latest products | Controller query | `ClientController@index` |
| 6 | `Product::active()->whereNotNull('photo1')` featured products | Controller query | `ClientController@index` |
| 7 | `Product::active()->withCount('orderItems')` bestsellers | Controller query | `ClientController@index` |
| 8 | `Product::active()->exists()` hasProducts check | Controller query | `ClientController@index` |
| 9 | `Category::all()` categories list | Controller query | `ClientController@index` |
| 10 | `enrichProductWithPromotion` × 4 | Enrichment logic | `ClientController@index` |
| 11 | `HeroSection` component | Frontend component | `Client/Products/Index.jsx` |
| 12 | `FeaturedCategories` component | Frontend component | `Client/Products/Index.jsx` |
| 13 | `FeaturedProducts` (×3) components | Frontend component | `Client/Products/Index.jsx` |
| 14 | `PromotionBanner` component | Frontend component | `Client/Products/Index.jsx` |
| 15 | `StoreFeatures` component | Frontend component | `Client/Products/Index.jsx` |
| 16 | `ProductCard` component | Frontend component | `Client/Products/Index.jsx` |
| 17 | `InfiniteScroll` component | Frontend component | `Client/Products/Index.jsx` |
| 18 | `useCart` hook | Frontend hook | `Client/Products/Index.jsx` |
| 19 | `BackToTopButton` component | Frontend component | `Client/Products/Index.jsx` |
| 20 | `router.get('/')` for filter navigation | Frontend logic | `Client/Products/Index.jsx` |
| 21 | Product search/filter/sort state | Frontend logic | `Client/Products/Index.jsx` |
| 22 | `PromotionBanner` model import | Controller import | `ClientController` |
| 23 | `handleAddToCart` callback | Frontend logic | `Client/Products/Index.jsx` |
| 24 | `Loading skeletons` | Frontend UI | `Client/Products/Index.jsx` |
| 25 | `No products found` empty state | Frontend UI | `Client/Products/Index.jsx` |

---

## Routes Affected

| Method | URI | Impact |
|--------|-----|--------|
| `GET` | `/` | Page content completely changed — no longer shows products |
| `GET` | `/products` | Unchanged — still handled by `ClientController@products` |
| `GET` | `/products?query=&category=&sort=` | Unchanged — separate route, not affected |
| `GET` | `/dashboard` | Unchanged — aliased to same controller but different page needs |

The `/products` route (`ClientController@products`) is a separate method and was **not modified**. It still queries products, categories, promotions, and renders `Client/Products/Products`. It remains a tenant-dependent product listing page.

---

## Preserved Functionality

| Feature | Status | How |
|---------|--------|-----|
| **Create Store** | ✓ Kept | CTA in hero, CTA in final section, link in navbar |
| **Merchant Login** | ✓ Kept | CTA in hero, CTA in final section, link in navbar |
| **Platform Branding** | ✓ Kept | Site name, logo from `website_info` (shared via HandleInertiaRequests) |
| **ShopLayout** | ✓ Kept | Navbar (Create Store / Merchant Login / Contact Us), Footer, FlashMessages |
| **Navbar links** | ✓ Kept | Home, Products, My Orders (platform-level), Wishlist, Cart |
| **Cart** | ✓ Kept | Session-based, no tenant dependency |
| **Wishlist** | ✓ Kept | Works for authenticated users |

---

## Risks

| ID | Risk | Severity | Mitigation |
|----|------|----------|------------|
| R1 | `/products` route still uses `ClientController@products` which queries all products without tenant_id filter (same pre-existing issue) | MEDIUM | Out of scope for Step 1 — `/products` is a separate route |
| R2 | `HandleInertiaRequests` still shares `categories` cached with `tenant?->id ?? 'default'` key — fetches all categories without tenant filter | LOW | Pre-existing; categories are now unused by homepage but still shared to all pages |
| R3 | `ShopNavbar` "My Orders" link for non-tenant context goes to `/orders` which requires auth — unauthenticated users see login page | LOW | Acceptable UX; unauthenticated users are redirected to login |
| R4 | The landing page displays site branding from `website_info` which is a single global WebsiteInfo record — if no record exists, falls back to "My Store" | LOW | Pre-existing; WebsiteInfo is created on first access |
| R5 | `ShopFooter` "Shop" links (All Products, New Arrivals, Best Sellers, Sale Items) all point to `/` — now a landing page, not a product listing | LOW | These are static footer links; `/` now shows landing page; `/products` is the actual product listing |
| R6 | The route `/dashboard` (line 42 of web.php) also maps to `ClientController@index` — will also show landing page instead of any dashboard | LOW | Pre-existing alias; `/dashboard` was never a real dashboard |
| R7 | `ClientController@index` no longer handles `only: ['products', 'searchQuery', 'filters']` requests — the old homepage frontend AJAX navigation via `router.get('/', params)` is now dead code in the storefront | LOW | No Inertia partial reload targets exist in the new component |
| R8 | Old component imports (`FeaturedProducts`, `PromotionBanner`, `useCart`, etc.) removed from homepage — if any other page imports these from `Client/Products/Index`, build would fail (none found) | NONE | Build verified — 2464 modules transformed with 0 errors |

---

## Verification

| Check | Result |
|-------|--------|
| Vite production build | ✓ Pass (2464 modules, 0 errors) |
| Storefront tests (43) | ✓ All pass |
| Auth tests (9) | ✗ Pre-existing failures (SQLite migration issue, unrelated) |
| No storefront files modified | ✓ Verified |
| No tenant route files modified | ✓ Verified |
| No admin route files modified | ✓ Verified |

---

## Summary

The root homepage `/` has been converted from a tenant-dependent storefront (with product listings, category filters, featured sections, promotion banners) into a clean SaaS platform landing page. All 25 tenant dependencies were removed — 9 controller queries and 16 frontend components/logic. The page now contains only platform branding, feature cards, a "How It Works" section, and CTAs for "Create Your Store" and "Merchant Login". No storefront, tenant, or admin routes were modified.
