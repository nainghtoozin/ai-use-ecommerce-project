# V3 Tenant Branding Trace Report

**Date:** 2026-06-26

## Root Cause

**`HandleInertiaRequests::share()` resolves `website_info` BEFORE the `Storefront` middleware sets the correct tenant.**

The Inertia `Middleware::handle()` calls `$this->share($request)` **before** `$next($request)`. This means shared props are computed eagerly, before route-specific middleware has run.

### Execution Order

```
1. IdentifyTenant (global web)
   ↳ Tenant::getCurrent() = DEFAULT tenant (slug='default')
   ↳ No URL-slug resolution — falls through all resolvers to getDefault()

2. HandleInertiaRequests::share() ← website_info COMPUTED HERE
   ↳ WebsiteInfo::first() → TenantScope adds WHERE tenant_id = {default_tenant_id}
   ↳ Returns default tenant's WebsiteInfo with site_name = 'ShopMyanmar'
   ↳ Inertia::share('website_info', $defaultTenantWebsiteInfo)
   ↳ **STALE — uses default tenant, not the requested store tenant**

3. $next($request) runs remaining middleware:
   a. CheckUserStatus, CheckMaintenanceMode
   b. Storefront middleware ← tenant CORRECTLY resolved HERE
      ↳ Resolves tenant from store_slug URL parameter
      ↳ app()->instance('current.tenant', $correctTenant)
      ↳ **Too late — website_info already computed**
   c. ValidateTenantBinding
   d. StorefrontController@index
      ↳ Passes correct tenant.name to page component (overrides shared tenant prop)

4. Inertia renders <title inertia> with platform_setting.site_name
   ↳ Browser tab shows "ShopMyanmar" (platform default)
```

## Affected Files

| File | Role |
|------|------|
| `vendor/inertiajs/inertia-laravel/src/Middleware.php:110-130` | Calls `share()` before `$next()` |
| `app/Http/Middleware/HandleInertiaRequests.php:54` | `WebsiteInfo::first()` scoped to default tenant |
| `app/Http/Middleware/IdentifyTenant.php:38` | Falls back to `Tenant::getDefault()` — no URL slug resolution |
| `app/Http/Middleware/Storefront.php:27` | Correctly resolves tenant but runs too late |
| `resources/js/Components/ShopNavbar.jsx:9,36` | Reads `website_info?.site_name` → receives default tenant's name |
| `resources/js/Components/ShopFooter.jsx:27,33` | Reads `website_info?.site_name` → receives default tenant's name |

## Rendering Chain

```
Route:  /store/{slug}
  ↓
IdentifyTenant:   current.tenant = DEFAULT (slug='default')
  ↓
HandleInertiaRequests::share():   website_info = { site_name: 'ShopMyanmar', ... }
  ↓
Storefront:   current.tenant = { slug, name: 'Correct Tenant Name' }
  ↓
StorefrontController@index:   tenant = { name: 'Correct Tenant Name' }
  ↓
Page: Storefront/Index
  ↓  reads: tenant.name (correct) from props
  ↓  reads: website_info?.site_name (WRONG - 'ShopMyanmar') from usePage().props
  ↓
Layout: ShopLayout
  ↓
ShopNavbar:   website_info?.site_name → 'ShopMyanmar' ← BUG
ShopFooter:   website_info?.site_name → 'ShopMyanmar' ← BUG
BrowserTitle: tenant.name → correct (set explicitly by page)
StorefrontHero: store.name || websiteInfo?.site_name → correct (tenant.name first)
```

## Current Branding Source

For `ShopNavbar` and `ShopFooter` on `/store/{slug}`:
- **Actual:** `WebsiteInfo` of the DEFAULT tenant (seeded with `site_name = 'ShopMyanmar'`)
- **Expected:** `WebsiteInfo` of the tenant matching `{slug}`

## Expected Branding Source

- `ShopNavbar` → `website_info` of the CURRENT tenant (by store_slug)
- `ShopFooter` → `website_info` of the CURRENT tenant
- BrowserTitle → `tenant.name` (already correct — set explicitly by page)
- `StorefrontHero` → `store?.name` (already correct — controller prop overrides)

## Minimal Safe Fix

**Target:** `app/Http/Middleware/Storefront.php` — add `Inertia::share('website_info', ...)` after resolving the correct tenant.

**Why this file:** It's the ONLY place where both the correct tenant is known AND the fix is contained to tenant storefront routes only. No impact on platform pages, no schema changes, no UI redesign.

**Fix:**
```php
public function handle(Request $request, Closure $next)
{
    // ... existing resolution code ...

    app()->instance('current.tenant', $tenant);
    $request->merge(['tenant' => $tenant]);

    // Re-share website_info with the correct tenant's data
    $settings = \App\Models\WebsiteInfo::first();
    \Inertia\Inertia::share('website_info', $settings ? $settings->toArray() : []);

    return $next($request);
}
```

`WebsiteInfo::first()` runs after `current.tenant` is set, so `TenantScope` correctly filters to this tenant's WebsiteInfo record.

## Regression Risk

**Very Low.** The fix:
- Only activates on `/store/{slug}` routes (the `Storefront` middleware is exclusive to these routes)
- Uses the exact same `WebsiteInfo::first()` logic — just at the correct time
- Doesn't change any model, schema, UI component, or blade template
- Platform pages are completely unaffected
- If the tenant has no WebsiteInfo record, `$settings` is null, and `website_info` becomes `[]` — the frontend fallback `'My Store'` is shown
