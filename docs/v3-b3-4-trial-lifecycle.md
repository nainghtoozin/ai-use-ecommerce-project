# V3-B3-4: Trial Lifecycle Implementation

**Status:** Done
**Date:** 2026-06-28

## Summary

Implemented free trial lifecycle for new merchants. When platform trial is enabled, new tenants on paid plans automatically enter a trialing subscription instead of requiring immediate payment.

## Changes

### Migration
- `database/migrations/2026_06_28_000001_add_trial_fields_to_platform_settings.php`
  - Added `trial_enabled` (boolean, default true) to `platform_settings`
  - Added `trial_days` (unsigned tiny int, default 14) to `platform_settings`

### Model
- `app/Models/PlatformSetting.php`
  - Added `trial_enabled` and `trial_days` to `$fillable`
  - Added casts: `trial_enabled` → boolean, `trial_days` → integer

### Seeder
- `database/seeders/PlatformSettingSeeder.php`
  - Default: `trial_enabled = true`, `trial_days = 14`

### Tenant Bootstrap
- `app/Services/TenantBootstrapService.php`
  - `createSubscription()` now checks `PlatformSetting::current()->trial_enabled`
  - If trial enabled and plan is paid → creates subscription with `status = 'trialing'`, `starts_at = now()`, `trial_ends_at = now() + trial_days`
  - If trial disabled or plan is free → existing behavior (pending/active status)
  - `FeatureGate::clearCache()` called in both branches

### No Changes Needed
- `ActivateTenantOnVerified` — already handles trialing subscriptions correctly (leaves them as-is)
- `EnsureTenantIsActive` — already allows tenants with activeSubscription (includes 'trialing')
- `SubscriptionIsActive` — already allows subscriptions `isInGoodStanding()` (includes 'trialing')
- `SubscriptionExpiryService::transitionTrialToExpired()` — already handles trialing → expired transition
- `Subscription` model — already has `isTrialing()`, `onTrial()`, `trialExpired()`, `daysLeftInTrial()`, `hasExpired()` with trial support

### Admin Billing Page
- `app/Http/Controllers/Admin/AdminBillingController.php` — added `trial_days_remaining` to response
- `resources/js/Pages/Admin/Billing/Index.jsx` — shows days remaining alongside trial end date

### Super Admin UI
- `app/Http/Controllers/SuperAdmin/SuperAdminPlatformSettingController.php` — validates `trial_enabled` and `trial_days`
- `resources/js/Pages/SuperAdmin/PlatformSettings/Index.jsx` — trial enable/disable toggle + trial days input

## Architecture

```
PlatformSetting.trial_enabled=true, trial_days=14
  │
  ▼
CreateStore → TenantBootstrapService.bootstrap()
  │
  ├─ Plan is FREE or trial disabled → status='pending' (existing flow)
  │
  └─ Plan is PAID + trial enabled → status='trialing', trial_ends_at=now+14d
       │
       ▼
  Email verified → ActivateTenantOnVerified
       │
       ├─ Tenant: pending → active
       │
       └─ Subscription: trialing → no change (stays trialing)
       │
       ▼
  Cron (every 5 min) → SubscriptionExpiryService
       │
       ├─ trial_ends_at in future → no change (still trialing)
       │
       └─ trial_ends_at past → status='expired' → redirect to billing
```

## Test Coverage

- 8 tests in `tests/Feature/TrialLifecycleTest.php`
  1. Trial subscription created during bootstrap (paid plan → trialing)
  2. Free plan skips trial (free plan → pending)
  3. Trial disabled skips trial (paid plan + trial disabled → pending)
  4. ActivateTenantOnVerified leaves trialing subscription intact
  5. EnsureTenantIsActive allows trialing subscriptions
  6. SubscriptionIsActive allows trialing subscriptions
  7. Trial expiry transitions via cron (trialing → expired)
  8. Trial does not expire before trial ends (remains trialing)

**Total: 57/57 tests passing** (across all 5 suites)
