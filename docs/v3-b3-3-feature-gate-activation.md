# V3-B3-3: FeatureGate Activation

**Date:** 2026-06-28
**Goal:** Disable `DEV_MODE` bypass so FeatureGate reads real `PlanFeature` database records. Add cache invalidation on plan assignment/change.

---

## Changes

### 1. `app/Services/FeatureGate.php`

`DEV_MODE` changed from `true` to `false` (line 41):

```php
protected const DEV_MODE = false;
```

All TODO comments about re-enabling after billing implementation are now resolved. FeatureGate reads directly from `PlanFeature::where('feature_key', $key)->where('is_enabled', true)->exists()` with 5-minute cache.

### 2. `app/Http/Controllers/SuperAdmin/SubscriptionController.php`

Added `use App\Services\FeatureGate` import and cache invalidation calls:

- **`assign()`** (after line 122): `FeatureGate::clearCache($plan)` — clears cache for the newly assigned plan
- **`changePlan()`** (after line 168): `FeatureGate::clearCache($newPlan)` + `FeatureGate::clearCache($oldPlan)` — clears cache for both old and new plans

This ensures that when a SuperAdmin changes a tenant's plan, the feature cache is invalidated immediately rather than waiting for the 5-minute TTL.

### 3. `tests/Feature/FeatureGateTest.php` (new file)

19 tests, 38 assertions covering:

| Category | Tests | What it verifies |
|----------|-------|-----------------|
| DEV_MODE | 1 | `DEV_MODE` constant is `false` |
| Free plan features | 4 | `single_products=enabled`, `variable/combo=disabled`, `typeEnabled()` |
| Starter plan features | 3 | `single/variable=enabled`, `combo=disabled` |
| Business plan features | 1 | All 3 features enabled |
| Static `enabled()` via user | 3 | End-to-end: authenticated user → tenant → subscription → plan → feature check |
| `require()` throws | 2 | `\InvalidArgumentException` on disabled feature, no-op on enabled |
| `getEnabledFeatures()` | 2 | Returns correct feature arrays per plan |
| `getAllFeaturesStatus()` | 1 | Locked features return `upgrade_hint` correctly |
| Cache invalidation | 2 | After `clearCache()`, stale data is refreshed; without invalidation, TTL serves stale data |

---

## Manual Test Verification

| Scenario | Expected | Actual |
|----------|----------|--------|
| Free plan → create variable product | Blocked | `FeatureGate::forPlan(free)->isEnabled('variable_products')` = `false` |
| Starter plan → create variable product | Allowed | `FeatureGate::forPlan(starter)->isEnabled('variable_products')` = `true` |
| Business plan → create combo product | Allowed | `FeatureGate::forPlan(business)->isEnabled('combo_products')` = `true` |

---

## Verification

- **49/49 tests pass** (117 assertions)
  - FeatureGateTest: 19/19
  - SubscriptionLimitServiceTest: 17/17
  - PlatformSettingsTest: 9/9
  - MerchantManagementTest: 4/4
- Vite build not required (no frontend changes)

---

## Regression Risk

| Risk | Severity | Mitigation |
|------|:--------:|------------|
| Existing Free tenants creating variable/combo products will now be blocked | **Medium** — only affects new creation attempts | Plan limits are already seeded; tenants exceeding plan features will see upgrade modals. Existing products are untouched. |
| Cache serves stale feature data for up to 5 min after plan change | **Low** — clearCache() added to both assign() and changePlan() | Manual cache clearing handles admin-triggered changes. 5-min TTL only applies to background data changes. |
| `Plan::free()` fallback returns null if Free plan is inactive | **Low** — `free()` scope filters `status='active'` | Seeder always creates Free as active. |
