# Identity Implementation Plan — v1

**Status:** FINAL — Planning Complete  
**Date:** 2026-07-07  
**Version:** 1.0  
**Project:** Multi-tenant SaaS E-commerce Platform  
**Governed by:** `docs/identity-architecture-lock-v2.md`  
**Blueprint source:** `docs/identity-database-blueprint-v1.md`  
**QA baseline:** `docs/merchant-qa-sprint-report.md`  
**Purpose:** Eliminate uncertainty during implementation. Every modified file is known before implementation begins. Every regression risk is identified before code is written.

---

## Table of Contents

1. Executive Summary
2. Implementation Philosophy
3. Sprint Roadmap
4. Sprint Details
5. File Impact Analysis
6. Service Layer Impact
7. Authentication Plan
8. Authorization Plan
9. Data Migration Plan
10. Backward Compatibility Plan
11. Regression Plan
12. Testing Strategy
13. Deployment Strategy
14. Engineering Self Review
15. Final Engineering Recommendation

---

## 1. Executive Summary

This document is the official implementation plan for the Identity Architecture migration across 8 production-ready sprints. It translates the locked architecture (v2) and locked database blueprint (v1) into executable work packages with complete file-level impact analysis.

**Current state:** The platform uses a `users` table that conflates identity, tenant membership, role, and ownership. The `users` table has a single `tenant_id` FK, meaning one user can belong to only one tenant. Multi-store ownership, cross-store customer registration, and staff cross-collaboration are impossible.

**Target state:** An Account → Membership → Tenant → Role → Permission hierarchy. A single Account (natural person) can own multiple stores, be a customer in other stores, and be staff in yet others — all with one email and one password.

**Migration approach:** Feature-flagged, zero-downtime, backward-compatible. The `users` table is kept for one full release cycle before deprecation. Dual-write during transition. Per-module feature flags for incremental migration.

**Risk summary:** 5 high-risk areas identified:
1. Data migration (users → accounts + memberships) — highest risk phase
2. Auth guard switchover — affects ALL authenticated requests
3. Gate::before() implementation — affects ALL authorization checks
4. Session compatibility during transition
5. Frontend Inertia shared data changes

**Engineering verdict:** The migration is feasible within 8 sprints. The feature flag strategy provides rollback capability at every stage. The backward compatibility matrix ensures every module is audited before changes.

---

## 2. Implementation Philosophy

### 2.1 Zero-Regression First

Every change must be backward compatible. No existing feature should break during any sprint. The `users` table and `App\Models\User` model continue working until Phase 8.

### 2.2 Feature Flags Gate Every Switch

| Flag | Purpose | Default | Rollback Action |
|---|---|---|---|
| `IDENTITY_USE_ACCOUNTS` | Switch auth provider from `users` to `accounts` | `false` | Flip to `false` |
| `IDENTITY_USE_GATE_BEFORE` | Enable custom Gate::before() authorization | `false` | Flip to `false` |
| `IDENTITY_MIGRATE_NOTIFICATIONS` | Use Account model for notifications | `false` | Flip to `false` |
| `IDENTITY_MIGRATE_BILLING` | Use Account/membership for billing | `false` | Flip to `false` |
| `IDENTITY_MIGRATE_PAYMENTS` | Use Account for payment flows | `false` | Flip to `false` |
| `IDENTITY_MIGRATE_ORDERS` | Use Account for order relationships | `false` | Flip to `false` |

### 2.3 Incremental by Module

Modules are migrated one at a time using per-module feature flags. If a regression is found in Billing, only the billing flag is rolled back. Other modules continue using the new identity system.

### 2.4 No Architectural Decisions During Implementation

Every architectural decision is already locked in v2. Every database decision is already locked in the blueprint. Implementation follows the plan — it does not make new decisions.

### 2.5 Safe Cleanup Criteria

The `users` table is dropped only when ALL of the following are true:
1. All `users` data is confirmed migrated to `accounts` + `tenant_memberships`
2. All foreign keys referencing `users.id` have been migrated to `accounts.id`
3. All sessions reference `account_id` (not `user_id`)
4. All notification records reference `App\Models\Account`
5. No controller or service file references `App\Models\User` (except the compatibility wrapper)
6. All tests pass with `IDENTITY_USE_ACCOUNTS = true`
7. One full release cycle with zero legacy code path hits

---

## 3. Sprint Roadmap

```
Sprint 1: Database Foundation     → Create new tables, modify existing
Sprint 2: Models & Relationships  → Eloquent models, relationships, scopes
Sprint 3: Data Migration          → Backfill accounts/memberships/profiles from users
Sprint 4: Authentication          → Switch auth guard, session changes, login flows
Sprint 5: Authorization           → Gate::before(), middleware updates, policies
Sprint 6: Registration & Flows    → Invitations, store switching, ownership transfer
Sprint 7: Notifications & Billing → Account-level notifications, billing migration
Sprint 8: Testing & QA            → Full regression, performance, security, cleanup
```

### 3.1 Dependency Graph

```
Sprint 1 (Database)
    │
    ▼
Sprint 2 (Models)
    │
    ▼
Sprint 3 (Data Migration)
    │
    ├──────────────────┐
    ▼                  ▼
Sprint 4 (Auth)    Sprint 5 (Authz)
    │                  │
    └────────┬─────────┘
             ▼
       Sprint 6 (Registration)
             │
             ▼
       Sprint 7 (Notifications)
             │
             ▼
       Sprint 8 (Testing & QA)
```

### 3.2 Parallelization Opportunities

- Sprint 4 (Authentication) and Sprint 5 (Authorization) should NOT be parallelized — they share Gate::before() and middleware dependencies
- Sprint 6 (Registration) can begin mid-Sprint 5 once auth guard is verified
- Sprint 7 (Notifications) can be parallelized with Sprint 6 if resources permit

---

## 4. Sprint Details

### Sprint 1: Database Foundation

**Purpose:** Create the new table structure. Zero application code changes.

**Scope:** 7 new tables, 2 modified tables, 1 deprecated table (no schema changes).

**Exit criteria:**
- All migrations run cleanly up and down
- Foreign keys and unique constraints verified
- Rollback works (down method restores prior state)
- No application code changes deployment

#### Migration 1: Create `accounts` table

| Column | Type | Constraints |
|---|---|---|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT |
| email | VARCHAR(255) | NOT NULL, UNIQUE |
| password | VARCHAR(255) | NOT NULL |
| email_verified_at | TIMESTAMP | NULLABLE |
| remember_token | VARCHAR(100) | NULLABLE |
| profile_image | VARCHAR(255) | NULLABLE |
| status | VARCHAR(50) | NOT NULL, DEFAULT 'active' |
| notification_preferences | JSON | NULLABLE |
| last_login_at | TIMESTAMP | NULLABLE |
| last_login_ip | VARCHAR(45) | NULLABLE |
| created_at | TIMESTAMP | NOT NULL |
| updated_at | TIMESTAMP | NOT NULL |
| deleted_at | TIMESTAMP | NULLABLE |

Indexes: `accounts_email_unique` (UNIQUE on email), `accounts_status_index`, `accounts_deleted_at_index`

#### Migration 2: Create `password_reset_tokens` (new table alongside old)

Create a NEW table `password_reset_tokens_new` with `account_id` as PK:

| Column | Type | Constraints |
|---|---|---|
| account_id | BIGINT UNSIGNED | PK, FK → accounts.id ON DELETE CASCADE |
| token | VARCHAR(255) | NOT NULL |
| created_at | TIMESTAMP | NULLABLE |

**Do NOT drop the old `password_reset_tokens` table.** Keep both during transition. The old table continues to be written to during Phase 1-3. After Phase 4 is verified, the old table is dropped and the new table is renamed.

#### Migration 3: Create `tenant_memberships` table

| Column | Type | Constraints |
|---|---|---|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT |
| account_id | BIGINT UNSIGNED | NOT NULL, FK → accounts.id ON DELETE SET NULL |
| tenant_id | BIGINT UNSIGNED | NOT NULL, FK → tenants.id ON DELETE CASCADE |
| role_id | BIGINT UNSIGNED | NOT NULL, FK → roles.id ON DELETE RESTRICT |
| is_owner | BOOLEAN | NOT NULL, DEFAULT FALSE |
| status | VARCHAR(50) | NOT NULL, DEFAULT 'active' |
| invited_by | BIGINT UNSIGNED | NULLABLE, FK → accounts.id ON DELETE SET NULL |
| invited_at | TIMESTAMP | NULLABLE |
| joined_at | TIMESTAMP | NULLABLE |
| is_default | BOOLEAN | NOT NULL, DEFAULT FALSE |
| created_at | TIMESTAMP | NOT NULL |
| updated_at | TIMESTAMP | NOT NULL |
| deleted_at | TIMESTAMP | NULLABLE |

Unique constraints: `tm_account_id_tenant_id_unique` on (account_id, tenant_id)
Indexes: `tm_tenant_id_account_id_index` on (tenant_id, account_id), `tm_tenant_id_status_index` on (tenant_id, status), `tm_account_id_index`, `tm_role_id_index`, `tm_invited_by_index`

**Note on SET NULL:** The `account_id` FK uses SET NULL (not CASCADE). This preserves the membership record as an audit trail when an Account is soft-deleted. The v1 specification used CASCADE; v2 overrides this to SET NULL.

#### Migration 4: Create `customer_profiles` table

| Column | Type | Constraints |
|---|---|---|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT |
| tenant_membership_id | BIGINT UNSIGNED | NOT NULL, UNIQUE, FK → tenant_memberships.id ON DELETE CASCADE |
| name | VARCHAR(255) | NOT NULL |
| phone | VARCHAR(20) | NULLABLE |
| metadata | JSON | NULLABLE |
| created_at | TIMESTAMP | NOT NULL |
| updated_at | TIMESTAMP | NOT NULL |
| deleted_at | TIMESTAMP | NULLABLE |

#### Migration 5: Create `staff_profiles` table

| Column | Type | Constraints |
|---|---|---|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT |
| tenant_membership_id | BIGINT UNSIGNED | NOT NULL, UNIQUE, FK → tenant_memberships.id ON DELETE CASCADE |
| position | VARCHAR(100) | NULLABLE |
| department | VARCHAR(100) | NULLABLE |
| created_at | TIMESTAMP | NOT NULL |
| updated_at | TIMESTAMP | NOT NULL |
| deleted_at | TIMESTAMP | NULLABLE |

#### Migration 6: Create `merchant_profiles` table

| Column | Type | Constraints |
|---|---|---|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT |
| tenant_membership_id | BIGINT UNSIGNED | NOT NULL, UNIQUE, FK → tenant_memberships.id ON DELETE CASCADE |
| business_name | VARCHAR(255) | NULLABLE |
| tax_id | VARCHAR(100) | NULLABLE |
| business_address | JSON | NULLABLE |
| metadata | JSON | NULLABLE |
| created_at | TIMESTAMP | NOT NULL |
| updated_at | TIMESTAMP | NOT NULL |
| deleted_at | TIMESTAMP | NULLABLE |

#### Migration 7: Create `social_accounts` table (empty schema, future-ready)

| Column | Type | Constraints |
|---|---|---|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT |
| account_id | BIGINT UNSIGNED | NOT NULL, FK → accounts.id ON DELETE CASCADE |
| provider | VARCHAR(50) | NOT NULL |
| provider_id | VARCHAR(255) | NOT NULL |
| provider_email | VARCHAR(255) | NULLABLE |
| avatar_url | VARCHAR(500) | NULLABLE |
| token | TEXT | NULLABLE |
| refresh_token | TEXT | NULLABLE |
| expires_at | TIMESTAMP | NULLABLE |
| created_at | TIMESTAMP | NOT NULL |
| updated_at | TIMESTAMP | NOT NULL |

Unique: `sa_provider_provider_id_unique` on (provider, provider_id)

#### Migration 8: Modify `sessions` table

Add columns:
- `account_id` BIGINT UNSIGNED NULLABLE (FK → accounts.id ON DELETE CASCADE)
- `current_tenant_membership_id` BIGINT UNSIGNED NULLABLE

Add index: `sessions_account_id_index` on (account_id)

**Do NOT remove `user_id` column.** Keep it for backward compatibility during transition.

#### Migration 9: Modify `notifications` table

No schema changes needed. The `tenant_id` column already exists. Only application-level changes (notifiable_type changes from `App\Models\User` to `App\Models\Account`).

### Sprint 2: Models & Relationships

**Purpose:** Implement Eloquent models and define relationships for the new identity tables.

**Scope:** 6 new models, updated relationships on existing models.

**Exit criteria:**
- All relationships return correct data in tinker
- Eager loading does not produce N+1
- Account model is authenticatable (implements Authenticatable, MustVerifyEmail)
- TenantMembership has correct FK relationships

#### New Models

##### `app/Models/Account.php`

```php
class Account extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, SoftDeletes, Notifiable;
    use HasNotifications;  // For notification preferences

    protected $fillable = [
        'email', 'password', 'email_verified_at',
        'remember_token', 'profile_image', 'status',
        'notification_preferences', 'last_login_at', 'last_login_ip',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'notification_preferences' => 'array',
        'last_login_at' => 'datetime',
    ];

    protected $hidden = ['password', 'remember_token'];

    public function memberships(): HasMany
    public function currentMembership(): BelongsTo  // via session
    public function socialAccounts(): HasMany
    public function sessions(): HasMany

    // Notification preferences helper
    public function wantsNotification(string $type): bool
    public function markLogin(string $ip): void
}
```

**Account should NOT have:**
- `HasRoles` trait (Spatie) — permission checking goes through Gate::before()
- `tenant_id` column
- `is_owner` column
- Direct `orders()` relationship — orders go through Membership

##### `app/Models/TenantMembership.php`

```php
class TenantMembership extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'account_id', 'tenant_id', 'role_id', 'is_owner',
        'status', 'invited_by', 'invited_at', 'joined_at', 'is_default',
    ];

    protected $casts = [
        'is_owner' => 'boolean',
        'is_default' => 'boolean',
        'invited_at' => 'datetime',
        'joined_at' => 'datetime',
    ];

    public function account(): BelongsTo
    public function tenant(): BelongsTo
    public function role(): BelongsTo
    public function customerProfile(): HasOne
    public function staffProfile(): HasOne
    public function merchantProfile(): HasOne

    // Authorization helpers
    public function hasPermission(string $ability): bool
    public function isActive(): bool
    public function isOwner(): bool
}
```

##### `app/Models/CustomerProfile.php`

```php
class CustomerProfile extends Model
{
    use SoftDeletes;

    protected $fillable = ['tenant_membership_id', 'name', 'phone', 'metadata'];
    protected $casts = ['metadata' => 'array'];

    public function membership(): BelongsTo
}
```

##### `app/Models/StaffProfile.php`

```php
class StaffProfile extends Model
{
    use SoftDeletes;

    protected $fillable = ['tenant_membership_id', 'position', 'department'];

    public function membership(): BelongsTo
}
```

##### `app/Models/MerchantProfile.php`

```php
class MerchantProfile extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_membership_id', 'business_name',
        'tax_id', 'business_address', 'metadata',
    ];
    protected $casts = ['business_address' => 'array', 'metadata' => 'array'];

    public function membership(): BelongsTo
}
```

##### `app/Models/SocialAccount.php`

```php
class SocialAccount extends Model
{
    protected $fillable = [
        'account_id', 'provider', 'provider_id', 'provider_email',
        'avatar_url', 'token', 'refresh_token', 'expires_at',
    ];
    protected $hidden = ['token', 'refresh_token'];

    public function account(): BelongsTo
}
```

#### Updated Existing Models

##### `app/Models/Tenant.php`

Add relationship:
```php
public function memberships(): HasMany
public function activeMemberships(): HasMany  // ->where('status', 'active')
public function ownerMembership(): HasOne     // ->where('is_owner', true)
public function adminMemberships(): HasMany   // ->whereHas('role', fn q => where name = admin)
```

Update `notifyAdmins()` to iterate memberships instead of users.

##### `app/Models/User.php`

**No changes in Sprint 2.** The User model continues reading from the `users` table. It is updated in Sprint 4 to become a backward-compatible wrapper around Account.

##### `app/Models/Role.php`

Add relationship:
```php
public function memberships(): HasMany  // tenant_memberships with this role
```

No schema changes to Role. It remains a Spatie model with `tenant_id` nullable.

### Sprint 3: Data Migration

**Purpose:** Backfill the new tables from existing `users` table data.

**Scope:** 8 sequential data migration scripts, validation checkpoints, rollback procedures.

**Exit criteria:**
- Every user record has a corresponding Account record
- Every non-SuperAdmin user has a corresponding TenantMembership
- Every tenant has exactly one owner membership
- SuperAdmin accounts have Account records but NO TenantMembership
- Data integrity verified with validation queries
- Rollback script tested and confirmed

#### Migration Script 1: Users → Accounts

```sql
INSERT INTO accounts (id, email, password, email_verified_at, remember_token,
                      profile_image, status, notification_preferences, created_at, updated_at)
SELECT
    u.id,
    u.email,
    u.password,
    u.email_verified_at,
    u.remember_token,
    u.profile_image,
    COALESCE(u.status, 'active'),
    u.notification_preferences,
    u.created_at,
    u.updated_at
FROM users u
WHERE u.deleted_at IS NULL;
```

**Edge case handling:**
- Duplicate emails: Group by email. Use the most recent User record for Account fields. Create multiple Memberships (one per tenant). Log warning.
- Null status: Default to 'active'.
- Null email (data integrity issue): Assign placeholder email, log error, require manual fix.

#### Migration Script 2: Users → TenantMemberships

```sql
INSERT INTO tenant_memberships (account_id, tenant_id, role_id, is_owner, status,
                                invited_by, invited_at, joined_at, is_default,
                                created_at, updated_at)
SELECT
    u.id,
    u.tenant_id,
    COALESCE(mhr.role_id, (SELECT id FROM roles WHERE name = 'customer' AND tenant_id = u.tenant_id LIMIT 1)),
    COALESCE(u.is_owner, FALSE),
    COALESCE(u.status, 'active'),
    NULL,  -- invited_by (not tracked in legacy data)
    NULL,  -- invited_at
    u.created_at,
    COALESCE(u.is_owner, FALSE),
    u.created_at,
    u.updated_at
FROM users u
LEFT JOIN model_has_roles mhr ON mhr.model_id = u.id
    AND mhr.model_type = 'App\Models\User'
WHERE u.tenant_id IS NOT NULL;
```

**Edge case handling:**
- User has no role in `model_has_roles`: Assign default customer role for that tenant.
- User has multiple roles in `model_has_roles`: Use the first non-customer role (prefer admin).
- User.tenant_id points to deleted tenant: Skip membership creation. Log warning.

#### Migration Script 3: Owner Verification

```sql
-- Find tenants with no owner
SELECT id FROM tenants t
WHERE NOT EXISTS (
    SELECT 1 FROM tenant_memberships tm
    WHERE tm.tenant_id = t.id AND tm.is_owner = TRUE
);
```

For each tenant with no owner: assign the oldest admin membership as owner. If no admin exists, assign the oldest active membership. Log all assignments.

```sql
-- Find tenants with multiple owners (should not happen, but defensive)
SELECT tenant_id, COUNT(*) FROM tenant_memberships
WHERE is_owner = TRUE
GROUP BY tenant_id
HAVING COUNT(*) > 1;
```

For each tenant with multiple owners: keep the earliest `joined_at` as owner, demote others.

#### Migration Script 4: CustomerProfiles

```sql
INSERT INTO customer_profiles (tenant_membership_id, name, phone, created_at, updated_at)
SELECT
    tm.id,
    COALESCE(u.name, 'Customer'),
    NULL,
    u.created_at,
    u.updated_at
FROM tenant_memberships tm
JOIN users u ON u.id = tm.account_id
JOIN roles r ON r.id = tm.role_id
WHERE r.name = 'customer';
```

#### Migration Script 5: StaffProfiles

```sql
INSERT INTO staff_profiles (tenant_membership_id, position, department, created_at, updated_at)
SELECT
    tm.id,
    NULL,
    NULL,
    u.created_at,
    u.updated_at
FROM tenant_memberships tm
JOIN users u ON u.id = tm.account_id
JOIN roles r ON r.id = tm.role_id
WHERE r.name = 'admin' AND tm.is_owner = FALSE;
```

#### Migration Script 6: MerchantProfiles

```sql
INSERT INTO merchant_profiles (tenant_membership_id, business_name, tax_id, created_at, updated_at)
SELECT
    tm.id,
    t.name,
    NULL,
    u.created_at,
    u.updated_at
FROM tenant_memberships tm
JOIN tenants t ON t.id = tm.tenant_id
JOIN users u ON u.id = tm.account_id
WHERE tm.is_owner = TRUE;
```

#### Migration Script 7: Sessions account_id backfill

```sql
UPDATE sessions s
JOIN users u ON u.id = s.user_id
SET s.account_id = u.id
WHERE s.account_id IS NULL;
```

#### Migration Script 8: Notifications notifiable migration

```sql
UPDATE notifications
SET notifiable_type = 'App\Models\Account',
    notifiable_id = (
        SELECT a.id FROM accounts a
        WHERE a.email = (
            SELECT u.email FROM users u WHERE u.id = notifications.notifiable_id
        )
    )
WHERE notifiable_type = 'App\Models\User';
```

**Validation checkpoints after each migration:**

| Checkpoint | Query | Expected |
|---|---|---|
| Account count matches user count | `SELECT COUNT(*) FROM accounts` vs `SELECT COUNT(*) FROM users WHERE deleted_at IS NULL` | Equal (or accounts >= users if duplicates merged) |
| Membership count | `SELECT COUNT(*) FROM tenant_memberships` | Equal to count of users with tenant_id IS NOT NULL |
| Owner verification | `SELECT COUNT(*) FROM tenants WHERE id NOT IN (SELECT tenant_id FROM tenant_memberships WHERE is_owner = TRUE)` | 0 |
| No orphan memberships | `SELECT COUNT(*) FROM tenant_memberships WHERE tenant_id NOT IN (SELECT id FROM tenants)` | 0 |
| CustomerProfile count | `SELECT COUNT(*) FROM customer_profiles` | Equal to count of customer-role memberships |

**Rollback strategy for Sprint 3:**
```sql
TRUNCATE TABLE merchant_profiles;
TRUNCATE TABLE staff_profiles;
TRUNCATE TABLE customer_profiles;
TRUNCATE TABLE tenant_memberships;
TRUNCATE TABLE accounts;
-- Users table is untouched. No data loss.
```

### Sprint 4: Authentication

**Purpose:** Switch the authentication guard from `users` to `accounts`.

**Scope:** Auth provider change, all auth controllers, session handling, password reset, email verification.

**Exit criteria:**
- Login works with existing credentials
- Registration creates Account + Membership
- Password reset works (account-level)
- Email verification works (account-level)
- SuperAdmin login works
- "Remember me" works
- Existing sessions remain valid
- Feature flag `IDENTITY_USE_ACCOUNTS` can be flipped on/off without data loss
- Dual-write enabled: new registrations write to both `accounts` and `users`

#### Files Modified

##### `config/auth.php`

Change provider model:
```php
'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model' => env('IDENTITY_USE_ACCOUNTS', false)
            ? App\Models\Account::class
            : App\Models\User::class,
    ],
],
```

Add conditional provider logic. During transition, the auth provider switches between `User` (legacy) and `Account` (new) based on the feature flag.

##### `app/Http/Requests/Auth/LoginRequest.php`

Current: `authenticate()` uses `User` model via `Auth::attempt()`.
Changes:
- Check `Account` status BEFORE authentication attempt
- If Account.status is 'suspended' or 'banned', return "Account unavailable" error
- After successful auth, resolve Membership context
- For store-scoped logins, verify Membership exists and is active

New authenticate logic:
```php
public function authenticate(): void
{
    $this->ensureIsRateLimited();

    // Find account first to check status
    $account = Account::where('email', $this->email)->first();

    if ($account && in_array($account->status, ['suspended', 'banned'])) {
        throw ValidationException::withMessages([
            'email' => __('Account unavailable.'),
        ]);
    }

    if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
        RateLimiter::hit($this->throttleKey());
        throw ValidationException::withMessages([
            'email' => trans('auth.failed'),
        ]);
    }

    // After successful auth, resolve membership
    if (config('feature.identity_use_accounts')) {
        // ResolveMembership handled by middleware after auth
    }

    RateLimiter::clear($this->throttleKey());
}
```

##### `app/Http/Controllers/Auth/AuthenticatedSessionController.php`

Changes:
- After `Auth::attempt()` succeeds (Account level), resolve tenant context
- For store-scoped login URLs, use the resolved tenant from middleware
- For platform-level login URL, check membership count → redirect to `/select-tenant` if > 1
- Store `account_id`, `current_tenant_membership_id`, `current_tenant_id` in session

##### `app/Http/Controllers/Auth/RegisteredUserController.php`

Changes:
- Create Account instead of User
- Create TenantMembership with customer role
- Create CustomerProfile
- Dispatch Registered event (for email verification)
- Dual-write: also create User record (legacy) when `IDENTITY_USE_ACCOUNTS` is false

##### `app/Http/Controllers/Auth/PasswordResetLinkController.php`

Changes:
- Look up Account by email instead of User
- Store reset token keyed by `account_id` (new `password_reset_tokens` table)
- Dual-write during transition: write to both old and new password_reset_tokens tables

##### `app/Http/Controllers/Auth/NewPasswordController.php`

Changes:
- Validate token against `account_id` instead of `email`
- Update Account.password instead of User.password
- Revoke all sessions for this Account (except current)

##### `app/Http/Controllers/Auth/VerifyEmailController.php`

Changes:
- Verify against Account model
- Set `Account.email_verified_at` instead of `User.email_verified_at`
- The verification link uses `{id}` which is the Account ID (matches existing pattern since User.id == Account.id)

##### `app/Http/Controllers/Auth/EmailVerificationNotificationController.php`

Changes:
- Send verification to Account email
- Throttle per Account

##### `StorefrontLoginController` (if exists separately)

Changes identical to AuthenticatedSessionController but for storefront-scoped login context.

##### `CreateStoreController` (if exists for merchant registration)

Changes:
- In transaction: Create Tenant → Create Account → Create default roles → Create TenantMembership (owner) → Create CustomerProfile (if needed) → Create MerchantProfile
- Dual-write: also create User record during transition

##### Session handling

Create middleware or helper to resolve current membership:
```php
// After auth, in a shared middleware
$account = Auth::user();
$tenant = app('current.tenant');  // Resolved by IdentifyTenant middleware

if ($tenant && $account) {
    $membership = TenantMembership::where('account_id', $account->id)
        ->where('tenant_id', $tenant->id)
        ->where('status', 'active')
        ->first();

    if ($membership) {
        session([
            'current_tenant_membership_id' => $membership->id,
            'current_tenant_id' => $tenant->id,
        ]);
    }
}
```

##### `app/Http/Middleware/IdentifyTenant.php`

Changes:
- Tenant resolution from URL slug unchanged
- Add membership resolution after auth
- If authenticated user has no membership in the resolved tenant, continue allowing public access (storefront pages) but restrict admin routes

##### `bootstrap/helpers.php`

Update `tenantId()` helper:
```php
function tenantId(): ?int
{
    // New path: check Account + Membership
    if (config('feature.identity_use_accounts')) {
        $account = auth()->user();
        if ($account) {
            $membershipId = session('current_tenant_membership_id');
            if ($membershipId) {
                $membership = TenantMembership::find($membershipId);
                return $membership?->tenant_id;
            }
        }
        $t = tenant();
        return $t ? (int) $t->id : null;
    }

    // Legacy path
    $user = auth()->user();
    if ($user && $user->tenant_id) {
        return (int) $user->tenant_id;
    }
    $t = tenant();
    return $t ? (int) $t->id : null;
}
```

#### Dual-Write Strategy

During Sprint 4, new registrations write to BOTH `accounts` and `users` tables:

```php
// In RegisteredUserController
DB::transaction(function () use ($data) {
    $account = Account::create([...]);

    // Legacy write (during transition only)
    if (! config('feature.identity_use_accounts')) {
        User::create([
            'id' => $account->id,
            'email' => $account->email,
            'password' => $account->password,
            'name' => $data['name'],
            'tenant_id' => $tenant->id,
            'status' => 'active',
        ]);
    }

    $membership = TenantMembership::create([...]);
    CustomerProfile::create([...]);
});
```

**Rollback scenario:** If `IDENTITY_USE_ACCOUNTS` is flipped back to `false`, the dual-write ensures the `users` table has the latest data. No data loss.

### Sprint 5: Authorization

**Purpose:** Update all authorization checks to work through Account → Membership → Role → Permission.

**Scope:** Gate::before(), middleware updates, policy updates, RoleMiddleware.

**Exit criteria:**
- Admin routes accessible with admin role
- Customer routes accessible with customer role
- Permission denied for wrong role (403)
- Owner bypass works (owner can access admin functions regardless of role)
- SuperAdmin bypass works
- Suspended membership returns 403
- No membership in tenant returns 403
- Feature flag `IDENTITY_USE_GATE_BEFORE` can be flipped on/off

#### Files Modified

##### `app/Providers/AuthServiceProvider.php`

Add Gate::before():
```php
Gate::before(function ($user, string $ability) {
    // SuperAdmin bypass
    if ($user instanceof Account && $user->hasRole('superadmin')) {
        return true;
    }

    // Legacy support: if $user is still User model, use existing HasRoles
    if ($user instanceof User) {
        return $user->hasPermissionTo($ability) ?: null;
    }

    // Account-based authorization
    $membership = app('current.membership');

    if (! $membership || $membership->status !== 'active') {
        return false;
    }

    // Owner bypass
    if ($membership->is_owner) {
        return true;
    }

    // Role-based check
    return $membership->role->hasPermissionTo($ability) ?: false;
});
```

##### `config/permission.php`

```php
'register_permission_check_method' => false,  // We use Gate::before()
```

##### `app/Models/Account.php`

**No HasRoles trait.** The `hasRole()` check for SuperAdmin is done differently:
```php
public function hasRole(string $role): bool
{
    // Check through Spatie's model_has_roles for legacy superadmin support
    return DB::table('model_has_roles')
        ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
        ->where('model_has_roles.model_id', $this->id)
        ->where('model_has_roles.model_type', 'App\Models\Account')
        ->where('roles.name', $role)
        ->exists();
}
```

##### `app/Models/User.php`

Remove `HasRoles` trait (or keep during transition with feature flag).

##### Create `ResolveMembership` Middleware

New middleware to resolve current membership and make it available:
```php
class ResolveMembership
{
    public function handle(Request $request, Closure $next)
    {
        if (config('feature.identity_use_gate_before') && Auth::check()) {
            $account = Auth::user();
            $tenant = app('current.tenant');

            if ($tenant) {
                $membership = TenantMembership::where('account_id', $account->id)
                    ->where('tenant_id', $tenant->id)
                    ->whereNull('deleted_at')
                    ->first();

                if ($membership) {
                    app()->instance('current.membership', $membership);
                    session()->put('current_tenant_membership_id', $membership->id);
                }
            }
        }

        return $next($request);
    }
}
```

##### Update `CheckTenantAccess` Middleware

Current: checks `$user->tenant_id` matches the resolved tenant.
New: checks that a Membership exists for (account_id, tenant_id) and is active.

```php
public function handle(Request $request, Closure $next)
{
    if (config('feature.identity_use_gate_before')) {
        $membership = app('current.membership');
        if (! $membership) {
            abort(403, 'You do not have access to this store.');
        }
        if ($membership->status !== 'active') {
            abort(403, 'Your access to this store has been ' . $membership->status . '.');
        }
        return $next($request);
    }

    // Legacy check
    $user = $request->user();
    $tenant = app('current.tenant');
    if ($user && $tenant && $user->tenant_id !== $tenant->id) {
        abort(403);
    }
    return $next($request);
}
```

##### Update `RoleMiddleware`

Current: checks `$user->hasRole()` via Spatie.
New: checks `app('current.membership')->role->name`.

```php
class RoleMiddleware
{
    public function handle($request, Closure $next, $role)
    {
        if (config('feature.identity_use_gate_before')) {
            $membership = app('current.membership');
            if (! $membership || $membership->role->name !== $role) {
                abort(403);
            }
            return $next($request);
        }

        // Legacy check
        if (! $request->user()->hasRole($role)) {
            abort(403);
        }
        return $next($request);
    }
}
```

##### Update `ValidateTenantBinding` Middleware

No changes needed — this middleware already checks that route-model-bound entities belong to the current tenant. It works with any auth model.

##### Update Permission and Role Controllers

`PermissionController` and `RoleController` changes:
- Update to handle tenant-scoped role management
- Role CRUD remains scoped to tenant (no change needed)
- Permission assignment to roles remains unchanged (Spatie tables)
- Add validation that role belongs to the correct tenant

#### Policy Updates

Audit all existing policies:

| Policy | Current | Change |
|---|---|---|
| `BillingPaymentMethodPolicy` | Unregistered | Register in AuthServiceProvider. Update to use Account/membership. |
| `CustomerAddressPolicy` | Uses `$user` | Update to use Account + membership context. |
| `OrderPolicy` | Uses `$user` | Update to use Account + membership context. |
| `ProductPolicy` (if any) | Minimal | Likely no change — Gate::before() handles. |
| `PaymentIntentPolicy` | Uses `$user` | Update to use Account + membership context. |

For policies that use `$user->can()`:
- After Gate::before() is deployed, `$user->can()` works automatically
- No policy code changes needed if Gate::before() is correctly implemented
- But update policy method signatures to accept `$account` instead of `$user` for clarity

#### Frontend Impact (Sprint 5)

##### `app/Http/Middleware/HandleInertiaRequests.php`

Update shared data to include Account + currentMembership:
```php
if (config('feature.identity_use_gate_before')) {
    $account = Auth::user();
    $membership = app('current.membership');

    return array_merge(parent::share($request), [
        'auth' => [
            'user' => $account ? [
                'id' => $account->id,
                'email' => $account->email,
                'name' => $membership?->customerProfile?->name ?? 'User',
                'profile_image' => $account->profile_image,
                'status' => $account->status,
                // Backward-compatible keys
                'tenant_id' => $membership?->tenant_id,
                'is_owner' => $membership?->is_owner ?? false,
            ] : null,
            'current_membership' => $membership,
        ],
    ]);
}
```

**Critical:** The frontend expects `auth.user` with keys like `name`, `email`, `tenant_id`, `is_owner`. These must be preserved through the backward-compatible array above. Missing keys cause JavaScript errors.

### Sprint 6: Registration & Invitation Flows

**Purpose:** Implement invitation flow, tenant switcher, refine registration flows.

**Scope:** Staff invitation, invitation acceptance, tenant switching, ownership transfer.

**Exit criteria:**
- Invitation sent → notification received
- Invitation accepted → membership becomes active
- Expired invitation → appropriate error
- Tenant switcher shows correct memberships
- Store switching works (no re-authentication)
- Ownership transfer works end-to-end

#### New Controllers

##### Create `InvitationController`

```php
class InvitationController extends Controller
{
    // Owner invites staff
    public function invite(Request $request, Tenant $tenant): void
    {
        // Authorize: current user is owner or has 'users.invite' permission
        // Find or create Account by email
        // Create TenantMembership with status='invited'
        // Send notification with signed URL
    }

    // Accept invitation via signed URL
    public function accept(Request $request, Tenant $tenant, TenantMembership $membership): RedirectResponse
    {
        // Validate signed URL
        // Verify authenticated Account matches membership
        // Update membership: status='active', joined_at=now
        // Set as current membership in session
        // Redirect to store dashboard
    }
}
```

##### Create `TenantSwitchController`

```php
class TenantSwitchController extends Controller
{
    // List all active memberships
    public function index(): InertiaResponse
    {
        $memberships = Auth::user()->memberships()
            ->with('tenant')
            ->where('status', 'active')
            ->get();
        return Inertia::render('SelectTenant', ['memberships' => $memberships]);
    }

    // Switch to a specific tenant
    public function switch(Request $request): RedirectResponse
    {
        $membership = TenantMembership::where('id', $request->membership_id)
            ->where('account_id', Auth::id())
            ->where('status', 'active')
            ->firstOrFail();

        session([
            'current_tenant_membership_id' => $membership->id,
            'current_tenant_id' => $membership->tenant_id,
        ]);

        return redirect()->route('storefront.dashboard', ['store_slug' => $membership->tenant->slug]);
    }
}
```

##### Create `OwnershipTransferController`

```php
class OwnershipTransferController extends Controller
{
    public function initiate(Request $request, Tenant $tenant): void
    {
        // Validate: current membership is owner
        // Validate: target email exists and has active membership
        // Log audit event (initiation)
        // Send confirmation to current owner
    }

    public function confirm(Request $request, Tenant $tenant): RedirectResponse
    {
        // Begin transaction
        // Current membership: is_owner = false
        // Target membership: is_owner = true
        // Log audit event (completion)
        // Notify both parties
        // Redirect
    }
}
```

#### Frontend Components

##### `SelectTenant.jsx`

New Inertia page listing all active memberships with tenant name, logo, role. User clicks one to switch.

##### Invitation components

- `InviteStaffModal.jsx` — Form to enter email, select role
- `InvitationAccepted.jsx` — Success page after accepting invitation

### Sprint 7: Notifications & Billing Migration

**Purpose:** Update notification system to use Account model. Migrate billing module to use Account/membership context.

**Scope:** Notification channels, Tenant::notifyAdmins(), billing services, payment services.

**Exit criteria:**
- Admin notifications sent through account membership list
- Customer notifications sent directly to Account
- Notification preferences respected
- Billing checks use Account/membership context
- Payment flows use Account instead of User

#### Notification Changes

##### `app/Models/Tenant.php`

Update `notifyAdmins()`:
```php
public function notifyAdmins($notification): void
{
    if (config('feature.identity_migrate_notifications')) {
        $this->memberships()
            ->whereHas('role', fn($q) => $q->where('name', 'admin'))
            ->where('status', 'active')
            ->get()
            ->each(fn($m) => $m->account->notify($notification));
    } else {
        // Legacy: notify users with admin role
        // (existing implementation)
    }
}
```

##### Notification Controller

Update to filter by Account:
```php
if (config('feature.identity_migrate_notifications')) {
    $notifications = Auth::user()->notifications()
        ->where('data->tenant_id', $currentTenantId)
        ->paginate();
} else {
    // Legacy notification fetch
}
```

##### Notification Preferences

The `accounts.notification_preferences` JSON column stores global notification preferences. Implement helper on Account:
```php
public function wantsNotification(string $type): bool
{
    return $this->notification_preferences[$type] ?? true;
}
```

#### Billing Changes

##### `FeatureGate` Service

Update feature checking to use Account/membership:
```php
if (config('feature.identity_migrate_billing')) {
    $membership = app('current.membership');
    if (! $membership) return false;
    $plan = $membership->tenant->subscription?->plan;
    // Check feature against plan
}
```

##### `SubscriptionLifecycleService`

Update to resolve Account context through membership:
```php
// Instead of $user->tenant->subscription
$membership = app('current.membership');
$subscription = $membership?->tenant->subscription;
```

##### `BillingNotificationService`

Update to send notifications to Account instead of User.

#### Payment Changes

##### Payment Intent / Transaction Services

Update `user_id` references to `account_id`:
```php
if (config('feature.identity_migrate_payments')) {
    $paymentIntent = PaymentIntent::create([
        'account_id' => Auth::id(),
        // ...other fields
    ]);
}
```

### Sprint 8: Testing & QA

**Purpose:** Full regression testing, performance testing, security audit, cleanup verification.

**Scope:** Complete test suite execution, manual QA, deployment verification.

**Exit criteria:**
- All 25 identity test scenarios from the v2 document pass
- Full regression test suite passes (auth, billing, orders, products, customers)
- Performance tests pass (< 5ms per auth/permission query at 100K tenants)
- Security audit passes (no cross-tenant leaks, no auth bypass)
- Rollback plan verified

#### Test Suites

##### Unit Tests

- Account model tests (creation, validation, soft delete, status transitions)
- TenantMembership model tests (unique constraint, status transitions, owner constraint)
- Profile model tests (customer, staff, merchant)
- Gate::before() unit tests (owner bypass, SuperAdmin bypass, role check, suspended membership)
- Feature flags tests (flag on/off behavior)

##### Feature Tests

- Authentication tests (login, logout, registration, password reset, email verification)
- Authorization tests (admin access, customer access, owner bypass, SuperAdmin bypass)
- Invitation tests (send, accept, expire, wrong account)
- Tenant switching tests (multiple memberships, suspended membership)
- Ownership transfer tests (initiate, confirm, validate)

##### Integration Tests

- Full merchant registration flow (create store → create account → create membership → login)
- Full customer registration flow (register → create membership → login → place order)
- Cross-tenant isolation tests (Account A cannot access Tenant B data)
- Dual-write verification tests (both tables written correctly)
- Feature flag toggle tests (flip on/off, verify behavior)

##### Regression Tests

- All existing auth feature tests pass with `IDENTITY_USE_ACCOUNTS = true`
- All existing auth feature tests pass with `IDENTITY_USE_ACCOUNTS = false`
- Billing test suite passes with `IDENTITY_MIGRATE_BILLING = true`
- Payment test suite passes with `IDENTITY_MIGRATE_PAYMENTS = true`
- Order test suite passes
- Product test suite passes
- Storefront test suite passes

##### Security Tests

- Cross-tenant data access attempts
- Direct ID manipulation in URLs
- Session hijacking attempts
- Permission escalation attempts
- Invitation token replay

---

## 5. File Impact Analysis

### 5.1 App/Models (6 new, 3 modified)

| File | Change | Why | Risk |
|---|---|---|---|
| `app/Models/Account.php` | **NEW** | Root identity model | Low |
| `app/Models/TenantMembership.php` | **NEW** | Links Account to Tenant | Low |
| `app/Models/CustomerProfile.php` | **NEW** | Customer-specific profile data | Low |
| `app/Models/StaffProfile.php` | **NEW** | Staff-specific profile data | Low |
| `app/Models/MerchantProfile.php` | **NEW** | Merchant business data | Low |
| `app/Models/SocialAccount.php` | **NEW** | OAuth provider linking | Low |
| `app/Models/Tenant.php` | **MODIFIED** | Add memberships() relationship, update notifyAdmins() | Medium |
| `app/Models/User.php` | **MODIFIED** | Sprint 4: becomes Account wrapper. Sprint 5: remove HasRoles. | High |
| `app/Models/Role.php` | **MODIFIED** | Add memberships() relationship | Low |

### 5.2 App/Services (10 modified, 1 new)

| Service | Change | Why | Risk |
|---|---|---|---|
| `TenantBootstrapService.php` | **MAJOR** | Create Account + TenantMembership instead of User. Create profile tables. | High |
| `FeatureGate.php` | **MAJOR** | Feature checking must use Account/membership context, not User.tenant_id | High |
| `SubscriptionLifecycleService.php` | **MINOR** | Account context through membership | Medium |
| `SubscriptionExpiryService.php` | **MINOR** | Same as above | Medium |
| `SubscriptionLimitService.php` | **MINOR** | Same as above | Medium |
| `BillingNotificationService.php` | **MINOR** | Send to Account instead of User | Low |
| `OrderNotificationService.php` | **MINOR** | Same as above | Low |
| `PaymentIntentService.php` | **MINOR** | Use account_id instead of user_id | Medium |
| `PaymentTransactionService.php` | **MINOR** | Same | Medium |
| `PaymentEvidenceService.php` | **MINOR** | Same | Low |
| `PaymentReviewService.php` | **MINOR** | Same | Low |
| `ImageUploadService.php` | **MINOR** | uploaded_by references Account | Low |
| `AuthenticationService` (if exists) | **MAJOR** | Auth guard change | High |
| **New: `InvitationService`** | **NEW** | Handle invitation creation, acceptance, expiry | Low |
| **New: `OwnershipTransferService`** | **NEW** | Validate and execute ownership transfers | Low |

### 5.3 App/Http/Controllers (11 modified, 3 new)

| Controller | Change | Why | Risk |
|---|---|---|---|
| `Auth/AuthenticatedSessionController.php` | **MAJOR** | Account auth. Membership resolution. Session changes. | High |
| `Auth/RegisteredUserController.php` | **MAJOR** | Create Account + Membership + CustomerProfile. Dual-write. | High |
| `Auth/PasswordResetLinkController.php` | **MAJOR** | Account-level password reset. New table. | Medium |
| `Auth/NewPasswordController.php` | **MAJOR** | Account-level password reset. | Medium |
| `Auth/VerifyEmailController.php` | **MAJOR** | Account-level verification. | Medium |
| `Auth/EmailVerificationNotificationController.php` | **MINOR** | Send to Account. | Low |
| `Auth/ConfirmablePasswordController.php` | **MINOR** | Account-level confirmation. | Low |
| `Auth/PasswordController.php` | **MINOR** | Account-level password update. | Low |
| `StorefrontLoginController.php` (if separate) | **MAJOR** | Same as AuthenticatedSessionController. | High |
| `CreateStoreController.php` (merchant registration) | **MAJOR** | Create Account + Tenant + Membership + profiles. | High |
| `OrderController.php` | **MINOR** | Add tenant_membership_id to order creation. | Medium |
| `StorefrontCheckoutController.php` | **MINOR** | Same. | Medium |
| `ClientOrderController.php` | **MINOR** | Same. | Medium |
| **New: `InvitationController.php`** | **NEW** | Invite + accept flows. | Low |
| **New: `TenantSwitchController.php`** | **NEW** | Tenant switching. | Low |
| **New: `OwnershipTransferController.php`** | **NEW** | Ownership transfer. | Low |

### 5.4 App/Http/Middleware (5 modified, 1 new)

| Middleware | Change | Why | Risk |
|---|---|---|---|
| `IdentifyTenant.php` | **MODIFIED** | Add membership resolution after tenant resolution. | High |
| `CheckTenantAccess.php` | **MODIFIED** | Check Membership existence, not user.tenant_id. | High |
| `RoleMiddleware.php` | **MODIFIED** | Check membership.role.name instead of user.hasRole(). | High |
| `ValidateTenantBinding.php` | **NO CHANGE** | Already works correctly. | None |
| `HandleInertiaRequests.php` | **MODIFIED** | Share Account + currentMembership. Backward-compatible keys. | High |
| **New: `ResolveMembership.php`** | **NEW** | Resolve and bind current membership to app container. | Medium |

### 5.5 App/Providers (2 modified)

| Provider | Change | Why | Risk |
|---|---|---|---|
| `AuthServiceProvider.php` | **MODIFIED** | Add Gate::before(). Register new policies. | High |
| `AppServiceProvider.php` | **MINOR** | Register new service bindings if needed. | Low |

### 5.6 App/Policies (6 modified, 1 new)

| Policy | Change | Why | Risk |
|---|---|---|---|
| `BillingPaymentMethodPolicy.php` | **MODIFIED** | Register it. Update to Account model. | Medium |
| `CustomerAddressPolicy.php` | **MINOR** | Update to Account/membership context. | Low |
| `OrderPolicy.php` | **MINOR** | Same. | Low |
| `PaymentIntentPolicy.php` | **MINOR** | Same. | Low |
| `ProductPolicy.php` (if exists) | **MINOR** | Same. | Low |
| `StorePolicy.php` (if exists) | **MINOR** | Same. | Low |
| **New: `TenantMembershipPolicy.php`** | **NEW** | For invitation and membership management. | Low |

### 5.7 App/Http/Requests (5 modified)

| Request | Change | Why | Risk |
|---|---|---|---|
| `Auth/LoginRequest.php` | **MAJOR** | Check Account.status. Membership resolution. | High |
| `Auth/RegisterRequest.php` (if exists) | **MAJOR** | Account-level validation. | Medium |
| `StoreLoginRequest.php` (if exists) | **MAJOR** | Same as LoginRequest. | High |
| `StoreRegisterRequest.php` (if exists) | **MAJOR** | Find or create Account. | Medium |
| `CreateStoreRequest.php` (if exists) | **MAJOR** | Account + Tenant validation. | Medium |

### 5.8 Config (3 modified)

| File | Change | Why | Risk |
|---|---|---|---|
| `config/auth.php` | **MODIFIED** | Conditional provider model (User vs Account). | High |
| `config/permission.php` | **MODIFIED** | `register_permission_check_method = false`. | High |
| `config/feature.php` (new or existing) | **MODIFIED** | Add identity feature flags. | Low |

### 5.9 Routes (2 modified)

| File | Change | Why | Risk |
|---|---|---|---|
| `routes/auth.php` | **MODIFIED** | No structural changes. Possibly add invitation routes. | Low |
| `routes/web.php` | **MINOR** | New routes for tenant switching, invitations. | Low |

### 5.10 Frontend (resources/js) — 8+ components modified

| Component | Change | Why | Risk |
|---|---|---|---|
| `HandleInertiaRequests.php` (shared data) | **MODIFIED** | `auth.user` structure changes. Must preserve backward-compatible keys. | High |
| `Auth/Login.jsx` | **MINOR** | May need to handle new error messages. | Low |
| `Auth/Register.jsx` | **MINOR** | Same. | Low |
| `Auth/ForgotPassword.jsx` | **MINOR** | Same. | Low |
| `Auth/ResetPassword.jsx` | **MINOR** | Same. | Low |
| All admin pages using `auth.user` | **MAJOR** | Must work with both old and new `auth.user` structure during transition. | High |
| All storefront pages using `auth.user` | **MAJOR** | Same. | High |
| **New: `SelectTenant.jsx`** | **NEW** | Tenant switcher page. | Low |
| **New: `InviteStaff.jsx` / `AcceptInvitation.jsx`** | **NEW** | Invitation flows. | Low |

### 5.11 Console/Kernel

| File | Change | Why | Risk |
|---|---|---|---|
| `app/Console/Kernel.php` | **MINOR** | May add maintenance command for data migration. | Low |

### 5.12 Database/Migrations (9 new)

| File | Change |
|---|---|
| `xxxx_xx_xx_xxxxxx_create_accounts_table.php` | **NEW** |
| `xxxx_xx_xx_xxxxxx_create_password_reset_tokens_new_table.php` | **NEW** |
| `xxxx_xx_xx_xxxxxx_create_tenant_memberships_table.php` | **NEW** |
| `xxxx_xx_xx_xxxxxx_create_customer_profiles_table.php` | **NEW** |
| `xxxx_xx_xx_xxxxxx_create_staff_profiles_table.php` | **NEW** |
| `xxxx_xx_xx_xxxxxx_create_merchant_profiles_table.php` | **NEW** |
| `xxxx_xx_xx_xxxxxx_create_social_accounts_table.php` | **NEW** |
| `xxxx_xx_xx_xxxxxx_add_account_id_to_sessions_table.php` | **NEW** |
| `xxxx_xx_xx_xxxxxx_add_foreign_keys_to_identity_tables.php` | **NEW** |

### 5.13 Tests (new and modified)

| File | Change |
|---|---|
| `tests/Feature/Auth/AuthenticationTest.php` | **MODIFIED** — Account-level tests |
| `tests/Feature/Auth/PasswordResetTest.php` | **MODIFIED** — Account-level broker |
| `tests/Feature/Auth/EmailVerificationTest.php` | **MODIFIED** — Account-level verification |
| `tests/Feature/Auth/RegistrationTest.php` | **MODIFIED** — Account + Membership creation |
| **New: `tests/Unit/AccountTest.php`** | **NEW** |
| **New: `tests/Unit/TenantMembershipTest.php`** | **NEW** |
| **New: `tests/Feature/InvitationTest.php`** | **NEW** |
| **New: `tests/Feature/TenantSwitchTest.php`** | **NEW** |
| **New: `tests/Feature/OwnershipTransferTest.php`** | **NEW** |
| **New: `tests/Feature/GateBeforeTest.php`** | **NEW** |
| **New: `tests/Feature/CrossTenantIsolationTest.php`** | **NEW** |

---

## 6. Service Layer Impact

### 6.1 Services with NO Change

| Service | Reason |
|---|---|
| `ImageService.php` | No identity dependency |
| `CurrencyService.php` | No identity dependency |
| `ImageOptimizationService.php` | No identity dependency |
| `StorageLimitService.php` | Only uses User for ID reference |
| `WebsiteInfoService.php` (if exists) | No identity dependency |
| `ProductService.php` (if exists) | Products are tenant-scoped, not identity-scoped |

### 6.2 Services with MINOR Change

| Service | Change | Risk |
|---|---|---|
| `ImageUploadService.php` | Change `uploaded_by` from User ID to Account ID | Low |
| `SubscriptionLifecycleService.php` | Resolve account context through membership | Medium |
| `SubscriptionExpiryService.php` | Resolve tenant through membership | Medium |
| `SubscriptionLimitService.php` | Resolve limit check through membership | Medium |
| `BillingNotificationService.php` | Send to Account instead of User | Low |
| `OrderNotificationService.php` | Send to Account instead of User | Low |
| `OrderWorkflow.php` (if exists) | Use membership context for order assignment | Low |
| `SubscriptionAuditService.php` | Log against Account ID | Low |
| `TenantDeletionService.php` | Verify owner through membership, not user.tenant_id | Medium |

### 6.3 Services with MAJOR Change

| Service | Change | Risk |
|---|---|---|
| `TenantBootstrapService.php` | Create Account + TenantMembership + profiles instead of User. Create owner membership, assign role. Create profiles. | **High** |
| `FeatureGate.php` | Feature checking must switch from `$user->tenant->subscription` to `$account->currentMembership->tenant->subscription`. This affects ALL subscription-gated features. | **High** |

### 6.4 Services with MAJOR Change (No architectural changes — only reference updates)

| Service | Change | Risk |
|---|---|---|
| `PaymentIntentService.php` | Reference Account instead of User | Medium |
| `PaymentTransactionService.php` | Reference Account instead of User | Medium |
| `PaymentEvidenceService.php` | Reference Account instead of User | Low |
| `PaymentReviewService.php` | Reference Account instead of User | Low |

### 6.5 NEW Services Required

| Service | Purpose | When |
|---|---|---|
| `InvitationService` | Handle invitation CRUD, acceptance validation, expiry checks | Sprint 6 |
| `OwnershipTransferService` | Validate and execute ownership transfers with audit logging | Sprint 6 |
| `MembershipResolutionService` | Resolve current membership from session/request context | Sprint 4-5 |

---

## 7. Authentication Plan

### 7.1 Implementation Order

```
Phase 1: Database Foundation (tables exist, no code changes)
Phase 2: Models & Relationships (Account model is authenticatable)
Phase 3: Data Migration (accounts/memberships populated with data)
Phase 4: Authentication Switch (feature-flagged)
  ├── 4a: Auth guard config
  ├── 4b: LoginRequest updates
  ├── 4c: AuthenticatedSessionController updates
  ├── 4d: Storefront login updates
  ├── 4e: Registration updates
  ├── 4f: Password reset updates
  ├── 4g: Email verification updates
  └── 4h: Dual-write enablement
```

### 7.2 Account Login

The `Auth::attempt()` flow:
```
1. Account lookup by email (accounts table, unique)
2. Account.status check (active/suspended/banned)
3. Password verification (bcrypt)
4. Session creation (stores account_id, current_tenant_membership_id)
5. Membership resolution (via URL context or session)
6. Login audit logging
7. Redirect
```

**Files affected:** `config/auth.php`, `app/Http/Requests/Auth/LoginRequest.php`, `app/Http/Controllers/Auth/AuthenticatedSessionController.php`

### 7.3 Merchant Login

Store-scoped admin login through `/store/{slug}/admin/login`:
```
1. IdentifyTenant resolves tenant from slug
2. Account authentication (same as above)
3. Membership lookup for (account_id, tenant_id)
4. Membership status check (active/invited/suspended/removed)
5. SuperAdmin check (bypass membership requirement)
6. Session set with membership context
7. Redirect to admin dashboard
```

**Files affected:** `StorefrontLoginController` (if separate), `IdentifyTenant` middleware

### 7.4 Customer Login

Store-scoped customer login through `/store/{slug}/login`:
```
1. IdentifyTenant resolves tenant from slug
2. Account authentication
3. Membership lookup with customer role
4. If no membership → redirect to registration
5. Session set with membership context
6. Redirect to storefront
```

**Files affected:** `StorefrontLoginController` (if separate)

### 7.5 Staff Login

Same as merchant login. Staff members have admin-role memberships. Login flow is identical.

### 7.6 Password Reset

Account-level password reset:
```
1. Email submitted → Account lookup
2. Generate token → store in password_reset_tokens (keyed by account_id)
3. Send reset link with email parameter
4. User clicks link → validates token + account_id
5. Update Account.password
6. Revoke all sessions (except current)
7. Redirect to login
```

**Key difference from current:** Token is keyed by `account_id`, not `email`. The `password_reset_tokens` table has a new version alongside the old one during transition.

**Files affected:** `app/Http/Controllers/Auth/PasswordResetLinkController.php`, `app/Http/Controllers/Auth/NewPasswordController.php`

### 7.7 Email Verification

Account-level verification:
```
1. Account registered → Registered event dispatched
2. Verification notification sent (MustVerifyEmail)
3. User clicks /verify-email/{id}/{hash}
4. Account.email_verified_at = now
5. Redirect to post-verification page
```

**Key difference from current:** The `id` parameter is the Account ID (matches User ID during migration due to 1:1 mapping). The `MustVerifyEmail` interface is implemented on Account.

**Files affected:** `app/Models/Account.php` (implements MustVerifyEmail), `app/Http/Controllers/Auth/VerifyEmailController.php`, `app/Http/Controllers/Auth/EmailVerificationNotificationController.php`

### 7.8 Remember Me

The "remember me" token is stored on the Account model's `remember_token` column. The cookie-based session restoration checks Account status on each request. No change in mechanism — only the model changes from User to Account.

### 7.9 OAuth Ready

The `social_accounts` table is created in Sprint 1 as an empty schema. No OAuth implementation in Phase 1-8. The table is ready for future OAuth integration.

**Verification:** Schema exists, model exists, relationships defined. No functional changes.

### 7.10 API Ready

Sanctum tokens are issued to the Account model. No API changes in Phase 1-8. The infrastructure is ready for future API token authentication.

---

## 8. Authorization Plan

### 8.1 Membership Resolution

Every authenticated request resolves the current membership:

```
Request → IdentifyTenant (resolves tenant from URL)
       → Authenticate (Auth::check())
       → ResolveMembership (NEW middleware)
            → Query: tenant_memberships WHERE account_id=? AND tenant_id=?
            → If not found: 403 (for admin routes) or public access (for storefront)
            → If found: bind to app('current.membership'), store in session
       → CheckMembershipStatus (existing or updated CheckTenantAccess)
            → status === 'active': continue
            → status === 'invited': redirect to accept
            → status === 'suspended': 403 "Access suspended"
            → status === 'removed': 403 "No longer has access"
       → CheckRoleOrPermission (middleware or Gate::authorize())
```

**New middleware:** `ResolveMembership` — runs after `IdentifyTenant` and `Authenticate`.

### 8.2 Role Resolution

The role is resolved directly from `tenant_memberships.role_id`:
```php
$membership = app('current.membership');
$roleName = $membership->role->name;  // 'admin', 'customer', etc.
$permissions = $membership->role->permissions;  // Spatie collection
```

**No HasRoles trait on Account or Membership.** Spatie's role-permission tables (`roles`, `permissions`, `role_has_permissions`) are used as reference data only. The `model_has_roles` table is NOT used for Account or Membership.

### 8.3 Permission Resolution

Permission checking through Gate::before():

```
Gate::before($account, $ability)
    ├── Is SuperAdmin? → return true
    ├── Is current membership resolved?
    │   └── No → return false
    ├── Is membership active?
    │   └── No → return false
    ├── Is membership.is_owner?
    │   └── Yes → return true (owner bypass)
    └── Check: membership.role.hasPermissionTo($ability)
        ├── Yes → return true
        └── No → return false
```

### 8.4 Tenant Resolution

Tenant is resolved by `IdentifyTenant` middleware from:
1. URL slug: `/store/{slug}/admin/...`
2. Subdomain: `{slug}.example.com`
3. Session: `current_tenant_membership_id` (fallback)

No change to tenant resolution mechanism. Only the membership check after resolution changes.

### 8.5 Owner Validation

```php
// Check if current membership is owner
$isOwner = app('current.membership')?->is_owner ?? false;

// Gate::before() bypass
if ($membership->is_owner) {
    return true;  // All permissions granted
}

// Owner-only actions (billing, staff management)
if (! $isOwner) {
    abort(403, 'Only the store owner can perform this action.');
}
```

### 8.6 Gate::before() Implementation

See Sprint 5 section for full implementation. Key points:
- Owner bypass returns `true` for ALL abilities
- SuperAdmin bypass returns `true` for ALL abilities
- Active membership check is required
- Role-based check uses Spatie's `role->hasPermissionTo()`
- No `model_has_roles` involvement
- Feature-flagged: `IDENTITY_USE_GATE_BEFORE`

### 8.7 Policy Updates

Existing policies that use `$user->can()` will continue working after Gate::before() is deployed because `$user->can()` calls the same Gate::before() logic.

Policies that explicitly check `$user->hasRole()` or `$user->hasPermissionTo()` via Spatie need updates:
```php
// Before
public function view(User $user, Order $order): bool
{
    return $user->hasPermissionTo('orders.view');
}

// After (works with Gate::before())
public function view(Account $account, Order $order): bool
{
    return $account->can('orders.view');
}
```

**In most cases, no policy changes are needed.** The `$user->can()` method uses Gate::before() after the switch. The method signature change (User → Account) is a typehint update only.

---

## 9. Data Migration Plan

### 9.1 Migration Batches

| Batch | Source | Target | Records | Estimated Time |
|---|---|---|---|---|
| Batch 1 | `users` → `accounts` | 1:1 mapping (group by email) | ~200-1000 | < 1 second |
| Batch 2 | `users` + `model_has_roles` → `tenant_memberships` | Per-user membership | ~200-2000 | < 2 seconds |
| Batch 3 | Owner verification | Fix missing/multiple owners | ~0-10 | < 1 second |
| Batch 4 | `tenant_memberships` + `users` → `customer_profiles` | Customer profiles | ~100-500 | < 1 second |
| Batch 5 | `tenant_memberships` + `users` → `staff_profiles` | Staff profiles | ~50-200 | < 1 second |
| Batch 6 | `tenant_memberships` + `tenants` → `merchant_profiles` | Merchant profiles | ~10-50 | < 1 second |
| Batch 7 | `sessions` → backfill account_id | Session mapping | ~100-500 | < 1 second |
| Batch 8 | `notifications` → update notifiable_type | Notification mapping | ~1000-10000 | < 5 seconds |

### 9.2 Rollback Strategy

Each batch is independently rollbackable:

```sql
-- Batch 1 rollback
TRUNCATE TABLE accounts;

-- Batch 2 rollback
TRUNCATE TABLE tenant_memberships;

-- Batch 3 rollback (revert owner changes)
UPDATE tenant_memberships SET is_owner = FALSE WHERE is_owner = TRUE;
-- Then restore from backup or re-run with original logic

-- Batch 4-6 rollback
TRUNCATE TABLE customer_profiles;
TRUNCATE TABLE staff_profiles;
TRUNCATE TABLE merchant_profiles;

-- Batch 7 rollback
UPDATE sessions SET account_id = NULL WHERE account_id IS NOT NULL;

-- Batch 8 rollback
UPDATE notifications
SET notifiable_type = 'App\Models\User',
    notifiable_id = (SELECT u.id FROM users u WHERE u.email = (
        SELECT a.email FROM accounts a WHERE a.id = notifications.notifiable_id
    ))
WHERE notifiable_type = 'App\Models\Account';
```

### 9.3 Validation Checkpoints

After each batch, run validation queries:

| Checkpoint | Query | Fail Action |
|---|---|---|
| Account count | `SELECT COUNT(*) FROM accounts` vs `SELECT COUNT(*) FROM users WHERE deleted_at IS NULL` | Stop. Investigate missing records. |
| Membership count | `SELECT COUNT(*) FROM tenant_memberships` vs `SELECT COUNT(*) FROM users WHERE tenant_id IS NOT NULL` | Stop. Missing memberships. |
| Owner count | `SELECT COUNT(*) FROM tenants WHERE id NOT IN (SELECT tenant_id FROM tenant_memberships WHERE is_owner = TRUE)` | Stop. Ownerless tenants. |
| Duplicate owners | `SELECT tenant_id FROM tenant_memberships WHERE is_owner = TRUE GROUP BY tenant_id HAVING COUNT(*) > 1` | Fix duplicates before proceeding. |
| Orphan memberships | `SELECT COUNT(*) FROM tenant_memberships WHERE tenant_id NOT IN (SELECT id FROM tenants)` | Stop. Data integrity issue. |
| Session mapping | `SELECT COUNT(*) FROM sessions WHERE user_id IS NOT NULL AND account_id IS NULL` | Warning. Incomplete session backfill. |

### 9.4 Failure Recovery

If a migration batch fails mid-execution:

1. **Stop immediately.** Do not proceed to the next batch.
2. **Rollback the failed batch only.** Remaining batches are unaffected.
3. **Fix the data issue** (e.g., duplicate email, missing FK reference).
4. **Re-run the failed batch.** Migration scripts are idempotent (use `INSERT IGNORE` or `ON DUPLICATE KEY UPDATE`).

**Automatic recovery not recommended.** Each failure should be manually reviewed. The migration runs on a development/staging copy first, so failures are caught before production.

---

## 10. Backward Compatibility Plan

### 10.1 User Model Wrapper (Sprint 4-8)

The `App\Models\User` model is converted to a backward-compatible wrapper around Account during Sprint 4:

```php
class User extends Authenticatable
{
    // During transition: User reads from accounts table
    protected $table = 'accounts';

    // Backward-compatible accessors
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

    // Keep existing relationships working
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function orders()
    {
        return $this->hasManyThrough(Order::class, TenantMembership::class,
            'account_id', 'user_id');
    }
}
```

**This wrapper is NOT returned by `Auth::user()` after Phase 4.** It exists only for:
- Third-party packages that reference `App\Models\User`
- Legacy code paths that haven't been updated yet
- Blade templates (if any) that reference `App\User` or `auth()->user()`

### 10.2 Inertia Shared Data

The `HandleInertiaRequests.php` middleware merges both old and new data structures during transition:

```php
'auth' => [
    'user' => $account ? [
        'id' => $account->id,
        'email' => $account->email,
        'name' => $membership?->customerProfile?->name ?? 'User',
        'profile_image' => $account->profile_image,
        'status' => $account->status,
        // Legacy keys (backward compatible)
        'tenant_id' => $membership?->tenant_id,
        'is_owner' => $membership?->is_owner ?? false,
        'is_admin' => $membership?->role?->name === 'admin',
    ] : null,
    'current_membership' => $membership,
],
```

### 10.3 Third-Party Package Compatibility

| Package | Dependency | Compatibility Strategy |
|---|---|---|
| Spatie Permission | `User` model with HasRoles | Remove HasRoles from User. Use Gate::before() instead. |
| Laravel Nova (if used) | `Auth::user()` returns User | Use class alias or configure Nova to use Account. |
| Laravel Debugbar (dev) | `Auth::user()` model inspection | No issues — Account is authenticatable. |
| Spatie Activitylog | `causer_type = App\Models\User` | Update causer_type to App\Models\Account. Migration script handles existing records. |

### 10.4 Session Compatibility

During transition, sessions have both `user_id` (legacy) and `account_id` (new). The authentication middleware checks both:

```php
// During transition: check both columns
if (Auth::check()) {
    $accountId = session('account_id') ?? session('user_id');
    // Resolve Account from either ID (they match during migration)
}
```

After Phase 4 is fully verified, `user_id` in sessions is no longer checked.

### 10.5 Database FK Compatibility

Existing foreign keys that reference `users.id` remain valid because Account.id == User.id (1:1 mapping during migration). After all references are migrated to `accounts.id`, the foreign keys are updated in Phase 8.

---

## 11. Regression Plan

### 11.1 High-Risk Modules

| Module | Risk | Impact | Mitigation | QA Required |
|---|---|---|---|---|
| **Authentication** | **HIGH** | All users unable to login | Feature flag rollback. Dual-write. Staged deployment. | Full auth test suite. Manual login for all roles. |
| **Authorization** | **HIGH** | All permission checks fail (403 everywhere) | Feature flag `IDENTITY_USE_GATE_BEFORE`. Gradual rollout per route group. | Every controller action. Every policy. Every middleware. |
| **Tenant Isolation** | **HIGH** | Cross-tenant data exposure | ValidateTenantBinding already secure. TenantAware trait on business models. | Cross-tenant access tests. Direct URL manipulation tests. |
| **Billing** | **HIGH** | Incorrect charges, subscription errors | Per-module feature flag. Dual-checking during transition. | Full billing test suite. Manual payment flow. |
| **Subscription** | **HIGH** | Feature gating broken | FeatureGate dual-checking. Grace period for flag transition. | Subscription lifecycle tests. Feature gate tests. |
| **Orders** | **MEDIUM** | Order creation failures, wrong customer context | All 3 order controllers updated consistently. Add tenant_membership_id FK. | Order creation tests. Order listing tests. |
| **Payments** | **MEDIUM** | Payment failures, double charges | Per-module flag. Payment gateway integration tests. | Full payment flow for each gateway. |
| **Notifications** | **MEDIUM** | Missing notifications, wrong recipients | Per-module flag. Dual notification routing. | Notification delivery tests. Tenant-scoped notification tests. |
| **SuperAdmin** | **HIGH** | Platform administrators lose access | Dedicated SuperAdmin test scenario. Gate::before() bypass check. | SuperAdmin login. Tenant management. Impersonation. |
| **Merchant Dashboard** | **MEDIUM** | Frontend JS errors, blank pages | Backward-compatible Inertia shared data. Test all admin pages. | Visual QA of all admin pages. |
| **Password Reset** | **MEDIUM** | Users unable to reset passwords | Keep old table alongside new. Dual-read during transition. | End-to-end password reset flow. |
| **Email Verification** | **LOW** | Verification link failures | Same mechanism (signed URL). Model change only. | Email verification flow. |

### 11.2 Regression Test Matrix

| Test Scenario | Sprint Affected | Expected Behavior | Regression Indicator |
|---|---|---|---|
| Login as admin | Sprint 4 | Redirected to admin dashboard | Login fails or wrong redirect |
| Login as customer | Sprint 4 | Redirected to storefront | Login fails or wrong redirect |
| Login as superadmin | Sprint 4 | Redirected to superadmin dashboard | Login fails |
| Register new merchant | Sprint 4, 6 | Tenant + Account + Membership created | Missing account or membership |
| Register new customer | Sprint 4, 6 | Account + Membership + CustomerProfile created | Missing profile |
| Place order | Sprint 4 | Order created, linked to account | Order missing or wrong user |
| View order history | Sprint 4, 5 | Customer sees own orders | Cross-tenant leak or empty list |
| Password reset | Sprint 4 | Email received, password changed | Token invalid or email not sent |
| Email verification | Sprint 4 | Email verified, status updated | Verification link fails |
| Admin product CRUD | Sprint 5 | All operations work with admin role | 403 errors |
| Customer storefront | Sprint 5 | Products visible, no admin access | Wrong permissions |
| Owner-specific features | Sprint 5 | Billing, staff management accessible | 403 errors on owner-only pages |
| SuperAdmin access | Sprint 5 | All tenant data accessible | 403 errors |
| Invite staff | Sprint 6 | Notification sent, membership created | Missing invitation |
| Switch tenant | Sprint 6 | Context changes, no re-auth | Wrong tenant data |
| Transfer ownership | Sprint 6 | Owner changes, both parties notified | Data loss or wrong owner |

### 11.3 Feature Flag Rollback Triggers

| Metric | Threshold | Action |
|---|---|---|
| Login error rate | > 1% of login requests | Flip `IDENTITY_USE_ACCOUNTS` to false |
| 403 error rate | > 5% of authenticated requests | Flip `IDENTITY_USE_GATE_BEFORE` to false |
| Payment failure rate | > 0.5% of payment attempts | Flip `IDENTITY_MIGRATE_PAYMENTS` to false |
| Order creation failure | > 1% of order attempts | Rollback order-related changes |
| Frontend JS errors | > 2% of page loads | Rollback Inertia shared data changes |

---

## 12. Testing Strategy

### 12.1 Unit Tests

| Test Class | Tests | Sprint |
|---|---|---|
| `tests/Unit/AccountTest.php` | Creation, validation, soft delete, status transitions, notification preferences, login tracking | Sprint 2 |
| `tests/Unit/TenantMembershipTest.php` | Unique constraint, status transitions, owner constraint, invitation fields, profile relationships | Sprint 2 |
| `tests/Unit/CustomerProfileTest.php` | Creation, FK relationship, soft delete | Sprint 2 |
| `tests/Unit/StaffProfileTest.php` | Creation, FK relationship | Sprint 2 |
| `tests/Unit/GateBeforeTest.php` | Owner bypass, SuperAdmin bypass, role check, suspended membership, no membership | Sprint 5 |

### 12.2 Feature Tests

| Test Class | Tests | Sprint |
|---|---|---|
| `tests/Feature/Auth/AuthenticationTest.php` | Login success, login failure, suspended account, banned account, remember me, logout | Sprint 4 |
| `tests/Feature/Auth/RegistrationTest.php` | Account creation, duplicate email, status checks | Sprint 4 |
| `tests/Feature/Auth/PasswordResetTest.php` | Reset link, token validation, password update, session revocation | Sprint 4 |
| `tests/Feature/Auth/EmailVerificationTest.php` | Verification link, resend, verification status | Sprint 4 |
| `tests/Feature/AuthorizationTest.php` | Admin access, customer access, owner bypass, SuperAdmin bypass, role middleware | Sprint 5 |
| `tests/Feature/InvitationTest.php` | Invite existing account, invite new account, accept, expire, wrong account | Sprint 6 |
| `tests/Feature/TenantSwitchTest.php` | Switch tenant, invalid membership, suspended membership, redirect | Sprint 6 |
| `tests/Feature/OwnershipTransferTest.php` | Transfer initiation, confirmation, validation, post-transfer permissions | Sprint 6 |
| `tests/Feature/NotificationTest.php` | Admin notification, customer notification, tenant-scoped list | Sprint 7 |

### 12.3 Integration Tests

| Test | Coverage | Sprint |
|---|---|---|
| Full merchant registration flow | Store create → Account create → Membership create → Login → Dashboard | Sprint 6 |
| Full customer registration flow | Register → Login → Place order → View order | Sprint 6 |
| Multi-tenant identity flow | Same email registers as merchant (Store A) and customer (Store B) | Sprint 6 |
| Cross-tenant isolation | Account A cannot access Tenant B admin routes | Sprint 5 |
| Feature flag toggle | Flip `IDENTITY_USE_ACCOUNTS` on/off, verify both paths work | Sprint 4 |
| Dual-write verification | Write to both accounts and users, verify data integrity | Sprint 4 |

### 12.4 Manual QA

| Scenario | QA Steps | Sprint |
|---|---|---|
| Merchant login | Navigate to /store/{slug}/admin/login → enter credentials → verify redirect | Sprint 4 |
| Customer login | Navigate to /store/{slug}/login → enter credentials → verify redirect | Sprint 4 |
| SuperAdmin login | Navigate to /superadmin/login → enter credentials → verify redirect | Sprint 4 |
| Password reset | Click "Forgot password" → enter email → check email → click link → set new password → login | Sprint 4 |
| Email verification | Register → check email → click link → verify status | Sprint 4 |
| Owner bypass | Set owner role to 'customer' → verify access to admin pages | Sprint 5 |
| Suspended membership | Set membership status to 'suspended' → verify 403 | Sprint 5 |
| Invitation | Invite staff from owner dashboard → check email → accept → verify access | Sprint 6 |
| Tenant switch | Register in 2 stores → login → switch → verify correct context | Sprint 6 |
| Ownership transfer | Transfer ownership → verify new owner has billing access → verify old owner does not | Sprint 6 |

### 12.5 Performance Tests

| Test | Scenario | Target |
|---|---|---|
| Auth query | 100 concurrent login requests | < 100ms P95 |
| Membership lookup | 100 concurrent authenticated requests with membership resolution | < 50ms P95 |
| Permission check | 100 concurrent Gate::before() calls (cached) | < 10ms P95 |
| Migration time | Full data migration on production-size data | < 30 seconds |

### 12.6 Security Tests

| Test | Scenario | Expected |
|---|---|---|
| Cross-tenant product access | Account A tries to view Product of Tenant B | 403 |
| Cross-tenant order access | Account A tries to view Order of Tenant B | 403 |
| Direct URL manipulation | User changes ID in URL to access another tenant's resource | 403 |
| Suspended membership access | Suspended member tries to access admin routes | 403 |
| Expired invitation | Use expired signed URL | Error page |
| Wrong account acceptance | Account A clicks invitation for Account B | Error |

### 12.7 Tenant Isolation Tests

| Test | Scenario | Expected |
|---|---|---|
| Product isolation | Store A products not visible in Store B | Data isolation |
| Order isolation | Store A orders not visible in Store B | Data isolation |
| Customer isolation | Store A customers not visible in Store B | Data isolation |
| Image isolation | Store A images not accessible without Store A context | Access denied or 404 |
| Settings isolation | Store A website settings not visible in Store B | Data isolation |
| Notification isolation | Store A admin does not receive Store B notifications | No leak |

---

## 13. Deployment Strategy

### 13.1 Environment Progression

```
Development (local)
    ↓
Staging (full data copy)
    ↓
Production (feature-flagged rollout)
    ↓
Post-deployment monitoring (24 hours)
    ↓
Feature flag flip (after verification)
```

### 13.2 Sprint 1-2 Deployment (Database + Models)

**Process:**
1. Run migrations on staging
2. Verify rollback
3. Run migrations on production during low-traffic window
4. Monitor for migration errors
5. **No application downtime** — new tables are unused until Sprint 4

**Rollback:** `php artisan migrate:rollback` — drops new tables, restores sessions table.

### 13.3 Sprint 3 Deployment (Data Migration)

**Process:**
1. Run migration scripts on staging with production-size data copy
2. Verify data integrity with validation checkpoints
3. Schedule production migration during lowest traffic window (e.g., 2 AM)
4. Run migration scripts
5. Run validation queries
6. If validation fails → rollback (truncate new tables)

**Rollback:** Truncate new tables. `users` table is untouched.

### 13.4 Sprint 4 Deployment (Authentication Switch)

**Process:**
1. Deploy code with `IDENTITY_USE_ACCOUNTS = false` (default, reads from users)
2. Enable dual-write: new registrations write to both tables
3. Monitor dual-write for 24 hours
4. Flip `IDENTITY_USE_ACCOUNTS = true` in staging
5. Run full auth test suite on staging
6. Flip `IDENTITY_USE_ACCOUNTS = true` in production
7. Monitor login error rates for 1 hour
8. If errors spike → flip back to `false`

**Rollback:** Flip flag to `false`. Dual-write ensures `users` table has latest data.

### 13.5 Sprint 5 Deployment (Authorization Switch)

**Process:**
1. Deploy Gate::before() code with `IDENTITY_USE_GATE_BEFORE = false`
2. Verify no regression with flag off (old auth path still works)
3. Flip `IDENTITY_USE_GATE_BEFORE = true` in staging
4. Run full auth test suite
5. Flip `IDENTITY_USE_GATE_BEFORE = true` in production
6. Monitor 403 error rates for 1 hour
7. If errors spike → flip back to `false`

**Rollback:** Flip flag to `false`.

### 13.6 Sprint 6-7 Deployment (Incremental Migration)

**Process:**
1. Deploy per-module changes with all flags = `false`
2. Enable one module at a time (e.g., `IDENTITY_MIGRATE_NOTIFICATIONS = true`)
3. Monitor that module for 24 hours
4. Enable next module
5. Repeat until all modules migrated

**Rollback:** Flip individual module flag to `false`.

### 13.7 Feature Flag Configuration

```php
// config/feature.php
return [
    'identity_use_accounts' => env('IDENTITY_USE_ACCOUNTS', false),
    'identity_use_gate_before' => env('IDENTITY_USE_GATE_BEFORE', false),
    'identity_migrate_notifications' => env('IDENTITY_MIGRATE_NOTIFICATIONS', false),
    'identity_migrate_billing' => env('IDENTITY_MIGRATE_BILLING', false),
    'identity_migrate_payments' => env('IDENTITY_MIGRATE_PAYMENTS', false),
    'identity_migrate_orders' => env('IDENTITY_MIGRATE_ORDERS', false),
];
```

### 13.8 Monitoring Plan

| Metric | Tool | Threshold | Alert |
|---|---|---|---|
| Login error rate | Laravel logs / DataDog | > 1% of requests | PagerDuty alert |
| 403 rate | Laravel logs | > 5% of authenticated requests | PagerDuty alert |
| Registration failure | Laravel logs | Any failure | Email alert |
| Password reset failure | Laravel logs | > 3 failures/hour | Email alert |
| Dual-write divergence | Scheduled job | Any divergence | Email alert |
| Migration validation | Post-migration script | Any failed checkpoint | Stop deployment |

### 13.9 Post-Deployment Validation

After each sprint deployment, verify:

1. **Login works** — Manual login as merchant, customer, superadmin
2. **Registration works** — Create new account, verify membership created
3. **Password reset works** — Submit email, click link, set new password, login
4. **Authorization works** — Access admin routes, verify 403 for unauthorized users
5. **No data loss** — Compare account/membership counts with user counts
6. **Session persistence** — Login, close browser, reopen, verify still logged in

---

## 14. Engineering Self Review

### 14.1 Hidden Complexity

#### Issue 1: Three Duplicate Order Creation Controllers

**Problem:** `OrderController::store()`, `StorefrontCheckoutController::store()`, and `ClientOrderController::store()` all contain 160+ lines of nearly identical order creation logic. The identity migration adds `tenant_membership_id` to orders — this must be set in all three controllers.

**Resolution:** Extract shared order creation logic into `OrderWorkflow` service or `OrderService`. This is already flagged as P1-6 in the merchant QA report. **Do this before the identity migration** to reduce the number of touch points from 3 to 1.

**Alternative if refactoring is not in scope:** Apply the same change (`$order->tenant_membership_id = $membership->id`) to all three controllers independently. Add a test for each controller to verify.

#### Issue 2: Password Reset Table Recreation Mid-Deployment

**Problem:** Dropping and recreating `password_reset_tokens` mid-deployment invalidates all active reset tokens.

**Resolution:** Create the new table (`password_reset_tokens_new`) alongside the old one in Sprint 1. During transition, application code checks BOTH tables for token validation. After Phase 4 is verified, the old table is dropped and the new one is renamed to `password_reset_tokens`.

#### Issue 3: SuperAdmin Role Detection

**Problem:** SuperAdmin accounts currently have `tenant_id = NULL` on the `users` table and a `superadmin` role assigned via `model_has_roles`. After migration, Account does NOT use HasRoles. How does SuperAdmin detection work?

**Resolution:** The SuperAdmin role check remains on `model_has_roles` (Spatie's pivot table). The Account model has a custom `hasRole()` method that queries `model_has_roles` directly (not through HasRoles trait). This is only used for SuperAdmin detection in Gate::before(). Regular role checks go through `tenant_memberships.role_id`.

#### Issue 4: Notifications Notifiable Type Migration

**Problem:** The `notifications.notifiable_type` column has polymorphic references to `App\Models\User`. After migration, new notifications reference `App\Models\Account`. Queries that filter by notifiable_type must handle both types during transition.

**Resolution:** During Sprint 3 (Migration 8), update all existing notification records to `App\Models\Account`. After that, all new notifications use `App\Models\Account` consistently. No dual-type handling needed after migration.

#### Issue 5: HandleInertiaRequests Backward Compatibility

**Problem:** The Inertia shared data structure changes from `auth.user` (with `name`, `email`, `tenant_id`, `is_owner`) to potentially different keys. Existing frontend components expect the old structure.

**Resolution:** The `HandleInertiaRequests.php` middleware preserves all legacy keys in the shared data array during transition. Frontend components continue receiving `auth.user.name`, `auth.user.tenant_id`, etc. After Phase 8, the legacy keys are removed and components are updated.

#### Issue 6: TenantMembership.account_id SET NULL vs CASCADE

**Problem:** The v1 specification used CASCADE for `tenant_memberships.account_id`. The v2 specification resolved this to SET NULL. If the migration code was already written with CASCADE, it must be updated.

**Resolution:** Verified: this implementation plan uses SET NULL from Sprint 1. The database blueprint v1 also confirms SET NULL. No ambiguity.

### 14.2 Missing Steps

| Step | Sprint | Justification |
|---|---|---|
| Add `tenant_membership_id` to orders table | Sprint 1 | Needed for order-to-membership mapping. Added as nullable FK. |
| Add `tenant_membership_id` to customer_addresses | Sprint 1 | For tenant-scoped addresses. Nullable FK. |
| Add `tenant_membership_id` to wishlists | Sprint 1 | For tenant-scoped wishlists. Nullable FK. |
| Update `model_has_roles` for SuperAdmin in ActivityLog migration | Sprint 3 | ActivityLog causer_type must be updated from User to Account. |
| Create `MembershipResolutionService` | Sprint 4 | Service to centralize current membership resolution. Prevents duplication across middleware. |
| Add `registered_from` column to accounts | Sprint 4 (optional) | Track whether account was created from merchant registration, customer registration, or invitation. Helpful for analytics. |

### 14.3 Migration Risks

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Duplicate emails in `users` table | Low | Account creation fails on UNIQUE constraint | Pre-migration deduplication script. Merge duplicates into one Account with multiple Memberships. |
| Missing role assignments in `model_has_roles` | Medium | Membership created without valid role_id | Default to `customer` role for the tenant if no role found. Log warning. |
| Orphan users (tenant_id points to deleted tenant) | Low | Membership creation fails on FK constraint | Skip membership creation. Create Account only. Log warning for manual intervention. |
| Session table with no `user_id` set | Low | Account backfill misses sessions | Sessions without `user_id` cannot be migrated. These are anonymous sessions — skip them. |
| ActivityLog records with deleted causer_id | Medium | FK constraint violation on causer migration | Use `SET NULL` or skip records where User was already deleted. |

### 14.4 Performance Risks

| Risk | Sprint | Impact | Mitigation |
|---|---|---|---|
| Membership lookup on every request | Sprint 5 | +1 query per authenticated request | Eager load membership with session. Cache membership ID in session. |
| Gate::before() role permission load | Sprint 5 | +1 query + cache load per auth check | Spatie caches permissions for 24 hours. Only the first request after a permission change triggers a DB query. |
| Notification migration query | Sprint 3 | Large UPDATE on notifications table | Run in batches of 1000. Use index on notifiable_type. |
| Session backfill | Sprint 3 | UPDATE on sessions table | Low risk — sessions table is small relative to data tables. |

### 14.5 Security Risks

| Risk | Sprint | Impact | Mitigation |
|---|---|---|---|
| Gate::before() returns true incorrectly | Sprint 5 | Authorization bypass | Unit tests for every Gate::before() path. Code review by second engineer. |
| Membership resolution returns wrong membership | Sprint 4 | Cross-tenant access | Use (account_id, tenant_id) composite lookup — cannot return wrong membership. |
| SuperAdmin hasRole() misdetection | Sprint 5 | SuperAdmin loses access | Dedicated test scenario. SuperAdmin bypass must return `true` unconditionally. |
| Session hijacking via user_id fallback | Sprint 4 | Unauthorized access | Limit user_id fallback window to one session lifetime (120 min). After that, account_id is required. |
| Old password_reset_tokens still valid after switch | Sprint 4 | Stale tokens replayed | Drop old password_reset_tokens table after verification. During transition, validate against both tables. |

### 14.6 Maintainability Concerns

| Concern | Resolution |
|---|---|
| Three profile tables instead of one | Accept for now. If profile types exceed 5, consolidate into a single `membership_profiles` table with type discriminator. |
| Dual-write complexity | Temporary (one release cycle). Wrap in DB transaction. Compensatory actions for failures. |
| User model wrapper | Remove after Phase 8. Keep only if third-party packages require it. |
| Feature flag proliferation | Five flags for identity migration. After all modules are migrated, remove flags in Phase 8 cleanup. |
| Account model should not have `HasRoles` | Custom `hasRole()` method for SuperAdmin detection only. Document this clearly. |

---

## 15. Final Engineering Recommendation

### 15.1 Implementation Order

The recommended implementation order, with rationale:

| Order | Sprint | Rationale |
|---|---|---|
| 1 | **Sprint 1: Database Foundation** | Must exist before anything else. Zero application impact. |
| 2 | **Sprint 2: Models & Relationships** | Must exist before data migration. Types code before data. |
| 3 | **Sprint 3: Data Migration** | Highest risk — test on staging with production-size data first. |
| 4 | **Sprint 4: Authentication** | Core identity change. Feature-flagged for rollback. |
| 5 | **Sprint 5: Authorization** | Depends on auth guard working. Feature-flagged. |
| 6 | **Sprint 6: Registration & Invitation** | New features on the new architecture. |
| 7 | **Sprint 7: Notifications & Billing** | Incremental migration with per-module flags. |
| 8 | **Sprint 8: Testing & QA** | Final verification before production rollout. |

### 15.2 Parallelization Recommendations

- Sprint 1 and Sprint 2 should be merged into a single deployment (tables and models are tightly coupled)
- Sprint 4 and Sprint 5 must NOT be parallelized (shared Gate::before() dependency)
- Sprint 6 can begin after Sprint 4 is verified (can overlap with Sprint 5)
- Sprint 7 can be parallelized with Sprint 6 if resources permit

### 15.3 Risk Acceptance

The following risks are accepted as part of this implementation:

1. **Dual-write complexity** during Sprint 4. Temporary (one release cycle). Compensatory actions for failures.
2. **Session fallback** during transition. Temporary (one session lifetime). Affects only active sessions during deployment.
3. **Three profile tables** instead of a single `membership_profiles` table. Current clarity over future abstraction.
4. **Feature flag proliferation** during migration. All flags are removed in Phase 8 cleanup.

### 15.4 Critical Success Factors

1. **Never rush Sprint 3 (Data Migration).** Test on a full production-size copy. Verify every tenant has exactly one owner.
2. **Never deploy Sprint 4 and Sprint 5 without feature flags.** The ability to flip back is the safety net.
3. **Never drop the `users` table before Phase 8.** Keep it for reference and rollback.
4. **Monitor authentication error rates during Sprint 4 and Sprint 5.** Any spike should trigger an automatic flag flip.
5. **Document every workaround in the code.** Future developers must understand why the backward compatibility layer exists.

### 15.5 Pre-Migration Prerequisites

Before Sprint 1 begins, the following should be completed:

1. **Fix the 3 duplicated order creation controllers** (P1-6 from QA report). Extract shared logic into `OrderWorkflow` service. This reduces the identity migration touch points from 3 controllers to 1 service.
2. **Fix `TenantAware` trait on `PaymentTransaction` and `SubscriptionAuditLog`** (P0-2, P0-3 from QA report). These cross-tenant data leaks should be fixed before the identity migration, not during it.
3. **Fix `WebsiteInfo::first()` calls** (P0-1 from QA report). This cross-tenant data leak on the root domain should be fixed independently.
4. **Ensure all existing auth tests pass** before migration begins. This provides a clean baseline for regression detection.

### 15.6 Success Criteria

The identity migration is considered complete when:

1. A single email can register as merchant (Store A), customer (Store B), and staff (Store C)
2. Each tenant's data is completely isolated
3. Switching between tenants takes one click, no re-authentication
4. Password reset changes password for all tenants at once
5. Email verification covers all current and future memberships
6. Staff can be invited and granted role-specific access
7. Ownership can be transferred without data loss
8. Every identity action is logged with account_id, membership_id, and context
9. All existing features continue working with zero regression
10. Feature flags can be toggled without data loss or user impact

---

*This implementation plan is the single source of truth for Phase 1-8 implementation. Every developer should read this document before writing code. No architectural decisions are made during implementation — only execution. Approved by Principal Architect on 2026-07-07.*
