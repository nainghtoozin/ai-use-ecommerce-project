# Phase 8 Full Regression Audit

**Date:** 2026-07-19  
**Scope:** Permission Architecture, Frontend Rendering, User Management, Backend Authorization, Owner/Staff Flows, Tenant Isolation  
**Mode:** Read-only audit — no code modified

---

## Executive Summary

Phase 8 implemented a custom permission architecture layered on top of Spatie Laravel-permission, with a bifurcated identity system (`User` legacy + `Account` new). The architecture introduces **tenant-scoped role resolution** through `TenantMembership` rather than Spatie's built-in teams feature. While functionally operational for basic flows, the implementation contains **14 distinct regressions** spanning permission bypass gaps, missing tenant scoping, duplicated logic, and inconsistent frontend/backend authorization.

**Critical issues:** Owner loses action buttons due to incomplete `getAllPermissions()` on legacy `User` model (REG-4) combined with `AdminSidebar`'s missing owner bypass (REG-1). Roles created by store admins leak globally due to missing `tenant_id` assignment (REG-2). The bifurcated identity system doubles code complexity across every permission-related file (REG-12).

---

## Architecture Diagram

```
 ┌─────────────────────────────────────────────────────┐
 │                    HTTP Request                       │
 └────────────────────┬────────────────────────────────┘
                      │
                      ▼
 ┌─────────────────────────────────────────────────────┐
 │           Middleware Stack (web.php)                  │
 │  auth → role:admin → tenant.valid → tenant.active    │
 │  ↑                    ↑                ↑              │
 │  CheckRole          CheckTenant     EnsureTenantIs    │
 │  Middleware          Member         Active            │
 └────────────────────┬────────────────────────────────┘
                      │
                      ▼
 ┌─────────────────────────────────────────────────────┐
 │           HandleInertiaRequests (share)              │
 │  auth.user ← IdentityProjection::forAuthenticatable  │
 │  .permissions ← $user->getAllPermissions()           │
 │  .is_owner ← $user->isOwner()                       │
 │  .is_superadmin ← $user->isSuperAdmin()              │
 │  .roles ← $user->getRoleNames()                     │
 └────────────────────┬────────────────────────────────┘
                      │
                      ▼
 ┌─────────────────────────────────────────────────────┐
 │              Inertia React Frontend                   │
 │                                                       │
 │  ┌─────────────────────┐   ┌──────────────────────┐  │
 │  │   usePermission()    │   │   AdminSidebar        │  │
 │  │   hook               │   │   can(perm) =         │  │
 │  │   can(perm):          │   │   userPermissions     │  │
 │  │   isSA→true           │   │   .includes(perm)     │  │
 │  │   isOwner→true         │   │   ← NO bypass!       │  │
 │  │   perm in set→true    │   │                      │  │
 │  └────────┬────────────┘   └──────────┬───────────┘  │
 │           │                            │              │
 │  ┌────────▼────────────────────────────▼───────────┐  │
 │  │     Page Components (Users, Roles, Products)     │  │
 │  │     can(perm) = isSA || isOwner || perm in set  │  │
 │  │     ← duplicated on every page                  │  │
 │  └────────────────────┬───────────────────────────┘  │
 │                       │                              │
 │  ┌────────────────────▼───────────────────────────┐  │
 │  │   Action Buttons / Dropdowns / Table Actions   │  │
 │  │   Hidden when can(perm) returns false          │  │
 │  └────────────────────────────────────────────────┘  │
 └─────────────────────────────────────────────────────┘
```

### Permission Resolution Flow

```
User (Account) → TenantMembership → Role → Permission
                                            ↑
User (legacy) ───────── Spatie model_has_roles → Permission
                                            ↑
SuperAdmin ──── global role in model_has_roles → Permission::all()
Owner ───────── is_owner flag on membership → Permission::all() (Account)
Owner ───────── is_owner column on users table → Spatie role permissions only (User legacy)
```

---

## Regression Inventory

### REG-1: AdminSidebar missing Owner/SuperAdmin permission bypass

| Field | Value |
|-------|-------|
| **Severity** | **Critical** |
| **Affected files** | `resources/js/Components/AdminSidebar.jsx:22` |
| **Root cause** | `const can = (perm) => userPermissions?.includes(perm)` does not check `isOwner` or `isSuperAdmin` |
| **Impact** | Sidebar items may be hidden from Owner/SuperAdmin if the `permissions` array is incomplete |
| **Recommended fix** | Change to `(perm) => isSuperAdmin || isOwner || userPermissions?.includes(perm)` |
| **Risk level** | High — causes UI regression where navigation items disappear |

### REG-2: Roles created without tenant_id (global leakage)

| Field | Value |
|-------|-------|
| **Severity** | **Critical** |
| **Affected files** | `app/Http/Controllers/Admin/RoleController.php:144-148` |
| **Root cause** | `Role::create(['name' => ..., 'guard_name' => 'web'])` omits `tenant_id`. Store admins create global roles visible to all tenants |
| **Impact** | Cross-tenant role visibility and potential permission leakage. Team members can be assigned roles from other tenants |
| **Recommended fix** | Set `'tenant_id' => Tenant::getCurrent()?->id` in role creation |
| **Risk level** | High — security boundary violation between tenants |

### REG-3: RolesIndex page has no Owner/SuperAdmin bypass

| Field | Value |
|-------|-------|
| **Severity** | **High** |
| **Affected files** | `resources/js/Pages/Admin/Roles/Index.jsx:8-11` |
| **Root cause** | Uses `userPermissions.includes('roles.create')` without `isOwner`/`isSuperAdmin` check |
| **Impact** | Owner cannot create/edit/delete roles if permissions array doesn't include granular permission strings |
| **Recommended fix** | Add `isSuperAdmin` and `isOwner` bypass like other pages do |
| **Risk level** | Medium — Owner still has backend access via direct URL entry |

### REG-4: Legacy User model getAllPermissions() lacks owner bypass

| Field | Value |
|-------|-------|
| **Severity** | **Critical** |
| **Affected files** | `app/Models/User.php` (no override) vs `app/Models/Account.php:498-511` |
| **Root cause** | `User::getAllPermissions()` uses Spatie default which only returns role-assigned permissions. Unlike `Account`, there's no `if ($this->isOwner()) return Permission::all()` override |
| **Impact** | Legacy owner (User with `is_owner=true`) does not receive all permissions. Backend `can()` checks fail, action buttons hidden, sidebar items missing |
| **Recommended fix** | Override `getAllPermissions()` on `User` model to return all permissions when `isOwner()` is true |
| **Risk level** | High — directly causes Owner to lose action buttons |

### REG-5: AuthorizationService::isOwner() ignores User model

| Field | Value |
|-------|-------|
| **Severity** | **High** |
| **Affected files** | `app/Services/AuthorizationService.php:82-87` |
| **Root cause** | `isOwner()` only checks `$user instanceof Account`, returns `false` for `User` |
| **Impact** | Middleware `CheckOwner` and policy `authorizeOwner()` fail for legacy User owners. Owner bypass in `AuthorizationService::can()` still works via `$user->can()` but direct `isOwner()` calls fail |
| **Recommended fix** | Add `if ($user instanceof User) return $user->isOwner()` check |
| **Risk level** | Medium — legacy User path is being migrated away |

### REG-6: CheckOwner middleware broken for legacy User owner

| Field | Value |
|-------|-------|
| **Severity** | **High** |
| **Affected files** | `app/Http/Middleware/CheckOwner.php` → `AuthorizationService::authorizeOwner()` |
| **Root cause** | Follows from REG-5 — `authorizeOwner()` calls `isOwner()` which returns false for User, then falls through to `isSuperAdmin()` check |
| **Impact** | Legacy store owner hits 403 on owner-only routes (if any exist) |
| **Recommended fix** | Same as REG-5 |
| **Risk level** | Medium |

### REG-7: TeamController lacks granular authorization

| Field | Value |
|-------|-------|
| **Severity** | **Medium** |
| **Affected files** | `app/Http/Controllers/Admin/TeamController.php:55-64` |
| **Root cause** | Methods like `index()`, `members()`, `invitations()` have no `can()` permission checks. Only `role:admin` middleware protects routes |
| **Impact** | Any admin-role user can view all team members, send invitations, suspend/remove members regardless of granular permission assignments |
| **Recommended fix** | Add `auth()->user()->can('users.view')` checks to TeamController methods |
| **Risk level** | Medium — route middleware provides coarse protection |

### REG-8: Duplicated permission logic across all frontend pages

| Field | Value |
|-------|-------|
| **Severity** | **Medium** |
| **Affected files** | 15+ page components (`resources/js/Pages/Admin/*/Index.jsx`) |
| **Root cause** | Each page defines `const can = (perm) => isSuperAdmin || isOwner || permissions.includes(perm)` instead of using `usePermission()` hook |
| **Impact** | Maintenance burden. Changes to bypass logic must be replicated across all pages. Some pages (RolesIndex) miss the bypass entirely |
| **Recommended fix** | Replace inline `can()` with `usePermission().can` or use `<Can>` component |
| **Risk level** | Low — functionally correct where implemented, but fragile |

### REG-9: RoleMiddleware is too permissive

| Field | Value |
|-------|-------|
| **Severity** | **Medium** |
| **Affected files** | `app/Http/Middleware/RoleMiddleware.php:22-24` |
| **Root cause** | Fallback allows any user with at least one permission through: `if ($role === 'admin' && $user->getAllPermissions()->isNotEmpty())` |
| **Impact** | A user with only `products.view` permission can access all admin routes. Granular controller checks still apply but route-level protection is weakened |
| **Recommended fix** | Remove the fallback or make it check specific admin-level permissions |
| **Risk level** | Medium — controller-level checks are the second line of defense |

### REG-10: Three inconsistent frontend permission mechanisms

| Field | Value |
|-------|-------|
| **Severity** | **Medium** |
| **Affected files** | `usePermission.js`, `AdminSidebar.jsx`, all `Pages/Admin/*.jsx` |
| **Root cause** | Three separate `can()` implementations with different bypass behavior |
| **Impact** | Inconsistent UI behavior — a user may see an item in one context but not another |
| **Recommended fix** | Consolidate to a single `usePermission` hook consumed everywhere |
| **Risk level** | Low-Medium |

### REG-11: Spatie teams feature disabled, custom implementation in use

| Field | Value |
|-------|-------|
| **Severity** | **Info** |
| **Affected files** | `config/permission.php:84` (`'teams' => false`) |
| **Root cause** | Teams disabled. The custom `TenantMembership`-based resolution duplicates Spatie's built-in team functionality |
| **Impact** | Extra maintenance burden. Custom code must handle all edge cases that Spatie teams would handle automatically |
| **Recommended fix** | Consider migrating to Spatie teams feature, or document why the custom approach is necessary |
| **Risk level** | Low — architectural debt |

### REG-12: `identity.use_accounts` bifurcated code paths

| Field | Value |
|-------|-------|
| **Severity** | **High** |
| **Affected files** | `Account.php`, `User.php`, `IdentityResolver.php`, `AuthorizationService.php`, `AdminUserController.php`, `TeamController.php`, `IdentityProjection.php` |
| **Root cause** | Every permission-related file has `if (config('identity.use_accounts'))` branches, effectively maintaining two parallel permission systems |
| **Impact** | ~30% code duplication across permission architecture. Increased bug surface area when one path is updated but not the other |
| **Recommended fix** | Complete migration to `Account` model, remove `User` legacy path, or create an abstraction layer |
| **Risk level** | Medium |

### REG-13: `tenant()` helper used when Spatie teams disabled

| Field | Value |
|-------|-------|
| **Severity** | **Medium** |
| **Affected files** | `app/Http/Requests/StoreRoleRequest.php:18` |
| **Root cause** | Uses `tenant()` helper function which is only available when Spatie teams feature is enabled (`'teams' => true`). This helper likely returns `null` |
| **Impact** | Role name uniqueness validation may not be tenant-scoped, allowing duplicate role names across tenants to collide or pass incorrectly |
| **Recommended fix** | Replace `tenant()` with `Tenant::getCurrent()` |
| **Risk level** | Medium |

### REG-14: Notifications link in sidebar has no permission guard

| Field | Value |
|-------|-------|
| **Severity** | **Low** |
| **Affected files** | `resources/js/Components/AdminSidebar.jsx:185` |
| **Root cause** | `{ label: 'Notifications', href: '/admin/notifications', icon: 'Bell' }` has no wrapping `can()` check |
| **Impact** | All admin users see Notifications link regardless of permissions |
| **Recommended fix** | Wrap with `can('notifications.view')` if such permission exists, or document as intentional |
| **Risk level** | Low |

---

## Priority Matrix

```
                    Impact
              High          Medium         Low
   ┌─────────────────────────────────────────────
 C │ REG-1 (Sidebar)    REG-2 (Roles)           
 r │ REG-4 (User perm)  REG-12 (bifurcation)    
 i │                                          
 t │                                          
 i ├─────────────────────────────────────────────
 c │ REG-5 (isOwner)    REG-9 (RoleMiddleware)  REG-14 (Notif link)
 a │ REG-6 (CheckOwner) REG-7 (Team auth)       
 l │                    REG-13 (tenant() helper)
   ├─────────────────────────────────────────────
 i │                    REG-3 (Roles page)       REG-8 (duplicated)
 t │                    REG-10 (3 mechanisms)   REG-11 (Spatie teams)
 y │                   
   └─────────────────────────────────────────────
```

---

## Risk Assessment

| Risk | Count | Details |
|------|-------|---------|
| **Critical** | 2 | REG-1: Sidebar owner bypass missing. REG-4: Legacy User permissions incomplete |
| **High** | 4 | REG-2: Role tenant leakage. REG-5/6: Owner check fails for User. REG-12: Bifurcated code |
| **Medium** | 6 | REG-3, REG-7, REG-8, REG-9, REG-10, REG-13 |
| **Low** | 2 | REG-11, REG-14 |

---

## Root Cause: Why Owner Loses Action Buttons

The Owner loses action buttons through a **compound failure** of multiple regressions:

1. **Backend:** For legacy `User` model (REG-4), `getAllPermissions()` does not return `Permission::all()` when `is_owner` is true. This means `auth.user.permissions` in Inertia shared props contains only Spatie-assigned role permissions, not all permissions.

2. **Frontend (AdminSidebar):** The sidebar's `can()` (REG-1) does `userPermissions?.includes(perm)` without checking `isOwner`. Since the permissions array from step 1 may lack certain permission strings, sidebar items are hidden.

3. **Frontend (Pages):** Most pages define `can(perm) => isSuperAdmin || isOwner || permissions.includes(perm)` which works correctly for page-level action buttons. However, any page that doesn't include the `isOwner` bypass (like RolesIndex, REG-3) will also hide buttons.

4. **For Account model:** `getAllPermissions()` correctly returns `Permission::all()` via `isOwner()` check. Sidebar items appear because the permissions array contains everything. However, if `use_accounts` config is toggled or during migration, the User path (step 1) is active.

**Exact trigger:** Legacy `User` with `is_owner=true` + `AdminSidebar.can()` without `isOwner` bypass.

---

## Permission Data Flow — Break Points

```
Backend Controller
  ↓
  can('users.view') → $user->can('users.view')
  ↓                        ↓
  User: Spatie check via model_has_roles
  Account: overridden → TenantMembership → Role → Permission
  ↓
  [OK if Account or User with correct role permissions]
  ↓
Middleware (HandleInertiaRequests)
  ↓
  IdentityProjection::forAuthenticatable($user)
  ↓
  $permissions = $user->getAllPermissions()->pluck('name')
  ↓
  ⚠ BREAK POINT 1 (REG-4): User::getAllPermissions() doesn't return all for owner
  ↓
Inertia shared props: auth.user.permissions
  ↓
React usePermission() hook → new Set(permissions)
  ↓
  can(perm): isSA→✓ | isOwner→✓ | permissions.has(perm)→✓
  ↓
  [OK — bypass covers incomplete array]
  ↓
AdminSidebar
  ↓
  can(perm): userPermissions?.includes(perm)
  ↓
  ⚠ BREAK POINT 2 (REG-1): No isOwner/isSuperAdmin bypass
  ↓
  Sidebar items hidden when permissions array incomplete
  ↓
Page Components
  ↓
  can(perm): isSA || isOwner || permissions.includes(perm)
  ↓
  ⚠ BREAK POINT 3 (REG-3, REG-8): Some pages miss isOwner check
  ↓
  Action buttons hidden
```

---

## Owner Regression — Detailed Analysis

### Account-based Owner (with `use_accounts=true`)
- ✅ `Account::isOwner()` returns true based on `TenantMembership.is_owner`
- ✅ `Account::getAllPermissions()` returns `Permission::all()` → all permissions in `auth.user.permissions`
- ✅ Page-level `can()`: `isOwner || permissions.includes(perm)` → true
- ✅ `usePermission().can()`: `isOwner → true` → true
- ⚠ REG-1: AdminSidebar `can()`: `permissions.includes(perm)` → true (because array has all perms)
- **Verdict:** Works, but fragile — depends on `getAllPermissions()` returning everything

### Legacy User-based Owner (with `use_accounts=false`)
- ✅ `User::isOwner()` returns `$this->is_owner` boolean
- ❌ REG-4: `User::getAllPermissions()` returns Spatie role permissions only
- → `auth.user.permissions` array is incomplete
- ❌ REG-1: AdminSidebar `can()`: `permissions.includes(perm)` may be false for missing permissions
- ✅ Page-level `can()`: `isOwner || permissions.includes(perm)` → true (isOwner saves it)
- **Verdict:** Sidebar broken; page action buttons work

### During Migration (incomplete identity switch)
- Most dangerous state — mixed code paths, unpredictable behavior

---

## Staff Authorization — Complete Flow

```
Invite Flow:
  TeamController::invite()
    → Validates email + role_id
    → Creates TeamInvitation (status=pending, expires=7d)
    → Sends email via TeamInvitationMail
    ❌ No permission check (REG-7) — relies on route middleware

Accept Flow:
  TeamInvitationController::accept()
    → Validates token
    → Creates Account if not exists
    → Creates TenantMembership with role_id
    → Sets membership status=active, is_owner=false
    → Redirects to store

Role Assignment:
  AdminUserController::store()
    → Creates Account (or reuses existing)
    → Creates TenantMembership with role_id
    → syncRoles([$data['role']]) → updates membership.role_id
    ⚠ syncRoles() on Account finds role by name + tenant_id

Permission Loading:
  Account::hasPermissionTo($permission)
    → Checks isSuperAdmin → true (bypass)
    → Gets current membership → TenantMembership
    → → hasPermission() checks is_owner → true (bypass)
    → → role->hasPermissionTo($ability)
    ✅ Correctly scoped

Policy Authorization:
  UserPolicy::viewAny()
    → AuthorizationService::can('users.view', $account)
    → → checks isSuperAdmin → true
    → → checks isOwner → true (for Account)
    → → $account->can($permission) → Account::hasPermissionTo()
    ✅ Correctly resolves through membership

Frontend Rendering:
  Team/Index.jsx
    → canManage = isOwner || permissions.includes('users.view')
    → Shows invite button, suspend/remove actions
    ✅ Correctly implemented
```

---

## Tenant Isolation Audit

| Concern | Status | Detail |
|---------|--------|--------|
| **Tenant membership** | ✅ Implemented | `TenantMembership` model with `tenant_id`, `account_id`, `role_id` |
| **Current tenant** | ✅ Implemented | `Tenant::getCurrent()` via `App::make('current.tenant')` |
| **Permission scope** | ✅ Account | Overridden `hasPermissionTo()` resolves through membership's role |
| **Permission scope** | ⚠ Legacy User | `User::can()` uses Spatie's global permission system, no tenant filtering |
| **Role scope** | ✅ With caveat | `Role::where('tenant_id', $tenantId)` filtered in queries |
| **Role scope** | ❌ REG-2 | `RoleController::store()` creates global roles (null tenant_id) |
| **Spatie integration** | ⚠ Custom | Teams disabled, custom membership-based resolution |
| **Cross-tenant guard** | ✅ | `CheckTenantAccess` middleware, `tenant.access` route middleware |
| **Data isolation** | ✅ | `TenantAware` trait, `TenantScope` global scope |

---

## Architecture Review

### Temporary Patches
1. **RoleMiddleware fallback** (`getAllPermissions()->isNotEmpty()`) — allows any permission-holding user through admin routes. Likely a temporary measure during migration.
2. **Inline `can()` on every page** — clearly intended to be replaced by `usePermission()` hook / `<Can>` component.

### Duplicated Permission Logic
1. `AuthorizationService` (backend) + `usePermission` hook (frontend) + per-page inline `can()` functions
2. `Account::hasRole()` + `Account::checkSpatieGlobalRoles()` + `CurrentRoleResolver::hasRole()`
3. Permission cache clearing in both `RoleObserver` and `PermissionObserver`

### Hardcoded Owner Checks
1. `AdminSidebar.jsx:181` — `auth?.user?.is_owner` bypass for Staff link
2. `Team/Index.jsx:9` — `isOwner || permissions.includes('users.view')`
3. `ProtectedOwnerTrait` (implicit, in `AdminUserController::protectOwner()`)

### Incorrect Permission Filters
1. `AdminSidebar.jsx:22` — missing `isOwner`/`isSuperAdmin` bypass
2. `Roles/Index.jsx:8-11` — missing `isOwner`/`isSuperAdmin` bypass

### Dead Code / Unused Helpers
1. `AuthorizationContext` — singleton registered but rarely consumed directly
2. `AuthorizationResolver` — wraps `$user->can()` with no additional logic
3. `ResolvesAuthorization` contract — may be unused beyond single implementation

### Technical Debt
1. Bifurcated identity system (`identity.use_accounts` config flag in ~15 files)
2. Spatie teams feature disabled with custom implementation
3. Three frontend permission mechanisms instead of one
4. `adminUrl()` helper added to bridge legacy → storefront admin URLs

---

## Phase Completion Estimate

| Component | Status | Completion |
|-----------|--------|------------|
| Permission Architecture | 🟡 In Progress | 65% |
| Backend Authorization | 🟡 In Progress | 70% |
| Frontend Permission Rendering | 🟡 In Progress | 60% |
| User Management Module | 🟢 Complete | 90% |
| Owner Bypass | 🔴 Broken | 40% |
| Staff Authorization | 🟡 In Progress | 75% |
| Tenant Isolation | 🟢 Complete | 85% |
| Permission Data Flow | 🟡 In Progress | 60% |
| Legacy → Account Migration | 🟡 In Progress | 50% |

**Overall Phase 8 Completion: ~65%**

| Metric | Value |
|--------|-------|
| Completed | 65% |
| Remaining | 20% |
| Blocked | 15% (needs REG-1 and REG-4 fixes first) |

---

## Recommended Fix Order (Execution Priority)

### Sprint 1 — Critical Path (Blockers)
1. **REG-4**: Add `getAllPermissions()` override to `User` model — return all permissions when `isOwner()` true
2. **REG-1**: Add `isSuperAdmin || isOwner` bypass to `AdminSidebar.can()`
3. **REG-2**: Add `tenant_id` to `RoleController::store()` role creation

### Sprint 2 — High Risk
4. **REG-5/REG-6**: Fix `AuthorizationService::isOwner()` to handle `User` model
5. **REG-3**: Add `isSuperAdmin`/`isOwner` bypass to `RolesIndex` page
6. **REG-13**: Replace `tenant()` with `Tenant::getCurrent()` in `StoreRoleRequest`

### Sprint 3 — Medium Priority
7. **REG-7**: Add granular `can()` checks to `TeamController` methods
8. **REG-9**: Tighten `RoleMiddleware` fallback or remove it
9. **REG-10**: Consolidate frontend permission logic into `usePermission` hook
10. **REG-8**: Refactor page components to use `usePermission` hook

### Sprint 4 — Cleanup
11. **REG-12**: Plan `User` → `Account` migration completion
12. **REG-11**: Evaluate Spatie teams vs custom approach
13. **REG-14**: Add permission guard to Notifications link

---

## Next Sprint Recommendation

**Focus on Sprint 1:** Fix REG-4, REG-1, REG-2 — these are the critical blockers that cause Owner to lose action buttons and roles to leak across tenants.

1. `User::getAllPermissions()` override — **1 file, ~5 lines**
2. `AdminSidebar.can()` bypass — **1 file, ~1 line**
3. `RoleController::store()` tenant_id — **1 file, ~1 line**
4. `RoleController::create()` permission scoping — **1 file, ~1 line**

These four changes resolve the two most visible regressions (Owner buttons, sidebar gaps) and fix the most dangerous security issue (cross-tenant role leakage).

Estimated effort: **2-3 hours** including testing.

---

## Report Location

```
docs/audits/phase8-regression-audit.md
```
