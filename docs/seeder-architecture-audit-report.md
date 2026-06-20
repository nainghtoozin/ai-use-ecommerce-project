# Seeder Architecture Audit Report

## Status: Completed (Read-Only Audit)

---

## 1. Seeder Inventory

Total: 14 seeders

| # | Seeder | File | Lines | Referenced By |
|---|--------|------|-------|---------------|
| 1 | `DatabaseSeeder` | `database/seeders/DatabaseSeeder.php` | 29 | `artisan db:seed` |
| 2 | `PermissionSeeder` | `database/seeders/PermissionSeeder.php` | 143 | DatabaseSeeder, RoleAndPermissionSeeder |
| 3 | `RoleAndPermissionSeeder` | `database/seeders/RoleAndPermissionSeeder.php` | 110 | DatabaseSeeder |
| 4 | `WebsiteSettingsSeeder` | `database/seeders/WebsiteSettingsSeeder.php` | 90 | DatabaseSeeder |
| 5 | `PaymentMethodSeeder` | `database/seeders/PaymentMethodSeeder.php` | 54 | DatabaseSeeder |
| 6 | `LocationSeeder` | `database/seeders/LocationSeeder.php` | 139 | DatabaseSeeder |
| 7 | `UserSeeder` | `database/seeders/UserSeeder.php` | 41 | DatabaseSeeder |
| 8 | `CategorySeeder` | `database/seeders/CategorySeeder.php` | 32 | DatabaseSeeder |
| 9 | `ProductSeeder` | `database/seeders/ProductSeeder.php` | 14 | DatabaseSeeder |
| 10 | `OrderSeeder` | `database/seeders/OrderSeeder.php` | 47 | DatabaseSeeder |
| 11 | `UnitSeeder` | `database/seeders/UnitSeeder.php` | 52 | DatabaseSeeder |
| 12 | `BrandSeeder` | `database/seeders/BrandSeeder.php` | 44 | DatabaseSeeder |
| 13 | `TenantSeeder` | `database/seeders/TenantSeeder.php` | 102 | DatabaseSeeder (last) |
| 14 | `PlanSeeder` | `database/seeders/PlanSeeder.php` | 135 | **NOT referenced anywhere** |

---

## 2. Seeder Classification

### GLOBAL SEEDERS (6)

| Seeder | Tables Affected | Purpose | Issues |
|--------|----------------|---------|--------|
| **PermissionSeeder** | `permissions` | Creates 77 permission records via `firstOrCreate` | Called TWICE during `db:seed` (direct + via RoleAndPermissionSeeder) |
| **RoleAndPermissionSeeder** | `roles`, `role_has_permissions`, `model_has_roles`, `users` | Creates 3 global roles (superadmin/admin/customer), assigns permissions, creates Super Admin user (admin@shop.com) | Combines role creation + user creation + permission assignment in one seeder |
| **WebsiteSettingsSeeder** | `website_infos` | Creates single WebsiteInfo record (id=1) with default branding, contact, SEO, social links | Global only; no tenant-specific settings |
| **PaymentMethodSeeder** | `payment_methods` | Creates 5 payment methods (KBZ Pay, WavePay, AYA Pay, Bank Transfer, COD) | No tenant_id; uses `create()` not `firstOrCreate()` |
| **LocationSeeder** | `cities`, `townships` | Creates 9 Myanmar cities with ~50 townships | Uses `create()` not `firstOrCreate()`; no tenant_id |
| **CategorySeeder** | `categories` | Creates 10 default categories (Electronics, Fashion, etc.) | Uses `firstOrCreate` on `name` only; no tenant_id |

### TENANT BOOTSTRAP SEEDERS (1)

| Seeder | Tables Affected | Purpose | Issues |
|--------|----------------|---------|--------|
| **TenantSeeder** | 27 tenant-scoped tables | Ensures default tenant (id=1) exists; backfills null `tenant_id` values | Data integrity utility, not true bootstrap |

Note: No seeder is responsible for tenant bootstrapping. All tenant bootstrap logic lives in runtime controllers (`CreateStoreController`, `TenantController`).

### DEV/DEMO SEEDERS (5)

| Seeder | Tables Affected | Purpose | Issues |
|--------|----------------|---------|--------|
| **UserSeeder** | `users`, `model_has_roles` | Creates 10 demo customer users with `customer` role | No explicit tenant_id; relies on `TenantAware` trait's `creating` callback which reads `Tenant::getCurrent()` |
| **ProductSeeder** | `products` | Creates 10 products via factory | No tenant context management |
| **OrderSeeder** | `orders`, `order_items` | Creates 20 orders with 1-4 items each via factories | Relies on UserSeeder + ProductSeeder running first |
| **UnitSeeder** | `units` | Creates 14 default units for ALL existing tenants | Iterates `Tenant::all()`. Does NOT create units for future tenants |
| **BrandSeeder** | `brands` | Creates 6 default brands for ALL existing tenants | Same issue as UnitSeeder |

### ORPHANED SEEDER (1)

| Seeder | Tables Affected | Purpose | Status |
|--------|----------------|---------|--------|
| **PlanSeeder** | `plans`, `plan_features` | Creates 3 plans (Free $0, Starter $9.99, Business $29.99) with feature mappings | **Exists but NEVER called** from DatabaseSeeder or anywhere else |

---

## 3. Duplicate Responsibilities

### 3.1 PermissionSeeder runs twice

```
DatabaseSeeder::run()
  ├── PermissionSeeder::run()         ← 1st execution
  ├── RoleAndPermissionSeeder::run()
  │     ├── PermissionSeeder::run()   ← 2nd execution (nested call)
  │     ├── Creates roles
  │     ├── Assigns permissions
  │     └── Creates Super Admin user
  ├── ...
  └── TenantSeeder::run()
```

Both calls use `firstOrCreate`, so no duplicate records are created. However, the database is iterated twice and permission cache is flushed twice. **Minor performance issue, not a data bug.**

### 3.2 Role creation logic duplicated in 3 places

The same pattern (look up global role → create tenant-scoped role → sync permissions) appears in:

| Location | File | Purpose |
|----------|------|---------|
| Runtime | `CreateStoreController::store()` | Public store registration |
| Runtime | `TenantController::store()` | Super admin manual tenant creation |
| Command | `SyncTenantRoles::handle()` | Artisan remediation command |

No shared `TenantBootstrapService` or `CreateTenantAction` class exists.

### 3.3 Default data creation split across seeders and runtime

| Data | Seeder Creates? | Runtime Creates? |
|------|----------------|------------------|
| Permissions | ✅ PermissionSeeder (global) | ❌ No |
| Global roles (templates) | ✅ RoleAndPermissionSeeder | ❌ No |
| Tenant-scoped roles | ❌ No | ✅ CreateStoreController, TenantController |
| Admin user | ✅ RoleAndPermissionSeeder (global) | ✅ CreateStoreController, TenantController |
| Customer users | ✅ UserSeeder (demo) | ❌ No (customers self-register) |
| Website settings | ✅ WebsiteSettingsSeeder (global) | ❌ No |
| Payment methods | ✅ PaymentMethodSeeder (global) | ❌ No |
| Categories | ✅ CategorySeeder (global) | ❌ No |
| Cities/Townships | ✅ LocationSeeder (global) | ❌ No |
| Brands | ✅ BrandSeeder (iterates existing tenants) | ❌ No |
| Units | ✅ UnitSeeder (iterates existing tenants) | ❌ No |
| Plans | ✅ PlanSeeder (exists, not called) | ❌ No |

---

## 4. Runtime vs Seeder Responsibilities

| Responsibility | Currently Handled By | Should Be? |
|---------------|---------------------|------------|
| Permission registration | PermissionSeeder (seeder) | Seeder (KEEP) |
| Global role template creation | RoleAndPermissionSeeder (seeder) | Seeder (KEEP) |
| Tenant role creation | CreateStoreController, TenantController (runtime) | Runtime (KEEP) |
| Owner admin user creation | CreateStoreController, TenantController (runtime) | Runtime (KEEP) |
| Subscription creation | CreateStoreController, TenantController (runtime) | Runtime (KEEP) |
| Tenant activation | ActivateTenantOnVerified listener (runtime) | Runtime (KEEP) |
| Default website settings per tenant | ❌ Missing | Runtime bootstrap |
| Default payment methods per tenant | ❌ Missing | Runtime bootstrap |
| Default categories per tenant | ❌ Missing | Runtime bootstrap |
| Default brands per tenant | ❌ Missing | Runtime bootstrap |
| Default units per tenant | ❌ Missing | Runtime bootstrap |
| Demo products/orders | ProductSeeder, OrderSeeder (seeders) | Seeder (REMOVE or move to separate demo seeder) |

---

## 5. Tenant Bootstrap Conflicts

### 5.1 Missing tenant bootstrap for default data

When a new tenant is created via `CreateStoreController`:
- ✅ Tenant-scoped admin + customer roles are created
- ✅ Subscription is created
- ✅ Owner admin user is created with all permissions
- ❌ **No website settings** created for the tenant
- ❌ **No payment methods** created for the tenant
- ❌ **No default categories** created for the tenant
- ❌ **No default brands** created for the tenant
- ❌ **No default units** created for the tenant

All of these are created globally by seeders but never per-tenant at runtime.

### 5.2 TenantScope visibility issue

Since `PaymentMethodSeeder`, `CategorySeeder`, `LocationSeeder`, and `WebsiteSettingsSeeder` create records **without** `tenant_id`, and the `TenantAware` trait's global scope filters by `tenant_id`, these records are potentially **invisible** to tenant-scoped queries unless `TenantSeeder` backfills them to the default tenant (id=1).

### 5.3 UnitSeeder/BrandSeeder only catch existing tenants

`UnitSeeder` and `BrandSeeder` iterate `Tenant::all()` during `db:seed`. New tenants created after seeding never get default units or brands.

### 5.4 Permission propagation

When a new permission is added to `PermissionSeeder`, it's:
- ✅ Created in the `permissions` table globally
- ✅ Assigned to `superadmin` role (via `Permission::all()`)
- ❌ **Not assigned** to existing tenant-scoped `admin` roles (those roles only synced permissions at creation time)
- ❌ **Not assigned** to existing `admin` users

This means adding a new permission requires running `php artisan tenants:sync-roles` to propagate it to existing tenants' admin roles.

---

## 6. Global Seeders

| Seeder | Current Responsibility | Problems |
|--------|----------------------|----------|
| PermissionSeeder | Single source of truth for all permissions | ✅ Good. CALLED TWICE (minor) |
| RoleAndPermissionSeeder | Creates 3 global role templates + Super Admin user | ❌ Combines 3 concerns (roles, permissions, user) into one seeder |
| WebsiteSettingsSeeder | Creates single global WebsiteInfo | ❌ Tenant bootstrap should create per-tenant settings |
| PaymentMethodSeeder | Creates 5 global payment methods | ❌ `create()` not idempotent; no tenant_id |
| LocationSeeder | Creates 9 cities + ~50 townships | ❌ `create()` not idempotent; no tenant_id |
| CategorySeeder | Creates 10 default categories | ❌ No tenant_id |

---

## 7. Tenant Seeders

| Seeder | Current Responsibility | Problems |
|--------|----------------------|----------|
| TenantSeeder | Backfills null tenant_ids; ensures default tenant | ✅ Good as data integrity tool. Should remain. |
| UnitSeeder | Creates 14 units for each existing tenant | ❌ Missing for new tenants; iterates all tenants |
| BrandSeeder | Creates 6 brands for each existing tenant | ❌ Same issue |

The project has **no true tenant bootstrap seeders**. All tenant-specific initialization happens at runtime in controllers.

---

## 8. Demo/Test Seeders

| Seeder | Data Volume | Production Suitability | Recommendation |
|--------|------------|----------------------|----------------|
| UserSeeder | 10 customer users | ❌ Not suitable (demo emails, fixed passwords) | Move to separate `DemoSeeder` |
| ProductSeeder | 10 products via factory | ❌ Not suitable (factory randomness) | Move to separate `DemoSeeder` |
| OrderSeeder | 20 orders with items | ❌ Not suitable (factory randomness) | Move to separate `DemoSeeder` |

---

## 9. Keep / Refactor / Remove Matrix

| Seeder | Category | V3 Action | Rationale |
|--------|----------|-----------|-----------|
| PermissionSeeder | Global | **KEEP** | Single source of truth for permissions. Minor: remove nested call from RoleAndPermissionSeeder. |
| RoleAndPermissionSeeder | Global | **REFACTOR** | Split into: (a) `RoleSeeder` — creates global role templates, (b) `SuperAdminSeeder` — creates super admin user. Remove nested PermissionSeeder call. |
| TenantSeeder | Integrity | **KEEP** | Essential data integrity utility for backfilling. |
| WebsiteSettingsSeeder | Global | **REFACTOR** | Make idempotent. Consider making tenant-bootstrap-aware or creating per-tenant default settings at runtime. |
| PaymentMethodSeeder | Global | **REFACTOR** | Replace with runtime bootstrap: create default payment methods per tenant during store creation. |
| LocationSeeder | Global | **REFACTOR** | Make idempotent (`firstOrCreate`). Keep global as shared reference data. |
| CategorySeeder | Global | **REFACTOR** | Make tenant-bootstrap-aware or create at runtime per tenant. |
| UnitSeeder | Dev/Demo | **REFACTOR** | Move into tenant bootstrap (create units for each new tenant at runtime, not just existing ones). |
| BrandSeeder | Dev/Demo | **REFACTOR** | Same as UnitSeeder — move into tenant bootstrap. |
| UserSeeder | Demo | **REMOVE** | Demo data. Move to separate `DemoSeeder` not called from production DatabaseSeeder. |
| ProductSeeder | Demo | **REMOVE** | Demo data. Move to `DemoSeeder`. |
| OrderSeeder | Demo | **REMOVE** | Demo data. Move to `DemoSeeder`. |
| PlanSeeder | Global | **KEEP** | Should be called from DatabaseSeeder (currently orphaned). |

---

## 10. Version 3 Recommendations

### 10.1 Extract TenantBootstrapService

Create a shared `TenantBootstrapService` to replace the duplicated role-creation logic in `CreateStoreController`, `TenantController`, and `SyncTenantRoles`:

```php
class TenantBootstrapService
{
    public function bootstrap(Tenant $tenant, array $options = []): void
    {
        // Create tenant-scoped roles (cloned from global templates)
        $this->createTenantRoles($tenant);

        // Create default payment methods
        $this->createPaymentMethods($tenant);

        // Create default categories
        $this->createCategories($tenant);

        // Create default units
        $this->createUnits($tenant);

        // Create default brands
        $this->createBrands($tenant);

        // Create default website settings
        $this->createWebsiteSettings($tenant);
    }
}
```

### 10.2 Fix PermissionSeeder duplication

Remove the `$this->call(PermissionSeeder::class)` from `RoleAndPermissionSeeder::run()` — it's already called by `DatabaseSeeder`.

### 10.3 Move role template creation to dedicated seeder

Split `RoleAndPermissionSeeder`:

- **`RoleSeeder`**: Creates 3 global role templates (`superadmin`, `admin`, `customer`) with base permissions
- **`SuperAdminSeeder`**: Creates the platform super admin user (`admin@shop.com`) and assigns `superadmin` role

### 10.4 Create DemoSeeder for development

Move `UserSeeder`, `ProductSeeder`, and `OrderSeeder` into a standalone `DemoSeeder` that is NOT called from production `DatabaseSeeder`:

```php
// database/seeders/DatabaseSeeder.php (production)
$this->call([
    PermissionSeeder::class,
    RoleSeeder::class,
    SuperAdminSeeder::class,
    PlanSeeder::class,
    TenantSeeder::class,
]);
```

```php
// database/seeders/DemoSeeder.php (development only)
public function run(): void
{
    $this->call([
        UserSeeder::class,
        CategorySeeder::class,  // if not made tenant-bootstrap
        BrandSeeder::class,     // if not made tenant-bootstrap
        UnitSeeder::class,      // if not made tenant-bootstrap
        ProductSeeder::class,
        OrderSeeder::class,
    ]);
}
```

### 10.5 Orphaned PlanSeeder

Add `PlanSeeder::class` to `DatabaseSeeder`. Plans are essential for the SaaS subscription system — without them, `Plan::free()` returns null and `CreateStoreController` skips subscription creation.

### 10.6 Permission propagation mechanism

Implement a command or service to propagate newly-added permissions to existing tenant-scoped admin roles:
```bash
php artisan tenants:sync-permissions
```

This should:
1. Find all permissions that exist globally but are missing from tenant `admin` role permission sets
2. Add them

### 10.7 Use firstOrCreate consistently

Convert `PaymentMethodSeeder` and `LocationSeeder` from `create()` to `firstOrCreate()` for idempotency.

---

## 11. Risk Analysis

| Risk | Severity | Impact | Mitigation |
|------|----------|--------|------------|
| PlanSeeder not called → `Plan::free()` returns null | **High** | New tenants created via public registration get NO subscription record | Add PlanSeeder to DatabaseSeeder |
| PermissionSeeder runs twice | Low | Cache flushed twice, unnecessary DB iteration | Remove nested call in RoleAndPermissionSeeder |
| New permissions not propagated to existing tenants | **Medium** | Existing tenant admin roles lack new permissions | Create `tenants:sync-permissions` command |
| Demo seeders in production path | **Medium** | Test users, products, orders created in production | Move to DemoSeeder, exclude from production |
| PaymentMethodSeeder uses `create()` not `firstOrCreate()` | Low | Duplicate records on re-seed | Change to `firstOrCreate` |
| LocationSeeder uses `create()` not `firstOrCreate()` | Low | Duplicate cities/townships on re-seed | Change to `firstOrCreate` |
| New tenants get no default data (categories, payment methods, brands, units) | **Medium** | Tenant admin must manually create all default data | Implement TenantBootstrapService |
| UnitSeeder/BrandSeeder don't cover future tenants | **Medium** | Tenants created after seeding have no units/brands | Move to tenant bootstrap |
| UserSeeder depends on Tenant::getCurrent() in CLI | Low | May create users in wrong tenant context | Add explicit tenant_id |

---

## 12. Proposed Seeder Architecture (V3)

```
database/seeders/
├── DatabaseSeeder.php          ← Calls global seeders only
├── DemoSeeder.php              ← Calls dev/demo seeders (NOT in production)
│
├── Global/
│   ├── PermissionSeeder.php    ← KEEP: single permission source of truth
│   ├── RoleSeeder.php          ← REFACTORED from RoleAndPermissionSeeder
│   ├── PlanSeeder.php          ← KEEP: add to DatabaseSeeder call list
│   └── LocationSeeder.php      ← KEEP: make idempotent
│
├── Bootstrap/
│   ├── SuperAdminSeeder.php    ← REFACTORED from RoleAndPermissionSeeder
│   └── TenantSeeder.php        ← KEEP: data integrity utility
│
├── Demo/
│   ├── UserSeeder.php          ← MOVED from root
│   ├── ProductSeeder.php       ← MOVED from root
│   ├── OrderSeeder.php         ← MOVED from root
│   ├── CategorySeeder.php      ← MOVED from root (or made bootstrap)
│   ├── UnitSeeder.php          ← MOVED from root (or made bootstrap)
│   └── BrandSeeder.php         ← MOVED from root (or made bootstrap)
│
└── (Removed)
    ├── RoleAndPermissionSeeder.php  ← REPLACED by RoleSeeder + SuperAdminSeeder
    ├── PaymentMethodSeeder.php      ← REPLACED by runtime bootstrap
    └── WebsiteSettingsSeeder.php    ← REPLACED by runtime bootstrap (or kept global)
```

### Runtime Bootstrap (new)

```
app/Services/
└── TenantBootstrapService.php   ← Centralizes: role cloning, payment methods,
                                   categories, units, brands, settings creation
```

### Commands (new or refactored)

```
app/Console/Commands/
├── SyncTenantRoles.php          ← KEEP: still useful for remediation
└── SyncTenantPermissions.php    ← NEW: propagate new permissions to tenants
```

---

## Summary of All Seeders

| # | Seeder | Tables | Type | Status | V3 Action |
|---|--------|--------|------|--------|-----------|
| 1 | PermissionSeeder | permissions | Global | ✅ Used, called twice | KEEP — remove nested call |
| 2 | RoleAndPermissionSeeder | roles, role_has_permissions, model_has_roles, users | Global | ✅ Used | REFACTOR — split into RoleSeeder + SuperAdminSeeder |
| 3 | WebsiteSettingsSeeder | website_infos | Global | ✅ Used | REFACTOR — move to runtime bootstrap or make tenant-aware |
| 4 | PaymentMethodSeeder | payment_methods | Global | ✅ Used | REFACTOR — move to runtime bootstrap |
| 5 | LocationSeeder | cities, townships | Global | ✅ Used | REFACTOR — make idempotent |
| 6 | CategorySeeder | categories | Global | ✅ Used | REFACTOR — move to runtime bootstrap or DemoSeeder |
| 7 | UserSeeder | users, model_has_roles | Demo | ✅ Used | REMOVE → move to DemoSeeder |
| 8 | ProductSeeder | products | Demo | ✅ Used | REMOVE → move to DemoSeeder |
| 9 | OrderSeeder | orders, order_items | Demo | ✅ Used | REMOVE → move to DemoSeeder |
| 10 | UnitSeeder | units | Demo | ✅ Used | REFACTOR — move to tenant bootstrap |
| 11 | BrandSeeder | brands | Demo | ✅ Used | REFACTOR — move to tenant bootstrap |
| 12 | TenantSeeder | 27 tenant-scoped tables | Integrity | ✅ Used | KEEP |
| 13 | PlanSeeder | plans, plan_features | Global | ❌ Orphaned | KEEP — add to DatabaseSeeder |
| 14 | DatabaseSeeder | (orchestrator) | — | ✅ Used | KEEP — restructure call list |
