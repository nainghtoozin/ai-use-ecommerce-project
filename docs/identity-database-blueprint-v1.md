# Identity Database Blueprint — v1

**Status:** FINAL — Blueprint Locked  
**Date:** 2026-07-07  
**Version:** 1.0  
**Project:** Multi-tenant SaaS E-commerce Platform  
**Governed by:** `docs/identity-architecture-lock-v2.md`  
**Purpose:** Single source of truth for every migration related to Identity. No database architecture decisions are made during implementation.

---

## Executive Summary

This document is the complete database blueprint for the Identity Architecture. It specifies every table, column, index, foreign key, and constraint required to implement Phase 1 (Database Foundation) through Phase 8 (Cleanup & Deprecation).

The blueprint covers:

- **5 new tables**: `accounts`, `tenant_memberships`, `customer_profiles`, `staff_profiles`, `social_accounts`
- **3 modified tables**: `sessions`, `password_reset_tokens`, `notifications`
- **1 deprecated table**: `users` (kept for backward compatibility, no new writes after migration)
- **Zero-downtime migration strategy** using dual-read/dual-write with feature flags
- **Data migration** from existing `users` table to new identity tables
- **Backward compatibility** layer preserving all existing foreign key references

Every architectural decision is inherited from the Architecture Lock v2 document. No decisions are made here — only specified.

---

## Database Philosophy

### Separation of Concerns

Each table owns exactly one responsibility:

| Table | Owns | Does Not Own |
|---|---|---|
| `accounts` | Who you are (email, password, verification) | What you can do, which tenant you belong to |
| `tenant_memberships` | What you can do in a tenant (role, ownership, status) | Your identity, the tenant's data |
| `tenants` | Store business data (name, slug, subscription) | Identity data, role definitions |
| `roles` | Named permission sets per tenant | Identity, membership status |
| `profiles` | Role-specific data (customer name, staff position) | Identity, role, permissions |

### Production First

- Every migration must be reversible
- Every column must have a clear business justification
- Every index must have a measured query pattern
- Every foreign key must have a justified cascade rule
- Zero-downtime deployment is the default assumption

### Naming Conventions

| Convention | Rule | Example |
|---|---|---|
| Table names | Snake case, plural | `tenant_memberships` |
| Columns | Snake case | `is_owner`, `email_verified_at` |
| Primary keys | `id` (BIGINT UNSIGNED AUTO_INCREMENT) | — |
| Foreign keys | Singular table name + `_id` | `account_id`, `tenant_id` |
| Indexes | `{table}_{column}_index` | `tenant_memberships_account_id_index` |
| Unique constraints | `{table}_{columns}_unique` | `tenant_memberships_account_id_tenant_id_unique` |
| JSON columns | Descriptive, prefixed with context | `notification_preferences`, `permissions_overrides` |
| Soft deletes | `deleted_at` (TIMESTAMP NULLABLE) | — |

---

## Identity Data Model

```
┌─────────────────────────────────────────────────────────────────┐
│                         accounts                                 │
│  id, email, password, email_verified_at, remember_token,         │
│  profile_image, status, notification_preferences,                │
│  last_login_at, last_login_ip, created_at, updated_at,           │
│  deleted_at                                                      │
└──────────────────────────┬──────────────────────────────────────┘
                           │ 1
                           │
                           │ * (one Account, many Memberships)
                           │
┌──────────────────────────▼──────────────────────────────────────┐
│                     tenant_memberships                            │
│  id, account_id, tenant_id, role_id, is_owner, status,           │
│  invited_by, invited_at, joined_at, is_default,                  │
│  created_at, updated_at, deleted_at                               │
│                                                                   │
│  UNIQUE(account_id, tenant_id)                                    │
│  Partial UNIQUE(tenant_id) WHERE is_owner = true                  │
└──────┬──────────────────────┬──────────────────────┬─────────────┘
       │                      │                      │
       │ 0..1                 │ 0..1                 │ 0..1
       │                      │                      │
┌──────▼──────────────┐ ┌────▼──────────────┐ ┌─────▼──────────────┐
│  customer_profiles   │ │  staff_profiles    │ │  merchant_profiles  │
│  id,                 │ │  id,               │ │  id,                │
│  membership_id (UNQ) │ │  membership_id(UNQ)│ │  membership_id(UNQ) │
│  name, phone,        │ │  position,         │ │  business_name,     │
│  metadata            │ │  department        │ │  tax_id,            │
│  created_at,         │ │  created_at,       │ │  business_address   │
│  updated_at,         │ │  updated_at,       │ │  created_at,        │
│  deleted_at          │ │  deleted_at        │ │  updated_at,        │
└──────────────────────┘ └───────────────────┘ └───┬────────────────┘
                                                    │
                                           ┌────────▼────────────────┐
                                           │   social_accounts       │
                                           │  id,                    │
                                           │  account_id,            │
                                           │  provider,              │
                                           │  provider_id,           │
                                           │  provider_email,        │
                                           │  avatar_url,            │
                                           │  token,                 │
                                           │  refresh_token,         │
                                           │  expires_at,            │
                                           │  created_at,            │
                                           │  updated_at             │
                                           │  UNIQUE(provider,       │
                                           │    provider_id)         │
                                           └────────────────────────┘
```

---

## Table Responsibilities

### `accounts` — NEW

| Aspect | Detail |
|---|---|
| **Status** | New table |
| **Purpose** | Root identity for a natural person |
| **Responsibility** | Email, password, verification, global account status |
| **Must never own** | Tenant_id, tenant-specific role, is_owner, tenant-specific data |

### `tenant_memberships` — NEW

| Aspect | Detail |
|---|---|
| **Status** | New table |
| **Purpose** | Links an Account to a Tenant with Role, Ownership, and Membership Status |
| **Responsibility** | role_id, is_owner, membership status (active/invited/suspended/removed), invitation tracking |
| **Must never own** | Email, password, account-level status, tenant business data |

### `merchant_profiles` — NEW

| Aspect | Detail |
|---|---|
| **Status** | New table (optional — see note) |
| **Purpose** | Stores merchant-specific business information for the owner's membership |
| **Responsibility** | Business name, tax ID, business address, owner-specific business data |
| **Must never own** | Identity data, tenant-level settings (those go on `tenants.settings`) |

> **Design note:** Business-level information (store name, address, logo) belongs on the `tenants` table. The `merchant_profiles` table is only for owner-specific business information that differs from tenant-level data. If no such data exists, this table can be omitted in Phase 1 and added later.

### `customer_profiles` — NEW

| Aspect | Detail |
|---|---|
| **Status** | New table |
| **Purpose** | Tenant-scoped customer data for customer-role memberships |
| **Responsibility** | Display name, phone, metadata specific to this customer in this tenant |
| **Must never own** | Login credentials, order data, addresses (stay on their own tables) |

### `staff_profiles` — NEW

| Aspect | Detail |
|---|---|
| **Status** | New table |
| **Purpose** | Tenant-scoped staff data for admin-role (non-owner) memberships |
| **Responsibility** | Position, department. No permission overrides in v3. |
| **Must never own** | Login credentials, role assignment (role_id is on membership) |

### `social_accounts` — NEW

| Aspect | Detail |
|---|---|
| **Status** | New table |
| **Purpose** | Links third-party OAuth providers to an Account |
| **Responsibility** | Provider name, provider user ID, provider email, encrypted tokens |
| **Must never own** | Login credentials, role information, tenant membership data |

### `sessions` — MODIFIED

| Aspect | Detail |
|---|---|
| **Status** | Modified table |
| **Purpose** | Stores active authentication sessions |
| **Changes** | Add `account_id` column alongside existing `user_id`. Add `current_tenant_membership_id`. |
| **Must never own** | Long-term identity data, role information, permissions cache |

### `password_reset_tokens` — MODIFIED

| Aspect | Detail |
|---|---|
| **Status** | Modified table |
| **Purpose** | Stores password reset tokens |
| **Changes** | Change primary key from `email` to `account_id`. Drop `email` column. Add FK to `accounts`. |
| **Must never own** | Identity data (email is derived from the Account relationship) |

### `notifications` — MODIFIED

| Aspect | Detail |
|---|---|
| **Status** | Modified table |
| **Purpose** | Stores in-app notifications |
| **Changes** | `tenant_id` column already exists. New notifications use `notifiable_type = App\Models\Account`. No schema change needed — only application-level changes. |

### `activity_logs` — EXISTING

| Aspect | Detail |
|---|---|
| **Status** | Existing table (used as-is) |
| **Purpose** | Logs identity and authorization events |
| **Changes** | Identity events use `causer_type = App\Models\Account` instead of `App\Models\User`. No schema changes needed for Phase 1. |
| **Must never own** | Business transaction data, identity credentials |

### `users` — DEPRECATED

| Aspect | Detail |
|---|---|
| **Status** | Deprecated table |
| **Purpose** | Legacy identity storage. Kept for backward compatibility during transition. |
| **Changes** | No schema changes. No new writes after Phase 4 deployment. |
| **Removal** | After Phase 8 (Cleanup & Deprecation), a final migration drops this table after all references are migrated. |

---

## Table Specifications

### `accounts`

| Column | Type | Nullable | Default | Responsibility |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | NO | AUTO_INCREMENT | Primary key, matches User.id during migration |
| `email` | VARCHAR(255) | NO | — | Global login identity. Must be unique. |
| `password` | VARCHAR(255) | NO | — | Bcrypt-hashed password |
| `email_verified_at` | TIMESTAMP | YES | NULL | Proof of email ownership |
| `remember_token` | VARCHAR(100) | YES | NULL | "Remember me" session token |
| `profile_image` | VARCHAR(255) | YES | NULL | Avatar/profile photo URL path |
| `status` | VARCHAR(50) | NO | `'active'` | `active`, `suspended`, `banned` — gates authentication |
| `notification_preferences` | JSON | YES | NULL | Global notification on/off flags |
| `last_login_at` | TIMESTAMP | YES | NULL | Last successful authentication |
| `last_login_ip` | VARCHAR(45) | YES | NULL | IP from last login |
| `created_at` | TIMESTAMP | NO | CURRENT_TIMESTAMP | |
| `updated_at` | TIMESTAMP | NO | CURRENT_TIMESTAMP ON UPDATE | |
| `deleted_at` | TIMESTAMP | YES | NULL | Soft delete timestamp |

### `tenant_memberships`

| Column | Type | Nullable | Default | Responsibility |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | NO | AUTO_INCREMENT | Primary key |
| `account_id` | BIGINT UNSIGNED | NO | — | FK to accounts |
| `tenant_id` | BIGINT UNSIGNED | NO | — | FK to tenants |
| `role_id` | BIGINT UNSIGNED | NO | — | FK to roles (Spatie) |
| `is_owner` | BOOLEAN | NO | FALSE | Ownership flag. At most one TRUE per tenant. |
| `status` | VARCHAR(50) | NO | `'active'` | `active`, `invited`, `suspended`, `removed` |
| `invited_by` | BIGINT UNSIGNED | YES | NULL | FK to accounts (who sent invitation) |
| `invited_at` | TIMESTAMP | YES | NULL | When invitation was sent |
| `joined_at` | TIMESTAMP | YES | NULL | When invitation was accepted |
| `is_default` | BOOLEAN | NO | FALSE | Default tenant for login redirect |
| `created_at` | TIMESTAMP | NO | CURRENT_TIMESTAMP | |
| `updated_at` | TIMESTAMP | NO | CURRENT_TIMESTAMP ON UPDATE | |
| `deleted_at` | TIMESTAMP | YES | NULL | Soft delete for membership removal audit |

### `merchant_profiles`

| Column | Type | Nullable | Default | Responsibility |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | NO | AUTO_INCREMENT | Primary key |
| `tenant_membership_id` | BIGINT UNSIGNED | NO | — | FK to tenant_memberships (UNIQUE) |
| `business_name` | VARCHAR(255) | YES | NULL | Legal business name (if different from store name) |
| `tax_id` | VARCHAR(100) | YES | NULL | Tax/VAT registration number |
| `business_address` | JSON | YES | NULL | Business address for invoices |
| `metadata` | JSON | YES | NULL | Future expansion: business type, registration docs |
| `created_at` | TIMESTAMP | NO | CURRENT_TIMESTAMP | |
| `updated_at` | TIMESTAMP | NO | CURRENT_TIMESTAMP ON UPDATE | |
| `deleted_at` | TIMESTAMP | YES | NULL | |

### `customer_profiles`

| Column | Type | Nullable | Default | Responsibility |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | NO | AUTO_INCREMENT | Primary key |
| `tenant_membership_id` | BIGINT UNSIGNED | NO | — | FK to tenant_memberships (UNIQUE) |
| `name` | VARCHAR(255) | NO | — | Display name in this store |
| `phone` | VARCHAR(20) | YES | NULL | Contact phone for this store |
| `metadata` | JSON | YES | NULL | Future expansion: birthday, preferences |
| `created_at` | TIMESTAMP | NO | CURRENT_TIMESTAMP | |
| `updated_at` | TIMESTAMP | NO | CURRENT_TIMESTAMP ON UPDATE | |
| `deleted_at` | TIMESTAMP | YES | NULL | |

### `staff_profiles`

| Column | Type | Nullable | Default | Responsibility |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | NO | AUTO_INCREMENT | Primary key |
| `tenant_membership_id` | BIGINT UNSIGNED | NO | — | FK to tenant_memberships (UNIQUE) |
| `position` | VARCHAR(100) | YES | NULL | Job title (e.g., "Cashier", "Manager") |
| `department` | VARCHAR(100) | YES | NULL | Department name |
| `created_at` | TIMESTAMP | NO | CURRENT_TIMESTAMP | |
| `updated_at` | TIMESTAMP | NO | CURRENT_TIMESTAMP ON UPDATE | |
| `deleted_at` | TIMESTAMP | YES | NULL | |

### `social_accounts`

| Column | Type | Nullable | Default | Responsibility |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | NO | AUTO_INCREMENT | Primary key |
| `account_id` | BIGINT UNSIGNED | NO | — | FK to accounts |
| `provider` | VARCHAR(50) | NO | — | `'google'`, `'github'`, `'apple'`, `'microsoft'` |
| `provider_id` | VARCHAR(255) | NO | — | User ID from the OAuth provider |
| `provider_email` | VARCHAR(255) | YES | NULL | Email from provider (read-only) |
| `avatar_url` | VARCHAR(500) | YES | NULL | Avatar URL from provider |
| `token` | TEXT | YES | NULL | Encrypted OAuth access token |
| `refresh_token` | TEXT | YES | NULL | Encrypted OAuth refresh token |
| `expires_at` | TIMESTAMP | YES | NULL | Token expiry timestamp |
| `created_at` | TIMESTAMP | NO | CURRENT_TIMESTAMP | |
| `updated_at` | TIMESTAMP | NO | CURRENT_TIMESTAMP ON UPDATE | |

### `sessions` — MODIFIED

**Existing schema from `0001_01_01_000000_create_users_table.php`:**

| Column | Type | Nullable | Change Required |
|---|---|---|---|
| `id` | VARCHAR(255) | NO | None (primary key) |
| `user_id` | BIGINT UNSIGNED | YES | Keep for backward compatibility during transition |
| `ip_address` | VARCHAR(45) | YES | None |
| `user_agent` | TEXT | YES | None |
| `payload` | LONGTEXT | NO | None |
| `last_activity` | INT | NO | None |

**New columns to add:**

| Column | Type | Nullable | Default | Responsibility |
|---|---|---|---|---|
| `account_id` | BIGINT UNSIGNED | YES | NULL | FK to accounts. New primary lookup for sessions. |
| `current_tenant_membership_id` | BIGINT UNSIGNED | YES | NULL | Current tenant context for this session |

### `password_reset_tokens` — RECREATED

**Current schema (to be replaced):**

| Column | Type | Change |
|---|---|---|
| `email` | VARCHAR(255) | Drop — no longer primary key |
| `token` | VARCHAR(255) | Keep |
| `created_at` | TIMESTAMP | Keep |

**New schema:**

| Column | Type | Nullable | Default | Responsibility |
|---|---|---|---|---|
| `account_id` | BIGINT UNSIGNED | NO | — | Primary key. FK to accounts. |
| `token` | VARCHAR(255) | NO | — | Bcrypt-hashed reset token |
| `created_at` | TIMESTAMP | YES | NULL | Token creation timestamp |

---

## Relationship Design

### Core Entity Relationships

```
Account (1) ──────< (0..*) TenantMembership (0..*) >────── (1) Tenant
```

| Entity A | Relationship | Entity B | Cardinality | Justification |
|---|---|---|---|---|
| Account | One-to-Many | TenantMembership | 1 : 0..* | One person can have memberships in many tenants |
| Tenant | One-to-Many | TenantMembership | 1 : 0..* | One tenant has many members (owner, staff, customers) |
| TenantMembership | Many-to-One | Account | *..1 | Each membership belongs to exactly one Account |
| TenantMembership | Many-to-One | Tenant | *..1 | Each membership belongs to exactly one Tenant |
| TenantMembership | Many-to-One | Role | *..1 | Each membership has exactly one Spatie Role |
| TenantMembership | One-to-One | CustomerProfile | 0..1 | Only if membership has customer role |
| TenantMembership | One-to-One | StaffProfile | 0..1 | Only if membership has admin/staff role (non-owner) |
| TenantMembership | One-to-One | MerchantProfile | 0..1 | Only if membership has is_owner=true |
| Account | One-to-Many | SocialAccount | 1 : 0..* | One Account can link many OAuth providers |
| Account | One-to-Many | Session | 1 : 0..* | One Account can have many concurrent sessions |
| Account | One-to-Many | Notification | 1 : 0..* | One Account receives many notifications |

### Spatie Table Relationships (Existing, Unchanged)

| Entity A | Relationship | Entity B | Cardinality |
|---|---|---|---|
| Role | Many-to-Many | Permission | * : * (via `role_has_permissions`) |
| Role | Belongs-to | Tenant | * : 1 (tenant_id nullable for superadmin) |
| User (legacy) | Many-to-Many | Role | * : * (via `model_has_roles`) |

> Note: The new architecture does NOT use `model_has_roles` for Account or TenantMembership. The `role_id` on `tenant_memberships` replaces it. The `model_has_roles` table remains for legacy User records during transition.

### Entity-Relationship Diagram (Detailed)

```
accounts
  │
  ├── social_accounts.account_id (1:0..*)
  │     UNIQUE(provider, provider_id)
  │
  ├── tenant_memberships.account_id (1:0..*)
  │     UNIQUE(account_id, tenant_id)
  │     │
  │     ├── tenants.id (via tenant_id)
  │     │     Unchanged. Existing FK from users.tenant_id is reused.
  │     │
  │     ├── roles.id (via role_id)
  │     │     Direct FK to Spatie Role (tenant-scoped).
  │     │     NOT through model_has_roles.
  │     │
  │     ├── customer_profiles.tenant_membership_id (0..1)
  │     │     UNIQUE(tenant_membership_id)
  │     │     Only for customer-role memberships.
  │     │
  │     ├── staff_profiles.tenant_membership_id (0..1)
  │     │     UNIQUE(tenant_membership_id)
  │     │     Only for admin-role non-owner memberships.
  │     │
  │     └── merchant_profiles.tenant_membership_id (0..1)
  │           UNIQUE(tenant_membership_id)
  │           Only for is_owner=true memberships.
  │
  ├── sessions.account_id (1:0..*)
  │     Indexed for session lookup.
  │
  └── password_reset_tokens.account_id (1:0..1)
        Primary key. One active reset token per Account.
```

---

## Foreign Key Rules

### FK Specification

| Foreign Key | Parent Table | Parent Column | On Delete | On Update | Justification |
|---|---|---|---|---|---|
| `tenant_memberships.account_id` | `accounts` | `id` | **SET NULL** | CASCADE | v2 resolution: CASCADE would destroy audit trail on soft-delete. SET NULL preserves membership record. |
| `tenant_memberships.tenant_id` | `tenants` | `id` | **CASCADE** | CASCADE | If tenant is deleted (soft-delete), all its memberships must go. |
| `tenant_memberships.role_id` | `roles` | `id` | **RESTRICT** | CASCADE | Cannot delete a role that is assigned to active memberships. |
| `tenant_memberships.invited_by` | `accounts` | `id` | **SET NULL** | CASCADE | If inviter account is deleted, preserve invitation record. |
| `customer_profiles.tenant_membership_id` | `tenant_memberships` | `id` | **CASCADE** | CASCADE | Profile dies with membership. |
| `staff_profiles.tenant_membership_id` | `tenant_memberships` | `id` | **CASCADE** | CASCADE | Profile dies with membership. |
| `merchant_profiles.tenant_membership_id` | `tenant_memberships` | `id` | **CASCADE** | CASCADE | Profile dies with membership. |
| `social_accounts.account_id` | `accounts` | `id` | **CASCADE** | CASCADE | Provider link dies with account. |
| `sessions.account_id` | `accounts` | `id` | **CASCADE** | CASCADE | Session dies with account. |
| `password_reset_tokens.account_id` | `accounts` | `id` | **CASCADE** | CASCADE | Reset token dies with account. |

### Key Design Decision: SET NULL on `tenant_memberships.account_id`

The v2 Self Review identified that CASCADE destroys the audit trail. SET NULL is the correct choice:

**Before (v1 specification):** CASCADE → Account soft-deleted → all Memberships deleted → lost history of who owned which stores.

**After (v2 + this blueprint):** SET NULL → Account soft-deleted → `account_id` becomes NULL on Memberships → Membership record preserved → `deleted_at` on Account prevents authentication → audit trail intact.

**Edge case handling:**
- NULL `account_id` on a Membership means the Account no longer exists
- The Membership record still shows tenant_id, role_id, is_owner, dates
- Authorization checks: Membership with NULL `account_id` is treated as inactive (no authentication possible)
- Cleanup: SuperAdmin can purge orphaned memberships after a configurable period (180 days)

---

## Index Strategy

### Index Specifications

| Table | Index | Columns | Type | Purpose |
|---|---|---|---|---|
| `accounts` | `accounts_email_unique` | `email` | UNIQUE | Enforce global email uniqueness. Primary lookup for authentication. |
| `accounts` | `accounts_status_index` | `status` | INDEX | Filter active/suspended/banned accounts for authentication gate. |
| `accounts` | `accounts_deleted_at_index` | `deleted_at` | INDEX | Exclude soft-deleted accounts from authentication queries. |
| `tenant_memberships` | `tm_account_id_tenant_id_unique` | `(account_id, tenant_id)` | UNIQUE | One membership per Account per Tenant. Core integrity constraint. |
| `tenant_memberships` | `tm_tenant_id_account_id_index` | `(tenant_id, account_id)` | INDEX | Reverse lookup: find all members of a tenant. Same columns, opposite order for query coverage. |
| `tenant_memberships` | `tm_tenant_id_owner_index` | `(tenant_id, is_owner)` | PARTIAL INDEX (WHERE is_owner=true) | Fast lookup of tenant owner. Used in ownership transfer checks, owner-only notifications. |
| `tenant_memberships` | `tm_account_id_index` | `(account_id)` | INDEX | Find all memberships for an Account (tenant switcher, login resolution). |
| `tenant_memberships` | `tm_tenant_id_status_index` | `(tenant_id, status)` | INDEX | Find active/suspended/invited members in a tenant. Used in notification routing. |
| `tenant_memberships` | `tm_role_id_index` | `(role_id)` | INDEX | FK index for role_id lookups. |
| `tenant_memberships` | `tm_invited_by_index` | `(invited_by)` | INDEX | FK index for invitation tracking. |
| `customer_profiles` | `cp_tenant_membership_id_unique` | `(tenant_membership_id)` | UNIQUE | One customer profile per membership. |
| `staff_profiles` | `sp_tenant_membership_id_unique` | `(tenant_membership_id)` | UNIQUE | One staff profile per membership. |
| `merchant_profiles` | `mp_tenant_membership_id_unique` | `(tenant_membership_id)` | UNIQUE | One merchant profile per membership. |
| `social_accounts` | `sa_provider_provider_id_unique` | `(provider, provider_id)` | UNIQUE | One link per provider identity. Used for OAuth lookup. |
| `social_accounts` | `sa_account_id_index` | `(account_id)` | INDEX | Find all provider links for an Account. |
| `sessions` | `sessions_account_id_index` | `(account_id)` | INDEX | Find all sessions for an Account (session management, revocation). |
| `sessions` | `sessions_user_id_index` | `(user_id)` | INDEX | Existing index. Keep during transition for legacy session lookup. |
| `password_reset_tokens` | `password_reset_tokens_account_id_primary` | `(account_id)` | PRIMARY | One active reset token per Account. |
| `notifications` | `notifications_tenant_id_index` | `(tenant_id)` | INDEX | Existing index. Filter notifications by tenant context. |

### Index Justification

**Why composite index on `(account_id, tenant_id)` instead of two separate indexes?**
- The unique constraint requires the composite. The most common queries are "find membership for (account, tenant)" — a composite lookup uses both columns.
- Reverse order `(tenant_id, account_id)` covers queries like "find all members of this tenant."

**Why PARTIAL INDEX on `(tenant_id, is_owner)`?**
- MySQL does not support partial indexes (WHERE clause) natively. Instead, use `WHERE is_owner = 1 AND tenant_id = ?`. A composite index on `(tenant_id, is_owner)` works efficiently because is_owner has low cardinality and tenant_id is highly selective.
- Alternative: If PostgreSQL is ever used, a partial index would be ideal.

**Why index on `accounts.status`?**
- Authentication gate checks `WHERE status = 'active' AND deleted_at IS NULL`. The combination of status + deleted_at filter is used on every login request. A composite index on `(status, deleted_at)` may be added if profiling shows this query is a bottleneck.

---

## Constraint Strategy

### Primary Keys

| Table | PK Strategy | Justification |
|---|---|---|
| All new tables | `id` BIGINT UNSIGNED AUTO_INCREMENT | Consistent with existing Laravel convention. Matches existing FK references. |
| `password_reset_tokens` | `account_id` (no auto-increment) | One token per Account. Upsert pattern: `INSERT ... ON DUPLICATE KEY UPDATE`. |

### Unique Constraints

| Table | Constraint | Columns | Justification |
|---|---|---|---|
| `accounts` | UNIQUE | `email` | Global email uniqueness per architecture principle. |
| `tenant_memberships` | UNIQUE | `(account_id, tenant_id)` | One membership per Account per Tenant. |
| `tenant_memberships` | Partial UNIQUE | `(tenant_id)` WHERE `is_owner = true` | Enforced at application level. At most one owner per tenant. Database constraint uses application-level validation + trigger or stored procedure if needed. |
| `customer_profiles` | UNIQUE | `tenant_membership_id` | One customer profile per membership. |
| `staff_profiles` | UNIQUE | `tenant_membership_id` | One staff profile per membership. |
| `merchant_profiles` | UNIQUE | `tenant_membership_id` | One merchant profile per membership. |
| `social_accounts` | UNIQUE | `(provider, provider_id)` | One link per provider identity. |

### Application-Level Constraints (Not Enforced at Database Level)

| Constraint | Enforcement | Rationale |
|---|---|---|
| At most one owner per tenant | Application logic in ownership transfer + unique index on is_owner (partial index not supported in MySQL without generated column trick) | MySQL does not support partial unique indexes natively. Use `where('is_owner', true)->first()` validation in service layer. |
| CustomerProfile only for customer-role memberships | Application logic in registration flow | The UNIQUE constraint prevents duplicates but does not prevent creating a CustomerProfile for an admin membership. Service layer must validate. |
| StaffProfile only for admin-role non-owner memberships | Application logic in staff creation flow | Same as above. |
| Profile types are mutually exclusive | Application logic | A membership should not have both CustomerProfile and StaffProfile. Validate in service layer. |

---

## Migration Order

### Step 1: Create New Identity Tables (Phase 1)

**Order:**
1. `accounts` table (no dependencies)
2. `merchant_profiles` table (depends on tenant_memberships, but created after tenant_memberships)
3. `customer_profiles` table (depends on tenant_memberships)
4. `staff_profiles` table (depends on tenant_memberships)

Wait — `tenant_memberships` depends on `accounts` AND `tenants` AND `roles` (all exist).
So the correct order is:

1. `accounts` (no dependencies — new root table)
2. `password_reset_tokens` (recreate with account_id as PK — depends on accounts)
3. `tenant_memberships` (depends on accounts, tenants, roles — all exist)
4. `customer_profiles` (depends on tenant_memberships)
5. `staff_profiles` (depends on tenant_memberships)
6. `merchant_profiles` (depends on tenant_memberships)
7. `social_accounts` (depends on accounts — future-ready, empty schema)

### Step 2: Modify Existing Tables (Phase 1 continued)

1. `sessions` — Add `account_id` and `current_tenant_membership_id` columns
2. `notifications` — No schema change needed (tenant_id already exists)

### Step 3: Data Migration (Phase 3)

1. Backfill `accounts` from `users` (group by email)
2. Backfill `tenant_memberships` from `users` + `model_has_roles`
3. Backfill `customer_profiles` from `users` (customer-role users)
4. Backfill `staff_profiles` from `users` (admin-role, non-owner users)
5. Backfill `merchant_profiles` from `tenants` and `users` (owner-relevant data)
6. Backfill `sessions.account_id` from `sessions.user_id` → `accounts.id` mapping
7. Update `notifications.notifiable_id` to point to `accounts.id` where `notifiable_type = 'App\Models\User'`

### Step 4: Application Switch (Phase 4-5)

1. Deploy new auth guard (`accounts` provider)
2. Switch all reads to new tables
3. Enable dual-write (new records written to both new and legacy tables)
4. Verify correctness in production

### Step 5: Legacy Cleanup (Phase 8)

1. Remove dual-write (stop writing to `users` table)
2. Drop `users` table after one full release cycle
3. Drop `model_has_roles` entries for User records (Spatie tables remain)
4. Remove backward compatibility layer

### Migration Order Rationale

The migration proceeds in dependency order:

```
accounts (root identity)
  │
  ├── password_reset_tokens (depends on accounts)
  ├── tenant_memberships (depends on accounts + tenants + roles)
  │     │
  │     ├── customer_profiles (depends on tenant_memberships)
  │     ├── staff_profiles (depends on tenant_memberships)
  │     └── merchant_profiles (depends on tenant_memberships)
  ├── social_accounts (depends on accounts, no data yet)
  └── sessions (add account_id, no FK initially)
```

The `users` table must NEVER be dropped until all application code references are migrated. It remains readable throughout Phase 1-7. Only Phase 8 removes it.

---

## Data Migration Strategy

### Migration 1: Users → Accounts

```sql
-- Pseudo-code for the migration logic
INSERT INTO accounts (id, email, password, email_verified_at, remember_token,
                      profile_image, status, notification_preferences, last_login_at,
                      created_at, updated_at)
SELECT
    u.id,                          -- Keep same ID for FK compatibility
    u.email,
    u.password,
    u.email_verified_at,
    u.remember_token,
    u.profile_image,
    COALESCE(u.status, 'active'),
    u.notification_preferences,
    NULL,                          -- last_login_at (not tracked in users)
    u.created_at,
    u.updated_at
FROM users u
WHERE u.deleted_at IS NULL;
```

**Edge cases:**
- If duplicate emails exist (from a pre-migration relaxation), group by email and merge: use the most recent User record for Account fields, create multiple Memberships.
- If a User record has no email (data integrity issue), assign a placeholder and log an error.

### Migration 2: Users → TenantMemberships

```sql
INSERT INTO tenant_memberships (account_id, tenant_id, role_id, is_owner, status,
                                invited_by, invited_at, joined_at, is_default,
                                created_at, updated_at)
SELECT
    u.id,
    u.tenant_id,
    r.id AS role_id,               -- Spatie role mapped through model_has_roles
    u.is_owner,
    COALESCE(u.status, 'active'),  -- 'active' is the default
    NULL,                          -- invited_by (legacy data has no invitation tracking)
    NULL,                          -- invited_at
    u.created_at,                  -- Approximate joined_at (first appearance in system)
    u.is_owner OR FALSE,           -- is_default: owner's tenant is their default
    u.created_at,
    u.updated_at
FROM users u
JOIN model_has_roles mhr ON mhr.model_id = u.id
    AND mhr.model_type = 'App\Models\User'
JOIN roles r ON r.id = mhr.role_id
WHERE u.tenant_id IS NOT NULL;     -- Exclude SuperAdmin accounts
```

### Migration 3: TenantMemberships → Owner Verification

```sql
-- After migration 2, verify every tenant has exactly one owner
-- For tenants with no owner: assign the oldest admin user
-- For tenants with multiple owners: keep only the first, demote others
UPDATE tenant_memberships
SET is_owner = TRUE
WHERE tenant_id IN (
    SELECT t.id FROM tenants t
    WHERE NOT EXISTS (
        SELECT 1 FROM tenant_memberships tm
        WHERE tm.tenant_id = t.id AND tm.is_owner = TRUE
    )
)
AND id IN (                          -- Pick the oldest admin
    SELECT MIN(tm2.id) FROM tenant_memberships tm2
    JOIN roles r ON r.id = tm2.role_id
    WHERE r.name = 'admin'
    AND tm2.tenant_id = t.id
);
```

### Migration 4: CustomerProfiles

```sql
INSERT INTO customer_profiles (tenant_membership_id, name, phone, created_at, updated_at)
SELECT
    tm.id,
    u.name,
    NULL AS phone,                  -- Phone not reliably stored on User
    u.created_at,
    u.updated_at
FROM tenant_memberships tm
JOIN users u ON u.id = tm.account_id
JOIN roles r ON r.id = tm.role_id
WHERE r.name = 'customer';
```

### Migration 5: StaffProfiles

```sql
INSERT INTO staff_profiles (tenant_membership_id, position, department, created_at, updated_at)
SELECT
    tm.id,
    NULL AS position,
    NULL AS department,
    u.created_at,
    u.updated_at
FROM tenant_memberships tm
JOIN users u ON u.id = tm.account_id
JOIN roles r ON r.id = tm.role_id
WHERE r.name = 'admin' AND tm.is_owner = FALSE;
```

### Migration 6: SuperAdmin Accounts

SuperAdmin accounts have `tenant_id IS NULL` in the `users` table. They are migrated as:

- Account record (same as migration 1)
- NO TenantMembership record
- Spatie superadmin role remains on `model_has_roles` (User model) during transition
- After auth switch: Account is checked for `superadmin` role via direct Spatie query on Account (Account model gets HasRoles for SuperAdmin detection only — see Phase 5)

### Migration 7: Sessions

```sql
UPDATE sessions s
JOIN accounts a ON a.email = (
    SELECT email FROM users u WHERE u.id = s.user_id
)
SET s.account_id = a.id;
```

### Migration 8: Notifications

```sql
-- Update existing notification records to reference Account model
UPDATE notifications
SET notifiable_type = 'App\Models\Account',
    notifiable_id = (
        SELECT a.id FROM accounts a
        JOIN users u ON u.email = a.email
        WHERE u.id = notifications.notifiable_id
    )
WHERE notifiable_type = 'App\Models\User';
```

### Orphan Record Handling

| Scenario | Handling |
|---|---|
| Users table has records with `tenant_id` pointing to deleted tenant | Skip membership creation. Log warning. Account is created without memberships. |
| Users table has records with `role_id` pointing to deleted role | Assign default `customer` role if tenant exists. Log warning. |
| Users table has duplicate emails | Merge into single Account. Create multiple Memberships. Log merge info. |
| Users table has records with `tenant_id = NULL` and no `superadmin` role | Create Account without memberships. Log warning: "Orphan user found with no tenant and no superadmin role." |

### Duplicate Prevention

| Phase | Mechanism |
|---|---|
| Phase 1 (schema) | UNIQUE index on `accounts.email` prevents duplicate emails from being inserted |
| Phase 3 (data migration) | Use `ON DUPLICATE KEY UPDATE` or `INSERT IGNORE` for first pass, then deduplicate second pass |
| Phase 4 (application) | Registration flow checks `accounts.email BEFORE INSERT` (not after) |
| Dual-write | New registrations write to both `accounts` and `users`. The `accounts.email` unique constraint prevents duplicates. |

---

## Zero Downtime Strategy

### Deployment Phases

Each deployment phase is designed to be reversible and non-breaking.

#### Phase 1-2: Schema Deployment

**Strategy:** Add-only. No existing tables are dropped or altered in a breaking way.

| Action | Breaking? | Rollback |
|---|---|---|
| Create `accounts` table | No | `DROP TABLE` |
| Create `tenant_memberships` table | No | `DROP TABLE` |
| Create profile tables | No | `DROP TABLE` |
| Add `account_id` to `sessions` | No (nullable) | `DROP COLUMN` |
| Recreate `password_reset_tokens` | **Yes** (drops old table) | Keep old table until Phase 4 |

**Mitigation for password_reset_tokens:**
- Do NOT drop the old `password_reset_tokens` table in Phase 1.
- Create the new `password_reset_tokens_new` table alongside the old one.
- During transition, check both tables.
- Only after Phase 4 (auth switch) is verified, drop the old table.

#### Phase 3: Data Migration (Background Job)

**Strategy:** Read-only migration. No writes to new tables during migration.

1. Run migration as a queued job or CLI command during low-traffic window
2. The migration reads from `users` and writes to `accounts` + `tenant_memberships`
3. No application code reads from new tables yet
4. Verify data integrity after migration
5. Rollback: truncate new tables, verify no data loss in old tables

#### Phase 4: Authentication Switch (Feature Flag)

**Strategy:** Feature flag + dual-read.

1. Deploy code that CAN read from both `users` and `accounts`
2. Feature flag: `IDENTITY_USE_ACCOUNTS = false` (default, reads from users)
3. Enable dual-write: new registrations write to BOTH `accounts` and `users`
4. Verify dual-write correctness in production
5. Flip feature flag to `true`: authentication reads from accounts
6. Monitor error rates, login success rates
7. If errors spike: flip flag back to `false` (reads from users again)

```php
// config/feature.php (pseudo-config)
'identity_use_accounts' => env('IDENTITY_USE_ACCOUNTS', false),

// Auth provider (pseudo-code)
if (config('feature.identity_use_accounts')) {
    $provider = 'accounts';  // App\Models\Account
} else {
    $provider = 'users';     // App\Models\User (legacy)
}
```

#### Phase 5: Authorization Switch

**Strategy:** Feature flag on Gate::before().

1. Deploy Gate::before() code alongside existing Spatie checks
2. Feature flag: `IDENTITY_USE_GATE_BEFORE = false` (uses existing HasRoles on User)
3. Switch to `true` after verification
4. Rollback: flip flag to `false`

#### Phase 6-7: Incremental Migration

**Strategy:** Per-module feature flags (one per module from the backward compatibility matrix).

Each module (notifications, billing, payments, etc.) has its own feature flag:

```php
'identity_migrate_notifications' => false,
'identity_migrate_billing' => false,
'identity_migrate_payments' => false,
```

When a module is migrated and verified, its flag is set to `true`. If a regression is found, only that module's flag is rolled back.

#### Phase 8: Legacy Cleanup

**Strategy:** Deprecation window.

1. After all modules are migrated (Phase 6-7 complete), keep legacy code for one full release cycle
2. Monitor error logs for any legacy code paths being hit
3. After zero legacy code hits for one release cycle, remove backward compatibility layer
4. Final migration: drop `users` table, remove dual-write
5. Rollback plan: restore `users` table from backup if critical regression found (emergency only)

### Rollback Plan Summary

| Phase | Rollback Action | Data Loss Risk |
|---|---|---|
| Phase 1-2 | Drop new tables. Remove new columns. | None |
| Phase 3 | Truncate new tables. Users table untouched. | None |
| Phase 4 | Flip feature flag to `false`. | None |
| Phase 5 | Flip feature flag to `false`. | None |
| Phase 6-7 | Flip per-module flag to `false`. | None |
| Phase 8 | Restore from database backup. | Minimal (depends on time since drop) |

---

## Backward Compatibility Strategy

### Legacy User Model

The existing `App\Models\User` MUST continue working throughout the migration. Two strategies are used:

#### Strategy A: User as Account Wrapper (Recommended)

```php
// During transition: User is a wrapper/proxy for Account
class User extends Authenticatable
{
    // Use the 'accounts' table as the data source
    protected $table = 'accounts';

    // Provide backward-compatible accessors
    public function getNameAttribute()
    {
        return $this->customerProfile?->name ?? 'User';
    }

    public function getTenantIdAttribute()
    {
        return $this->currentMembership?->tenant_id;
    }

    public function getIsOwnerAttribute()
    {
        return $this->currentMembership?->is_owner ?? false;
    }

    // Backward-compatible relationships
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function orders()
    {
        return $this->hasManyThrough(Order::class, TenantMembership::class, 'account_id', 'user_id');
    }
}
```

**Advantages:**
- All existing `$user->*` calls continue working
- No changes to 66 controllers, 84 services, and all frontend components until Phase 8
- Third-party packages that reference `App\Models\User` continue working

**Disadvantages:**
- The `User` model needs to know about `currentMembership` (needs session context)
- Some accessors may be slower (indirection through Membership)

#### Strategy B: User Model as Legacy Table Reader

```php
// Alternative: User model continues reading from 'users' table
class User extends Authenticatable
{
    protected $table = 'users';
    // ...unchanged from current implementation
}
```

**This is only viable until Phase 4.** After the auth guard switches to `accounts`, `Auth::user()` returns an `Account` instance, not a `User` instance. Strategy A is needed to bridge this gap.

### Compatibility Layer Decisions

| Concern | Decision |
|---|---|
| `Auth::user()` returns what? | Returns `Account` instance after Phase 4. The `User` model is a wrapper that is NOT returned by Auth. |
| Inertia `auth.user` | After Phase 4, `auth.user` contains Account data. Add backward-compatible keys (`name`, `email`, `tenant_id`, `is_owner`) via accessors or merge. |
| `$user->can()` | After Phase 5, `$account->can()` goes through Gate::before(). Legacy `$user->can()` calls use the same Gate. |
| `model_has_roles` for User | Legacy entries remain in `model_has_roles` during transition. New code does NOT add entries here. |
| Third-party packages | If a package calls `Auth::user()` and expects `User` class, use a class alias: `class_alias(Account::class, User::class)` — but this is fragile. Prefer configuring the package to accept the Account model. |

### Safe Cleanup Criteria

The `users` table is dropped only when ALL of the following are true:

1. `accounts.email_verified_at` has the same data as `users.email_verified_at` (verified)
2. All `user_id` foreign keys in the database have been migrated to `account_id`
3. All existing sessions reference `account_id` (not `user_id`)
4. All notification records reference `App\Models\Account` (not `App\Models\User`)
5. No controller or service file in `app/` references `App\Models\User` (except the compatibility wrapper)
6. All tests pass with `IDENTITY_USE_ACCOUNTS = true`
7. One full release cycle has passed with zero legacy code path hits

---

## ER Diagram

### Textual Entity-Relationship Diagram

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                                  ACCOUNTS                                             │
│  (id PK, email UQ, password, email_verified_at, remember_token, profile_image,      │
│   status, notification_preferences, last_login_at, last_login_ip,                   │
│   created_at, updated_at, deleted_at)                                                │
└─────────────────────────────────────────────────────────────────────────────────────┘
  │ 1
  │
  ├─────────────┬─────────────────────┬──────────────────┬──────────────────┐
  │             │                     │                  │                  │
  │ 0..*        │ 0..*                │ 1                │ 1                │
  │             │                     │                  │                  │
  ▼             ▼                     ▼                  ▼                  ▼
┌────────┐ ┌──────────┐ ┌──────────────────────┐ ┌──────────────┐ ┌──────────────────┐
│SOCIAL  │ │TENANT    │ │ PASSWORD_RESET_TOKENS │ │   SESSIONS   │ │  NOTIFICATIONS   │
│ACCOUNTS│ │MEMBERSHIPS│ │                      │ │              │ │                  │
│        │ │          │ │ (account_id PK,       │ │ (id PK,      │ │ (id UUID PK,     │
│(id PK, │ │(id PK,   │ │  token, created_at)   │ │  user_id,    │ │  notifiable_type, │
│account │ │account_id│ └──────────────────────┘ │  account_id,  │ │  notifiable_id,  │
│_id FK, │ │FK,       │                          │  membership_id│ │  data JSON,      │
│provider│ │tenant_id │                          │  ip_address,  │ │  tenant_id FK,   │
│UQ,     │ │FK,       │                          │  user_agent,  │ │  read_at,        │
│provider│ │role_id   │                          │  payload,     │ │  created_at)     │
│_id,    │ │FK,       │                          │  last_act.)   │ └──────────────────┘
│token,  │ │is_owner, │                          └──────────────┘
│refresh │ │status,   │
│_token, │ │invited_by│
│expires)│ │FK,       │
└────────┘ │invited_at│
           │joined_at,│
           │is_default│
           │deleted_at│
           │          │
           │UQ(account│
           │_id,      │
           │tenant_id)│
           └────┬─────┘
                │ 0..1
                │
     ┌──────────┼──────────────┐
     │          │              │
     0..1       0..1           0..1
     │          │              │
     ▼          ▼              ▼
┌──────────┐ ┌──────────┐ ┌──────────────┐
│CUSTOMER  │ │ STAFF    │ │ MERCHANT     │
│PROFILES  │ │ PROFILES │ │ PROFILES     │
│          │ │          │ │              │
│(id PK,   │ │(id PK,   │ │(id PK,       │
│membership│ │membership│ │membership    │
│_id UQ FK,│ │_id UQ FK,│ │_id UQ FK,   │
│name,     │ │position, │ │business_name,│
│phone,    │ │dept)     │ │tax_id,       │
│metadata) │ │          │ │bus_address)  │
└──────────┘ └──────────┘ └──────────────┘
```

### External Entity Relationships

```
TENANT_MEMBERSHIPS (1) ──── (1) TENANTS
  │
  └── (1) ROLES (Spatie: tenant_id, name, guard_name UQ)
         │
         └── (*) PERMISSIONS (Spatie: via role_has_permissions)
```

---

## Security Considerations

### Password Storage

| Concern | Implementation |
|---|---|
| Algorithm | Bcrypt via Laravel's `Hash::make()` or `hashed` cast |
| Cost factor | Default (10). No less than 10. |
| Column type | VARCHAR(255) — sufficient for bcrypt output (60 chars) with overhead |
| Reset flow | Token stored in `password_reset_tokens`, keyed by `account_id`. Token is hashed before storage. |
| Never logged | Password field is excluded from logging, JSON serialization, and audit trails |

### Email Verification

| Concern | Implementation |
|---|---|
| Verified column | `accounts.email_verified_at` (TIMESTAMP NULLABLE) |
| Verification link | Signed URL with `id` + `hash` (Laravel's `MustVerifyEmail`) |
| Unverified access | Blocked by `verified` middleware. Applies to all tenants. |
| Re-verification | If email changes (future feature), `email_verified_at` is set to NULL and re-verification is required. |

### Soft Delete

| Table | Soft Delete Column | Purpose |
|---|---|---|
| `accounts` | `deleted_at` | Preserve audit trail. Prevent authentication. |
| `tenant_memberships` | `deleted_at` | Preserve membership history. `status = 'removed'` is preferred over soft-delete for active membership termination. |
| `customer_profiles` | `deleted_at` | Preserve customer data for order history. |
| `staff_profiles` | `deleted_at` | Preserve staff assignment history. |
| `merchant_profiles` | `deleted_at` | Preserve business ownership history. |

**Soft-delete behavior:**
- `Account` with `deleted_at` set → cannot authenticate (blocked at login gate)
- `TenantMembership` with `deleted_at` set → excluded from authorization checks
- Soft-deleted records are excluded from all default queries via Laravel's `SoftDeletes` trait
- Hard-delete is never used for identity tables. Only database cleanup scripts (after 180+ days) may purge soft-deleted records.

### Ownership Protection

| Scenario | Protection |
|---|---|
| Multiple owners | Application-level check: `tenant_memberships` partial unique constraint enforced in service layer |
| Owner removal | Transfer only (never direct delete). Owner must transfer to another active member first. |
| Owner account deletion | SET NULL on `tenant_memberships.account_id`. Tenant becomes ownerless. SuperAdmin intervention required. |
| Owner status change | Owner cannot be suspended by anyone except themselves (via transfer). SuperAdmin can override in emergency. |

### Membership Validation

| Validation | Where Enforced |
|---|---|
| UNIQUE(account_id, tenant_id) | Database-level unique constraint |
| At most one owner per tenant | Application-level (service layer) |
| Role exists and is active | FK constraint + application check |
| Membership status transitions | Application-level: `invited → active → suspended → removed` |
| Owner cannot have status = 'invited' | Application-level: owner membership is always 'active' |

### Tenant Isolation

| Boundary | Mechanism |
|---|---|
| Membership lookup | Scoped by `(account_id, tenant_id)` — only returns membership if both match |
| Business data | TenantAware global scope on all business models |
| Route model binding | ValidateTenantBinding middleware checks entity's tenant_id matches current tenant |
| API | X-Tenant header validated against membership |
| Notification | `tenant_id` in notification payload filters by current tenant context |

### Session Security

| Concern | Implementation |
|---|---|
| Session ID | Random 40-character string (Laravel default) |
| Session fixation | `session()->regenerate()` after login |
| Session expiry | Configurable lifetime (default: 120 minutes). "Remember me" extends to 5 years. |
| Session revocation | Delete from `sessions` table WHERE `account_id = ?` |
| Concurrent session limit | Not enforced by default. Future: limit to N sessions per account. |

---

## Performance Considerations

### Query Analysis

#### Authentication Query

```sql
-- Every login request
SELECT * FROM accounts
WHERE email = ? AND deleted_at IS NULL
LIMIT 1;
```

**Index:** `accounts_email_unique` covers the lookup. `deleted_at` is not indexed but is a minor filter (most records are not deleted).

**Performance:** O(1) lookup by email. Expected <1ms for any scale.

#### Membership Lookup Query

```sql
-- Every authenticated request (middleware)
SELECT * FROM tenant_memberships
WHERE account_id = ? AND tenant_id = ? AND deleted_at IS NULL
LIMIT 1;
```

**Index:** `tm_account_id_tenant_id_unique` covers the lookup. Both columns are in the unique constraint.

**Performance:** O(1) lookup by composite key. Expected <1ms.

#### Permission Check Query

```sql
-- Every Gate::before() call (cached by Spatie after first load)
SELECT p.* FROM permissions p
JOIN role_has_permissions rhp ON rhp.permission_id = p.id
WHERE rhp.role_id = ?;
```

**Index:** Spatie's `role_has_permissions` primary key covers this. Spatie caches permissions for 24 hours.

**Performance:** First request loads all permissions for the role (cache miss). Subsequent requests hit Spatie cache. Expected <1ms for cached, <5ms for cache miss.

#### Owner Lookup Query

```sql
-- Owner-only notifications, ownership transfer validation
SELECT * FROM tenant_memberships
WHERE tenant_id = ? AND is_owner = TRUE AND deleted_at IS NULL
LIMIT 1;
```

**Index:** `tm_tenant_id_owner_index` (composite on `tenant_id, is_owner`). LOW cardinality on `is_owner` — but `tenant_id` is highly selective.

**Performance:** Expected <2ms for any scale. The tenant_id filter narrows the result set to one tenant's memberships, then is_owner picks the single owner.

#### Tenant Switcher Query

```sql
-- Find all active memberships for an Account
SELECT * FROM tenant_memberships
WHERE account_id = ? AND deleted_at IS NULL;
```

**Index:** `tm_account_id_index` covers this.

**Performance:** Expected <1ms. Most accounts have fewer than 10 memberships.

### Expected Scaling

| Metric | 1K Tenants | 10K Tenants | 100K Tenants | 1M Accounts |
|---|---|---|---|---|
| `accounts` table size | ~1K rows | ~50K rows | ~500K rows | 1M rows |
| `tenant_memberships` size | ~2K rows | ~150K rows | ~1.5M rows | 3M rows |
| Authentication query | <1ms | <1ms | <1ms | <1ms |
| Membership lookup | <1ms | <1ms | <2ms | <2ms |
| Permission check (cached) | <1ms | <1ms | <1ms | <1ms |
| Permission check (miss) | <5ms | <5ms | <5ms | <5ms |
| Owner lookup | <1ms | <1ms | <2ms | <2ms |
| Tenant switcher | <1ms | <1ms | <2ms | <2ms |

### Bottleneck Analysis

| Bottleneck | Risk | Mitigation |
|---|---|---|
| **Permission cache invalidation** | Low-Medium | Spatie flushes cache on role/permission change. At 100K tenants, a role change in one tenant should not flush the entire cache. Mitigation: Use Spatie's cache-per-tenant pattern (cache key includes tenant_id). |
| **Notification table size** | Medium | Notifications grow unbounded. At 1M accounts with 10 notifications/month, the table grows by 10M rows/month. Mitigation: Archive or purge notifications older than 90 days. |
| **Session table with database driver** | Medium | Database sessions at scale require table cleanup. Mitigation: Use Redis for session driver in production. |
| **Membership table JOINs** | Low | Most queries use direct FK lookups (account_id, tenant_id). No heavy JOINs expected. The `with('role.permissions')` eager load is the heaviest query. Mitigation: Eager loading is scoped to the current membership (1 record). |

---

## Scaling Strategy

### Short-Term (1K-10K Tenants)

- All tables on a single MySQL instance
- No partitioning needed
- Standard indexes sufficient
- Session driver: database (default Laravel) or file

### Medium-Term (10K-100K Tenants)

- Move session driver to Redis
- Implement notification archival (move notifications > 90 days to `notifications_archive` table)
- Add `deleted_at` composite indexes for soft-delete queries
- Consider read replicas for notification-heavy queries

### Long-Term (100K+ Tenants, 1M+ Accounts)

- Partition `notifications` by month (MySQL partitioning)
- Implement Redis caching for membership lookup (cache key: `membership:{account_id}:{tenant_id}`)
- Consider sharding `tenant_memberships` by `tenant_id` range
- Implement rate limiting on auth endpoints at the load balancer level (not just application)
- Move to dedicated MySQL instance for identity tables (separate from business data)

---

## Engineering Self Review

### Identified Issues and Resolutions

#### Issue 1: Dual-Write Complexity During Migration

**Risk:** Writing to both `accounts` and `users` tables during Phase 4 introduces a window where the two tables can diverge (e.g., write to accounts succeeds, write to users fails).

**Resolution:** Wrap dual-writes in a database transaction where possible. For writes spanning multiple connections (different databases), implement a compensatory action: if the users write fails, log the failure and queue a reconciliation job. Accept temporary divergence — the accounts table is the source of truth, and the users table is deprecated.

#### Issue 2: Password Reset Token Table Recreation

**Risk:** Dropping and recreating `password_reset_tokens` mid-deployment invalidates all active reset tokens.

**Resolution:** Do NOT drop the old table in Phase 1. Create the new table with a different name (`password_reset_tokens_new`). During transition, check both tables for token validation. After Phase 4 is verified, drop the old table and rename the new one.

#### Issue 3: Tenant ID on Roles vs. Membership

**Risk:** The `roles` table already has `tenant_id` (from existing migration `2026_05_28_000006_add_tenant_id_to_roles.php`). The `tenant_memberships.role_id` FK references a tenant-scoped role. There is a theoretical risk that a membership in Tenant A references a role that belongs to Tenant B.

**Resolution:** The migration must ensure that roles are created per-tenant correctly (the existing backfill migration already does this). Application code must never create a membership with a role_id from a different tenant. The `CreateStoreController` creates tenant-scoped roles during store creation. Add application-level validation: `throw_if($membership->role->tenant_id !== $membership->tenant_id)`.

#### Issue 4: Soft-Delete Cascade on Tenants

**Risk:** If a tenant is soft-deleted, CASCADE on `tenant_memberships.tenant_id` would delete all memberships. This is undesirable — the tenant's data (including membership records) should be preserved for audit.

**Resolution:** Do NOT use CASCADE for tenant soft-delete. The migration should handle soft-delete at the application level: when a tenant is soft-deleted, its memberships are NOT deleted. The `TenantAware` global scope handles business data isolation. Membership records are only deleted when the tenant is hard-deleted (which should never happen in production).

#### Issue 5: Profile Table Proliferation

**Risk:** Three profile tables (customer, staff, merchant) may proliferate further with future role types. Each profile table adds migration overhead, query complexity, and maintenance burden.

**Resolution:** Accept three profile tables for Phase 1. If the number of profile types grows beyond 5, consider a single `membership_profiles` table with a `type` discriminator column and JSON metadata. The three-table approach is cleaner for current use cases and avoids premature abstraction.

#### Issue 6: Activity Log Causer Migration

**Risk:** The `activity_logs.causer_type` column stores polymorphic references. Existing records reference `App\Models\User`. After migration, new records reference `App\Models\Account`. Querying activity logs for a specific account must handle both types.

**Resolution:** During Phase 3 migration, update all activity_log records where `causer_type = 'App\Models\User'` to `causer_type = 'App\Models\Account'` with the corresponding `causer_id` mapping. This is a one-time data migration. After that, all new records use `App\Models\Account` consistently.

#### Issue 7: Session Table `user_id` vs `account_id`

**Risk:** During Phase 4 switchover, existing sessions have `user_id` set but `account_id` is NULL. If the authentication logic only checks `account_id`, valid sessions are rejected.

**Resolution:** During transition, the authentication middleware checks BOTH `user_id` AND `account_id`. If `account_id` is NULL, it falls back to `user_id` and maps through the `users → accounts` email mapping. After one full session lifetime (configurable: 120 minutes by default), all sessions should have `account_id` populated. After the transition window, the fallback is removed.

---

## Final Engineering Recommendation

### Implementation Order

1. **Phase 0.5** — This blueprint. Approve before any migration is written.

2. **Phase 1.1** — Create `accounts`, `tenant_memberships`, `customer_profiles`, `staff_profiles`, `social_accounts` tables. Add `account_id` and `current_tenant_membership_id` to `sessions`. Create `password_reset_tokens_new`.

3. **Phase 1.2** — Add indexes. Add foreign keys (with SET NULL for `tenant_memberships.account_id`, CASCADE for profiles, RESTRICT for role_id).

4. **Phase 3** — Data migration from `users` to new tables. Run as background job. Verify data integrity.

5. **Phase 4** — Deploy with feature flag. Enable dual-write. Flip flag after verification.

6. **Phase 5** — Deploy Gate::before() with feature flag. Flip flag after verification.

7. **Phase 6-7** — Per-module migration using the backward compatibility matrix. One module at a time.

8. **Phase 8** — Remove backward compatibility layer. Drop `users` table after one full release cycle.

### Critical Success Factors

- **Never rush Phase 3 (Data Migration).** Test on a full production-size copy. Verify every tenant has exactly one owner.
- **Never deploy Phase 4 and Phase 5 without feature flags.** The ability to flip back is the safety net.
- **Never drop the `users` table before Phase 8.** Keep it for reference and rollback.
- **Monitor authentication error rates during Phase 4 and Phase 5.** Any spike should trigger an automatic flag flip.
- **Document every workaround in the code.** Future developers must understand why the backward compatibility layer exists.

### Risks Worth Accepting

| Risk | Why Acceptable |
|---|---|
| Three profile tables instead of one | Current clarity over future abstraction. Can merge later if needed. |
| Dual-write complexity | Temporary (one release cycle). Compensatory actions for failures. |
| Role tenant_id validation | Single `throw_if` guard covers all edge cases. |
| Session fallback during transition | Temporary (one session lifetime). Affects only active sessions during deployment window. |

---

*This blueprint is the single source of truth for every migration related to Identity. No database architecture decisions should be made during implementation without updating this document first. Approved by Principal Architect on 2026-07-07.*
