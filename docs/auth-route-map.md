# Auth Route Map

**Date:** 2026-06-09  
**Scope:** Canonical login/logout routes, redirect destinations, removed legacy dead routes

---

## Login Routes

| Route | Name | Controller | Inertia Page | Audience | Status |
|-------|------|-----------|--------------|----------|--------|
| `GET /login` | `login` | `AuthenticatedSessionController@create` | `Auth/Login` | All users (role-agnostic) | Existing |
| `GET /superadmin/login` | `superadmin.login` | `AuthenticatedSessionController@create` | `Auth/Login` | SuperAdmin | **Added** |
| `GET /admin/login` | `admin.login` | `AuthenticatedSessionController@create` | `Auth/Login` | Legacy admin (fallback) | **Added** |
| `GET /store/{slug}/admin/login` | `storefront.admin.login` | `AuthenticatedSessionController@create` | `Auth/Login` | Store admin | **Added** |
| `GET /store/{slug}/login` | `storefront.login` | `StorefrontLoginController@create` | `Storefront/Login` | Store customer | Existing |

### Login POST endpoints (unchanged)

| Route | Controller | Notes |
|-------|-----------|-------|
| `POST /login` | `AuthenticatedSessionController@store` | All login pages POST here. Redirect depends on user role. |
| `POST /store/{slug}/login` | `StorefrontLoginController@store` | Tenant-scoped login. Redirect depends on user role. |

---

## Logout Route

| Route | Controller | File |
|-------|-----------|------|
| `POST /logout` | `AuthenticatedSessionController@destroy` | `routes/auth.php:57` |

### Frontend Logout Calls

| Component | Context Sent | `store_slug` Sent | Notes |
|-----------|-------------|-------------------|-------|
| `AdminSidebar.jsx` | `'superadmin'` or `'admin'` | `tenant?.slug` | Now sends correct context for SuperAdmin |
| `ShopNavbar.jsx` | `'storefront'` or `''` | `storeSlug` | Unchanged (was correct) |
| `AppLayout.jsx` | `'superadmin'` or `'admin'` or `''` | `tenant?.slug` | Now sends proper slug + superadmin context |

---

## Logout Redirect Destinations

All logout redirects now use **named routes** that exist:

| Context | `store_slug` | Redirect Destination | Route Name | Status |
|---------|-------------|---------------------|------------|--------|
| `superadmin` | any | `GET /superadmin/login` | `superadmin.login` | **Fixed** (was 404) |
| `admin` | present | `GET /store/{slug}/admin/login` | `storefront.admin.login` | **Fixed** (was 404) |
| `admin` | null/empty | `GET /admin/login` | `admin.login` | **Fixed** (was 404) |
| `storefront` | present | `GET /store/{slug}` | `storefront.index` | Already worked |
| `storefront` | null/empty | `GET /` | (home) | Already worked |
| default + SuperAdmin | any | `GET /superadmin/login` | `superadmin.login` | **Fixed** (was 404) |
| default + has slug | present | `GET /store/{slug}` | `storefront.index` | Already worked |
| default + neither | null/empty | `GET /` | (home) | Already worked |

---

## Login Success Redirects (unchanged, noted for reference)

| Login Endpoint | User Role | Redirect |
|---------------|-----------|----------|
| `POST /login` | Admin | `storefront.admin.dashboard` (tenant from `Tenant::getCurrent()`) |
| `POST /login` | SuperAdmin | Same as admin (`isAdmin()` returns true) |
| `POST /login` | Customer | `client.dashboard` |
| `POST /store/{slug}/login` | Admin/SuperAdmin | `storefront.admin.dashboard` |
| `POST /store/{slug}/login` | Customer | `storefront.index` |

---

## Routes Added

All three routes were added as `guest`-middleware GET routes that render `Auth/Login`:

| File | Line | Route |
|------|------|-------|
| `routes/web.php` | ~129 (before AUTHENTICATED ROUTES) | `GET /superadmin/login` → `superadmin.login` |
| `routes/web.php` | ~129 (before AUTHENTICATED ROUTES) | `GET /admin/login` → `admin.login` |
| `routes/web.php` | ~108 (inside storefront group) | `GET /store/{slug}/admin/login` → `storefront.admin.login` |

## Routes Removed (Legacy Dead Routes)

No routes were removed. The following controller methods are **unused by any route** (dead code, no route registered):

| Controller Method | Inertia Page | Notes |
|------------------|-------------|-------|
| `AdminController::showLogin()` | `Admin/Auth/Login` (page doesn't exist) | Replaced by `AuthenticatedSessionController@create` rendering `Auth/Login` |
| `ClientController::showLogin()` | `Auth/Login` | No route calls this. The root `GET /login` uses `AuthenticatedSessionController@create`. |

---

## Files Modified

| File | Change |
|------|--------|
| `routes/web.php` | Added 3 GET login routes |
| `app/Http/Controllers/Auth/AuthenticatedSessionController.php` | Changed `destroy()` + `fallbackLogoutRedirect()` to use named routes |
| `resources/js/Components/AdminSidebar.jsx` | Fixed logout to send `'superadmin'` context for SuperAdmin, `'admin'` for tenant admin |
| `resources/js/Layouts/AppLayout.jsx` | Added `tenant` extraction; fixed logout to send proper `store_slug` and superadmin context |

## Verification

- `php artisan route:list --name=login` shows all 5 login routes (3 added + 2 existing)
- `npx vite build` passes (no frontend compilation errors)
- All logout redirects point to existing named routes — no more 404s
