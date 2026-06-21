# V3-A3 Tenant Bootstrap Service Audit

## Status: Completed (Read-Only Audit)

---

## 1. Executive Summary

The project has **no dedicated TenantBootstrapService**. Tenant initialization logic is duplicated across **3 controllers** (`CreateStoreController`, `TenantController`, `RegisteredUserController`) with identical patterns for role creation, permission syncing, and user creation. Additionally, **5 seeders** create data that should be per-tenant bootstrap responsibilities (WebsiteSettings, PaymentMethods, Categories, Units, Brands). The `TenantAware` trait auto-assigns `tenant_id` on creation, but only if a current tenant context exists — during seeding (console context), the `TenantScope` is bypassed entirely.

**Critical gap:** New tenants created after seeding get **zero default data** beyond roles and a subscription. No default WebsiteInfo, payment methods, categories, brands, or units are created. The `WebsiteInfo::getSettings()` method lazy-creates settings on first access, but this creates a coupling between business logic and data initialization.

---

## 2. Current Store Creation Flow

### Public Store Registration

```
POST /create-store
  → CreateStoreController::store()
  │
  ├── 1. Validate request (name, slug, owner_name, owner_email, password)
  │
  ├── 2. DB::transaction
  │     ├── Tenant::create(['status' => 'pending'])
  │     ├── Tenant::clearDefaultCache()
  │     ├── Plan::free()
  │     ├── Subscription::create(['plan_id' => $plan->id, 'status' => 'pending'])
  │     ├── foreach ['admin', 'customer']:
  │     │     ├── Role::create(name, tenant_id)
  │     │     └── syncPermissions from global role template
  │     ├── User::create(['status' => 'active'])
  │     ├── Set tenant_id, is_owner = true
  │     ├── assignRole('admin')
  │     ├── syncPermissions(Permission::all())
  │     └── return $admin
  │
  ├── 3. event(new Registered($admin))   // triggers verification email
  │
  └── 4. Redirect to success page

Email verified
  → ActivateTenantOnVerified::handle()
    ├── tenant.status = 'active'
    ├── tenant.activated_at = now()
    ├── subscription.status = 'active'
    ├── subscription.starts_at = now()
    └── Send WelcomeOwner notification
```

### SuperAdmin Tenant Creation

```
POST /superadmin/tenants
  → TenantController::store()
  │
  ├── 1. Validate request (name, slug, plan_id, admin_name/email/password)
  │
  ├── 2. DB::transaction
  │     ├── Tenant::create(['status' => 'active' or specified])
  │     ├── Tenant::clearDefaultCache()
  │     ├── Plan::find($plan_id) ?? Plan::free()
  │     ├── Subscription::create(['status' => 'active', starts_at => now()])
  │     ├── foreach ['admin', 'customer']:     (IDENTICAL to above)
  │     │     ├── Role::create(name, tenant_id)
  │     │     └── syncPermissions from global role template
  │     ├── if create_admin:
  │     │     ├── User::create(['email_verified_at' => now()])
  │     │     ├── Set tenant_id, is_owner = true
  │     │     ├── assignRole('admin')
  │     │     └── syncPermissions(Permission::all())
  │     └── return $tenant
  │
  └── 3. Redirect to tenant index
```

### Customer Registration (within existing store)

```
POST /store/{slug}/register
  → RegisteredUserController::store()
    ├── Validate name, email, password
    ├── User::create(['tenant_id' => $tenant->id])
    ├── Role::firstOrCreate('customer', tenant_id)
    ├── syncPermissions from global customer role (if newly created)
    ├── assignRole('customer')
    ├── event(new Registered($user))
    └── Auth::login($user)
```

---

## 3. Tenant Creation Analysis

### Where Tenant Records Are Created

| Location | File | Line | Status | Trigger |
|----------|------|------|--------|---------|
| CreateStoreController | `app/Http/Controllers/CreateStoreController.php` | 45 | `pending` | Public store registration |
| TenantController | `app/Http/Controllers/SuperAdmin/TenantController.php` | 69 | `active` (default) | SuperAdmin creates merchant |
| TenantSeeder | `database/seeders/TenantSeeder.php` | 55 | `active` | `db:seed` (system seed) |

### How tenant_id Is Assigned

- **Models using `TenantAware` trait:** Auto-assigned via `creating` hook (`TenantAware.php:14-21`) which reads `Tenant::getCurrent()`
- **Console context:** `TenantScope.php:26-28` bypasses tenant scope during `db:seed` and `migrate` commands
- **Manual override:** Controllers explicitly set `$user->tenant_id = $tenant->id` after creation (because `Tenant::getCurrent()` returns null during the DB transaction before the tenant is fully committed)

### Validations

| Controller | Validated Fields | Unique Checks |
|-----------|-----------------|---------------|
| CreateStoreController | name, slug, domain, owner_name, owner_email, password | slug: `unique:tenants`, domain: `unique:tenants`, email: `unique:users` |
| TenantController | name, slug, domain, email, status, plan_id, create_admin, admin_name, admin_email, admin_password | slug + domain + admin_email same as above |
| RegisteredUserController | name, email, password | email: `unique:users` |

---

## 4. Role Bootstrap Analysis

### Where Admin Role Is Created

| Location | File | Lines | Pattern |
|----------|------|-------|---------|
| CreateStoreController | `CreateStoreController.php` | 70-90 | `Role::create` then sync from global template |
| TenantController | `TenantController.php` | 94-114 | IDENTICAL code block |
| RegisteredUserController | `RegisteredUserController.php` | 67-82 | `Role::firstOrCreate` for customer role only |

### Where Customer Role Is Created

| Location | File | Lines | Pattern |
|----------|------|-------|---------|
| CreateStoreController | `CreateStoreController.php` | 70-90 | Same loop as admin |
| TenantController | `TenantController.php` | 94-114 | Same loop as admin |
| RegisteredUserController | `RegisteredUserController.php` | 67-82 | `firstOrCreate` with conditional sync |

### How Permissions Are Assigned

1. **Global role templates** are created by `RoleAndPermissionSeeder`:
   - `superadmin`: `Permission::all()` (96 permissions)
   - `admin`: 44 specific permissions
   - `customer`: 4 permissions

2. **Per-tenant roles** copy from global templates:
   ```php
   $globalRole = Role::where('name', $roleName)->whereNull('tenant_id')->first();
   if ($globalRole) {
       $role->syncPermissions($globalRole->permissions);
   }
   ```

3. **Owner user** gets `admin` role + `syncPermissions(Permission::all())` (all 96 permissions, bypassing the 44-permission admin role limit)

### Duplicated Code

The role creation + permission sync block (lines 70-90 in CreateStoreController, lines 94-114 in TenantController) is **identical**. This is the primary candidate for extraction into `TenantBootstrapService`.

---

## 5. User Bootstrap Analysis

### Merchant Admin Creation

| Aspect | CreateStoreController | TenantController |
|--------|---------------------|------------------|
| File line | 92-113 | 116-137 |
| Status | `User::STATUS_ACTIVE` | `User::STATUS_ACTIVE` |
| Email verified | No (needs verification) | `email_verified_at = now()` |
| is_owner | `true` | `true` |
| Role | `admin` (per-tenant) | `admin` (per-tenant) |
| Permissions | `Permission::all()` | `Permission::all()` |
| Event | `Registered` | None |

### Customer Creation

| Aspect | RegisteredUserController |
|--------|------------------------|
| File line | 56-87 |
| Role | `customer` (per-tenant, firstOrCreate) |
| Permissions | From global customer role (if newly created) |
| Event | `Registered` |
| Auth | Logged in immediately |

### Key Observation

The owner user is always assigned `admin` role + all permissions. There is **no `owner` role**. The `is_owner` boolean flag is the only distinguisher. The `protectOwner()` method in `AdminUserController` guards against modification but there is no dedicated role-based protection.

---

## 6. Subscription Bootstrap Analysis

### How Plans Are Assigned

| Controller | Plan Selection | Status |
|-----------|---------------|--------|
| CreateStoreController | `Plan::free()` | `pending` (activated on email verification) |
| TenantController | `Plan::find($plan_id) ?? Plan::free()` | Same as tenant status (typically `active`) |

### How Free Plan Is Selected

```php
$plan = Plan::free();
```

`Plan::free()` is a scope method that returns the plan where `is_default = true`. This relies on `PlanSeeder` having been executed to create the Free plan record.

### Subscription Creation Pattern

| Controller | Lines | Key Fields |
|-----------|-------|------------|
| CreateStoreController | 60-68 | plan_id, billing_interval, status: 'pending', starts_at: null, expires_at: null |
| TenantController | 81-92 | plan_id, billing_interval, status: mirrors tenant, starts_at: now(), expires_at: calculated |

### Subscription Activation

Only in `ActivateTenantOnVerified` listener (line 24-27):
```php
if ($subscription && $subscription->status === 'pending') {
    $subscription->status = 'active';
    $subscription->starts_at = now();
    $subscription->save();
}
```

### Files Involved

| File | Role |
|------|------|
| `app/Models/Plan.php` | Plan model with `free()` scope, `defaultInterval()`, `calculateExpiryDate()` |
| `app/Models/Subscription.php` | Subscription model (TenantAware) |
| `app/Http/Controllers/CreateStoreController.php` | Creates subscription during public registration |
| `app/Http/Controllers/SuperAdmin/TenantController.php` | Creates subscription during superadmin tenant creation |
| `app/Listeners/ActivateTenantOnVerified.php` | Activates subscription on email verification |
| `app/Services/SubscriptionExpiryService.php` | Lifecycle management (active→past_due→expired→suspended) |
| `app/Services/SubscriptionLimitService.php` | Usage limits enforcement |
| `database/seeders/PlanSeeder.php` | Creates plan definitions |

---

## 7. WebsiteInfo Analysis

### Current State

- **Single global record** created by `WebsiteSettingsSeeder` with id=1
- Model uses `TenantAware` trait (has `tenant_id` column)
- `getSettings()` method lazy-creates a record with defaults if none exists
- **CRITICAL ISSUE:** `getSettings()` uses `self::first()` without tenant scope, always returns the first record regardless of tenant

### Bootstrap Behavior

`WebsiteInfo::getSettings()` (line 92-116):
1. Check cache for `website_settings_{tenant_id}`
2. If cached, return
3. If not cached, call `self::first()` — returns first record (usually id=1 for all tenants)
4. If no record exists, create one with minimal defaults

**Data isolation failure:** All tenants share the same WebsiteInfo record. Tenant admin edits through `SettingsController` affect all tenants.

### Tenant Linkage

- `tenant_id` column exists on `website_infos` table
- `SettingsController::update()` (line 38-42) checks `firstWhere('tenant_id', tenant()->id)`
- `getSettings()` does NOT filter by tenant_id (uses `self::first()`)
- **Result:** Settings are theoretically per-tenant but practically global

---

## 8. Payment Method Analysis

### Current State

- `PaymentMethodSeeder` creates 5 global records with no `tenant_id`
- `PaymentMethod` model uses `TenantAware` trait
- **No bootstrap during tenant creation** — new tenants get no default payment methods
- The `TenantSeeder` backfills null tenant_ids, assigning all payment methods to the default tenant

### Should Move to TenantBootstrapService? **YES**

**Reasons:**
1. Payment methods are tenant-owned (per-tenant configuration)
2. No bootstrap exists for new tenants (they get zero payment methods)
3. Current global seeding creates cross-tenant data confusion
4. Each merchant should configure their own payment accounts

---

## 9. Category / Brand / Unit Analysis

### Current State

| Seeder | Creates | Scope | Future Tenants? |
|--------|---------|-------|-----------------|
| `CategorySeeder` | 10 categories | Global (no tenant_id) | ❌ No defaults |
| `BrandSeeder` | 6 brands per existing tenant | Iterates `Tenant::all()` | ❌ No defaults |
| `UnitSeeder` | 14 units per existing tenant | Iterates `Tenant::all()` | ❌ No defaults |

### Risks

| Risk | Severity | Description |
|------|----------|-------------|
| Future tenants get no defaults | **HIGH** | Tenants created after `db:seed` have zero categories, brands, or units |
| Global categories cause data leak | **MEDIUM** | `CategorySeeder` creates records with null tenant_id. If `allowsNullTenantFallback` is true, all tenants see all categories. If false, no tenant sees any categories |
| BrandSeeder uses `firstOrCreate` on `name` but unique key is on `(tenant_id, slug)` | **MEDIUM** | Can cause duplicate key violations on re-seed if slugs overlap |

### Recommendation

All three should be moved into `TenantBootstrapService` to ensure every new tenant receives defaults at creation time.

---

## 10. Settings Analysis

### Settings Classification

| Setting | Storage | Current Owner | Bootstrap | Should Be |
|---------|---------|---------------|-----------|-----------|
| Website Info (site name, branding, contact, SEO) | `website_infos` table | Shared (global record) | Lazy via `getSettings()` | Tenant-owned |
| Notification settings | `settings` table | Per-tenant | Default 'true' | Tenant-owned |
| Telegram integration | `telegram_integrations` table | Per-tenant | None | Tenant-owned |
| Theme/Color | `website_infos.theme_color` | Shared | Hardcoded default | Tenant-owned |
| Currency/Timezone | `website_infos` | Shared | Hardcoded default | Platform default, tenant override |
| Payment methods | `payment_methods` table | Shared (global seed) | None | Tenant-owned |
| Shipping settings | `website_infos` (shipping fields) | Shared | Hardcoded default | Tenant-owned |
| Plans | `plans` table | Platform-owned | PlanSeeder | Platform-owned (KEEP) |
| Permissions | `permissions` table | Platform-owned | PermissionSeeder | Platform-owned (KEEP) |

### Platform-Owned Settings

| Setting | Why Platform | Bootstrap |
|---------|-------------|-----------|
| Plans | Same for all tenants, managed by SuperAdmin | PlanSeeder |
| Permissions | Global permission registry | PermissionSeeder |
| Locations (cities/townships) | Shared reference data | LocationSeeder |

### Tenant-Owned Settings

| Setting | Bootstrap Status | Future Action |
|---------|-----------------|---------------|
| WebsiteInfo | ❌ Not bootstrapped (lazy-created, shared) | Create per-tenant defaults |
| Payment methods | ❌ Not bootstrapped | Create per-tenant defaults |
| Categories | ❌ Not bootstrapped (global seed only) | Create per-tenant defaults |
| Brands | ❌ Not bootstrapped (existing tenants only) | Create per-tenant defaults |
| Units | ❌ Not bootstrapped (existing tenants only) | Create per-tenant defaults |
| Notification prefs | ✅ Defaults in User model | Already works |
| Telegram integration | ❌ Not bootstrapped | Manual setup (OK for now) |

---

## 11. Events & Side Effects

### Event Flow During Store Creation

```
CreateStoreController::store()
  │
  ├── DB::transaction (synchronous)
  │     ├── Tenant created
  │     ├── Subscription created
  │     ├── Roles created
  │     ├── User created
  │     └── Permissions assigned
  │
  ├── event(new Registered($admin))      ← Laravel built-in event
  │     └── Sends email verification link
  │
  └── Response sent

[Later] User clicks verification link
  ├── event(new Verified($user))         ← Laravel built-in event
  │     └── ActivateTenantOnVerified::handle()
  │           ├── tenant.status = 'active'
  │           ├── subscription.status = 'active'
  │           └── WelcomeOwner notification
  │
  └── Redirect to login
```

### All Registered Listeners (EventServiceProvider)

```php
protected $listen = [];  // EMPTY
```

- **No explicit mappings.** The `ActivateTenantOnVerified` listener relies on auto-discovery.
- **No observers.** The `app/Observers/` directory does not exist.
- **No bootstrap jobs.** No queued jobs handle post-creation tasks.
- **No custom events.** No `TenantCreated`, `TenantBootstrapped`, or similar events exist.

### Side Effects of Current Flow

| Side Effect | Trigger | Location | Async? |
|-------------|---------|----------|--------|
| Email verification sent | `Registered` event | Laravel Auth | Yes (mail queue) |
| Tenant activated | `Verified` event | ActivateTenantOnVerified | No |
| Subscription activated | `Verified` event | ActivateTenantOnVerified | No |
| Welcome notification | `Verified` event | ActivateTenantOnVerified | Yes (notification queue) |
| Broadcast events | Various order/chat actions | Various event classes | Yes (broadcast queue) |
| Dashboard metrics refresh | Dashboard page load | Cache miss → job dispatch | Yes |

---

## 12. Bootstrap Candidate Matrix

### MOVE TO TENANTBOOTSTRAPSERVICE

| # | Item | Current Location | Duplicated? | Priority | Complexity |
|---|------|-----------------|-------------|----------|------------|
| 1 | **Create admin role (per-tenant)** | 3 controllers (identical code) | ✅ Yes (triple) | **HIGH** | Low |
| 2 | **Create customer role (per-tenant)** | 3 controllers (identical code) | ✅ Yes (triple) | **HIGH** | Low |
| 3 | **Copy permissions from global templates** | 3 controllers (identical pattern) | ✅ Yes (triple) | **HIGH** | Low |
| 4 | **Create owner user** | 2 controllers (similar) | ✅ Yes (double) | **HIGH** | Low |
| 5 | **Assign role + permissions to owner** | 2 controllers (similar) | ✅ Yes (double) | **HIGH** | Low |
| 6 | **Create subscription (Free plan)** | 2 controllers (similar) | ✅ Yes (double) | **HIGH** | Low |
| 7 | **Create default WebsiteInfo** | Lazy in `getSettings()` | No (but broken) | **HIGH** | Medium |
| 8 | **Create default payment methods** | Not bootstrapped | N/A | **MEDIUM** | Low |
| 9 | **Create default categories** | Not bootstrapped | N/A | **MEDIUM** | Low |
| 10 | **Create default brands** | Not bootstrapped | N/A | **MEDIUM** | Low |
| 11 | **Create default units** | Not bootstrapped | N/A | **MEDIUM** | Low |
| 12 | **Set default notification preferences** | In User model accessor | No (works) | **LOW** | Low |

### KEEP OUTSIDE TENANTBOOTSTRAPSERVICE

| # | Item | Reason |
|---|------|--------|
| 1 | **PermissionSeeder** | System-level, runs once per installation |
| 2 | **RoleAndPermissionSeeder** | Creates global role templates (superadmin, admin, customer) |
| 3 | **PlanSeeder** | Creates plan definitions (Free, Starter, Business) |
| 4 | **LocationSeeder** | Creates shared city/township reference data |
| 5 | **TenantSeeder** | Data integrity backfill utility |
| 6 | **SuperAdmin user creation** | Platform-level, not tenant |
| 7 | **Event dispatching (Registered)** | Standard Laravel auth flow |
| 8 | **Email verification flow** | Standard Laravel auth flow |
| 9 | **Subscription lifecycle management** | Ongoing (SubscriptionExpiryService) |
| 10 | **Plan enforcement / FeatureGate** | Runtime check, not bootstrap |

### NEEDS REVIEW

| # | Item | Issue | Recommendation |
|---|------|-------|---------------|
| 1 | **WebsiteInfo::getSettings()** | Uses `self::first()` without tenant scope | Fix to filter by tenant_id OR move to bootstrap |
| 2 | **TenantSeeder backfill** | Creates default tenant (id=1), masks missing tenant_ids | Remove tenant creation, keep backfill only |
| 3 | **Permission propagation** | New permissions not synced to existing tenant roles | Add `tenants:sync-permissions` command |
| 4 | **Owner role** | No dedicated `owner` role, uses `is_owner` flag | Add `owner` role in bootstrap |

---

## 13. Risk Analysis

### Critical Risks

| # | Risk | Description | Current Mitigation | Recommended Action |
|---|------|-------------|-------------------|-------------------|
| R1 | **No default data for new tenants** | Categories, payment methods, brands, units, website settings are never created for new tenants | `WebsiteInfo::getSettings()` lazy-creates (but broken — no tenant scope) | Move all to TenantBootstrapService |
| R2 | **WebsiteInfo data isolation failure** | All tenants share the same WebsiteInfo record. One tenant's settings overwrite another's | None | Add tenant_id scoping to getSettings(), create per-tenant defaults at bootstrap |
| R3 | **Owner has no protected role** | Owner identified only by `is_owner` flag, holds `admin` role (same as staff). No `owner` role exists | `protectOwner()` in AdminUserController | Add `owner` role to bootstrap, protect it |
| R4 | **Role bootstrap code duplicated in 3 controllers** | Any change to role creation logic must be replicated in all 3 locations | Manually kept in sync (brittle) | Extract to TenantBootstrapService |

### Medium Risks

| # | Risk | Description | Recommended Action |
|---|------|-------------|-------------------|
| R5 | **New permissions not propagated** | Adding a permission to PermissionSeeder does not update existing tenant admin roles | Create `tenants:sync-permissions` command |
| R6 | **BrandSeeder/UnitSeeder miss future tenants** | Only tenants existing at seed time get brands/units. Seeders iterate `Tenant::all()` | Move to TenantBootstrapService |
| R7 | **PaymentMethodSeeder uses `create()` not `firstOrCreate()`** | Duplicate records on re-seed | Either fix idempotency or move to bootstrap |
| R8 | **Subscription starts null for public registration** | `starts_at = null` until email verification | Acceptable for now (verified flow) |
| R9 | **No TenantCreated event** | Cannot hook into tenant creation without modifying controllers | Add event dispatching to bootstrap service |

### Low Risks

| # | Risk | Description |
|---|------|-------------|
| R10 | **TenantSeeder creates a default tenant** | Fresh installs get a tenant they didn't ask for |
| R11 | **FeatureGate DEV_MODE bypasses all checks** | Plan limits not enforced until DEV_MODE turned off |
| R12 | **No bootstrap jobs** | Post-creation is fully synchronous within DB transaction |

---

## 14. Implementation Complexity

| Component | Files to Create | Files to Modify | Complexity | Effort |
|-----------|----------------|-----------------|------------|--------|
| TenantBootstrapService | 1 | 0 | **Medium** | 1-2 days |
| Refactor CreateStoreController | 0 | 1 | **Low** | 2-4 hours |
| Refactor TenantController | 0 | 1 | **Low** | 2-4 hours |
| Refactor RegisteredUserController | 0 | 1 | **Low** | 1-2 hours |
| Add default data creation (categories, payment methods, brands, units) | 0 | 1 (service) | **Low** | 2-3 hours |
| Add WebsiteInfo bootstrap | 0 | 1 (service) | **Low** | 1 hour |
| Remove/repurpose 5 seeders | 0 | 5 | **Low** | 1-2 hours |
| Add Owner role | 0 | 5-6 | **Low-Medium** | 1 day |
| Add Permission propagation command | 1 | 0 | **Low** | 2-3 hours |
| Add TenantCreated event | 1 | 1 | **Low** | 1 hour |
| **Total** | **3** | **10-12** | **Low-Medium** | **3-5 days** |

### Files Affected

| File | Change | Type |
|------|--------|------|
| `app/Services/TenantBootstrapService.php` | **NEW** — central bootstrap logic | Create |
| `app/Events/TenantCreated.php` | **NEW** — bootstrap event (optional) | Create |
| `app/Console/Commands/SyncTenantPermissions.php` | **NEW** — permission propagation | Create |
| `app/Http/Controllers/CreateStoreController.php` | Replace inline bootstrap with service call | Modify |
| `app/Http/Controllers/SuperAdmin/TenantController.php` | Replace inline bootstrap with service call | Modify |
| `app/Http/Controllers/Auth/RegisteredUserController.php` | Replace inline customer role creation with service call | Modify |
| `database/seeders/DatabaseSeeder.php` | Remove tenant bootstrap seeders from call list | Modify |
| `database/seeders/WebsiteSettingsSeeder.php` | Keep file, remove from DatabaseSeeder | Modify |
| `database/seeders/PaymentMethodSeeder.php` | Keep file, remove from DatabaseSeeder | Modify |
| `database/seeders/CategorySeeder.php` | Keep file, remove from DatabaseSeeder | Modify |
| `database/seeders/UnitSeeder.php` | Keep file, remove from DatabaseSeeder | Modify |
| `database/seeders/BrandSeeder.php` | Keep file, remove from DatabaseSeeder | Modify |
| `app/Models/WebsiteInfo.php` | Fix `getSettings()` tenant scoping | Modify |

---

## 15. Recommended Service Structure

```php
namespace App\Services;

class TenantBootstrapService
{
    /**
     * Full bootstrap: called when a new tenant is created.
     */
    public function bootstrap(Tenant $tenant, array $options = []): User
    {
        DB::transaction(function () use ($tenant, $options) {
            $this->createRoles($tenant);                    // admin + customer roles
            $this->createSubscription($tenant, $options);   // Free plan assignment
            $this->createWebsiteSettings($tenant);          // default site settings
            $this->createPaymentMethods($tenant);           // default payment methods
            $this->createCategories($tenant);               // default categories
            $this->createBrands($tenant);                   // default brands
            $this->createUnits($tenant);                    // default units
            $owner = $this->createOwner($tenant, $options); // owner user + role + perms

            event(new TenantCreated($tenant, $owner));      // dispatch event
        });

        return $owner;
    }

    /**
     * Role-only bootstrap: for customer registration within existing store.
     */
    public function ensureCustomerRole(Tenant $tenant): Role
    {
        // Creates customer role if not exists, copies permissions from global template
    }

    // Private methods...
    private function createRoles(Tenant $tenant): void { /* ... */ }
    private function createSubscription(Tenant $tenant, array $options): Subscription { /* ... */ }
    private function createWebsiteSettings(Tenant $tenant): WebsiteInfo { /* ... */ }
    private function createPaymentMethods(Tenant $tenant): void { /* ... */ }
    private function createCategories(Tenant $tenant): void { /* ... */ }
    private function createBrands(Tenant $tenant): void { /* ... */ }
    private function createUnits(Tenant $tenant): void { /* ... */ }
    private function createOwner(Tenant $tenant, array $options): User { /* ... */ }
}
```

### Benefits of This Structure

1. **Single source of truth** for all tenant initialization logic
2. **Eliminates duplicated code** across 3 controllers
3. **Guarantees default data** for every new tenant (categories, payment methods, etc.)
4. **Event-driven** — TenantCreated event allows listeners to hook in
5. **Testable** — service can be unit tested independently
6. **Versionable** — new bootstrap items added in one place

---

## 16. Final Recommendations

### Immediate (No Code Changes)

1. Document that `WebsiteInfo::getSettings()` does not filter by tenant_id (data isolation gap)
2. Document that new tenants get no default categories, payment methods, brands, or units
3. Document that role bootstrap is duplicated in 3 controllers

### Short-Term (V3 Sprint 1)

4. **Create `TenantBootstrapService`** with all bootstrap candidates listed in Section 12
5. **Refactor `CreateStoreController`** to call `TenantBootstrapService::bootstrap()`
6. **Refactor `TenantController`** to call `TenantBootstrapService::bootstrap()`
7. **Refactor `RegisteredUserController`** to call `TenantBootstrapService::ensureCustomerRole()`
8. **Fix `WebsiteInfo::getSettings()`** to scope by tenant_id
9. **Remove tenant bootstrap seeders** from DatabaseSeeder (keep files for reference)

### Medium-Term (V3 Sprint 2)

10. **Add `owner` role** to bootstrap with all permissions (protected, non-editable)
11. **Add `tenant:sync-permissions` command** for permission propagation
12. **Add `TenantCreated` event** for extensibility

### Long-Term (V3 Sprint 3)

13. **Remove `TenantSeeder` tenant creation** (keep backfill only)
14. **Turn off `FeatureGate::DEV_MODE`** and enforce plan limits
15. **Add platform-specific settings model** to separate platform config from tenant config

---

## Summary

| Metric | Value |
|--------|-------|
| File Created | `docs/v3-tenant-bootstrap-audit-report.md` |
| Store Creation Files | 3 controllers (CreateStoreController, TenantController, RegisteredUserController) + 1 listener (ActivateTenantOnVerified) |
| Bootstrap Candidates | 12 items to move into TenantBootstrapService |
| TenantBootstrap Responsibilities | Role creation, permission sync, user creation, subscription, WebsiteInfo, payment methods, categories, brands, units, notification defaults |
| Keep Outside Bootstrap | PermissionSeeder, RoleAndPermissionSeeder, PlanSeeder, LocationSeeder, TenantSeeder, SuperAdmin user, event dispatching, subscription lifecycle |
| Critical Risks | R1 (no default data), R2 (WebsiteInfo isolation), R3 (no owner role), R4 (duplicated code) |
| Implementation Complexity | Low-Medium (3 new files, 10-12 modified files, 3-5 days) |
| Recommended Next Step | Create `app/Services/TenantBootstrapService.php` and extract role + subscription bootstrap from controllers |
