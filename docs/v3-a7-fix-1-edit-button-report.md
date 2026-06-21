# V3-A7-FIX-1 Edit Button Audit & Fix Report

## Status: Completed

---

## Root Cause

The seeders create permissions named `categories.edit`, `brands.edit`, and `units.edit`, but both the frontend Index pages and backend controllers check for `*.update` permissions:

| Permission | Seeder Creates | Frontend/Backend Checks |
|-----------|---------------|------------------------|
| categories | `categories.edit` | `categories.update` |
| brands | `brands.edit` | `brands.update` |
| units | `units.edit` | `units.update` |

Users assigned roles with `*.edit` permissions never see the Edit button because the `can('*.update')` check in the frontend always returns `false`.

---

## Files Modified

| File | Lines | Change |
|------|-------|--------|
| `database/seeders/PermissionSeeder.php` | 47, 100, 106 | `*.edit` → `*.update` for categories, units, brands |
| `database/seeders/RoleAndPermissionSeeder.php` | 36, 55, 61 | `*.edit` → `*.update` for categories, units, brands |

---

## Permission Checks Fixed

| Resource | Before (Seeded) | After (Seeded) |
|----------|----------------|----------------|
| Categories | `categories.edit` | `categories.update` |
| Units | `units.edit` | `units.update` |
| Brands | `brands.edit` | `brands.update` |

No frontend or controller changes needed — they already use `*.update`.

---

## Edit Buttons Visible

After re-seeding (`php artisan db:seed`):
1. `PermissionSeeder` creates `categories.update`, `brands.update`, `units.update` via `firstOrCreate()`
2. `RoleAndPermissionSeeder` assigns these to the admin role via `syncPermissions()`
3. Users with admin role have `*.update` permissions
4. Frontend `can('*.update')` returns `true` → Edit button renders

---

## Tests Passed

| Test Suite | Results |
|------------|---------|
| `MerchantManagementTest` (4) | ✅ All passed |
| `StorefrontRegistrationTest` (5) | ✅ All passed |

---

## Regression Risk

Low. Only seeder files modified — no production application code, routes, controllers, or frontend files changed.
- Existing databases keep the old `*.edit` permissions (harmless, unused)
- Re-seeding adds `*.update` permissions without removing `*.edit`
- Fresh installs get only `*.update` (correct from the start)
- No impact on user authentication, role hierarchy, or data integrity
