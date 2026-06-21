# V3-A5 TenantBootstrapService — Design & Implementation Plan

## Status: Completed (Design Only)

---

## 1. Executive Summary

Tenant initialization logic is currently scattered across **3 controllers** (`CreateStoreController`, `TenantController`, `RegisteredUserController`), **5 seeders** that create data that should be per-tenant, and **1 model method** (`WebsiteInfo::getSettings()`) that lazy-creates settings with broken tenant scoping. This design centralizes all tenant initialization into a single `App\Services\TenantBootstrapService` with a clean public API, consistent error handling, event-driven extensibility, and a phased implementation roadmap. The service will eliminate 3 instances of duplicated role creation code, guarantee default data for every new tenant, and fix the WebsiteInfo data isolation gap.

---

## 2. Current Bootstrap Inventory

### Controllers (Inline Bootstrap Logic)

| File | Lines | Bootstrap Responsibilities | Duplicated? |
|------|-------|---------------------------|-------------|
| `CreateStoreController.php` | 42-113 | Tenant create, Subscription create, Role create (admin+customer), Owner user create, Permission sync | ✅ Yes (role+user block) |
| `TenantController.php` | 67-137 | Tenant create, Subscription create, Role create (admin+customer), Owner user create (conditional), Permission sync | ✅ Yes (role+user block) |
| `RegisteredUserController.php` | 56-87 | Customer role firstOrCreate, Permission sync from global template | ✅ Yes (customer role) |

### Seeders (Tenant Bootstrap Data, Not System)

| Seeder | Tables | Data | Problem |
|--------|--------|------|---------|
| `WebsiteSettingsSeeder.php` | website_infos | Single global record | All tenants share same record. Data isolation failure |
| `PaymentMethodSeeder.php` | payment_methods | 5 methods, no tenant_id | Global, should be per-tenant |
| `CategorySeeder.php` | categories | 10 categories, no tenant_id | Global, should be per-tenant |
| `UnitSeeder.php` | units | 14 units per existing tenant | Future tenants get nothing |
| `BrandSeeder.php` | brands | 6 brands per existing tenant | Future tenants get nothing |

### Models with Bootstrap Logic

| Model | Method | Lines | Responsibility |
|-------|--------|-------|----------------|
| `WebsiteInfo.php` | `getSettings()` | 92-116 | Lazy-creates WebsiteInfo with defaults (but broken — no tenant scope) |
| `User.php` | `booted()` creating hook | 76-91 | Auto-assigns tenant_id, defaults status to active |

### Listener

| Listener | Event | Responsibility |
|----------|-------|----------------|
| `ActivateTenantOnVerified.php` | Verified | Activates tenant + subscription after email verification |

### Services (Not Bootstrap, But Related)

| Service | Responsibility | Bootstrap Relevance |
|---------|----------------|---------------------|
| `SubscriptionLimitService.php` | Enforces plan limits | Post-bootstrap enforcement |
| `SubscriptionExpiryService.php` | Lifecycle management | Post-bootstrap only |
| `FeatureGate.php` | Feature access control | Post-bootstrap only |

---

## 3. Responsibility Matrix

### MOVE INTO TENANTBOOTSTRAPSERVICE

| # | Responsibility | Current Location | Priority | Reason |
|---|---------------|-----------------|----------|--------|
| 1 | Create tenant-scoped roles (admin, customer, owner) | 3 controllers | **HIGH** | Eliminates triplicated code |
| 2 | Sync permissions from global role templates | 3 controllers | **HIGH** | Coupled to role creation |
| 3 | Create owner user with is_owner=true | 2 controllers | **HIGH** | Coupled to role/permission assignment |
| 4 | Assign role to owner + sync permissions | 2 controllers | **HIGH** | Coupled to user creation |
| 5 | Create subscription (Free plan assignment) | 2 controllers | **HIGH** | Essential for SaaS billing |
| 6 | Create default WebsiteInfo | WebsiteSettingsSeeder + WebsiteInfo::getSettings() | **HIGH** | Fix data isolation; guarantee per-tenant settings |
| 7 | Create default payment methods | PaymentMethodSeeder | **MEDIUM** | Each tenant needs their own payment config |
| 8 | Create default categories | CategorySeeder | **MEDIUM** | Each tenant needs starter categories |
| 9 | Create default brands | BrandSeeder | **MEDIUM** | Each tenant needs starter brands |
| 10 | Create default units | UnitSeeder | **MEDIUM** | Each tenant needs starter units |
| 11 | Ensure customer role exists | RegisteredUserController | **MEDIUM** | Simplify customer registration flow |

### KEEP OUTSIDE

| # | Responsibility | Reason |
|---|---------------|--------|
| 1 | SuperAdmin user creation | Platform-level, not tenant-level |
| 2 | Global role template creation (RoleAndPermissionSeeder) | System seed, runs once |
| 3 | Permission registry (PermissionSeeder) | System seed, runs once |
| 4 | Plan definitions (PlanSeeder) | System seed, runs once |
| 5 | Location data (LocationSeeder) | Shared reference data |
| 6 | Tenant data backfill (TenantSeeder) | Integrity utility |
| 7 | Tenant activation after email verification | Event-driven, async |
| 8 | Subscription lifecycle management | Ongoing process, not bootstrap |
| 9 | Plan enforcement (FeatureGate, SubscriptionLimitService) | Runtime checks, not bootstrap |

### NEEDS REVIEW

| # | Responsibility | Issue | Decision |
|---|---------------|-------|----------|
| 1 | Tenant record creation | Currently in controllers. Should bootstrap service receive existing tenant or create it? | Service receives existing tenant. Tenant creation stays in controllers/commands. |
| 2 | Email verification flow | Registered event → Verified event → Activation. Should bootstrap happen before or after? | Keep existing flow. Bootstrap runs synchronously in transaction. Activation happens on verify. |
| 3 | Subscription status | Currently `pending` for public registration, `active` for superadmin creation. Should service handle both? | Service accepts `$status` parameter with default `pending`. |

---

## 4. Service Design

### Namespace and Location

```
App\Services\TenantBootstrapService.php
```

### Class Signature

```php
namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TenantBootstrapService
{
    // Public API
    public function bootstrap(Tenant $tenant, array $options = []): User;
    public function ensureCustomerRole(Tenant $tenant): Role;

    // Internal
    protected function beginTransaction(): void;
    protected function commit(): void;
    protected function rollback(): void;

    // Role & Permission
    protected function createRoles(Tenant $tenant): void;
    protected function createRole(Tenant $tenant, string $name, array $permissions): Role;
    protected function ensureAdminRole(Tenant $tenant): Role;
    protected function ensureCustomerRoleInternal(Tenant $tenant): Role;
    protected function syncPermissions(Role $role, ?string $templateName = null): void;

    // User
    protected function createOwner(Tenant $tenant, array $ownerData): User;
    protected function assignOwnerRole(User $owner, Tenant $tenant): void;
    protected function assignOwnerPermissions(User $owner): void;

    // Subscription
    protected function createSubscription(Tenant $tenant, ?int $planId = null): Subscription;

    // Default Data
    protected function createWebsiteInfo(Tenant $tenant): WebsiteInfo;
    protected function createPaymentMethods(Tenant $tenant): void;
    protected function createCategories(Tenant $tenant): void;
    protected function createBrands(Tenant $tenant): void;
    protected function createUnits(Tenant $tenant): void;

    // Events
    protected function dispatchTenantCreated(Tenant $tenant, User $owner): void;
}
```

### Dependency Injection

```php
public function __construct(
    private readonly PlanService $planService,           // Resolve plans
    private readonly ImageService $imageService,         // For default images
    private readonly ActivityLogger $activityLogger,     // Audit trail
) {}
```

---

## 5. Method Structure

### Primary Public Method: `bootstrap()`

```php
/**
 * Full tenant bootstrap.
 *
 * @param Tenant $tenant  The newly created tenant (must have id)
 * @param array $options  {
 *     @type string  $owner_name     Required for public registration
 *     @type string  $owner_email    Required for public registration
 *     @type string  $owner_password Required for public registration
 *     @type int     $plan_id        Optional plan override (default: free)
 *     @type string  $status         Subscription status ('pending'|'active')
 *     @type bool    $create_owner   Create owner user (default: true)
 *     @type bool    $email_verified Pre-verify owner email (default: false)
 *     @type bool    $create_defaults Create default data (default: true)
 * }
 * @return User  The created owner user
 * @throws \RuntimeException On bootstrap failure
 */
public function bootstrap(Tenant $tenant, array $options = []): User
```

### Execution Sequence

```
bootstrap($tenant, $options)
  │
  ├── DB::beginTransaction()
  │
  ├── 1. createRoles($tenant)
  │     ├── createRole($tenant, 'owner', Permission::all())
  │     ├── createRole($tenant, 'admin', $adminPermissions)     // 44 perms
  │     ├── createRole($tenant, 'customer', $customerPermissions) // 4 perms
  │     └── (future: manager, staff)
  │
  ├── 2. createSubscription($tenant, $options['plan_id'] ?? null)
  │     ├── $plan = $plan_id ? Plan::find($plan_id) : Plan::free()
  │     ├── Subscription::create([...])
  │     └── Return $subscription
  │
  ├── 3. createOwner($tenant, $options)
  │     ├── User::create([...]) with is_owner=true
  │     ├── assignOwnerRole($owner, $tenant)     → role: owner
  │     ├── assignOwnerPermissions($owner)        → syncPermissions(ALL)
  │     └── Return $owner
  │
  ├── 4. createWebsiteInfo($tenant)
  │     ├── WebsiteInfo::create([...]) with tenant_id
  │     └── Return $websiteInfo
  │
  ├── 5. createPaymentMethods($tenant)   (if $options['create_defaults'])
  │     ├── PaymentMethod::create([...]) × 5
  │
  ├── 6. createCategories($tenant)       (if $options['create_defaults'])
  │     ├── Category::create([...]) × 10
  │
  ├── 7. createBrands($tenant)           (if $options['create_defaults'])
  │     ├── Brand::create([...]) × 6
  │
  ├── 8. createUnits($tenant)            (if $options['create_defaults'])
  │     ├── Unit::create([...]) × 14
  │
  ├── 9. dispatchTenantCreated($tenant, $owner)
  │     ├── event(new TenantCreated($tenant, $owner))
  │
  ├── DB::commit()
  │
  └── Return $owner
```

### Secondary Public Method: `ensureCustomerRole()`

```php
/**
 * Ensure a customer role exists for a tenant (used during customer registration).
 * Called by RegisteredUserController instead of inline role creation.
 *
 * @param Tenant $tenant
 * @return Role
 */
public function ensureCustomerRole(Tenant $tenant): Role
{
    $role = Role::firstOrCreate([
        'name' => 'customer',
        'guard_name' => 'web',
        'tenant_id' => $tenant->id,
    ]);

    if ($role->wasRecentlyCreated) {
        $this->syncPermissions($role, 'customer');
    }

    return $role;
}
```

---

## 6. Execution Flow Diagram

```
CURRENT FLOW (Simplified):

CreateStoreController           TenantController          RegisteredUserController
┌──────────────────────┐       ┌──────────────────────┐   ┌──────────────────────┐
│ Tenant::create()     │       │ Tenant::create()     │   │ Role::firstOrCreate  │
│ Subscription::create │       │ Subscription::create │   │   ('customer')       │
│ Role::create ×2      │       │ Role::create ×2      │   │ syncPermissions     │
│ User::create (owner) │       │ User::create (opt.)  │   │ assignRole          │
│ assignRole('admin')  │       │ assignRole('admin')  │   └──────────────────────┘
│ syncPermissions(all) │       │ syncPermissions(all) │
│ event(Registered)    │       └──────────────────────┘
└──────────────────────┘

             ║                                       ▲
             ║ DUPLICATED                             │ DUPLICATED
             ║                                        │
             ▼                                        │

TARGET FLOW:

CreateStoreController           TenantController          RegisteredUserController
┌──────────────────────┐       ┌──────────────────────┐   ┌────────────────────────┐
│ Tenant::create()     │       │ Tenant::create()     │   │ bootstrap.             │
│ bootstrap.           │       │ bootstrap.           │   │   ensureCustomerRole() │
│   bootstrap($tenant) │       │   bootstrap($tenant) │   └────────────────────────┘
│ event(Registered)    │       └──────────────────────┘
└──────────────────────┘
         │                        │
         └──────────┬─────────────┘
                    ▼
     TenantBootstrapService::bootstrap($tenant, $options)
     ┌─────────────────────────────────────────────────────┐
     │ 1. createRoles()           → owner, admin, customer │
     │ 2. createSubscription()    → Free plan              │
     │ 3. createOwner()           → is_owner=true          │
     │                           → assignRole('owner')    │
     │                           → syncPermissions(ALL)   │
     │ 4. createWebsiteInfo()     → per-tenant settings    │
     │ 5. createPaymentMethods()  → 5 default methods      │
     │ 6. createCategories()      → 10 default categories  │
     │ 7. createBrands()          → 6 default brands       │
     │ 8. createUnits()           → 14 default units       │
     │ 9. dispatchTenantCreated() → event                  │
     └─────────────────────────────────────────────────────┘
```

---

## 7. Dependency Analysis

### Models Required (Direct Usage)

| Model | Used For | Already Imported In Controllers? |
|-------|----------|----------------------------------|
| `App\Models\Tenant` | Receiving tenant, cache clearing | ✅ Yes |
| `App\Models\User` | Creating owner user | ✅ Yes |
| `App\Models\Role` | Creating tenant-scoped roles | ✅ Yes |
| `App\Models\Subscription` | Creating subscriptions | ✅ Yes |
| `App\Models\Plan` | Resolving Free plan | ✅ Yes |
| `App\Models\WebsiteInfo` | Creating default settings | ✅ Yes |
| `App\Models\PaymentMethod` | Creating default payment methods | No (seeder only) |
| `App\Models\Category` | Creating default categories | No (seeder only) |
| `App\Models\Brand` | Creating default brands | No (seeder only) |
| `App\Models\Unit` | Creating default units | No (seeder only) |
| `Spatie\Permission\Models\Permission` | Syncing all permissions | ✅ Yes |

### Services Required (Direct Usage)

| Service | Used For | Already Injected In Controllers? |
|---------|----------|----------------------------------|
| `FeatureGate` | Clearing plan cache | ✅ Yes (PlanSeeder) |

### Events to Create

| Event | Payload | Purpose |
|-------|---------|---------|
| `App\Events\TenantCreated` | Tenant $tenant, User $owner | Allows listeners to hook into tenant creation (e.g., send welcome email, provision external resources) |

---

## 8. Owner Strategy

### Recommendation: Dual Approach (Safe Migration Path)

For V3, the service should support **both** architectures to allow a phased migration:

```php
protected function createOwner(Tenant $tenant, array $options): User
{
    $owner = User::create([
        'name' => $options['owner_name'],
        'email' => $options['owner_email'],
        'password' => Hash::make($options['owner_password']),
        'status' => User::STATUS_ACTIVE,
        'tenant_id' => $tenant->id,
        'is_owner' => true,
    ]);

    if (config('tenant-bootstrap.use_owner_role', false)) {
        // FUTURE: dedicated owner role
        $ownerRole = Role::where('name', 'owner')
            ->where('tenant_id', $tenant->id)
            ->first();
        $owner->assignRole($ownerRole);
    } else {
        // CURRENT: admin role (backward compatible)
        $adminRole = Role::where('name', 'admin')
            ->where('tenant_id', $tenant->id)
            ->first();
        $owner->assignRole($adminRole);
    }

    // Always sync all permissions (guarantee)
    $owner->syncPermissions(Permission::all());

    return $owner;
}
```

**Recommended default:** Start with current architecture (`use_owner_role = false`). Enable owner role after RoleController protection is in place and migrations are ready.

### Migration Phases

| Phase | Config Value | Owner Role Exists? | Owner Gets | Status |
|-------|-------------|-------------------|------------|--------|
| Phase 1-5 | `false` | No (seeded but unused) | `admin` role + all perms | Current behavior |
| Phase 6 | `true` | Yes (created in bootstrap) | `owner` role + all perms | New behavior |

---

## 9. Subscription Strategy

### Bootstrap Subscription Flow

```php
protected function createSubscription(Tenant $tenant, ?int $planId = null, string $status = 'pending'): Subscription
{
    $plan = $planId ? Plan::find($planId) : Plan::free();

    if (!$plan) {
        throw new \RuntimeException(
            'No default plan found. Ensure PlanSeeder has been executed.'
        );
    }

    $subscription = $tenant->subscription()->create([
        'plan_id' => $plan->id,
        'billing_interval' => $plan->defaultInterval(),
        'status' => $status,
        'starts_at' => $status === 'active' ? now() : null,
        'expires_at' => $status === 'active'
            ? $plan->calculateExpiryDate(now(), $plan->defaultInterval())
            : null,
    ]);

    FeatureGate::clearCache($plan);

    return $subscription;
}
```

### Plan Failure Handling

| Scenario | Handling |
|----------|----------|
| `Plan::free()` returns null | Throw `\RuntimeException` with clear message about PlanSeeder |
| Plan not found (by id) | Throw `\RuntimeException` with invalid plan_id message |
| Plan has no defaultInterval | Default to 'monthly' |
| Subscription creation fails | Wrapped in DB transaction — full rollback |

### Status Strategy

| Creation Path | Status | Rationale |
|--------------|--------|-----------|
| Public registration (`CreateStoreController`) | `pending` | Activated after email verification |
| SuperAdmin creation (`TenantController`) | `active` | SuperAdmin pre-approves |
| Command/CLI creation | `active` | Immediate activation |

---

## 10. Default Data Strategy

### Categories (10 Defaults)

| Name | Description |
|------|-------------|
| Electronics | Latest gadgets, smartphones, laptops |
| Fashion | Clothing, shoes, accessories |
| Home & Kitchen | Home appliances, kitchenware, furniture |
| Beauty & Personal Care | Skincare, makeup, haircare |
| Sports & Outdoors | Sports equipment, outdoor gear |
| Books & Media | Books, e-books, music, movies |
| Toys & Games | Children toys, board games, puzzles |
| Grocery & Food | Food items, beverages, snacks |
| Health & Wellness | Vitamins, supplements, medical supplies |
| Automotive | Car accessories, parts, tools |

### Brands (6 Defaults)

| Name | Slug | Description |
|------|------|-------------|
| Samsung | samsung | South Korean electronics |
| Apple | apple | American technology |
| Xiaomi | xiaomi | Chinese electronics |
| Nike | nike | Athletic footwear and apparel |
| Adidas | adidas | Sportswear and footwear |
| Sony | sony | Japanese conglomerate |

### Units (14 Defaults)

| Name | Short | Description |
|------|-------|-------------|
| Piece | pcs | Individual pieces or items |
| Kilogram | kg | Weight in kilograms |
| Gram | g | Weight in grams |
| Liter | L | Volume in liters |
| Milliliter | mL | Volume in milliliters |
| Meter | m | Length in meters |
| Centimeter | cm | Length in centimeters |
| Box | box | Box or carton |
| Pack | pk | Pack of items |
| Dozen | doz | 12 pieces |
| Pair | pr | Pairs (shoes, socks) |
| Set | set | Set of items |
| Bottle | btl | Bottled items |
| Bag | bag | Bagged items |

### Payment Methods (5 Defaults)

| Name | Type | Active |
|------|------|--------|
| KBZ Pay | mobile_wallet | Yes |
| WavePay | mobile_wallet | Yes |
| AYA Pay | mobile_wallet | Yes |
| Bank Transfer | bank_transfer | Yes |
| Cash on Delivery | cod | No |

### WebsiteInfo Defaults

```php
[
    'site_name' => $tenant->name,
    'theme_color' => '#3B82F6',
    'default_language' => 'en',
    'timezone' => 'Asia/Yangon',
    'currency_code' => 'MMK',
    'currency_symbol' => 'K',
    'date_format' => 'Y-m-d',
    'allow_registration' => true,
    'maintenance_mode' => false,
    'is_active' => true,
]
```

### What Should Remain Empty

| Item | Reason |
|------|--------|
| Products | Merchant creates own inventory |
| Orders | No orders until store is live |
| Coupons | Merchant creates own promotions |
| Promotions | Merchant creates own campaigns |
| Staff users | Owner creates staff as needed |

---

## 11. Event Strategy

### Recommended Events

| Event | Dispatch Timing | Payload | Purpose |
|-------|----------------|---------|---------|
| `TenantCreated` | After DB commit | Tenant $tenant, User $owner | Primary hook for post-creation logic |
| (none for individual steps) | — | — | Steps are internal; no per-step events needed |

### TenantCreated Event

```php
namespace App\Events;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class TenantCreated
{
    use Dispatchable;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly User $owner,
    ) {}
}
```

### Potential Listeners (Future)

| Listener | Responsibility | Async? |
|----------|----------------|--------|
| SendWelcomeOwner | Welcome notification after creation | ✅ Yes (queue) |
| ProvisionExternalResources | S3 bucket, CDN, etc. | ✅ Yes (job) |
| ComputeInitialMetrics | Seed dashboard cache | ✅ Yes (job) |
| LogTenantCreation | Audit trail entry | No |

### Current Listener Compatibility

The existing `ActivateTenantOnVerified` listener continues to work unchanged. It listens for the `Verified` event (Laravel built-in), not `TenantCreated`. The separation is:

```
TenantBootstrapService::bootstrap()
  → Creates tenant data synchronously
  → Dispatches TenantCreated (for extensibility)

[Later] Email verified
  → ActivateTenantOnVerified::handle()
    → Sets tenant.status = 'active'
    → Sets subscription.status = 'active'
```

---

## 12. Error Recovery Strategy

### Database Transaction Strategy

The entire bootstrap runs inside a single `DB::transaction()`:

```php
public function bootstrap(Tenant $tenant, array $options = []): User
{
    return DB::transaction(function () use ($tenant, $options) {
        // All bootstrap steps
        // If ANY step fails, ALL changes are rolled back
    });
}
```

### Failure Scenarios

| Scenario | Behavior | User Impact |
|----------|----------|-------------|
| Plan not found | `\RuntimeException` thrown | Transaction rolls back. Tenant NOT created. User sees error. |
| Role creation fails | `\Exception` propagated | Full rollback. Tenant not created. |
| User creation fails | `\Exception` propagated | Full rollback. |
| Permission sync fails | `\Exception` propagated | Full rollback. |
| Default data creation fails | `\Exception` propagated | Full rollback. |

### Idempotency

The service assumes the tenant does NOT yet exist in the database (fresh creation). `Role::firstOrCreate` and `updateOrCreate` could be used for specific steps, but the primary contract is: **call once per tenant**.

### Partial Bootstrap Prevention

To prevent partial bootstrap (e.g., roles created but no subscription):

```php
$steps = [
    'roles' => fn() => $this->createRoles($tenant),
    'subscription' => fn() => $this->createSubscription($tenant, ...),
    'owner' => fn() => $this->createOwner($tenant, ...),
    'website_info' => fn() => $this->createWebsiteInfo($tenant),
    'payment_methods' => fn() => $this->createPaymentMethods($tenant),
    'categories' => fn() => $this->createCategories($tenant),
    'brands' => fn() => $this->createBrands($tenant),
    'units' => fn() => $this->createUnits($tenant),
];

foreach ($steps as $name => $step) {
    try {
        $step();
    } catch (\Throwable $e) {
        Log::error("TenantBootstrap failed at step '{$name}' for tenant {$tenant->id}", [
            'exception' => $e,
            'tenant_id' => $tenant->id,
        ]);
        throw $e; // Triggers DB rollback
    }
}
```

---

## 13. Risk Analysis

### Files Affected

| File | Change Type | Risk | Mitigation |
|------|-------------|------|------------|
| `app/Services/TenantBootstrapService.php` | **NEW** | None (new file) | — |
| `app/Events/TenantCreated.php` | **NEW** | None (new file) | — |
| `app/Http/Controllers/CreateStoreController.php` | **MODIFY** | **MEDIUM** | Replace inline block with service call. Keep inline code commented during transition |
| `app/Http/Controllers/SuperAdmin/TenantController.php` | **MODIFY** | **MEDIUM** | Same as above |
| `app/Http/Controllers/Auth/RegisteredUserController.php` | **MODIFY** | **LOW** | Replace inline customer role creation with `ensureCustomerRole()` |
| `database/seeders/DatabaseSeeder.php` | **MODIFY** | **LOW** | Remove 5 bootstrap seeders from call list |
| `database/seeders/WebsiteSettingsSeeder.php` | **KEEP** | None | File stays for reference, removed from DatabaseSeeder |
| `database/seeders/PaymentMethodSeeder.php` | **KEEP** | None | Same |
| `database/seeders/CategorySeeder.php` | **KEEP** | None | Same |
| `database/seeders/UnitSeeder.php` | **KEEP** | None | Same |
| `database/seeders/BrandSeeder.php` | **KEEP** | None | Same |
| `app/Models/WebsiteInfo.php` | **MODIFY** | **LOW** | Fix `getSettings()` to scope by tenant_id. Existing behavior preserved for records that exist |

### Controllers Affected

| Controller | Current Lines | Replacement | Risk |
|------------|--------------|-------------|------|
| CreateStoreController | 42-113 (71 lines) | ~10 lines | Medium — ensure no behavioral differences |
| TenantController | 67-137 (70 lines) | ~10 lines | Medium — same |
| RegisteredUserController | 67-82 (15 lines) | ~3 lines | Low — simple replacement |

### Backward Compatibility Risks

| Risk | Level | Mitigation |
|------|-------|------------|
| Service behaves differently from inline code | **MEDIUM** | Keep inline code as fallback during transition. Compare outputs in staging. |
| Existing tenants missing default data | **NONE** | Service only affects NEW tenants. Existing tenants keep their current state. |
| WebsiteInfo data isolation fix breaks existing tenants | **LOW** | Fix `getSettings()` to fall back to global record if no tenant-specific record exists. |
| Owner role migration affects existing logins | **LOW** | Owner retains `admin` role during Phase 1-5. `owner` role added in Phase 6 after full testing. |
| Subscription creation differs | **LOW** | Service mimics exact current behavior. Differences are bugs to fix. |

### Testing Requirements

| Test | Type | Coverage |
|------|------|----------|
| Service creates all expected roles | Unit | owner, admin, customer created with correct permissions |
| Service creates subscription | Unit | Free plan assigned, correct status |
| Service creates owner user | Unit | is_owner=true, correct role, all permissions |
| Service creates default data | Unit | 10 categories, 6 brands, 14 units, 5 payment methods, 1 WebsiteInfo |
| Transaction rollback on failure | Unit | If step 5 fails, steps 1-4 are rolled back |
| Idempotent ensureCustomerRole | Unit | Role created only once, permissions synced once |
| Controller integration | Feature | CreateStoreController produces same result with service as inline code |

---

## 14. Implementation Roadmap

### Phase 1: Service Skeleton (Day 1)

**Create files:**
- `app/Services/TenantBootstrapService.php` — empty class with method stubs
- `app/Events/TenantCreated.php` — event class

**No behavioral changes.** Controllers continue using inline code.

**Verification:** `php artisan db:seed` completes successfully.

### Phase 2: Role Bootstrap (Day 1-2)

**Implement methods:**
- `createRoles()` — creates owner, admin, customer roles
- `createRole()` — single role creation with permission sync
- `ensureCustomerRole()` — for customer registration

**Update controllers:**
- `CreateStoreController` — replace role creation block with `$bootstrapService->bootstrap()`
- `TenantController` — same replacement
- `RegisteredUserController` — replace inline with `$bootstrapService->ensureCustomerRole()`

**Verification:** Create a store via public registration and superadmin panel. Verify roles and permissions match before/after.

### Phase 3: Subscription Bootstrap (Day 2)

**Implement methods:**
- `createSubscription()` — Free plan assignment with status handling

**Integration:** Already called from `bootstrap()`. Removes subscription creation from controllers.

**Verification:** Verify subscription records match before/after. Check pending vs active status.

### Phase 4: WebsiteInfo Bootstrap (Day 2-3)

**Repurpose `WebsiteSettingsSeeder` data into service method:**
- `createWebsiteInfo()` — creates per-tenant WebsiteInfo with defaults

**Fix `WebsiteInfo::getSettings()`:**
- Add tenant_id scoping to `self::first()` query
- Fall back to global record (id=1) if no tenant-specific record exists

**Remove from seeder:**
- Remove `WebsiteSettingsSeeder::class` from `DatabaseSeeder`

**Verification:** New tenants get their own WebsiteInfo. Existing tenants continue seeing global record.

### Phase 5: Default Data Bootstrap (Day 3-4)

**Implement methods:**
- `createPaymentMethods()` — 5 default payment methods
- `createCategories()` — 10 default categories
- `createBrands()` — 6 default brands
- `createUnits()` — 14 default units

**Remove from seeder:**
- Remove `PaymentMethodSeeder`, `CategorySeeder`, `UnitSeeder`, `BrandSeeder` from `DatabaseSeeder`

**Verification:** New tenants have all defaults. `php artisan migrate:fresh --seed` creates 0 products/orders.

### Phase 6: Controller Refactor (Day 4-5)

**Clean up controllers:**
- Remove commented inline code from `CreateStoreController`
- Remove commented inline code from `TenantController`
- Final parameter tuning for `bootstrap()` options

**Documentation:**
- Update docblocks
- Add inline comments for future developers

**Verification:** Full integration test suite passes. Store creation, tenant management, and customer registration all work.

---

## 15. Final Architecture

### Directory Structure After Implementation

```
app/
├── Services/
│   ├── TenantBootstrapService.php    ← NEW: Central bootstrap logic
│   ├── FeatureGate.php               ← Unchanged
│   ├── SubscriptionLimitService.php  ← Unchanged
│   ├── SubscriptionExpiryService.php ← Unchanged
│   └── ...
│
├── Events/
│   ├── TenantCreated.php             ← NEW: Tenant bootstrap event
│   └── ... (existing events)
│
├── Listeners/
│   ├── ActivateTenantOnVerified.php  ← Unchanged
│   └── ... (existing listeners)
│
├── Http/Controllers/
│   ├── CreateStoreController.php     ← MODIFIED: calls bootstrap()
│   ├── SuperAdmin/TenantController.php ← MODIFIED: calls bootstrap()
│   └── Auth/RegisteredUserController.php ← MODIFIED: calls ensureCustomerRole()

database/seeders/
├── DatabaseSeeder.php                ← MODIFIED: system seeders only
├── DemoDataSeeder.php                ← Existing: demo data
├── PermissionSeeder.php              ← Unchanged
├── RoleAndPermissionSeeder.php       ← Unchanged
├── PlanSeeder.php                    ← Unchanged
├── LocationSeeder.php                ← Unchanged
├── TenantSeeder.php                  ← Unchanged
├── UserSeeder.php                    ← Unchanged (kept for DemoDataSeeder)
├── ProductSeeder.php                 ← Unchanged (kept for DemoDataSeeder)
├── OrderSeeder.php                   ← Unchanged (kept for DemoDataSeeder)
├── WebsiteSettingsSeeder.php         ← KEPT: reference only
├── PaymentMethodSeeder.php           ← KEPT: reference only
├── CategorySeeder.php                ← KEPT: reference only
├── UnitSeeder.php                    ← KEPT: reference only
└── BrandSeeder.php                   ← KEPT: reference only
```

### Data Flow After Implementation

```
FRESH INSTALL
  php artisan migrate:fresh --seed
    → PermissionSeeder (system)
    → RoleAndPermissionSeeder (system)
    → PlanSeeder (system)
    → LocationSeeder (system)
    → TenantSeeder (backfill only)
    Result: 0 tenants, 0 stores, 0 products, 0 orders

PUBLIC STORE REGISTRATION
  POST /create-store
    → CreateStoreController::store()
      → Tenant::create()
      → TenantBootstrapService::bootstrap($tenant, [
          'owner_name' => ...,
          'owner_email' => ...,
          'owner_password' => ...,
          'status' => 'pending',
        ])
        → createRoles()        → owner, admin, customer
        → createSubscription() → Free plan
        → createOwner()        → is_owner=true, owner role, all perms
        → createWebsiteInfo()  → per-tenant settings
        → createPaymentMethods() → 5 defaults
        → createCategories()   → 10 defaults
        → createBrands()       → 6 defaults
        → createUnits()        → 14 defaults
        → dispatch TenantCreated
      → event(new Registered($owner))
    → Redirect to success

SUPERADMIN TENANT CREATION
  POST /superadmin/tenants
    → TenantController::store()
      → Tenant::create()
      → TenantBootstrapService::bootstrap($tenant, [
          'plan_id' => $selectedPlan,
          'owner_name' => ...,
          'owner_email' => ...,
          'owner_password' => ...,
          'status' => 'active',
          'email_verified' => true,
        ])
        → Same flow as above, but with active status
    → Redirect to tenant list

CUSTOMER REGISTRATION
  POST /store/{slug}/register
    → RegisteredUserController::store()
      → TenantBootstrapService::ensureCustomerRole($tenant)
      → User::create()
      → assignRole('customer')
      → event(new Registered($user))
```

---

## Summary

| Metric | Value |
|--------|-------|
| File Created | `docs/v3-tenant-bootstrap-design-plan.md` |
| Bootstrap Candidates | 11 items (roles, subscription, owner, WebsiteInfo, payment methods, categories, brands, units, settings) |
| Service Methods | 14 (2 public: bootstrap, ensureCustomerRole; 12 protected) |
| Execution Flow | 9 steps inside DB transaction → event dispatch |
| Files Affected | 11 (1 new service, 1 new event, 3 controllers modified, 1 model fixed, 5 seeders removed from DatabaseSeeder) |
| Controllers Affected | 3 (CreateStoreController, TenantController, RegisteredUserController) |
| Risk Level | Low-Medium (no schema changes, backward compatible, phased rollout) |
| Implementation Complexity | Low-Medium (6 phases, ~5 days with testing) |
| Recommended Next Step | Begin Phase 1: Create `app/Services/TenantBootstrapService.php` with method stubs and `app/Events/TenantCreated.php` |
