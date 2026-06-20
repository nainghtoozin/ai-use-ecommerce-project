# V3 Seeder Classification Audit

## Status: Completed (Read-Only Audit)

---

## 1. Seeder Inventory

Total Seeders Found: **14**

| # | Seeder | File | Lines | Currently Called? |
|---|--------|------|-------|------------------|
| 1 | `DatabaseSeeder` | `database/seeders/DatabaseSeeder.php` | 29 | Entry point |
| 2 | `PermissionSeeder` | `database/seeders/PermissionSeeder.php` | 143 | Yes (also nested in RoleAndPermissionSeeder) |
| 3 | `RoleAndPermissionSeeder` | `database/seeders/RoleAndPermissionSeeder.php` | 110 | Yes |
| 4 | `PlanSeeder` | `database/seeders/PlanSeeder.php` | 135 | **NO — Orphaned** |
| 5 | `LocationSeeder` | `database/seeders/LocationSeeder.php` | 139 | Yes |
| 6 | `WebsiteSettingsSeeder` | `database/seeders/WebsiteSettingsSeeder.php` | 90 | Yes |
| 7 | `PaymentMethodSeeder` | `database/seeders/PaymentMethodSeeder.php` | 54 | Yes |
| 8 | `CategorySeeder` | `database/seeders/CategorySeeder.php` | 32 | Yes |
| 9 | `UserSeeder` | `database/seeders/UserSeeder.php` | 41 | Yes |
| 10 | `ProductSeeder` | `database/seeders/ProductSeeder.php` | 14 | Yes |
| 11 | `OrderSeeder` | `database/seeders/OrderSeeder.php` | 47 | Yes |
| 12 | `UnitSeeder` | `database/seeders/UnitSeeder.php` | 52 | Yes |
| 13 | `BrandSeeder` | `database/seeders/BrandSeeder.php` | 44 | Yes |
| 14 | `TenantSeeder` | `database/seeders/TenantSeeder.php` | 102 | Yes (last) |

---

## 2. Current Seeder Flow

```
DatabaseSeeder::run()
  ├── 1. PermissionSeeder::class        ← Create 96 permissions
  ├── 2. RoleAndPermissionSeeder::class ← Creates roles + superadmin user
  │       └── calls PermissionSeeder again (duplicate)
  ├── 3. WebsiteSettingsSeeder::class   ← Global WebsiteInfo (id=1)
  ├── 4. PaymentMethodSeeder::class     ← 5 global payment methods
  ├── 5. LocationSeeder::class          ← 9 cities + ~50 townships
  ├── 6. UserSeeder::class              ← 10 demo customers (DEMO)
  ├── 7. CategorySeeder::class          ← 10 default categories
  ├── 8. ProductSeeder::class           ← 10 factory products (DEMO)
  ├── 9. OrderSeeder::class             ← 20 orders + items (DEMO)
  ├── 10. UnitSeeder::class             ← 14 units per existing tenant
  ├── 11. BrandSeeder::class            ← 6 brands per existing tenant
  └── 12. TenantSeeder::class           ← Creates default tenant + backfill

NOT CALLED:
  - PlanSeeder::class                   ← 3 subscription plans (ORPHANED)
```

---

## 3. System Seeders

Data required for a fresh SaaS installation. Platform-level, not tenant-scoped.

| Seeder | Tables | Data | Issues |
|--------|--------|------|--------|
| **PermissionSeeder** | `permissions` | 96 permission records | Called twice (direct + nested), otherwise correct |
| **RoleAndPermissionSeeder** | `roles`, `role_has_permissions`, `model_has_roles`, `users` | 3 global roles (superadmin, admin, customer), superadmin user (admin@shop.com) | Combines 3 concerns (roles + permissions + user). Should split into RoleSeeder + SuperAdminSeeder |
| **PlanSeeder** | `plans`, `plan_features` | Free ($0), Starter ($9.99), Business ($29.99) plans with feature mappings | **ORPHANED** — not called from DatabaseSeeder. SaaS cannot function without plans |
| **LocationSeeder** | `cities`, `townships` | 9 Myanmar cities with ~50 townships | Uses `create()` not `firstOrCreate()` (not idempotent) |
| **TenantSeeder** | `tenants` + 27 backfill tables | Creates default tenant (id=1), backfills null tenant_ids | **VIOLATION**: Creates a tenant during `--seed`. Backfill is a data integrity crutch |

**Total System Seeders: 5**

---

## 4. Demo Seeders

Testing / development data. Should NOT run in production.

| Seeder | Tables | Data | Issues |
|--------|--------|------|--------|
| **UserSeeder** | `users`, `model_has_roles` | 10 customer users (john@example.com, jane@example.com, etc.) | Seeded in production path. No explicit tenant_id |
| **ProductSeeder** | `products` | 10 products via factory | Seeded in production path. Factory randomness |
| **OrderSeeder** | `orders`, `order_items` | 20 orders with 1-4 items each | Seeded in production path. Depends on UserSeeder + ProductSeeder |

**Total Demo Seeders: 3**

---

## 5. Tenant Bootstrap Seeders

Data that should be created when a merchant creates a store, NOT during global seeding.

| Seeder | Tables | Data | Issues |
|--------|--------|------|--------|
| **WebsiteSettingsSeeder** | `website_infos` | Single global WebsiteInfo record | **VIOLATION**: Creates platform-level settings as a single global row. All tenants share the same WebsiteInfo. Should be per-tenant bootstrap |
| **PaymentMethodSeeder** | `payment_methods` | 5 payment methods (KBZ Pay, WavePay, AYA Pay, Bank Transfer, COD) | **VIOLATION**: Creates global payment methods. Uses `create()` not `firstOrCreate()`. Should be per-tenant |
| **CategorySeeder** | `categories` | 10 default categories (Electronics, Fashion, Home & Kitchen, etc.) | **VIOLATION**: Creates global categories with no tenant_id. Should be per-tenant bootstrap |
| **UnitSeeder** | `units` | 14 default units per existing tenant (Piece, kg, g, L, etc.) | **VIOLATION**: Only seeds units for tenants that exist AT SEED TIME. New tenants get nothing. Should be tenant bootstrap |
| **BrandSeeder** | `brands` | 6 default brands per existing tenant (Samsung, Apple, Nike, etc.) | **VIOLATION**: Same as UnitSeeder — only existing tenants. Should be tenant bootstrap |

**Total Tenant Bootstrap Seeders: 5**

---

## 6. SaaS Violations

### Critical Violations

| # | Violation | Seeder | Impact |
|---|-----------|--------|--------|
| V1 | **Tenant created during seeding** | TenantSeeder | `php artisan migrate:fresh --seed` creates a default store. A fresh install should have ZERO tenants |
| V2 | **Orphaned PlanSeeder** | PlanSeeder | Plans never seeded. `Plan::free()` returns null. Store creation silently skips subscription setup |
| V3 | **Demo data in production path** | UserSeeder, ProductSeeder, OrderSeeder | Running `--seed` in production creates test users, products, and orders |
| V4 | **Tenant bootstrap data seeded globally** | WebsiteSettingsSeeder, PaymentMethodSeeder, CategorySeeder | Platform-level seeding creates data that should be per-tenant. Data isolation failure |
| V5 | **Backfill crutch** | TenantSeeder | `TenantSeeder` backfills null tenant_ids to tenant 1. This masks the real problem: data should have tenant_ids at creation time |

### Medium Violations

| # | Violation | Seeder | Impact |
|---|-----------|--------|--------|
| V6 | **Future tenants miss defaults** | UnitSeeder, BrandSeeder | Units/brands only seeded for tenants existing at seed time. New tenants get nothing |
| V7 | **PermissionSeeder called twice** | PermissionSeeder (nested) | Cache flushed twice, unnecessary DB iteration |
| V8 | **Non-idempotent creates** | PaymentMethodSeeder, LocationSeeder | Using `create()` instead of `firstOrCreate()` causes duplicates if re-seeded |
| V9 | **RoleAndPermissionSeeder is overloaded** | RoleAndPermissionSeeder | Creates roles + permissions + superadmin user in one class. Violates single responsibility |

---

## 7. DatabaseSeeder Review

### Currently Executed (12 seeders):

```
 1. PermissionSeeder::class         ← SYSTEM (correct, but called twice)
 2. RoleAndPermissionSeeder::class  ← SYSTEM (correct, but overloaded)
 3. WebsiteSettingsSeeder::class    ← TENANT BOOTSTRAP (should NOT be here)
 4. PaymentMethodSeeder::class      ← TENANT BOOTSTRAP (should NOT be here)
 5. LocationSeeder::class           ← SYSTEM (correct)
 6. UserSeeder::class               ← DEMO (should NOT be here)
 7. CategorySeeder::class           ← TENANT BOOTSTRAP (should NOT be here)
 8. ProductSeeder::class            ← DEMO (should NOT be here)
 9. OrderSeeder::class              ← DEMO (should NOT be here)
10. UnitSeeder::class               ← TENANT BOOTSTRAP (should NOT be here)
11. BrandSeeder::class              ← TENANT BOOTSTRAP (should NOT be here)
12. TenantSeeder::class             ← SYSTEM but creates tenant (VIOLATION)
```

### Required Seeders:

```
 1. PermissionSeeder::class         ← SYSTEM
 2. RoleAndPermissionSeeder::class  ← SYSTEM (or split)
 3. LocationSeeder::class           ← SYSTEM
 4. PlanSeeder::class               ← SYSTEM (currently orphaned)
 5. TenantSeeder::class             ← SYSTEM (backfill only, no tenant creation)
```

### Unnecessary Seeders (in DatabaseSeeder):

```
 1. WebsiteSettingsSeeder::class    ← Move to TenantBootstrapService
 2. PaymentMethodSeeder::class      ← Move to TenantBootstrapService
 3. CategorySeeder::class           ← Move to TenantBootstrapService
 4. UserSeeder::class               ← Move to DemoSeeder
 5. ProductSeeder::class            ← Move to DemoSeeder
 6. OrderSeeder::class              ← Move to DemoSeeder
 7. UnitSeeder::class               ← Move to TenantBootstrapService
 8. BrandSeeder::class              ← Move to TenantBootstrapService
```

---

## 8. Recommended Architecture

### Target: `php artisan migrate:fresh --seed`

After seed:
- ✓ Superadmin exists (admin@shop.com)
- ✓ Permissions exist (96 records)
- ✓ Plans exist (Free, Starter, Business)
- ✓ Locations exist (9 cities, ~50 townships)
- ✓ Platform settings exist
- ✗ No tenants created
- ✗ No merchant stores
- ✗ No products
- ✗ No orders

### Proposed DatabaseSeeder

```php
// database/seeders/DatabaseSeeder.php
public function run(): void
{
    $this->call([
        // SYSTEM SEEDERS (always required)
        PermissionSeeder::class,           // 96 permissions
        RoleAndPermissionSeeder::class,    // global roles + superadmin user
        PlanSeeder::class,                 // subscription plans
        LocationSeeder::class,             // shared cities/townships
        TenantSeeder::class,               // integrity backfill (no tenant creation)
    ]);
}
```

### Proposed DemoSeeder (development only)

```php
// database/seeders/DemoSeeder.php
public function run(): void
{
    $this->call([
        UserSeeder::class,                 // 10 demo customers
        ProductSeeder::class,              // 10 demo products
        OrderSeeder::class,                // 20 demo orders
    ]);
}
```

Called via: `php artisan db:seed --class=DemoSeeder` or `php artisan migrate:fresh --seed --seeder=DemoSeeder`

### Proposed TenantBootstrapService

```php
// app/Services/TenantBootstrapService.php
class TenantBootstrapService
{
    public function bootstrap(Tenant $tenant): void
    {
        $this->createRoles($tenant);           // admin, customer roles
        $this->createWebsiteSettings($tenant); // site name, branding, etc.
        $this->createPaymentMethods($tenant);  // KBZ Pay, WavePay, etc.
        $this->createCategories($tenant);      // Electronics, Fashion, etc.
        $this->createUnits($tenant);           // Piece, kg, g, L, etc.
        $this->createBrands($tenant);          // Samsung, Apple, Nike, etc.
        $this->assignFreePlan($tenant);        // subscription
    }
}
```

### Seeder Directory Structure (Recommended)

```
database/seeders/
├── DatabaseSeeder.php           ← SYSTEM only
├── DemoSeeder.php               ← DEMO only (not in production)
│
├── PermissionSeeder.php         ← SYSTEM (KEEP)
├── RoleAndPermissionSeeder.php  ← SYSTEM (KEEP or split)
├── PlanSeeder.php               ← SYSTEM (ADD to call list)
├── LocationSeeder.php           ← SYSTEM (KEEP, make idempotent)
├── TenantSeeder.php             ← SYSTEM (REFACTOR: remove tenant creation, keep backfill)
│
├── UserSeeder.php               ← DEMO (MOVE to DemoSeeder)
├── ProductSeeder.php            ← DEMO (MOVE to DemoSeeder)
├── OrderSeeder.php              ← DEMO (MOVE to DemoSeeder)
│
├── WebsiteSettingsSeeder.php    ← REMOVE (replace with bootstrap)
├── PaymentMethodSeeder.php      ← REMOVE (replace with bootstrap)
├── CategorySeeder.php           ← REMOVE (replace with bootstrap)
├── UnitSeeder.php               ← REMOVE (replace with bootstrap)
├── BrandSeeder.php              ← REMOVE (replace with bootstrap)
```

---

## 9. Migration Plan

### Phase 1: Audit Complete (Current)
- All 14 seeders inventoried and classified
- This document created

### Phase 2: System Seeder Fixes

| Step | Action | Files |
|------|--------|-------|
| 2.1 | Add PlanSeeder to DatabaseSeeder call list | `DatabaseSeeder.php` |
| 2.2 | Remove nested PermissionSeeder call from RoleAndPermissionSeeder | `RoleAndPermissionSeeder.php:79` |
| 2.3 | Remove tenant creation logic from TenantSeeder (keep backfill only) | `TenantSeeder.php` |
| 2.4 | Convert LocationSeeder to use `firstOrCreate` | `LocationSeeder.php` |

### Phase 3: TenantBootstrapService

| Step | Action | Files |
|------|--------|-------|
| 3.1 | Create `app/Services/TenantBootstrapService.php` | New file |
| 3.2 | Add `createRoles()` method | TenantBootstrapService |
| 3.3 | Add `createWebsiteSettings()` method | TenantBootstrapService |
| 3.4 | Add `createPaymentMethods()` method | TenantBootstrapService |
| 3.5 | Add `createCategories()` method | TenantBootstrapService |
| 3.6 | Add `createUnits()` method | TenantBootstrapService |
| 3.7 | Add `createBrands()` method | TenantBootstrapService |
| 3.8 | Add `assignFreePlan()` method | TenantBootstrapService |

### Phase 4: Controller Refactoring

| Step | Action | Files |
|------|--------|-------|
| 4.1 | Replace inline bootstrap in CreateStoreController with TenantBootstrapService | `CreateStoreController.php` |
| 4.2 | Replace inline bootstrap in TenantController with TenantBootstrapService | `TenantController.php` |

### Phase 5: Demo Separation

| Step | Action | Files |
|------|--------|-------|
| 5.1 | Create `DemoSeeder.php` calling UserSeeder, ProductSeeder, OrderSeeder | New file |
| 5.2 | Remove UserSeeder, ProductSeeder, OrderSeeder from DatabaseSeeder | `DatabaseSeeder.php` |

### Phase 6: Cleanup

| Step | Action | Files |
|------|--------|-------|
| 6.1 | Remove WebsiteSettingsSeeder from DatabaseSeeder | `DatabaseSeeder.php` |
| 6.2 | Remove PaymentMethodSeeder from DatabaseSeeder | `DatabaseSeeder.php` |
| 6.3 | Remove CategorySeeder from DatabaseSeeder | `DatabaseSeeder.php` |
| 6.4 | Remove UnitSeeder from DatabaseSeeder | `DatabaseSeeder.php` |
| 6.5 | Remove BrandSeeder from DatabaseSeeder | `DatabaseSeeder.php` |

---

## 10. Risk Analysis

| Risk | Severity | Phase | Impact | Mitigation |
|------|----------|-------|--------|------------|
| PlanSeeder orphaned → `Plan::free()` returns null | **HIGH** | 2.1 | New stores get no subscription. `CreateStoreController` silently skips subscription creation. | Add PlanSeeder to DatabaseSeeder FIRST before any other change |
| TenantSeeder removal breaks existing installs | **MEDIUM** | 2.3 | Existing databases with null tenant_ids would not be backfilled | Keep backfill logic. Only remove tenant creation. Run TenantSeeder after all system seeders |
| PermissionSeeder duplicate call is low risk | **LOW** | 2.2 | Minor performance hit from double cache flush | Remove nested call. Both use `firstOrCreate` so no data corruption |
| BootstrapService replaces controller logic | **MEDIUM** | 4.1, 4.2 | Existing tenant creation paths change behavior if service differs from inline logic | Ensure service replicates exact current behavior. Test both public registration and superadmin creation paths |
| Demo data removed from production path | **LOW** | 5.1, 5.2 | Dev environments lose convenience of demo data on `--seed` | Document `php artisan db:seed --class=DemoSeeder` as replacement |
| BrandSeeder/UnitSeeder removal | **LOW** | 6.4, 6.5 | Existing tenants lose brand/unit seeding on `--seed` | BootstrapService handles it at tenant creation time. Existing tenants unaffected |

---

## 11. Version 3 Readiness

| Requirement | Status | Action Needed |
|-------------|--------|---------------|
| System seeders identified | ✅ Complete | Phase 2 |
| Demo seeders separated | ⚠️ Partial | Phase 5 |
| Tenant bootstrap extracted | ❌ Not started | Phase 3 |
| TenantSeeder violations fixed | ⚠️ Partial | Phase 2.3 |
| PlanSeeder orphaned | ❌ Not started | Phase 2.1 |
| No default tenant on fresh install | ❌ Not started | Phase 2.3 |
| No demo data in production path | ❌ Not started | Phase 5 |
| Tenant data seeded per-tenant | ❌ Not started | Phase 3 + 4 |
| PermissionSeeder duplicate call | ⚠️ Identified | Phase 2.2 |
| Non-idempotent seeders fixed | ⚠️ Identified | Phase 2.4 |

### Readiness Score: **35%**

The architecture is clearly identified but no refactoring has been applied. The separation boundary between System/Demo/TenantBootstrap is well-defined. Implementation requires Phases 2-6.

---

## Summary

| Metric | Count |
|--------|-------|
| **Total Seeders** | 14 |
| **System Seeders** | 5 (PermissionSeeder, RoleAndPermissionSeeder, PlanSeeder, LocationSeeder, TenantSeeder) |
| **Demo Seeders** | 3 (UserSeeder, ProductSeeder, OrderSeeder) |
| **Tenant Bootstrap Seeders** | 5 (WebsiteSettingsSeeder, PaymentMethodSeeder, CategorySeeder, UnitSeeder, BrandSeeder) |
| **Other** | 1 (DatabaseSeeder — orchestrator) |
| **DatabaseSeeder Issues** | 8 (demo data in prod, tenant bootstrap data in prod, PlanSeeder missing, PermissionSeeder duplicate, TenantSeeder creates tenant) |
| **SaaS Violations Found** | 9 (V1-V9) |
| **Recommended Refactors** | 6 phases (system fixes, bootstrap service, controller refactor, demo separation, cleanup, idempotency) |
| **Version 3 Readiness** | 35% (architecture identified, implementation pending) |
