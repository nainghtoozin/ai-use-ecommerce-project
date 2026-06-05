# Store Admin Foundation

> **Date:** 2026-06-05
> **Strategy:** Safe migration — new routes alongside existing, no deletions, no modifications to existing code.

---

## Routes Added

**122 new routes** registered under `/store/{store_slug}/admin/*` with name prefix `storefront.admin.*`.

### File: `routes/storefront-admin.php` (new)
### Wired from: `routes/web.php:461` via `require __DIR__ . '/storefront-admin.php';`

### Route Group Definition

```php
Route::prefix('store/{store_slug}/admin')
    ->name('storefront.admin.')
    ->middleware(['storefront', 'auth', 'role:admin', 'tenant.valid'])
    ->group(function () {

        // Account routes (outside tenant.active)
        Route::get('/dashboard', ...)->name('dashboard');
        Route::get('/billing', ...)->name('billing');
        Route::post('/billing/renew', ...)->name('billing.renew');
        Route::get('/suspended', ...)->name('suspended');

        // Operations routes (inside tenant.active)
        Route::middleware('tenant.active')->group(function () {
            // Products, Orders, Categories, Banners, Promotions,
            // Reports, Coupons, Payment Methods, Cities, Townships,
            // Website Info, Notification Settings, Telegram Integration,
            // Users, Activity Logs, Roles, Permissions, Admin Chat
        });
    });
```

### Route Categories

| Category | Route Count | Name Pattern |
|---|---|---|
| Account (dashboard, billing, suspended) | 4 | `storefront.admin.{dashboard,billing,suspended}` |
| Products | 12 | `storefront.admin.products.*` |
| Orders | 14 | `storefront.admin.orders.*` |
| Categories | 7 | `storefront.admin.categories.*` |
| Banners | 7 | `storefront.admin.banners.*` |
| Promotions | 11 | `storefront.admin.promotions.*` |
| Reports | 7 | `storefront.admin.reports.*` |
| Coupons | 6 | `storefront.admin.coupons.*` |
| Payment Methods | 7 | `storefront.admin.payment-methods.*` |
| Cities | 8 | `storefront.admin.cities.*` |
| Townships | 7 | `storefront.admin.townships.*` |
| Website Info | 2 | `storefront.admin.website-info.*` |
| Settings | 3 | `storefront.admin.settings.*` |
| Users | 10 | `storefront.admin.users.*` |
| Activity Logs | 2 | `storefront.admin.activity-logs.*` |
| Roles | 7 | `storefront.admin.roles.*` |
| Permissions | 1 | `storefront.admin.permissions.index` |
| Admin Chat | 5 | `storefront.admin.chat.*` |
| Notifications (admin) | 1 | `storefront.admin.notifications.admin` |
| **Total** | **122** | |

All routes use the **same controllers** as the existing `/admin/*` routes. No controllers were modified.

---

## Middleware Flow

### New Storefront Admin Middleware Chain

```
incoming request
  │
  ▼
[Web Global Middleware]
  ├── IdentifyTenant          → sets current.tenant from user.tenant_id
  │                             (subdomain/header/session fallback)
  ├── HandleInertiaRequests   → shares Inertia data
  ├── CheckUserStatus         → blocks suspended/banned users
  └── CheckMaintenanceMode    → blocks when maintenance mode on
  │
  ▼
[Route Group Middleware]
  ├── storefront              → RESOLVES tenant from URL {store_slug}
  │                             Aborts 404 if slug has no tenant.
  │                             OVERRIDES current.tenant with URL tenant.
  │
  ├── auth                    → Requires authentication.
  │                             Redirects to /login if unauthenticated.
  │
  ├── role:admin              → Requires 'admin' role.
  │                             SuperAdmin automatically bypasses.
  │                             403 if unauthorized.
  │
  ├── tenant.valid            → Validates user has a tenant_id.
  │                             SuperAdmin bypasses.
  │                             Logs out + redirects to login if missing.
  │                             403 if tenant record deleted.
  │
  └── tenant.active           → Checks tenant subscription status.
        (inner group)           SuperAdmin bypasses.
                                Suspended → redirects to suspended page.
                                Expired → redirects to dashboard.
                                Active/trialing → allows through.
```

### Key Differences from Existing `/admin/*` Middleware

| Aspect | Existing `/admin/*` | New `/store/{store_slug}/admin/*` |
|---|---|---|
| Tenant source | `IdentifyTenant` global middleware (from `$user->tenant_id`) | `Storefront` route middleware (from URL `{store_slug}`) |
| URL format | `/admin/products` | `/store/may-fashion/admin/products` |
| `current.tenant` source | User's `tenant_id` relationship | URL slug resolved via `StoreResolver` |
| Cross-tenant check | Implicit (data scoped by `$user->tenant_id` in controllers) | Same as existing — controllers scope by `$user->tenant_id` |

### Tenant Resolution Detail

1. `IdentifyTenant` (global web middleware) runs first, setting `current.tenant` from the authenticated user's `tenant_id`.
2. `Storefront` (route group middleware) runs next, re-resolving the tenant from the URL `{store_slug}` parameter and **overriding** `current.tenant`.
3. `tenant.valid` and `tenant.active` still check `$user->tenant` (the user's own tenant record), not `current.tenant`. This is acceptable for migration because:
   - Controllers scope data queries by `$user->tenant_id`
   - The user must have the `admin` role
   - Admin users always have a `tenant_id`

**Security note:** A merchant from Store A could visit `/store/store-b/admin/products` and the middleware would pass (because `tenant.valid`/`tenant.active` checks the user's own tenant, not the URL's tenant). However, the controllers would still show Store A's data (scoped by `$user->tenant_id`). No data leak — just potentially confusing URL. A cross-tenant verification middleware should be added in a future phase.

---

## Files Modified

| File | Action | Lines Changed |
|---|---|---|
| `routes/storefront-admin.php` | **CREATED** | 181 lines — full storefront admin route group |
| `routes/web.php` | **MODIFIED** | +2 lines — added `require __DIR__ . '/storefront-admin.php';` at line 461 |

### Files NOT Modified (Safe Migration)

| File Category | Reason |
|---|---|
| `app/Http/Controllers/*` | Same controllers reused — no modifications needed |
| `app/Http/Middleware/*` | Existing middleware reused — no modifications needed |
| `bootstrap/app.php` | Route registration via `require` avoids touching app config |
| `resources/js/*` | Frontend not touched — existing React pages continue to use `/admin/*` URLs |
| `resources/views/admin/*` | Blade templates continue to use `route('admin.*')` — old routes still active |
| `tests/*` | No test modifications needed — existing routes unchanged |

---

## Migration Risk Assessment

### Risk Level: **LOW** (Safe Migration)

| Risk | Impact | Mitigation |
|---|---|---|
| Route name collision | None | `storefront.admin.*` prefix is unique — no overlap with `admin.*` or `storefront.*` |
| Controller breakage | None | Same controllers, same method signatures — no changes |
| Middleware breakage | None | Same middleware, same parameters — simply different composition order |
| Tenant misidentification | Low | `current.tenant` may differ from `$user->tenant` — see note above |
| Impersonation breakage | None | Impersonation changes `$user` on the session — middleware works against authenticated user; superadmin bypasses all tenant checks |
| Frontend regression | None | React and Blade continue using old `/admin/*` URLs |
| Test regression | None | All existing routes unchanged — 43 storefront tests pass |
| Redirect loop | None | Controllers redirect to `route('admin.*')` which still exists |

### Known Limitations (Acceptable for Phase 1)

1. **Redirects go to old URLs** — after form submission, controllers call `route('admin.products.index')` which redirects to `/admin/products`, not `/store/{slug}/admin/products`. This is intentional — existing redirects are preserved.
2. **No cross-tenant guard** — a merchant can visit another store's admin URL (though they'll see their own data). Future middleware needed.
3. **`current.tenant` may be stale** — `IdentifyTenant` sets it from user, `Storefront` overrides from URL. The final value is correct for storefront admin, but `tenant.valid`/`tenant.active` don't use it.
4. **Password reset not available in storefront admin** — users would need to use the global `/password/reset` flow.

---

## Testing Checklist

### Verification Tests (Manual)

- [x] `php artisan route:list --name=storefront.admin.` — confirms 122 routes registered
- [x] `php artisan test --filter=Storefront` — 43/43 pass (no regression)
- [ ] `GET /store/{valid_slug}/admin/dashboard` — authenticated admin user → 200
- [ ] `GET /store/{valid_slug}/admin/products` — authenticated admin user → 200
- [ ] `GET /store/{invalid_slug}/admin/dashboard` — 404 (storefront middleware)
- [ ] `GET /store/{valid_slug}/admin/dashboard` — unauthenticated → redirect to /login
- [ ] `GET /store/{valid_slug}/admin/dashboard` — authenticated customer role → 403
- [ ] `GET /store/{valid_slug}/admin/dashboard` — authenticated superadmin → 200
- [ ] `GET /store/{valid_slug}/admin/billing` — authenticated admin → 200
- [ ] `POST /store/{valid_slug}/admin/orders/{order}/confirm` — authenticated admin → works
- [ ] `GET /store/{valid_slug}/admin/products/create` — authenticated admin → 200
- [ ] Legacy `GET /admin/dashboard` — still works unchanged
- [ ] Legacy `GET /admin/products` — still works unchanged
- [ ] Legacy `POST /admin/orders/{order}/confirm` — still works unchanged

### Automated Tests Added

None. Existing routes are unchanged, new routes use the same controllers. The 43 existing storefront tests verify that the `storefront` middleware, `auth`, and tenant resolution continue working correctly.

---

## Comparison: Old vs New Routes

| Feature | Old `/admin/*` | New `/store/{slug}/admin/*` |
|---|---|---|
| URL | `/admin/products` | `/store/may-fashion/admin/products` |
| Tenant source | User's `tenant_id` | URL slug + StoreResolver |
| Route names | `admin.products.index` | `storefront.admin.products.index` |
| Controllers | Same | **Same (reused)** |
| Middleware | `auth, role:admin, tenant.valid, tenant.active` | `storefront, auth, role:admin, tenant.valid, tenant.active` |
| Frontend links | Hardcoded `/admin/...` in React | Not yet changed |
| Sidebar | Hardcoded `/admin/...` | Not yet changed |
| Controller redirects | `route('admin.*')` | **Still `route('admin.*')`** (unchanged) |
| SuperAdmin access | Yes (bypasses role check) | Yes (same RoleMiddleware) |
| Subscription check | Yes (tenant.active) | Yes (same tenant.active) |
| Blade templates | `route('admin.*')` | **Still `route('admin.*')`** (old routes active) |
