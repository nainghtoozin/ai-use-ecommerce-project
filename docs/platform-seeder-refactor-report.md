# Platform Seeder Refactor Report

**Sprint**: 6.3.2
**Date**: 2026-07-12
**Scope**: Seeder alignment to Platform Identity Design Lock
**Reference**: `docs/platform-identity-design-lock.md`

---

## 1. Seeders Modified

| Seeder | Status | Changes |
|---|---|---|
| `RoleAndPermissionSeeder` | **Refactored** | Removed tenant-scoped role creation. Only creates global `superadmin` role + SuperAdmin Account. |
| `TenantSeeder` | **Refactored** | Removed hardcoded `id=1`. Removed backfill logic. Uses `updateOrCreate` on `slug`. |
| `MembershipSeeder` | **Refactored** | Added tenant-scoped role creation. Added owner/customer membership creation. Added duplicate/missing owner repair. |
| `UserSeeder` | **Deprecated** | No longer creates records. Customer creation moved to MembershipSeeder. |
| `OrderSeeder` | **Refactored** | Added Account mode support. Queries Account via TenantMembership when `identity.use_accounts=true`. |
| `DemoDataSeeder` | **Updated** | Removed UserSeeder call. Now calls ProductSeeder + OrderSeeder only. |
| `DatabaseSeeder` | **Refactored** | Fixed execution order. TenantSeeder + MembershipSeeder now run before tenant-scoped seeders. |
| `CategorySeeder` | **Refactored** | Added tenant iteration. Now creates per-tenant categories. |
| `LocationSeeder` | **Refactored** | Added tenant iteration. Now creates per-tenant cities/townships. |
| `WebsiteSettingsSeeder` | **Refactored** | Added tenant iteration. Now creates per-tenant website info. |
| `PaymentMethodSeeder` | **Refactored** | Added tenant iteration. Now creates per-tenant payment methods. |
| `OrderFactory` | **Refactored** | Added Account mode support. Queries Account via TenantMembership. |

### Seeders Unchanged

| Seeder | Reason |
|---|---|
| `PermissionSeeder` | Already correct — creates global permissions only |
| `PlanSeeder` | Already correct — platform-level plans |
| `PlatformSettingSeeder` | Already correct — platform-level settings |
| `BillingPaymentMethodSeeder` | Already correct — platform-level billing methods |
| `UnitSeeder` | Already correct — iterates over tenants |
| `BrandSeeder` | Already correct — iterates over tenants |

---

## 2. Platform Seeder Flow

### RoleAndPermissionSeeder

```
1. Clear Spatie permission cache
2. Call PermissionSeeder (global permissions)
3. Create superadmin role (global, tenant_id=NULL)
4. Sync all permissions to superadmin role
5. Create SuperAdmin Account (admin@shop.com)
6. Assign superadmin role to Account
7. Create legacy User record (backward compatibility)
8. Assign superadmin role to User
```

**Key Rules**:
- SuperAdmin has NO `tenant_id`
- SuperAdmin has NO `TenantMembership`
- SuperAdmin has NO merchant/customer/staff profile
- `superadmin` is the ONLY global role

---

## 3. Demo Tenant Flow

### TenantSeeder

```
1. Create Default Store (slug='default')
2. Create Khine Electronics (slug='khine')
3. Create Gadget World (slug='gadget')
4. Clear tenant cache
```

**Key Rules**:
- Each tenant is created via `updateOrCreate` on `slug`
- No hardcoded `id` — database auto-increments
- No backfill logic — that's a migration artifact
- All tenants start with `status='active'`

---

## 4. Owner Creation Flow

### MembershipSeeder::ensureOwnerMembership()

```
1. Check if owner already exists for tenant
2. If exists → skip (no duplicate)
3. Resolve owner email from tenant slug
4. Create or update Account record
5. Find tenant-scoped admin role
6. Create TenantMembership:
   - account_id → owner Account
   - tenant_id → demo tenant
   - role_id → admin role
   - is_owner → true
   - status → 'active'
```

**Owner Email Mapping**:
| Tenant | Owner Email |
|---|---|
| Default Store | `owner@defaultstore.com` |
| Khine Electronics | `owner@khine.com` |
| Gadget World | `owner@gadget.com` |

---

## 5. Membership Creation Flow

### MembershipSeeder

```
For each tenant:
  1. ensureTenantRoles()
     - Create admin role (tenant_id = tenant.id)
     - Create staff role (tenant_id = tenant.id)
     - Create customer role (tenant_id = tenant.id)

  2. ensureOwnerMembership()
     - Create owner Account
     - Create owner TenantMembership (is_owner=true)

3. ensureCustomerMemberships()
   - Only for Default Store
   - Create 10 customer Accounts
   - Create 10 customer TenantMemberships

4. repairDuplicateOwners()
   - For each tenant, check for duplicate owners
   - Keep first, clear is_owner on others

5. repairMissingOwners()
   - For each tenant, check if owner exists
   - If missing, create from tenant email
```

---

## 6. Repair Summary

### Duplicate Owner Repair

```
For each tenant:
  Query: tenant_memberships WHERE tenant_id=X AND is_owner=true
  If count > 1:
    Keep: first record (ORDER BY id ASC)
    Clear: is_owner=false on remaining records
```

### Missing Owner Repair

```
For each tenant:
  Query: tenant_memberships WHERE tenant_id=X AND is_owner=true
  If count == 0:
    Resolve email from tenant.email or "owner@{slug}.com"
    Create Account
    Create TenantMembership with is_owner=true
```

---

## 7. Manual Test Checklist

### Platform SuperAdmin Verification

```bash
php artisan tinker
```

```php
// SuperAdmin exists in accounts table
$sa = App\Models\Account::where('email', 'admin@shop.com')->first();
assert($sa !== null, 'SuperAdmin Account exists');

// SuperAdmin has global superadmin role
assert($sa->hasRole('superadmin'), 'SuperAdmin has superadmin role');

// SuperAdmin has NO tenant memberships
assert($sa->memberships()->count() === 0, 'SuperAdmin has no memberships');

// SuperAdmin has NO tenant_id
assert($sa->tenant_id === null, 'SuperAdmin has no tenant_id');
```

### Demo Tenant Verification

```bash
php artisan tinker
```

```php
// All demo tenants exist
$tenants = App\Models\Tenant::whereIn('slug', ['default', 'khine', 'gadget'])->get();
assert($tenants->count() === 3, 'Three demo tenants exist');

// Each tenant has exactly one owner
foreach ($tenants as $tenant) {
    $ownerCount = App\Models\TenantMembership::where('tenant_id', $tenant->id)
        ->where('is_owner', true)
        ->count();
    assert($ownerCount === 1, "Tenant '{$tenant->name}' has exactly one owner");
}
```

### Owner Account Verification

```bash
php artisan tinker
```

```php
// Owner accounts exist
$ownerEmails = ['owner@defaultstore.com', 'owner@khine.com', 'owner@gadget.com'];
foreach ($ownerEmails as $email) {
    $account = App\Models\Account::where('email', $email)->first();
    assert($account !== null, "Owner account {$email} exists");
    
    // Owner has membership
    $membership = App\Models\TenantMembership::where('account_id', $account->id)
        ->where('is_owner', true)
        ->first();
    assert($membership !== null, "Owner {$email} has membership");
    
    // Owner is NOT SuperAdmin
    assert(!$account->hasRole('superadmin'), "Owner {$email} is not SuperAdmin");
}
```

### Tenant Role Verification

```bash
php artisan tinker
```

```php
// Each tenant has admin, staff, customer roles
$tenants = App\Models\Tenant::whereIn('slug', ['default', 'khine', 'gadget'])->get();
foreach ($tenants as $tenant) {
    $roles = App\Models\Role::where('tenant_id', $tenant->id)->pluck('name');
    assert($roles->contains('admin'), "Tenant '{$tenant->name}' has admin role");
    assert($roles->contains('staff'), "Tenant '{$tenant->name}' has staff role");
    assert($roles->contains('customer'), "Tenant '{$tenant->name}' has customer role");
}

// Global superadmin role exists with tenant_id=NULL
$superadminRole = App\Models\Role::where('name', 'superadmin')->whereNull('tenant_id')->first();
assert($superadminRole !== null, 'Global superadmin role exists');
```

### Customer Membership Verification

```bash
php artisan tinker
```

```php
// Default Store has 10 customer memberships
$defaultTenant = App\Models\Tenant::where('slug', 'default')->first();
$customerRole = App\Models\Role::where('name', 'customer')
    ->where('tenant_id', $defaultTenant->id)
    ->first();

$customerMemberships = App\Models\TenantMembership::where('tenant_id', $defaultTenant->id)
    ->where('role_id', $customerRole->id)
    ->count();
assert($customerMemberships === 10, 'Default Store has 10 customer memberships');
```

---

## 8. Remaining Risks

### RISK 1: Legacy User Model Still Referenced

**Level**: Medium
**Description**: 13+ model relationships still reference `User::class` (Order, Message, Wishlist, etc.).
**Impact**: In Account mode, these relationships may return empty collections.
**Mitigation**: Phase 7 will add polymorphic `account_id` FK to business tables.

### RISK 2: UserSeeder Deprecated but Still Called

**Level**: Low
**Description**: `UserSeeder` is deprecated but still called by `DemoDataSeeder` (now a no-op).
**Impact**: None — seeder outputs info message and exits.
**Mitigation**: Remove `UserSeeder` from codebase in Phase 7.

### RISK 3: SyncsIdentity Creates Dual Records

**Level**: Low
**Description**: When SuperAdmin Account is created, `SyncsIdentity` creates a matching User record.
**Impact**: Both `accounts` and `users` tables have SuperAdmin records.
**Mitigation**: Intentional for backward compatibility. Will be removed when `users` table is dropped.

### RISK 4: TenantSeeder Backfill Removed

**Level**: Low
**Description**: The `backfillNullTenantIds()` method was removed from TenantSeeder.
**Impact**: Legacy data with `tenant_id=NULL` won't be auto-assigned to Default Store.
**Mitigation**: This was a migration artifact. Production data should already have `tenant_id` set.

### RISK 5: Global Role Scope During Seeding

**Level**: Low
**Description**: `TenantScope` exempts `Role::class` from tenant filtering and disables during `db:seed`.
**Impact**: `Role::firstOrCreate` finds existing roles regardless of `tenant_id`.
**Mitigation**: Correct behavior — global roles (superadmin) must be findable.

---

## 9. Seeder Execution Order (Final)

```
┌─────────────────────────────────────────────────────────────┐
│ PLATFORM SEEDERS (no tenant dependency)                     │
├─────────────────────────────────────────────────────────────┤
│ 1. PermissionSeeder         → permissions (global)          │
│ 2. RoleAndPermissionSeeder  → superadmin role + Account     │
│ 3. PlanSeeder               → plans + plan_features         │
│ 4. PlatformSettingSeeder    → platform_settings             │
│ 5. BillingPaymentMethodSeeder → billing_payment_methods     │
├─────────────────────────────────────────────────────────────┤
│ TENANT BOOTSTRAP                                            │
├─────────────────────────────────────────────────────────────┤
│ 6. TenantSeeder             → tenants (demo)                │
│ 7. MembershipSeeder         → roles + memberships           │
├─────────────────────────────────────────────────────────────┤
│ TENANT-SCOPED SEEDERS (require tenants to exist)            │
├─────────────────────────────────────────────────────────────┤
│ 8. LocationSeeder           → cities + townships (per tenant)│
│ 9. WebsiteSettingsSeeder    → website_infos (per tenant)    │
│ 10. PaymentMethodSeeder     → payment_methods (per tenant)  │
│ 11. CategorySeeder          → categories (per tenant)       │
│ 12. UnitSeeder              → units (per tenant)            │
│ 13. BrandSeeder             → brands (per tenant)           │
└─────────────────────────────────────────────────────────────┘

Optional (via DemoDataSeeder):
  14. ProductSeeder           → products (per tenant)
  15. OrderSeeder             → orders (per tenant)
```

---

## 10. Design Lock Compliance Matrix

| Design Lock Rule | Seeder Compliance | Status |
|---|---|---|
| SuperAdmin is platform-only | `RoleAndPermissionSeeder` creates only Account | ✅ |
| SuperAdmin has no TenantMembership | No membership created for SuperAdmin | ✅ |
| Every tenant has exactly one owner | `MembershipSeeder` enforces + repairs | ✅ |
| Tenant roles are tenant-scoped | `MembershipSeeder` creates with `tenant_id` | ✅ |
| superadmin role is global | `RoleAndPermissionSeeder` creates with `tenant_id=NULL` | ✅ |
| Account is canonical identity | Account created first, User via SyncsIdentity | ✅ |
| No login-time mutation | No identity mutation in seeders | ✅ |
| TenantScoped data has tenant_id | All tenant-scoped seeders iterate over tenants | ✅ |

---

**END OF REFACTOR REPORT**
