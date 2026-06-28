# V3 Hotfix: Platform Branding Source Sync

**Date:** 2026-06-22
**Scope:** Audit all branding references, classify by domain (platform vs tenant), and ensure each uses the correct source.

---

## Root Cause

V3-B2-C introduced `PlatformSetting` as the primary branding source for ALL pages, including tenant storefront layouts. Tenant-facing components (`AppLayout.jsx`, `Client/Products/Index.jsx`) were changed to prefer `platform_setting` over `website_info`, causing tenant storefronts to display platform branding instead of their own store settings.

---

## Audit Summary

52 branding references found across 20+ files. Classified into three domains:

| Domain | Source | Files | Purpose |
|---|---|---|---|
| **Platform** | `PlatformSetting::current()` | `app.blade.php`, `AdminSidebar`, `AdminFooter`, `AdminHeader`, `GuestLayout`, `CreateStoreController` | SuperAdmin UI, login page, registration pages, browser title/favicon |
| **Tenant** | `WebsiteInfo` | `AppLayout`, `ShopNavbar`, `ShopFooter`, `ContactDrawer`, `ProductCard`, `Client/Products/Index`, 12 blade templates | Storefront pages, cart, checkout, orders, client nav/footer |
| **Legacy** | `config('app.name')` | Seeders, migrations, config files, test email route | System identifiers (Redis prefix, cache prefix), not display branding |

---

## Fixes Applied

### Reverted to Tenant Source (WebsiteInfo primary)

| File | Before (V3-B2-C) | After (Hotfix) |
|---|---|---|
| `resources/js/Layouts/AppLayout.jsx` | `plat.site_logo \|\| website_info?.logo` (platform first) | `website_info?.logo` (tenant only) |
| `resources/js/Layouts/AppLayout.jsx` | `plat.site_name \|\| website_info?.site_name` (platform first) | `website_info?.site_name \|\| platform_setting?.site_name` (tenant first, platform fallback) |
| `resources/js/Pages/Client/Products/Index.jsx` | `plat.site_name \|\| website_info?.site_name` (platform first) | `website_info?.site_name \|\| 'My Store'` (tenant only) |

### Already Correct (Platform Source)

| File | Source | Primary |
|---|---|---|
| `resources/views/app.blade.php` — title | `platform_setting.site_name` → `website_info.site_name` | Platform ✅ |
| `resources/views/app.blade.php` — favicon | `platform_setting.favicon` | Platform ✅ |
| `resources/js/Components/AdminSidebar.jsx` | SuperAdmin: `platform_setting`, Admin: `website_info` | Correct per role ✅ |
| `resources/js/Components/AdminFooter.jsx` | SuperAdmin: `platform_setting`, Admin: `website_info` | Correct per role ✅ |
| `resources/js/Components/AdminHeader.jsx` | SuperAdmin subtitle from `platform_setting` | Platform ✅ |
| `resources/js/Layouts/GuestLayout.jsx` | `platform_setting.site_logo` → `website_info.logo` | Platform ✅ |
| `app/Http/Controllers/CreateStoreController.php` | `PlatformSetting::current()` → `WebsiteInfo` | Platform ✅ |

### Unchanged Tenant Components (WebsiteInfo only)

| Component | Source | Status |
|---|---|---|
| `ShopNavbar.jsx` | `website_info?.logo`, `website_info?.site_name` | ✅ Untouched |
| `ShopFooter.jsx` | `website_info` fields | ✅ Untouched |
| `ContactDrawer.jsx` | `website_info` contact/social fields | ✅ Untouched |
| `ProductCard.jsx` | `website_info?.enable_wishlist` | ✅ Untouched |
| All `client/*.blade.php` templates | `$websiteInfo` | ✅ Untouched |
| `layouts/app.blade.php` (legacy) | `$websiteInfo->logo` as favicon | ✅ Untouched |

---

## Branding Sources Unified

| UI Element | SuperAdmin (Platform) | Store Admin (Tenant) | Storefront (Public) |
|---|---|---|---|
| Browser title | `PlatformSetting.site_name` | `WebsiteInfo.site_name` | `WebsiteInfo.site_name` |
| Favicon | `PlatformSetting.favicon` | `WebsiteInfo.logo` (legacy) | `WebsiteInfo.logo` |
| Navbar logo | `PlatformSetting.site_logo` | `WebsiteInfo.logo` | `WebsiteInfo.logo` |
| Footer name | `PlatformSetting.site_name` | `WebsiteInfo.site_name` | `WebsiteInfo.site_name` |
| Footer email | `PlatformSetting.support_email` | — | `WebsiteInfo.support_email` |
| Login page | `PlatformSetting` | `WebsiteInfo` | — |
| Registration | `PlatformSetting` | — | — |

---

## Files Modified

| File | Change |
|---|---|
| `resources/js/Layouts/AppLayout.jsx` | Reverted to `website_info` as primary branding source for tenant storefront |
| `resources/js/Pages/Client/Products/Index.jsx` | Reverted to `website_info` only (removed `platform_setting` usage) |

---

## Platform Components Updated

- `app.blade.php` (title + favicon)
- `AdminSidebar.jsx` (SuperAdmin logo/name)
- `AdminFooter.jsx` (SuperAdmin name/email)
- `AdminHeader.jsx` (SuperAdmin subtitle)
- `GuestLayout.jsx` (login page logo/name)
- `CreateStoreController` (registration branding)

---

## Tenant Components Unchanged

- `ShopNavbar.jsx`, `ShopFooter.jsx`, `ContactDrawer.jsx`, `ProductCard.jsx`
- `AppLayout.jsx` (restored to tenant-primary)
- `Client/Products/Index.jsx` (restored to tenant-only)
- All `client/*.blade.php` templates
- All `layouts/*.blade.php` templates
- `admin/*.blade.php` templates

---

## Tests Passed

- `PlatformSettingsTest`: 9/9 (31 assertions)
- `MerchantManagementTest`: 4/4 (8 assertions)
- Total: 13 passed (39 assertions)

---

## Regression Risk

**None.** Changes restore V3-B2-C tenant components to pre-V3-B2-C behavior:
- `AppLayout.jsx` — returns to using `website_info` as primary source
- `Client/Products/Index.jsx` — returns to `website_info` only
- Platform components remain on `PlatformSetting` as implemented in V3-B2-C
- All existing tests pass with no modifications
