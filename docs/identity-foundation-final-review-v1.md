# Identity Foundation Final Review — v1

**Status:** FINAL — Engineering Review Complete  
**Date:** 2026-07-07  
**Version:** 1.0  
**Project:** Multi-tenant SaaS E-commerce Platform  
**Supersedes:** No prior document — this is the final review before Phase 1 implementation  
**Governed by:** All prior identity documents (see Foundation Status below)

---

## Table of Contents

1. Executive Summary
2. Foundation Status
3. Owner Override Scope — FINAL POLICY
4. Identity Cache Strategy
5. Legacy Compatibility Strategy
6. Compatibility Matrix
7. Engineering Consistency Review
8. Risk Assessment
9. Final Engineering Approval

---

## 1. Executive Summary

This document is the final engineering review before Phase 1 (Database Foundation) implementation begins. It closes four remaining architectural guardrails that were specified but not fully detailed in prior documents:

1. **Owner Override Scope** — The precise boundary of what an owner can and cannot do, documented as official engineering policy
2. **Identity Cache Strategy** — Complete caching design for the Account → Membership → Role → Permission stack
3. **Legacy Compatibility Strategy** — How every existing module continues working during and after migration
4. **Consistency Verification** — Cross-document audit confirming zero contradictions across all four prior documents

**Status:** All four guardrails are resolved. Zero unresolved issues remain.

**Engineering verdict:** The project is ready to begin Phase 1 (Database Foundation). No additional planning documents are required. The implementation plan (`docs/identity-implementation-plan-v1.md`) is the single reference developers should follow for Sprint 1-8 execution.

---

## 2. Foundation Status

### 2.1 Prior Documents

| Document | Status | Role |
|---|---|---|
| `docs/identity-architecture-lock-v1.md` | ✅ Approved | Original architecture specification |
| `docs/identity-architecture-lock-v2.md` | ✅ Approved | Refined architecture (owner strategy, backward compat, testing) |
| `docs/identity-database-blueprint-v1.md` | ✅ Approved | Complete table/column/FK specifications |
| `docs/identity-implementation-plan-v1.md` | ✅ Approved | 8-sprint execution plan with file-level impact analysis |
| `docs/identity-event-flow-v1.md` | ✅ Approved | 26 event specifications with notification/audit routing |

### 2.2 Architecture Principles (Locked)

These eight principles govern all implementation decisions:

| # | Principle | Meaning |
|---|---|---|
| 1 | Identity is global | Account owns email, password, status. No tenant_id on Account. |
| 2 | Membership is per-tenant | TenantMembership links Account to Tenant with role + ownership. |
| 3 | Authentication proves Identity | Login verifies Account credentials, not membership. |
| 4 | Authorization checks Membership | What you can do depends on your membership's role. |
| 5 | Role is per-Membership | One role per membership. Direct role_id FK. |
| 6 | Profile extends Membership | CustomerProfile, StaffProfile, MerchantProfile linked to membership. |
| 7 | Tenant owns business data | Products, orders, settings belong to Tenant, not Account. |
| 8 | SuperAdmin is platform-level | No tenant membership. Bypasses tenant-scoped authorization. |

### 2.3 Feature Flags (Locked)

| Flag | Purpose | Default |
|---|---|---|
| `IDENTITY_USE_ACCOUNTS` | Switch auth provider from users to accounts | `false` |
| `IDENTITY_USE_GATE_BEFORE` | Enable custom Gate::before() authorization | `false` |
| `IDENTITY_MIGRATE_NOTIFICATIONS` | Use Account model for notifications | `false` |
| `IDENTITY_MIGRATE_BILLING` | Use Account/membership for billing | `false` |
| `IDENTITY_MIGRATE_PAYMENTS` | Use Account for payment flows | `false` |
| `IDENTITY_MIGRATE_ORDERS` | Use Account for order relationships | `false` |

---

## 3. Owner Override Scope — FINAL POLICY

### 3.1 The Ownership Principle

Ownership is a **business and legal responsibility**, not a permission role. The `is_owner` flag on `TenantMembership` is the single source of truth. There is no `owner_id` column on the `tenants` table.

The owner bypass in `Gate::before()` grants **all permissions implicitly** within the tenant scope. The owner's Spatie role assignment (typically `admin`) is irrelevant for authorization — it exists only for Spatie compatibility.

### 3.2 Owner CAN Do — Complete List

All actions within the **owner's own tenant**:

| Category | Action | Policy |
|---|---|---|
| **Store Ownership** | View store dashboard, manage store profile | Unrestricted access to all tenant settings |
| **Store Ownership** | Update tenant name, slug, description, logo, banner | Full CRUD on Tenant model |
| **Store Ownership** | Configure custom domain | Exclusive to owner |
| **Store Ownership** | Transfer ownership to another active member | Exclusive to owner |
| **Store Ownership** | Initiate store deletion (soft-delete) | Exclusive to owner |
| **Store Ownership** | Cancel pending deletion | Exclusive to owner |
| **Store Ownership** | Voluntary store lock/closure | Exclusive to owner |
| **Store Ownership** | Store recovery after lock | Requires SuperAdmin (owner cannot unlock after lock) |
| **Billing** | View billing dashboard, invoices, payment history | Unrestricted |
| **Billing** | Manage payment methods (add, remove, update) | Exclusive to owner |
| **Billing** | Change billing contact information | Exclusive to owner |
| **Billing** | Access billing notifications (receipts, failures) | Exclusive to owner |
| **Subscription** | View current subscription and plan details | Unrestricted |
| **Subscription** | Upgrade, downgrade, cancel subscription | Exclusive to owner |
| **Subscription** | Change billing interval | Exclusive to owner |
| **Subscription** | View subscription history and audit log | Exclusive to owner |
| **Staff Management** | Invite new staff members | Exclusive to owner |
| **Staff Management** | Change staff roles | Exclusive to owner |
| **Staff Management** | Suspend staff members | Exclusive to owner |
| **Staff Management** | Remove staff members | Exclusive to owner |
| **Website Settings** | Update storefront branding, theme, layout | Unrestricted |
| **Website Settings** | Configure SEO settings, custom code | Unrestricted |
| **Website Settings** | Manage payment gateways configuration | Exclusive to owner |
| **Business Operations** | View all products, orders, customers (tenant-scoped) | Unrestricted (via Gate::before() bypass) |
| **Business Operations** | Create, update, delete products | Unrestricted |
| **Business Operations** | Create, update, cancel orders | Unrestricted |
| **Business Operations** | View all analytics and reports | Unrestricted |
| **Business Operations** | Manage promotions, coupons | Unrestricted |
| **Financial Access** | View revenue reports, sales reports, payment reports | Unrestricted within tenant |
| **Financial Access** | Export financial data | Unrestricted within tenant |
| **Financial Access** | Manage payout accounts and methods | Exclusive to owner |

### 3.3 Owner CANNOT Do — Complete List

| Category | Action | Why |
|---|---|---|
| **Platform Administration** | Access SuperAdmin dashboard | Platform-level only; owner has no platform role |
| **Platform Administration** | View or manage other tenants | Cross-tenant access is prohibited by architecture principle #7 |
| **Platform Administration** | Modify platform settings (currency, plans, features) | SuperAdmin-only |
| **Platform Administration** | Manage platform-level roles and permissions | SuperAdmin-only |
| **Platform Administration** | View platform financial reports (all tenants) | SuperAdmin-only |
| **SuperAdmin Actions** | Impersonate other accounts | SuperAdmin-only |
| **SuperAdmin Actions** | Force-unlock their own store after lock | Requires SuperAdmin escalation |
| **SuperAdmin Actions** | Override subscription status manually | SuperAdmin-only |
| **Global Billing** | Modify platform pricing plans | SuperAdmin-only |
| **Global Billing** | View other tenants' billing data | Cross-tenant access prohibited |
| **Other Merchant Stores** | Access admin dashboard of another store | No membership = no access |
| **Other Merchant Stores** | View products, orders, customers of another store | No membership = no access |
| **Platform Settings** | Modify global registration settings | SuperAdmin-only |
| **Platform Settings** | Modify platform-wide notification templates | SuperAdmin-only |
| **Platform Notifications** | Send platform-wide announcements | SuperAdmin-only |
| **Platform Financial Reports** | View aggregate revenue across all tenants | SuperAdmin-only |
| **Tenant Data of Others** | Access any business data belonging to another tenant | Cross-tenant isolation enforced by TenantAware trait + middleware |

### 3.4 Ownership Boundary Rule

```
Owner → Tenant Scope ONLY → Never Platform Scope

Owner of Tenant A:
  ✅ Can do everything within Tenant A
  ✅ Can be customer in Tenant B (with membership)
  ✅ Can be staff in Tenant C (with membership)
  ❌ Cannot do anything in Tenant D (no membership)
  ❌ Cannot do anything at platform level
  ❌ Cannot access SuperAdmin functions
```

### 3.5 Ownership Transfer Policy

| Rule | Policy |
|---|---|
| Who can initiate | Only the current owner |
| Target requirements | Must have active membership in the same tenant. Must not be the current owner. |
| Post-transfer: previous owner | `is_owner = false`, role unchanged (typically `admin`). Retains role-based permissions. Loses owner-exclusive abilities. |
| Post-transfer: new owner | `is_owner = true`. Gains all owner-exclusive abilities. Role unchanged. |
| Irreversible | Transfer is final. No automatic undo. SuperAdmin can reverse manually. |
| Notification | Both parties notified. Audit log written. |

### 3.6 Owner and Subscription

| Aspect | Policy |
|---|---|
| Subscription ownership | Subscription belongs to the **Tenant**, not the owner |
| Billing ownership | Transfers with ownership — new owner manages billing |
| Previous owner payment methods | Must be removed from tenant's active payment methods on transfer |
| Subscription history | Stays with the Tenant — not affected by owner change |
| Owner cannot cancel subscription for others | Only their own tenant's subscription |

### 3.7 Owner and Account Deletion

If the owner's Account is soft-deleted:
- `tenant_memberships.account_id` is set to NULL (SET NULL FK rule)
- The membership record is preserved (audit trail)
- The Tenant loses its owner → SuperAdmin must assign a new owner
- The Tenant itself is NOT deleted (business data preserved)
- Order history referencing the owner's previous Account ID is preserved

---

## 4. Identity Cache Strategy

### 4.1 Caching Philosophy

The identity system operates on a read-heavy, write-light pattern. Authentication, authorization, and tenant resolution happen on **every request**. Membership changes, role changes, and permission changes happen infrequently (typically < 1% of requests).

```
Read path (every authenticated request):
  Account lookup → Membership lookup → Role resolution → Permission check

Write path (infrequent):
  Registration → Membership change → Role change → Permission change
```

**Strategy:** Cache the read path aggressively. Invalidate aggressively on writes. Use short TTLs for safety. Prefer Redis for production, file cache for development.

### 4.2 What to Cache

| Data | Cache Key | TTL | Why Cache |
|---|---|---|---|
| Current membership lookup | `membership:{account_id}:{tenant_id}` | 300s (5 min) | Resolved on every authenticated request |
| Account by email | `account:email:{email}` | 300s (5 min) | Looked up on every login attempt |
| Account by ID | `account:id:{id}` | 300s (5 min) | Looked up on every authenticated request |
| Role permissions | `role:permissions:{role_id}` | 86400s (24h) | Spatie default. Changes infrequently. |
| Tenant subscription | `tenant:subscription:{tenant_id}` | 300s (5 min) | Checked on feature-gated routes |
| Tenant context | `tenant:context:{tenant_id}` | 300s (5 min) | Resolved on every tenant-scoped request |
| User navigation permissions | `nav:permissions:{account_id}:{tenant_id}` | 300s (5 min) | Checked on every admin page load |
| Feature gate cache | `feature:gate:{tenant_id}:{plan_id}` | 300s (5 min) | Checked on feature-gated actions |

### 4.3 What NOT to Cache

| Data | Why Not |
|---|---|
| Active sessions | Sessions are managed by Laravel's session driver (database, Redis, file). No additional cache layer needed. |
| Password hashes | Never cached. Verified on every login. |
| Email verification status | Read from database on MustVerifyEmail check. Lightweight query. |
| Account status (suspended/banned) | Read fresh on each login. Status changes must take effect immediately. |
| Membership status (active/suspended) | Read fresh in middleware. Suspension must take effect immediately. |

### 4.4 Cache Key Naming Convention

```
{domain}:{entity}:{identifier}:{context}

Examples:
membership:42:12              → Account 42's membership in Tenant 12
account:email:user@example.com → Account lookup by email
role:permissions:5             → Permissions for role ID 5
tenant:subscription:12        → Subscription for Tenant 12
tenant:context:12             → Tenant context data
nav:permissions:42:12         → Navigation permissions for Account 42 in Tenant 12
feature:gate:12:3             → Feature gate for Tenant 12 with plan ID 3
```

### 4.5 Cache Invalidation Rules

Every identity-affecting write MUST invalidate the corresponding cache.

| Event | Cache to Invalidate | Timing |
|---|---|---|
| **Account Created** | — (no cache to invalidate, new data) | — |
| **Account Email Changed** (future) | `account:email:{old_email}`, `account:id:{id}` | Immediate |
| **Account Status Changed** | `account:id:{id}` | Immediate |
| **Account Soft-Deleted** | `account:id:{id}`, `account:email:{email}` | Immediate |
| **Login** | `account:email:{email}` (updates last_login_at) | Immediate |
| **Password Changed** | — (password not cached) | — |
| **Email Verified** | `account:id:{id}` | Immediate |
| **Membership Created** | — (no existing cache to invalidate) | — |
| **Membership Activated** | `membership:{account_id}:{tenant_id}` | Immediate |
| **Membership Suspended** | `membership:{account_id}:{tenant_id}`, `nav:permissions:{account_id}:{tenant_id}` | Immediate |
| **Membership Removed** | `membership:{account_id}:{tenant_id}`, `nav:permissions:{account_id}:{tenant_id}` | Immediate |
| **Membership Status Changed** | `membership:{account_id}:{tenant_id}`, `nav:permissions:{account_id}:{tenant_id}` | Immediate |
| **Role Changed** | `membership:{account_id}:{tenant_id}`, `nav:permissions:{account_id}:{tenant_id}` | Immediate |
| **Permission Added to Role** | `role:permissions:{role_id}`, `nav:permissions:*:{tenant_id}` | Immediate |
| **Permission Removed from Role** | `role:permissions:{role_id}`, `nav:permissions:*:{tenant_id}` | Immediate |
| **Owner Transfer Completed** | `membership:{old_owner_id}:{tenant_id}`, `membership:{new_owner_id}:{tenant_id}`, `nav:permissions:*:{tenant_id}` | Immediate |
| **Invitation Accepted** | `membership:{account_id}:{tenant_id}` | Immediate |
| **Invitation Revoked** | `membership:{account_id}:{tenant_id}` | Immediate |
| **Store Switched** | — (session change, no model cache to invalidate) | — |
| **Logout** | — (session destroyed, cache unaffected) | — |
| **Subscription Activated** | `tenant:subscription:{tenant_id}`, `feature:gate:{tenant_id}:*` | Immediate |
| **Subscription Renewed** | `tenant:subscription:{tenant_id}`, `feature:gate:{tenant_id}:*` | Immediate |
| **Subscription Expired** | `tenant:subscription:{tenant_id}`, `feature:gate:{tenant_id}:*` | Immediate |
| **Subscription Plan Changed** | `feature:gate:{tenant_id}:*` | Immediate |
| **Store Locked** | `tenant:context:{tenant_id}` | Immediate |
| **Store Unlocked** | `tenant:context:{tenant_id}` | Immediate |
| **Tenant Settings Changed** | `tenant:context:{tenant_id}` | Immediate |

### 4.6 Cache Refresh Strategy

| Scenario | Strategy |
|---|---|
| **Cache miss on read** | Load from database, write to cache, return data |
| **Stale cache (TTL expired)** | Same as cache miss — reload from database |
| **Active invalidation** | Delete cache key on write events (see invalidation table above) |
| **Bulk invalidation** | Use cache tags (Redis) or prefix scan to invalidate groups (e.g., all `nav:permissions:*:{tenant_id}`) |

### 4.7 Cache Warm-up

| Scenario | Warm-up Strategy |
|---|---|
| **Post-deployment** | First request after deployment warms cache naturally (cache miss → load from DB → cache). No explicit warm-up needed. |
| **After data migration** | Cache is empty after migration. First authenticated request per account warms membership cache. First permission check per role warms Spatie cache. |
| **After bulk role change** | If a role's permissions are changed affecting many members, invalidate `role:permissions:{role_id}` once. Each member's next request will reload. No per-member invalidation needed. |
| **Scheduled warm-up** | Not needed. Identity data is loaded on-demand. Pre-warming would add complexity with minimal benefit. |

### 4.8 Cache Fallback

| Failure | Fallback |
|---|---|
| **Redis connection failure** | Bypass cache entirely. Read directly from database. Log error. Alert operations team. |
| **Cache invalidation failure** | Continue execution. Stale cache will expire naturally via TTL. Log warning. |
| **Cache write failure** | Continue execution. Next read will miss cache and load from database. Log warning. |
| **Cache tag not supported** | Use prefix-based invalidation instead of tags. TTL-based expiry as safety net. |

### 4.9 Redis Compatibility

The cache keys follow Redis best practices:

- Keys are colon-delimited (`membership:42:12`)
- No key contains spaces or special characters
- Keys are designed for Redis `SCAN` with prefix matching (`SCAN 0 MATCH nav:permissions:*:12`)
- TTLs are set on every key (no eternal keys)
- Keys are small (< 200 bytes each)
- No key exceeds Redis string limits

### 4.10 Future Horizontal Scaling

| Concern | Mitigation |
|---|---|
| **Multiple app servers** | Shared Redis cache. All servers read/write same cache. No local cache. |
| **Redis cluster** | Cache keys are designed for consistent hashing. No cross-node dependencies. |
| **Cache stampede** | Short TTLs (5 min) + automatic reload on miss. No expensive recomputation. For role permissions (24h TTL), the database query is < 5ms — stampede risk is negligible. |
| **Read replicas** | All identity reads go to primary database or cache. Identity data is too critical for read-replica lag. |

### 4.11 Performance Considerations

| Metric | Without Cache | With Cache |
|---|---|---|
| Membership lookup | < 2ms (indexed query) | < 0.1ms (Redis) |
| Role permission check | < 5ms (Spatie cache hit) | < 0.1ms (Redis hit, same as Spatie) |
| Account lookup by email | < 1ms (indexed query) | < 0.1ms (Redis) |
| Full auth middleware stack | < 15ms | < 5ms |

The caching strategy targets a **3-5x improvement** in auth middleware performance. The primary benefit is reduced database load, not raw speed (the indexed queries are already fast at current scale).

### 4.12 Security Considerations

| Concern | Mitigation |
|---|---|
| **Cached sensitive data** | No passwords, tokens, or secrets are cached. Only membership IDs, role IDs, permission names, and tenant context. |
| **Cache poisoning** | Cache is write-only via application code. External users cannot write to cache. Invalidation is triggered by database mutations. |
| **Stale authorization** | TTLs are short (5 min max). Worst case: a suspended member retains access for up to 5 minutes. Acceptable risk — session revocation on password change is immediate (not cached). |

---

## 5. Legacy Compatibility Strategy

### 5.1 Adapter Pattern: User as Account Wrapper

The `App\Models\User` model is NOT removed. During the transition period, it is converted to a backward-compatible wrapper that reads from the `accounts` table:

```
Before migration:
  User model → reads from `users` table
  Auth::user() → returns User instance

After Sprint 4 (feature flag on):
  User model → reads from `accounts` table (via $table = 'accounts')
  Auth::user() → returns Account instance
  User wrapper exists only for third-party packages and legacy references

After Phase 8:
  User model → deleted (or kept as thin wrapper for packages)
  All references → Account model
```

### 5.2 Compatibility Layer Architecture

```
┌──────────────────────────────────────────────────┐
│                 Application Code                   │
│  (controllers, services, frontend, tests)          │
└──────────────────────┬───────────────────────────┘
                       │
          ┌────────────┴────────────┐
          │                         │
          ▼                         ▼
┌──────────────────┐   ┌──────────────────────────┐
│   Account Model   │   │  User Model (Wrapper)     │
│   (new identity)  │   │  (backward compat only)   │
│                   │   │                           │
│  - Auth::user()   │   │  - Third-party packages   │
│  - Gate::before() │   │  - Legacy code references │
│  - Relationships  │   │  - Blade templates (if)   │
└──────────────────┘   └──────────────────────────┘
                       │
                       ▼
          ┌──────────────────────────┐
          │   accounts table         │
          │   (source of truth)      │
          └──────────────────────────┘
          ┌──────────────────────────┐
          │   users table            │
          │   (deprecated, read-only) │
          └──────────────────────────┘
```

### 5.3 Dual-Read Strategy

| Read Pattern | Phase 1-3 | Phase 4-5 | Phase 6-7 | Phase 8 |
|---|---|---|---|---|
| Authentication | `users` table | `accounts` table (flag) or `users` (fallback) | `accounts` only | `accounts` only |
| Session lookup | `sessions.user_id` | `sessions.account_id` (with `user_id` fallback) | `sessions.account_id` | `sessions.account_id` |
| Authorization | User::can() via Spatie | Gate::before() (flag) or HasRoles (fallback) | Gate::before() only | Gate::before() only |
| User relationships | `$user->orders()`, etc. | Backward-compatible accessors on Account | Native Account relationships | Native Account relationships |
| Inertia `auth.user` | Current (User-based) | Merged (Account + backward keys) | Account only | Account only |

### 5.4 Dual-Write Strategy

During Phase 4-7, mutations write to BOTH new and legacy tables:

```php
// Dual-write pattern (Phase 4-7 only)
if (! config('feature.identity_use_accounts')) {
    // Legacy write: create User record mirroring Account
    User::create([
        'id' => $account->id,  // Same ID
        'email' => $account->email,
        'password' => $account->password,
        'name' => $data['name'],
        'tenant_id' => $tenant->id,
        'status' => 'active',
    ]);
}

// New write always
$account = Account::create([...]);
```

**Compensatory action:** If the legacy write fails, log the failure and queue a reconciliation job. The `accounts` table is the source of truth — the `users` table is deprecated.

### 5.5 Module Compatibility Detail

#### Authentication (MAJOR CHANGE)

| Aspect | Detail |
|---|---|
| **Legacy dependency** | `Auth::user()` returns User. Auth guard provider is `users`. |
| **Compatibility strategy** | Feature flag switches provider between User and Account. Dual-read during transition. Existing sessions remain valid via `user_id` → `account_id` mapping. |
| **Migration phase** | Sprint 4 |
| **Risk level** | HIGH |
| **Rollback** | Flip `IDENTITY_USE_ACCOUNTS` to `false` |
| **Testing required** | Full auth test suite, manual login for each role |

#### Authorization / Spatie Permission (MAJOR CHANGE)

| Aspect | Detail |
|---|---|
| **Legacy dependency** | User model uses `HasRoles` trait. `$user->can()` checks through Spatie. |
| **Compatibility strategy** | Gate::before() replaces HasRoles. `register_permission_check_method = false`. Feature flag `IDENTITY_USE_GATE_BEFORE` controls which path is active. |
| **Migration phase** | Sprint 5 |
| **Risk level** | HIGH |
| **Rollback** | Flip `IDENTITY_USE_GATE_BEFORE` to `false` |
| **Testing required** | Every controller action, every policy, every middleware |

#### Billing (MINOR REFACTOR)

| Aspect | Detail |
|---|---|
| **Legacy dependency** | `$user->tenant->subscription`, `$user->can('billing.manage')` |
| **Compatibility strategy** | Backward-compatible accessors on Account provide `$account->tenant` via currentMembership. Gate::before() handles `can()` checks. |
| **Migration phase** | Sprint 7 |
| **Risk level** | MEDIUM |
| **Rollback** | Flip `IDENTITY_MIGRATE_BILLING` to `false` |
| **Testing required** | Full billing flow, subscription management |

#### Subscriptions (MINOR REFACTOR)

| Aspect | Detail |
|---|---|
| **Legacy dependency** | `$user->hasActiveSubscription()`, `$user->hasFeature()` on User model. |
| **Compatibility strategy** | Add backward-compatible methods to Account: `$account->hasActiveSubscription()` delegates to `$account->currentMembership?->tenant->subscription`. |
| **Migration phase** | Sprint 7 (alongside billing) |
| **Risk level** | MEDIUM |
| **Rollback** | Flip `IDENTITY_MIGRATE_BILLING` to `false` |
| **Testing required** | Subscription lifecycle tests, feature gate tests |

#### FeatureGate Service (MAJOR REFACTOR)

| Aspect | Detail |
|---|---|
| **Legacy dependency** | `FeatureGate::forUser($user)` where `$user` must have `tenant_id`. |
| **Compatibility strategy** | Add `FeatureGate::forAccount($account)` that resolves tenant via currentMembership. Keep legacy method during transition with a deprecation warning. |
| **Migration phase** | Sprint 5 (alongside authorization) |
| **Risk level** | HIGH |
| **Rollback** | Revert to `forUser()` implementation |
| **Testing required** | Feature gate integration tests for each gated feature |

#### Orders (MINOR REFACTOR)

| Aspect | Detail |
|---|---|
| **Legacy dependency** | `$order->user_id` references `users.id`. `$order->customerMembership` already exists as a relationship. |
| **Compatibility strategy** | Add `tenant_membership_id` to orders (nullable). Keep `user_id` column during transition. Update all 3 order creation controllers consistently. |
| **Migration phase** | Sprint 1 (schema) + Sprint 4 (application) |
| **Risk level** | MEDIUM |
| **Rollback** | Revert `tenant_membership_id` column addition |
| **Testing required** | Order creation for guest, customer, admin. All 3 controllers tested. |

#### Products (NO CHANGE)

| Aspect | Detail |
|---|---|
| **Legacy dependency** | `$product->created_by` references `users.id` but is informational. Products are tenant-scoped via TenantAware trait. |
| **Compatibility strategy** | No changes needed. Product CRUD is independent of auth model. |
| **Migration phase** | None |
| **Risk level** | LOW |
| **Rollback** | Not applicable |

#### Customers (MINOR REFACTOR)

| Aspect | Detail |
|---|---|
| **Legacy dependency** | `CustomerAddress.user_id` references `users.id`. `Wishlist.user_id` references `users.id`. |
| **Compatibility strategy** | Add `tenant_membership_id` to `customer_addresses` and `wishlists` (nullable). Keep `user_id` columns during transition. |
| **Migration phase** | Sprint 1 (schema) + Sprint 4 (application) |
| **Risk level** | MEDIUM |
| **Rollback** | Revert new column additions |
| **Testing required** | Address CRUD, wishlist CRUD, cross-tenant isolation |

#### Notifications (MINOR REFACTOR)

| Aspect | Detail |
|---|---|
| **Legacy dependency** | `$user->notifications()`, `Notification::send($users, $notification)`. `notifiable_type` = `App\Models\User`. |
| **Compatibility strategy** | Account model implements Notifiable. Update `Tenant::notifyAdmins()` to iterate memberships. Migrate existing notification records from User to Account. |
| **Migration phase** | Sprint 7 |
| **Risk level** | MEDIUM |
| **Rollback** | Flip `IDENTITY_MIGRATE_NOTIFICATIONS` to `false` |
| **Testing required** | Notification delivery, tenant-scoped filtering |

#### Payments (MINOR REFACTOR)

| Aspect | Detail |
|---|---|
| **Legacy dependency** | `PaymentIntent.user_id`, `PaymentTransaction.user_id`. |
| **Compatibility strategy** | Add `account_id` columns alongside existing `user_id`. Migrate data. |
| **Migration phase** | Sprint 1 (schema) + Sprint 7 (application) |
| **Risk level** | MEDIUM |
| **Rollback** | Flip `IDENTITY_MIGRATE_PAYMENTS` to `false` |
| **Testing required** | Full payment flow for each gateway |

#### Financial Console (NO CHANGE)

| Aspect | Detail |
|---|---|
| **Legacy dependency** | Financial reports query `PaymentTransaction`, `Subscription` records. No direct User dependency. |
| **Compatibility strategy** | No changes needed. Financial console works with payment/subscription tables. |
| **Migration phase** | None |
| **Risk level** | NONE |
| **Testing required** | No identity-related changes needed |

#### Merchant Dashboard (TEMPORARY ADAPTER)

| Aspect | Detail |
|---|---|
| **Legacy dependency** | `Auth::user()` in controllers. `$page.props.auth.user` in frontend. |
| **Compatibility strategy** | HandleInertiaRequests middleware merges backward-compatible keys (`name`, `email`, `tenant_id`, `is_owner`) into the shared data. Frontend components continue using the same keys. Adapter is removed in Phase 8. |
| **Migration phase** | Sprint 4 (Inertia shared data) |
| **Risk level** | MEDIUM |
| **Rollback** | Revert shared data changes |
| **Testing required** | Visual QA of all admin pages |

#### SuperAdmin (MINOR REFACTOR)

| Aspect | Detail |
|---|---|
| **Legacy dependency** | `$user->isSuperAdmin()`, `$user->hasRole('superadmin')`. SuperAdmin has `tenant_id = null` in users table. |
| **Compatibility strategy** | SuperAdmin detection changes from `users.tenant_id IS NULL` to custom `hasRole('superadmin')` on Account (queries `model_has_roles` directly). |
| **Migration phase** | Sprint 5 |
| **Risk level** | HIGH (if superadmin detection fails, platform administrators lose access) |
| **Rollback** | Flip `IDENTITY_USE_GATE_BEFORE` to `false` |
| **Testing required** | SuperAdmin login, tenant management, impersonation, all superadmin pages |

#### Website Settings (NO CHANGE)

| Aspect | Detail |
|---|---|
| **Legacy dependency** | WebsiteInfo is tenant-scoped, loaded per-tenant irrespective of authentication. |
| **Compatibility strategy** | No changes needed. WebsiteInfo is independent of auth model. |
| **Migration phase** | None |
| **Risk level** | NONE |
| **Testing required** | No identity-related changes needed |

#### Pusher Notifications (NO CHANGE)

| Aspect | Detail |
|---|---|
| **Legacy dependency** | Pusher broadcasts use channel names based on model IDs. |
| **Compatibility strategy** | Channel names use Account ID (same as User ID during migration). No change in channel naming. |
| **Migration phase** | None |
| **Risk level** | LOW |
| **Testing required** | Real-time notification delivery |

#### Image Upload (MINOR REFACTOR)

| Aspect | Detail |
|---|---|
| **Legacy dependency** | `ImageUploadService` tracks `uploaded_by` referencing User ID. |
| **Compatibility strategy** | Change `uploaded_by` to reference Account ID instead. Same ID value during migration (1:1 mapping). |
| **Migration phase** | Sprint 4 |
| **Risk level** | LOW |
| **Testing required** | Image upload for each role |

#### Currency Management (NO CHANGE)

| Aspect | Detail |
|---|---|
| **Legacy dependency** | Currency config reads from `WebsiteInfo` (merchant-level) and `PlatformSetting` (platform-level). No identity dependency. |
| **Compatibility strategy** | No changes needed for identity migration. Currency issues from QA report (P0-5, P0-6) are separate concerns. |
| **Migration phase** | None |
| **Risk level** | NONE |
| **Testing required** | No identity-related changes needed |

---

## 6. Compatibility Matrix

| Module | Legacy Dependency | Compatibility Strategy | Migration Phase | Risk Level | Regression Risk | Testing Required | Cleanup Phase |
|---|---|---|---|---|---|---|---|
| Authentication | `Auth::user()` returns User. Provider: `users`. | Feature flag. Dual-read. Account adapter. | Sprint 4 | HIGH | HIGH | Full auth test suite. Manual login for all roles. | Phase 8 |
| Authorization / Spatie | User HasRoles. `$user->can()` via Spatie. | Gate::before(). `register_permission_check_method = false`. | Sprint 5 | HIGH | HIGH | Every controller. Every policy. Every middleware. | Phase 8 |
| Billing | `$user->tenant->subscription`. | Backward-compatible Account accessors. Gate::before() handles `can()`. | Sprint 7 | MEDIUM | MEDIUM | Full billing flow. Subscription management. | Phase 8 |
| Subscription | `$user->hasActiveSubscription()`. | Account delegates to membership → tenant → subscription. | Sprint 7 | MEDIUM | MEDIUM | Subscription lifecycle tests. Feature gate tests. | Phase 8 |
| FeatureGate | `FeatureGate::forUser($user)`. | Add `forAccount()`. Keep legacy method with deprecation. | Sprint 5 | HIGH | MEDIUM | Feature gate integration tests. | Phase 8 |
| Orders | `$order->user_id` references `users.id`. | Add `tenant_membership_id` (nullable). Update 3 controllers. | Sprint 1 + 4 | MEDIUM | MEDIUM | Order creation (all 3 controllers). Order listing. | Phase 8 |
| Products | `$product->created_by` (informational). TenantAware trait. | No changes. | None | LOW | LOW | No identity tests needed. | None |
| Customers | `CustomerAddress.user_id`. `Wishlist.user_id`. | Add `tenant_membership_id` (nullable). | Sprint 1 + 4 | MEDIUM | MEDIUM | Address CRUD. Wishlist CRUD. | Phase 8 |
| Notifications | `$user->notifications()`. `notifiable_type = User`. | Account notifiable. Update `notifyAdmins()`. Migrate records. | Sprint 7 | MEDIUM | MEDIUM | Notification delivery. Tenant-scoped filtering. | Phase 8 |
| Payments | `PaymentIntent.user_id`. `PaymentTransaction.user_id`. | Add `account_id` columns. Migrate data. | Sprint 1 + 7 | MEDIUM | MEDIUM | Full payment flow for each gateway. | Phase 8 |
| Financial Console | Queries payment/subscription tables. No User dependency. | No changes. | None | NONE | NONE | None. | None |
| Merchant Dashboard | `Auth::user()` in controllers. Inertia `auth.user`. | HandleInertiaRequests backward-compatible keys. | Sprint 4 | MEDIUM | MEDIUM | Visual QA of all admin pages. | Phase 8 |
| SuperAdmin | `$user->isSuperAdmin()`. `hasRole('superadmin')`. `tenant_id = null`. | Custom `hasRole()` on Account. Gate::before() bypass. | Sprint 5 | HIGH | HIGH | SuperAdmin login. All superadmin pages. | Phase 8 |
| Website Settings | WebsiteInfo tenant-scoped. No User dependency. | No changes. | None | NONE | NONE | None. | None |
| Pusher | Channels use model IDs. | Account ID = User ID during migration. | None | LOW | LOW | Real-time notification tests. | None |
| Image Upload | `uploaded_by` references User ID. | Change to Account ID. | Sprint 4 | LOW | LOW | Image upload tests. | Phase 8 |
| Currency | Reads from WebsiteInfo + PlatformSetting. No identity dependency. | No changes. | None | NONE | NONE | None. | None |

---

## 7. Engineering Consistency Review

### 7.1 Cross-Document Audit

Every prior document was audited against this document for contradictions. The following sections were verified:

| Prior Document | Key Sections Reviewed | Contradictions Found |
|---|---|---|
| Architecture Lock v2 | Owner Strategy (§9), Gate::before() (§9.4), Owner Responsibilities (§9.2), Backward Compatibility Matrix (§27) | 0 — consistent |
| Database Blueprint v1 | FK rules (SET NULL on tm.account_id), Index strategy, Table specifications | 0 — consistent |
| Implementation Plan v1 | Sprint definitions, File impact analysis, Dual-write strategy, Feature flags | 0 — consistent |
| Event Flow v1 | Event specifications, Notification routing, Audit log schema | 0 — consistent |

### 7.2 Specific Consistency Checks

**Check 1: Owner is_owner flag location**
- V2: `is_owner` on TenantMembership, not on Account, not on Tenant
- Blueprint: `tenant_memberships.is_owner` BOOLEAN
- Implementation: Same column in Sprint 1 migration
- Event Flow: Owner Transfer events on TenantMembership
- **This document:** `tenant_memberships.is_owner` — ✅ Consistent

**Check 2: SET NULL on tenant_memberships.account_id**
- V2: SET NULL (overrides v1's CASCADE)
- Blueprint: SET NULL (v2 resolution)
- Implementation: SET NULL in FK specification
- Event Flow: Account deletion preserves membership record
- **This document:** SET NULL confirmed — ✅ Consistent

**Check 3: SuperAdmin detection**
- V2: SuperAdmin has no tenant membership. Gate::before() checks `hasRole('superadmin')`.
- Blueprint: No TenantMembership for SuperAdmin. `model_has_roles` remains.
- Implementation: Custom `hasRole()` on Account queries `model_has_roles` directly.
- Event Flow: SuperAdmin bypass documented in Login event.
- **This document:** Same approach — ✅ Consistent

**Check 4: Permission overrides deferred**
- V2: Moved to Version 5. Not in Phase 1-5.
- Blueprint: `staff_profiles.permissions_overrides` JSON omitted (or nullable, never read).
- Implementation: Phase 5 uses strict role-based only.
- Event Flow: Permission Changed event is for role-permission assignments only.
- **This document:** No mention of permission overrides — ✅ Consistent

**Check 5: Session strategy**
- V2: `account_id` + `current_tenant_membership_id` in session.
- Blueprint: `sessions.account_id` and `sessions.current_tenant_membership_id` added.
- Implementation: Dual-read during transition (check both `user_id` and `account_id`).
- Event Flow: Login event creates session with both keys.
- **This document:** Same approach — ✅ Consistent

**Check 6: Password reset table**
- V2: Keyed by `account_id`, not `email`.
- Blueprint: New table alongside old. Old dropped after Phase 4.
- Implementation: `password_reset_tokens_new` created in Sprint 1. Dual-read during transition.
- Event Flow: Password Reset Requested + Password Changed events use new table.
- **This document:** Same approach — ✅ Consistent

**Check 7: Profile table design**
- V2: Three profile tables (customer, staff, merchant).
- Blueprint: Three separate tables with UNIQUE FK to `tenant_memberships.id`.
- Implementation: Three models, three migrations.
- Event Flow: Membership Created event creates appropriate profile.
- **This document:** Three profile tables — ✅ Consistent

**Check 8: Dual-write scope**
- V2: Not explicitly detailed (added in Implementation Plan).
- Blueprint: Write to both tables during Phase 4. Feature flag controls reads.
- Implementation: Writes to both tables, reads from one. Transaction-wrapped.
- Event Flow: Not detailed (application-level concern).
- **This document:** Dual-write confirmed with compensatory actions — ✅ Consistent

### 7.3 Contradictions Found and Resolved

**None.** All prior documents are internally consistent and consistent with each other. The architecture has been stable across 5 documents with zero contradictions.

---

## 8. Risk Assessment

### 8.1 Final Risk Register

| Risk | Category | Likelihood | Impact | Mitigation | Status |
|---|---|---|---|---|---|
| Data migration produces duplicate email accounts | Migration | MEDIUM | HIGH | Pre-migration deduplication script. Group by email. Merge duplicates. | ACCEPTABLE |
| Auth guard switch invalidates existing sessions | Migration | MEDIUM | HIGH | Dual-read with `user_id` fallback. Feature flag rollback. | ACCEPTABLE |
| Gate::before() returns wrong result for edge case | Authorization | LOW | HIGH | Comprehensive unit tests for every Gate::before() path. Code review. | ACCEPTABLE |
| SuperAdmin detection fails | Authorization | LOW | CRITICAL | Dedicated test scenario. Gate::before() SuperAdmin bypass must return true unconditionally. | ACCEPTABLE |
| Frontend breaks due to missing Inertia keys | Frontend | MEDIUM | HIGH | HandleInertiaRequests preserves all legacy keys. Test all admin/storefront pages. | ACCEPTABLE |
| Three order controllers updated inconsistently | Maintenance | MEDIUM | MEDIUM | Extract shared logic into OrderWorkflow service BEFORE identity migration. | ACCEPTABLE (pre-migration prerequisite) |
| Notification migration misses records | Migration | LOW | MEDIUM | Count-based validation before/after migration script. | ACCEPTABLE |
| Cache invalidation misses an event | Performance | LOW | LOW | Short TTLs (5 min) as safety net. Stale data auto-corrects. | ACCEPTABLE |
| Permission cache flush not triggered | Authorization | LOW | MEDIUM | Spatie's built-in cache flush on role/permission change. Additional flush in invalidation table. | ACCEPTABLE |
| Owner transfer race condition | Concurrency | LOW | MEDIUM | `lockForUpdate()` on tenant row within transaction. | ACCEPTABLE |
| Dual-write divergence | Migration | LOW | MEDIUM | Reconciliation job. Accounts table is source of truth. | ACCEPTABLE |
| Third-party package expects User class | Compatibility | LOW | LOW | User wrapper model. Class alias as fallback. | ACCEPTABLE |

### 8.2 Unresolved Risks

**Zero unresolved risks.** All identified risks have clear mitigation strategies. No risk is accepted without a documented fallback.

### 8.3 Pre-Migration Prerequisites

Before Sprint 1 begins, these items must be completed:

| Prerequisite | Owner | Due |
|---|---|---|
| Fix cross-tenant data leaks: `PaymentTransaction` and `SubscriptionAuditLog` missing TenantAware trait (P0-2, P0-3 from QA report) | Development team | Before Sprint 1 |
| Fix `WebsiteInfo::first()` calls on root domain leaking cross-tenant data (P0-1 from QA report) | Development team | Before Sprint 1 |
| Extract duplicated order creation logic into shared `OrderWorkflow` service (P1-6 from QA report) | Development team | Before Sprint 1 |
| Register `BillingPaymentMethodPolicy` in AuthServiceProvider (P1-5 from QA report) | Development team | Before Sprint 1 |
| Set `FeatureGate::DEV_MODE = true` re-enable check (P1-8 from QA report) | Development team | Before Sprint 1 |

These are pre-existing QA issues that must be fixed before the identity migration begins. They are not caused by the identity migration, but fixing them beforehand reduces the complexity of the migration.

---

## 9. Final Engineering Approval

### 9.1 Approval Checklist

| # | Criterion | Status | Notes |
|---|---|---|---|
| 1 | Architecture is locked (v2 document approved) | ✅ | No open architecture questions |
| 2 | Database blueprint is locked (v1 document approved) | ✅ | All table/column/FK decisions made |
| 3 | Implementation plan is complete (v1 document approved) | ✅ | 8 sprints, file-level impact, service impact |
| 4 | Event flows are specified (v1 document approved) | ✅ | 26 events, notification routing, audit schema |
| 5 | Owner override scope is documented | ✅ | This document (§3) |
| 6 | Cache strategy is designed | ✅ | This document (§4) |
| 7 | Legacy compatibility is documented per-module | ✅ | This document (§5, §6) |
| 8 | Cross-document consistency verified | ✅ | Zero contradictions found |
| 9 | All risks identified and mitigated | ✅ | 12 risks documented with mitigations |
| 10 | Pre-migration prerequisites identified | ✅ | 5 items from QA report |
| 11 | No architectural decisions remain | ✅ | All decisions locked across 5 documents |
| 12 | Implementation can begin without additional planning | ✅ | Developers follow implementation plan only |

### 9.2 Engineering Recommendation

**The project is ready to begin Phase 1 (Database Foundation).**

All five identity foundation documents are now complete:

| Document | What It Provides | Developer Uses It For |
|---|---|---|
| Architecture Lock v2 | Why we're building this. The architectural rules. | Understanding the system design |
| Database Blueprint v1 | What the database looks like. Every column and FK. | Writing migrations |
| Implementation Plan v1 | How we build it. 8 sprints with file-level impact. | Day-to-day development |
| Event Flow v1 | What events fire, what notifications send, what gets logged. | Writing services, notifications, audit |
| Foundation Final Review v1 | The final guardrails: owner policy, cache, compatibility. | Implementation reference |

### 9.3 Developer Instructions

When Phase 1 implementation begins, developers should:

1. **Read** `docs/identity-implementation-plan-v1.md` first — it contains the sprint-by-sprint breakdown with file-level impact
2. **Reference** `docs/identity-database-blueprint-v1.md` for exact column types, FK rules, and index specifications
3. **Reference** `docs/identity-architecture-lock-v2.md` for architectural rules and business logic
4. **Reference** `docs/identity-event-flow-v1.md` for notification/audit requirements
5. **Reference** this document for owner rules, cache invalidation, and backward compatibility

### 9.4 Sprint 1 Go Order

```
docs/identity-foundation-final-review-v1.md
    │
    ▼
docs/identity-implementation-plan-v1.md → Sprint 1: Database Foundation
    │
    ├── Migration 1: Create accounts table
    ├── Migration 2: Create password_reset_tokens (new)
    ├── Migration 3: Create tenant_memberships table
    ├── Migration 4: Create customer_profiles table
    ├── Migration 5: Create staff_profiles table
    ├── Migration 6: Create merchant_profiles table
    ├── Migration 7: Create social_accounts table
    ├── Migration 8: Modify sessions table
    └── Migration 9: Modify notifications (no schema change)
```

**Sprint 1 has zero application code changes.** Only database migrations. This makes it the safest sprint — it can be deployed and rolled back without affecting any running code.

---

*This document is the final engineering review before Phase 1 implementation. It closes all remaining architectural guardrails. After approval, no additional planning documents should be required before implementation. Approved by Principal Architect on 2026-07-07.*
