# Multi-Role Email & Identity Strategy — Architecture Audit

---

## Executive Summary

This document is a complete architecture audit of the current Identity and Email strategy.
It documents exactly how authentication, registration, authorization, and tenant isolation
work today, analyzes every identity scenario against production SaaS requirements, and
provides a recommended architecture for Version 3 and Version 4.

The single most critical finding is that the `users.email` column has a **database-level
UNIQUE constraint** enforced by **every registration validation rule**. This means the same
email address can never exist twice in the system, regardless of role (SuperAdmin, merchant,
customer) or tenant (Store A, Store B). A person who registers as a customer in one store
cannot register as a customer in another store, and a merchant who owns a store cannot also
shop as a customer in another merchant's store.

This is fundamentally incompatible with production multi-tenant SaaS expectations, where a
single natural person should be able to hold multiple roles across multiple tenants without
creating separate email aliases.

---

## Current Architecture

### Identity Model

There is exactly **one identity model**: `App\Models\User`.

This single model represents every type of user in the system:

| Identity | Spatie Role | `is_owner` | `tenant_id` |
|---|---|---|---|
| **SuperAdmin** | `superadmin` | `false` | `null` |
| **Store Owner (Merchant)** | `admin` | `true` | points to tenant |
| **Store Admin (Staff)** | `admin` | `false` | points to tenant |
| **Customer** | `customer` | `false` | points to tenant |
| **Guest** | N/A (no User record) | — | — |

There is **no** separate `Customer` model, `Merchant` model, `Admin` model, or `Staff`
model. Identity differentiation is entirely through Spatie roles (`superadmin`, `admin`,
`customer`) and business logic flags (`is_owner`, `tenant_id`).

### Database Schema

All identity records live in a single `users` table:

```
users
├── id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
├── tenant_id           BIGINT UNSIGNED NULLABLE FK -> tenants(id) ON DELETE SET NULL
├── name                VARCHAR(255)
├── email               VARCHAR(255) UNIQUE               ← GLOBAL UNIQUE CONSTRAINT
├── email_verified_at   TIMESTAMP NULLABLE
├── is_owner            BOOLEAN DEFAULT FALSE
├── password            VARCHAR(255)
├── remember_token      VARCHAR(100) NULLABLE
├── status              VARCHAR(255) DEFAULT 'active'
├── profile_image       VARCHAR(255) NULLABLE
├── allow_cod           BOOLEAN DEFAULT FALSE
├── notification_preferences JSON NULLABLE
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP
```

The `email` column has a **unique index** at the database level:
`CREATE UNIQUE INDEX users_email_unique ON users(email)`.

There is no composite unique constraint involving both `tenant_id` and `email`. The
uniqueness is **global across all tenants and all roles**.

---

## Current Authentication Flow

### Guards

A single authentication guard is used:

```php
// config/auth.php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
],
```

There is **one guard**, **one provider**, **one password broker**. There are no separate
guards for admin vs customer, no multi-tenant guard, and no API guard (Sanctum is not
installed).

### Login Flows

There are two login controllers and three login entry points:

| Entry Point | Controller | Who Can Login |
|---|---|---|
| `GET|POST /login` | `AuthenticatedSessionController` | SuperAdmin **only**. Tenant users with `tenant_id` set and no `superadmin` role are rejected with "Please login through your store URL." |
| `GET|POST /store/{store_slug}/login` | `StorefrontLoginController` | Any user whose `tenant_id` matches the current tenant, or legacy users with `null tenant_id`. |
| `GET|POST /store/{store_slug}/admin/login` | `StorefrontLoginController` | Same as above (same controller, different route). |

### Login Validation (LoginRequest)

The `LoginRequest` form request validates:
- `email`: required, string, email format
- `password`: required, string

Authentication uses `Auth::attempt()` with standard email/password matching. Rate limiting
is applied (5 attempts per email+IP combination).

### Logout Flow

`AuthenticatedSessionController::destroy()` determines the redirect context (superadmin,
admin, storefront) from the POST data and redirects to the appropriate login page.

---

## Email Ownership Model

### Tables That Own Email Addresses

| Table | Column | Purpose | Unique? |
|---|---|---|---|
| `users` | `email` | User login email | YES — global UNIQUE index |
| `tenants` | `email` | Store contact email (nullable) | NO |
| `orders` | `email` | Guest/customer order email (denormalized) | NO |
| `website_infos` | `contact_email`, `support_email` | Site contact emails | NO |
| `platform_settings` | `support_email` | Platform support email | NO |
| `password_reset_tokens` | `email` | Password reset token lookup | Primary Key |

### Tables That Enforce Uniqueness

Only **one column** in the entire database enforces email uniqueness:

- `users.email` — `UNIQUE` index at the database level

### Tenant Email Field

The `tenants.email` field is **nullable** and has **no unique constraint**. It stores a
contact email for the store, which may or may not match the owner's login email. This is a
business contact field, not an identity field.

---

## Identity Relationships

```
User (1) ──→ Tenant (1)        A user belongs to at most one tenant
Tenant (1) ──→ User (*)        A tenant has many users

User (1) ──→ Order (*)         A user has many orders (via user_id)
Order ──→ email (denormalized) Guest email stored directly on Order

User (1) ──→ CustomerAddress (*)  A user has many addresses
```

Key observation: A `User` belongs to exactly **one** `Tenant` (or zero for SuperAdmins).
There is no pivot table allowing a user to belong to multiple tenants.

---

## Current Validation Rules

### Merchant Registration (CreateStoreController)

```php
$request->validate([
    'owner_name'  => 'required|string|max:255',
    'owner_email' => 'required|email|max:255|unique:users,email',
    'password'    => ['required', 'confirmed', Rules\Password::defaults()],
]);
```

The `owner_email` field is validated with `unique:users,email`. This means a new store
**cannot** be created with an email that already exists in the `users` table, regardless
of role (customer, admin, superadmin).

### Customer Registration (RegisteredUserController)

```php
$request->validate([
    'name'     => ['required', 'string', 'max:255'],
    'email'    => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
    'password' => ['required', 'confirmed', Rules\Password::defaults()],
]);
```

Same constraint: `unique:users` (equivalent to `unique:users,email`). A customer cannot
register with an email that belongs to any existing user, including a customer in another
store, an admin, or a superadmin.

### Login Request

```php
// No email uniqueness check — only format validation
'email'    => ['required', 'string', 'email'],
'password' => ['required', 'string'],
```

---

## Current Database Constraints

### `users` table

```
- PRIMARY KEY (id)
- UNIQUE INDEX (email)
- FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL
```

There is **no** composite unique constraint on `(tenant_id, email)`. The `email` field is
globally unique across all users regardless of tenant or role.

### `password_reset_tokens` table

```
- PRIMARY KEY (email)
```

The `password_reset_tokens` table uses `email` as the primary key. This means only one
password reset token can exist per email at any given time. If a user has the same email
in multiple roles (currently impossible due to the unique constraint, but would be relevant
in a future architecture), only one role could have a pending password reset.

---

## Current Risks

### Risk 1: Email Cannot Be Reused Across Roles

A person who registers as a customer in Store A cannot register as a customer in Store B
using the same email. They would need to use a different email (e.g., `+` alias or separate
account).

**Severity**: High for a production multi-tenant platform.

### Risk 2: Email Cannot Be Reused Across Tenants

A person who is a store owner / merchant cannot later register as a customer inside another
merchant's store using the same email. This prevents cross-store shopping with a unified
email identity.

**Severity**: High — limits organic cross-store commerce.

### Risk 3: No Separate Customer Identity

Customers and merchants share the same `users` table and the same model. This means:
- Customer data (addresses, order history) lives alongside admin/merchant data
- There is no clean separation of concerns at the model level
- Policy and permission logic must check roles on every access

**Severity**: Medium — manageable today but creates complexity.

### Risk 4: Password Reset Is Email-Keyed

`password_reset_tokens` uses `email` as the primary key. In a future architecture where
one email maps to multiple identities, the password reset flow would need to disambiguate
which identity (which tenant, which role) the user wants to reset.

**Severity**: Medium — would block future multi-identity support.

### Risk 5: Email Verification Is Identity-Level

Email verification (`email_verified_at`) is a single boolean on the `User` record. If one
email represents multiple identities (e.g., admin in Store A, customer in Store B),
verification would apply to all identities or none. There is no per-tenant or per-role
verification status.

**Severity**: Medium — affects trust separation between roles.

### Risk 6: No User-Tenant Pivot

A user can belong to only one tenant via `tenant_id`. There is no pivot table that would
allow a user to have memberships in multiple tenants. This means:
- A merchant cannot also be a customer in another store
- A consultant/service provider cannot access multiple tenants

**Severity**: Medium — limits natural cross-tenant collaboration.

---

## Scenario Analysis

### Scenario 1: Merchant Registers → Creates Store

**Current behavior**: Allowed. The merchant's email is checked against `unique:users,email`.
If the email is not already taken, the tenant is created and the owner user record is
inserted.

**Verdict**: Works correctly. This is the primary registration flow.

### Scenario 2: Merchant Tries Creating Another Store Using the Same Email

**Current behavior**: **Blocked**. The `unique:users,email` validation rule rejects the
registration because the email already exists in the `users` table.

**Should this be allowed?** In a production SaaS platform, a single natural person often
owns multiple stores. Shopify allows this — a single account can own multiple stores, each
with separate billing. The current architecture prevents this.

**Advantages of allowing**:
- Natural person owns multiple stores under one login
- Unified billing and account management
- Reduced friction for power users

**Disadvantages of allowing**:
- Requires changing email from globally unique to tenant-scoped unique
- Requires introducing a user-tenant membership model (pivot table or separate accounts)
- Password reset, email verification, and session management become more complex

**Business implications**: Preventing multi-store ownership is a significant competitive
disadvantage. Most SaaS e-commerce platforms (Shopify, BigCommerce) allow a single account
to manage multiple stores.

### Scenario 3: Merchant Becomes Customer Inside Another Merchant's Store

**Current behavior**: **Blocked**. The merchant's email already exists in the `users`
table with an `admin` role. Customer registration checks `unique:users,email`.

**Should this be allowed?** Yes. A merchant who runs their own store should be able to
shop as a customer in another merchant's store without creating a second email account.

**Advantages of allowing**:
- Merchants naturally cross-shop and discover other stores on the platform
- Unified email across the platform reduces password fatigue
- Enables marketplace-style cross-store interactions

**Disadvantages of allowing**:
- Same identity (email) would have two roles (admin + customer) across two tenants
- Session management and context switching become non-trivial
- The system needs to know "which identity" the user is acting as at any moment

### Scenario 4: Customer Registers in Store A → Registers in Store B

**Current behavior**: **Blocked**. Customer email from Store A already exists in `users`.
Registration in Store B fails with unique validation error.

**Should this be allowed?** Yes. A customer who shops at Store A should be able to shop
at Store B with the same email.

**Advantages of allowing**:
- Natural cross-store shopping behavior
- Reduced friction — customers use one email across the platform
- Fundamental for a multi-tenant marketplace

**Disadvantages of allowing**:
- The same email/user would have `customer` role in two tenants
- Order history would be split across tenants
- The system would need to scope all queries by both user AND tenant

### Scenario 5: Customer Registers Twice Inside the Same Store

**Current behavior**: **Blocked**. The `unique:users,email` rule prevents duplicate
registration regardless of tenant.

**Should this be allowed?** No. A customer should not be able to register twice in the
same store with the same email. This is correct behavior.

**Expected behavior**: The system should enforce **tenant-scoped email uniqueness** —
the same email cannot register twice in the same tenant, but CAN register in a different
tenant with the same or different role.

### Scenario 6: Password Reset With Multiple Identities

**Current behavior**: `Password::sendResetLink()` looks up the email in the `users` table
and sends a reset link. The `User::sendPasswordResetNotification()` method customizes the
reset URL to be tenant-aware if the user has a tenant.

**Problem**: If one email maps to multiple user records (across tenants or roles), the
password reset flow would need to:
1. Determine which identity the user wants to reset
2. Allow resetting one without affecting the other
3. Or allow resetting all with a single action

**Recommendation**: A future architecture should support either:
- Separate password per tenant identity (user manages multiple passwords)
- Or unified password across identities (one password reset affects all)

### Scenario 7: Email Verification With Multiple Identities

**Current behavior**: `email_verified_at` is a single timestamp on the `User` record.

**Problem**: If one email maps to multiple user records, each record would need its own
verification status. Alternatively, if using a unified identity model, verification would
apply to all identities at once.

**Recommendation**: A future architecture should support per-role or per-tenant
verification, or consider verification at the email level (one verification for all
identities sharing that email).

### Scenario 8: Future Social Login (OAuth)

**Current behavior**: No OAuth implementation exists. Only email/password login.

**Would the current architecture support future OAuth?** Not without significant changes.

**Required changes for OAuth**:
1. The `users` table would need a provider-specific identifier column (e.g.,
   `google_id`, `github_id`, `apple_id`) or a separate `social_accounts` pivot table
2. Unique constraints would need to be on `(provider, provider_id)` rather than on email
3. Social login typically links to an existing email-based account or creates a new one
4. The current global email uniqueness constraint would conflict with OAuth's email
   handling — social providers may return verified emails that already exist in the system

**Recommendation**: A separate `social_accounts` table with `(provider, provider_id)`
unique constraint, linked to a user identity record. The email field should not be the
primary lookup for OAuth.

---

## Industry Comparison

### Shopify

- **Identity model**: Single account can own **multiple stores**. One email, one password,
  one login session, access to all stores via store switcher.
- **Customer model**: Customers are **per-store**. A customer in Store A is a completely
  separate record from a customer in Store B, even with the same email.
- **Email uniqueness**: Email is unique **per store** for customers. A merchant email is
  globally unique (platform-level identity).
- **Key insight**: Shopify uses a **unified merchant identity** (one account for the
  platform) + **per-store customer identity** (customer records are scoped to each store).

### WooCommerce

- **Identity model**: WordPress user model. One email = one user. Multi-site networks
  allow one user to be a member of multiple sites.
- **Customer model**: Customers are WordPress users. Same user can be a customer on
  multiple WooCommerce stores within a multi-site network.
- **Email uniqueness**: Globally unique across the entire WordPress installation.
- **Key insight**: WooCommerce inherited WordPress's single-user model. Multi-store support
  requires WordPress MultiSite, where one user can have different roles on different sites.

### BigCommerce

- **Identity model**: Similar to Shopify. One account can manage multiple stores.
- **Customer model**: Per-store customer records. Same email can register as a customer
  in multiple stores.
- **Email uniqueness**: Merchant email is globally unique. Customer email is unique
  per store.

### Notion

- **Identity model**: One email = one account. Workspaces are a many-to-many
  relationship — one account can belong to multiple workspaces.
- **Multi-tenancy**: Workspace membership via workspace_id + role (owner, member, guest).
- **Email uniqueness**: Globally unique.

### Slack

- **Identity model**: One email = one account. Workspaces are many-to-many via
  workspace membership.
- **Multi-tenancy**: Membership pivot table with roles. Same email can be in 50+ workspaces.
- **Email uniqueness**: Globally unique at the account level. Workspace membership is
  separate.
- **Key insight**: Slack's model is closest to what this platform needs — one identity
  (email+password) with many workspace (tenant) memberships.

### GitHub

- **Identity model**: One email = one account. Organizations are many-to-many via
  organization membership.
- **Email uniqueness**: Globally unique. Email can be changed.
- **Key insight**: GitHub uses account-level identity + organization-level roles.

### Summary Table

| Platform | Merchant Identity | Customer Identity | Email Uniqueness | Multi-Tenant Membership |
|---|---|---|---|---|
| **Shopify** | Unified platform account | Per-store (separate records) | Merchant: global; Customer: per-store | Merchant: one-to-many (stores); Customer: one-to-one |
| **WooCommerce** | Single WP user | Same WP user | Global across installation | Via MultiSite network membership |
| **BigCommerce** | Unified platform account | Per-store | Merchant: global; Customer: per-store | Merchant: one-to-many |
| **Notion** | Single account | N/A | Global | Many-to-many via workspace membership |
| **Slack** | Single account | N/A | Global | Many-to-many via workspace membership |
| **GitHub** | Single account | N/A | Global | Many-to-many via organization membership |
| **Current Platform** | Single User record | Same User record | Global across all | One-to-one (one tenant per user) |

---

## Recommended Architecture

### Principle

**Separate the identity (who you are) from the membership (what you can do).**

The current system conflates "identity" (email + password) with "role + tenant
membership". These should be distinct concepts:

1. **Identity** (Account): A record with email, password, verification status. This is
   who the person is. An identity can exist without any tenant membership (e.g., a
   SuperAdmin account).
2. **Membership** (Tenant Role): A record linking an identity to a tenant with a specific
   role. An identity can have zero, one, or many memberships.

### Recommended Schema

Two new tables, one modified table:

#### Table: `accounts` (new)

Stores the login identity — email, password, verification.

```sql
CREATE TABLE accounts (
    id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    email               VARCHAR(255) UNIQUE NOT NULL,
    email_verified_at   TIMESTAMP NULLABLE,
    password            VARCHAR(255) NOT NULL,
    remember_token      VARCHAR(100) NULLABLE,
    profile_image       VARCHAR(255) NULLABLE,
    notification_preferences JSON NULLABLE,
    created_at          TIMESTAMP,
    updated_at          TIMESTAMP
);
```

**Email uniqueness**: Global UNIQUE constraint on `email`. Each email can exist only once
at the identity level.

#### Table: `tenant_user` (new pivot, replacing `users.tenant_id`)

Links an account to a tenant with a role.

```sql
CREATE TABLE tenant_user (
    id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    account_id  BIGINT UNSIGNED NOT NULL FK -> accounts(id) ON DELETE CASCADE,
    tenant_id   BIGINT UNSIGNED NOT NULL FK -> tenants(id) ON DELETE CASCADE,
    role        VARCHAR(50) NOT NULL,         -- 'owner', 'admin', 'staff', 'customer'
    is_owner    BOOLEAN DEFAULT FALSE,
    status      VARCHAR(50) DEFAULT 'active',  -- active, suspended, banned
    is_default  BOOLEAN DEFAULT FALSE,          -- which tenant to log into by default
    created_at  TIMESTAMP,
    updated_at  TIMESTAMP,
    UNIQUE KEY (account_id, tenant_id),
    UNIQUE KEY (tenant_id, role, account_id)   -- role-specific logic can use this
);
```

**Email uniqueness**: Scoped to `(account_id, tenant_id)` — one account can have only one
membership per tenant.

#### Table: `users` (modified — deprecated)

The existing `users` table can remain during migration as a compatibility layer. New code
would use `accounts` + `tenant_user`. Eventually, the `users` table would be migrated to
this new structure.

#### Table: `customer_profiles` (new — per-tenant customer data)

For customer-specific data that should be tenant-scoped.

```sql
CREATE TABLE customer_profiles (
    id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    tenant_user_id BIGINT UNSIGNED NOT NULL FK -> tenant_user(id) ON DELETE CASCADE,
    name        VARCHAR(255) NOT NULL,
    phone       VARCHAR(20) NULLABLE,
    -- Other customer-specific fields
    created_at  TIMESTAMP,
    updated_at  TIMESTAMP,
    UNIQUE (tenant_user_id)
);
```

This is NOT merely a "separate customers table" — it is an extension profile for
customer-role memberships. Admin-role memberships do not need a customer profile.

---

## Recommended Email Strategy

### Short-Term (Version 3 Compatible)

**Keep the existing single `users` table but relax uniqueness constraints.**

1. Change `users.email` from a global UNIQUE index to a composite UNIQUE index on
   `(tenant_id, email)`.
   - Allows the same email to exist in multiple tenants
   - Prevents duplicate registration within the same tenant
   - SuperAdmin accounts (null tenant_id) remain globally unique naturally

2. Update validation rules:
   - `CreateStoreController`: Keep `unique:users,email` for owner creation (a person can
     own only one store initially, or this can be relaxed later)
   - `RegisteredUserController`: Change to `unique:users,email,NULL,id,tenant_id,{tenantId}`
     — tenant-scoped uniqueness for customer registration

3. Add `superadmin@` prefix detection or a separate superadmin email check to ensure
   SuperAdmin emails remain unique.

### Long-Term (Version 4+)

**Adopt the `accounts` + `tenant_user` schema described above.**

1. `accounts.email` remains globally unique (one account per email)
2. `tenant_user` allows an account to belong to multiple tenants with different roles
3. Customer profiles are tenant-scoped extensions, not separate identity records

### Email Verification Strategy

For the short term (Version 3):
- Keep `email_verified_at` on the `User` record
- When an email exists in multiple tenants, verifying one User record could mark all
  User records with that email as verified (unified verification)
- Or mark each tenant's User record independently (per-tenant verification)

For the long term (Version 4):
- Move `email_verified_at` to the `accounts` table
- Verification is at the account level — once verified, all memberships benefit

---

## Tenant Isolation Strategy

### Current State

Tenant isolation works through:
1. `IdentifyTenant` middleware — sets the current tenant
2. `TenantAware` trait — adds global scope for `tenant_id`
3. `CheckTenantAccess` middleware — validates user belongs to current tenant
4. Route-model-binding validation via `ValidateTenantBinding` middleware

The **critical gap** is that `CheckTenantAccess` checks `user.tenant_id`, which is a
single value. In a multi-membership architecture, this check would need to look at the
`tenant_user` pivot table.

### Recommended Short-Term (Version 3)

Keep the existing tenant isolation middleware stack but:
1. When checking tenant access (`CheckTenantAccess`), validate against `tenant_id` on the
   User record (as today)

### Recommended Long-Term (Version 4)

Update tenant isolation to work with the pivot model:
1. `CheckTenantAccess` checks `tenant_user` for an active membership
2. `TenantAware` trait works as before — all tenant-scoped models still have `tenant_id`
3. The `User` model (or a `membership()` relationship) provides the current tenant context
4. Session stores current tenant context (which tenant the user is acting as right now)

---

## Authentication Strategy

### Short-Term (Version 3)

Keep the single `web` guard. Make these minimum changes:

1. **Login flow**: After `Auth::attempt()` succeeds, determine which tenant the user
   belongs to from `user.tenant_id` (as today).
2. **Multi-tenant login**: If the user has records in multiple tenants (after relaxing
   email uniqueness), the login flow needs to determine which tenant context to use:
   - Preferred: Store slug in the URL determines the tenant (already done)
   - If no slug in URL: Redirect to a tenant selector page
3. **Session storage**: Store `current_tenant_id` in the session after login

### Long-Term (Version 4)

1. **Authentication**: Login with email + password against the `accounts` table
2. **Session**: Store `account_id` in the session
3. **Tenant selection**: After login, if the account has multiple tenant memberships,
   show a tenant switcher or redirect based on URL context
4. **Switch tenant**: Allow switching tenant membership without re-authentication
   (inspiration: GitHub organization switcher, Slack workspace switcher)

### Guard Configuration for Version 4

```php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'accounts',  // Changed from 'users'
    ],
],

'providers' => [
    'accounts' => [
        'driver' => 'eloquent',
        'model' => App\Models\Account::class,
    ],
],
```

---

## Password Reset Strategy

### Short-Term (Version 3)

Keep the existing flow. After relaxing email uniqueness:
- `Password::sendResetLink()` will find the first User record with the given email
  (or all records). The behavior is undefined with multiple records.
- Recommendation: Reset the password for **all** User records with that email, or send a
  single notification that allows resetting all at once.

### Long-Term (Version 4)

- Password reset operates on the `accounts` table, not individual User records
- Resetting the account password effectively resets access for all memberships
- `password_reset_tokens` should use `account_id` instead of `email` as the key
- The reset flow: submit email → if account found, send reset link → reset account
  password → all memberships authenticated with the new password

---

## Email Verification Strategy

### Short-Term (Version 3)

After relaxing email uniqueness:
- If a User record is verified with email `a@b.com`, all User records with that email
  could also be marked as verified (propagation)
- Or each User record requires independent verification (more secure, more friction)

Recommendation: **Propagate verification** — if the same email is used in multiple tenants,
verifying one verifies all. This reduces friction and recognizes that email access is the
proof (if you can click the verification link, you own the email regardless of tenant).

### Long-Term (Version 4)

- `email_verified_at` lives on the `accounts` table
- One verification covers all memberships
- Verification notification sends to the account email one time only

---

## Future OAuth Compatibility

### What Is Needed

1. **A `social_accounts` table** linking third-party providers to accounts:

```sql
CREATE TABLE social_accounts (
    id                BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    account_id        BIGINT UNSIGNED NOT NULL FK -> accounts(id) ON DELETE CASCADE,
    provider          VARCHAR(50) NOT NULL,     -- 'google', 'github', 'apple'
    provider_id       VARCHAR(255) NOT NULL,    -- unique ID from the provider
    provider_email    VARCHAR(255) NULLABLE,
    avatar_url        VARCHAR(500) NULLABLE,
    created_at        TIMESTAMP,
    updated_at        TIMESTAMP,
    UNIQUE (provider, provider_id)
);
```

2. **OAuth login flow**: Find or create an account by `(provider, provider_id)`, then
   create or attach tenant membership.

3. **Email linking**: When OAuth returns an email that matches an existing account, link
   the social account to that account (with user confirmation for security).

### Compatibility With Current Architecture

The current architecture (single `users` table, no OAuth) would need the following to
support OAuth:

1. A `user_providers` or `social_accounts` table (new migration)
2. OAuth controller(s) with provider-specific callbacks
3. Account linking logic (merge social identity with existing email identity)

The **recommended `accounts` + `tenant_user` architecture** is inherently more compatible
with OAuth because:
- The `accounts` table is the user's true identity
- OAuth providers authenticate the account, not a specific tenant membership
- Tenant memberships are downstream of account authentication

---

## Migration Impact Assessment

### Impact of Changing Email Uniqueness (Short-Term Change)

| Area | Impact | Effort |
|---|---|---|
| **Database** | Drop global UNIQUE index on `users.email`; add composite UNIQUE `(tenant_id, email)` | Low — single migration |
| **Validation** | Update `CreateStoreController` and `RegisteredUserController` rules | Low — 2 files |
| **Login** | `StorefrontLoginController` already checks `tenant_id` match — minimal change | Low |
| **Password reset** | `Password::sendResetLink()` may find multiple records — needs update | Medium |
| **Email verification** | Verification link contains user ID — already scoped correctly | Low |
| **Admin controllers** | All query scoping uses `tenant_id` — already correct | None |
| **Existing users** | Backfill — existing unique emails remain valid | Low |
| **SuperAdmin accounts** | Ensure SuperAdmin (null tenant_id) emails remain globally unique | Low |

### Impact of Full Refactor to `accounts` + `tenant_user` (Long-Term)

| Area | Impact | Effort |
|---|---|---|
| **New tables** | Create `accounts`, `tenant_user`, `customer_profiles` | Medium |
| **Data migration** | Migrate existing `users` data to new schema | High |
| **Authentication** | Change guard provider from `users` to `accounts` | Medium |
| **Authorization** | Update role checks to look through `tenant_user` | Medium |
| **Tenant middleware** | Update `CheckTenantAccess` to use pivot table | Medium |
| **Session management** | Add tenant-switching to session | Medium |
| **Policies** | Update policies to check account + tenant membership | Medium |
| **Frontend** | Update Inertia shared data to include account + membership info | Medium |
| **Existing API** | Maintain backward compatibility layer | High |

---

## Backward Compatibility Assessment

### Short-Term Change (Composite Unique Index)

- **Existing users**: All existing unique emails remain valid. No data migration needed.
- **Login**: Existing login flow works unchanged. Users continue logging in through their
  store URL.
- **Session**: Existing sessions remain valid.
- **Admin routes**: All admin routes use `tenant_id` scoping — no changes needed.
- **Password reset**: The `User::sendPasswordResetNotification()` method is already
  tenant-aware and uses `$this->tenant->slug`. If there are multiple User records with
  the same email, the password reset should be sent for all of them, or the flow should
  ask the user which tenant to reset.

### Long-Term Refactor (Accounts + Tenant User)

- **Backward compatibility layer**: The existing `users` table would need to be maintained
  as a view or legacy table until all code is migrated.
- **Drop-in replacement**: The `User` model could be refactored to use the `accounts` table
  internally while keeping the same API (facade pattern).
- **Data migration strategy**: Run in phases — (1) create accounts table and backfill,
  (2) create tenant_user records, (3) update code to read from new tables, (4) deprecate
  old users table.

---

## Version 3 Recommendation

**Do NOT implement the full `accounts` + `tenant_user` refactor in Version 3.**

The Version 3 release cycle should not include a database schema change of this magnitude.
Instead, make the **minimum viable change** to fix the email reuse problem:

1. **Change `users.email` unique constraint** from global to composite `(tenant_id, email)`.
   - This allows the same email to register as a customer in multiple stores
   - This allows a merchant to also be a customer in another store
   - This prevents duplicate registration within the same store

2. **Update validation rules**:
   - `RegisteredUserController.store()`: Change to tenant-scoped unique
   - `CreateStoreController.store()`: Keep global unique for store owners (one store per
     email), OR relax based on business requirements

3. **Update password reset flow** to handle multiple User records with the same email:
   - When `Password::sendResetLink()` is called, find all User records with that email
   - Send one reset notification per User record, or send one notification that affects all

4. **Update `CheckTenantAccess`** to be more permissive when a user has the correct email
   but belongs to a different tenant than expected (this edge case arises only if
   tenant-scoped uniqueness is in place).

**Risk**: Low for Version 3. The change is isolated to the `users` table unique index
and two validation rules. Login flows already handle tenant-scoped user lookup.

---

## Version 4 Recommendation

**Implement the full identity refactor in Version 4.**

The Version 4 release should adopt the `accounts` + `tenant_user` architecture described
in this document. This is the right solution for a production multi-tenant SaaS platform.

### Recommended Approach

1. **Phase 1 (Foundation)**: Create `accounts` and `tenant_user` tables. Implement
   `Account` model. Add migration to copy existing `users` data to accounts.

2. **Phase 2 (Authentication)**: Change auth guard to use `accounts` provider. Update
   login/registration flows to work with accounts + tenant_user. Implement tenant
   switching.

3. **Phase 3 (Authorization)**: Update middleware, policies, and permission checks to
   work with the new model. The `tenant_user.role` field replaces Spatie's role-tenant
   scope for determining the user's role in the current tenant.

4. **Phase 4 (Deprecation)**: Deprecate the old `users` table. Create a database view or
   legacy model for backward compatibility. Migrate all read queries to the new schema.

5. **Phase 5 (Cleanup)**: Drop the `users` table after all code has been migrated and
   tested in production.

### Key Design Decisions for Version 4

- **Email uniqueness**: `accounts.email` globally unique (one identity per email)
- **Tenant membership**: `tenant_user` with `(account_id, tenant_id)` unique constraint
- **Role per membership**: `tenant_user.role` — one account can be an admin in one tenant
  and a customer in another
- **Default tenant**: `tenant_user.is_default` — which tenant to log into by default
- **Session tenant**: Session stores `current_tenant_membership_id` for context
- **Password**: Account-level password (one password for all memberships)
- **Verification**: Account-level email verification
- **OAuth**: `social_accounts` table linking to `accounts`

---

## Final Engineering Recommendation

### For Version 3 (Immediate)

The current behavior prevents the same email from being reused anywhere in the system.
This is a **production blocker** for a multi-tenant SaaS platform.

**Recommended action**: Change the `users.email` unique constraint from a global UNIQUE
index to a composite UNIQUE index on `(tenant_id, email)`.

This single change:
- Allows a customer to register in multiple stores with the same email
- Allows a merchant to become a customer in another store
- Still prevents duplicate registration within the same store
- Requires no new tables, no new models, no new controllers
- Requires updating two validation rules
- Has minimal risk and can be deployed in a single migration

### For Version 4 (Next Major Release)

**Recommended action**: Implement the `accounts` + `tenant_user` architecture. This is the
correct long-term solution for a production multi-tenant SaaS platform. The change is
significant but necessary for:

- True multi-store ownership (one account, many stores)
- Cross-store customer identity with tenant-scoped profiles
- OAuth compatibility
- Clean separation of identity from membership
- Alignment with industry standards (Shopify, Slack, GitHub model)

### Summary

| Aspect | Version 3 (Now) | Version 4 (Next) |
|---|---|---|
| **Email uniqueness** | Composite `(tenant_id, email)` | Global on `accounts.email` |
| **Identity model** | Single `users` table | `accounts` + `tenant_user` pivot |
| **Customer model** | User with `customer` role | `accounts` + `tenant_user` + `customer_profiles` |
| **Multi-store ownership** | Relaxed but limited | Fully supported |
| **Cross-store customer** | Supported via composite unique | Supported natively |
| **OAuth** | Requires extra work | Natively compatible |
| **Migration effort** | Low (1 index change + 2 validation updates) | High (new tables, data migration, code refactor) |
| **Risk** | Low | Medium-High |
| **Backward compatibility** | Full | Requires compatibility layer |
