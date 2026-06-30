# V3 Auth Route Regression Fix

## Root Cause

Commit `9eb664f6` ("test", 2026-06-29) changed the route name on line 132 of `routes/web.php` from `admin.login` to `storefront.admin.login`:

```php
// routes/web.php:132 (inside storefront group)
Route::get('/admin/login', ...)->name('storefront.admin.login');  // BROKEN
```

This route sits inside a route group with `->name('storefront.')` prefix (line 118). Laravel prepends the group name prefix to the route name, so the actual registered route became `storefront.storefront.admin.login` — the `storefront.` segment is doubled.

All 5 callers reference `route('storefront.admin.login', ...)`, which does not match the doubled name.

**Before (working):** `->name('admin.login')` → actual: `storefront.admin.login`

**After (broken):** `->name('storefront.admin.login')` → actual: `storefront.storefront.admin.login`

## Files Modified

| File | Change |
|------|--------|
| `routes/web.php:132` | Reverted `->name('storefront.admin.login')` → `->name('admin.login')` |

No other files were modified. No new routes were added. No routes were removed.

## Redirect Flow (After Fix)

```
Merchant logs in via /store/{slug}/admin/login
  → POST /store/{slug}/admin/login (StorefrontLoginController@store)
  → AuthenticatedSessionController@store
  → redirect()->route('storefront.admin.dashboard', ['store_slug' => $slug])
  → /store/{slug}/admin/dashboard

Merchant clicks logout (POST /logout with context=admin, store_slug={slug})
  → AuthenticatedSessionController@destroy()
  → match ($context):
       'admin' => $storeSlug
         ? redirect()->route('storefront.admin.login', ['store_slug' => $storeSlug])  ← FIXED
         : redirect()->route('admin.login')
  → /store/{slug}/admin/login ✓
```

## Callers of `storefront.admin.login`

| File | Line | Usage |
|------|------|-------|
| `routes/web.php` | 132 | Route definition (fixed) |
| `app/Http/Controllers/Auth/AuthenticatedSessionController.php` | 124 | Logout redirect for merchant admin |
| `app/Http/Middleware/CheckTenantAccess.php` | 37 | Cross-tenant access violation redirect |
| `app/Notifications/WelcomeOwner.php` | 23 | Welcome email login link |
| `app/Http/Controllers/CreateStoreController.php` | 86 | Onboarding complete page login URL |

## Tests Performed

| Test | Result |
|------|--------|
| `route('storefront.admin.login', ...)` generates correct URL `store/{slug}/admin/login` | PASS |
| `route('storefront.login', ...)` | PASS |
| `route('superadmin.login')` | PASS |
| `route('admin.login')` | PASS |
| `route('admin.dashboard')` | PASS |
| `route('storefront.admin.dashboard', ...)` | PASS |
| `route('storefront.index', ...)` | PASS |
| `route('client.dashboard')` | PASS |
| Merchant login via `store/{slug}/admin/login` → dashboard | PASS (HTTP 302→200) |
| Merchant logout → redirect to `store/{slug}/admin/login` | PASS (HTTP 302 → correct URL) |

## Regression Test

```bash
# Verify route name
php artisan route:list --path="store/{store_slug}/admin/login"
# Expected: storefront.admin.login

# Verify route generation
php artisan tinker --execute="echo route('storefront.admin.login', ['store_slug' => 'test']);"
# Expected: .../store/test/admin/login
```

## Remaining Risks

1. **No unnamed POST login routes**: Lines 129 and 133 in `routes/web.php` both omit `->name()`, giving both a name of `storefront.` (empty after prefix). This is a pre-existing issue and does not affect the fix, but could cause route name collisions if these routes are ever referenced by name.

2. **CheckTenantAccess route name check**: The middleware checks `Str::contains($route->getName(), 'storefront.admin.')`. After fix, the login route name is `storefront.admin.login` which correctly contains `storefront.admin.`. No change needed.

3. **Ziggy route list**: `resources/js/ziggy.js` does not include the `storefront.admin.login` entry. Client-side navigation to this URL uses hardcoded paths (e.g., `adminUrl('/admin/login')`) rather than named routes, so this is not affected.
