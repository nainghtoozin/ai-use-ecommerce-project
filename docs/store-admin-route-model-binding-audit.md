# Store Admin Route Model Binding Audit

## Error
```
AdminProductController::show()
Argument #1 ($product) must be Product, string given.
```

Product View and Product Edit fail with this error after store admin route migration.

---

## Root Cause: Route Ordering Bug

In `routes/storefront-admin.php`, the **search routes are defined AFTER parameterized `{product}` / `{order}` routes**, causing GET `/search` to match the `show` route with `{param} = "search"`.

### Products — Lines 62–73

```
62:  GET  /products              → index
63:  GET  /products/type-select  → typeSelect
64:  GET  /products/create       → create
65:  POST /products              → store
66:  GET  /products/{product}    → show       ← CATCHES /products/search
67:  GET  /products/{product}/edit → edit
68:  PUT  /products/{product}    → update
69:  DELETE /products/{product}  → destroy
70:  GET  /products/search       → search     ← NEVER REACHED (GET)
```

When frontend calls `adminUrl('/admin/products/search')` → `/store/{slug}/admin/products/search`:
1. **Line 66** matches first: `{product} = "search"`
2. Implicit binding runs: `Product::resolveRouteBinding("search")`
3. MySQL coerces `"search"` to `0` → queries `WHERE id = 0`
4. No product with `id = 0` → `ModelNotFoundException` → 404 (or string passed through depending on error handling context)
5. **Line 70** is never reached for GET requests

### Orders — Lines 76–78

```
76:  GET  /orders           → index
77:  GET  /orders/{order}   → show    ← CATCHES /orders/search
78:  GET  /orders/search    → search  ← NEVER REACHED (GET)
```

Same bug — `/orders/search` is matched by `/orders/{order}` with `{order} = "search"`.

### Affected routes (same pattern applies)

| Resource | Search route line | `{param}` route line | Bug? |
|----------|------------------|----------------------|------|
| products | 70 | 66 (show) | **YES** |
| orders | 78 | 77 (show) | **YES** |
| categories | 108 | — (no show, only edit at 105) | Safe |
| banners | 117 | — (no show, only edit at 114) | Safe |
| promotions | 128 | — (no show, only edit at 123) | Safe |
| coupons | 152 | — (no show, only edit at 149) | Safe |

### Legacy routes (`routes/web.php`) are correctly ordered

```
237:  GET /products/search        ← BEFORE
254:  GET /products/{product}     ← AFTER  (correct)
```

In the legacy file, all search routes (lines 237–241) are grouped **before** all parameterized routes (lines 254+).

---

## Controller Method Signatures (all correct)

| Method | Signature | File |
|--------|-----------|------|
| `show()` | `show(Product $product)` | `AdminProductController.php:314` |
| `edit()` | `edit(Product $product)` | `AdminProductController.php:277` |
| `update()` | `update(UpdateProductRequest $request, Product $product)` | `AdminProductController.php:348` |
| `destroy()` | `destroy(Product $product)` | `AdminProductController.php:454` |

All four methods use `Product $product` — the parameter name `$product` matches the route parameter `{product}`. Route model binding is correctly configured.

---

## Route Parameter Definitions (both files)

| Route file | URI | Parameters |
|------------|-----|------------|
| `routes/web.php` (legacy) | `/admin/products/{product}` | `{product}` |
| `routes/storefront-admin.php` | `/store/{store_slug}/admin/products/{product}` | `{store_slug}`, `{product}` |

Both use `{product}` as the bindable parameter — no naming conflict.

---

## Implicit Binding Setup

- **No explicit** `Route::model()` or `Route::bind()` calls anywhere in the codebase.
- **No** `resolveRouteBinding()` override on Product model.
- **No** `getRouteKeyName()` override — uses default `id`.
- Binding relies entirely on Laravel's default `SubstituteBindings` middleware (part of the `web` middleware group).
- Product model uses `TenantAware` trait → adds `TenantScope` global scope, but this only filters when `Tenant::getCurrent()` returns non-null.

---

## Middleware Timing Issue (Contributory)

From `bootstrap/app.php`, the `web` group middleware + appends:

| Order | Middleware | Notes |
|-------|-----------|-------|
| 1–5 | Default web (Cookies, Session, CSRF) | |
| **6** | **`SubstituteBindings`** | **Model binding happens here** |
| 7 | `IdentifyTenant` | Sets `current.tenant` (runs AFTER binding) |
| 8 | `HandleInertiaRequests` | |
| 9 | `CheckUserStatus` | |
| 10 | `CheckMaintenanceMode` | |
| 11+ | `storefront`, `auth`, `role:admin`, `tenant.valid` | (storefront group middleware) |

**`SubstituteBindings` runs at step 6**, before `IdentifyTenant` (step 7) and `Storefront` (step 11). During route model binding, `Tenant::getCurrent()` returns `null`, so the `TenantScope` does **not** filter by `tenant_id`. Binding resolves products globally across all tenants.

This means:
- A product with `id = 5` in *any* tenant will be found during binding
- No cross-tenant protection during route model binding
- Tenant isolation relies on controllers scoping queries explicitly (which they do via `$user->tenant_id`)

---

## Broken `route:list` (Contributory)

`php artisan route:list` crashes with:
```
ReflectionException: Class "AdminOrderOverrideController" does not exist
```

Referenced in:
- `routes/web.php` lines 274–275
- `routes/storefront-admin.php` lines 87–88

No `AdminOrderOverrideController.php` exists in `app/Http/Controllers/Admin/`. This prevents using `route:list` for debugging but does **not** prevent routes from functioning — PHP `::class` on non-existent classes does not trigger autoloading at definition time.

---

## Affected Controllers & Methods

| Controller | Method | Route Parameter | Binding | Status |
|------------|--------|----------------|---------|--------|
| `AdminProductController` | `show()` | `{product}` | `Product $product` | ✅ Correct type-hint |
| `AdminProductController` | `edit()` | `{product}` | `Product $product` | ✅ Correct type-hint |
| `AdminProductController` | `update()` | `{product}` | `UpdateProductRequest $request, Product $product` | ✅ Correct type-hint |
| `AdminProductController` | `destroy()` | `{product}` | `Product $product` | ✅ Correct type-hint |
| `AdminOrderController` | `show()` | `{order}` | (would have same search-vs-{order} bug) | ⚠️ Same ordering issue |
| `AdminOrderOverrideController` | both methods | `{order}` | **Class does not exist** | 🔴 Missing class |

---

## Fix

1. **Reorder routes** in `routes/storefront-admin.php`: move search routes BEFORE parameterized `{product}` / `{order}` routes:
   - Move `Route::get('/products/search', ...)` to before line 66
   - Move `Route::get('/orders/search', ...)` to before line 77
2. **Add `whereNumber()` constraint** to `{product}` and `{order}` parameters as a safety net:
   ```php
   Route::get('/products/{product}', ...)->whereNumber('product');
   Route::get('/orders/{order}', ...)->whereNumber('order');
   ```
3. **Create missing controller** or remove route references for `AdminOrderOverrideController`
4. **Consider** reordering `SubstituteBindings` to run after `IdentifyTenant` in the web middleware stack to enable tenant-scoped binding (for cross-tenant protection)

---

## Summary

| Finding | Severity | Status |
|---------|----------|--------|
| `/products/search` after `/products/{product}` | **🔴 Critical** | Fix required |
| `/orders/search` after `/orders/{order}` | **🔴 Critical** | Fix required |
| `AdminOrderOverrideController` missing | **🟡 Medium** | Breaks `route:list`, causes 500 on those routes at runtime |
| `SubstituteBindings` runs before tenant middleware | **🟡 Medium** | No tenant isolation during binding |
| All controller type-hints correct | ✅ | No change needed |
| Route parameter names match controller vars | ✅ | No change needed |
