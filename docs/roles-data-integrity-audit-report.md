# Roles Data Integrity & Tenant Role Architecture Audit Report

## Status: Completed (Read-Only Audit)

---

## 1. Executive Summary

The roles system has **30 total records** across **6 unique role names**, with significant data integrity issues arising from the transition from single-store to multi-tenant architecture. **12 global role records** exist but only **1 (superadmin)** is legitimate. The unique index `(tenant_id, name, guard_name)` allows multiple NULL tenant_id rows in MySQL, causing duplicate global role accumulation on each `migrate:fresh --seed`. Two tenants are missing their `admin` role entirely. Five user-role assignments reference stale global roles instead of tenant-specific ones. One cross-tenant assignment error exists. Two custom roles have naming inconsistencies. Role-protection code references `admin`/`superadmin` by name string, which does not protect the `admins` variant in Tenant 2.

---

## 2. Role Inventory

### Table Structure
```
roles
├── id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
├── tenant_id       BIGINT UNSIGNED NULLABLE → tenants(id) ON DELETE SET NULL
├── name            VARCHAR(255)
├── guard_name      VARCHAR(255)
├── created_at      TIMESTAMP NULLABLE
└── updated_at      TIMESTAMP NULLABLE

UNIQUE KEY: (tenant_id, name, guard_name)
FOREIGN KEY: tenant_id → tenants(id) ON DELETE SET NULL
```

### Global Roles (tenant_id = NULL) — 12 records

| ID | Name | Guard | Created | Users | Status |
|----|------|-------|---------|-------|--------|
| 1 | superadmin | web | 2026-05-24 | 2 | ✅ Legitimate |
| 2 | admin | web | 2026-05-24 | 0 | ❌ Duplicate (original template) |
| 3 | customer | web | 2026-05-24 | 4 | ❌ Duplicate (original template, stale assignments) |
| 12 | admin | web | 2026-05-31 | 0 | ❌ Duplicate |
| 13 | customer | web | 2026-05-31 | 0 | ❌ Duplicate |
| 14 | admin | web | 2026-05-31 | 1 | ❌ Duplicate (1 stale assignment to User 19) |
| 15 | customer | web | 2026-05-31 | 0 | ❌ Duplicate |
| 18 | admin | web | 2026-06-02 | 0 | ❌ Duplicate |
| 19 | customer | web | 2026-06-02 | 0 | ❌ Duplicate |
| 36 | admin | web | 2026-06-20 | 0 | ❌ Duplicate |
| 37 | admin | web | 2026-06-20 | 0 | ❌ Duplicate |
| 38 | admin | web | 2026-06-20 | 0 | ❌ Duplicate |

### Tenant Roles (tenant_id = NOT NULL) — 18 records

| ID | Name | Tenant | Guard | Created | Users | Status |
|----|------|--------|-------|---------|-------|--------|
| 4 | admin | 1 | web | 2026-05-28 | 1 | ✅ Correct |
| 5 | customer | 1 | web | 2026-05-28 | 12 | ✅ Correct |
| 6 | **admins** | 2 | web | 2026-05-28 | 1 | ⚠️ Naming: plural "admins" |
| 7 | customer | 2 | web | 2026-05-28 | 4 | ✅ Correct |
| 10 | admin | 4 | web | 2026-05-31 | 1 | ✅ Correct |
| 11 | customer | 4 | web | 2026-05-31 | 0 | ✅ Orphaned (no users yet) |
| 20 | admin | 10 | web | 2026-06-02 | 1 | ✅ Correct |
| 21 | customer | 10 | web | 2026-06-02 | 0 | ✅ Orphaned (no users yet) |
| 22 | customer | 11 | web | 2026-06-03 | 1 | ⚠️ Missing admin role for this tenant |
| 23 | customer | 12 | web | 2026-06-03 | 1 | ⚠️ Missing admin role for this tenant |
| 28 | **Manager** | 2 | web | 2026-06-16 | 1 | ⚠️ Naming: capitalized |
| 29 | admin | 15 | web | 2026-06-17 | 1 | ✅ Correct |
| 30 | customer | 15 | web | 2026-06-17 | 0 | ✅ Orphaned (no users yet) |
| 31 | **Managers** | 15 | web | 2026-06-17 | 1 | ⚠️ Naming: plural + capitalized |
| 32 | admin | 16 | web | 2026-06-19 | 1 | ✅ Correct |
| 33 | customer | 16 | web | 2026-06-19 | 0 | ✅ Orphaned (no users yet) |
| 34 | admin | 17 | web | 2026-06-19 | 1 | ✅ Correct |
| 35 | customer | 17 | web | 2026-06-19 | 0 | ✅ Orphaned (no users yet) |

---

## 3. Global Roles Analysis

### Legitimate Global Role

**superadmin (id=1)** — The only intended global role. Exists as a platform-level role with no tenant affiliation. Used by `RoleController::edit()`/`update()`/`destroy()` protection checks (by name string `'superadmin'`). Assigned to 2 users (User 1 = Super Admin, User 11 = Anna White).

### Duplicate Global Role Templates (Legacy)

**admin templates (ids 2, 12, 14, 18, 36, 37, 38)** — 7 records for the same `admin` role. The original `RoleAndPermissionSeeder` uses `Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'])`. With the `TenantAware` global scope, `firstOrCreate` searches with an implicit `tenant_id` filter. When no current tenant exists (seeder context), the scope does not filter by tenant_id. However, the `TenantAware` `creating` callback auto-assigns tenant_id from `Tenant::getCurrent()`, which may be null. Because MySQL's unique index permits multiple NULL `tenant_id` values, each execution creates a new row.

**customer templates (ids 3, 13, 15, 19)** — 4 records. Same root cause as admin templates.

**Root cause:** The unique key `(tenant_id, name, guard_name)` treats each NULL `tenant_id` as a distinct value in MySQL. Every `php artisan db:seed` (or `migrate:fresh --seed`) creates 2-3 new global role rows.

### Global Role References

| System | References | Correctness |
|--------|-----------|-------------|
| `RoleAndPermissionSeeder` | Looks up global roles by name (firstOrCreate) | ❌ Creates duplicates per execution |
| `CreateStoreController` | Clones global roles to tenant roles (lookup by name) | ⚠️ Picks first match, needs deduplication |
| `TenantController` | Same clone pattern | ⚠️ Same issue |
| `SyncTenantRoles` | Same clone pattern | ⚠️ Same issue |
| `RoleController` protection | Checks name string `['superadmin', 'admin']` | ✅ Correct intent but doesn't cover `admins` variant |
| 2026-05-28 migration | Backfill picks global role by `keyBy('name')` | ⚠️ Picks arbitrary global role when duplicates exist |

---

## 4. Tenant Roles Analysis

### Correct Tenant Roles

| Tenant | Admin Role | Customer Role | Users |
|--------|-----------|---------------|-------|
| 1 | admin (id=4) | customer (id=5) | 1 admin, 12 customers |
| 4 | admin (id=10) | customer (id=11) | 1 admin, 0 customers |
| 10 | admin (id=20) | customer (id=21) | 1 admin, 0 customers |
| 15 | admin (id=29) | customer (id=30) | 1 admin, 0 customers |
| 16 | admin (id=32) | customer (id=33) | 1 admin, 0 customers |
| 17 | admin (id=34) | customer (id=35) | 1 admin, 0 customers |

### Missing Admin Roles (Bootstrap Failure)

| Tenant | Customer Role | Admin Role | Impact |
|--------|--------------|------------|--------|
| 11 | customer (id=22) | ❌ MISSING | No tenant admin can be created |
| 12 | customer (id=23) | ❌ MISSING | No tenant admin can be created |

These tenants were likely created before the tenant-scoped role bootstrap was fully implemented or via a different code path that only created the customer role.

### Naming Anomalies

| Tenant | Role Name | Problem |
|--------|-----------|---------|
| 2 | `admins` (id=6) | Should be `admin` — pluralized |
| 2 | `Manager` (id=28) | Capitalized first letter |
| 15 | `Managers` (id=31) | Pluralized + capitalized |

The Tenant 2 `admins` role is particularly dangerous: the `RoleController` protect checks look for `'admin'` (exact string), so this role bypasses all edit/delete protection. A user with the `admins` role can be edited or deleted by name string check.

---

## 5. Role Naming Consistency

| Issue | Roles Affected | Risk |
|-------|---------------|------|
| Plural `admins` vs `admin` | T2 (id=6) | **HIGH**: bypasses name-string protection checks |
| Capitalization `Manager` vs lowercase | T2 (id=28) | **LOW**: convention only, not referenced in protection |
| Plural `Managers` vs singular | T15 (id=31) | **LOW**: convention only |
| Protection code checks exact name | `RoleController` 4 checks | **MEDIUM**: `admins` is invisible to name-string matching |

All protection code in `RoleController`, `StoreRoleRequest`, and `UpdateRoleRequest` uses `in_array($role->name, ['superadmin', 'admin'])`. The `admins` role is not matched. If Tenant 2's admin user has `admins` instead of `admin`, they can edit/delete their own role.

---

## 6. Role Assignment Analysis

### Full Assignment Map (35 total assignments, 30 unique user-role pairs)

| User | Name | Tenant | Role(s) | Status |
|------|------|--------|---------|--------|
| 1 | Super Admin | 1 | superadmin (global) | ✅ Correct |
| 2 | John Doe | 1 | customer (T1) | ✅ Correct |
| 3 | Jane Smith | 1 | customer (T1) | ✅ Correct |
| 4 | Mike Johnson | 1 | customer (T1) | ✅ Correct |
| 5 | Sarah Williams | 1 | customer (T1) | ✅ Correct |
| 6 | David Brown | 1 | customer (T1) | ✅ Correct |
| 7 | Emily Davis | 1 | customer (T1) | ✅ Correct |
| 8 | Chris Wilson | 1 | customer (T1) | ✅ Correct |
| 9 | Lisa Taylor | 1 | customer (T1) | ✅ Correct |
| 10 | Tom Anderson | 1 | customer (T1) | ✅ Correct |
| 11 | Anna White | 1 | superadmin (global), customer (global) | ⚠️ Dual assignment + stale customer |
| 13 | May May | 2 | admins (T2) | ⚠️ "admins" naming issue |
| 14 | Aung Aung | 2 | customer (T2) | ✅ Correct |
| 16 | kaung | 2 | customer (T2) | ✅ Correct |
| 17 | Aung Gyi | 4 | admin (T4) | ✅ Correct |
| 18 | Test | 1 | admin (T1) | ✅ Correct |
| 19 | Test | 1 | admin (global, id=14) | ❌ **Stale global assignment** |
| 22 | Test | 10 | admin (T10) | ✅ Correct |
| 23 | Naing Htoo | 10 | customer (global, id=3) | ❌ **Stale global assignment** |
| 24 | Khin Khin | 10 | customer (T1, id=5) | ❌ **Cross-tenant assignment!** |
| 25 | Customer A | 11 | customer (T11) | ✅ Correct |
| 26 | Customer B | 12 | customer (T12) | ✅ Correct |
| 30 | myint | 1 | customer (T1) | ✅ Correct |
| 31 | Htet | 10 | customer (global, id=3) | ❌ **Stale global assignment** |
| 32 | aye chan | 1 | customer (T1) | ✅ Correct |
| 33 | khin pwint | 2 | customer (T2) | ✅ Correct |
| 34 | hello | 2 | customer (global, id=3) | ❌ **Stale global assignment** |
| 35 | Aye May | 2 | customer (T2) | ✅ Correct |
| 38 | nna | 2 | Manager (T2) | ⚠️ Capitalized name |
| 39 | Store MM | 15 | admin (T15) | ✅ Correct |
| 40 | Store MMC | 15 | Managers (T15) | ⚠️ Plural + capitalized |
| 41 | Naing | 16 | admin (T16) | ✅ Correct |
| 42 | Aye Aye | 17 | admin (T17) | ✅ Correct |

### Problems Found

1. **User 11 (Anna White)** — Has both `superadmin` (global) AND `customer` (global). The `superadmin` role is likely intentional, but the `customer` role is stale from UserSeeder.

2. **User 19 (Test, T1)** — Assigned to global admin (id=14) instead of T1 admin (id=4). The migration backfill missed this user.

3. **User 23 (Naing Htoo, T10)** — Assigned to global customer (id=3) instead of T10 customer (id=21).

4. **User 24 (Khin Khin, T10)** — Assigned to T1 customer (id=5). **Cross-tenant assignment** — this user is in tenant 10 but references a role belonging to tenant 1.

5. **User 31 (Htet, T10)** — Assigned to global customer (id=3) instead of T10 customer (id=21).

6. **User 34 (hello, T2)** — Assigned to global customer (id=3) instead of T2 customer (id=7).

### Roles with Zero Users (Orphaned)

| ID | Name | Tenant | Created | Source |
|----|------|--------|---------|--------|
| 2 | admin | NULL | 2026-05-24 | Global template duplicate |
| 11 | customer | 4 | 2026-05-31 | Tenant bootstrap (no customer users yet) |
| 12 | admin | NULL | 2026-05-31 | Global template duplicate |
| 13 | customer | NULL | 2026-05-31 | Global template duplicate |
| 15 | customer | NULL | 2026-05-31 | Global template duplicate |
| 18 | admin | NULL | 2026-06-02 | Global template duplicate |
| 19 | customer | NULL | 2026-06-02 | Global template duplicate |
| 21 | customer | 10 | 2026-06-02 | Tenant bootstrap (no customer users yet) |
| 30 | customer | 15 | 2026-06-17 | Tenant bootstrap (no customer users yet) |
| 33 | customer | 16 | 2026-06-19 | Tenant bootstrap (no customer users yet) |
| 35 | customer | 17 | 2026-06-19 | Tenant bootstrap (no customer users yet) |
| 36 | admin | NULL | 2026-06-20 | Global template duplicate |
| 37 | admin | NULL | 2026-06-20 | Global template duplicate |
| 38 | admin | NULL | 2026-06-20 | Global template duplicate |

---

## 7. Tenant Bootstrap Analysis

### Role Creation Flow

Three independent code paths create tenant-scoped roles:

| Path | File | When | Creates |
|------|------|------|---------|
| Public store registration | `CreateStoreController::store()` | User registers new store | admin + customer |
| Super admin tenant creation | `TenantController::store()` | Super admin creates tenant | admin + customer |
| Sync command | `SyncTenantRoles::handle()` | `php artisan tenants:sync-roles` | admin + customer (firstOrCreate) |

### Implementation Pattern (duplicated across all 3)

```php
foreach (['admin', 'customer'] as $roleName) {
    $role = Role::where('name', $roleName)
        ->where('guard_name', 'web')
        ->where('tenant_id', $tenant->id)
        ->first();

    if (!$role) {
        $role = new Role();
        $role->name = $roleName;
        $role->guard_name = 'web';
        $role->tenant_id = $tenant->id;
        $role->save();

        $globalRole = Role::where('name', $roleName)
            ->whereNull('tenant_id')
            ->first();
        if ($globalRole) {
            $role->syncPermissions($globalRole->permissions);
        }
    }
}
```

### Critical Issues

1. **No shared service** — the same logic is copy-pasted 3 times
2. **Global role ambiguity** — `Role::where('name', $roleName)->whereNull('tenant_id')->first()` returns an arbitrary global role when duplicates exist
3. **Missing data for T11 and T12** — These tenants only have a customer role. `SyncTenantRoles` should have caught these, but either it wasn't run or the tenants were created by an older code path
4. **No admin role = no admin login** — Tenants 11 and 12 cannot have admin users logged in
5. **Permission drift** — New permissions added to `PermissionSeeder` are never synced to existing tenant-scoped admin roles

---

## 8. Sync Command Analysis

### `tenants:sync-roles`

**File:** `app/Console/Commands/SyncTenantRoles.php`

**Purpose:** Iterates all tenants and uses `firstOrCreate` to ensure each has tenant-scoped `admin` and `customer` roles. Optionally migrates user role assignments with `--migrate-assignments`.

**Behavior:**
- Idempotent (uses `firstOrCreate`)
- Copies permissions from global role template
- Safe for existing data

**Duplication risk:** Low (firstOrCreate)
**Corruption risk:** Low
**Coverage gap:** Does not fix Tenants 11/12 because they ALREADY have a customer role — it only creates if completely missing. The admin role IS missing for these tenants, so it would create them. But the command was likely never run for these tenants.

### No Other Sync Commands

No `tenants:sync-permissions` command exists. New permissions added to the global permission set are never propagated to existing tenant-scoped admin roles.

---

## 9. Legacy Data Detection

### Pre-SaaS Global Roles (Original Single-Store)

| ID | Name | Tenant | Origin | Classification |
|----|------|--------|--------|---------------|
| 1 | superadmin | NULL | Original seed (2026-05-24) | **KEEP** — legitimate platform role |
| 2 | admin | NULL | Original seed (2026-05-24) | **DELETE CANDIDATE** — global template, no users |
| 3 | customer | NULL | Original seed (2026-05-24) | **NEEDS REVIEW** — still has 4 stale user assignments |

### Migration-Created Duplicates (2026-05-28 migration)

The `backfillTenantRoles()` method in `2026_05_28_000006_add_tenant_id_to_roles.php` reads global roles by name. If multiple global `admin` or `customer` rows existed (which they did), the `keyBy('name')` call selects one arbitrarily. This may have created tenant clones from the wrong global template.

### Post-Migration Accumulated Duplicates

| IDs | Name | Created | Source |
|-----|------|---------|--------|
| 12, 13 | admin, customer | 2026-05-31 | Repeated `migrate:fresh --seed` |
| 14, 15 | admin, customer | 2026-05-31 | Repeated `migrate:fresh --seed` |
| 18, 19 | admin, customer | 2026-06-02 | Repeated `migrate:fresh --seed` |
| 36, 37, 38 | admin | 2026-06-20 | Recent `migrate:fresh --seed` during development |

### Tenant Bootstrap Gap (T11, T12)

Tenants 11 and 12 were created during development testing and only received customer roles. The admin role creation was skipped or these were created via a test route that didn't include role bootstrap.

---

## 10. Data Integrity Findings

### Issue 1: Duplicate Global Roles (11 redundant rows)
| Severity | Impact | Root Cause |
|----------|--------|------------|
| **HIGH** | Permission template ambiguity, wasted rows | MySQL unique index allows multiple NULL tenant_ids |

### Issue 2: Missing Admin Roles (2 tenants)
| Severity | Impact | Root Cause |
|----------|--------|------------|
| **HIGH** | T11 and T12 cannot have admin users | Bootstrap skipped or incomplete |

### Issue 3: Cross-Tenant Assignment
| Severity | Impact | Details |
|----------|--------|---------|
| **HIGH** | User 24 (Khin Khin, T10) assigned T1 customer role (id=5) | Permission leakage: this user has a role belonging to a different tenant |

### Issue 4: Stale Global Role Assignments (5 users)
| Severity | Impact | Users |
|----------|--------|-------|
| **MEDIUM** | Users 11, 19, 23, 31, 34 are on global roles instead of tenant-specific ones | These users may lose permissions if global roles are cleaned up |

### Issue 5: Naming Inconsistency (3 roles)
| Severity | Impact | Roles |
|----------|--------|-------|
| **MEDIUM** | `admins` (T2) bypasses protection name-string checks | `RoleController` searches for `'admin'` not `'admins'` |

### Issue 6: Dual Role Assignment
| Severity | Impact | User |
|----------|--------|------|
| **LOW** | User 11 has both superadmin AND customer | Unusual but functionally harmless |

### Issue 7: Role Name Spoofing via Admin Panel
| Severity | Impact | Details |
|----------|--------|---------|
| **MEDIUM** | Admin can create role named `Admin` (capital A) which bypasses lowercase string check | StoreRoleRequest now has `not_in:superadmin,admin` but doesn't cover case variants |

---

## 11. Version 3 Readiness

| V3 Feature | Current State | Readiness | Gap |
|-----------|---------------|-----------|-----|
| Owner Role | Owner is flagged via `is_owner` bool on User model, not a role | ✅ Ready | No migration needed |
| Owner Protection | Protected by `is_owner` checks in controller logic | ✅ Ready | Role protection covers `superadmin` + `admin` |
| Manager Role | Exists ad-hoc as `Manager` (T2) and `Managers` (T15) with inconsistent naming | ⚠️ Needs standardization | Define lowercase `manager`^[1]^ |
| Staff Role | Does not exist yet | ⚠️ Needs creation | Define `staff` role with limited permissions |
| `admin.access` permission | Not implemented | ❌ Missing | Current admin access is role-based, not permission-based |
| Protected System Roles | `superadmin` + `admin` protected by name string | ⚠️ Fragile | Name-string matching doesn't cover `admins` or case variants |
| Tenant Role Isolation | TenantScoped via `getTenantFilter()` | ✅ Ready | Tenant-specific roles visible only within their tenant |

**[1] Naming convention**: All code references use lowercase (`'admin'`, `'customer'`, `'superadmin'`). Custom roles should follow `lowercase-with-dashes` convention to match permission naming.

---

## 12. Safe Cleanup Plan

### Phase 1: Consolidate Global Roles (Safe — read-only data)

| Action | Target | Method | Risk |
|--------|--------|--------|------|
| DELETE | Global admin ids 2, 12, 18, 36, 37, 38 | SQL DELETE (0 users each, unused) | None |
| DELETE | Global customer ids 13, 15, 19 | SQL DELETE (0 users each, unused) | None |
| KEEP | Global admin id 14 | Has 1 user (User 19) — needs reassignment first | Low |
| KEEP | Global customer id 3 | Has 4 users — needs reassignment first | Low |

### Phase 2: Fix Role Assignments (Requires migration)

| Action | User | Current | Target | Risk |
|--------|------|---------|--------|------|
| REASSIGN | 19 (T1) | global admin (id=14) | T1 admin (id=4) | Low |
| REASSIGN | 23 (T10) | global customer (id=3) | T10 customer (id=21) | Low |
| REASSIGN | 24 (T10) | T1 customer (id=5) | T10 customer (id=21) | **Medium — cross-tenant fix** |
| REASSIGN | 31 (T10) | global customer (id=3) | T10 customer (id=21) | Low |
| REASSIGN | 34 (T2) | global customer (id=3) | T2 customer (id=7) | Low |
| REMOVE | 11 (T1) | global customer (id=3) | Remove duplicate role | Low |

### Phase 3: Create Missing Tenant Admin Roles

| Tenant | Action | Method |
|--------|--------|--------|
| 11 | CREATE admin role | Clone permissions from global admin template |
| 12 | CREATE admin role | Clone permissions from global admin template |

### Phase 4: Fix Naming Inconsistencies (Requires discussion)

| Role | Tenant | Current Name | Proposed Name | Risk |
|------|--------|-------------|---------------|------|
| T2 admin | 2 | `admins` | `admin` | **MEDIUM** — affects User 13 (May May) |
| T2 custom | 2 | `Manager` | `manager` | **LOW** — affects User 38 (nna) |
| T15 custom | 15 | `Managers` | `manager` | **LOW** — affects User 40 (Store MMC) |

Renaming `admins` → `admin` is the highest priority because it's a system role name that bypasses protection.

### Phase 5: Prevent Future Duplicates

1. Add database-level partial unique index for global roles (MySQL 8.0.13+):
```sql
ALTER TABLE roles ADD UNIQUE INDEX roles_name_guard_global_unique (name, guard_name, (IFNULL(tenant_id, 0)));
```
Or use a unique index on `(COALESCE(tenant_id, 0), name, guard_name)` via generated column.

2. Fix `RoleAndPermissionSeeder` to use explicit `whereNull('tenant_id')` + `firstOrCreate` with all three fields including tenant_id:
```php
Role::firstOrCreate(
    ['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => null]
);
```

### Summary: Cleanup Candidates

| Category | Count | Records |
|----------|-------|---------|
| **DELETE CANDIDATE** | 7 | Global admin duplicates: id 2, 12, 18, 36, 37, 38 |
| **DELETE CANDIDATE** | 3 | Global customer duplicates: id 13, 15, 19 |
| **RENAME CANDIDATE** | 1 | `admins` → `admin` (T2, id=6) |
| **RENAME CANDIDATE** | 2 | `Manager` → `manager` (T2, id=28), `Managers` → `manager` (T15, id=31) |
| **CREATE** | 2 | Missing admin roles for T11, T12 |
| **REASSIGN** | 5 | Stale global assignments: Users 19, 23, 31, 34, 11 |
| **REASSIGN** | 1 | Cross-tenant fix: User 24 |
| **KEEP** | 3 | Global: superadmin (id=1). Templates: admin (id=14), customer (id=3) — temporarily |
| **KEEP** | 18 | All tenant-specific roles |

---

## 13. Risk Analysis

### Data Loss Risk — LOW

Duplicate global roles have zero users (except id=14 admin and id=3 customer). Deleting unused global roles is safe. The `role_has_permissions` and `model_has_roles` pivot tables cascade on role delete, so unused role deletion is clean.

### Permission Loss Risk — MEDIUM

If stale assignments (Users 19, 23, 31, 34) are cleaned without first reassigning, the users lose their roles. However, the roles they're on (global) and the tenant-specific equivalents have identical permission sets, so reassignment is safe.

The User 24 cross-tenant issue is more delicate: they have T1 customer permissions but belong to T10. Simply reassigning to T10 customer may change their permission set if the two differ.

### Owner Lockout Risk — LOW

No tenant owner is assigned a role that would be affected by cleanup. T11 and T12 have no admin role at all, so no lockout risk from cleanup — they're already locked out.

### Tenant Corruption Risk — MEDIUM

Renaming `admins` → `admin` for T2 (id=6) could break any code that references role_id=6 by ID. However, all code references roles by name string, not ID. The Spatie permission system uses the pivot table `model_has_roles.role_id` which is a FK, so renaming the role name doesn't affect assignments.

### Migration Complexity — HIGH

A comprehensive cleanup requires:
1. Multiple database queries (DELETE, UPDATE, INSERT)
2. Verification queries to ensure no breakage
3. A migration file or an Artisan command
4. Testing on a staging environment first
5. Coordination with any running instances (dev/staging/production)

---

## 14. Final Recommendations

### Immediate (Read-Only — No Changes):

1. Fix `RoleAndPermissionSeeder` to include `'tenant_id' => null` in `firstOrCreate` calls to prevent future duplicates
2. Add case-insensitive protection to `StoreRoleRequest` and `UpdateRoleRequest` to prevent `Admin`/`Superadmin`/`ADMIN` spoofing (normalize to lowercase before check)
3. Document that new permissions added to `PermissionSeeder` need manual propagation to existing tenant admin roles (until a `tenants:sync-permissions` command exists)

### Short-Term (After Audit Approval):

4. Run `php artisan tenants:sync-roles` to create missing admin roles for T11 and T12
5. Create a `tenants:sync-permissions` command to propagate new permissions to existing tenant-admin roles
6. Reassign stale global-role users to tenant-specific role equivalents

### Medium-Term (V3 Preparation):

7. Consolidate duplicate global roles via dedicated Artisan command
8. Rename `admins` → `admin` for T2, `Manager`/`Managers` → `manager` for T2/T15
9. Extract `TenantBootstrapService` to eliminate 3x role creation code duplication
10. Implement `admin.access` permission for granular admin access control

### Long-Term (V3):

11. Consider changing protection from name-string matching to a `is_protected` boolean column on the `roles` table
12. Implement standard role naming convention: lowercase with dashes for all tenant-created roles
13. Add unique constraint enforcement at the application level for global roles (prevent duplicate NULL tenant_id entries)
