# V3-B3-2: Plan Limits Activation

**Date:** 2026-06-28
**Goal:** Activate `SubscriptionLimitService` by seeding real plan limits on Plan model. No FeatureGate changes, no payment integration.

---

## Changes

### 1. `database/seeders/PlanSeeder.php`

Added `product_limit`, `staff_limit`, `storage_limit` to all three seeded plans:

| Plan | product_limit | staff_limit | storage_limit (MB) |
|------|:---:|:---:|:---:|
| **Free** | 10 | 2 | 100 |
| **Starter** | 100 | 5 | 1024 |
| **Business** | null | null | null |

Also added `monthly_price` and `yearly_price` to each plan to avoid relying on the migration backfill (which sets `yearly_price = price * 10`).

### 2. `tests/Feature/SubscriptionLimitServiceTest.php` (new file)

17 tests, 45 assertions covering:

#### Product Limit (6 tests)
- `test_free_plan_blocks_product_creation_at_limit` — 10 products → `canCreateProduct()` = false
- `test_free_plan_allows_product_creation_under_limit` — 5 products → `canCreateProduct()` = true
- `test_free_plan_allows_product_creation_when_empty` — 0 products → `canCreateProduct()` = true
- `test_business_plan_allows_unlimited_products` — 999 products on Business → `canCreateProduct()` = true
- `test_starter_plan_allows_creation_under_limit` — 50/100 → `canCreateProduct()` = true
- `test_starter_plan_blocks_at_limit` — 100/100 → `canCreateProduct()` = false

#### Staff Limit (3 tests)
- `test_free_plan_blocks_staff_creation_at_limit` — 2 staff → `canCreateStaff()` = false
- `test_free_plan_allows_staff_creation_under_limit` — 1 staff → `canCreateStaff()` = true
- `test_business_plan_allows_unlimited_staff` — 0 staff on Business → unlimited

#### Storage Limit (4 tests)
- `test_free_plan_blocks_upload_when_full` — 100MB used → `canUpload(1)` = false
- `test_free_plan_allows_upload_when_under_limit` — 50MB used, uploading 10MB → `canUpload()` = true
- `test_free_plan_blocks_upload_exceeding_remaining` — 95MB used, uploading 10MB → `canUpload(10MB)` = false
- `test_business_plan_allows_unlimited_storage` — 999GB used → `canUpload(PHP_INT_MAX)` = true

#### Assertion Tests (3 tests)
- `test_assert_can_create_product_throws_at_limit` — Runtime exception at limit
- `test_assert_can_create_staff_throws_at_limit` — Runtime exception at limit
- `test_assert_can_upload_throws_when_full` — Runtime exception when full

#### Usage Report (1 test)
- `test_get_all_usage_returns_expected_structure` — Verifies getAllUsage() returns correct structure with current, limit, remaining, is_unlimited, percent

### No changes to
- `app/Services/FeatureGate.php` — DEV_MODE still true
- `app/Services/SubscriptionLimitService.php` — no architecture changes
- `app/Http/Middleware/SubscriptionIsActive.php` — no changes
- `app/Http/Middleware/EnsureTenantIsActive.php` — no changes
- Any controller, route, or view

---

## Verification

- **17/17 new tests pass** (45 assertions)
- **13/13 existing tests pass** (PlatformSettingsTest 9/9, MerchantManagementTest 4/4)
- Vite build not required (no frontend changes)

The `SubscriptionLimitService` correctly:
- Blocks creation when count >= limit
- Allows creation when count < limit
- Treats null as unlimited (backward compatible)
- Reports remaining counts and percentages correctly
- Throws runtime exceptions with upgrade messages

---

## Regression Risk

| Risk | Severity | Mitigation |
|------|:--------:|------------|
| Existing tenants may exceed new limits | **Medium** — Free tenants with >10 products or >2 staff will hit blocks | Only affects new creation attempts; existing data untouched. Downgrade warnings already exist in SuperAdmin UI. |
| Seeder no longer idempotent on `price` column | **Low** — `monthly_price`/`yearly_price` now set directly | Backward compatible; `price` column still populated |
| Plan seeding always sets `storage_limit` for existing plans | **Low** — `updateOrCreate` by slug updates all columns | Intended behavior |
