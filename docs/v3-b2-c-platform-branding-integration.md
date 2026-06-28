# V3-B2-C: Platform Branding Integration Report

**Date:** 2026-06-22
**Scope:** Connect `PlatformSetting::current()` values to all platform-level UI surfaces.

---

## Changes by Part

### Part 1: Application Title
**File:** `resources/views/app.blade.php`
- Updated `<title inertia>` to use `$page['props']['platform_setting']['site_name']` as primary source
- Fallback chain: `platform_setting.site_name` → `website_info.site_name` → `'My E-Commerce Store'`
- No hardcoded title — always reads from shared Inertia data

### Part 2: Favicon
**File:** `resources/views/app.blade.php`
- Added `<link rel="icon">` tag sourcing from `platform_setting.favicon`
- Renders `asset('storage/' . $path)` for local paths, raw URL for remote
- Cache-busting `?v={{ time() }}` appended
- **Fallback:** No favicon link rendered when favicon is empty/null

### Part 3: SuperAdmin Navbar (Sidebar Logo)
**File:** `resources/js/Components/AdminSidebar.jsx`
- For SuperAdmin: uses `platform_setting.site_logo` and `platform_setting.site_name`
- For regular admin: unchanged, uses `website_info`
- **Fallback:** If logo missing, falls back to colored icon + `'SuperAdmin'` text

### Part 4: SuperAdmin Login Page
**File:** `resources/js/Layouts/GuestLayout.jsx`
- Now reads `platform_setting.site_logo` and `platform_setting.site_name` first
- Fallback chain: `platform_setting` → `website_info` → `'Electronics Store'`

### Part 5: SuperAdmin Footer
**File:** `resources/js/Components/AdminFooter.jsx`
- For SuperAdmin: displays `platform_setting.site_name` and `platform_setting.support_email` (as mailto link)
- Support email separator and link hidden when email is empty
- For regular admin: unchanged, uses `website_info.site_name`

### Part 6: Public Landing Pages
**Files Modified:**
- `app/Http/Controllers/CreateStoreController.php` — `index()` and `success()` now source `siteName`/`logoUrl` from `PlatformSetting::current()` first, falling back to `WebsiteInfo`
- `resources/js/Pages/Client/Products/Index.jsx` — landing page `siteName` falls back to `platform_setting.site_name`
- `resources/js/Components/AdminHeader.jsx` — SuperAdmin subtitle shows `"{platform_name} — SuperAdmin"` instead of "Manage your store"

### Part 7: Fallbacks
| Setting | Primary Source | Fallback 1 | Fallback 2 |
|---|---|---|---|
| site_name | `platform_setting.site_name` | `website_info.site_name` | `'My E-Commerce Store'` |
| site_logo | `platform_setting.site_logo` | `website_info.logo` | Placeholder icon |
| favicon | `platform_setting.favicon` | Not rendered if empty | — |
| support_email | `platform_setting.support_email` | Not displayed if empty | — |

### Cache
Already handled: `PlatformSetting::clearCache()` is called in `PlatformSettingService::update()`, so favicon/title/logo reflect new values immediately without cache purge.

---

## Files Modified

| File | Part | Change |
|---|---|---|
| `app/Http/Middleware/HandleInertiaRequests.php` | Shared data | Added `'platform_setting' => PlatformSetting::current()->toArray()` |
| `resources/views/app.blade.php` | 1, 2 | Dynamic title + favicon link from PlatformSetting |
| `resources/js/Components/AdminSidebar.jsx` | 3 | SuperAdmin uses `platform_setting` for logo + brand name |
| `resources/js/Layouts/GuestLayout.jsx` | 4 | Login page uses `platform_setting` for logo + name |
| `resources/js/Components/AdminFooter.jsx` | 5 | Footer shows platform name + support email for SuperAdmin |
| `resources/js/Components/AdminHeader.jsx` | 6 | SuperAdmin subtitle dynamic |
| `resources/js/Pages/Client/Products/Index.jsx` | 6 | Landing page `siteName` falls back to `platform_setting` |
| `app/Http/Controllers/CreateStoreController.php` | 6 | Registration pages use `PlatformSetting` for branding |

---

## Branding Sources

| Source | File | Values |
|---|---|---|
| `PlatformSetting` model | `config/filesystems.php` → storage | `site_name`, `site_logo`, `favicon`, `support_email` |
| Shared via HandleInertiaRequests | `props.platform_setting` | All PlatformSetting attributes as array |

---

## Pages Updated

| Page | Component | What Changed |
|---|---|---|
| All Inertia pages | `app.blade.php` | Title + favicon now from PlatformSetting |
| SuperAdmin sidebar | `AdminSidebar.jsx` | Logo + name from PlatformSetting |
| SuperAdmin footer | `AdminFooter.jsx` | Name + support email from PlatformSetting |
| SuperAdmin header | `AdminHeader.jsx` | Subtitle shows platform name |
| Login page | `GuestLayout.jsx` | Logo + name from PlatformSetting |
| Store registration | `CreateStore.jsx` | Name + logo from PlatformSetting |
| Registration success | `StoreRegistrationSuccess.jsx` | Name + logo from PlatformSetting |
| Public landing | `Client/Products/Index.jsx` | Name falls back to PlatformSetting |

---

## Fallback Logic

```
site_name:   platform_setting.site_name → website_info.site_name → 'My E-Commerce Store'
site_logo:   platform_setting.site_logo → website_info.logo → placeholder icon
favicon:     platform_setting.favicon → omitted if null
email:       platform_setting.support_email → hidden if null
```

---

## Tests Passed

- `PlatformSettingsTest`: 9/9 (31 assertions)
- `MerchantManagementTest`: 4/4 (8 assertions)
- Total: 13 passed (39 assertions)

---

## Regression Risk

**Low.** All changes are additive with backward-compatible fallback chains:
- `platform_setting` shared data is new — existing components that don't read it are unaffected
- All existing fallback values preserved as second/third tier
- No migrations, no model changes, no new dependencies
- Non-SuperAdmin flows (tenant admin/storefront) unchanged
