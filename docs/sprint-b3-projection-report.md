# Sprint B.3 — Identity Projection Report

**Date:** 2026-07-12
**Objective:** Fix identity presentation across admin UI — Display Name, Role, Avatar, Badges, and Counts.

## Files Modified

| File | Change |
|------|--------|
| `app/Models/Account.php:142-144` | Added SuperAdmin fallback in `getDisplayName()` — returns `'Super Admin'` when no `name` column and user is SuperAdmin (prevents showing email as display name) |
| `database/seeders/RoleAndPermissionSeeder.php:173` | Added `'name' => 'Super Admin'` to Account `updateOrCreate` so the SuperAdmin Account record has a proper name |
| `app/Auth/IdentityProjection.php:55` | Added `'profile_image_url'` to projection (frontend components check `profile_image_url` but it was missing, causing perpetual initials fallback) |
| `resources/js/Pages/Admin/Users/Index.jsx:168` | Changed `'N/A'` fallback to `'—'` (em-dash) for cleaner display when role_name is null |
| `resources/js/Pages/Admin/Users/Show.jsx:58` | Same `'N/A'` -> `'—'` fix |
| `resources/js/Pages/SuperAdmin/Tenants/Show.jsx:139` | Same `'N/A'` -> `'—'` fix |
| `resources/js/Pages/SuperAdmin/Subscriptions/Show.jsx:554` | Same `'N/A'` -> `'—'` fix |

## Issues Found & Fixed

### 1. SuperAdmin Display Name shows email (Fixed)
- **Root Cause:** `RoleAndPermissionSeeder` created the SuperAdmin Account without setting `name`. `Account::getDisplayName()` fell through to `return $this->email`.
- **Fix:** Added `'name' => 'Super Admin'` to the seeder + `isSuperAdmin()` guard in `getDisplayName()` for safety.
- **Impact:** `auth.user.name` (used by navbar, sidebar, headers) now shows "Super Admin" instead of "admin@shop.com".

### 2. `profile_image_url` missing from IdentityProjection (Fixed)
- **Root Cause:** The projection sent `profile_image` (raw storage path) but the frontend components (`Users/Index.jsx`, `Users/Show.jsx`) check `profile_image_url`. Both `User` and `Account` models have the `getProfileImageUrlAttribute` accessor that resolves via `ImageService::url()`.
- **Fix:** Added `'profile_image_url' => $user->profile_image_url` to the projection.
- **Impact:** Profile images now render correctly when uploaded.

### 3. Role name `'N/A'` fallback in frontend (Fixed)
- **Root Cause:** Four frontend components displayed `'N/A'` when `role_name` was null. This was misleading since it looks like a value rather than a placeholder.
- **Fix:** Changed to em-dash `'—'` across all four affected files.
- **Impact:** Cleaner display when role_name is null (edge case for users without memberships or roles).

### 4. Wishlist not available for Account users (Deferred)
- **Root Cause:** `wishlistItems()` relationship exists only on `User`, not `Account`. The `wishlist` table references `users.id`. Supporting Account users requires schema migration to polymorphic `morphs()` or dual-column approach.
- **Fix:** None — deferred to Phase 7 schema migration (13+ models affected).
- **Status:** `HandleInertiaRequests` already returns 0 for Account users. No action until Phase 7.

### 5. Role Count and Permission Count (Verified — No Change Needed)
- **Analysis:** `RoleController::index()` and `RoleController::show()` already use `->withCount(['memberships' => ...])` in Account mode, mapped to `users_count` via `$role->memberships_count ?? 0`.
- **Verdict:** Correct as-is.

## Summary

All four critical identity presentation gaps addressed. The SuperAdmin no longer sees their email as the display name, profile images render correctly, and role name placeholders use cleaner em-dash notation. Wishlist for Account users remains blocked on Phase 7 schema migration.
