# Admin Profile Tenant Route Fix

## Bug
Navigating to **Profile** from `/store/{slug}/admin/dashboard` went to `/profile`, losing the tenant context.

## Root Cause
- No profile routes existed under the `store/{store_slug}/admin` prefix in `routes/storefront-admin.php`
- Hardcoded `/profile` links in React admin components bypassed the `adminUrl()` helper
- `adminUrl()` only transformed paths starting with `/admin/`, not `/profile`
- `ProfileController::update()` always redirected to `route('profile.edit')` (`/profile`) regardless of tenant context

## Files Modified

### 1. `routes/storefront-admin.php`
- Added `ProfileController` import
- Added 3 profile routes inside the storefront admin prefix:
  - `GET /profile` → `ProfileController@edit` (name: `storefront.admin.profile.edit`)
  - `PATCH /profile` → `ProfileController@update` (name: `storefront.admin.profile.update`)
  - `DELETE /profile` → `ProfileController@destroy` (name: `storefront.admin.profile.destroy`)

### 2. `resources/js/Utils/adminUrl.js`
- Extended `adminUrl()` to handle `/profile` paths:
  - When a store slug is detected and path is `/profile`, returns `/store/{slug}/admin/profile`
  - Existing `/admin/*` transformation unchanged
  - Non-storefront contexts return `/profile` unchanged

### 3. `resources/js/Components/AdminSidebar.jsx`
- Line 344: Changed `href="/profile"` → `href={adminUrl('/profile')}`

### 4. `resources/js/Layouts/AppLayout.jsx`
- Line 193: Changed `href="/profile"` → `href={adminUrl('/profile')}` (sidebar profile link)
- Line 302: Changed `href="/profile"` → `href={adminUrl('/profile')}` (user dropdown menu)

### 5. `resources/js/Pages/Profile/Edit.jsx`
- Added `import { adminUrl } from '@/Utils/adminUrl'`
- Line 40: Changed `profileForm.patch('/profile', ...)` → `profileForm.patch(adminUrl('/profile'), ...)`
- Line 54: Changed `deleteForm.delete('/profile', ...)` → `deleteForm.delete(adminUrl('/profile'), ...)`

### 6. `app/Http/Controllers/ProfileController.php`
- `update()` method: After saving, checks for `$request->route('store_slug')`
  - If present (storefront admin context): redirects to `route('storefront.admin.profile.edit', ['store_slug' => $slug])`
  - If absent (legacy context): redirects to `route('profile.edit')` (original behavior)

## Hardcoded Routes Found

| Location | Old Route | New Route | Fixed |
|---|---|---|---|
| `AdminSidebar.jsx:344` | `/profile` | `adminUrl('/profile')` → `/store/{slug}/admin/profile` | ✅ |
| `AppLayout.jsx:193` | `/profile` | `adminUrl('/profile')` → `/store/{slug}/admin/profile` | ✅ |
| `AppLayout.jsx:302` | `/profile` | `adminUrl('/profile')` → `/store/{slug}/admin/profile` | ✅ |
| `Profile/Edit.jsx:40` | `/profile` (PATCH) | `adminUrl('/profile')` → `/store/{slug}/admin/profile` | ✅ |
| `Profile/Edit.jsx:54` | `/profile` (DELETE) | `adminUrl('/profile')` → `/store/{slug}/admin/profile` | ✅ |
| `ProfileController.php:37` | `route('profile.edit')` (redirect) | Detects store_slug → `route('storefront.admin.profile.edit')` | ✅ |
| `admin/partials/navbar.blade.php:33` | `route('profile.edit')` | N/A (legacy Blade, no tenant context) | ⏭️ |
| `layouts/navigation.blade.php:31,77` | `route('profile.edit')` | N/A (default Breeze layout) | ⏭️ |
| `client/components/navbar.blade.php:69` | `route('profile.edit')` | N/A (legacy client nav) | ⏭️ |

## Routes Fixed

| Route Name | Path | Action |
|---|---|---|
| `storefront.admin.profile.edit` | `/store/{slug}/admin/profile` (GET) | `ProfileController@edit` |
| `storefront.admin.profile.update` | `/store/{slug}/admin/profile` (PATCH) | `ProfileController@update` |
| `storefront.admin.profile.destroy` | `/store/{slug}/admin/profile` (DELETE) | `ProfileController@destroy` |

## Manual Verification Result

**Test A: Profile link from storefront admin**
1. Navigate to `/store/testshop/admin/dashboard` ✅
2. Click "Profile" in sidebar → `/store/testshop/admin/profile` ✅ (tenant context preserved)
3. Profile form renders correctly ✅

**Test B: Save Profile**
1. Edit name on `/store/testshop/admin/profile` ✅
2. Click "Save" → PATCH to `/store/testshop/admin/profile` ✅
3. Redirect remains at `/store/testshop/admin/profile` ✅ (tenant context preserved)

**Test C: Logout**
1. Logout from `/store/testshop/admin/dashboard` → redirects to `/store/testshop/admin/login` ✅ (unchanged, handled by Step 4 fix)

**Test D: Legacy admin context (no tenant)**
1. Navigate to `/admin/dashboard` ✅
2. Click "Profile" → `/profile` ✅ (original behavior preserved)
3. Save profile → redirect to `/profile` ✅ (original behavior preserved)
