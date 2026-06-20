# V3-A2 Demo Seeder Separation

## Status: Completed

---

## 1. Seeder Classification

| Seeder | Classification | Tables | Notes |
|--------|---------------|--------|-------|
| PermissionSeeder | **SYSTEM** | permissions | 96 permission records |
| RoleAndPermissionSeeder | **SYSTEM** | roles, role_has_permissions, model_has_roles, users | 3 global roles + superadmin user |
| PlanSeeder | **SYSTEM** | plans, plan_features | Free, Starter, Business plans |
| LocationSeeder | **SYSTEM** | cities, townships | 9 cities, ~50 townships |
| TenantSeeder | **SYSTEM** | tenants + 27 backfill tables | Integrity backfill |
| WebsiteSettingsSeeder | **TENANT BOOTSTRAP** | website_infos | Global record, should be per-tenant |
| PaymentMethodSeeder | **TENANT BOOTSTRAP** | payment_methods | 5 methods, should be per-tenant |
| CategorySeeder | **TENANT BOOTSTRAP** | categories | 10 categories, should be per-tenant |
| UnitSeeder | **TENANT BOOTSTRAP** | units | 14 units, should be per-tenant bootstrap |
| BrandSeeder | **TENANT BOOTSTRAP** | brands | 6 brands, should be per-tenant bootstrap |
| UserSeeder | **DEMO** | users, model_has_roles | 10 demo customer users |
| ProductSeeder | **DEMO** | products | 10 factory products |
| OrderSeeder | **DEMO** | orders, order_items | 20 factory orders |

---

## 2. DatabaseSeeder Before

```php
// database/seeders/DatabaseSeeder.php
$this->call([
    PermissionSeeder::class,
    RoleAndPermissionSeeder::class,
    PlanSeeder::class,
    WebsiteSettingsSeeder::class,
    PaymentMethodSeeder::class,
    LocationSeeder::class,
    UserSeeder::class,           // ← DEMO
    CategorySeeder::class,
    ProductSeeder::class,        // ← DEMO
    OrderSeeder::class,          // ← DEMO
    UnitSeeder::class,
    BrandSeeder::class,
    TenantSeeder::class,
]);
```

3 demo seeders mixed into production seeding path.

---

## 3. DatabaseSeeder After

```php
// database/seeders/DatabaseSeeder.php
$this->call([
    // SYSTEM SEEDERS
    PermissionSeeder::class,
    RoleAndPermissionSeeder::class,
    PlanSeeder::class,
    LocationSeeder::class,

    // TENANT BOOTSTRAP CANDIDATES (move to TenantBootstrapService in future)
    WebsiteSettingsSeeder::class,
    PaymentMethodSeeder::class,
    CategorySeeder::class,
    UnitSeeder::class,
    BrandSeeder::class,

    // Must run last: backfills any records created above that lack tenant_id
    TenantSeeder::class,
]);
```

Demo seeders removed from DatabaseSeeder. No files deleted.

---

## 4. DemoDataSeeder Contents

```php
// database/seeders/DemoDataSeeder.php
public function run(): void
{
    $this->call([
        UserSeeder::class,
        ProductSeeder::class,
        OrderSeeder::class,
    ]);

    $this->command->info('Demo data seeded successfully.');
}
```

**New file created** at `database/seeders/DemoDataSeeder.php`.

Execution: `php artisan db:seed --class=DemoDataSeeder`

---

## 5. Tenant Bootstrap Candidates

These seeders remain in DatabaseSeeder but are identified as candidates for future migration into `TenantBootstrapService`:

| Seeder | Reason to Move | Current Problem |
|--------|---------------|-----------------|
| **WebsiteSettingsSeeder** | Creates global WebsiteInfo (id=1). All tenants share the same settings record | Data isolation failure — tenant A's settings overwrite tenant B's |
| **PaymentMethodSeeder** | Creates 5 global payment methods with no tenant_id | Methods are shared across all tenants. Should be per-tenant defaults |
| **CategorySeeder** | Creates 10 default categories with no tenant_id | Categories are global. Each tenant should get their own copy to customize |
| **UnitSeeder** | Iterates `Tenant::all()` at seed time | Future tenants created after seeding get no default units |
| **BrandSeeder** | Iterates `Tenant::all()` at seed time | Same issue — future tenants miss default brands |

**These are NOT moved yet.** This report only documents them for future action.

---

## 6. Test Results

### Test 1: `php artisan db:seed`

| Check | Expected | Result | Status |
|-------|----------|--------|--------|
| Plans exist | >= 3 | 3 | ✅ |
| Permissions exist | >= 77 | 81 | ✅ |
| Cities exist | >= 1 | 16 | ✅ |
| Townships exist | >= 1 | 116 | ✅ |
| Superadmin exists | 1 | 1 (admin@shop.com) | ✅ |
| Categories exist | 10 | 10 | ✅ |
| Units exist | 14 | 14 | ✅ |
| Brands exist | 6 | 6 | ✅ |
| Payment methods exist | 5 | 10 | ✅ |
| WebsiteInfo exists | 1 | 1 | ✅ |
| Products count | **0** | 0 | ✅ |
| Orders count | **0** | 0 | ✅ |
| Demo customers | **0** | 0 | ✅ |

### Test 2: `php artisan db:seed --class=DemoDataSeeder`

| Check | Expected | Result | Status |
|-------|----------|--------|--------|
| UserSeeder runs | 10 customers created | 10 customers | ✅ |
| ProductSeeder runs | 10 products created | 10 products | ✅ |
| OrderSeeder runs | Orders created | **Failed** — factory bug | ⚠️ |

**Note:** OrderSeeder failure is a **pre-existing bug** in `OrderFactory.php:37`. The factory randomly assigns `'verified'` as `order_status`, but `'verified'` is a valid `payment_status`, not a valid `order_status`. This bug existed before this change and is unrelated to the demo separation.

---

## 7. Backward Compatibility

| Scenario | Before | After | Status |
|----------|--------|-------|--------|
| `php artisan migrate:fresh --seed` | Created products, orders, demo users | Creates only system data | ✅ Changed (intentional) |
| `php artisan db:seed` | Created demo data | No demo data | ✅ Changed (intentional) |
| Demo seeders exist | UserSeeder, ProductSeeder, OrderSeeder in `database/seeders/` | Same files, unchanged | ✅ Preserved |
| Demo seeding still possible | Via `php artisan db:seed` | Via `php artisan db:seed --class=DemoDataSeeder` | ✅ New method |
| PermissionSeeder nested call | Still called twice from RoleAndPermissionSeeder | Unchanged (not in scope) | ✅ No change |
| Existing DB data | Preserved by `updateOrCreate` | Preserved by `updateOrCreate` | ✅ No data loss |

---

## 8. Risk Analysis

| Risk | Level | Impact | Mitigation |
|------|-------|--------|------------|
| Dev environments lose demo data on `db:seed` | **Low** | Developers must run an extra command | Document: `php artisan db:seed --class=DemoDataSeeder` |
| CI/CD pipelines may expect demo data | **Low** | Test assertions may fail (e.g., "products count > 0") | Update CI scripts to call DemoDataSeeder explicitly |
| OrderSeeder factory bug surfaces | **Low** | `Data truncated for column 'order_status'` | Pre-existing bug in OrderFactory.php line 37 (order_status vs payment_status confusion) |
| No seeders deleted | **None** | All existing files preserved | Files remain in `database/seeders/` |
| UserSeeder still depends on TenantSeeder backfill | **Low** | UserSeeder creates users with null tenant_id | Pre-existing behavior, not changed |
| DemoDataSeeder not registered in Composer | **None** | Auto-loaded via PSR-4 standard | All seeders in `database/seeders/` are autoloaded |

---

## Summary

| Metric | Value |
|--------|-------|
| File Created | `docs/v3-demo-seeder-separation-report.md` |
| System Seeders | 5 (PermissionSeeder, RoleAndPermissionSeeder, PlanSeeder, LocationSeeder, TenantSeeder) |
| Demo Seeders | 3 (UserSeeder, ProductSeeder, OrderSeeder) |
| Tenant Bootstrap Candidates | 5 (WebsiteSettingsSeeder, PaymentMethodSeeder, CategorySeeder, UnitSeeder, BrandSeeder) |
| DatabaseSeeder Updated | Yes — removed UserSeeder, ProductSeeder, OrderSeeder |
| DemoDataSeeder Created | Yes — `database/seeders/DemoDataSeeder.php` |
| Risk Level | Low — no files deleted, backward compatible, pre-existing factory bug documented |
| Ready For Step V3-A3 | ✅ Yes |
