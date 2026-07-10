# Phase 6 — Tenant Provisioning Fix Report

## Bug

A newly created merchant with `IDENTITY_USE_ACCOUNTS=true` is immediately
redirected to `/store/{tenant}/admin/suspended` after login, instead of the
dashboard. This occurs for every new store — the merchant never sees their
dashboard.

## Root Cause

The tenant provisioning pipeline (`TenantBootstrapService::createSubscription`)
creates a `Subscription` with `status = 'pending'` (from the `$status` parameter
passed by `CreateStoreController`). The `Tenant` itself is also created with
`status = 'pending'` (set during `Tenant::create()`). **Neither status is ever
updated from `'pending'` to a valid operational state.**

When the verified owner logs in and is redirected to the dashboard,
`EnsureTenantIsActive` middleware checks:

```
if (! in_array($tenant->status, ['active', 'trialing'])) {
    return $this->redirectToSuspended($storeSlug);
}
```

Since `$tenant->status === 'pending'` is not in `['active', 'trialing']`, the
middleware unconditionally redirects to the suspension page — even when the
owner's email is verified.

### Two missing steps

1. **`createSubscription()` never syncs the tenant status.** After creating
   the subscription (whether 'trialing' for trial plans or 'active' otherwise),
   the tenant's `status` column remains 'pending'.

2. **`CreateStoreController` passes `'status' => 'pending'` to `bootstrap()`.**
   This means the non-trial subscription path creates the subscription with
   status 'pending' too. The _intended_ initial status is 'active' (for free
   plans) or 'trialing' (for trial-enabled paid plans).

## Tenant Provisioning Flow (Corrected)

```
CreateStoreController::store()
  │
  ├── Tenant::create(['status' => 'pending'])   ← correct: pending is safe default
  │
  └── TenantBootstrapService::bootstrap()
        │
        ├── createRoles()                         ← admin, customer
        │
        ├── createSubscription($tenant, null, 'active')
        │     │
        │     ├── resolvePlan()
        │     │     ├── trial enabled → cheapest paid plan
        │     │     └── no trial      → free plan
        │     │
        │     ├── trial enabled?
        │     │     YES → sub = 'trialing', tenant.status ← 'trialing'  [FIX]
        │     │     NO  → sub = 'active',   tenant.status ← 'active'    [FIX]
        │     │
        │     └── return Subscription
        │
        ├── createOwnerAccount()                  ← Account, status=active
        │
        ├── assignOwnerRole()                     ← admin role
        │
        ├── TenantMembership::create()            ← account_id, tenant_id, is_owner
        │
        ├── createDefaultUnits/Categories/Brands/PaymentMethods
        │
        └── TenantCreated::dispatch()
```

## Fix

### File: `app/Services/TenantBootstrapService.php` (line 285)

**Before (line 285):**
```php
FeatureGate::clearCache($plan);

return $subscription;
```

**After:**
```php
FeatureGate::clearCache($plan);

$tenant->update(['status' => $subscription->status]);

return $subscription;
```

This ensures the tenant's `status` column always mirrors the subscription's
status after bootstrap. Trial-enabled tenants become 'trialing'. Free-plan
tenants become 'active'. The tenant is never left in 'pending'.

### File: `app/Http/Controllers/CreateStoreController.php` (line 89)

**Before:**
```php
'status' => 'pending',
```

**After:**
```php
'status' => 'active',
```

This instructs `createSubscription()` to create non-trial subscriptions with
`status = 'active'` (instead of 'pending'). The trial path is unaffected
(its status is hardcoded to 'trialing' in `createSubscription()`).

## Files Modified

| File | Change |
|------|--------|
| `app/Services/TenantBootstrapService.php:285` | Add `$tenant->update(['status' => $subscription->status])` after subscription creation |
| `app/Http/Controllers/CreateStoreController.php:89` | Change `'status' => 'pending'` to `'status' => 'active'` |

## Validation

### Account mode (`IDENTITY_USE_ACCOUNTS=true`)

| Check | Result |
|-------|--------|
| Tenant status after bootstrap | `trialing` (trial) or `active` (free plan) |
| Subscription status | `trialing` (trial) or `active` (free plan) |
| TenantMembership created | `is_owner = true`, correct role |
| EnsureTenantIsActive passes | No redirect to suspended |
| Dashboard accessible | HTTP 200 |

### Legacy mode (`IDENTITY_USE_ACCOUNTS=false`)

| Check | Result |
|-------|--------|
| Tenant status after bootstrap | `trialing` or `active` |
| EnsureTenantIsActive passes | No redirect to suspended |
| Dashboard accessible | HTTP 200 |

## Manual QA

1. Set `IDENTITY_USE_ACCOUNTS=true` in `.env`
2. Register a new account
3. Verify email
4. Create a new store (via `/create-store`)
5. Log in via `/store/{slug}/admin/login`
6. Confirm you land on the **dashboard**, not the suspended page
7. Run `php artisan tinker` and inspect:
   ```php
   $tenant = \App\Models\Tenant::where('slug', '{slug}')->first();
   $tenant->status;                     // 'trialing' or 'active'
   $tenant->subscription->status;      // 'trialing' or 'active'
   $tenant->ownerMembership->is_owner; // true
   ```
8. Toggle `IDENTITY_USE_ACCOUNTS=false` and repeat. Flow must remain unchanged.
