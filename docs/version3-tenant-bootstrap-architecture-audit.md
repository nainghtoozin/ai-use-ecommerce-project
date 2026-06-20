# Version 3 — Tenant Bootstrap & Role Architecture Audit

## 1. Executive Summary

The application uses a **SaaS multi-tenant architecture** where each `Tenant` IS a store (1:1 mapping). Registration is public via `/create-store`. Upon registration, a `pending` tenant is created, then activated via email verification. There are currently **3 system roles** (`superadmin`, `admin`, `customer`) — but no dedicated `owner` role. The merchant owner is identified by an `is_owner` boolean flag on the `users` table and is assigned the `admin` role with all permissions synced.

**Critical gap:** The owner has no protected role. They hold the `admin` role — same as any staff member created after registration. There is nothing preventing accidental removal of the owner's permissions, role, or all admin users (including the owner).

---

## 2. Store Creation Flow

```
Browser: GET /create-store
  → CreateStoreController::index()
    → renders CreateStore.jsx (Inertia form)

Browser: POST /create-store [name, slug, description, domain, owner_name, owner_email, password]
  → CreateStoreController::store() [DB::transaction]
    │
    ├── 1. Create Tenant
    │     name: validated.name
    │     slug: validated.slug
    │     status: 'pending'
    │     store_url: '/store/' + slug
    │
    ├── 2. Create Subscription
    │     plan: Plan::free()
    │     status: 'pending'
    │     starts_at: null
    │     expires_at: null
    │
    ├── 3. Create per-tenant roles
    │     foreach ['admin', 'customer']:
    │       Role::create(name, guard_name='web', tenant_id)
    │       Copy permissions from global role (tenant_id=NULL)
    │
    ├── 4. Create Owner User
    │     name/email/password from validated
    │     tenant_id = tenant.id (set after create, as TenantAware auto-assigns current tenant)
    │     is_owner = true
    │     status = 'active'
    │
    ├── 5. Assign admin role + all permissions
    │     $admin->assignRole($adminRole)
    │     $admin->syncPermissions(Permission::all())
    │
    ├── 6. Return $admin from transaction
    │
    ├── 7. event(new Registered($admin))
    │     → Sends email verification
    │
    └── 8. Redirect to /store-registration/success?store={slug}
          → StoreRegistrationSuccess.jsx ("Verify your email")

Owner clicks email verification link
  → VerifyEmailController
    → dispatches Verified event
      → ActivateTenantOnVerified::handle()
        ├── tenant.status = 'active'
        ├── tenant.activated_at = now()
        ├── subscription.status = 'active'
        ├── subscription.starts_at = now()
        └── Sends WelcomeOwner notification

Owner visits /store/{slug}/onboarding/complete
  → CreateStoreController::onboarding()
    → OnboardingComplete.jsx ("Your Store is Live!")

Owner logs in at /store/{slug}/admin/login
  → Admin dashboard (full CRUD)
```

**Alternative: SuperAdmin creates a tenant**
```
SuperAdmin: POST /superadmin/tenants
  → TenantController::store()
    → Same flow but:
      - Tenant status: configurable (default 'active')
      - Subscription: immediately active
      - Admin user: email_verified_at = now() (no verification needed)
      - No Registered event dispatched
```

### Files Involved

| File | Role |
|------|------|
| `app/Http/Controllers/CreateStoreController.php` | Public store creation controller |
| `app/Http/Controllers/SuperAdmin/TenantController.php` | Superadmin tenant creation |
| `app\Http\Controllers\Auth\RegisteredUserController.php` | Customer registration (storefront) |
| `app\Models\Tenant.php` | Tenant model (acts as Store) |
| `app\Models\User.php` | User model with `is_owner` flag |
| `app\Models\Role.php` | Custom role model (extends Spatie) |
| `app\Models\Subscription.php` | Subscription model (TenantAware) |
| `app\Models\Plan.php` | Plan model (Free, Starter, Business) |
| `app\Models\WebsiteInfo.php` | Site settings (used for CreateStore form) |
| `app\Listeners\ActivateTenantOnVerified.php` | Tenant activation after email verification |
| `app\Services\StoreResolver.php` | Tenant resolution by slug (cached) |
| `app\Services\SubscriptionLimitService.php` | Staff/product/storage limit enforcement |
| `app\Services\SubscriptionExpiryService.php` | Subscription lifecycle automation |
| `app\Services\FeatureGate.php` | Plan feature gating (DEV_MODE=true) |
| `database/seeders/RoleAndPermissionSeeder.php` | Global roles + SuperAdmin user creation |
| `database/seeders/PermissionSeeder.php` | All permission records |
| `database/seeders/PlanSeeder.php` | Plan definitions |
| `resources/js/Pages/Public/CreateStore.jsx` | Frontend registration form |
| `resources/js/Pages/Public/StoreRegistrationSuccess.jsx` | Post-registration page |
| `resources/js/Pages/Public/OnboardingComplete.jsx` | Post-verification page |

---

## 3. Tenant Bootstrap Flow

There is **no dedicated bootstrap service or job**. Tenant initialization happens entirely within `CreateStoreController::store()` in a synchronous transaction.

### What IS bootstrapped (in order):
1. Tenant record created (status: `pending`)
2. Free plan subscription (status: `pending`)
3. Per-tenant `admin` role created (permissions copied from global `admin` role)
4. Per-tenant `customer` role created (permissions copied from global `customer` role)
5. Owner user created (`is_owner=true`, `tenant_id` set)
6. Owner assigned `admin` role + all permissions synced

### What is NOT bootstrapped:
- **No default settings** (WebsiteInfo, Settings records)
- **No default payment methods**
- **No default shipping zones/methods**
- **No default categories or products**
- **No notification preferences**
- **No default locations** (cities/townships)
- **No website info defaults** (site name, logo, etc.)

These are either:
- Global singletons shared across tenants (WebsiteInfo)
- Created manually by the merchant after login
- Seeded during `php artisan db:seed` (for the default tenant only)

### Tenant Activation (async, via event):
1. `Registered` event → sends verification email
2. `Verified` event → `ActivateTenantOnVerified` listener:
   - Tenant: `pending` → `active`
   - Subscription: `pending` → `active`

---

## 4. Default Roles Analysis

### Role 1: `superadmin`

| Property | Value |
|----------|-------|
| **Name** | `superadmin` |
| **Creation source** | `RoleAndPermissionSeeder` (line 83-87) |
| **Tenant scoped** | No (global, tenant_id=NULL) |
| **Permissions** | ALL 96 permissions |
| **Editable** | No protection on edit (can be renamed, permissions changed) |
| **Deletable** | Backend protects via `RoleController::destroy()` (line 196-199) |
| **Frontend protected** | Yes (`Roles/Index.jsx` line 21-26) |
| **Purpose** | Platform administrators, cross-tenant management |

### Role 2: `admin`

| Property | Value |
|----------|-------|
| **Name** | `admin` |
| **Creation source** | `RoleAndPermissionSeeder` (lines 11-66) + runtime in `CreateStoreController::store()` |
| **Tenant scoped** | Yes (per-tenant, tenant_id set) |
| **Permissions** | 44 specific admin permissions (view/create/update/delete for all modules) |
| **Editable** | No protection on edit (permissions can be added/removed) |
| **Deletable** | Backend protects via `RoleController::destroy()` (line 196-199) |
| **Frontend protected** | **NO** — only `superadmin` and `customer` are protected in frontend (missing `admin`) |
| **Purpose** | Merchant staff with admin-level access |

### Role 3: `customer`

| Property | Value |
|----------|-------|
| **Name** | `customer` |
| **Creation source** | `RoleAndPermissionSeeder` (lines 68-73) + runtime in `RegisteredUserController::store()` |
| **Tenant scoped** | Yes (per-tenant, tenant_id set) |
| **Permissions** | 4: `orders.view-own`, `orders.create`, `orders.cancel-own`, `payments.upload-proof` |
| **Editable** | No protection on edit |
| **Deletable** | Backend protects via `RoleController::destroy()` (line 196-199) |
| **Frontend protected** | Yes (`Roles/Index.jsx` line 21-26) |
| **Purpose** | Storefront customers |

### Roles Summary Table

| Role | Scoped | Seeder | Runtime | Backend Delete Protected | Frontend Delete Protected | Edit Protected |
|------|--------|--------|---------|--------------------------|--------------------------|---------------|
| `superadmin` | Global | ✓ | ✗ | ✓ | ✓ | ✗ |
| `admin` | Per-tenant | ✓ | ✓ | ✓ | ✗ | ✗ |
| `customer` | Per-tenant | ✓ | ✓ | ✓ | ✓ | ✗ |

---

## 5. Permission Assignment Analysis

### How permissions are initially created
**`PermissionSeeder`** creates all 96 permission records globally (no tenant scoping). Permissions are shared across all tenants.

### How permissions are assigned

**A. Global roles (seeder):**
```
superadmin: Permission::all() → syncPermissions
admin: 44 specific permissions → syncPermissions
customer: 4 specific permissions → syncPermissions
```

**B. Per-tenant roles (runtime):**
When a tenant is created, the global role's permissions are **copied** to the new tenant-specific role:
```php
$globalRole = Role::where('name', $roleName)->whereNull('tenant_id')->first();
$role->syncPermissions($globalRole->permissions);
```

**C. Owner user (runtime):**
```php
$admin->assignRole($adminRole);                 // admin role
$admin->syncPermissions(Permission::all());     // ALL permissions
```
The owner gets `admin` role + ALL permissions (not just the 44 from the admin role).

**D. Customer user (runtime):**
```php
$user->assignRole($customerRole);
```
Permissions come from the role assignment (4 perms).

**E. Admin-created staff users:**
`AdminUserController::store()` / `update()` → `$user->syncRoles([$role])`
Permissions come from the assigned role.

### Permission flow diagram
```
PermissionSeeder (96 global permissions)
  │
  ├──→ superadmin role (global) → has ALL permissions
  │
  ├──→ admin role (global) → has 44 permissions
  │     └──→ [per-tenant admin] → copies from global admin
  │
  ├──→ customer role (global) → has 4 permissions
  │     └──→ [per-tenant customer] → copies from global customer
  │
  └──→ Owner user → admin role + syncPermissions(ALL)
        → Admin-created staff → syncRoles([role])
```

---

## 6. Owner Role Analysis

### Current state
There is **no dedicated `owner` role** in the system. The merchant owner:

1. Gets the `admin` role (same as any staff member)
2. Gets **all** permissions synced directly (beyond what `admin` role provides)
3. Has `is_owner = true` flag on `users` table
4. Is protected from modification/deletion by `AdminUserController` (lines 33-41, 258-259)

### Owner protections (backend only)
| Protection | Location |
|-----------|----------|
| Owner cannot be deleted | `AdminUserController` line 310-318 (checks last admin, not specifically owner) |
| Owner role cannot be changed by non-superadmin | `AdminUserController` line 258-259 |
| Owner cannot be modified by non-superadmin | `AdminUserController::protectOwner()` lines 33-41 |
| Owner self-protection | None (owner can delete own account if last admin check passes) |

### Risks
1. **No protected role** — If someone removes `admin` role from the owner, they lose all access
2. **Permission reliant on syncPermissions(ALL)** — If someone resyncs permissions, owner only gets the `admin` role's 44 perms (loses dashboard.view, billing.*, etc.)
3. **No UI distinction** — Owner looks identical to any other admin user
4. **No inheritance** — No way to identify "this user owns the store" from role alone

---

## 7. Existing Protection Analysis

### Backend Protections

| Protection | File | Lines | Mechanism |
|-----------|------|-------|-----------|
| System role deletion blocked | `RoleController::destroy()` | 196-199 | Hardcoded check: `in_array($role->name, ['superadmin', 'admin', 'customer'])` |
| Role with assigned users blocked | `RoleController::destroy()` | 205-208 | `$role->users->count() > 0` → abort |
| Owner modification protected | `AdminUserController::protectOwner()` | 33-41 | Non-superadmin cannot modify `is_owner` users |
| Last admin deletion blocked | `AdminUserController::destroy()` | 310-318 | Count of admin-role users must stay > 1 |
| Self-deletion blocked | `AdminUserController::destroy()` | 320-323 | Cannot delete your own account |
| Last superadmin deletion blocked | `User::delete()` | 68-74 | Checks remaining superadmin count |
| Route-level tenant scoping | Various middleware | — | `tenant.binding`, `tenant.access`, `tenant.valid` |
| Subscription expiration | `SubscriptionExpiryService` | — | Auto-suspends tenant |

### Frontend Protections

| Protection | File | Lines | Notes |
|-----------|------|-------|-------|
| Delete role: `superadmin` blocked | `Roles/Index.jsx` | 20-26 | Alert shown, no API call made |
| Delete role: `customer` blocked | `Roles/Index.jsx` | 20-26 | Same check as superadmin |
| Delete role: `admin` NOT blocked | `Roles/Index.jsx` | — | **Frontend gap** — would send API call, backend rejects it |
| Edit role: none blocked | — | — | No frontend protection against editing any role |

### Gap: Frontend vs Backend Mismatch
- Frontend only protects `superadmin` and `customer` from deletion
- Backend protects `superadmin`, `admin`, AND `customer`
- If frontend attempts to delete `admin`, the API returns 403, but the user sees an error instead of an explanation

### Gap: No Edit Protection
- No role has frontend or backend protection preventing:
  - Renaming system roles
  - Removing all permissions from a system role
  - Making a role unusable

---

## 8. Multi-Tenant Safety Analysis

### Tenant Isolation Mechanisms

| Layer | Mechanism | Effectiveness |
|-------|-----------|---------------|
| **Database** | `tenant_id` column on most tables | Strong (all data queries filtered) |
| **Global Scope** | `TenantScope` auto-applies to all `TenantAware` models | Strong (automatic, can be bypassed via `withoutTenantScope()`) |
| **Route Binding** | `ValidateTenantBinding` middleware | Strong (validates model tenant_id matches) |
| **Access Control** | `CheckTenantAccess` middleware | Strong (logs out if tenant mismatch) |
| **Role Filtering** | Manual in controllers via `getTenantFilter()` | **Manual per controller** (Role model is exempt from TenantScope) |
| **Permission Records** | Not tenant-scoped (global) | **Weak** — all tenants share permissions. Deleting a permission affects all tenants |

### Role Isolation Details
- Role model EXEMPT from `TenantScope` (line 16-17 of `TenantScope.php`)
- Role query filtering done **manually** in each controller via `getTenantFilter()`
- Superadmin bypasses all tenant filtering (sees all roles across all tenants)
- Permission records are **fully global** — no `tenant_id` column
- Role-permission assignments (`role_has_permissions` table) have no tenant column

### User Isolation Details
- Users have `tenant_id` column (set on creation via `TenantAware` trait)
- User query filtering done **manually** via `getTenantFilter()` in `AdminUserController`
- Auth session is tenant-scoped (login within a store context)

### Cross-Tenant Risks
| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Permission modification affects all tenants | Low (only superadmin can manage permissions) | High | Superadmin access is tightly controlled |
| Role deletion affects only one tenant's copy | Low | Medium | Per-tenant roles have unique (tenant_id, name) |
| Data leak via shared permissions | Low | Medium | Controller-level permission checks are tenant-scoped |

---

## 9. Current Risks

### Critical Risks
| # | Risk | Description | Impact |
|---|------|-------------|--------|
| 1 | **Owner lockout** | No protected owner role. Admin-role staff can remove the owner's role or all permissions. | Owner loses access to their store |
| 2 | **System role permission removal** | No backend guard prevents removing all permissions from `admin` or `customer` roles | All staff/customers lose access |
| 3 | **Last admin deletion** | Protected only when checking role count, but multiple users could be `admin` | Owner cannot manage store |

### High Risks
| # | Risk | Description |
|---|------|-------------|
| 4 | **FeatureGate DEV_MODE=true** | All plan limits bypassed. Product types, staff limits, storage limits not enforced |
| 5 | **Frontend/backend protection mismatch** | `admin` role not protected in frontend deletion UI |
| 6 | **No bootstrap defaults** | New merchants get empty stores — no default categories, payment methods, or settings |

### Medium Risks
| # | Risk | Description |
|---|------|-------------|
| 7 | **Permission global scope** | All tenants share same permission records. A permission deleted by superadmin affects all tenants |
| 8 | **Synchronous bootstrap** | Store creation is fully synchronous. A failure mid-transaction leaves orphaned data |
| 9 | **RoleScope exemption** | Role model exempt from TenantScope — relies on manual filtering in controllers. Missing a filter = cross-tenant data leak |
| 10 | **Owner permission mismatch** | Owner gets `syncPermissions(ALL)`, but admin role has only 44 perms. Role/permission gap |

### Low Risks
| # | Risk | Description |
|---|------|-------------|
| 11 | **No events/observers** | Tenant creation has no events. Extending bootstrap logic requires modifying the controller |
| 12 | **Subscription starts null** | New subscriptions have `starts_at=null` until email verification |
| 13 | **Logged-out on tenant mismatch** | `CheckTenantAccess` logs out user silently — could confuse multi-store users |

---

## 10. Version 3 Feasibility Analysis

### Protected Owner Role — Feasibility: HIGH

| Requirement | Feasibility | Complexity | Impact |
|------------|-------------|------------|--------|
| Dedicated `owner` role auto-created during tenant bootstrap | **Easy** | Low | Modify `CreateStoreController::store()` to create `owner` role |
| Owner role non-editable | **Medium** | Medium | Add `owner` to backend protection list in `RoleController`, add frontend guard in `Roles/*.jsx` |
| Owner role non-deletable | **Easy** | Low | Add `owner` to existing protected roles list |
| Owner always has all permissions | **Medium** | Medium | Seed/logic: auto-sync all permissions to owner role; protect from permission removal |
| Owner identification | **Easy** | Low | Keep `is_owner` flag OR use role name `owner` |

### Default Manager Role — Feasibility: HIGH

| Requirement | Feasibility | Complexity |
|------------|-------------|------------|
| Role created during bootstrap | **Easy** | Low |
| Subset of admin permissions | **Easy** | Low |
| Editable/deletable | **Easy** | Already not protected |

### Default Staff Role — Feasibility: HIGH

Same as Manager role but with more restricted permissions. Could be a separate role or a lower tier.

### Default Customer Role — Feasibility: COMPLETED

Already exists as `customer` role (4 permissions). Created during bootstrap.

### Implementation Approach

The cleanest approach for V3:
1. **Create a new `owner` role** (per-tenant, protected) during bootstrap
2. **Modify bootstrap**: Owner gets `owner` role instead of `admin` role
3. **Protect `owner` role** in backend + frontend (non-editable, non-deletable, always has all perms)
4. **Migrate existing owners** from `is_owner` flag to `owner` role
5. **Optionally add `manager` and `staff` roles** for tiered access

---

## 11. Migration Impact Analysis

### Files That Would Change

| File | Change Required | Complexity |
|------|----------------|------------|
| `app/Http/Controllers/CreateStoreController.php` | Add `owner` role creation, assign instead of `admin` | Low |
| `app/Http/Controllers/Auth/RegisteredUserController.php` | Minor (customer role unchanged) | None |
| `app/Http/Controllers/Admin/RoleController.php` | Add `owner` to protected delete list | Low |
| `app/Http/Controllers/Admin/AdminUserController.php` | Protect owner from role change, prevent deleting owner role | Medium |
| `app/Http/Controllers/Admin/AdminUserController.php` | Store/update: prevent assigning `owner` role to non-owners | Medium |
| `app/Http/Middleware/RoleMiddleware.php` | Possibly add `owner` bypass (like superadmin) | Low |
| `database/seeders/RoleAndPermissionSeeder.php` | Add `owner` role seed definition | Low |
| `database/seeders/PermissionSeeder.php` | Potentially no change (permissions already exist) | None |
| `resources/js/Pages/Admin/Roles/Index.jsx` | Add `owner` to frontend protected list | Low |
| `resources/js/Pages/Admin/Roles/Show.jsx` | Add `owner` edit/delete guard | Low |
| `resources/js/Pages/Admin/Users/Index.jsx` | Owner identification in user list | Low |
| `resources/js/Pages/Admin/Users/Show.jsx` | Owner badge, protection | Low |
| `app/Models/User.php` | `isOwner()` could check role OR flag | Low |

### Database Changes Required
- None for the `owner` role — existing `roles` table supports tenant-scoped roles
- A migration to assign existing `is_owner=true` users to a new `owner` role (or just add protection for `is_owner` flag)

### Migration Complexity: LOW-MEDIUM

| Phase | Complexity | Effort |
|-------|-----------|--------|
| Phase 1: Add `owner` role to bootstrap | Low | 1 file |
| Phase 2: Protect `owner` role (backend) | Low | 2 files |
| Phase 3: Protect `owner` role (frontend) | Low | 2-3 files |
| Phase 4: Migrate existing owners | Low-Medium | 1 migration + testing |
| Phase 5: Add manager/staff roles | Low | Bootstrap + seed only |
| **Total** | **Low-Medium** | **~8-10 files** |

### Risk Level: LOW

The `owner` role is additive — it doesn't change the existing `admin` role. Existing tenants can keep working as-is. The migration can be opt-in or batch-processed.

---

## 12. Recommendations

### Immediate (Pre-V3)
1. **Fix frontend/backend mismatch**: Add `admin` to frontend protected role list in `Roles/Index.jsx:20-26`
2. **Add edit protection for system roles**: Prevent permission removal from `superadmin`, `admin`, `customer` roles via backend validation
3. **Document FeatureGate DEV_MODE**: Add clear TODO/issue for re-enabling plan enforcement

### V3 Architecture
1. **Create dedicated `owner` role** with ALL permissions, auto-assigned during tenant bootstrap
2. **Protect `owner` role** (non-editable, non-deletable, always has all permissions)
3. **Keep `is_owner` flag** as secondary identifier (consistent with existing code)
4. **Owner bootstrap flow**: Owner gets `owner` role + all permissions (instead of `admin` + syncPermissions)
5. **Add permission seeding for `owner` role** in `RoleAndPermissionSeeder`
6. **Optionally add `manager` and `staff` roles** with pre-defined permission sets

### V3 Recommended Tenant Bootstrap
```
CreateStoreController::store():
  1. Create Tenant (pending)
  2. Create Subscription (pending)
  3. Create per-tenant roles:
     ├── owner     (protected, all permissions)
     ├── admin     (existing, 44 permissions)
     ├── manager   (new, subset of admin perms)
     └── customer  (existing, 4 permissions)
  4. Create Owner User (is_owner=true)
  5. Assign owner role to owner
  6. Dispatch Registered event
```

---

## 13. Proposed Version 3 Architecture

```
┌─────────────────────────────────────────────────────┐
│                  TENANT CREATION                      │
├─────────────────────────────────────────────────────┤
│                                                       │
│  CreateTenant                                         │
│    ├── Tenant (status: pending)                       │
│    ├── Subscription (status: pending, plan: free)     │
│    ├── Roles:                                         │
│    │   ├── owner     ──── ALL permissions             │
│    │   ├── admin     ──── 44 permissions (existing)   │
│    │   ├── manager   ──── 20 permissions (new)        │
│    │   └── customer  ──── 4 permissions (existing)    │
│    └── Owner User:                                    │
│        ├── is_owner = true                            │
│        ├── role: owner (NOT admin)                    │
│        └── all permissions synced                     │
│                                                       │
└─────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────┐
│                  ROLE PROTECTION                      │
├─────────────────────────────────────────────────────┤
│                                                       │
│  superadmin  →  Non-editable, Non-deletable           │
│  owner       →  Non-editable, Non-deletable           │
│  admin       →  Deletable only if no users assigned   │
│  manager     →  Fully editable/deletable              │
│  customer    →  Deletable only if no users assigned   │
│                                                       │
└─────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────┐
│              PERMISSION HIERARCHY                     │
├─────────────────────────────────────────────────────┤
│                                                       │
│  owner       →  ALL permissions (auto-synced)         │
│  superadmin  →  ALL permissions (global)              │
│  admin       →  Module CRUD (44 perms)                │
│  manager     →  View + limited edit (e.g., 20 perms)  │
│  customer    →  Order own + payment proof (4 perms)   │
│                                                       │
└─────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────┐
│               TENANT BOOTSTRAP COMPLETION             │
├─────────────────────────────────────────────────────┤
│                                                       │
│  After email verification:                            │
│    1. Tenant: pending → active                        │
│    2. Subscription: pending → active                  │
│    3. WelcomeOwner notification                       │
│                                                       │
│  Future: Optionally bootstrap defaults:               │
│    1. Default payment method (COD)                    │
│    2. Default shipping settings                       │
│    3. Default notification preferences                │
│    4. Default website info                            │
│                                                       │
└─────────────────────────────────────────────────────┘
```
