# Sprint B.1 — Tenant Membership Completion Report

**Date**: 2026-07-12
**Scope**: Complete TenantMembership creation across all entry points

---

## Files Changed

| File | Change | Type |
|------|--------|------|
| `database/seeders/MembershipSeeder.php` | **NEW** — Creates Owner + Customer memberships for seeded tenants | New |
| `database/seeders/DatabaseSeeder.php` | Added `MembershipSeeder::class` to call order after `TenantSeeder` | Modified |
| `database/seeders/UserSeeder.php` | Added `name` to Account creation; updated comment noting MembershipSeeder handles memberships | Modified |
| `app/Http/Requests/StoreUserRequest.php` | Made email uniqueness check account-aware (`accounts` table when in Account mode) | Modified |
| `app/Http/Controllers/Admin/AdminUserController.php` | Reuses existing Account when email already exists; scopes role lookup to tenant; passes `name` on Account create | Modified |
| `app/Console/Commands/RepairMissingMemberships.php` | **NEW** — `php artisan memberships:repair` command to backfill missing memberships | New |

---

## Flow Verification

### 1. Store Registration (`CreateStoreController` → `TenantBootstrapService`)

```
CreateStoreController.store()
  └─ TenantBootstrapService.bootstrap()
       ├─ createRoles()         → admin, customer roles with tenant_id
       ├─ createSubscription()  → Free plan subscription
       └─ createOwnerAccount()  → Account::create() + TenantMembership::create(is_owner=true, role=admin)
```

**Status**: ✅ Already correct before this sprint. No changes needed.

### 2. Customer Registration (`RegisteredUserController`)

```
RegisteredUserController.store()
  └─ storeAccount()
       ├─ Account::where('email')->first()
       ├─ If not found → Account::create()
       ├─ Check existing membership → reject if duplicate
       ├─ TenantMembership::create(is_owner=false, role=customer, status=active)
       ├─ CustomerProfile::firstOrCreate()
       └─ Auth::guard('accounts')->login($account)
```

**Status**: ✅ Already correct before this sprint. No changes needed.

### 3. Staff Invitation (`AdminUserController`)

```
AdminUserController.store()
  ├─ (NEW) Account::where('email')->first() — reuse existing
  ├─ (NEW) Check existing membership for tenant → reject if duplicate
  ├─ If not found → Account::create() with name + email + password
  ├─ TenantMembership::create(is_owner=false, role={selected}, status=active)
  └─ syncRoles() — Spatie assignment (redundant but harmless)
```

**Status**: ✅ Fixed in this sprint. Previously created a new Account every time. Now reuses existing Accounts.

### 4. Seeder Flow

```
DatabaseSeeder
  ├─ PermissionSeeder          → Spatie permissions
  ├─ RoleAndPermissionSeeder   → Global roles + SuperAdmin User & Account
  ├─ PlanSeeder                → Plans & features
  ├─ TenantSeeder              → Creates 3 tenants + backfill
  │    └─ (NEW) MembershipSeeder
  │         ├─ For each tenant:
  │         │    ├─ ensure admin/customer/staff role with tenant_id
  │         │    └─ create Owner Account + TenantMembership(is_owner=true)
  │         └─ For demo customers:
  │              └─ create TenantMembership(is_owner=false) for Default Store
```

**Status**: ✅ Fixed in this sprint. Previously zero memberships created during seeding.

### 5. Repair Command

```
php artisan memberships:repair
  ├──dry-run          → Report only, no DB changes
  └──tenant-id=       → Target specific tenant (default: Default Store)

Scans all Accounts without any membership.
Skips SuperAdmin (global — membership not needed).
Creates customer membership for remaining accounts.
```

**Status**: ✅ New in this sprint.

---

## Membership Table Verification

| Tenant | Owner Account | Owner Email | Customer Memberships |
|--------|--------------|-------------|---------------------|
| Default Store (ID=1) | Created | `owner@defaultstore.com` | 10 (john, jane, mike, etc.) |
| Khine Electronics | Created | `owner@khine.com` | None (test tenant) |
| Gadget World | Created | `owner@gadget.com` | None (test tenant) |

Each Owner membership:
- `is_owner` = `true`
- `role_id` = admin role (tenant-scoped)
- `status` = `active`

Each Customer membership:
- `is_owner` = `false`
- `role_id` = customer role (tenant-scoped)
- `status` = `active`

---

## Role Resolution After This Sprint

```
Account (admin@shop.com)
  └─ model_has_roles → superadmin [global]
       └─ Bypasses membership — infinite permissions

Account (owner@defaultstore.com)
  └─ memberships[0] → tenant=Default Store, role=admin, is_owner=true
       └─ hasRole('admin') → true (via membership.role)
       └─ hasPermissionTo(*) → true (via is_owner)

Account (john@example.com)
  └─ memberships[0] → tenant=Default Store, role=customer, is_owner=false
       └─ hasRole('customer') → true (via membership.role)
       └─ hasPermissionTo('orders.view-own') → true (via role → permissions)
```

---

## Remaining Issues

| Issue | Impact | Notes |
|-------|--------|-------|
| Model relationships hardcoded to `User::class` (13 models) | Orders, Messages, Wishlists etc. still reference `users.id` | Requires schema migration — outside sprint scope |
| SuperAdmin has no display name in Account mode | Shows email instead of "Super Admin" | `Account::getDisplayName()` returns email when no membership exists |
| `RegisteredUserController` creates `CustomerProfile` but `AdminUserController` does not | Staff users created via admin panel have no profile record | Low priority — profile is optional |
| `UserSeeder` creates customers without `tenant_id` (legacy mode) | Demo customers have no tenant association | Legacy mode — acceptable |
| `StoreUserRequest` validates `name` as required but Account path may reuse existing Account without updating name | Name not updated when reusing existing Account | Name is set on initial creation; subsequent invitations reuse name as-is |
| No `staff` role permissions seeded | Staff role has zero permissions by default | Must be configured in admin panel after creation |

---

## Commands Reference

```bash
# Run the repair command (dry-run first)
php artisan memberships:repair --dry-run

# Apply repair to Default Store
php artisan memberships:repair

# Apply repair to a specific tenant
php artisan memberships:repair --tenant-id=2

# Re-seed with memberships
php artisan db:seed --class=MembershipSeeder
```
