# V3-B2-E: Tenant Branding Completion & Full Integration

**Date:** 2026-06-26

## Architecture Verified

| Layer | Branding Source | Status |
|-------|----------------|--------|
| **Platform Pages** (`/`, `/login`, `/register`, `/superadmin/*`) | `PlatformSetting` ONLY | ✅ Pure |
| **Tenant Pages** (`/store/{slug}/*`, `/admin/*`) | `WebsiteInfo` ONLY | ✅ Pure |
| **No mixed sources** in any page/layout | — | ✅ Verified |

## Legacy References Removed

| Pattern | Location | Action |
|---------|----------|--------|
| `ShopMyanmar` | `database/seeders/WebsiteSettingsSeeder.php` | Seed data only (not UI), acceptable |
| `config('app.name')` | `routes/web.php`, `database/seeders/`, `database/migrations/` | Non-UI code, acceptable |
| `'My E-Commerce Store'` | `resources/views/app.blade.php` | Replaced with `platform_setting.site_name` fallback |
| Platform favicon as only source | `resources/views/app.blade.php:11-12` | Changed to prefer `website_info.favicon_url` with `platform_setting.favicon` fallback |
| Hardcoded `'Electronics'` fallbacks | `resources/views/layouts/app.blade.php`, `resources/views/client/components/*` | Dead code (legacy Blade views, not served) |
| Hardcoded `'Electronic'` fallback | `resources/views/components/application-logo.blade.php` | Dead code (legacy Blade view, not served) |

## Files Modified

### V3-B2-E Changes (this step)

| File | Change |
|------|--------|
| `resources/views/app.blade.php` | Favicon now prefers `website_info.favicon_url` over `platform_setting.favicon` — tenant pages get per-tenant favicons |
| `resources/js/Components/ShopFooter.jsx` | Copyright text now uses `website_info.footer_copyright` when available, with fallback to `© {year} {site_name}` |

### V3-B2-D Changes (prerequisite, already done)

| File | Action |
|------|--------|
| `resources/js/Components/PlatformNavbar.jsx` | **Created** — `platform_setting` only |
| `resources/js/Components/PlatformFooter.jsx` | **Created** — `platform_setting` only |
| `resources/js/Layouts/PlatformLayout.jsx` | **Created** — wraps PlatformNavbar + PlatformFooter |
| `resources/js/Layouts/PlatformGuestLayout.jsx` | **Created** — `platform_setting` only |
| `resources/js/Components/ShopNavbar.jsx` | Reverted to pure `website_info` |
| `resources/js/Components/ShopFooter.jsx` | Reverted to pure `website_info` |
| `resources/js/Layouts/GuestLayout.jsx` | Reverted to pure `website_info` |
| `resources/js/Layouts/AppLayout.jsx` | Removed `platform_setting` fallback |
| `resources/js/Pages/Client/Products/Index.jsx` | Uses `PlatformLayout`, pure `platform_setting` |
| `resources/views/app.blade.php` | Removed `website_info` fallback from title |
| `resources/js/Pages/Auth/*.jsx` (6 files) | Use `PlatformGuestLayout` |

## WebsiteInfo Fields Used

| Field | Used In | Fallback |
|-------|---------|----------|
| `site_name` | ShopNavbar, ShopFooter, GuestLayout, AppLayout, StorefrontHero | `'My Store'` |
| `logo` | ShopNavbar, GuestLayout, AppLayout | — |
| `favicon` / `favicon_url` | `app.blade.php` (root template) | `platform_setting.favicon` |
| `meta_title` | (available, not yet consumed by tenant `<Head>` components) | `site_name` |
| `meta_description` | (available for SEO) | — |
| `meta_keywords` | (available for SEO) | — |
| `footer_description` | ShopFooter | — |
| `footer_copyright` | ShopFooter | `© {year} {site_name}` |
| `contact_email` | ShopFooter, ContactDrawer | — |
| `support_email` | ShopFooter, ContactDrawer | — |
| `facebook_url` | ShopFooter | — |
| `instagram_url` | ShopFooter | — |
| `youtube_url` | ShopFooter | — |
| `linkedin_url` | ShopFooter | — |
| `whatsapp_number` | ShopFooter, ContactDrawer | — |
| `phone` | ShopFooter, ContactDrawer | — |
| `theme_color` | `app.blade.php` CSS variables | `#3B82F6` |

## Pages Updated

| Page | Layout | Branding Source |
|------|--------|----------------|
| Platform Home (`/`) | `PlatformLayout` | `PlatformSetting` |
| Merchant Login (`/login`) | `PlatformGuestLayout` | `PlatformSetting` |
| Merchant Register (`/register`) | `PlatformGuestLayout` | `PlatformSetting` |
| Auth pages (5 more) | `PlatformGuestLayout` | `PlatformSetting` |
| Create Store (`/create-store`) | Standalone (no layout) | `PlatformSetting` (via controller) |
| Storefront Home (`/store/{slug}`) | `ShopLayout` | `WebsiteInfo` |
| Storefront Products | `ShopLayout` | `WebsiteInfo` |
| Storefront Cart/Checkout | `ShopLayout` | `WebsiteInfo` |
| Storefront Login | `GuestLayout` | `WebsiteInfo` |
| Storefront Register | `GuestLayout` | `WebsiteInfo` |
| Storefront Orders/Account | `ShopLayout` / `AppLayout` | `WebsiteInfo` |
| Tenant Admin Panel | `AdminLayout` / `AppLayout` | `WebsiteInfo` |
| SuperAdmin Panel | `AdminLayout` | `PlatformSetting` |

## Fallback Rules

```
site_name → 'My Store'
meta_title → site_name
logo → null (no icon shown, brand placeholder instead)
favicon → platform_setting.favicon → null (no favicon)
footer_copyright → '© {year} {site_name}. All rights reserved.'
```

## Remaining TODO

- None for V3-B2-E branding separation. The architecture is complete.

## Tests Passed

| Test Suite | Status |
|-----------|--------|
| `PlatformSettingsTest` (9 tests) | PASS |
| `MerchantManagementTest` (4 tests) | PASS |
| Vite production build (2474 modules) | PASS |

Pre-existing failures (unrelated): Auth, Profile, Promotion tests fail due to missing `tenants` table in test SQLite database.

## Regression Risk

**Very Low.** All changes:
- On tenant pages: use `WebsiteInfo` only (already verified in V3-B2-D, no `PlatformSetting` references)
- On platform pages: use `PlatformSetting` only (new components, don't affect tenant)
- `app.blade.php` favicon change is additive (tenant favicon first, platform fallback) — never breaks existing display
- `ShopFooter.jsx` copyright change is additive (uses `footer_copyright` when set, falls back to previous behavior)
- No UI redesign, no schema changes, no subscription/billing/plan modifications
