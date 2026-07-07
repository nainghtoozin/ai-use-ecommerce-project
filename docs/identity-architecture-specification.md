# Identity Architecture Specification

---

## Executive Summary

This document defines the official engineering specification for the identity and
authentication architecture of this multi-tenant SaaS e-commerce platform. It replaces
the earlier audit (`docs/multi-role-email-identity-strategy.md`) with a prescriptive
design suitable for guiding implementation across three release versions.

The current architecture uses a single `users` table with a globally unique email
constraint. This prevents a natural person from holding multiple roles across multiple
tenants (e.g., a merchant who also shops as a customer in another store). The design
below introduces a clean separation between **Identity** (who you are) and **Membership**
(what you can do in each tenant), aligning with proven patterns from Shopify, Slack,
GitHub, and other production multi-tenant platforms.

---

## Design Goals

1.  **One email, many roles** — A single natural person uses one email to act as a
    merchant in one tenant, a customer in another, and a staff member in a third.
2.  **Tenant-scoped uniqueness** — An email is unique per tenant for each role type,
    but can exist across tenants.
3.  **Clean separation of concerns** — Identity data (email, password, verification) is
    separate from membership data (role, tenant, status).
4.  **Role-isolation** — A user's role in Tenant A has no bearing on their role in
    Tenant B.
5.  **Future-proof** — The design must support OAuth, team invitations, multi-device
    sessions, and soft-delete without structural changes.
6.  **Incremental migration** — The design must be reachable in steps without breaking
    the running system.
7.  **Audit transparency** — Every identity action (login, role change, membership
    removal) is logged with causality.

---

## Current Identity Architecture

### Tables

- **`users`** — Single table for all identities. `email` has a global `UNIQUE` index.
- **`password_reset_tokens`** — Keyed by `email` (primary key).
- **`sessions`** — Laravel default session table.

### Roles

- **`superadmin`** — Platform-level administrator. No tenant.
- **`admin`** — Tenant administrator (merchant, staff with admin role).
- **`customer`** — Storefront customer.

### Constraints

- `users.email` — `UNIQUE` globally. No email can appear twice regardless of tenant or
  role.
- `users.tenant_id` — Nullable foreign key. A user belongs to at most one tenant.
- `roles` table — Tenant-scoped via `(tenant_id, name, guard_name)` unique constraint.

### Registration Validation

- `CreateStoreController::store()` — `owner_email => unique:users,email`
- `RegisteredUserController::store()` — `email => unique:users`

---

## Current Problems

1.  **Global email uniqueness** — Same email cannot register in two different stores,
    cannot be both merchant and customer.
2.  **Single-tenant ownership** — A user belongs to exactly one tenant via
    `tenant_id`. No multi-tenant membership.
3.  **Identity-membership coupling** — Email/password/verification live on the same
    record as tenant-specific role and status.
4.  **No staff model** — Staff are "admin" role users with no distinction from the
    store owner.
5.  **No invitation flow** — Staff must be created manually; no invite-accept flow.
6.  **No OAuth readiness** — No `social_accounts` table or provider identifier columns.
7.  **Password reset is email-keyed** — Primary key on `password_reset_tokens.email`
    cannot support multiple identities sharing an email.

---

## Identity Principles

### Principle 1: Identity Is Global

An **Account** represents a natural person. It owns the email, password, and
verification status. An account is globally unique by email and exists independently
of any tenant.

### Principle 2: Membership Is Per-Tenant

A **Membership** links an Account to a Tenant with a Role. An account has exactly
one membership per tenant. An account can have zero, one, or many memberships across
different tenants.

### Principle 3: Role Is Per-Membership

A Role defines what an account can do within a specific tenant. The same account can
be an `admin` in one tenant and a `customer` in another. Permissions are derived from
the role of the current membership.

### Principle 4: Profile Is Role-Specific Extensions

A **Profile** extends a membership with role-specific data. A `customer` membership
has a `customer_profile` (name, phone, addresses). An `admin` membership has no
extension (admin data lives on the account or membership). A future `staff`
membership might have a `staff_profile` (position, department).

### Principle 5: Authentication Proves Identity, Authorization Checks Membership

- **Authentication** verifies email + password against the `accounts` table.
- **Authorization** checks the current membership's role and permissions against the
  requested action.
- A user who is authenticated but has no membership in the current tenant is
  unauthorized (unless they are a SuperAdmin).

---

## Recommended Architecture

### High-Level Model

```
┌─────────────────────────────────────────────────────────┐
│                       Account                            │
│  (email, password, verification, global identity)        │
└────────────┬────────────────────────────────────────────┘
             │ 1
             │
             │ * (one Account, many Memberships)
             │
┌────────────▼────────────────────────────────────────────┐
│                  Tenant Membership                       │
│  (tenant_id, role_id, is_owner, status, invited_by)      │
└────────────┬────────────────────────────────────────────┘
             │ 1
             │
             ├──────────────────────────────────┐
             │ (optional extension per role)    │
             │                                  │
┌────────────▼────────────┐    ┌────────────────▼───────────┐
│   Customer Profile      │    │     Staff Profile          │
│  (name, phone, etc.)    │    │  (position, department)    │
└─────────────────────────┘    └────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│                        Tenant                             │
│  (name, slug, settings, subscription)                     │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│                      Role (Spatie)                        │
│  (name, guard_name, tenant_id, permissions)              │
└─────────────────────────────────────────────────────────┘
```

---

## Account Model

### Responsibility

The `Account` model is the root identity. It represents a natural person and is the
subject of authentication. An account exists independently of any tenant.

### Fields

| Field | Type | Constraints | Purpose |
|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | AUTO_INCREMENT | Internal identifier |
| `email` | VARCHAR(255) | UNIQUE NOT NULL | Login identifier |
| `password` | VARCHAR(255) | NOT NULL | Bcrypt-hashed password |
| `email_verified_at` | TIMESTAMP | NULLABLE | Email verification timestamp |
| `remember_token` | VARCHAR(100) | NULLABLE | "Remember me" token |
| `profile_image` | VARCHAR(255) | NULLABLE | Avatar/profile photo |
| `notification_preferences` | JSON | NULLABLE | Global notification settings |
| `status` | VARCHAR(50) | DEFAULT 'active' | `active`, `suspended`, `banned` |
| `last_login_at` | TIMESTAMP | NULLABLE | Last successful login |
| `last_login_ip` | VARCHAR(45) | NULLABLE | IP from last login |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |
| `deleted_at` | TIMESTAMP | NULLABLE | Soft delete |

### Key Behaviors

- Email is globally unique. No two accounts can share the same email.
- An account with `status = 'suspended'` or `'banned'` cannot authenticate regardless
  of tenant membership.
- Soft-delete preserves audit trail. A soft-deleted account cannot authenticate.
- Password reset operates at the account level.

### Relationships

```php
class Account extends Authenticatable
{
    public function memberships(): HasMany;
    public function currentMembership(): HasOne;  // via session or default flag
    public function ownedTenants(): HasMany;       // through memberships where is_owner
}
```

---

## Membership Model

### Responsibility

The `TenantMembership` model (naming: `tenant_user` or `memberships`) links an Account
to a Tenant. It is the carrier of role, ownership flag, and status within the tenant.

### Fields

| Field | Type | Constraints | Purpose |
|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | AUTO_INCREMENT | |
| `account_id` | BIGINT UNSIGNED | FK -> accounts, ON DELETE CASCADE | |
| `tenant_id` | BIGINT UNSIGNED | FK -> tenants, ON DELETE CASCADE | |
| `role_id` | BIGINT UNSIGNED | FK -> roles, ON DELETE RESTRICT | Spatie role for this membership |
| `is_owner` | BOOLEAN | DEFAULT FALSE | Is this the store owner? |
| `status` | VARCHAR(50) | DEFAULT 'active' | `active`, `invited`, `suspended`, `removed` |
| `invited_by` | BIGINT UNSIGNED | NULLABLE, FK -> accounts | Who invited this membership |
| `invited_at` | TIMESTAMP | NULLABLE | When the invitation was sent |
| `joined_at` | TIMESTAMP | NULLABLE | When the invitation was accepted |
| `is_default` | BOOLEAN | DEFAULT FALSE | Default tenant for login |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |
| `deleted_at` | TIMESTAMP | NULLABLE | Soft delete |

### Constraints

- `UNIQUE (account_id, tenant_id)` — One membership per account per tenant.
- `UNIQUE (tenant_id, account_id)` — Same constraint, symmetric.

### Key Behaviors

- An account can have zero memberships (SuperAdmin-only accounts) or many memberships.
- `is_owner` can be true for at most one membership per tenant (tenant has one owner).
- `status = 'invited'` means the account has not accepted the invitation yet.
- `status = 'removed'` means the membership was terminated. The record is retained for
  audit.
- Soft-delete preserves history.

### Relationships

```php
class TenantMembership extends Model
{
    public function account(): BelongsTo;
    public function tenant(): BelongsTo;
    public function role(): BelongsTo;        // Spatie Role
    public function customerProfile(): HasOne; // if role = customer
    public function staffProfile(): HasOne;    // if role = admin/staff
}
```

---

## Merchant Model

### Definition

A **Merchant** is not a separate model. A merchant is an `Account` with a
`TenantMembership` where:
- `role_id` points to an `admin` role (tenant-scoped)
- `is_owner = true`

### Rules

- A tenant must have exactly one owner at any time.
- Ownership can be transferred (see Store Ownership Transfer flow below).
- The owner has all permissions within the tenant implicitly.
- The owner can create, update, and remove staff memberships.

### Merchant Profile

Merchant-specific business information (business name, tax ID, etc.) can be stored
either:
- On the `tenant` record itself (if it is store-level information), or
- On an optional `merchant_profiles` table linked to the membership.

Recommendation: Store business-level information on the `Tenant` model. Store
owner-specific information (if different from tenant-level) on a `merchant_profiles`
table.

---

## Customer Model

### Definition

A **Customer** is an `Account` with a `TenantMembership` where:
- `role_id` points to a `customer` role (tenant-scoped)
- `is_owner = false`

### Customer Profile

Customer-specific data lives in a `customer_profiles` table, linked to the membership:

| Field | Type | Purpose |
|---|---|---|
| `id` | PK | |
| `tenant_membership_id` | BIGINT UNSIGNED UNIQUE FK | |
| `name` | VARCHAR(255) | Display name in this store |
| `phone` | VARCHAR(20) NULLABLE | |
| `created_at` | | |
| `updated_at` | | |
| `deleted_at` | NULLABLE | |

### Customer Data Per Tenant

All customer data is **tenant-scoped**:
- `customer_addresses` — already has `tenant_id` + `user_id`. In the new model, this
  becomes `tenant_id` + `tenant_membership_id`.
- `orders` — already has `tenant_id` + `user_id`. Same migration path.
- `wishlists` — already has `tenant_id` + `user_id`. Same path.

### Cross-Tenant Customer Identity

The same account can have a `customer` membership in Store A and a `customer`
membership in Store B. Each membership has its own `customer_profile` with potentially
different names and phone numbers. Orders in Store A are visible only in Store A.

---

## Staff Model

### Definition

A **Staff member** is an `Account` with a `TenantMembership` where:
- `role_id` points to an `admin` role (tenant-scoped)
- `is_owner = false`
- `status = 'active'` or `'invited'`

### Staff Roles (Future)

Staff roles within the `admin` role can be refined through Spatie permissions. The
platform can define granular permission sets:

| Staff Type | Typical Permissions |
|---|---|
| **Manager** | All permissions except billing and user management |
| **Cashier** | Orders: view, create, update status; no product/price editing |
| **Inventory** | Products: view, create, update; no pricing, no orders |
| **Marketing** | Promotions, banners, coupons; no orders, no billing |
| **Support** | Orders: view, update status; customers: view; no billing |

### Staff Profile

Staff-specific data lives in a `staff_profiles` table:

| Field | Type | Purpose |
|---|---|---|
| `id` | PK | |
| `tenant_membership_id` | BIGINT UNSIGNED UNIQUE FK | |
| `position` | VARCHAR(100) NULLABLE | Job title |
| `department` | VARCHAR(100) NULLABLE | Department name |
| `permissions_overrides` | JSON NULLABLE | Additional permissions beyond role |
| `created_at` | | |
| `updated_at` | | |
| `deleted_at` | NULLABLE | |

---

## Role Strategy

### Current State

Spatie roles are already tenant-scoped via the `(tenant_id, name, guard_name)` unique
constraint. Roles `admin` and `customer` exist per tenant. Permissions are global
(not tenant-scoped).

### Recommended Strategy

**Keep the current Spatie setup** with these adjustments:

1. **Roles remain tenant-scoped.** Each tenant has its own `admin` and `customer`
   roles. Future roles (e.g., `manager`, `cashier`) are also tenant-scoped.

2. **Permissions remain global.** A permission named `orders.view` is a single record.
   It is assigned to tenant-scoped roles. This is correct behavior — the permission
   name is the same everywhere, but its assignment is per-tenant.

3. **Membership.role_id points to the Spatie Role.** Checking permissions for a
   membership is:
   ```
   $membership->role->hasPermissionTo('orders.view')
   ```

4. **Owner permissions.** The store owner's membership has `role_id` pointing to the
   `admin` role (which has all permissions). No special treatment needed — the
   `admin` role should grant all permissions.

5. **SuperAdmin role.** The `superadmin` role remains global (no `tenant_id`).
   SuperAdmin authentication uses a separate guard or bypass logic (as it does
   today).

### Permission Checking Flow

```
Authenticate → Get Account
  → Get current TenantMembership (from session)
    → Get Membership.role (Spatie Role)
      → Check $role->hasPermissionTo($permission)
```

---

## Permission Strategy

### What Permissions Belong To

Permissions belong to **Roles**, not to individual accounts or memberships.

- An `Account` has no inherent permissions.
- A `TenantMembership` has a `role_id` pointing to a Spatie `Role`.
- Permissions are assigned to `Role` via Spatie's `role_has_permissions` table.
- An account's effective permissions in a tenant are the permissions of the role
  assigned to its membership.

### Exception: Permission Overrides

For flexibility, the `staff_profiles.permissions_overrides` JSON field can store
additional permission names that are merged with (but do not replace) the role's
permissions. This allows a "Manager" role to have most permissions while a specific
manager also gets billing access without creating a custom role.

### Checking Permissions in Code

```php
// Gate policy
$user->can('orders.view')    // Current: $user = Account model
                             // Future: Gate resolves from current membership

// Explicit
$membership->hasPermission('orders.view')
// Internally: $this->role->hasPermissionTo('orders.view')
//   OR in_array('orders.view', $this->staffProfile->permissions_overrides ?? [])

// Middleware
// 'permission:orders.view' — checks the current membership's effective permissions
```

---

## Authentication Strategy

### Guard Configuration

```php
// config/auth.php (Future State)
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'accounts',
    ],
],

'providers' => [
    'accounts' => [
        'driver' => 'eloquent',
        'model' => App\Models\Account::class,
    ],
],
```

The guard authenticates against the `Account` model. After authentication, the
session stores:
- `account_id` — The authenticated account
- `current_tenant_membership_id` — The membership being used in this session

### Authentication Flow (Detailed)

```
1. User submits email + password
2. System looks up Account by email
3. If not found → "Invalid credentials"
4. If found but Account.status is suspended/banned → "Account suspended"
5. If found and password matches → Log in
6. Determine default tenant membership:
   a. If URL contains store slug → use membership for that tenant
   b. If session has existing membership → restore it
   c. If account has is_default membership → use that
   d. If account has exactly one membership → use that
   e. If account has multiple memberships → redirect to tenant selector
7. Store account_id + membership_id in session
8. Log audit event (login, IP, user-agent, tenant_id)
```

### Multi-Tenant Login

When an account has memberships in multiple tenants and the URL does not specify which
tenant to use:

1. Redirect to a **Tenant Switcher** page (conceptual: `/select-tenant`)
2. User picks which store to access
3. Session updates `current_tenant_membership_id`
4. User is redirected to that store's dashboard

This is identical to Slack's workspace switcher or GitHub's organization switcher.

---

## Authorization Strategy

### Middleware Stack

```
Route: /store/{store_slug}/admin/orders
├── IdentifyTenant       → Resolves tenant from URL slug
├── Authenticate          → Auth::check() against Account guard
├── ResolveMembership     → Finds membership for (account_id, tenant_id)
│                          → If none: 403 or redirect
│                          → Sets tenant context in session
├── CheckMembershipStatus → membership.status must be 'active'
│                          → 'invited': "Please accept invitation"
│                          → 'suspended': "Access denied"
├── CheckRoleOrPermission → membership.role has required permission
│                          → 'role:admin' or 'permission:orders.view'
├── ValidateTenantBinding → Route model binding scoping
├── EnsureTenantIsActive   → Tenant status + subscription check
└── CheckStoreLocked       → Mutation blocking check
```

### Policy Resolution

Laravel Gates and Policies resolve through the currently authenticated Account.

```php
// AuthServiceProvider (Version 4+)
Gate::before(function (Account $account, $ability) {
    $membership = $account->currentMembership();

    if (!$membership) {
        return false; // No tenant context → no permissions
    }

    // SuperAdmin bypass
    if ($account->isSuperAdmin()) {
        return true;
    }

    // Check role-based permission
    if ($membership->hasPermission($ability)) {
        return true;
    }

    return false; // Gate checks policies as fallback
});
```

### Authorization Checks in Controllers

```php
// Current pattern (works with minimal changes):
$this->authorize('orders.view');

// Under the hood in Version 4+:
// Gate resolves the Account, calls Gate::before(),
// checks current membership's permissions.
```

---

## Registration Flows

### Flow 1: Merchant Creates a Store

```
Visitor → /create-store → Form
  ├── Store name, slug, description
  ├── Owner name, email, password
  └── Submit

1. Validate:
   - tenant.slug: unique
   - owner_email: unique:accounts,email (or unique:users,email in V3)
   - password: confirmed, min 8

2. Transaction:
   a. Create Tenant (status: pending)
   b. Create Account (if new) OR find existing Account
   c. Create TenantMembership:
      - account_id, tenant_id
      - role_id → tenant's 'admin' role
      - is_owner = true
      - status = 'active'
   d. Create subscription
   e. Create default data (units, categories, etc.)

3. Dispatch Registered event (sends email verification)
4. Log in as the Account
5. Redirect to onboarding
```

### Flow 2: Customer Registers in a Store

```
Visitor → /store/{slug}/register → Form
  ├── Name, email, password
  └── Submit

1. Validate:
   - WebsiteInfo: allow_registration must be true
   - Tenant must exist and be active
   - email: unique within this tenant

   In V3: unique:users,email,NULL,id,tenant_id,{tenantId}
   In V4+: unique:tenant_memberships,NULL,id,account_id IN (SELECT id FROM accounts WHERE email = ?)

2. Transaction:
   a. Find or create Account by email
   b. If Account exists and already has membership in this tenant → error
   c. Create TenantMembership:
      - account_id, tenant_id
      - role_id → tenant's 'customer' role
      - is_owner = false
      - status = 'active'
   d. Create CustomerProfile:
      - name from form
   e. If Account was just created → Dispatch Registered event

3. Log in
4. Redirect to storefront
```

### Flow 3: Staff Invitation by Owner

```
Merchant → /store/{slug}/admin/staff/invite → Form
  ├── Email, role (Manager, Cashier, etc.)
  └── Submit

1. Validate:
   - Authorize: current membership is owner OR has 'users.create' permission
   - Email format valid

2. Transaction:
   a. Find or create Account by email
   b. If Account has membership in this tenant → error (already a member)
   c. Create TenantMembership:
      - account_id, tenant_id
      - role_id → tenant's selected staff role
      - is_owner = false
      - status = 'invited'
      - invited_by = current account id
      - invited_at = now

3. Send invitation notification to email
   - Notification contains accept link: /store/{slug}/accept-invitation?token=...
   - If Account does not exist yet, notification includes temporary password setup

4. Return success to merchant
```

### Flow 4: Invitation Acceptance

```
Recipient → /store/{slug}/accept-invitation?token=...
  ├── If logged in → confirm and join
  ├── If not logged in → login or register first, then confirm
  └── Submit

1. Validate token and membership
2. Update TenantMembership:
   - status = 'active'
   - joined_at = now
3. Redirect to store dashboard
```

---

## Login Flows

### SuperAdmin Login

```
GET/POST /superadmin/login → AuthenticatedSessionController
1. Authenticate against Account (or User in V3)
2. Must have 'superadmin' role
3. No tenant context needed
4. Redirect to /superadmin/dashboard
```

### Merchant/Staff Login (Storefront Admin)

```
GET/POST /store/{slug}/admin/login → StorefrontLoginController
1. IdentifyTenant middleware resolves tenant from slug
2. Authenticate against Account
3. Find TenantMembership for (account_id, tenant_id)
4. If membership.status is 'invited' → "Please accept invitation"
5. If membership.status is 'suspended' → "Account suspended in this store"
6. If no membership → "You don't have access to this store"
7. Set session: current_tenant_membership_id
8. If role is 'admin' → redirect to storefront admin dashboard
```

### Customer Login

```
GET/POST /store/{slug}/login → StorefrontLoginController
1. IdentifyTenant middleware resolves tenant from slug
2. Authenticate against Account
3. Find TenantMembership for (account_id, tenant_id)
4. If no membership → check if this is a legacy user matching by email
   → In V3, find user by email where tenant_id matches
   → In V4+, no membership means no access; redirect to register
5. Set session: current_tenant_membership_id
6. Redirect to storefront
```

### Root Login Restriction

The root `/login` route (outside a store context) remains **SuperAdmin only**. Tenant
users (admins, customers) must log in through their store URL. This is the same
behavior as the current system.

---

## Password Reset Strategy

### Current Behavior

- Password reset is email-keyed (`password_reset_tokens.email` = PK)
- `User::sendPasswordResetNotification()` customizes URL per tenant
- `NewPasswordController::store()` redirects based on `$user->tenant->slug`

### Version 3 (Same Database, Relaxed Uniqueness)

- `Password::sendResetLink()` may find multiple User records with the same email
- Behavior: Reset the password for **all** User records with that email
  - Find all User records where `email = ?`
  - Update `password` for all of them
  - This is safe because the same person owns all those records (same email = same person)
- The reset notification still customizes the URL per tenant
- `password_reset_tokens.email` remains PK — but since all records share the email,
  the token look-up still works

### Version 4+ (Accounts Table)

- Password reset operates on the `accounts` table
- `Password::broker('accounts')` — new password broker for accounts
- `password_reset_tokens.account_id` — keyed by account ID, not email
- Resetting the account password affects all memberships (the person uses the same
  password everywhere)
- Reset link sends to the account's email
- Reset notification does not need a tenant-specific URL — the user resets their
  account password and then logs in through any store

### Tenant-Specific Reset (Optional Enhancement)

If the UX requires tenant-specific password reset (e.g., "Reset My Store X Password"):

1. Email-based lookup finds the account
2. The reset link includes a `store_slug` parameter
3. After reset, the user is redirected to `/store/{slug}/login`

---

## Email Verification Strategy

### Version 3 (Same Database, Relaxed Uniqueness)

- `email_verified_at` remains on the `User` record
- If the same email exists across multiple User records, verifying one verifies all
  (propagation — the person proved they own the email)
- Verification link contains `{id}`, which identifies a specific User record
- On verification, set `email_verified_at` for all User records with that email

### Version 4+ (Accounts Table)

- `email_verified_at` moves to the `accounts` table
- One verification covers all memberships
- Verification notification sends once
- `MustVerifyEmail` contract is implemented by `Account`
- `Verified` event fires once, not once per membership

---

## Invitation Strategy

### Table

No new table needed. The `TenantMembership` model already has:
- `status = 'invited'`
- `invited_by` (who sent the invite)
- `invited_at` (when sent)
- `joined_at` (when accepted)

### Token

Invitation tokens can be stored in a new `invitation_tokens` table, or as a signed
URL parameter, or using Laravel's signed URL feature:

```
/accept-invitation/{tenant_membership_id}?expires=...&signature=...
```

### Flow

```
1. Owner creates invite → TenantMembership created with status='invited'
2. System generates signed URL → sent via email
3. Recipient clicks link:
   a. If authenticated as the target account
      → Membership status updated to 'active', joined_at = now
   b. If not authenticated
      → Redirect to login/register, then accept
   c. If authenticated as a different account
      → Error: "This invitation was sent to a different email"
4. After acceptance, redirect to tenant with appropriate role
```

### Invitation Expiry

- Invitations expire after 7 days (configurable)
- `TenantMembership` with `status = 'invited'` and `invited_at < now - 7 days`
  are automatically cleaned up or marked as `expired`

---

## Tenant Resolution Strategy

### Current System

`IdentifyTenant` middleware resolves the current tenant from:
1. Authenticated user's `tenant_id`
2. Subdomain
3. `X-Tenant` header
4. Session
5. Default tenant

### Future System (Version 4+)

`IdentifyTenant` middleware resolves the current tenant from:
1. URL path (`/store/{slug}/...`)
2. Subdomain (`store.mysaas.com`)
3. Authenticated account's current membership's tenant (from session)
4. Session (`current_tenant_membership_id` → `tenant_id`)
5. Default tenant

The `Storefront` middleware (which sets tenant context from the URL slug) becomes the
primary resolver for store-scoped routes.

### Tenant Switching

An Account with multiple memberships can switch tenants without re-authentication:

```
POST /switch-tenant
Payload: { tenant_id: 123 }

1. Verify Account has an active membership for tenant_id
2. Update session: current_tenant_membership_id
3. Redirect to the new tenant's base URL
```

This is analogous to GitHub's organization switcher or Slack's workspace switcher.

---

## Session Strategy

### Version 3 (Minimal Change)

- Laravel default session driver (database, file, or Redis)
- Session stores `user_id` (as today)
- No multi-tenant session support needed (user has one tenant_id)

### Version 4+ (Multi-Tenant Sessions)

Session stores:
| Key | Value | Purpose |
|---|---|---|
| `account_id` | INT | Authenticated account |
| `current_tenant_membership_id` | INT | Current active membership |
| `current_tenant_id` | INT | Denormalized for quick access |
| `ip_address` | VARCHAR(45) | For audit and security |
| `user_agent` | TEXT | For audit and security |
| `last_activity` | INT | Session expiry |

### Multi-Device Support

- Each login creates a new session (Laravel default behavior)
- "Remember me" uses `remember_token` on the `Account` model
- Account can view and revoke active sessions (future feature)
- Session revocation uses the `sessions` table with `account_id` filter

### Concurrent Sessions by Tenant

An Account can be logged in to multiple tenants simultaneously (e.g., tab for Store A
admin, tab for Store B storefront). Each session stores a different
`current_tenant_membership_id`. This is handled automatically because each tenant has
a different URL path, and the `IdentifyTenant` middleware sets the context per request.

---

## OAuth Readiness

### Required Schema

```sql
CREATE TABLE social_accounts (
    id                BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    account_id        BIGINT UNSIGNED NOT NULL,
    provider          VARCHAR(50)  NOT NULL,      -- 'google', 'github', 'apple', 'microsoft'
    provider_id       VARCHAR(255) NOT NULL,       -- User ID from provider
    provider_email    VARCHAR(255) NULLABLE,
    avatar_url        VARCHAR(500) NULLABLE,
    token             TEXT NULLABLE,                -- Encrypted access token
    refresh_token     TEXT NULLABLE,                -- Encrypted refresh token
    expires_at        TIMESTAMP NULLABLE,
    created_at        TIMESTAMP,
    updated_at        TIMESTAMP,
    UNIQUE (provider, provider_id),
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
);
```

### OAuth Login Flow

```
1. User clicks "Sign in with Google"
2. Provider redirects to our callback
3. Look up social_accounts by (provider, provider_id)
4. If found → authenticate the linked Account
5. If not found:
   a. Check if provider email matches an existing Account
   b. If yes → link social account to existing Account → authenticate
   c. If no → create new Account → create social_accounts link → authenticate
6. After authentication, proceed with tenant resolution (same as email login)
```

### OAuth Registration

```
1. User clicks "Sign up with Google"
2. Provider redirects to our callback
3. Create Account (if new) with verified email
4. Create social_accounts link
5. If registration was initiated within a store context:
   → Create TenantMembership with 'customer' role
6. If registration was platform-level:
   → Redirect to tenant creation or tenant selector
```

---

## Database Design

### Table: `accounts`

Primary identity table. One record per natural person.

| Column | Type | Constraints |
|---|---|---|
| id | BIGINT UNSIGNED | PK AUTO_INCREMENT |
| email | VARCHAR(255) | UNIQUE NOT NULL |
| password | VARCHAR(255) | NOT NULL |
| email_verified_at | TIMESTAMP | NULLABLE |
| remember_token | VARCHAR(100) | NULLABLE |
| profile_image | VARCHAR(255) | NULLABLE |
| notification_preferences | JSON | NULLABLE |
| status | VARCHAR(50) | DEFAULT 'active' |
| last_login_at | TIMESTAMP | NULLABLE |
| last_login_ip | VARCHAR(45) | NULLABLE |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |
| deleted_at | TIMESTAMP | NULLABLE |

### Table: `tenant_memberships`

Links accounts to tenants with role and ownership.

| Column | Type | Constraints |
|---|---|---|
| id | BIGINT UNSIGNED | PK AUTO_INCREMENT |
| account_id | BIGINT UNSIGNED | FK -> accounts, ON DELETE CASCADE |
| tenant_id | BIGINT UNSIGNED | FK -> tenants, ON DELETE CASCADE |
| role_id | BIGINT UNSIGNED | FK -> roles, ON DELETE RESTRICT |
| is_owner | BOOLEAN | DEFAULT FALSE |
| status | VARCHAR(50) | DEFAULT 'active' |
| invited_by | BIGINT UNSIGNED | FK -> accounts, NULLABLE |
| invited_at | TIMESTAMP | NULLABLE |
| joined_at | TIMESTAMP | NULLABLE |
| is_default | BOOLEAN | DEFAULT FALSE |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |
| deleted_at | TIMESTAMP | NULLABLE |
| UNIQUE | | (account_id, tenant_id) |

### Table: `customer_profiles`

Tenant-scoped customer data. One per customer membership.

| Column | Type | Constraints |
|---|---|---|
| id | BIGINT UNSIGNED | PK AUTO_INCREMENT |
| tenant_membership_id | BIGINT UNSIGNED | UNIQUE FK -> tenant_memberships, ON DELETE CASCADE |
| name | VARCHAR(255) | NOT NULL |
| phone | VARCHAR(20) | NULLABLE |
| metadata | JSON | NULLABLE |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |
| deleted_at | TIMESTAMP | NULLABLE |

### Table: `staff_profiles`

Tenant-scoped staff data. One per admin/staff membership.

| Column | Type | Constraints |
|---|---|---|
| id | BIGINT UNSIGNED | PK AUTO_INCREMENT |
| tenant_membership_id | BIGINT UNSIGNED | UNIQUE FK -> tenant_memberships, ON DELETE CASCADE |
| position | VARCHAR(100) | NULLABLE |
| department | VARCHAR(100) | NULLABLE |
| permissions_overrides | JSON | NULLABLE |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |
| deleted_at | TIMESTAMP | NULLABLE |

### Table: `social_accounts`

OAuth provider links.

| Column | Type | Constraints |
|---|---|---|
| id | BIGINT UNSIGNED | PK AUTO_INCREMENT |
| account_id | BIGINT UNSIGNED | FK -> accounts, ON DELETE CASCADE |
| provider | VARCHAR(50) | NOT NULL |
| provider_id | VARCHAR(255) | NOT NULL |
| provider_email | VARCHAR(255) | NULLABLE |
| avatar_url | VARCHAR(500) | NULLABLE |
| token | TEXT | NULLABLE (encrypted) |
| refresh_token | TEXT | NULLABLE (encrypted) |
| expires_at | TIMESTAMP | NULLABLE |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |
| UNIQUE | | (provider, provider_id) |

### Existing Tables (Compatible With Minimal Changes)

| Table | Change Required | Reason |
|---|---|---|
| `users` | Deprecated in V4+. New data written to `accounts` + `tenant_memberships`. Reads use a view or legacy model. | Migration target. |
| `tenants` | No structural change. | Remains the root tenant entity. |
| `roles` | No change. Already tenant-scoped via `(tenant_id, name, guard_name)`. | Spatie roles remain. |
| `permissions` | No change. | Spatie permissions remain. |
| `customer_addresses` | Add `tenant_membership_id` FK, keep `user_id` for backward compat. | Migration path. |
| `orders` | Add `tenant_membership_id` FK, keep `user_id` for backward compat. | Migration path. |
| `sessions` | Change `user_id` to `account_id`. Add `tenant_membership_id`. | Session storage. |
| `password_reset_tokens` | Change PK from `email` to `account_id` (V4+). | Unique per account. |

---

## Entity Relationship Explanation

### Core Entities

```
Account (1) ────── (0..*) TenantMembership (0..*) ────── (1) Tenant
                    │
                    ├── (0..1) CustomerProfile
                    │
                    └── (0..1) StaffProfile
```

- **Account** is the root identity. Exists independently.
- **TenantMembership** is the link. Requires both Account and Tenant.
- **CustomerProfile** exists only for customer-role memberships.
- **StaffProfile** exists only for admin/staff-role memberships.

### Tenant Context

```
Tenant (1) ──── (0..*) TenantMembership ──── (0..*) Account
  │
  └── (1) WebsiteInfo (singleton per tenant)
  └── (0..*) Product, Order, Category, etc. (tenant-scoped business tables)
```

### Authentication Context

```
Session (1) ──── (1) Account (authenticated via 'web' guard)
  │
  └── (1) TenantMembership (current context, stored in session)
       │
       └── (1) Tenant (resolved from membership)
```

### Spatie Authorization Context

```
TenantMembership (1) ──── (1) Role (Spatie, tenant-scoped)
                           │
                           └── (0..*) Permission (Spatie, global)
```

---

## Industry Comparison

### Shopify

**Pattern**: One account can own multiple stores (merchant identity). Customers are
per-store records with separate login credentials.

**What to adopt**: Multi-store ownership for merchants. One login, many stores.

**What to avoid**: Per-store customer credentials. Customers should use one email
across stores with tenant-scoped profiles.

### Slack

**Pattern**: One account with many workspace memberships. Workspace switcher.
Membership has a role (owner, admin, member, guest). Invitation flow.

**What to adopt**: Tenant membership model. Workspace (tenant) switcher. Invitation
with accept flow. This is the closest match to the proposed architecture.

**What to avoid**: Slack's model has no concept of "customer" vs "admin" —
membership is just a role. We need role-specific profile extensions.

### GitHub

**Pattern**: One account with many organization memberships. Organization roles
(owner, member, outside collaborator). Team-based permission groups. OAuth is
first-class.

**What to adopt**: Organization (tenant) membership model. OAuth integration
pattern. Team-based permission grouping (future).
**What to avoid**: GitHub's outside collaborator pattern (not applicable to
e-commerce).

### Notion

**Pattern**: One account with many workspace memberships. Guest access for
external collaborators.

**What to adopt**: Guest concept for limited tenant access (future).

### BigCommerce

**Pattern**: Similar to Shopify. One account, multiple stores. Customers are
per-store.

**What to adopt**: Same as Shopify.

### Laravel Jetstream Teams

**Pattern**: Team-based membership within a single application. User belongs to a
team, team has a role, user can switch teams.

**What to adopt**: The team membership + team switcher pattern is directly
applicable to tenant membership.

**What to avoid**: Jetstream's role assignment is simpler than what we need.
We need Spatie permission granularity, not just "owner" / "member".

---

## Version 3 Plan

### Goal

Make the minimum viable change to enable same-email cross-tenant registration
while keeping the existing `users` table and auth flow.

### Changes

1. **Change `users.email` unique index** from `UNIQUE (email)` to
   `UNIQUE (tenant_id, email)`.
   - Allows same email in multiple tenants
   - Still prevents duplicate registration within the same tenant
   - SuperAdmin (null tenant_id) emails remain unique by default (null is treated
     as a distinct value in MySQL composite unique indexes)

2. **Update `RegisteredUserController` validation** from `unique:users` to
   tenant-scoped uniqueness:
   ```
   unique:users,email,NULL,id,tenant_id,{currentTenantId}
   ```

3. **Update `CreateStoreController` validation** — keep `unique:users,email` for
   owner registration (one store per email in V3), or relax to allow multi-store
   ownership depending on business requirements.

4. **Update password reset** — When `Password::reset()` finds a User, update
   password for all User records sharing that email.

5. **Update email verification** — When a User record is verified, propagate
   `email_verified_at` to all User records sharing that email.

### Risks

- Low risk. The change is isolated to one index and two validation rules.
- Existing users are unaffected — their emails remain valid.
- Backward compatible.

---

## Version 4 Plan

### Goal

Full identity refactor: introduce `accounts` + `tenant_memberships` + profiles.

### Phase 1: Foundation

- Create `accounts` table
- Create `tenant_memberships` table
- Create `customer_profiles` table
- Create `staff_profiles` table
- Create `Account` model (extends `Authenticatable`)

### Phase 2: Data Migration

Script that:
1. For each unique email in `users`, create an `Account` record
2. For each `User` record, create a `TenantMembership`:
   - `account_id` from step 1
   - `tenant_id` from user's tenant_id
   - `role_id` from user's Spatie role
   - `is_owner` from user's is_owner flag
   - `status` from user's status
3. For each customer-role membership, create a `CustomerProfile`
4. For each admin-role membership (non-owner), create a `StaffProfile`

### Phase 3: Authentication Update

- Change `config/auth.php` provider to `accounts`
- Update `LoginRequest` to authenticate against `Account`
- Update `AuthenticatedSessionController` and `StorefrontLoginController` to
  resolve membership after authentication
- Update `RegisteredUserController` to create Account + Membership + Profile

### Phase 4: Session & Middleware Update

- Add `current_tenant_membership_id` to session after login
- Update `CheckTenantAccess` middleware to check membership existence
- Add `ResolveMembership` middleware
- Add tenant switcher endpoint

### Phase 5: Authorization Update

- Update `Gate::before()` to check permissions through current membership
- Update `RoleMiddleware` to check membership role
- Update `UserPolicy` to work with `Account` model
- Add `CustomerOrderPolicy` and `CustomerAddressPolicy` updates

### Phase 6: Deprecation

- Create a `User` legacy model (backed by a view or direct table reads) for
  backward compatibility
- Log warnings when legacy model is accessed
- Migrate all new code to use `Account` + `TenantMembership`

### Phase 7: Cleanup

- Audit all code references to `App\Models\User`
- Replace with `App\Models\Account` or `App\Models\TenantMembership`
- Drop `users` table after all references are removed

---

## Version 5 Plan

### Goal

OAuth, advanced team management, multi-device session management.

### Features

1. **Social Accounts** — Create `social_accounts` table, implement OAuth login
   for Google, GitHub, Apple, Microsoft

2. **Staff Role Management UI** — Granular permission assignment, role templates,
   staff invite flow UI

3. **Multi-Device Session Management** — View active sessions per account, revoke
   sessions remotely

4. **Audit Dashboard** — Complete audit trail of all identity events (login,
   membership change, role change, invitation, removal)

5. **Guest Access** — Limited tenant access for external collaborators (consultants,
   accountants) with restricted permissions

6. **Team-Based Permissions** — Group staff into teams, assign permissions to
   teams (inspired by GitHub teams)

---

## Migration Strategy (High Level Only)

### Principle

Every migration step must be reversible and backward compatible. No data loss.

### Step 1: Schema Migration (V3)

```sql
-- Drop global unique index on email
ALTER TABLE users DROP INDEX users_email_unique;

-- Add composite unique index
ALTER TABLE users ADD UNIQUE INDEX users_tenant_email_unique (tenant_id, email);
```

### Step 2: New Tables (V4 P1)

```sql
-- Create accounts table
CREATE TABLE accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    email_verified_at TIMESTAMP NULL,
    remember_token VARCHAR(100) NULL,
    profile_image VARCHAR(255) NULL,
    notification_preferences JSON NULL,
    status VARCHAR(50) DEFAULT 'active',
    last_login_at TIMESTAMP NULL,
    last_login_ip VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    UNIQUE (email)
);

-- Create tenant_memberships table
CREATE TABLE tenant_memberships (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT UNSIGNED NOT NULL,
    tenant_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    is_owner BOOLEAN DEFAULT FALSE,
    status VARCHAR(50) DEFAULT 'active',
    invited_by BIGINT UNSIGNED NULL,
    invited_at TIMESTAMP NULL,
    joined_at TIMESTAMP NULL,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    UNIQUE (account_id, tenant_id),
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT
);
```

### Step 3: Data Copy (V4 P2)

```php
// Pseudocode for data migration
DB::transaction(function () {
    // De-duplicate users by email, create accounts
    $users = User::all()->groupBy('email');
    foreach ($users as $email => $userGroup) {
        $first = $userGroup->first();
        $account = Account::create([
            'email' => $email,
            'password' => $first->password,
            'email_verified_at' => $first->email_verified_at,
            'remember_token' => $first->remember_token,
            'profile_image' => $first->profile_image,
            'status' => $first->status,
        ]);

        foreach ($userGroup as $user) {
            TenantMembership::create([
                'account_id' => $account->id,
                'tenant_id' => $user->tenant_id,
                'role_id' => $user->roles()->first()->id ?? null,
                'is_owner' => $user->is_owner,
                'status' => 'active',
                'is_default' => false,
            ]);
        }
    }
});
```

### Step 4: Switchover (V4 P3-P5)

1. Deploy new code that reads from `accounts` + `tenant_memberships`
2. Keep `users` table for reads during transition (dual-read strategy)
3. After verifying correctness, switch all reads to new tables
4. Remove legacy `User` model reference
5. Drop `users` table (after a full release cycle of verification)

---

## Risks

### Risk 1: Data Migration Complexity (V4)

**Risk**: Migrating existing `users` with globally unique emails to the new schema
when some emails were blocked from reuse. After the V3 change, new cross-tenant
registrations become possible, but existing users' data is already in place.

**Mitigation**: No data needs to change during V3. During V4, each unique email
becomes one account, and each existing User record becomes one membership. No data
is lost or merged aggressively.

### Risk 2: Password Reset Ambiguity (V3)

**Risk**: After relaxing uniqueness, `Password::sendResetLink()` may find the wrong
User record or behave unpredictably with multiple records sharing an email.

**Mitigation**: Update the password reset flow to reset ALL records sharing that
email. Since the same person owns all records (same email = same person), this is
safe and desirable.

### Risk 3: Session Context Confusion (V4)

**Risk**: An Account with memberships in multiple tenants might access a URL for
the wrong tenant, causing cross-tenant data exposure or authorization errors.

**Mitigation**: The `IdentifyTenant` middleware resolves the tenant from the URL
first. The `CheckTenantAccess` middleware validates that the account has a
membership for that tenant. If not, the user is redirected appropriately. The
`ValidateTenantBinding` middleware ensures route-model-bound entities belong to
the resolved tenant.

### Risk 4: SuperAdmin Edge Case

**Risk**: SuperAdmin accounts have `null tenant_id`. After the composite unique
index change in V3, MySQL treats `NULL` values in a composite unique index as
distinct (multiple rows with `(NULL, same_email)` are allowed).

**Mitigation**: Add a `WHERE tenant_id IS NULL` condition to SuperAdmin
registration validation to manually enforce global uniqueness for SuperAdmin
emails. Or ensure SuperAdmin creation uses `unique:users,email` with a
`->whereNull('tenant_id')` modifier.

### Risk 5: Third-Party Package Compatibility

**Risk**: Packages like Spatie Permission and Laravel Nova (if used) may expect
the `User` model to be the authenticatable model.

**Mitigation**: Configure Spatie to use the `Account` model:
```php
// config/auth.php
'model' => App\Models\Account::class,
```

Update Spatie's `permission.php` to reference the `Account` model. Keep the
`User` model as a wrapper or alias during transition.

---

## Trade-offs

### Trade-off 1: Single Auth Guard vs. Multiple Guards

**Single guard (recommended)**: One `web` guard, one `accounts` provider.
- Simpler configuration
- No guard switching
- Requires session-level tenant context

**Multiple guards**: Separate guards for `web-admin`, `web-customer`, `api`.
- More complex configuration
- Guard switching required
- More explicit about context

**Decision**: Single guard. The tenant membership model provides all the context
needed. Multiple guards would add complexity without significant benefit for this
architecture.

### Trade-off 2: Separate Customer Table vs. Membership + Profile

**Separate customer table**: A `customers` table separate from `users`/`accounts`,
with its own email and password. Used by Shopify, BigCommerce.

**Membership + Profile (recommended)**: Customers are Account memberships with
customer profiles. Uses one email across tenants.

**Decision**: Membership + Profile. The separate customer table pattern forces
customers to manage multiple credentials across stores. The membership pattern
provides a unified login with tenant-scoped profiles.

### Trade-off 3: Direct Spatie Role on Membership vs. Custom Role System

**Direct Spatie role**: `tenant_memberships.role_id` points directly to a
Spatie `Role` record.

**Custom role system**: A new `team_roles` table with `tenant_memberships` having
a `role` string, and a separate mapping to Spatie permissions.

**Decision**: Direct Spatie role. Reusing Spatie's existing role infrastructure
avoids maintaining a parallel permission system. The existing tenant-scoped roles
in Spatie already match this use case.

### Trade-off 4: Account-Level Password vs. Membership-Level Password

**Account-level (recommended)**: One password for all memberships. Reset once,
affects all.

**Membership-level**: Separate password per tenant membership. A person manages
multiple passwords.

**Decision**: Account-level password. Multiple passwords per person is poor UX
and creates security risks (password reuse, forgotten passwords).

---

## Final Engineering Recommendation

### Implement Version 3 Now

The Version 3 changes are low-risk, backward compatible, and solve the immediate
problem of email reuse across tenants. They can be deployed in a single release
cycle.

1. Change `users.email` index to composite `(tenant_id, email)`
2. Update `RegisteredUserController` validation to tenant-scoped unique
3. Update password reset to handle multiple User records per email
4. Update email verification to propagate across shared emails

### Plan Version 4 as the Next Major Release

The Version 4 refactor to `accounts` + `tenant_memberships` is the correct
long-term architecture. It aligns with Slack, GitHub, and Shopify patterns, and
it enables every future requirement in this document.

1. Create new tables without dropping old ones
2. Run data migration (de-duplicate users by email into accounts)
3. Update auth guard to use accounts provider
4. Add membership resolution middleware
5. Update authorization to check through memberships
6. Deprecate old `users` table
7. Full release cycle for stabilization before cleanup

### Reserve Version 5 for OAuth and Advanced Features

OAuth, multi-device session management, advanced team management, and guest
access are valuable features but not blocking for the core identity refactor.

### Why Not Skip Straight to Version 4?

Because Version 3 delivers the critical business value (cross-tenant email reuse)
in days, with zero schema migration risk and no code refactoring. Version 4
requires careful data migration, new models, updated middleware, and extensive
testing across all routes. Version 3 solves the symptom while Version 4 solves
the root cause — both are needed, but at different cadences.
