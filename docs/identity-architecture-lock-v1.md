# Identity Architecture Lock — v1

**Status:** FINAL — Architecture Locked  
**Date:** 2026-07-07  
**Project:** Multi-tenant SaaS E-commerce Platform  
**Supersedes:** `docs/identity-architecture-specification.md`, `docs/multi-role-email-identity-strategy.md`

---

## Executive Summary

This document locks every architectural decision governing the identity system of this multi-tenant SaaS e-commerce platform. It is the single source of truth for identity, membership, tenant, role, permission, authentication, and authorization architecture. Future implementation phases must follow this specification without redesigning the foundation.

The core architectural shift is the separation of **Identity** (who you are) from **Membership** (what you can do in each tenant). This replaces the current `users` table where a single record conflated login credentials, tenant membership, role, and ownership.

The design is inspired by Slack (workspace membership model), Shopify (multi-store merchant identity), and GitHub (organization membership + role assignment). It supports one Account owning multiple stores, being a customer in other stores, and being staff in yet others — all with one email and one password.

---

## Vision

A single natural person should be able to:
- Own and manage multiple stores from a single login
- Shop as a customer in any other store on the platform
- Work as staff in stores they do not own
- Have a unified notification and password management experience
- Switch between tenant contexts without re-authentication

All of this must be supported with:
- Clean separation of identity, membership, and authorization
- Tenant-scoped data isolation
- Audit transparency for every identity action
- OAuth readiness without structural changes
- API authentication readiness without structural changes

---

## Architecture Principles

1. **Identity is global.** An Account represents a natural person. Email is globally unique. The Account exists independently of any tenant.

2. **Membership is per-tenant.** A TenantMembership links an Account to a Tenant with a Role. One Account has exactly one Membership per Tenant. An Account can have zero, one, or many Memberships across different Tenants.

3. **Authentication proves Identity.** Login verifies email + password against the Account. Success proves who you are, not what you can do.

4. **Authorization checks Membership.** What you can do in a tenant depends on your Membership's Role and its Permissions. An authenticated Account with no Membership in the current tenant is unauthorized.

5. **Role is per-Membership.** Each Membership has exactly one Role. The same Account can be `admin` in one tenant and `customer` in another.

6. **Profile extends Membership.** Role-specific data lives in extension tables (CustomerProfile, StaffProfile) linked to the Membership, not to the Account or Tenant.

7. **Tenant owns business data.** The Tenant model owns store-level data (products, orders, settings). It does not own identity data.

8. **SuperAdmin is platform-level.** A SuperAdmin Account has no tenant Membership. It bypasses tenant-scoped authorization entirely.

---

## Final Identity Model

### Account

**Responsibility:** The root identity of a natural person. Owns email, password, verification status, and global account status.

| Property | Rule | Rationale |
|---|---|---|
| Email | Globally unique, immutable after creation | One email identifies one person across the entire platform |
| Password | Bcrypt-hashed, account-level | One password for all tenants; reset affects all memberships |
| Email verification | Account-level, single timestamp | Proving email ownership covers all memberships |
| Status | `active`, `suspended`, `banned` | Account-level status gates authentication entirely |
| Profile Image | Optional, global | Same avatar across all tenants |
| Notification preferences | JSON, global defaults | Per-tenant overrides stored on Membership or Profile |
| Soft delete | `deleted_at` timestamp | Never hard-deleted; preserves audit trail and order references |

**What it must never own:**
- Tenant-specific role or permissions
- Tenant-specific status
- `is_owner` flag
- `tenant_id`

---

## Final Membership Model

### TenantMembership

**Responsibility:** Links an Account to a Tenant. Carries the role, ownership flag, membership status, and invitation metadata.

| Property | Rule | Rationale |
|---|---|---|
| account_id | FK to accounts, CASCADE on delete | Membership cannot exist without an Account |
| tenant_id | FK to tenants, CASCADE on delete | Membership cannot exist without a Tenant |
| role_id | FK to roles, RESTRICT on delete | Membership must have exactly one Role |
| is_owner | BOOLEAN, default false | At most one per Tenant |
| status | `active`, `invited`, `suspended`, `removed` | Membership-level status independent of Account status |
| invited_by | FK to accounts, nullable | Tracks who sent the invitation |
| invited_at | Timestamp, nullable | For invitation expiry |
| joined_at | Timestamp, nullable | When invitation was accepted |
| is_default | BOOLEAN, default false | Default tenant for login redirect |
| UNIQUE | (account_id, tenant_id) | One membership per Account per Tenant |

**What it must never own:**
- Email or password
- Email verification status
- Account-level status (suspended/banned)
- Tenant business data (products, orders, settings)
- Global notification preferences

---

## Final Tenant Model

### Tenant

**Responsibility:** Represents a store/business entity. Owns tenant-scoped business data.

No structural changes from the current model. The Tenant:
- Owns its name, slug, domain, settings, subscription plan
- Owns all business data (products, orders, categories, etc.)
- Has an `owner` implicitly through the Membership with `is_owner=true`
- Does NOT have a direct `owner_id` FK (ownership is through the membership pivot)

**What it must never own:**
- Identity data (emails, passwords)
- Account-level settings
- Global platform configuration

---

## Final Role Model

### Role (Spatie)

**Responsibility:** Defines a named set of permissions within a tenant.

**Structure:** No change from current. Roles remain tenant-scoped via the existing `(tenant_id, name, guard_name)` unique constraint.

| Property | Rule |
|---|---|
| Scoping | Tenant-scoped (each tenant has its own `admin`, `customer`, etc.) |
| Guard | `web` for all tenant roles |
| SuperAdmin | No `tenant_id` (platform-level role) |
| Role-to-Membership | Direct FK on `tenant_memberships.role_id` |
| Role-to-Permission | Through Spatie's `role_has_permissions` table |

**Standard Roles Per Tenant:**
- `admin` — Full permissions within the tenant. Assigned to owners and staff.
- `customer` — Storefront permissions only (view products, place orders, manage own profile).

**Future Staff Roles (defined per tenant):**
- `manager` — All permissions except billing and user management
- `cashier` — Order view/create/status-update; no product or pricing
- `inventory` — Product view/create/update; no pricing or orders
- `marketing` — Promotions, banners, coupons; no orders or billing
- `support` — Order view/status-update; customer view; no billing

---

## Final Permission Model

### Permission (Spatie)

**Responsibility:** Names a discrete capability within the platform.

| Property | Rule |
|---|---|
| Scoping | Global (not tenant-scoped). A permission name is the same everywhere. |
| Assignment | To Roles only (via `role_has_permissions`) |
| Direct-to-Membership | Never. Permissions are never assigned directly to Accounts or Memberships. |
| Permission Overrides | Future: `staff_profiles.permissions_overrides` JSON field for exception-based permission additions |

**Naming Convention:**
```
{resource}.{action}
```
Examples: `orders.view`, `orders.create`, `orders.update_status`, `products.edit`, `users.invite`, `billing.manage`

---

## Authentication Strategy

### Guard Configuration

A single authentication guard:

```
Guard: web
Provider: accounts
Model: App\Models\Account
Driver: session
```

**No separate guards** for admin, customer, or API. Single guard with tenant context resolution via session.

### Why Single Guard

- Simpler configuration (no guard switching)
- One login endpoint for all roles
- Tenant membership provides context differentiation
- Future API auth uses Sanctum tokens on the same Account model

### Session Payload

After authentication, the session stores:

| Key | Value | Purpose |
|---|---|---|
| `account_id` | Account ID | Authenticated identity |
| `current_tenant_membership_id` | Membership ID | Current tenant context (null for SuperAdmin) |
| `current_tenant_id` | Tenant ID (denormalized) | Quick access without membership join |

### Login Flow

```
1. User submits email + password
2. Look up Account by email
3. If not found → "Invalid credentials" (generic, no user enumeration)
4. If found but Account.status is 'suspended' or 'banned' → "Account unavailable"
5. If password does not match → "Invalid credentials"
6. If password matches → authenticate session
7. Resolve tenant context:
   a. If URL path contains store slug → resolve Membership for (account_id, tenant_id_from_slug)
   b. If session has existing current_tenant_membership_id → verify still valid
   c. If Account has is_default membership → use that
   d. If Account has exactly one active membership → use that
   e. If Account has multiple memberships with no context → redirect to /select-tenant
8. Store account_id + membership_id in session
9. Log audit event (login, IP, user_agent, tenant_id)
10. Redirect to appropriate dashboard (admin → admin dashboard, customer → storefront)
```

### Store-Specific Login

- `/store/{slug}/login` — Customer login scoped to a specific store
- `/store/{slug}/admin/login` — Admin/Staff login scoped to a specific store
- After auth, verify Membership exists for (account_id, tenant_id_from_slug)
- If Membership does not exist → "You don't have access to this store"
- If Membership.status is 'invited' → "Please accept your invitation first"
- If Membership.status is 'suspended' → "Your access to this store has been suspended"

### SuperAdmin Login

- `/superadmin/login` — Platform-level login
- After auth, verify Account has `superadmin` role
- No tenant context needed
- Redirect to `/superadmin/dashboard`

### Root Login Restriction

- `/login` remains SuperAdmin-only
- Tenant users must log in through their store URL
- This is unchanged from current behavior

---

## Authorization Strategy

### Middleware Stack

```
Route: /store/{slug}/admin/orders
├── IdentifyTenant          → Resolves tenant from URL slug → sets App('current.tenant')
├── Authenticate            → Auth::check() against web guard (Account model)
├── ResolveMembership       → Finds Membership for (Auth::id(), current_tenant->id)
│                           → If none found: 403
│                           → Sets session current_tenant_membership_id if not already set
├── CheckMembershipStatus   → membership.status === 'active'?
│                           → 'invited': redirect to accept invitation
│                           → 'suspended': 403 "Access suspended in this store"
│                           → 'removed': 403
├── CheckRoleOrPermission   → Gate::authorize() or middleware('permission:orders.view')
├── ValidateTenantBinding   → Route-model-bound entities belong to current tenant
├── EnsureTenantIsActive    → Tenant.status === 'active' && subscription valid
└── EnsureStoreNotLocked    → Tenant.locked_at === null
```

### Gate Integration

```php
// In AuthServiceProvider::boot()
Gate::before(function (Account $account, string $ability) {
    // SuperAdmin bypass — has implicit access to everything
    if ($account->hasRole('superadmin')) {
        return true;
    }

    $membership = $account->currentMembership();

    if (! $membership || $membership->status !== 'active') {
        return false;
    }

    // Owner bypass — owner of the current tenant has all permissions
    if ($membership->is_owner) {
        return true;
    }

    // Check through membership's role
    return $membership->role->hasPermissionTo($ability);
});
```

### Spatie Configuration

```php
// config/permission.php
'register_permission_check_method' => false,  // We implement our own Gate::before()
```

The Account model does NOT use Spatie's `HasRoles` trait. Role resolution goes through the current Membership's `role_id` FK.

### Permission Override (Future)

The `staff_profiles.permissions_overrides` JSON field stores additional permission names for staff members who need capabilities beyond their role. The Gate::before() checks:

```php
// Merge role permissions with overrides
$rolePermissions = $membership->role->permissions->pluck('name');
$overrides = $membership->staffProfile?->permissions_overrides ?? [];
$effectivePermissions = $rolePermissions->merge($overrides);
return $effectivePermissions->contains($ability);
```

---

## Business Rules

### Account Rules

| Rule | Decision | Rationale |
|---|---|---|
| Can one Account own multiple Stores? | **YES** | One person, multiple stores (Shopify model) |
| Can one Account be Customer in another Store? | **YES** | Merchants should cross-shop with same email |
| Can one Account be Staff in another Store? | **YES** | Cross-store collaboration, consultants |
| Can one Account be SuperAdmin AND have Memberships? | **YES** (edge case) | For testing and support scenarios |
| Can an Account have 0 Memberships? | **YES** | SuperAdmin-only accounts, or accounts that haven't joined any tenant yet |
| Should Accounts ever be hard-deleted? | **NO** | Soft-delete only. Preserves order history and audit trail. |
| Should Account email be changeable? | **YES** | Via verified email change flow (future feature) |
| Should Account password expire? | **NO** | No forced rotation. User changes on demand. |

### Membership Rules

| Rule | Decision | Rationale |
|---|---|---|
| One Membership per Account per Tenant? | **YES** | UNIQUE(account_id, tenant_id) enforced |
| One Membership = one Role? | **YES** | Direct role_id FK. No multi-role per membership. |
| Can a Tenant have multiple Owners? | **NO** | Exactly one owner at any time |
| Should Owner be transferable? | **YES** | Via ownership transfer flow |
| Should Membership be suspendable? | **YES** | Membership-level suspension (tenant-specific) |
| Should Membership be archivable? | **YES** | Soft-delete or status='removed' for audit |
| Should Membership have a status? | **YES** | active, invited, suspended, removed |
| Should invitations expire? | **YES** | 7-day expiry (configurable) |

### Role Rules

| Rule | Decision | Rationale |
|---|---|---|
| Can a Membership have no Role? | **NO** | Membership must have exactly one role_id |
| Can a Role be shared across Memberships? | **YES** | Many memberships can point to the same role |
| Are roles tenant-scoped? | **YES** | Each tenant has its own set of roles |
| Is the SuperAdmin role tenant-scoped? | **NO** | Global, no tenant_id |

### Tenant Rules

| Rule | Decision | Rationale |
|---|---|---|
| Can a Tenant have 0 Memberships? | **NO** | At minimum, the owner's membership exists |
| Can a Tenant have 0 Customers? | **YES** | A new store may not have customers yet |
| Should Tenant be suspendable? | **YES** | Via Tenant.status (subscription/billing-related) |
| Should Tenant be deletable? | **SOFT-DELETE** | Preserves data integrity |

---

## Table Responsibilities

### `accounts`

| Aspect | Detail |
|---|---|
| **Why it exists** | Root identity for a natural person |
| **Responsibility** | Email, password, email_verified_at, remember_token, profile_image, status, notification_preferences, last_login_at, last_login_ip |
| **Must never own** | tenant_id, tenant-specific role, is_owner flag, tenant-specific status, tenant business data |

### `tenant_memberships`

| Aspect | Detail |
|---|---|
| **Why it exists** | Links an Account to a Tenant with Role, Ownership, and Status |
| **Responsibility** | account_id, tenant_id, role_id, is_owner, status, invited_by, invited_at, joined_at, is_default |
| **Must never own** | Email, password, email_verified_at account-level status, tenant business data, global notification preferences |

### `tenants`

| Aspect | Detail |
|---|---|
| **Why it exists** | Represents a store/business entity |
| **Responsibility** | Name, slug, domain, email, logo, status, settings, subscription_plan_id, activated_at, locked_at, expires_at |
| **Must never own** | Identity credentials, account-level data, global configuration |

### `customer_profiles`

| Aspect | Detail |
|---|---|
| **Why it exists** | Extends a customer-role Membership with store-scoped personal data |
| **Responsibility** | tenant_membership_id (unique FK), name, phone, metadata |
| **Must never own** | Login credentials, role information, order data, addresses (those stay on their respective tables) |

### `staff_profiles`

| Aspect | Detail |
|---|---|
| **Why it exists** | Extends an admin/staff-role Membership with position and permission overrides |
| **Responsibility** | tenant_membership_id (unique FK), position, department, permissions_overrides (JSON) |
| **Must never own** | Login credentials, role assignment, business data |

### `social_accounts`

| Aspect | Detail |
|---|---|
| **Why it exists** | Links third-party OAuth providers to an Account |
| **Responsibility** | account_id, provider, provider_id, provider_email, avatar_url, token, refresh_token, expires_at |
| **Must never own** | Login credentials, role information, tenant membership data |

### `sessions`

| Aspect | Detail |
|---|---|
| **Why it exists** | Stores active authentication sessions |
| **Responsibility** | id, account_id, current_tenant_membership_id, ip_address, user_agent, payload, last_activity |
| **Must never own** | Long-term identity data, role information, permissions cache |

### `password_reset_tokens`

| Aspect | Detail |
|---|---|
| **Why it exists** | Stores password reset tokens |
| **Responsibility** | account_id (primary key), token, created_at |
| **Must never own** | Identity data, email (keyed by account_id, not email) |

### `audit_logs` / `activity_log`

| Aspect | Detail |
|---|---|
| **Why it exists** | Logs identity and authorization events |
| **Responsibility** | account_id, membership_id, tenant_id, event_type, context, ip_address, user_agent, old_values, new_values |
| **Must never own** | Business transaction data, identity credentials |

---

## Relationship Design

### Core Relationship Structure

```
Account (1) ──────< (0..*) TenantMembership (0..*) >────── (1) Tenant
                     │
                     ├── (0..1) CustomerProfile
                     │
                     └── (0..1) StaffProfile
                     │
                     └── (*) AuditLog
```

### Cardinality Justifications

| Relationship | Cardinality | Justification |
|---|---|---|
| Account → TenantMembership | One-to-Many | One person can have memberships in many tenants |
| TenantMembership → Account | Many-to-One | Many memberships point to one Account |
| Tenant → TenantMembership | One-to-Many | One tenant has many members (owner, staff, customers) |
| TenantMembership → Tenant | Many-to-One | One membership belongs to exactly one tenant |
| TenantMembership → Role | Many-to-One | Many memberships can share the same role (e.g., many customers) |
| Role → TenantMembership | One-to-Many | One role can be assigned to many memberships |
| TenantMembership → CustomerProfile | One-to-One | At most one customer profile per membership |
| TenantMembership → StaffProfile | One-to-One | At most one staff profile per membership |
| Account → SocialAccount | One-to-Many | One account can link many OAuth providers |
| Account → AuditLog | One-to-Many | One account generates many audit events |

### Unique Constraints

| Table | Constraint | Purpose |
|---|---|---|
| `accounts` | UNIQUE(email) | One identity per email globally |
| `tenant_memberships` | UNIQUE(account_id, tenant_id) | One membership per account per tenant |
| `customer_profiles` | UNIQUE(tenant_membership_id) | At most one customer profile per membership |
| `staff_profiles` | UNIQUE(tenant_membership_id) | At most one staff profile per membership |
| `social_accounts` | UNIQUE(provider, provider_id) | One link per provider identity |
| `tenant_memberships` | Partial UNIQUE(tenant_id) WHERE is_owner=true | At most one owner per tenant |

### Foreign Key Rules

| FK | Parent | Delete Rule | Rationale |
|---|---|---|---|
| tenant_memberships.account_id | accounts | CASCADE | If account deleted, all memberships go (soft-delete prevents data loss) |
| tenant_memberships.tenant_id | tenants | CASCADE | If tenant deleted, all memberships go |
| tenant_memberships.role_id | roles | RESTRICT | Cannot delete a role that is assigned to memberships |
| tenant_memberships.invited_by | accounts | SET NULL | Preserves membership if inviting account is deleted |
| customer_profiles.tenant_membership_id | tenant_memberships | CASCADE | Profile dies with membership |
| staff_profiles.tenant_membership_id | tenant_memberships | CASCADE | Profile dies with membership |
| social_accounts.account_id | accounts | CASCADE | Provider link dies with account |

---

## Registration Flows

### Flow 1: Merchant Creates a Store

```
Visitor → /create-store → Form (store name, slug, owner name, email, password)

1. Validate:
   - tenant.slug: unique in tenants
   - owner.email: unique:accounts,email
   - password: confirmed, min 8

2. Transaction:
   a. Create Tenant (status: 'trialing' or 'pending')
   b. Create Account (email, password, email_verified_at = null)
   c. Create default roles (admin, customer) for this tenant
   d. Create TenantMembership:
      - account_id, tenant_id
      - role_id → tenant's 'admin' role (created in step c)
      - is_owner = true
      - status = 'active'
      - is_default = true

3. Dispatch Registered event (sends email verification)
4. Log in as the Account (set session with this membership as current)
5. Redirect to onboarding wizard
```

### Flow 2: Customer Registers in a Store

```
Visitor → /store/{slug}/register → Form (name, email, password)

1. Validate:
   - Tenant exists and is active
   - WebsiteInfo.allow_registration = true
   - email: globally unique in accounts
   - Check: if Account with this email already has a membership in this tenant → error

2. Transaction:
   a. Find or create Account by email
      - If Account exists and status is not 'active' → registration blocked
   b. Create TenantMembership:
      - account_id, tenant_id
      - role_id → tenant's 'customer' role
      - is_owner = false
      - status = 'active'
   c. Create CustomerProfile:
      - tenant_membership_id
      - name from form

3. If Account was just created → Dispatch Registered event
4. Log in (set session membership to this tenant)
5. Redirect to storefront
```

### Flow 3: Staff Invitation

```
Merchant → /store/{slug}/admin/staff/invite → Form (email, role)

1. Authorize: current membership is owner OR has 'users.invite' permission
2. Validate: email format, not already a member of this tenant

3. Transaction:
   a. Find or create Account by email
   b. Create TenantMembership:
      - account_id, tenant_id
      - role_id → tenant's selected staff role
      - is_owner = false
      - status = 'invited'
      - invited_by = current account id
      - invited_at = now

4. Send invitation notification to email
   - If Account already exists → signed URL for direct acceptance
   - If Account does not exist → notification includes registration link
5. Return success to inviter
```

### Flow 4: Invitation Acceptance

```
Recipient → /store/{slug}/invitations/{membership_id}/accept?signature=...

1. Validate signed URL (or token)
2. If not authenticated:
   a. If Account exists → redirect to login, then back to accept
   b. If Account does not exist → redirect to registration, then back to accept
3. If authenticated as a different Account → error
4. Update TenantMembership:
   - status = 'active'
   - joined_at = now
5. Log audit event
6. Set as current membership in session
7. Redirect to store dashboard
```

---

## Login Flows

### Merchant/Admin Login

```
POST /store/{slug}/admin/login
1. IdentifyTenant resolves tenant from slug
2. Authenticate: Auth::attempt(email, password)
3. Look up Membership for (account_id, tenant_id)
4. If Membership.status === 'invited' → "Please accept your invitation"
5. If Membership.status === 'suspended' → "Your access has been suspended"
6. If Membership.status === 'removed' → "You no longer have access"
7. If no Membership exists → "You don't have access to this store"
8. Set session: current_tenant_membership_id, current_tenant_id
9. Record login audit event
10. Redirect to admin dashboard
```

### Customer Login

```
POST /store/{slug}/login
1. IdentifyTenant resolves tenant from slug
2. Authenticate: Auth::attempt(email, password)
3. Look up Membership for (account_id, tenant_id) with customer role
4. If Membership.status === 'suspended' → "Account suspended in this store"
5. If no Membership → redirect to registration
6. Set session: current_tenant_membership_id, current_tenant_id
7. Record login audit event
8. Redirect to storefront
```

### Multi-Tenant Login (No URL Context)

```
POST /login (platform-level, SuperAdmin only for production)

If same endpoint is used for tenant users without store slug in URL:
1. Authenticate against Account
2. Count active Memberships:
   - 0: If SuperAdmin → redirect to superadmin dashboard
   - 0: If not SuperAdmin → "You don't have access to any store. Please contact support."
   - 1: Redirect to that store's dashboard
   - 2+: Redirect to /select-tenant
```

---

## Password Reset Strategy

### Reset is Account-Level

The password belongs to the Account, not to individual Memberships. A single reset changes the password for all tenant access.

### Flow

```
1. User submits email on /forgot-password (or /store/{slug}/forgot-password)
2. System looks up Account by email
3. If Account found:
   a. Generate reset token
   b. Store in password_reset_tokens (keyed by account_id)
   c. Send email with reset link
4. If Account not found → still show "If this email exists, a reset link has been sent" (no enumeration)

Reset Link: /reset-password/{token}?email={email}
   - If from store-scoped page: /store/{slug}/reset-password/{token}

5. User submits new password
6. Validate token (check account_id + token + expiry)
7. Update Account.password
8. Revoke all sessions for this Account (except current)
9. Redirect to login page
```

### Tenant-Specific UX (Optional)

The reset link can include a `store_slug` parameter to redirect the user back to the correct login page after reset. The password reset itself is still Account-level.

### Key Difference From Current

- Current: `password_reset_tokens` keyed by `email` (PK)
- Future: `password_reset_tokens` keyed by `account_id`
- Current: Password is per-User record
- Future: Password is per-Account, shared across all memberships

---

## Email Verification Strategy

### Verification is Account-Level

One verification covers all memberships. Proving email ownership proves identity regardless of tenant context.

### Flow

```
1. Account registers → Dispatch Registered event
2. Verification notification sent to email
   - Link: /verify-email/{id}/{hash}
3. User clicks link
4. Mark Account.email_verified_at = now
5. Redirect to post-verification URL
```

### Re-Verification

- If user changes email → new verification required
- Old email is immediately freed for reuse by another Account
- Unverified accounts cannot authenticate (MustVerifyEmail contract)

### Resend Verification

- Throttled (1 per minute)
- Available from profile settings and post-registration prompt

---

## Invitation Strategy

### Data Model

Invitations live on the `TenantMembership` model itself:

```
status = 'invited'
invited_by = Account ID of inviter
invited_at = timestamp of invitation
joined_at = null (set on acceptance)
```

### Token Strategy: Signed URLs

Use Laravel signed URLs instead of a separate `invitation_tokens` table:

```
/store/{slug}/invitations/{membership_id}/accept?expires=...&signature=...
```

Advantages:
- No additional table needed
- Built-in expiry via `now()->addDays(7)`
- Built-in tamper protection via signature

### Acceptance Rules

1. URL must be valid (not expired, signature matches)
2. If recipient is logged in as the target Account → accept immediately
3. If recipient is not logged in → redirect to login, then accept
4. If recipient is logged in as a different Account → error "This invitation was sent to a different email"
5. If recipient Account does not exist → redirect to registration with pre-filled email

### Expiry

- Invitations expire after 7 days
- `TenantMembership` with `status = 'invited'` and `invited_at < now - 7 days` are considered expired
- Failed acceptance attempt on expired membership → "This invitation has expired. Please ask the store owner to send a new invitation."

---

## Store Switching Strategy

### Flow

```
POST /select-tenant
Payload: { tenant_membership_id: 123 }

1. Verify Account has active Membership for the given tenant_membership_id
2. If Membership.status !== 'active' → 403
3. Update session: current_tenant_membership_id, current_tenant_id
4. Log audit event (tenant switch)
5. Redirect to that tenant's base URL or dashboard
```

### UI Analogy

Works exactly like:
- Slack's workspace switcher (sidebar)
- GitHub's organization switcher (top-left dropdown)

### Tenant Selector Page

When an Account has multiple memberships and no context is specified:

```
GET /select-tenant
- List all active memberships with tenant name, logo, role
- User clicks one → POST /select-tenant → redirect
```

---

## Ownership Transfer Strategy

### Preconditions

1. Current owner has active Membership with `is_owner = true`
2. Target Account has active Membership in the same tenant
3. Target Account is not the current owner
4. Current owner initiates the transfer

### Flow

```
1. Current owner → /store/{slug}/admin/transfer-ownership → Form (target email)

2. Validate:
   - Current membership.is_owner = true
   - Target Account exists and has Membership in this tenant
   - Target Membership.status = 'active'
   - Target Account is not the same as current

3. Authorization:
   - Log audit event (ownership transfer initiated)

4. Notification to current owner:
   - "Confirm ownership transfer to {target_email}?"
   - This is a confirmation, not a secondary approval

5. On confirmation:
   a. BEGIN TRANSACTION
   b. Current Membership: is_owner = false (role stays as 'admin')
   c. Target Membership: is_owner = true (role stays unchanged)
   d. Log audit event (ownership transferred from X to Y)
   e. COMMIT

6. Notify both parties:
   - Previous owner: "You have transferred ownership of {store}"
   - New owner: "You are now the owner of {store}"

7. Redirect to store dashboard
```

### Post-Transfer

- Previous owner retains their Membership with `is_owner = false` and `admin` role
- Previous owner's permissions are now role-based (not owner-bypass)
- Previous owner can be removed or demoted by the new owner
- If previous owner was the only admin, the new owner should assign them a staff role or they lose admin access

### Transfer Restrictions

- Cannot transfer ownership to a suspended Account
- Cannot transfer ownership to an invited (not yet joined) Membership
- Cannot transfer ownership of a locked/suspended tenant
- Transfer can be cancelled by the current owner before confirmation

---

## Notification Strategy

### Notification Target Architecture

| Notification Type | Target | Routing |
|---|---|---|
| New order for store | All admin memberships in the tenant | Through Tenant.notifyAdmins() |
| Order status change | Customer's Account | Through Account notification channel |
| Payment verified | Admin memberships + customer Account | Both through respective channels |
| Invitation received | Target Account | Through Account notification channel |
| Password reset | Account | Through Account (MustVerifyEmail) |
| Email verification | Account | Through Account (MustVerifyEmail) |
| Ownership transfer | Previous owner + new owner | Through Account notification channel |
| Low stock warning | Admin memberships in the tenant | Through Tenant.notifyAdmins() |

### Implementation Notes

- `Account` implements `HasNotifications` trait (Laravel Notifiable)
- Notifications can be sent directly to an Account
- Tenant-wide notifications use the tenant's membership list:
  ```
  $tenant->memberships()
      ->whereHas('role', fn($q) => $q->where('name', 'admin'))
      ->where('status', 'active')
      ->get()
      ->each(fn($m) => $m->account->notify($notification));
  ```

### Notification Preferences

- Account-level global preferences stored as JSON on `accounts.notification_preferences`
- Preference key: `{type}` mapped to boolean
- Default: all enabled for the appropriate roles
- Per-tenant notification preferences (future): stored on Membership or Profile

---

## Session Strategy

### Session Structure

After authentication, each session stores:

| Key | Type | Purpose |
|---|---|---|
| `account_id` | integer | Authenticated Account |
| `current_tenant_membership_id` | integer|null | Current context (null for SuperAdmin) |
| `current_tenant_id` | integer|null | Denormalized for quick access |
| `ip_address` | string | Security audit |
| `user_agent` | string | Security audit |

### Concurrent Sessions

- Each login creates a new session (Laravel default)
- An Account can be logged in on multiple devices simultaneously
- Each session can have a different `current_tenant_membership_id`
- Example: Tab 1 for Store A admin dashboard, Tab 2 for Store B storefront

### Tenant Context Per-Request

Each request resolves the tenant context in this order:

1. `IdentifyTenant` middleware resolves tenant from URL slug (highest priority)
2. If no slug in URL, fall back to session's `current_tenant_membership_id`
3. Migrate middleware updates session if URL slug differs from session

This allows a user to open Store A in one tab and Store B in another without session conflicts.

### Session Revocation (Future)

- Account views active sessions from profile
- Revoke individual sessions (delete from `sessions` table)
- Revoke all sessions (delete all where `account_id = X`)
- Password reset revokes all sessions except current

---

## OAuth Readiness

### Table: `social_accounts`

| Column | Type | Purpose |
|---|---|---|
| id | BIGINT PK | |
| account_id | BIGINT FK → accounts | Linked Account |
| provider | VARCHAR(50) | 'google', 'github', 'apple', 'microsoft' |
| provider_id | VARCHAR(255) | User ID from the provider |
| provider_email | VARCHAR(255) NULLABLE | Email from provider |
| avatar_url | VARCHAR(500) NULLABLE | Avatar from provider |
| token | TEXT NULLABLE | Encrypted access token |
| refresh_token | TEXT NULLABLE | Encrypted refresh token |
| expires_at | TIMESTAMP NULLABLE | Token expiry |
| UNIQUE | (provider, provider_id) | |

### OAuth Login Flow

```
1. User clicks "Sign in with {Provider}"
2. Provider redirects to callback with authorization code
3. Exchange code for access token
4. Retrieve user info from provider (id, email, name, avatar)
5. Look up social_accounts by (provider, provider_id)
6. If found → authenticate the linked Account
7. If not found:
   a. Check if provider email matches an existing Account
   b. If yes → create social_accounts link → authenticate Account
   c. If no → create new Account → create social_accounts link → authenticate Account
8. After authentication, proceed with tenant resolution (same as email login)
```

### OAuth Registration Flow

```
1. User clicks "Sign up with {Provider}" within a store context
2. Provider callback → Account created (if new) with verified email
3. Social_accounts link created
4. If within store context → create Membership with customer role
5. If platform-level → redirect to tenant creation or tenant selector
```

### Security Considerations

- OAuth tokens are encrypted at rest
- Provider email is trusted (provider has verified it)
- If provider email matches an existing Account, user must confirm relinking
- Each provider+provider_id pair is unique (unlinkable to a different Account)

---

## API Readiness

### Strategy: Laravel Sanctum

- Sanctum tokens are issued to `Account` model
- API requests authenticate via Bearer token or session cookie
- API requests must specify tenant context:
  - Header: `X-Tenant: {slug}` or `X-Tenant-ID: {id}`
  - Or URL-based: `/api/store/{slug}/...`
- Token abilities (scopes) for fine-grained API permissions

### Token Model

| Property | Purpose |
|---|---|
| Belongs to | Account |
| Abilities | Array of permission strings (e.g., `['orders:read', 'products:write']`) |
| Expiry | Optional expiration |
| Last used | Track for audit |

### API Authorization

Same Gate::before() logic applies:
1. Authenticate Account via Sanctum token
2. Resolve tenant from header or URL
3. Resolve Membership for (account_id, tenant_id)
4. Check role-based permissions

### Mobile App Considerations

- Mobile app authenticates via Sanctum token (long-lived or refreshable)
- Tenant context provided in each request
- Store switcher built into the app
- Push notifications via Firebase/APNs (separate token storage)

---

## Security Considerations

### Authentication Security

| Measure | Implementation |
|---|---|
| Password hashing | Bcrypt via Laravel `hashed` cast |
| Rate limiting | 5 attempts per email+IP on login; 3 on password reset |
| Account enumeration prevention | Generic "Invalid credentials" message |
| Session fixation | Laravel `regenerate()` after login |
| Remember me | Token rotation on every auth check |
| Brute force | Throttle middleware on all auth routes |

### Authorization Security

| Measure | Implementation |
|---|---|
| Defense in depth | Route middleware + Gate::before() + controller authorize() |
| Tenant isolation | IdentifyTenant → ResolveMembership → CheckMembershipStatus stack |
| SuperAdmin bypass | Only in Gate::before(), never in business logic |
| Permission caching | Spatie's built-in 24-hour cache with flush on change |
| Invitation tampering | Signed URLs with expiry |

### Data Security

| Measure | Implementation |
|---|---|
| Password storage | Bcrypt, never logged |
| OAuth tokens | Encrypted at rest |
| Audit logs | Append-only (no update/delete) |
| Soft delete | Prevents accidental data loss |
| Email in logs | Account ID used as primary reference; email logged only in identity events |

### Cross-Tenant Protection

| Attack Vector | Protection |
|---|---|
| Direct ID access in URL | ValidateTenantBinding middleware |
| Missing tenant scope | TenantAware trait (global scope) on all business models |
| API cross-tenant | X-Tenant header validated against membership |
| Image access | Storage path includes tenant ID prefix (future) |

---

## Migration Risks

### Risk 1: Data Migration Complexity

**Risk:** Migrating existing `users` records to `accounts` + `tenant_memberships` when duplicate emails exist (currently blocked by global unique constraint, but possible after V3 changes).

**Mitigation:** The data migration script must:
1. Group users by email
2. Create one Account per unique email
3. Create one Membership per user record
4. Handle edge cases: User with null tenant_id (SuperAdmin), User with missing role assignment

**Rollback:** Keep `users` table as read-only during transition. Dual-read strategy until verified.

### Risk 2: Auth Guard Switchover

**Risk:** Changing the auth provider from `users` to `accounts` could break existing sessions and cause authentication failures during deployment.

**Mitigation:**
1. Deploy new `accounts` table alongside `users` (no schema changes to users)
2. Run data migration to populate accounts + memberships
3. Deploy code that reads from both tables (dual-write during transition)
4. Switch auth guard in a separate deployment after verification
5. Existing sessions remain valid because they store user_id, which becomes account_id

### Risk 3: Spatie Permission Cache

**Risk:** Spatie caches permissions with a model reference. Changing the permission-checking model from User to Account (or Membership) could cause stale cache issues.

**Mitigation:** Flush Spatie permission cache during deployment. Verify cache key uniqueness.

### Risk 4: Third-Party Package Expectations

**Risk:** Packages like Laravel Nova, Filament, or custom admin panels may expect the authenticatable model to be `User` or may depend on `HasRoles`.

**Mitigation:** Configure a backward-compatible `User` model that extends Account or maps to it. Or update package configurations to reference `Account`.

### Risk 5: Session Invalidation

**Risk:** All existing sessions become invalid if the session structure changes (user_id → account_id).

**Mitigation:** Keep session structure compatible. Store both `user_id` (for backward compatibility during transition) and `account_id`. After full migration, drop `user_id` from sessions.

---

## Trade-offs

### Trade-off 1: Single Auth Guard vs. Multiple Guards

**Decision: Single guard (web) with session-based tenant context**

Advantages:
- Simpler configuration and maintenance
- No guard switching in middleware
- One login endpoint for all roles
- Compatible with all Laravel auth features (password reset, verification, etc.)

Disadvantages:
- Session must carry tenant context
- Cannot use guard-based route protection (e.g., `auth:customer`)
- All roles share the same auth endpoints

**Rejected alternative:** Separate guards for `admin` and `customer`.
- Pro: Clearer separation at the route level
- Con: Duplicated auth configuration, guard switching complexity, password reset broker duplication

### Trade-off 2: Direct role_id vs. Spatie HasRoles on Membership

**Decision: Direct role_id FK on tenant_memberships**

Advantages:
- Single query to get the role (no polymorphic pivot)
- Enforces one-role-per-membership at the database level
- Simpler permission checking via direct role access
- No need for Spatie's model_has_roles table

Disadvantages:
- Cannot assign multiple roles to a Membership (but this is intentional)
- Bypasses Spatie's role assignment API (can't use `assignRole()` on Membership)
- Custom Gate::before() needed instead of Spatie's built-in

**Rejected alternative:** HasRoles trait on Membership with model_has_roles pivot.
- Pro: Full Spatie API available on Membership
- Con: Polymorphic pivot adds complexity; multi-role per membership not needed

### Trade-off 3: Account-Level vs. Membership-Level Password

**Decision: Account-level password (one password for all memberships)**

Advantages:
- Single password for the user to remember
- Password reset affects all tenants at once
- Simpler UX

Disadvantages:
- Compromised password affects all tenants
- User cannot have different passwords per role

**Rejected alternative:** Membership-level password (different password per tenant).
- Pro: Isolated compromise (one tenant's password doesn't affect others)
- Con: Password fatigue, multiple password resets, complex session management

### Trade-off 4: Account-Level vs. Membership-Level Email Verification

**Decision: Account-level verification**

Advantages:
- Verify once, use everywhere
- Simpler flow

Disadvantages:
- Verified Account has verified access to all current and future tenants
- Cannot require per-store verification for compliance reasons

**Accepted risk:** Email ownership is the proof. If you own the email, you can access all tenants. This matches Slack, GitHub, and Shopify patterns.

### Trade-off 5: Invitation via Signed URL vs. Invitation Tokens Table

**Decision: Signed URLs**

Advantages:
- No additional table
- Leverages Laravel's built-in signed URL support
- Expiry built into the URL

Disadvantages:
- Cannot revoke an individual invitation after the URL is sent (signature is valid until expiry)
- URL in email logs could be replayed until expiry

**Compromise:** For revocation support, add an `invited_at` check. If a new invitation is sent with a newer `invited_at`, the old URL is invalidated (we check that the URL's timestamp matches the current `invited_at`).

---

## Phase-by-Phase Roadmap

### Phase 0: Architecture Lock (Current)

**Purpose:** Finalize and lock all architectural decisions. No code changes.

**Output:** This document (`docs/identity-architecture-lock-v1.md`)

### Phase 1: Database Foundation

**Purpose:** Create the new table structure. No code changes to controllers or middleware.

**Actions:**
1. Create `accounts` migration
2. Create `tenant_memberships` migration
3. Create `customer_profiles` migration
4. Create `staff_profiles` migration
5. Create `social_accounts` migration (empty schema, ready for future)
6. Modify `password_reset_tokens` to support account_id (or create new table)
7. Modify `sessions` to store account_id + membership_id

**Validation:**
- Verify migrations run cleanly up and down
- Verify foreign keys and unique constraints
- Verify rollback works

### Phase 2: Models & Relationships

**Purpose:** Implement Eloquent models and define relationships.

**Actions:**
1. Create `Account` model (extends Authenticatable, implements MustVerifyEmail)
2. Create `TenantMembership` model
3. Create `CustomerProfile` model
4. Create `StaffProfile` model
5. Define relationships on existing models (Tenant, User → backward compat)
6. Update `Role` model (no changes expected)
7. Update `Permission` model (no changes expected)

**Validation:**
- Verify all relationships return correct data
- Verify tinker queries work
- Verify eager loading doesn't produce N+1

### Phase 3: Data Migration

**Purpose:** Migrate existing data from `users` to `accounts` + `tenant_memberships`.

**Actions:**
1. Create migration/seeder script:
   - Group users by email
   - Create Account per unique email
   - Create TenantMembership per User record
   - Create CustomerProfile for customer-role users
   - Link social accounts (if any)
2. Add backward-compatible accessors on Account for legacy code expected through User model

**Validation:**
- Run migration on staging database
- Verify every User has a corresponding Account + Membership
- Verify no data loss
- Verify SuperAdmin accounts (null tenant_id) handled correctly
- Test rollback

### Phase 4: Authentication

**Purpose:** Switch authentication from User to Account.

**Actions:**
1. Update `config/auth.php`:
   - Provider model → `Account::class`
2. Update `LoginRequest` to check Account.status before authentication
3. Update `AuthenticatedSessionController`:
   - After auth, resolve Membership context
   - Store account_id + membership_id in session
4. Update `StorefrontLoginController`:
   - Same changes as above
5. Update `RegisteredUserController`:
   - Create Account + TenantMembership + CustomerProfile
6. Update `CreateStoreController`:
   - Create Account + Tenant + TenantMembership (owner)
7. Update password reset to use Account-level broker
8. Update email verification to use Account-level verification

**Validation:**
- Login with existing user credentials works
- Registration creates Account + Membership
- Password reset works
- Email verification works
- SuperAdmin login works
- Existing sessions remain valid

### Phase 5: Authorization

**Purpose:** Update all authorization checks to work through Membership.

**Actions:**
1. Update `config/permission.php`:
   - `register_permission_check_method` → `false`
2. Implement Gate::before() in AuthServiceProvider:
   - SuperAdmin bypass
   - Owner bypass (is_owner = true)
   - Role-based permission check
3. Create `ResolveMembership` middleware:
   - Finds Membership for (account_id, tenant_id)
   - If not found, abort(403)
   - Sets session context
4. Update `CheckTenantAccess` middleware:
   - Check Membership existence instead of user.tenant_id
5. Update `ValidateTenantBinding` middleware:
   - Already working correctly (checks tenant_id on models)
6. Update `RoleMiddleware`:
   - Check membership.role.name instead of user roles
7. Update all policies:
   - Replace `$user->can()` checks if they relied on HasRoles on User
   - New pattern: Gate::authorize() with Account, checked through Gate::before()

**Validation:**
- Admin routes accessible with correct role
- Customer routes accessible with customer role
- Permission denied for wrong role
- Permission denied for suspended membership
- Permission denied for no membership
- SuperAdmin bypass works correctly
- Owner bypass works correctly

### Phase 6: Registration & Invitation

**Purpose:** Implement invitation flow and refine registration flows.

**Actions:**
1. Implement staff invitation controller:
   - Create Membership with status='invited'
   - Send notification with signed URL
2. Implement invitation acceptance controller:
   - Validate signed URL
   - Update Membership status to 'active'
3. Implement tenant switcher page:
   - List active memberships
   - POST /select-tenant endpoint
4. Refine merchant registration flow:
   - Handle case where Account already exists (existing email, new tenant)

**Validation:**
- Send invitation → notification received
- Accept invitation → Membership active
- Expired invitation → appropriate error
- Invitation to non-existent email → Account created on acceptance
- Tenant switcher shows correct memberships
- Switch tenant → redirected to correct dashboard

### Phase 7: Notifications & Audit

**Purpose:** Update notification system and implement audit logging.

**Actions:**
1. Update notification channels to use Account model:
   - Admin notifications sent through tenant membership list
   - Customer notifications sent through Account
2. Create audit log entries for identity events:
   - Login, logout, membership change, role change, invitation, ownership transfer
3. Implement notification preferences on Account model
4. Update `Tenant::notifyAdmins()` to use memberships

**Validation:**
- Admin receives order notification
- Customer receives order status notification
- Audit log entries created for all identity events
- Notification preferences respected

### Phase 8: Cleanup & Deprecation

**Purpose:** Remove backward compatibility layer and clean up legacy code.

**Actions:**
1. Create a backward-compatible `User` model wrapper (if needed for third-party packages)
2. Update all remaining references to `App\Models\User`:
   - Replace with `App\Models\Account` or `App\Models\TenantMembership`
3. Update Inertia shared data to use Account instead of User
4. Audit all Blade templates (if any) for User references
5. Drop `is_owner`, `is_admin` from User table (if still present)
6. Drop `tenant_id` from User table (if still present)
7. Consider deprecating `users` table after full migration verified
8. Update all documentation

**Validation:**
- No `App\Models\User` imports remain in new code
- All tests pass
- All features work without relying on legacy model
- `users` table no longer written to (read-only for verification)

### Phase 9: Production QA

**Purpose:** Full production verification before going live.

**Actions:**
1. Run complete regression test suite
2. Verify all 25+ architecture decisions are implemented correctly
3. Verify multi-tenant isolation with cross-tenant test scenarios
4. Performance test with large membership datasets
5. Security audit of all auth endpoints
6. Verify rollback plan works

**Validation:**
- All scenarios pass
- No regression in existing features
- Auth performance acceptable
- No security vulnerabilities

---

## Final Engineering Recommendation

### Core Decision: Account + Membership Architecture

The separation of identity from membership is the correct long-term architecture for this multi-tenant platform. It aligns with proven patterns from Slack (workspace membership), Shopify (multi-store ownership), and GitHub (organization membership).

### Implementation Priority

1. **Phase 1 (Database Foundation) and Phase 2 (Models)** should be merged into a single sprint. The schema and models are tightly coupled and benefit from simultaneous implementation.

2. **Phase 3 (Data Migration)** is the highest-risk phase. It should be tested on a full production-size data copy before deployment.

3. **Phase 4 (Authentication)** and **Phase 5 (Authorization)** should not be split across deployments. Auth and auth are interdependent. Deploy them together.

4. **Phase 6 (Invitations)** can be parallelized with Phase 5 if resources permit.

5. **Phase 8 (Cleanup)** should not be rushed. Keep the `users` table for at least one full release cycle before dropping it.

### Verification Strategy

Before each production deployment:

1. Run the full test suite (unit + feature + browser tests)
2. Execute cross-tenant isolation tests (Account A cannot access Tenant B data)
3. Verify all three login flows (admin, customer, superadmin)
4. Verify invitation flow end-to-end
5. Verify ownership transfer
6. Review audit log for completeness
7. Rollback test (revert migration, verify no data loss)

### Success Criteria

This architecture is successful when:

1. A single email can register as merchant (Store A), customer (Store B), and staff (Store C)
2. Each tenant's data is completely isolated
3. Switching between tenants takes one click, no re-authentication
4. Password reset changes password for all tenants at once
5. Email verification covers all current and future memberships
6. Staff can be invited and granted role-specific access
7. Ownership can be transferred without data loss
8. OAuth providers can be linked to any Account
9. API tokens authenticate the Account and respect tenant context
10. Every identity action is logged with account_id, membership_id, and context

---

*This document is the official Identity Architecture specification for the project. All future implementation phases must follow this specification without redesigning the foundation. Any proposed changes to this architecture must go through a formal architecture review process.*
