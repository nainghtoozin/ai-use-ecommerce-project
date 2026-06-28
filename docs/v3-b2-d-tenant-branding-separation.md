# V3-B2-D: Tenant Branding Separation

**Date:** 2026-06-26

## Goal

Architecturally separate platform branding (`PlatformSetting`) from tenant branding (`WebsiteInfo`). Each page type reads from exactly one source — never both.

## Architecture Rule

| Page Type | Branding Source | Example Routes |
|-----------|----------------|----------------|
| **Platform Pages** | `PlatformSetting` ONLY | `/`, `/login`, `/register`, `/create-store`, `/superadmin/*` |
| **Tenant Pages** | `WebsiteInfo` ONLY | `/store/{slug}/*` |

## Changes

### New Files Created (Platform-Only)

| File | Purpose | Data Source |
|------|---------|-------------|
| `resources/js/Components/PlatformNavbar.jsx` | Platform public navbar | `platform_setting` |
| `resources/js/Components/PlatformFooter.jsx` | Platform public footer | `platform_setting` |
| `resources/js/Layouts/PlatformLayout.jsx` | Platform page layout | wraps PlatformNavbar + PlatformFooter |
| `resources/js/Layouts/PlatformGuestLayout.jsx` | Platform auth page layout | `platform_setting` (guest/login) |

### Files Modified (Tenant-Only)

| File | Change | Data Source |
|------|--------|-------------|
| `resources/js/Components/ShopNavbar.jsx` | Removed `platform_setting` and conditional logic | `website_info` only |
| `resources/js/Components/ShopFooter.jsx` | Removed `platform_setting` and conditional logic | `website_info` only |
| `resources/js/Layouts/GuestLayout.jsx` | Removed `platform_setting` fallback chain | `website_info` only |
| `resources/js/Layouts/AppLayout.jsx` | Removed `platform_setting?.site_name` fallback | `website_info` only |

### Files Modified (Platform-Only)

| File | Change | Layout |
|------|--------|--------|
| `resources/js/Pages/Client/Products/Index.jsx` | Swapped `ShopLayout` → `PlatformLayout`, pure `platform_setting` | `PlatformLayout` |
| `resources/js/Pages/Auth/Login.jsx` | Swapped `GuestLayout` → `PlatformGuestLayout` | `PlatformGuestLayout` |
| `resources/js/Pages/Auth/Register.jsx` | Swapped `GuestLayout` → `PlatformGuestLayout` | `PlatformGuestLayout` |
| `resources/js/Pages/Auth/ForgotPassword.jsx` | Swapped `GuestLayout` → `PlatformGuestLayout` | `PlatformGuestLayout` |
| `resources/js/Pages/Auth/ResetPassword.jsx` | Swapped `GuestLayout` → `PlatformGuestLayout` | `PlatformGuestLayout` |
| `resources/js/Pages/Auth/VerifyEmail.jsx` | Swapped `GuestLayout` → `PlatformGuestLayout` | `PlatformGuestLayout` |
| `resources/js/Pages/Auth/ConfirmPassword.jsx` | Swapped `GuestLayout` → `PlatformGuestLayout` | `PlatformGuestLayout` |
| `resources/views/app.blade.php` | Removed `website_info` fallback from `$siteTitle`/favicon | `platform_setting` only |

### Unchanged (Correct Architecture Already)

| File | Reason |
|------|--------|
| `AdminHeader.jsx` | SuperAdmin-only component, uses `platform_setting` correctly |
| `AdminSidebar.jsx` | Context-aware admin panel (isSuperAdmin check), not public-facing |
| `AdminFooter.jsx` | Context-aware admin panel (isSuperAdmin check), not public-facing |
| `ShopLayout.jsx` | Pure layout, no branding logic — delegates to child components |
| `AdminLayout.jsx` | Pure layout, no branding logic — delegates to child components |
| `resources/js/Pages/Public/CreateStore.jsx` | Already standalone, uses props from `CreateStoreController` |
| `resources/js/Pages/Storefront/Login.jsx` | Uses `GuestLayout` (now pure `website_info`) |
| `resources/js/Pages/Storefront/Register.jsx` | Uses `GuestLayout` (now pure `website_info`) |
| `resources/js/Pages/Notifications/Index.jsx` | Uses `ShopLayout` (now pure `website_info` via child components) |

## Tests Passed

| Test Suite | Status |
|-----------|--------|
| `PlatformSettingsTest` (9 tests) | PASS |
| `MerchantManagementTest` (4 tests) | PASS |
| `ExampleTest` (unit) | PASS |
| Vite production build (2474 modules) | PASS |

Pre-existing failures (unrelated): Auth, Profile, Promotion tests fail due to missing `tenants` table in test SQLite database.

## Regression Risk

**Low.** All changes are additive/separatory:
- Platform-only components are newly created, never remove existing functionality
- Tenant-only components remove `platform_setting` references that were fallbacks — the primary `website_info` path is unchanged
- Admin panel components (`AdminSidebar`, `AdminFooter`, `AdminHeader`) are untouched
- Blade template removes `website_info` fallback for `$siteTitle`/favicon — this only affects cases where no React page sets `<Head title>`, which is already handled by all pages
