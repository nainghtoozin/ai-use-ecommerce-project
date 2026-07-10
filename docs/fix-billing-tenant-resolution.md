# Fix: Billing Tenant Resolution

## Bug

Billing pages returned `403 Store Not Found` after migrating to Account Mode (`IDENTITY_USE_ACCOUNTS=true`).

Authentication succeeded and the dashboard loaded, but **every** billing page method rejected the request.

## Root Cause

Every method in `AdminBillingController` resolves the tenant via:

```php
$tenant = auth()->user()->tenant;
```

In Account mode, `auth()->user()` returns an `Account` model. The `Account` model has no `tenant()` relationship — it does not have a `tenant_id` column. So `auth()->user()->tenant` returns `null`, triggering:

```php
if (!$tenant) {
    abort(403, 'Store not found.');
}
```

This affects **8 methods** in the controller: `index()`, `subscription()`, `upgrade()`, `paymentHistory()`, `checkout()`, `payment()`, `paymentSubmit()`, and `renew()`.

### Secondary Issues

1. **`EnsureTenantIsActive` middleware** (applied to most routes including billing outer group via `tenant.active`) also used `$user->tenant` — same null issue for Account mode.
2. **`IdentifyTenant` middleware** resolved Account's tenant by taking the **first** `TenantMembership` only. For accounts with multiple tenant memberships, this could resolve to the wrong tenant on the first request.
3. **`ImageService::resolveTenant()`** had `auth()->user()->tenant` as the primary path with `Tenant::getCurrent()` as fallback — the primary path returned null for Account.

## Fix

### Files Modified

1. **`app/Http/Controllers/Admin/AdminBillingController.php`**
   - Replaced all 8 occurrences of `auth()->user()->tenant` with `Tenant::getCurrent()`
   - `Tenant::getCurrent()` reads from `app('current.tenant')`, which is set by:
     - **`IdentifyTenant`** (global middleware) — resolves from User `tenant_id` or Account `TenantMembership`
     - **`Storefront`** middleware (for `/store/{slug}/*` routes) — resolves from URL `store_slug`
   - The returned `Tenant` model is used identically: `$tenant->subscription`, `$tenant->slug` for redirects, etc.

   **Before:**
   ```php
   $tenant = auth()->user()->tenant;
   if (!$tenant) {
       abort(403, 'Store not found.');
   }
   ```

   **After:**
   ```php
   $tenant = Tenant::getCurrent();
   if (!$tenant) {
       abort(403, 'Store not found.');
   }
   ```
   (plus `use App\Models\Tenant;` added to imports)

2. **`app/Http/Middleware/EnsureTenantIsActive.php`**
   - Changed `$tenant = $user->tenant;` to `$tenant = $user instanceof Account ? Tenant::getCurrent() : $user->tenant;`
   - Added `use App\Models\Account;` import

3. **`app/Http/Middleware/IdentifyTenant.php`**
   - Improved Account mode resolution to prefer the session slug (`current_tenant_slug`) over blindly taking the first membership
   - If the session has a stored slug, the middleware first tries to find a membership matching that tenant
   - Falls back to the first membership if no session match found
   - This ensures cross-request consistency: after a user visits `/store/{slug}/admin/*`, the `Storefront` middleware sets the slug in the session, and subsequent admin requests for the same tenant use the session value

4. **`app/Services/ImageService.php`**
   - `resolveTenant()`: Added Account check before `auth()->user()->tenant`
   - Account mode: uses `Tenant::getCurrent()` directly

### Middleware Status Audit

| Middleware | Alias | Account Mode Support | Status |
|---|---|---|---|
| `CheckTenantAccess` | `tenant.access` | Checks `TenantMembership` explicitly | ✅ Correct |
| `TenantIsValid` | `tenant.valid` | Checks `TenantMembership` explicitly | ✅ Correct |
| `EnsureTenantIsActive` | `tenant.active` | Now uses `Tenant::getCurrent()` | ✅ Fixed |
| `ValidateTenantBinding` | `tenant.binding` | Validates model `tenant_id` — no Account dependency | ✅ Fine |
| `Storefront` | `storefront` | Resolves tenant from URL slug | ✅ Fine |
| `IdentifyTenant` | (global) | Prefers session slug, falls back to first membership | ✅ Improved |

### Tenant Resolution Chain (Account Mode, After Fix)

```
Request → IdentifyTenant (global)
  → Account → try session slug → try first membership
    → set current.tenant

Request → Storefront middleware (storefront-admin routes)
  → StoreResolver::resolve($storeSlug)
    → overwrite current.tenant

Request → AdminBillingController
  → Tenant::getCurrent() → app('current.tenant')
    → Tenant model → subscription, plan, usage, slug
```

## Testing

1. **Account Mode** (`IDENTITY_USE_ACCOUNTS=true`):
   - `/store/{slug}/admin/billing` — loads billing dashboard, subscription details, upgrade page, payment history, checkout, payment form
   - `/admin/billing` — loads billing dashboard for the Account's primary tenant
   - Subscription renew, payment submit — all work correctly
   - Error routes (suspended, expired) redirect correctly using tenant slug

2. **Legacy Mode** (`IDENTITY_USE_ACCOUNTS=false`):
   - Complete billing flow unchanged — `auth()->user()->tenant` continues to work via User model's `tenant()` relationship

## Known Limitations

1. **`AdminOrderController::tenantFilter()`** still uses `auth()->user()->tenant_id` — separate issue for order pages.
2. **`TelegramIntegrationController`** references `auth()->user()->tenant_id` directly — separate issue.
3. **`SubscriptionLimitService::staffCount()`** still queries `User` model — may undercount staff limits in Account mode but does not block billing page access.
4. **Multi-tenant Account (edge case)**: For admin billing routes (`/admin/billing`), the tenant resolves from the session or first membership. If an Account has multiple memberships, the first visit may use the wrong tenant until a storefront route sets the session. This is unlikely in practice — most Accounts have one membership.
