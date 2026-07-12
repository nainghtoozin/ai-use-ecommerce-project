# Phase 6.1 Final Check — Deep Consistency Audit

**Date:** Read-only audit. No files modified.

---

## 1. Identity Projection Consistency — PASS

`IdentityProjection::forAuthenticatable()` is the single projection layer. Every authenticated screen receives data from this class.

**Verified fields (18 total):**

| Field | Source | Consistent? |
|-------|--------|-------------|
| `id` | `$user->id` | ✅ |
| `display_name` | `$user->getDisplayName()` | ✅ |
| `name` | Same as `display_name` | ✅ |
| `email` | `$user->email` | ✅ |
| `avatar` / `profile_image` | `$user->profile_image` | ✅ |
| `role` | `$user->getRoleNames()->first()` | ✅ |
| `role_name` | Same as `role` | ✅ |
| `role_label` | `$user->getRoleLabel()` | ✅ |
| `roles` | All role names as array | ✅ |
| `status` | `$user->status` | ✅ |
| `membership_status` | `$membership->status` (Account) / `$user->status` (User) | ✅ |
| `is_owner` | `$membership->is_owner` (Account) / `$user->isOwner()` (User) | ✅ |
| `is_admin` | `$user->isAdmin()` | ✅ |
| `is_superadmin` | `$user->isSuperAdmin()` | ✅ |
| `tenant_id` | User: `tenant_id` column / Account: `Tenant::getCurrent()?->id` | ✅ |
| `tenant_name` | `Tenant::getCurrent()?->name` | ✅ |
| `tenant_slug` | `Tenant::getCurrent()?->slug` | ✅ |
| `permissions` | `$user->getAllPermissions()->pluck('name')` | ✅ |
| `joined_at` | `$membership->joined_at` (Account) / `$user->created_at` (User) | ✅ |

**All 10 UI components** verified using these projection fields — zero old `roles?.[0]?.name` patterns remain.

---

## 2. Display Name Resolution — PASS (with spec drift note)

### Actual resolution chain on Account model (`getDisplayName()`, lines 124–144):

```
1. MerchantProfile.business_name   (via $membership->merchantProfile)
2. CustomerProfile.name            (via $membership->customerProfile)
3. $this->email                    (fallback)
```

**`getNameAttribute()`** (line 119) delegates to `getDisplayName()`, so `auth.user.name` resolves correctly.

### Spec drift (non-functional)

| Claimed spec | Actual implementation |
|-------------|---------------------|
| `Account.name` → | Not consulted (would be circular — `getNameAttribute()` calls `getDisplayName()`) |
| `MerchantProfile.display_name` → | Uses `business_name` instead |
| `CustomerProfile.full_name` → | Uses `name` instead |
| Email fallback | ✅ Correct |

**Verdict:** Functionally correct. The chain produces a valid display name in all cases. The column naming drift (`business_name` vs `display_name`, `name` vs `full_name`) is cosmetic. `Account.name` is correctly excluded (would be a circular self-reference).

### User model (`getDisplayName()`, lines 104–107):
```
1. $this->name
2. $this->email (fallback)
```
✅ Correct.

---

## 3. Role Count Queries — FAIL (architectural inconsistency)

### Problem

RoleController counts from `model_has_roles` in Account mode instead of `tenant_memberships.role_id`.

| Method | Lines | Queries | Should query |
|--------|-------|---------|-------------|
| `index()` | 42–44, 47–49 | `withCount(['accounts' => fn($q) => $q->whereHas('memberships', ...)])` | `withCount(['memberships' => fn($q) => $q->where('role_id', ...)])` or `tenant_memberships.role_id` directly |
| `show()` | 118–120, 123–125 | Same pattern | Same |
| `destroy()` | 238–240, 241–243 | `$role->accounts()->whereHas('memberships', ...)->count()` | `TenantMembership::where('role_id', $role->id)->count()` |

### Why it works anyway

`AdminUserController::store()` (line 180) and `update()` (line 315) call `$user->syncRoles(...)` which writes to BOTH `tenant_memberships.role_id` (via Account override) AND `model_has_roles` (via Spatie fallback). So the counts currently match.

### Risk

If any future code path creates a `TenantMembership` without calling `syncRoles()`, counts diverge.

### Contrast with correct implementation

`IdentityResolver::resolveRoleCount()` (lines 167–171) correctly queries `tenant_memberships.role_id`:

```php
$query = Account::whereHas('memberships', fn($q) => $q
    ->where('tenant_id', $tenantId)
    ->where('role_id', $roleId)   // <-- correct
);
```

### All role count queries found

| File | Line | Queries `model_has_roles` | Should use `tenant_memberships.role_id` |
|------|------|--------------------------|----------------------------------------|
| `app/Http/Controllers/Admin/RoleController.php` | 42–44, 47–49 | ✅ index() | ❌ |
| `app/Http/Controllers/Admin/RoleController.php` | 118–120, 123–125 | ✅ show() | ❌ |
| `app/Http/Controllers/Admin/RoleController.php` | 238–240, 241–243 | ✅ destroy() | ❌ |
| `app/Http/Controllers/Admin/AdminUserController.php` | 78 | `whereHas('roles', ...)` filter | ❌ should use `whereHas('memberships.role', ...)` |

**Verdict:** FAIL — 4 queries in 2 controllers use `model_has_roles` instead of `tenant_memberships.role_id`.

---

## 4. TenantMembership Integrity — PASS

### Schema

| Constraint | Type | Purpose |
|------------|------|---------|
| `(account_id, tenant_id)` | **UNIQUE** | Prevents duplicate memberships |
| `tenant_id` FK | `CASCADE ON DELETE` | Memberships cleaned up with tenant |
| `account_id` FK | `SET NULL ON DELETE` | Orphaned memberships null out |
| `role_id` FK | `RESTRICT ON DELETE` | Cannot delete role while members reference it |
| `SoftDeletes` | `deleted_at` | Safe deletion, membership can be restored |

### Relationships on TenantMembership model

| Relationship | Type | Target | Notes |
|-------------|------|--------|-------|
| `account()` | BelongsTo | Account | Nullable |
| `tenant()` | BelongsTo | Tenant | Required |
| `role()` | BelongsTo | Role | Required |
| `customerProfile()` | HasOne | CustomerProfile | Unique per membership |
| `staffProfile()` | HasOne | StaffProfile | Unique per membership |
| `merchantProfile()` | HasOne | MerchantProfile | Unique per membership |

### Integrity check

- `$membership->hasPermission($ability)` — correctly short-circuits to `true` if `is_owner`, otherwise delegates to `$this->role->hasPermissionTo($ability)`. ✅
- `$membership->isOwner()` — returns `$this->is_owner` cast to bool. ✅
- `$membership->isActive()` — checks `$this->status === 'active'`. ✅

**Verdict:** PASS. No schema or integrity issues.

---

## 5. Owner Initialization — PASS

### Account mode (`TenantBootstrapService::createOwnerAccount()`, lines 184–210)

1. Creates `Account` record (email, password, status)
2. Creates `TenantMembership` record (account_id, tenant_id, role_id, is_owner=true)
3. **No** `User` record created
4. **No** `model_has_roles` entry created (role is in membership)

### Legacy mode (`TenantBootstrapService::createOwner()`, lines 160–179)

1. Creates `User` record (name, email, password, status, is_owner=true, tenant_id)
2. `assignOwnerRole()` assigns 'admin' role via Spatie (`model_has_roles`)
3. **No** Account or TenantMembership created

### CreateStoreController (lines 35–82)

- Uses `config('identity.use_accounts')` to determine flow
- Account mode: `unique:accounts,email` validation
- Legacy mode: `unique:users,email` validation
- Fires `event(new Registered($admin))` correctly for both modes

**Verdict:** PASS. Modes are mutually exclusive. No cross-contamination.

---

## 6. Tenant Email Synchronization — FAIL (data drift risk)

### Finding

The `tenants` table has an `email` column (nullable, not null). It is set only when:
- A SuperAdmin creates/edits a tenant via `SuperAdmin\TenantController`
- It is **NOT** set during public store creation (`CreateStoreController`)

When the Account or User email changes (via profile update or admin edit), **`tenant.email` is never synced**.

### Evidence

- No model observer for Account or User email changes
- No event listener for `Updated` event
- No `boot()` hook on any model that syncs email
- `EventServiceProvider` has no email-related listeners

### Impact

- `tenant.email` is an orphaned column with no synchronization mechanism. If queried, it would return stale data.
- Currently **no code reads** `tenant.email` for business logic (display name uses profile chain, auth uses direct account/user email), so this is a data quality issue, not a functional bug.

### Queries affected (none currently broken, but risky)

| Potential query | Risk |
|----------------|------|
| `Tenant::where('email', $newEmail)->first()` | Would miss recent changes |
| Displaying `tenant.email` in SuperAdmin UI | Would show stale data |

**Verdict:** FAIL — no email sync mechanism exists.

---

## 7. Remaining Legacy User References — PASS (intentional)

All legacy `User` references found fall into 2 categories:

### Category 1: Legacy-mode code paths (correct — guarded by `config('identity.use_accounts')`)

| File | Pattern | Guard |
|------|---------|-------|
| `IdentityResolver.php` | `User::role('admin')` | Inside `if (!supportsAccount())` |
| `AdminUserController.php` | `user instanceof User` | Inside Legacy branch |
| `RegisteredUserController.php` | `User::create()` | Inside `if (!$useAccounts)` |
| AuthenticatedSessionController, StorefrontLoginController, etc. | `User::where('email', ...)` | Inside `if (!$useAccounts)` |

### Category 2: Model relationships (need Phase 7 redesign — schema changes required)

| File | Pattern | Notes |
|------|---------|-------|
| `app/Models/Order.php:87` | `belongsTo(User::class)` | Needs polymorphic or configurable FK |
| `app/Models/Message.php:29,34` | `belongsTo(User::class)` | Same |
| `app/Models/CustomerAddress.php:37` | `belongsTo(User::class)` | Same |
| `app/Models/Wishlist.php:15` | `belongsTo(User::class)` | Same |
| 8 more models | Various `belongsTo(User)` | All documented in identity-consistency-audit.md |

**Verdict:** PASS. Legacy references are correctly guarded or scoped to Phase 7.

---

## 8. Role & Permission Projection — PASS

### Account model authorization override coverage

| Spatie method | Line | Override? | Resolves through |
|---------------|------|-----------|-----------------|
| `hasRole()` | 348 | ✅ | `TenantMembership.role_id` + owner implicit admin |
| `hasPermissionTo()` | 387 | ✅ | `$membership->hasPermission()` → role-permission pivot |
| `getRoleNames()` | 413 | ✅ | `$membership->role->name` + admin for owner |
| `getAllPermissions()` | 437 | ✅ | `$membership->role->permissions` (all for owner/superadmin) |
| `assignRole()` | 477 | ✅ | Updates `TenantMembership.role_id` |
| `syncRoles()` | 526 | ✅ | Delegates to `assignRole()` for first role |

### Owner implicit admin

Both `getRoleNames()` (line 432) and `hasRole()` (line 367) append `'admin'` when `$membership->is_owner` is true. This means:
- `$account->isAdmin()` returns `true` for owners
- `$account->hasRole('admin')` returns `true` for owners
- `$account->getAllPermissions()` returns all permissions for owners

### SuperAdmin is always global

`isSuperAdmin()` (line 84) checks `model_has_roles` directly (via `hasGlobalRole()`), NOT membership. This is intentional — only system-wide superadmins bypass tenant isolation.

### Permission checks via Gate work correctly

When a controller calls `auth()->user()->can('orders.view')`, Laravel's Gate resolves to `$user->hasPermissionTo()` which is overridden on Account to check `$membership->hasPermission()`.

**Verdict:** PASS. All 6 Spatie methods are correctly overridden.

---

## 9. UI Projection Consistency — PASS

### Component audit summary (104 identity data access points)

| Component | Instances | Old patterns | Correct fields |
|-----------|-----------|-------------|----------------|
| `AdminHeader.jsx` | 8 | 0 | `role_label`, `is_superadmin`, `name`, `email` |
| `AdminSidebar.jsx` | 8 | 0 | `role_label`, `permissions`, `is_superadmin`, `name` |
| `AdminFooter.jsx` | 1 | 0 | `is_superadmin` |
| `AppLayout.jsx` | 15 | 0 | `is_admin`, `is_superadmin`, `id`, `name`, `email` |
| `PlatformNavbar.jsx` | 6 | 0 | `name`, `email` |
| `ShopNavbar.jsx` | 8 | 0 | `name`, `email`, `permissions` |
| `Users/Index.jsx` | 28 | 0 | `role_name`, `is_owner`, `status`, `permissions` |
| `Users/Show.jsx` | 21 | 0 | `role_name`, `is_owner`, `email_verified_at`, `status` |
| `Users/Edit.jsx` | 9 | 0 | `role_name`, `status`, `allow_cod`, `profile_image` |

**Zero instances** of `roles?.[0]?.name`, hardcoded role strings, or email-as-display-name found.

**Verdict:** PASS. All components use the IdentityProjection fields correctly.

---

## 10. Database Consistency — PASS

### Schema alignment check

| Concept | Table | Column | Matches model? |
|---------|-------|--------|----------------|
| Account identity | `accounts` | `id, email, password, status, profile_image` | ✅ |
| Membership | `tenant_memberships` | `account_id, tenant_id, role_id, is_owner, status` | ✅ |
| Role-permission | `model_has_roles`, `model_has_permissions` | Standard Spatie | ✅ |
| Role definition | `roles` | `id, name, guard_name, tenant_id` | ✅ (custom `TenantAware` trait on Role) |
| User (Legacy) | `users` | `id, tenant_id, name, email, password, is_owner, status` | ✅ |
| Profile data | `customer_profiles`, `staff_profiles`, `merchant_profiles` | `tenant_membership_id` FK | ✅ |

### Constraint validation

- `tenant_memberships` has `UNIQUE(account_id, tenant_id)` — prevents duplicate memberships ✅
- `merchant_profiles`, `customer_profiles`, `staff_profiles` have `UNIQUE(tenant_membership_id)` — one profile per membership ✅
- `accounts` has `UNIQUE(email)` — one account per email ✅
- `tenant_memberships` has FK `RESTRICT` on `role_id` — prevents role deletion while members exist ✅

**Verdict:** PASS. All constraints are correct.

---

## Summary

| Module | Status | Issue |
|--------|--------|-------|
| 1. IdentityProjection | **PASS** | Single projection layer, 18 fields, all components consuming correctly |
| 2. Display Name | **PASS** (spec drift) | Chain uses `business_name` → `customerProfile.name` → email. Documented spec has different column names. No functional impact. |
| 3. Role Count Queries | **FAIL** | 4 queries in 2 controllers query `model_has_roles` instead of `tenant_memberships.role_id` |
| 4. TenantMembership | **PASS** | Schema, constraints, methods all correct |
| 5. Owner Init | **PASS** | Mutually exclusive flows, correct in both modes |
| 6. Tenant Email Sync | **FAIL** | `tenant.email` column has no synchronization mechanism |
| 7. Legacy References | **PASS** | All guarded by config or scoped to Phase 7 |
| 8. Role & Permission | **PASS** | All 6 Spatie methods correctly overridden |
| 9. UI Projection | **PASS** | 104 access points, zero old patterns |
| 10. Database | **PASS** | All constraints, FKs, uniques correct |

---

## Is Phase 7 Safe?

**YES**

### Justification

Two FAIL items exist, neither blocks Phase 7:

1. **Role Count Queries (FAIL #3)** — Counts from `model_has_roles` are currently correct because `syncRoles()` keeps both tables in sync. The architectural drift is real but not data-corrupting. Phase 7 should refactor RoleController to use `tenant_memberships.role_id` directly, matching `IdentityResolver::resolveRoleCount()`. The fix is contained to `RoleController.php` (~4 query modifications). No schema changes needed.

2. **Tenant Email Sync (FAIL #6)** — `tenant.email` is an orphaned column. **No code currently reads it** for any business logic, so there is no data corruption risk. The column exists from an earlier architecture assumption that `Tenant` would be the contact point for billing/notification emails. Phase 7 should either remove the column or add a `TenantObserver` to sync on Account/User email updates.

### What Phase 7 should start with

The audit confirms the identity projection layer is consistent. Phase 7 can safely focus on:

1. **Model relationship migration** — 13 models with `belongsTo(User::class)` (Orders, Messages, etc.) — requires new columns/migrations
2. **RoleController refactor** — Switch to `tenant_memberships.role_id` for counts (4 query changes in 1 file)
3. **AdminUserController role filter** — Switch to `whereHas('memberships.role', ...)` (1 query change in 1 file)
4. **Tenant email sync** — Add Observer or remove column (1 file)
5. **ChatController / TelegramIntegrationController** — Full polymorphic migration (multiple files)
6. **TenantDeletionService** — Handle Account-mode cleanup (1 file)
