# V3 Tenant WebsiteInfo Timing Fix

**Date:** 2026-06-26

## Root Cause Fixed

`HandleInertiaRequests::share()` computes `website_info` before the `Storefront` middleware resolves the correct tenant from `store_slug`. Inertia's `Middleware::handle()` calls `share()` at line 115 **before** `$next($request)` at line 122. At that point, `IdentifyTenant` had set `current.tenant` to the default tenant (no URL-slug resolver), so `WebsiteInfo::first()` returned the default tenant's record.

## Fix

**File modified:** `app/Http/Middleware/Storefront.php`

After resolving the correct tenant from `store_slug` and setting `current.tenant`, the middleware now re-shares `website_info`:

```php
$settings = WebsiteInfo::first();
\Inertia\Inertia::share('website_info', $settings ? $settings->toArray() : []);
```

`WebsiteInfo::first()` is scoped by `TenantScope` to the now-correct `current.tenant`, returning the requested store's branded data instead of the default tenant's.

## Tests Passed

| Test Suite | Status |
|-----------|--------|
| `PlatformSettingsTest` (9 tests) | PASS |
| `MerchantManagementTest` (4 tests) | PASS |

(Pre-existing SQLite test failures unrelated.)

## Regression Risk

**Very Low.** Fix is contained to `Storefront` middleware which only runs on `/store/{slug}` routes. Platform pages, admin pages, and all other routes are completely unaffected. Uses the same `WebsiteInfo::first()` query — just at the correct point in the middleware pipeline. No schema, model, UI, or blade changes.
