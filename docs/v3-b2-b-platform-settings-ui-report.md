# V3-B2-B: SuperAdmin Platform Settings UI Report

**Date:** 2026-06-22
**Scope:** Create SuperAdmin-only Platform Settings page with file uploads, toggle switches, and full CRUD tests.

---

## Files Created

### Controller: `app/Http/Controllers/SuperAdmin/SuperAdminPlatformSettingController.php`
- `index()` — returns Inertia page with `PlatformSettingService::get()`
- `update()` — validates input, handles file uploads via `ImageService`, saves via `PlatformSettingService::update()`
- Injects `PlatformSettingService` and `ImageService` via constructor
- File handling: uploads logo/favicon to `platform-settings/` folder, deletes old files when replaced
- Boolean fields use `$request->boolean()` for reliable casting

### React Page: `resources/js/Pages/SuperAdmin/PlatformSettings/Index.jsx`
- 3 sections: General Information, Branding, System Settings
- General: Platform Name (required text), Support Email (email)
- Branding: Platform Logo + Favicon using `ImageUpload` component with drag-and-drop, preview, and remove
- System Settings: Toggle switches (custom `role="switch"` buttons) for Maintenance Mode and Registration Enabled
- Submits via `router.post()` with `FormData` and `forceFormData: true`
- Uses `AdminLayout` with consistent card/form styling
- Responsive grid layout (1-col mobile, 2-col desktop for branding section)

### Test: `tests/Feature/PlatformSettingsTest.php`
- 9 tests, 31 assertions
- Covers: view page, update platform name, update support email, upload logo, upload favicon, toggle maintenance mode, toggle registration, persistence after refresh, guest access denied
- Uses `DatabaseTransactions` + minimal SQLite schema pattern (matching `MerchantManagementTest`)
- File upload tests use `Storage::fake('public')` + `UploadedFile::fake()->image()`

---

## Files Modified

| File | Change |
|---|---|
| `routes/web.php` | Added GET and POST routes for `/superadmin/platform-settings` under SuperAdmin group |
| `database/seeders/PermissionSeeder.php` | Added `platform.settings.view` and `platform.settings.update` permissions |
| `resources/js/Components/AdminSidebar.jsx` | Added "Platform Settings" menu item under System Management |

---

## Routes Added

| Method | URI | Name | Handler |
|---|---|---|---|
| GET | `/superadmin/platform-settings` | `superadmin.platform-settings.index` | `SuperAdminPlatformSettingController@index` |
| POST | `/superadmin/platform-settings` | `superadmin.platform-settings.update` | `SuperAdminPlatformSettingController@update` |

---

## Permissions Added

- `platform.settings.view`
- `platform.settings.update`

---

## Architecture Decisions

1. **POST instead of PUT** for the update route — required for multipart FormData file uploads. Laravel handles this pattern cleanly.
2. **ImageService reused** — same upload/delete/resolveTenant pattern as existing `SettingsController`. SuperAdmin uploads bypass tenant storage tracking (tenant is null, early return).
3. **ImageUpload component** — existing reusable component from `@/Components/ImageUpload` used for both logo and favicon with full drag-and-drop, preview, and removal.
4. **No file input for logo/favicon initial value** — `ImageUpload` handles both string URLs (existing) and File objects (new uploads) via `getImagePreviewUrl()`.

---

## Test Results

```
Tests:    9 passed (31 assertions)
  ✓ superadmin can view platform settings page
  ✓ superadmin can update platform name
  ✓ superadmin can update support email
  ✓ superadmin can upload logo
  ✓ superadmin can upload favicon
  ✓ superadmin can toggle maintenance mode
  ✓ superadmin can toggle registration enabled
  ✓ persistence after refresh
  ✓ guest cannot access platform settings
```

Existing `MerchantManagementTest`: 4 passed (no regression).

---

## Regression Risk

**Low.** All changes are additive:
- New controller, new routes, new React page — no existing code modified except adding permissions and sidebar menu item
- `PermissionSeeder` change is additive (new permission strings)
- `AdminSidebar.jsx` change is additive (new menu item)
- No migrations, no model changes, no service changes
- `WebsiteInfo`, `TenantBootstrapService`, `Subscription`, `Plans` — untouched
