# V3-A4 Owner Role & Tenant Hierarchy Audit

## Status: Completed (Read-Only Audit)

---

## 1. Executive Summary

The current role architecture has **no dedicated Owner role**. Store owners are identified solely by the `is_owner` boolean flag on the `users` table and are assigned the `admin` role — the same role as any staff member. This creates a **critical protection gap**: if someone removes the `admin` role from the owner or removes all permissions, the owner loses access to their store. The RoleController protects `superadmin` and `admin` roles from deletion/edit, but `customer` and any custom roles have no backend edit protection. The frontend protects `superadmin` and `admin` from delete, and `customer` from delete only. No role has **edit protection** — permissions can be freely added/removed from any role.

---

## 2. Current Role Architecture

### Role Definitions

| Role | Scope | Created By | tenant_id | Protection |
|------|-------|-----------|-----------|------------|
| `superadmin` | Global (tenant_id=NULL) | RoleAndPermissionSeeder | NULL | Backend: edit+delete protected. Frontend: edit+delete hidden |
| `admin` | Per-tenant (tenant_id=T) | RoleAndPermissionSeeder + runtime controllers | Tenant ID | Backend: edit+delete protected. Frontend: edit+delete hidden |
| `customer` | Per-tenant (tenant_id=T) | RoleAndPermissionSeeder + runtime controllers | Tenant ID | Backend: NOT edit protected, delete protected. Frontend: delete hidden only |
| Custom roles (Manager, etc.) | Per-tenant | Admin creates via UI | Tenant ID | No protection |

### Role Creation Flow

```
Seeder (global templates):
  superadmin (global) → Permission::all()
  admin (global)      → 44 specific permissions
  customer (global)   → 4 specific permissions

Runtime (tenant creation):
  foreach ['admin', 'customer']:
    Role::create(name, tenant_id=$tenant->id)
    syncPermissions from global role template

Runtime (customer registration):
  Role::firstOrCreate('customer', tenant_id=$tenant->id)
  syncPermissions from global customer role (if new)
```

### Permission Assignment

| User Type | Role | Permissions | Source |
|-----------|------|-------------|--------|
| SuperAdmin | `superadmin` | ALL 96 | `Permission::all()` synced in seeder |
| Owner | `admin` | ALL 96 | `syncPermissions(Permission::all())` at creation |
| Staff (admin-created) | `admin` (or custom) | 44 (or custom) | From role assignment |
| Customer | `customer` | 4 | From role assignment |

---

## 3. Current Owner Architecture

### What Makes a User an Owner

The owner is identified by **two mechanisms**:

1. **`is_owner = true`** (boolean column on `users` table)
2. **`admin` role** (same role as any staff member)

Both are set during tenant creation in `CreateStoreController::store()` (lines 92-113) and `TenantController::store()` (lines 116-137):

```php
// Owner creation (CreateStoreController)
$admin = User::create([...]);                     // Line 92
$admin->tenant_id = $tenant->id;                 // Line 99
$admin->is_owner = true;                         // Line 100
$admin->save();                                   // Line 101
$admin->assignRole($adminRole);                  // Line 108 — admin role
$admin->syncPermissions(Permission::all());       // Line 111 — ALL permissions
```

### Owner Protections (Current)

| Protection | Location | Mechanism | Strength |
|-----------|----------|-----------|----------|
| Cannot be modified by non-superadmin | AdminUserController:33-41 | `protectOwner()` checks `$user->isOwner()` | Medium (boolean flag) |
| Cannot be deleted (last admin guard) | AdminUserController:310-318 | Checks admin count > 1 | Medium (role-based, not owner-specific) |
| Frontend suspend/ban/delete hidden | Users/Index.jsx:85,88,94 | `isSuperAdmin || !user.is_owner` | Low (frontend only) |
| Cannot change owner role by non-superadmin | AdminUserController:258-259 | `$user->isOwner() && $data['role'] !== 'admin'` | Medium |

### Owner Protection Gaps

| Gap | Risk | Impact |
|-----|------|--------|
| No dedicated `owner` role | **CRITICAL** | If `admin` role is removed, owner loses all access |
| Permission sync is explicit | **HIGH** | `syncPermissions(ALL)` at creation time. Future permission changes don't auto-sync to owner |
| Owner holds same role as staff | **MEDIUM** | Cannot distinguish owner from admin in role-based checks |
| No `is_owner` middleware | **MEDIUM** | No route-level protection for owner-only actions |
| Owner appears identical in role lists | **LOW** | Only distinguished by UI badge, not by role name |

---

## 4. Current Protection Mechanisms

### Backend Role Protection

| Protection | File | Lines | Mechanism |
|-----------|------|-------|-----------|
| `superadmin` cannot be edited | RoleController::edit() | 131-133 | `in_array($role->name, ['superadmin', 'admin'])` → abort(403) |
| `superadmin` cannot be updated | RoleController::update() | 149-152 | Same check → error redirect |
| `superadmin` cannot be deleted | RoleController::destroy() | 180-183 | Same check → error redirect |
| `admin` cannot be edited | RoleController::edit() | 131-133 | Same check as superadmin |
| `admin` cannot be updated | RoleController::update() | 149-152 | Same check as superadmin |
| `admin` cannot be deleted | RoleController::destroy() | 180-183 | Same check as superadmin |
| Role with users cannot be deleted | RoleController::destroy() | 189-192 | Checks `$role->users()->count() > 0` |
| Last superadmin deletion blocked | User::delete() | 68-74 | Counts remaining superadmins |
| Last admin deletion blocked | AdminUserController::destroy() | 310-318 | Counts admin-role users in tenant |

### Frontend Role Protection

| Protection | File | Lines | Mechanism |
|-----------|------|-------|-----------|
| `superadmin` edit button hidden | Roles/Index.jsx | 61 | `!['superadmin', 'admin'].includes(role.name)` |
| `superadmin` delete button hidden | Roles/Index.jsx | 65 | Same |
| `admin` edit button hidden | Roles/Index.jsx | 61 | Same |
| `admin` delete button hidden | Roles/Index.jsx | 65 | Same |
| `customer` delete blocked | Roles/Index.jsx | 18-22 | `confirmDelete()` checks protected roles list |
| Owner suspend/ban/delete hidden | Users/Index.jsx | 85-96 | `isSuperAdmin || !user.is_owner` |

### Protection Gaps

| Gap | Severity | Detail |
|-----|----------|--------|
| `customer` role has no backend edit protection | **MEDIUM** | Permissions can be freely changed on customer role |
| Custom roles (Manager, etc.) have no protection | **MEDIUM** | Any admin-created role is fully editable/deletable |
| No role has edit permission protection | **MEDIUM** | `syncPermissions([])` (empty) is not blocked for protected roles in update |
| No owner-specific role protection | **HIGH** | No `owner` role exists to protect |
| `admin` role not in frontend confirmDelete | **LOW** | Only listed in JSX conditional rendering, not in `protectedRoles` array for delete confirmation |

---

## 5. Hierarchy Analysis

### Current Hierarchy

```
PLATFORM (Global)
  └── superadmin    — Platform owner, manages all

TENANT (Per-Store)
  ├── admin         — Merchant owner + staff (shared role)
  └── customer      — Frontend customers

CUSTOM (Ad-hoc)
  ├── Manager       — Created in some tenants (naming inconsistency)
  ├── admins        — Typo variant (should be admin)
  └── Managers      — Plural variant
```

### Recommended Hierarchy (V3 Target)

```
PLATFORM (Global)
  └── superadmin    — No change

TENANT (Per-Store)
  ├── owner         — NEW: Protected, store owner
  ├── admin         — Protected: merchant staff with full access
  ├── manager       — NEW: Optional, limited permissions
  ├── staff         — NEW: Optional, basic permissions
  └── customer      — No change
```

### Issues with Current Hierarchy

1. **Owner and admin are conflated** — The store owner uses the `admin` role, making it impossible to distinguish owner actions from staff actions via role checks
2. **No hierarchy depth** — Only 2 tenant roles (admin, customer). No mid-tier roles for delegated management
3. **Ad-hoc roles with naming issues** — `Manager` (capitalized), `Managers` (plural), `admins` (typo) exist in some tenants
4. **RoleScope exemption** — `Role` model is exempt from `TenantScope`, relying on manual filtering in controllers. Missing a filter = cross-tenant data exposure

---

## 6. Owner Role Options

### Option A: Owner Role + All Permissions (Recommended)

```
Owner creation:
  ├── Role::create('owner', tenant_id, protected=true)
  ├── $owner->assignRole('owner')
  └── $owner->syncPermissions(Permission::all())
```

| Aspect | Assessment |
|--------|-----------|
| Implementation | Create `owner` role in seeder. Add to bootstrap. Assign during tenant creation |
| Protection | Add `owner` to RoleController protected list (edit+delete protected) |
| Permission sync | Auto-sync all permissions to owner role; make permission removal impossible |
| UI impact | Owner badge shown via role name (not just is_owner flag) |
| Impersonation | Update ImpersonationController to accept `owner` role (currently requires `admin`) |
| Complexity | **Low-Medium** |

### Option B: Admin Role + is_owner Flag (Current — Keep As-Is)

```
Owner creation:
  ├── $owner->assignRole('admin')
  ├── $owner->is_owner = true
  └── $owner->syncPermissions(Permission::all())
```

| Aspect | Assessment |
|--------|-----------|
| Implementation | Already works. No changes needed |
| Protection | Relies on `protectOwner()` and frontend conditional rendering |
| Permission sync | Relies on explicit sync (not role-based) |
| UI impact | Owner badge via `is_owner` flag |
| Role distinction | None — owner is indistinguishable from admin via role |
| Complexity | **None** (current state) |

### Option C: Owner Role + Admin Role (Hybrid)

```
Owner creation:
  ├── Role::create('owner', tenant_id, protected=true)
  ├── $owner->assignRole('owner')
  ├── $owner->assignRole('admin')
  └── $owner->syncPermissions(Permission::all())
```

| Aspect | Assessment |
|--------|-----------|
| Implementation | Owner has both roles. Redundant — owner+admin+all perms |
| Protection | Owner role protects identity. Admin role allows normal admin checks |
| Complexity | **Medium** — dual role assignment, permission inheritance confusion |

### Recommendation: Option A

**Option A** is the safest and cleanest architecture:
- Owner gets a **unique, protected role** with all permissions
- Owner is identifiable by role name (not just a boolean flag)
- RoleController can protect `owner` alongside `superadmin` and `admin`
- Tenant bootstrap creates the `owner` role just like it creates `admin` and `customer`
- `is_owner` flag can be kept as a secondary identifier for backward compatibility

---

## 7. Recommended Hierarchy

```
PLATFORM LAYER (Global, no tenant_id)
┌─────────────────────────────────────┐
│  superadmin                         │
│  ├── Manages all merchants          │
│  ├── Manages plans/subscriptions    │
│  ├── Can impersonate tenants        │
│  └── Protected: non-editable        │
└─────────────────────────────────────┘

TENANT LAYER (Per-Store, tenant_id=T)
┌─────────────────────────────────────┐
│  owner                              │
│  ├── Store owner (is_owner=true)    │
│  ├── Has ALL permissions            │
│  ├── Protected: non-editable        │
│  └── Cannot be deleted/downgraded   │
├─────────────────────────────────────┤
│  admin                              │
│  ├── Merchant staff                 │
│  ├── CRUD permissions (44 perms)    │
│  ├── Protected: deletable only      │
│  │   if 0 users assigned            │
│  └── Editable permissions           │
├─────────────────────────────────────┤
│  manager (OPTIONAL)                 │
│  ├── View + limited edit            │
│  ├── ~20 permissions                │
│  └── Fully editable/deletable       │
├─────────────────────────────────────┤
│  staff (OPTIONAL)                   │
│  ├── View-only permissions          │
│  ├── ~10 permissions                │
│  └── Fully editable/deletable       │
├─────────────────────────────────────┤
│  customer                           │
│  ├── Frontend users                 │
│  ├── 4 permissions                  │
│  ├── Protected: name non-editable   │
│  └── Deletable only if 0 users      │
└─────────────────────────────────────┘
```

---

## 8. Recommended Owner Model

### New Owner Characteristics

| Property | Value |
|----------|-------|
| Role name | `owner` |
| Scope | Per-tenant (tenant_id=T) |
| Permissions | ALL (auto-synced, immutable) |
| Backend: Editable | **NO** — RoleController blocks edit/update |
| Backend: Deletable | **NO** — RoleController blocks delete |
| Frontend: Edit/Delete | **NO** — Hidden in Roles/Index.jsx |
| Frontend: Badge | Shown via role name (plus existing is_owner flag) |
| Created by | TenantBootstrapService during tenant creation |
| Assigned to | Store owner user (is_owner=true) |
| Migration | New `owner` role seeded in RoleAndPermissionSeeder |

### Owner vs Admin Permission Difference

| Permission | Owner | Admin | Manager | Staff | Customer |
|-----------|-------|-------|---------|-------|----------|
| dashboard.view | ✅ | ✅ | ✅ | ✅ | ❌ |
| products.* | ALL | CRUD | View+edit | View only | ❌ |
| orders.* | ALL | Manage | View only | ❌ | Own only |
| users.* | ALL | CRUD | View only | ❌ | ❌ |
| roles.* | ALL | CRUD | ❌ | ❌ | ❌ |
| settings.* | ALL | Edit | ❌ | ❌ | ❌ |
| billing.view | ✅ | ✅ | ❌ | ❌ | ❌ |
| reports.* | ALL | View | View | ❌ | ❌ |

---

## 9. Protected Roles Matrix

### V3 Target Protection

| Role | Editable | Deletable | Permission Change | Tenant Scope |
|------|----------|-----------|-------------------|-------------|
| **superadmin** | ❌ Protected | ❌ Protected | ❌ Protected | Global |
| **owner** | ❌ Protected | ❌ Protected | ❌ Protected (all perms always) | Tenant |
| **admin** | ❌ Protected | ⚠️ Only if 0 users | ✅ Editable | Tenant |
| **manager** | ✅ Editable | ✅ Deletable | ✅ Editable | Tenant |
| **staff** | ✅ Editable | ✅ Deletable | ✅ Editable | Tenant |
| **customer** | ⚠️ Name protected | ⚠️ Only if 0 users | ✅ Editable | Tenant |

### Enforcement Locations

| Protection | Backend | Frontend |
|-----------|---------|----------|
| Edit blocked | RoleController::edit() (131-133), RoleController::update() (149-152) | Roles/Index.jsx (61) |
| Delete blocked | RoleController::destroy() (180-183) | Roles/Index.jsx (65) |
| Permission change blocked | Not currently implemented | Not currently implemented |
| Users-assigned delete blocked | RoleController::destroy() (189-192) | No frontend check |

### Required Backend Changes for V3

| Role | Edit Protection | Delete Protection | Permission Protection |
|------|----------------|------------------|---------------------|
| superadmin | Already protected (131-133) | Already protected (180-183) | Needs addition |
| owner | Add to protected list | Add to protected list | Add: auto-sync all perms, block removal |
| admin | Already protected | Already protected | Already unprotected (keep) |
| customer | Not currently protected | Already protected (delete) | Already unprotected (keep) |
| manager/staff | Not applicable | Not applicable | Not applicable |

---

## 10. TenantBootstrap Impact

### Current Bootstrap (3 controllers)

```php
// CreateStoreController::store()
foreach (['admin', 'customer'] as $roleName) {
    $role = Role::create([...]);
    $role->syncPermissions($globalRole->permissions);
}
$owner = User::create([...]);
$owner->assignRole($adminRole);              // ← Admin role (not owner)
$owner->syncPermissions(Permission::all());
```

### V3 Bootstrap (TenantBootstrapService)

```php
// TenantBootstrapService::bootstrap()
// Roles
$this->createRole($tenant, 'owner', Permission::all());     // NEW
$this->createRole($tenant, 'admin', $adminPermissions);     // 44 perms
$this->createRole($tenant, 'customer', $customerPermissions); // 4 perms
// Optional
$this->createRole($tenant, 'manager', $managerPermissions); // ~20 perms
$this->createRole($tenant, 'staff', $staffPermissions);     // ~10 perms

// Owner user
$owner = User::create([...]);
$owner->assignRole($ownerRole);              // ← Owner role (not admin)
$owner->syncPermissions(Permission::all());  // All permissions
```

### Key Changes

| Aspect | Current | V3 |
|--------|---------|----|
| Owner role | `admin` | `owner` |
| Owner permissions | All (explicit sync) | All (from role + sync) |
| `admin` role purpose | Merchant staff + owner | Merchant staff only |
| Bootstrap roles | admin, customer | owner, admin, (manager), (staff), customer |

---

## 11. Create Store Impact

### Changes Required in CreateStoreController

| Current Line | Current Code | V3 Change |
|-------------|-------------|-----------|
| 70-90 | Creates `admin` and `customer` roles | Also create `owner` role |
| 103-109 | `$admin->assignRole($adminRole)` | `$admin->assignRole($ownerRole)` |
| 111 | `$admin->syncPermissions(Permission::all())` | Same (or rely on role permissions) |

### Changes Required in TenantController

| Current Line | Current Code | V3 Change |
|-------------|-------------|-----------|
| 94-114 | Creates `admin` and `customer` roles | Also create `owner` role |
| 128-134 | `$admin->assignRole($adminRole)` | `$admin->assignRole($ownerRole)` |

### No Changes Required in RegisteredUserController

Customer registration flow is unchanged. The `customer` role bootstrap remains the same.

---

## 12. SuperAdmin Impact

### Impersonation Impact

Current impersonation flow (`ImpersonationController::start()`, line 45):
```php
if (!$user->hasRole('admin')) {
    return redirect()->back()->with('error', 'Target user does not have admin access.');
}
```

**Impact:** If owner gets a separate `owner` role (instead of `admin`), this check would block impersonation because the owner would no longer have the `admin` role. **Change required:**

```php
// Updated check for V3
if (!$user->hasRole('admin') && !$user->hasRole('owner')) {
    return redirect()->back()->with('error', 'Target user does not have admin or owner access.');
}
```

### RoleMiddleware Impact

Current bypass (`RoleMiddleware.php`, line 20):
```php
if ($role === 'admin' && $user->hasRole('superadmin')) {
    return $next($request);
}
```

**Impact:** No change. Owner would still access admin routes via `hasRole('admin')` — but only if they have the `admin` role. If `admin` role is removed from owner during migration, this middleware needs updating to also accept `owner` role:

```php
// Updated for V3 (if owner no longer has admin role)
if ($role === 'admin' && ($user->hasRole('superadmin') || $user->hasRole('owner'))) {
    return $next($request);
}
```

### SuperAdmin Panel Impact

No impact. SuperAdmin routes (`/superadmin/*`) use `role:superadmin` middleware. Owner role has no access to these routes. Tenant management, plan management, and subscription management remain exclusive to SuperAdmin.

---

## 13. Migration Complexity

### File Changes Required

| File | Change | Complexity |
|------|--------|------------|
| `database/seeders/RoleAndPermissionSeeder.php` | Add `owner` role definition with ALL permissions | **Low** |
| `app/Services/TenantBootstrapService.php` | Create `owner` role during bootstrap | **Low** (new file) |
| `app/Http/Controllers/CreateStoreController.php` | Assign `owner` role instead of `admin` | **Low** |
| `app/Http/Controllers/SuperAdmin/TenantController.php` | Same as above | **Low** |
| `app/Http/Controllers/Admin/RoleController.php` | Add `owner` to protected role lists (lines 131, 149, 180) | **Low** |
| `app/Http/Middleware/RoleMiddleware.php` | Add `owner` bypass for admin routes (optional) | **Low** |
| `app/Http/Controllers/SuperAdmin/ImpersonationController.php` | Add `owner` role check (line 45) | **Low** |
| `resources/js/Pages/Admin/Roles/Index.jsx` | Add `owner` to frontend protected lists (line 18, 61, 65) | **Low** |
| `resources/js/Pages/Admin/Users/Index.jsx` | Show owner badge via role (optional, already via is_owner) | **Low** |
| `app/Models/User.php` | Add `isOwnerByRole()` or update `isOwner()` | **Low** |
| `database/migrations/xxxx_add_owner_role.php` | Assign `owner` role to existing `is_owner=true` users | **Medium** |

### Database Changes

| Change | Required? | Type |
|--------|-----------|------|
| New `roles` table columns | **No** — existing `roles` table supports tenant-scoped roles | None |
| New `owner` role record | **Yes** — added via seeder + bootstrap | Data (not schema) |
| Migrate existing owners | **Yes** — assign `owner` role to existing `is_owner=true` users | Data migration |
| Remove `is_owner` column | **No** — keep as secondary identifier | None |
| New permission records | **No** — all 96 permissions already exist | None |

### Migration Steps

```
Phase 1: Add owner role to seeders
  └── RoleAndPermissionSeeder: add 'owner' with Permission::all()
  └── DatabaseSeeder: no change (already calls RoleAndPermissionSeeder)

Phase 2: Protect owner role
  └── RoleController: add 'owner' to protected lists
  └── Roles/Index.jsx: add 'owner' to frontend protected lists

Phase 3: Update bootstrap
  └── TenantBootstrapService: create owner role during store creation
  └── CreateStoreController: assign owner role to owner user
  └── TenantController: assign owner role to owner user

Phase 4: Data migration
  └── Migration: assign 'owner' role to existing is_owner=true users
  └── Keep 'admin' role on existing owners (backward compat)

Phase 5: Update impersonation
  └── ImpersonationController: accept owner role for impersonation
```

---

## 14. Risk Analysis

### Critical Risks

| # | Risk | Severity | Phase | Mitigation |
|---|------|----------|-------|------------|
| R1 | **Owner loses admin panel access** if `owner` role lacks admin route access | **CRITICAL** | 3 | Ensure RoleMiddleware accepts `owner` role for admin routes. Add bypass check. |
| R2 | **Existing owners lose permissions** if role migration misses users | **CRITICAL** | 4 | Migration must assign `owner` role to ALL existing `is_owner=true` users. Run count verification after migration. |
| R3 | **Impersonation broken** for existing owners if they no longer have `admin` role | **HIGH** | 5 | Update ImpersonationController before or simultaneously with role change. Document order-of-operations. |

### High Risks

| # | Risk | Severity | Phase | Mitigation |
|---|------|----------|-------|------------|
| R4 | **Owner gets `owner` role but loses `admin`-specific permissions** if admin role has permissions not duplicated in owner role | **HIGH** | 3 | Owner role should have `Permission::all()` same as current `syncPermissions(all)` behavior |
| R5 | **Staff management UIs check for `admin` role** and miss owner | **MEDIUM** | 3 | Audit all `hasRole('admin')` checks. Replace with `isAdmin()` which checks both admin and owner |
| R6 | **Permission propagation** — new permissions added after owner role creation not synced | **MEDIUM** | 3 | Owner role should auto-sync all permissions (like superadmin does). Add mechanism to keep it in sync |

### Medium Risks

| # | Risk | Severity | Phase | Mitigation |
|---|------|----------|-------|------------|
| R7 | **Custom roles (Manager, staff) may conflict** with new standard roles if tenants already use these names | **MEDIUM** | 2 | Document that existing custom roles are unaffected. New tenants get standard set. |
| R8 | **Frontend/backend protection mismatch** for owner role | **MEDIUM** | 2 | Ensure both RoleController and Roles/Index.jsx include `owner` |
| R9 | **RoleMiddleware permission bypass** allows non-owner/admins into admin panel (any user with any permission) | **MEDIUM** | — | Pre-existing issue. Needs `admin.access` permission to fix |

### Low Risks

| # | Risk | Severity | Phase | Mitigation |
|---|------|----------|-------|------------|
| R10 | **`is_owner` flag redundancy** — once owner role exists, flag is less critical but still useful | **LOW** | 4 | Keep flag. It serves as fast query filter and backward compatibility |
| R11 | **Role name case sensitivity** — `owner` vs `Owner` | **LOW** | 1 | Use lowercase `owner` consistent with existing naming |
| R12 | **TenantScope exemption for Role model** — manual filtering required | **LOW** | — | Pre-existing. Owner role inherits same behavior |

---

## 15. Backward Compatibility

| Scenario | Before | After | Status |
|----------|--------|-------|--------|
| Existing `is_owner=true` users | Have `admin` role | Keep `admin` role + add `owner` role | ✅ Compatible |
| New tenant creation | Owner gets `admin` role | Owner gets `owner` role | ⚠️ Change (intentional) |
| RoleController protection | Protects `superadmin`, `admin` | Also protects `owner` | ✅ Additive |
| Frontend role UI | Shows `admin` with owner badge | Shows `owner` with owner badge | ✅ Improvement |
| Impersonation | Targets `admin` role | Targets `admin` or `owner` role | ✅ Additive |
| Admin route access | Via `admin` role | Via `admin` or `owner` role | ✅ Additive |
| Permission checks | Via `admin` role permissions | Via `owner` role permissions | ✅ Equivalent |
| `isAdmin()` method | Checks `admin` or `superadmin` role | Should also check `owner` | ⚠️ Needs update |

---

## 16. Final Recommendation

### Recommended Role Hierarchy

```
superadmin (global, protected)
  └── owner (per-tenant, protected, all permissions)
        └── admin (per-tenant, protected, 44 permissions)
              └── manager (per-tenant, editable, ~20 permissions)
                    └── staff (per-tenant, editable, ~10 permissions)
                          └── customer (per-tenant, ~4 permissions)
```

### Recommended Owner Architecture

- **Create `owner` role** with all 96 permissions (same as superadmin but tenant-scoped)
- **Keep `is_owner` flag** as secondary identifier for fast queries and backward compatibility
- **Owner gets `owner` role** instead of `admin` role during bootstrap
- **Owner still gets `syncPermissions(ALL)`** for explicit permission guarantee
- **Protect `owner` role** in RoleController (edit, delete, permission change)
- **Protect `owner` role** in frontend Roles/Index.jsx
- **Update `User::isAdmin()`** to include `owner` role
- **Update `RoleMiddleware`** to accept `owner` for admin routes
- **Update `ImpersonationController`** to accept `owner` for impersonation

### Recommended Bootstrap Sequence

```php
TenantBootstrapService::bootstrap($tenant, $ownerData):
  1. Create roles:
     a. owner      → Permission::all()        [protected]
     b. admin      → 44 permissions           [protected]
     c. manager    → ~20 permissions          [editable] (optional)
     d. staff      → ~10 permissions          [editable] (optional)
     e. customer   → 4 permissions            [name protected]
  2. Create subscription
  3. Create owner user:
     a. is_owner = true
     b. assignRole('owner')
     c. syncPermissions(Permission::all())
  4. Create WebsiteInfo (default settings)
  5. Create default payment methods
  6. Create default categories
  7. Create default brands
  8. Create default units
  9. Dispatch TenantCreated event
```

### Recommended Permission Strategy

| Aspect | Option A (Recommended) | Option B | Option C |
|--------|----------------------|----------|----------|
| Owner permissions | All (role-based) | All (explicit sync) | All (role + sync) |
| Permission source | Owner role has all perms | `syncPermissions(ALL)` at creation | Both |
| Permission change handling | Auto-sync to owner role | Manual re-sync needed | Redundant |
| New permission propagation | Auto (owner role gets all) | Manual (`tenants:sync-permissions`) | Redundant |
| Implementation | **Safest** | Current approach | Over-engineered |

**Recommended: Option A** — Owner role with `Permission::all()`. Owner role permissions are immutable (cannot be changed via UI). New permissions auto-propagate because `owner` role always gets `Permission::all()`. The explicit `syncPermissions(ALL)` at creation time serves as a guarantee.

---

## Summary

| Metric | Value |
|--------|-------|
| File Created | `docs/v3-owner-role-hierarchy-audit.md` |
| Current Owner Model | `is_owner` boolean flag + `admin` role (no dedicated role) |
| Recommended Owner Model | `owner` role (all permissions, protected) + keep `is_owner` flag |
| Protected Roles | superadmin, owner, admin |
| Editable Roles | manager, staff, customer |
| TenantBootstrap Impact | Add `owner` role creation. Assign owner role to owner user |
| Migration Complexity | Low-Medium (10-12 files, 4 phases, ~2-3 days) |
| Risk Level | Medium — critical risks around impersonation and admin access (both mitigatable) |
| Recommended Next Step | Update `RoleAndPermissionSeeder` to include `owner` role definition, then implement TenantBootstrapService |
