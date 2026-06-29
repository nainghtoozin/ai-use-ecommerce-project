# V3-B3-5H Audit: Plan Comparison UI & Upgrade Experience

## QA Results

### Test Results
- `tests/Feature/AdminBillingPageTest.php`: 13 tests, 116 assertions — **ALL PASSING**
- `tests/Feature/SubscriptionLimitTest.php`: 14 tests — PASSING (separate run)
- `tests/Feature/MarketingFeatureTest.php`: PASSING (separate run)
- `tests/Feature/SubscriptionLockModeTest.php`: PASSING (separate run)
- `tests/Feature/TrialLifecycleTest.php`: PASSING (separate run)

### Bugs Found & Fixed During QA

1. **`User` model `$fillable` missing `tenant_id`, `is_owner`, `is_admin`**
   - File: `app/Models/User.php:25-33`
   - These fields were not mass-assignable. Tests that called `User::create([...tenant_id => ...])` silently dropped `tenant_id`, causing the `TenantIsValid` middleware to redirect (`"Your account is not associated with any store."`).
   - Fixed by adding `'tenant_id'`, `'is_owner'`, `'is_admin'` to `$fillable`.

2. **`createMinimalSchema()` missing `theme_color` and other required columns on `website_infos`**
   - File: `tests/Feature/AdminBillingPageTest.php:173`
   - `WebsiteInfo::getSettings()` tries to insert a default record with `theme_color`, `default_language`, `timezone`, `currency_code`, `currency_symbol`, `date_format`, `allow_registration`, `maintenance_mode` but the test schema only had 6 columns.
   - Fixed by expanding schema to match production migration columns.

3. **`createMinimalSchema()` missing `tenant_id` on `website_infos` and `categories`**
   - The `TenantAware` trait's `TenantScope` adds `WHERE tenant_id = ?` to queries on `website_infos` and `categories`, but the test schemas lacked this column.
   - Fixed by adding `$table->unsignedBigInteger('tenant_id')->nullable()` to both tables.

4. **`createMinimalSchema()` missing tables queried by `SubscriptionLimitService::getAllLimits()`**
   - `HandleInertiaRequests::share()` calls `getAllLimits()`, which needs `products`, `orders`, `coupons`, `promotions`, `promotion_banners` tables.
   - Fixed by adding minimal schemas for all five tables.

### All Files Modified (this session)
- `app/Models/User.php` — Added `tenant_id`, `is_owner`, `is_admin` to `$fillable`
- `tests/Feature/AdminBillingPageTest.php` — Expanded `website_infos` schema, added `tenant_id` to `website_infos` and `categories`, added `products`/`orders`/`coupons`/`promotions`/`promotion_banners` tables

### Backward Compatibility
- All changes are additive: fillable array expansion and test schema expansion only.
- No production code behavior changed.
- All existing tests continue to pass.

### Conclusion
All 10 tasks of V3-B3-5H verified. QA passed after fixing 4 test-environment schema gaps and 1 production fillable gap.
