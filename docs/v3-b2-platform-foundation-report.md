# V3-B2-A: Platform Settings Foundation Report

**Date:** 2026-06-21
**Scope:** Create dedicated platform_settings table, model, service, and seeder.

## Files Created

### Migration: `database/migrations/2026_06_21_000001_create_platform_settings_table.php`
- Columns: `id`, `site_name` (default 'My Application'), `site_logo` (nullable), `favicon` (nullable), `support_email` (nullable), `maintenance_mode` (boolean, default false), `registration_enabled` (boolean, default true), timestamps
- No `tenant_id` — single-row, platform-wide singleton pattern

### Model: `app/Models/PlatformSetting.php`
- `$fillable`: `site_name`, `site_logo`, `favicon`, `support_email`, `maintenance_mode`, `registration_enabled`
- `$casts`: `maintenance_mode` → boolean, `registration_enabled` → boolean
- `::current()` static method: singleton via `Cache::rememberForever('platform_settings', ...)`
  - Returns first row or creates one with defaults
- `::clearCache()`: busts cache after update

### Service: `app/Services/PlatformSettingService.php`
- `get()`: returns `PlatformSetting::current()`
- `update(array $data)`: updates singleton row, clears cache, returns fresh instance

### Seeder: `database/seeders/PlatformSettingSeeder.php`
- Inserts default row with all column defaults
- Uses `firstOrCreate` for idempotency
- Registered in `DatabaseSeeder`

## Key Decisions
- **Single row, no tenant_id**: Platform settings are truly global/tenant-agnostic
- **Cache singleton**: Reduces DB query to one per cache lifetime; cleared on every update
- **Seeder idempotent**: Safe to rerun
- **Separate from WebsiteInfo**: Platform settings owned by SuperAdmin; WebsiteInfo remains per-tenant

## Test Results
- MerchantManagementTest: 4 passed
- Migration ran successfully
- Seeder populates default row
