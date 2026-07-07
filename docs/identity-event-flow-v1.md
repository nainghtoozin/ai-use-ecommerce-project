# Identity Event Flow — v1

**Status:** FINAL — Event Architecture Locked  
**Date:** 2026-07-07  
**Version:** 1.0  
**Project:** Multi-tenant SaaS E-commerce Platform  
**Governed by:** `docs/identity-architecture-lock-v2.md`  
**Database source:** `docs/identity-database-blueprint-v1.md`  
**Implementation source:** `docs/identity-implementation-plan-v1.md`  
**Purpose:** Single source of truth for every lifecycle event in the Account + Membership architecture. Governs all future Notifications, Audit Logs, Email, Webhooks, Billing Events, and Automation.

---

## Table of Contents

1. Executive Summary
2. Event Architecture Principles
3. Core Entity Lifecycle Diagrams
4. Event Specifications
5. Event Dependency Graph
6. Notification Routing Matrix
7. Audit Log Schema
8. Idempotency & Retry Policy
9. Failure Handling & Rollback
10. Future Readiness
11. Engineering Self Review

---

## 1. Executive Summary

This document defines every lifecycle event in the Account + Membership identity architecture. Each event specifies its purpose, trigger, preconditions, postconditions, database impact, services activated, notifications dispatched, audit logs written, and failure handling.

**25 events** are specified across 6 lifecycle categories:

| Category | Events | Count |
|---|---|---|
| **Account Lifecycle** | Created, Password Reset Requested, Password Changed, Email Verification, Login, Logout | 6 |
| **Membership Lifecycle** | Created, Activated, Suspended, Removed, Role Changed, Permission Changed | 6 |
| **Registration Flows** | Merchant Registration, Customer Registration, Store Creation | 3 |
| **Invitation Flows** | Sent, Accepted, Rejected, Expired | 4 |
| **Ownership Flows** | Transfer Started, Transfer Completed | 2 |
| **Subscription & Store** | Activated, Renewed, Expired, Store Locked, Store Unlocked | 5 |

**Total: 26 events**

### Event Architecture Principle

```
Event Source → Event Dispatcher → Service Layer → 
    ├── Database Write
    ├── Notifications (in-app, email)
    ├── Audit Log (activity_log)
    └── Future: Webhook / Queue / Billing Event
```

Every event follows a strict ordering:
1. Source triggers event
2. Preconditions validated
3. Database transaction executed
4. Postconditions confirmed
5. Notifications dispatched
6. Audit log written
7. Future webhook/queue registered (if applicable)

---

## 2. Event Architecture Principles

### 2.1 Event Ownership

| Entity | Owns Events | Subscribes To Events |
|---|---|---|
| Account | Created, Password Reset, Email Verification, Login, Logout | Membership events (via notifications) |
| TenantMembership | Created, Activated, Suspended, Removed, Role Changed | Account events (via relationship) |
| Tenant | Store Locked, Store Unlocked | Membership events (via ownership) |
| Subscription | Activated, Renewed, Expired | Account events (via billing contact) |

### 2.2 Event Ordering

Events are strictly ordered within a transaction:

```
1. Precondition Check
2. Database Mutation
3. Postcondition Verification
4. Notification Dispatch
5. Audit Log Write
6. Future Event Registration (async)
```

### 2.3 Idempotency

| Event Type | Idempotent? | Strategy |
|---|---|---|
| Account Created | YES | Email unique constraint prevents duplicates |
| Membership Created | YES | UNIQUE(account_id, tenant_id) prevents duplicates |
| Membership Suspended | YES | Status already suspended → no-op |
| Membership Removed | YES | Status already removed → no-op |
| Owner Transfer Started | NO | Must execute exactly once per initiation |
| Owner Transfer Completed | NO | Must execute exactly once per transfer |
| Password Changed | YES | Token validates once; subsequent attempts fail |
| Email Verification | YES | Already verified → no-op |
| Login | NO | Creates unique session each time |
| Logout | NO | Destroys specific session |

### 2.4 Retry Policy

| Can Retry | Cannot Retry |
|---|---|
| Email Notification | Login (new session each time) |
| Database Write (transaction retry) | Owner Transfer (state change) |
| Webhook Dispatch | Password Change (token consumed) |
| Audit Log Write | Membership Status Change (state machine) |

### 2.5 Transaction Boundaries

Each event that mutates database state MUST be wrapped in a database transaction. If any step in the event handler fails, the entire transaction is rolled back:

```
BEGIN TRANSACTION
    ├── Validate preconditions
    ├── Execute mutation(s)
    ├── Verify postconditions
    ├── Dispatch notifications (queued)
    ├── Write audit log
    └── Register future events (queued)
COMMIT
```

Notifications and future events are dispatched AFTER commit (via queued jobs). This prevents the event handler from failing due to a notification delivery failure.

---

## 3. Core Entity Lifecycle Diagrams

### 3.1 Account Lifecycle

```
                    ┌──────────────┐
                    │  PENDING     │ (email not verified)
                    │              │
                    │  Can login?  │ NO
                    │  Can act?    │ NO
                    └──────┬───────┘
                           │
                     Email Verification
                           │
                           ▼
                    ┌──────────────┐
                    │  ACTIVE      │ (email verified, status = active)
                    │              │
                    │  Can login?  │ YES
                    │  Can act?    │ YES (with valid membership)
                    │  Status      │ 'active'
                    └──────┬───────┘
                           │
              ┌────────────┼────────────┐
              │            │            │
              ▼            ▼            ▼
    ┌──────────────┐ ┌──────────┐ ┌──────────┐
    │  SUSPENDED   │ │  BANNED  │ │ DELETED  │
    │              │ │          │ │ (soft)   │
    │ Can login?   │ │ Can      │ │          │
    │ NO           │ │ login?   │ │ Can      │
    │ Status:      │ │ NO       │ │ login?   │
    │ 'suspended'  │ │ Status:  │ │ NO       │
    │              │ │ 'banned' │ │          │
    └──────────────┘ └──────────┘ └──────────┘
```

**State transitions:**
- PENDING → ACTIVE (email verified)
- ACTIVE → SUSPENDED (admin action)
- SUSPENDED → ACTIVE (admin action)
- ACTIVE → BANNED (admin action, final state)
- ACTIVE → DELETED (soft delete)
- SUSPENDED → DELETED (soft delete)

### 3.2 Membership Lifecycle

```
                    ┌──────────────┐
                    │   INVITED    │ (via invitation flow)
                    │              │
                    │  Can login?  │ NO (blocked at auth)
                    │  Can act?    │ NO
                    │  Status      │ 'invited'
                    │  Expiry      │ 7 days
                    └──────┬───────┘
                           │
                     Invitation Accepted
                           │
                           ▼
                    ┌──────────────┐
                    │   ACTIVE     │
                    │              │
                    │  Can login?  │ YES
                    │  Can act?    │ YES (with role permissions)
                    │  Status      │ 'active'
                    │  joined_at   │ now()
                    └──────┬───────┘
                           │
              ┌────────────┼──────────────┐
              │            │              │
              ▼            ▼              ▼
    ┌──────────────┐ ┌──────────┐ ┌──────────────┐
    │  SUSPENDED   │ │ REMOVED  │ │   ACTIVE     │
    │              │ │          │ │ (role change) │
    │ Can login?   │ │ Can      │ │              │
    │ NO           │ │ login?   │ │ Can login?   │ YES
    │ Status:      │ │ NO       │ │ Permissions  │ CHANGED
    │ 'suspended'  │ │          │ │              │
    └──────────────┘ └──────────┘ └──────────────┘
```

**State transitions:**
- INVITED → ACTIVE (accepted)
- INVITED → EXPIRED (7 days passed, no state change — checked on acceptance attempt)
- ACTIVE → SUSPENDED (owner/admin action)
- SUSPENDED → ACTIVE (owner/admin action)
- ACTIVE → REMOVED (owner/admin action, final state for active membership)
- REMOVED is a terminal state (prevents future login)

### 3.3 Owner Transfer Lifecycle

```
                    ┌──────────────────┐
                    │   CURRENT OWNER  │ (is_owner = true)
                    │                  │
                    │  Initiates       │ POST /transfer-ownership
                    │  transfer        │
                    └────────┬─────────┘
                             │
                             ▼
                    ┌──────────────────┐
                    │   TRANSFER       │ 
                    │   INITIATED      │
                    │                  │
                    │  Audit log       │ ownership_transfer_initiated
                    │  Notification    │ to current owner (confirm)
                    │                  │
                    │  Pending         │ owner confirmation
                    │  confirmation    │ (within session)
                    └────────┬─────────┘
                             │
               ┌─────────────┴─────────────┐
               │                           │
               ▼                           ▼
    ┌──────────────────┐       ┌──────────────────┐
    │   CONFIRMED      │       │   CANCELLED      │
    │                  │       │                  │
    │ Transaction:     │       │ No DB changes    │
    │                  │       │ Audit log:       │
    │ Current:         │       │ cancelled        │
    │ is_owner = false │       │                  │
    │                  │       └──────────────────┘
    │ Target:          │
    │ is_owner = true  │
    │                  │
    │ Audit log:       │
    │ completed        │
    │                  │
    │ Notify both      │
    │ parties          │
    └──────────────────┘
```

### 3.4 Combined Registration Flow (Merchant)

```
Visitor                    System                    Database
   │                          │                         │
   │  POST /create-store       │                         │
   │─────────────────────────►│                         │
   │                          │                         │
   │                          │  BEGIN TRANSACTION      │
   │                          │────────────────────────►│
   │                          │                         │
   │                          │  Create Tenant          │
   │                          │────────────────────────►│ tenants.insert()
   │                          │                         │
   │                          │  Create Account         │
   │                          │────────────────────────►│ accounts.insert()
   │                          │                         │
   │                          │  Create Roles           │
   │                          │  (admin, customer)      │
   │                          │────────────────────────►│ roles.insert(x2)
   │                          │                         │
   │                          │  Create Membership      │
   │                          │  (owner, admin role)    │
   │                          │────────────────────────►│ tm.insert()
   │                          │                         │
   │                          │  Create MerchantProfile │
   │                          │────────────────────────►│ mp.insert()
   │                          │                         │
   │                          │  Create Subscription    │
   │                          │  (trialing or pending)  │
   │                          │────────────────────────►│ subscriptions.insert()
   │                          │                         │
   │                          │  COMMIT                 │
   │                          │────────────────────────►│
   │                          │                         │
   │  ─── Event: Account      │                         │
   │  Created ───────────────►│                         │
   │                          │                         │
   │  ─── Event: Store        │                         │
   │  Created ───────────────►│                         │
   │                          │                         │
   │  ─── Event: Membership   │                         │
   │  Created ───────────────►│                         │
   │                          │                         │
   │  Log in automatically    │                         │
   │◄─────────────────────────│                         │
   │                          │                         │
   │  Redirect to onboarding  │                         │
   │◄─────────────────────────│                         │
```

### 3.5 Combined Registration Flow (Customer)

```
Visitor                    System                    Database
   │                          │                         │
   │  POST /store/{slug}/     │                         │
   │  register                │                         │
   │─────────────────────────►│                         │
   │                          │                         │
   │  ─── Event: Pre-validate │                         │
   │  tenant active,          │                         │
   │  registration allowed    │                         │
   │                          │                         │
   │  Find or Create Account  │                         │
   │  by email                │                         │
   │                          │                         │
   │  ─── Event: Account      │                         │
   │  Created (if new) ──────►│                         │
   │                          │                         │
   │  BEGIN TRANSACTION       │                         │
   │─────────────────────────►│                         │
   │                          │                         │
   │  Create Membership       │                         │
   │  (customer role,         │                         │
   │   is_owner = false)      │                         │
   │─────────────────────────►│ tm.insert()             │
   │                          │                         │
   │  Create CustomerProfile  │                         │
   │─────────────────────────►│ cp.insert()             │
   │                          │                         │
   │  COMMIT                  │                         │
   │─────────────────────────►│                         │
   │                          │                         │
   │  ─── Event: Membership   │                         │
   │  Created ───────────────►│                         │
   │                          │                         │
   │  Log in automatically    │                         │
   │◄─────────────────────────│                         │
   │                          │                         │
   │  Redirect to storefront  │                         │
   │◄─────────────────────────│                         │
```

### 3.6 Invitation Flow

```
Inviter (Owner)             System                   Invitee (Target)          Database
      │                        │                           │                      │
      │  POST /staff/invite    │                           │                      │
      │───────────────────────►│                           │                      │
      │                        │  Validate:                │                      │
      │                        │  - owner or has           │                      │
      │                        │    users.invite           │                      │
      │                        │  - email not already      │                      │
      │                        │    member                 │                      │
      │                        │                           │                      │
      │                        │  Find or Create Account   │                      │
      │                        │  by email                 │                      │
      │                        │──────────────────────────►│ accounts.insert()    │
      │                        │  (if new)                 │                      │
      │                        │                           │                      │
      │                        │  BEGIN TRANSACTION        │                      │
      │                        │──────────────────────────►│                      │
      │                        │                           │                      │
      │                        │  ─── Event: Invitation    │                      │
      │                        │  Sent                     │                      │
      │                        │                           │                      │
      │                        │  Create Membership        │                      │
      │                        │  (status = 'invited')     │                      │
      │                        │──────────────────────────►│ tm.insert()          │
      │                        │                           │                      │
      │                        │  COMMIT                   │                      │
      │                        │──────────────────────────►│                      │
      │                        │                           │                      │
      │  "Invitation sent"     │                           │                      │
      │◄───────────────────────│                           │                      │
      │                        │                           │                      │
      │                        │  Send notification        │                      │
      │                        │  (signed URL)            │                      │
      │                        │──────────────────────────►│                      │
      │                        │                           │                      │
      │                        │                           │  Click link          │
      │                        │                           │◄─────────────────────│
      │                        │                           │                      │
      │                        │◄──────────────────────────│  GET /invitations/   │
      │                        │                           │  {id}/accept         │
      │                        │                           │                      │
      │                        │  Validate:                │                      │
      │                        │  - signed URL valid       │                      │
      │                        │  - not expired            │                      │
      │                        │  - Account matches        │                      │
      │                        │                           │                      │
      │                        │  Update Membership        │                      │
      │                        │  (status = 'active',      │                      │
      │                        │   joined_at = now())      │                      │
      │                        │──────────────────────────►│ tm.update()          │
      │                        │                           │                      │
      │                        │  ─── Event: Invitation    │                      │
      │                        │  Accepted ───────────────►│                      │
      │                        │                           │                      │
      │  ─── Event: Notify     │                           │                      │
      │  Inviter ─────────────►│                           │                      │
      │                        │                           │                      │
      │                        │  Redirect to dashboard    │                      │
      │                        │──────────────────────────►│                      │
```

### 3.7 Login Flow (Store-Scoped)

```
User                           System                        Database
 │                               │                              │
 │  POST /store/{slug}/admin/    │                              │
 │  login                        │                              │
 │──────────────────────────────►│                              │
 │                               │  IdentifyTenant middleware   │
 │                               │  Resolve tenant from slug   │
 │                               │─────────────────────────────►│ tenants: select
 │                               │                              │
 │                               │  Authenticate via Account    │
 │                               │  (email + password)          │
 │                               │─────────────────────────────►│ accounts: select
 │                               │                              │
 │                               │  Check Account.status        │
 │                               │  ('active' required)         │
 │                               │                              │
 │                               │  Resolve Membership          │
 │                               │  (account_id + tenant_id)    │
 │                               │─────────────────────────────►│ tm: select
 │                               │                              │
 │                               │  Check Membership.status     │
 │                               │  ('active' required)         │
 │                               │                              │
 │                               │  BEGIN TRANSACTION           │
 │                               │                              │
 │                               │  Regenerate session          │
 │                               │  Set account_id,             │
 │                               │  membership_id in session    │
 │                               │                              │
 │                               │  Update last_login_at,       │
 │                               │  last_login_ip               │
 │                               │─────────────────────────────►│ accounts: update
 │                               │                              │
 │                               │  Insert session record       │
 │                               │─────────────────────────────►│ sessions: insert
 │                               │                              │
 │                               │  COMMIT                      │
 │                               │                              │
 │                               │  ─── Event: Login            │
 │                               │                              │
 │                               │  Write audit log             │
 │                               │  (login success, IP,         │
 │                               │   user_agent, tenant_id)     │
 │                               │─────────────────────────────►│ activity_log: insert
 │                               │                              │
 │  Redirect to admin dashboard  │                              │
 │◄──────────────────────────────│                              │
```

---

## 4. Event Specifications

---

### 4.1 Account Created

**Purpose:** A new natural person registers on the platform or is discovered via invitation.

**Trigger:** Registration form submission, invitation acceptance (for new emails), OAuth callback (future).

**Source:** `RegisteredUserController` (customer), `CreateStoreController` (merchant), `InvitationController` (invitation).

**Target:** `accounts` table — new row inserted.

**Preconditions:**
- Email is globally unique in `accounts` table
- Password meets minimum requirements (min 8 chars)
- If via invitation: invitation record exists with matching email
- If via merchant registration: tenant slug is unique

**Postconditions:**
- `accounts` table has 1 new row
- `email_verified_at` is NULL (email verification required)
- `status` is `'active'`
- If part of merchant registration: Tenant, TenantMembership, and MerchantProfile also created
- If part of customer registration: TenantMembership and CustomerProfile also created

**Database Impact:**
| Table | Action | Rows |
|---|---|---|
| `accounts` | INSERT | 1 |

**Services Triggered:**
- `TenantBootstrapService` (if merchant registration)
- `InvitationService` (if via invitation)

**Notifications Triggered:**
- Email verification notification (if `MustVerifyEmail` enabled)
- Welcome email (future)

**Audit Logs:**
| Event Type | Actor | Target | Detail |
|---|---|---|---|
| `account_created` | self | Account ID | email, source (merchant/customer/invitation) |

**Future Email:** Welcome email, platform introduction series.

**Future Webhook:** `account.created` — platform notification, third-party CRM sync.

**Future Queue:** Account verification reminder job (if email not verified within 24h).

**Rollback Strategy:** Delete the Account record. If part of a tenant creation, also delete Tenant + Membership records (CASCADE handles FK relationships).

**Failure Handling:**
| Failure | Action |
|---|---|
| Email already exists | Validation error: "The email has already been taken." |
| Tenant slug taken | Validation error: "This store name is already in use." |
| Password too weak | Validation error: minimum requirements message |
| Database constraint violation | Transaction rollback. User sees generic error. |

---

### 4.2 Merchant Registration

**Purpose:** A new merchant creates a store on the platform. This is the primary acquisition flow.

**Trigger:** `POST /create-store` form submission.

**Source:** `CreateStoreController`.

**Target:** Multiple tables — Tenant, Account, TenantMembership, Roles, MerchantProfile, Subscription.

**Preconditions:**
- Tenant slug is globally unique
- Account email is globally unique
- Password meets minimum requirements
- Platform allows new store registrations (not in maintenance mode)

**Postconditions:**
- Tenant created with `status = 'trialing'` or `'pending'`
- Account created with `status = 'active'`
- 2 roles created (admin, customer) scoped to this tenant
- TenantMembership created with `is_owner = true`, `role_id = admin role`, `status = 'active'`
- MerchantProfile created with business name from tenant data
- Subscription created with trial or pending status
- User is authenticated and redirected to onboarding

**Database Impact:**
| Table | Action | Rows |
|---|---|---|
| `tenants` | INSERT | 1 |
| `accounts` | INSERT | 1 |
| `roles` | INSERT | 2 |
| `tenant_memberships` | INSERT | 1 |
| `merchant_profiles` | INSERT | 1 |
| `subscriptions` | INSERT | 1 |

**Services Triggered:**
- `TenantBootstrapService::bootstrap()` — orchestrates the entire flow
- `SubscriptionLifecycleService` — creates trial/pending subscription

**Notifications Triggered:**
- Email verification notification (to new Account)
- Welcome email (future)

**Audit Logs:**
| Event Type | Actor | Target | Detail |
|---|---|---|---|
| `merchant_registered` | self | Account ID | tenant_id, tenant_slug |
| `tenant_created` | self | Tenant ID | tenant_name, tenant_slug |
| `store_created` | self | Tenant ID | — |

**Future Email:** Onboarding series, getting started guide.

**Future Webhook:** `store.created` — platform notification, third-party integration triggers.

**Future Queue:** Tenant provisioning tasks (default data creation, DNS setup, CDN purge).

**Rollback Strategy:** Delete the Tenant record (CASCADE deletes roles, memberships, subscription). Delete the Account record. User remains unauthenticated.

**Failure Handling:**
| Failure | Action |
|---|---|
| Tenant slug taken | Transaction rollback. Validation error. |
| Email duplicate | Transaction rollback. Validation error. |
| Subscription creation fails | Tenant and Account exist. Subscription marked as pending. Admin notified manually. |
| Role creation fails | Transaction rollback. Database constraint error. |

---

### 4.3 Customer Registration

**Purpose:** A new customer registers in a specific store.

**Trigger:** `POST /store/{slug}/register` form submission.

**Source:** `RegisteredUserController`.

**Target:** `accounts` (maybe INSERT), `tenant_memberships` (INSERT), `customer_profiles` (INSERT).

**Preconditions:**
- Tenant exists and is active
- Store allows public registration (`WebsiteInfo.allow_registration = true`)
- Email is globally unique in `accounts` (if new Account) OR Account exists with active status
- Account does not already have an active membership in this tenant

**Postconditions:**
- If new email: Account created with `status = 'active'`
- If existing email: Account found (no new row)
- TenantMembership created with `role_id = customer role`, `is_owner = false`, `status = 'active'`
- CustomerProfile created with submitted name
- User is authenticated as that Account in this membership context

**Database Impact:**
| Table | Action | Rows |
|---|---|---|
| `accounts` | INSERT (if new) | 0-1 |
| `tenant_memberships` | INSERT | 1 |
| `customer_profiles` | INSERT | 1 |

**Services Triggered:**
- `TenantBootstrapService::ensureCustomerRole()` — ensures customer role exists for this tenant

**Notifications Triggered:**
- Email verification notification (if new Account and MustVerifyEmail enabled)
- Welcome to store notification (future)

**Audit Logs:**
| Event Type | Actor | Target | Detail |
|---|---|---|---|
| `customer_registered` | self | Account ID | tenant_id, membership_id |

**Future Email:** Order confirmations, promotional emails (with consent).

**Future Webhook:** `customer.created` — webhook to merchant's integrated systems (CRM, marketing).

**Future Queue:** Customer welcome sequence, abandoned cart tracking setup.

**Rollback Strategy:** Delete the CustomerProfile, delete the TenantMembership. If Account was just created, delete it too. User is not authenticated.

**Failure Handling:**
| Failure | Action |
|---|---|
| Email already in tenant | Validation error: "An account with this email already exists in this store." |
| Account suspended | Validation error: "This account cannot register." |
| Registration disabled | Validation error: "Registration is currently closed." |
| Tenant inactive | Validation error: "This store is not available." |

---

### 4.4 Store Creation

**Purpose:** An existing Account creates a second (or more) store on the platform.

**Trigger:** `POST /create-store` by an already authenticated Account.

**Source:** `CreateStoreController`.

**Target:** Same as Merchant Registration but reuses existing Account.

**Preconditions:**
- Account is authenticated and active
- Tenant slug is globally unique
- Account does not already own a store with the same slug (allowed to own multiple)

**Postconditions:**
- Tenant created with `status = 'trialing'`
- No new Account (existing Account used)
- 2 roles created (admin, customer) scoped to this tenant
- TenantMembership created with `is_owner = true`, `role_id = admin role`
- MerchantProfile created
- Subscription created
- User has multiple memberships now — can switch between stores

**Database Impact:**
| Table | Action | Rows |
|---|---|---|
| `tenants` | INSERT | 1 |
| `roles` | INSERT | 2 |
| `tenant_memberships` | INSERT | 1 |
| `merchant_profiles` | INSERT | 1 |
| `subscriptions` | INSERT | 1 |

**Services Triggered:**
- `TenantBootstrapService::bootstrap()` with `create_owner = false` option (Account already exists)

**Notifications Triggered:**
- None directly (Account already exists, no verification needed)

**Audit Logs:**
| Event Type | Actor | Target | Detail |
|---|---|---|---|
| `store_created` | Account ID | Tenant ID | account already had N memberships |

**Future Email:** None (existing user).

**Future Webhook:** `store.created` — same as Merchant Registration.

**Future Queue:** Same as Merchant Registration.

**Rollback Strategy:** Delete the Tenant record (CASCADE). Existing Account is unaffected.

**Failure Handling:**
| Failure | Action |
|---|---|
| Slug taken | Validation error. Transaction rollback. |
| Account suspended | Validation error: "Your account cannot create new stores." |
| Subscription creation fails | Tenant exists without subscription. Log warning. Manual intervention. |

---

### 4.5 Membership Created

**Purpose:** An Account is linked to a Tenant with a Role. This is the core relationship event.

**Trigger:** Customer registration, merchant registration, staff invitation, migration script.

**Source:** Various controllers, `TenantBootstrapService`, `InvitationService`.

**Target:** `tenant_memberships` table — new row inserted.

**Preconditions:**
- Account exists and is active (or is being created in same transaction)
- Tenant exists and is active
- Role exists and belongs to the same tenant
- No existing membership for (account_id, tenant_id) — UNIQUE constraint
- If `is_owner = true`: no other owner exists for this tenant

**Postconditions:**
- `tenant_memberships` has 1 new row
- `is_owner`, `status`, `role_id` are set according to the flow
- If `status = 'active'`: Account can now access this tenant
- If `status = 'invited'`: Account cannot access until accepted

**Database Impact:**
| Table | Action | Rows |
|---|---|---|
| `tenant_memberships` | INSERT | 1 |

**Services Triggered:**
- `MembershipResolutionService` — cache/membership context updated

**Notifications Triggered:**
- None directly (the parent flow sends notifications)

**Audit Logs:**
| Event Type | Actor | Target | Detail |
|---|---|---|---|
| `membership_created` | Account ID (creator) | Membership ID | account_id, tenant_id, role_id, is_owner, status |

**Future Email:** Depends on parent flow (invitation email, welcome email).

**Future Webhook:** `membership.created` — tenant activity feed, audit systems.

**Future Queue:** Membership provisioning tasks (if any).

**Rollback Strategy:** Delete the membership record. Parent flow handles related cleanup.

**Failure Handling:**
| Failure | Action |
|---|---|
| Duplicate membership | UNIQUE constraint violation. Rollback. Validation error. |
| Role not found | FK constraint violation. Rollback. Create role first. |
| Tenant not found | FK constraint violation. Rollback. Parent flow error. |

---

### 4.6 Membership Activated

**Purpose:** An invited membership becomes active. Usually triggered by invitation acceptance.

**Trigger:** Invitation acceptance from signed URL.

**Source:** `InvitationController::accept()`.

**Target:** `tenant_memberships` — UPDATE status from `'invited'` to `'active'`, SET `joined_at = now()`.

**Preconditions:**
- Membership exists with `status = 'invited'`
- Signed URL is valid (not expired, signature matches)
- Authenticated Account matches the membership's `account_id`
- Account status is `'active'`

**Postconditions:**
- `membership.status = 'active'`
- `membership.joined_at = now()`
- Account can now access this tenant with the assigned role

**Database Impact:**
| Table | Action | Rows |
|---|---|---|
| `tenant_memberships` | UPDATE | 1 |

**Services Triggered:**
- `InvitationService::accept()`

**Notifications Triggered:**
- To the inviter: "Your invitation to {email} has been accepted."

**Audit Logs:**
| Event Type | Actor | Target | Detail |
|---|---|---|---|
| `membership_activated` | Account ID (acceptor) | Membership ID | was 'invited', now 'active' |

**Future Email:** — (same as notification above).

**Future Webhook:** `membership.activated` — tenant activity feed.

**Future Queue:** — (notification already sent synchronously or via queue).

**Rollback Strategy:** Revert status to `'invited'`, clear `joined_at`.

**Failure Handling:**
| Failure | Action |
|---|---|
| URL expired | Error: "This invitation has expired." |
| Signature mismatch | Error: "Invalid invitation link." |
| Wrong account | Error: "This invitation was sent to a different email." |
| Account suspended | Error: "Your account cannot accept invitations." |

---

### 4.7 Membership Suspended

**Purpose:** A member is temporarily blocked from accessing a tenant. Account-level access to other tenants is unaffected.

**Trigger:** Owner action via staff management UI, automated action (payment failure).

**Source:** Staff management controller, `SubscriptionExpiryService` (automated).

**Target:** `tenant_memberships` — UPDATE status from `'active'` to `'suspended'`.

**Preconditions:**
- Actor has authority: membership is owner OR has `users.manage` permission
- Target membership exists with `status = 'active'`
- Target membership is NOT `is_owner = true` (owner cannot be suspended by anyone except SuperAdmin)

**Postconditions:**
- `membership.status = 'suspended'`
- Account cannot authenticate or access this tenant
- Account retains access to other tenants
- Suspension is reversible (can be set back to `'active'`)

**Database Impact:**
| Table | Action | Rows |
|---|---|---|
| `tenant_memberships` | UPDATE | 1 |

**Services Triggered:**
- Session revocation for this tenant (remove `current_tenant_membership_id` from active sessions)

**Notifications Triggered:**
- To the suspended member: "Your access to {store} has been suspended."

**Audit Logs:**
| Event Type | Actor | Target | Detail |
|---|---|---|---|
| `membership_suspended` | Actor Account ID | Target Membership ID | reason, initiated_by |

**Future Email:** Same as notification.

**Future Webhook:** `membership.suspended` — tenant activity feed, audit trail.

**Future Queue:** Session cleanup job.

**Rollback Strategy:** Revert status to `'active'`. Re-activate sessions.

**Failure Handling:**
| Failure | Action |
|---|---|
| Target is owner | Error: "The store owner cannot be suspended. Transfer ownership first." |
| Already suspended | No-op. Success response. |
| Actor lacks permission | 403 Forbidden. |

---

### 4.8 Membership Removed

**Purpose:** A member is permanently removed from a tenant. Terminal state — irreversible.

**Trigger:** Owner action via staff management UI.

**Source:** Staff management controller.

**Target:** `tenant_memberships` — UPDATE status from `'active'` to `'removed'`.

**Preconditions:**
- Actor is owner or has `users.manage` permission
- Target membership exists with `status = 'active'`
- Target membership is NOT `is_owner = true` (owner cannot be removed)
- Target membership cannot be the last admin in the tenant (must leave at least one admin member)

**Postconditions:**
- `membership.status = 'removed'`
- Account permanently cannot access this tenant
- Membership record retained for audit purposes (soft-delete NOT used)
- All sessions for this Account with this tenant context are invalidated

**Database Impact:**
| Table | Action | Rows |
|---|---|---|
| `tenant_memberships` | UPDATE | 1 |

**Services Triggered:**
- Session revocation for this tenant context

**Notifications Triggered:**
- To the removed member: "You have been removed from {store}."

**Audit Logs:**
| Event Type | Actor | Target | Detail |
|---|---|---|---|
| `membership_removed` | Actor Account ID | Target Membership ID | reason, last_role |

**Future Email:** Same as notification.

**Future Webhook:** `membership.removed` — tenant activity feed.

**Future Queue:** Session cleanup job.

**Rollback Strategy:** NOT REVERSIBLE by design. Membership removal is terminal. If a member needs re-access, a new invitation must be sent and accepted.

**Failure Handling:**
| Failure | Action |
|---|---|
| Target is owner | Error: "Transfer ownership before removing." |
| Last admin check fails | Error: "Cannot remove the last admin. Promote another member first." |
| Actor lacks permission | 403 Forbidden. |

---

### 4.9 Invitation Sent

**Purpose:** A staff invitation is sent to an email address. The recipient may or may not already have an Account.

**Trigger:** Owner/staff member submits invitation form.

**Source:** `InvitationController::invite()`.

**Target:** `tenant_memberships` — INSERT with `status = 'invited'`.

**Preconditions:**
- Actor has `users.invite` permission (or is owner)
- Email format is valid
- Email does not already have an active membership in this tenant
- Tenant has available staff slots (plan-based limit)

**Postconditions:**
- If email belongs to existing Account: Account found, no new Account created
- If email is new: Account created with `status = 'active'` (or pending verification)
- TenantMembership created with `status = 'invited'`, `invited_by = actor ID`, `invited_at = now()`
- Signed URL generated with 7-day expiry

**Database Impact:**
| Table | Action | Rows |
|---|---|---|
| `accounts` | INSERT (if new) | 0-1 |
| `tenant_memberships` | INSERT | 1 |

**Services Triggered:**
- `InvitationService::send()`

**Notifications Triggered:**
- To invitee (existing Account): "You've been invited to join {store} as {role}."
- To invitee (new email): "You've been invited to join {store}. Create your account to accept."

**Audit Logs:**
| Event Type | Actor | Target | Detail |
|---|---|---|---|
| `invitation_sent` | Actor Account ID | Membership ID | target_email, role_id, invited_by |

**Future Email:** Invitation reminder (if not accepted within 3 days) — future queue job.

**Future Webhook:** `invitation.sent` — tenant activity feed.

**Future Queue:** Invitation expiry check job (runs daily, checks invitations older than 7 days).

**Rollback Strategy:** Delete the membership record (and new Account if created).

**Failure Handling:**
| Failure | Action |
|---|---|
| Email already a member | Validation error: "This user is already a member of this store." |
| Staff slot limit reached | Validation error: "Your plan does not allow additional staff members." |
| Invalid email | Validation error: "Please enter a valid email address." |

---

### 4.10 Invitation Accepted

**Purpose:** The recipient accepts the invitation and gains access to the tenant.

**Trigger:** Clicking the signed URL in the invitation notification.

**Source:** `InvitationController::accept()`.

**Target:** `tenant_memberships` — UPDATE status from `'invited'` to `'active'`, SET `joined_at = now()`.

**Preconditions:**
- Signed URL is valid (not expired, HMAC signature matches)
- Membership exists with `status = 'invited'`
- Membership's `invited_at` is within 7 days
- Authenticated Account ID matches the membership's `account_id`
- Account status is `'active'`

**Postconditions:**
- `membership.status = 'active'`
- `membership.joined_at = now()`
- Account can now access this tenant with the assigned role
- If Account was just created (new registration via invitation): email verification also triggered

**Database Impact:**
| Table | Action | Rows |
|---|---|---|
| `tenant_memberships` | UPDATE | 1 |

**Services Triggered:**
- `InvitationService::accept()`

**Notifications Triggered:**
- To the inviter: "{email} has accepted your invitation to join {store}."

**Audit Logs:**
| Event Type | Actor | Target | Detail |
|---|---|---|---|
| `invitation_accepted` | Acceptor Account ID | Membership ID | invited_by, days_since_invited |

**Future Email:** — (notification sent).

**Future Webhook:** `invitation.accepted` — tenant activity feed.

**Future Queue:** — (notification sent synchronously or via queue).

**Rollback Strategy:** Revert status to `'invited'`, clear `joined_at`.

**Failure Handling:**
| Failure | Action |
|---|---|
| URL expired | Error: "This invitation has expired. Ask the store owner to send a new invitation." |
| Wrong Account | Error: "This invitation was sent to a different email address." |
| Account suspended | Error: "Your account cannot accept invitations." |
| Membership not found | Error: "This invitation is no longer valid." |

---

### 4.11 Invitation Rejected

**Purpose:** The recipient explicitly declines the invitation.

**Trigger:** Recipient clicks "Decline" link in notification email or invitation management page.

**Source:** `InvitationController::reject()`.

**Target:** `tenant_memberships` — DELETE or UPDATE status to `'removed'`.

**Preconditions:**
- Membership exists with `status = 'invited'`
- Authenticated Account matches the membership's `account_id`

**Postconditions:**
- `membership.status = 'declined'` (or record soft-deleted)
- No further access possible through this invitation
- Inviter is notified

**Database Impact:**
| Table | Action | Rows |
|---|---|---|
| `tenant_memberships` | UPDATE (status = 'declined') | 1 |

**Services Triggered:**
- `InvitationService::reject()`

**Notifications Triggered:**
- To the inviter: "{email} has declined your invitation to join {store}."

**Audit Logs:**
| Event Type | Actor | Target | Detail |
|---|---|---|---|
| `invitation_declined` | Acceptor Account ID | Membership ID | — |

**Future Email:** — (notification sent).

**Future Webhook:** `invitation.declined` — tenant activity feed.

**Future Queue:** —.

**Rollback Strategy:** Revert status to `'invited'`. Not recommended — rejection is user intent.

**Failure Handling:**
| Failure | Action |
|---|---|
| Already accepted | Error: "This invitation has already been accepted." |
| Already expired | Error: "This invitation has expired." |

---

### 4.12 Invitation Expired

**Purpose:** An invitation reaches its 7-day expiry. The membership transitions to an expired state.

**Trigger:** Scheduled job (daily cron) or on-demand check at acceptance attempt.

**Source:** Console command `invitations:process-expired` (or checked at acceptance).

**Target:** `tenant_memberships` — UPDATE status from `'invited'` to `'expired'` (or leave as `'invited'` with expired flag).

**Preconditions:**
- Membership exists with `status = 'invited'`
- `invited_at + 7 days < now()`

**Postconditions:**
- Membership is no longer acceptable
- If status changed to `'expired'`: record marked as expired
- If status kept as `'invited'`: acceptance check rejects based on date comparison

**Database Impact:**
| Table | Action | Rows |
|---|---|---|
| `tenant_memberships` | UPDATE (optional) | N |

**Services Triggered:**
- `InvitationService::processExpired()`

**Notifications Triggered:**
- To the inviter: "The invitation for {email} to join {store} has expired."

**Audit Logs:**
| Event Type | Actor | Target | Detail |
|---|---|---|---|
| `invitation_expired` | system | Membership ID | invited_at, expired_at |

**Future Email:** Same as notification.

**Future Webhook:** `invitation.expired` — tenant activity feed.

**Future Queue:** Cleanup job to archive expired invitations older than 90 days.

**Rollback Strategy:** Revert status to `'invited'`, extend `invited_at`. Manual only.

**Failure Handling:**
| Failure | Action |
|---|---|
| Already accepted | No-op. Skip. |
| Already expired | No-op. Skip. |

---

### 4.13 Owner Transfer Started

**Purpose:** The current owner initiates an ownership transfer to another active member.

**Trigger:** Owner submits ownership transfer form with target email.

**Source:** `OwnershipTransferController::initiate()`.

**Target:** No database mutation yet. Audit log only at this stage.

**Preconditions:**
- Current membership has `is_owner = true`
- Target Account exists and has active membership in this tenant
- Target membership status is `'active'`
- Target Account is not the current owner

**Postconditions:**
- Transfer initiation logged in audit trail
- Confirmation prompt shown to current owner (in-app)
- Transfer is NOT yet executed — pending confirmation

**Database Impact:**
| Table | Action | Rows |
|---|---|---|
| (none) | — | 0 |

**Services Triggered:**
- `OwnershipTransferService::initiate()`

**Notifications Triggered:**
- To current owner: "Are you sure you want to transfer ownership of {store} to {target_email}?" (in-app confirmation)

**Audit Logs:**
| Event Type | Actor | Target | Detail |
|---|---|---|---|
| `owner_transfer_initiated` | Current owner Account ID | Tenant ID | target_account_id, target_email |

**Future Email:** — (in-app confirmation first).

**Future Webhook:** `ownership.transfer_initiated` — tenant audit trail.

**Future Queue:** —.

**Rollback Strategy:** No mutation to roll back. Cancel the transfer (return to previous state).

**Failure Handling:**
| Failure | Action |
|---|---|
| Not owner | 403 Forbidden. |
| Target not found | Validation error: "No member found with this email." |
| Target is owner | Validation error: "This user is already the owner." |
| Target suspended | Validation error: "Cannot transfer ownership to a suspended member." |

---

### 4.14 Owner Transfer Completed

**Purpose:** The ownership transfer is confirmed and executed.

**Trigger:** Current owner confirms the transfer (POST with confirmation token).

**Source:** `OwnershipTransferController::confirm()`.

**Target:** `tenant_memberships` — UPDATE two rows: previous owner sets `is_owner = false`, target sets `is_owner = true`.

**Preconditions:**
- Transfer was previously initiated (audit log exists)
- Current owner is still authenticated and still has `is_owner = true`
- Target membership still exists and is still `'active'`
- Tenant is not locked or suspended

**Postconditions:**
- Previous owner's membership: `is_owner = false` (role unchanged, still `admin`)
- Target's membership: `is_owner = true` (role unchanged)
- Previous owner loses owner-exclusive abilities (billing, subscription, staff management, deletion)
- Previous owner retains role-based abilities (product, order management)
- New owner gains billing ownership
- Payment methods from previous owner dissociated from tenant (if linked to Account)

**Database Impact:**
| Table | Action | Rows |
|---|---|---|
| `tenant_memberships` | UPDATE (previous owner) | 1 |
| `tenant_memberships` | UPDATE (new owner) | 1 |

**Services Triggered:**
- `OwnershipTransferService::complete()`

**Notifications Triggered:**
- To previous owner: "You have transferred ownership of {store} to {target_email}."
- To new owner: "You are now the owner of {store}."

**Audit Logs:**
| Event Type | Actor | Target | Detail |
|---|---|---|---|
| `owner_transfer_completed` | Previous owner Account ID | Tenant ID | previous_owner_id, new_owner_id |

**Future Email:** Same as notifications.

**Future Webhook:** `ownership.transferred` — tenant audit trail, billing system notification.

**Future Queue:** Payment method dissociation job (cleanup previous owner's payment methods).

**Rollback Strategy:** REVERSE the transfer: set previous owner back to `is_owner = true`, set new owner back to `is_owner = false`. This is an administrative operation, not exposed to users.

**Failure Handling:**
| Failure | Action |
|---|---|
| Owner changed since initiation | Validation error: "Ownership has changed since this transfer was started." |
| Target no longer active | Transaction rollback. Error: "The target member is no longer active." |
| Tenant locked | Transaction rollback. Error: "This store is currently locked." |

---

### 4.15 Role Changed

**Purpose:** A member's role in a tenant is changed, affecting their permissions.

**Trigger:** Owner/staff management UI — role dropdown change.

**Source:** Staff/Role management controller.

**Target:** `tenant_memberships` — UPDATE `role_id` to new role.

**Preconditions:**
- Actor has authority: membership is owner OR has `users.manage` permission
- Target membership exists and is `'active'`
- New role belongs to the same tenant
- Target membership is NOT the owner being demoted (owner role change is handled by ownership transfer)

**Postconditions:**
- `membership.role_id` updated to new role
- Member's effective permissions change immediately
- Gate::before() checks against new role on next request
- Spatie permission cache flushed for this tenant if cached

**Database Impact:**
| Table | Action | Rows |
|---|---|---|
| `tenant_memberships` | UPDATE (role_id) | 1 |

**Services Triggered:**
- None specific. Authorization system picks up new role on next Gate::before() call.

**Notifications Triggered:**
- To the member: "Your role in {store} has been changed to {new_role}."

**Audit Logs:**
| Event Type | Actor | Target | Detail |
|---|---|---|---|
| `role_changed` | Actor Account ID | Membership ID | old_role_id, new_role_id, old_role_name, new_role_name |

**Future Email:** Same as notification.

**Future Webhook:** `membership.role_changed` — tenant activity feed, audit trail.

**Future Queue:** Permission cache flush job.

**Rollback Strategy:** Revert `role_id` to previous value.

**Failure Handling:**
| Failure | Action |
|---|---|
| Target is owner | Error: "Use ownership transfer to change the owner's role." |
| New role not found | FK constraint. Error: "Role not found." |
| Actor lacks permission | 403 Forbidden. |

---

### 4.16 Permission Changed

**Purpose:** A role's permissions are modified (add/remove). Affects all memberships with that role.

**Trigger:** SuperAdmin or authorized admin updates role permissions.

**Source:** `PermissionController` or `RoleController`.

**Target:** `role_has_permissions` — INSERT or DELETE.

**Preconditions:**
- Actor has `roles.manage` permission (SuperAdmin or authorized admin)
- Role exists
- Permission exists
- If removing: the permission is currently assigned to the role

**Postconditions:**
- Permission added or removed from role
- All memberships with this role gain or lose the permission
- Spatie permission cache flushed
- Change takes effect immediately on next Gate::before() call

**Database Impact:**
| Table | Action | Rows |
|---|---|---|
| `role_has_permissions` | INSERT or DELETE | 1 |

**Services Triggered:**
- Spatie cache flush

**Notifications Triggered:**
- None directly (this is an administrative action, not notified to individual members)

**Audit Logs:**
| Event Type | Actor | Target | Detail |
|---|---|---|---|
| `permission_changed` | Actor Account ID | Role ID | permission_name, action (added/removed) |

**Future Email:** —.

**Future Webhook:** `role.permission_changed` — audit trail.

**Future Queue:** Permission cache flush job (async).

**Rollback Strategy:** Reverse the INSERT/DELETE (remove what was added, add what was removed).

**Failure Handling:**
| Failure | Action |
|---|---|
| Permission not found | Validation error. |
| Role not found | 404. |
| Actor lacks permission | 403 Forbidden. |

---

### 4.17 Password Reset Requested

**Purpose:** A user requests a password reset link.

**Trigger:** `POST /forgot-password` form submission.

**Source:** `PasswordResetLinkController::store()`.

**Target:** `password_reset_tokens` — UPSERT with `account_id` as PK.

**Preconditions:**
- Email is submitted (format validated)
- Account exists with this email (if not, still show success to prevent enumeration)
- Rate limit not exceeded (throttle: 3 per email per hour)

**Postconditions:**
- If Account found: new reset token generated and stored
- If Account not found: no token generated, but same success message shown
- Reset link sent to email

**Database Impact:**
| Table | Action | Rows |
|---|---|---|
| `password_reset_tokens` | UPSERT | 1 |

**Services Triggered:**
- Laravel's `PasswordBroker` (customized for Account model)

**Notifications Triggered:**
- Password reset email with signed URL

**Audit Logs:**
| Event Type | Actor | Target | Detail |
|---|---|---|---|
| `password_reset_requested` | (email only, not authenticated) | Account ID | IP, user_agent |

**Future Email:** Same as notification.

**Future Webhook:** `password.reset_requested` — security audit.

**Future Queue:** —.

**Rollback Strategy:** Delete the reset token.

**Failure Handling:**
| Failure | Action |
|---|---|
| Email not found | Success message shown (no enumeration). No token created. |
| Rate limit exceeded | Throttle error. Try again later. |
| Account suspended | Success message shown (no enumeration). No token created. |

---

### 4.18 Password Changed

**Purpose:** A user successfully resets their password using a reset token.

**Trigger:** `POST /reset-password` form submission with token + email + new password.

**Source:** `NewPasswordController::store()`.

**Target:** `accounts` — UPDATE `password`. `password_reset_tokens` — DELETE token.

**Preconditions:**
- Token exists in `password_reset_tokens` for this `account_id`
- Token is not expired (typically 60 minutes)
- New password meets minimum requirements
- Account status is `'active'`

**Postconditions:**
- `account.password` updated with new bcrypt hash
- Reset token consumed (deleted from table)
- All sessions for this Account revoked (except current)
- Can no longer use old password
- New password works across all tenants

**Database Impact:**
| Table | Action | Rows |
|---|---|---|
| `accounts` | UPDATE (password) | 1 |
| `password_reset_tokens` | DELETE | 1 |
| `sessions` | DELETE (all except current) | N |

**Services Triggered:**
- Session revocation service

**Notifications Triggered:**
- Confirmation email: "Your password has been changed."

**Audit Logs:**
| Event Type | Actor | Target | Detail |
|---|---|---|---|
| `password_changed` | Account ID | Account ID | via_reset (true/false), sessions_revoked_count |

**Future Email:** Same as notification.

**Future Webhook:** `password.changed` — security audit.

**Future Queue:** —.

**Rollback Strategy:** Cannot roll back password change (old password is hashed and not stored). Restore from backup if needed.

**Failure Handling:**
| Failure | Action |
|---|---|
| Invalid/expired token | Validation error: "This password reset token is invalid or has expired." |
| Account suspended | Validation error: "This account cannot change its password." |
| Weak password | Validation error: minimum requirements. |

---

### 4.19 Email Verification

**Purpose:** An Account proves ownership of their email address.

**Trigger:** Clicking the signed verification link in the verification email.

**Source:** `VerifyEmailController`.

**Target:** `accounts` — UPDATE `email_verified_at = now()`.

**Preconditions:**
- Signed URL is valid (`{id}/{hash}` matches Account ID + email HMAC)
- Account exists with this ID
- Account's email matches the hash

**Postconditions:**
- `account.email_verified_at = now()`
- Account can now access protected routes (MustVerifyEmail middleware)
- All current and future memberships benefit from this verification
- Verified badge shown in account settings

**Database Impact:**
| Table | Action | Rows |
|---|---|---|
| `accounts` | UPDATE (email_verified_at) | 1 |

**Services Triggered:**
- None.

**Notifications Triggered:**
- None directly (the verification was triggered from the notification)

**Audit Logs:**
| Event Type | Actor | Target | Detail |
|---|---|---|---|
| `email_verified` | Account ID | Account ID | — |

**Future Email:** —.

**Future Webhook:** `account.email_verified` — security audit.

**Future Queue:** —.

**Rollback Strategy:** Set `email_verified_at` back to NULL. Admin action only.

**Failure Handling:**
| Failure | Action |
|---|---|
| Invalid signature | Error: "Invalid verification link." |
| Already verified | Redirect to already-verified page. |
| Account not found | Error: "Account not found." |

---

### 4.20 Login

**Purpose:** An Account authenticates and creates a session.

**Trigger:** Login form submission (email + password), "Remember me" cookie restoration.

**Source:** `AuthenticatedSessionController::store()`, `StorefrontLoginController`, session re-authentication.

**Target:** `sessions` — INSERT new session. `accounts` — UPDATE `last_login_at`, `last_login_ip`.

**Preconditions:**
- Account exists with the submitted email
- Account status is `'active'`
- Password matches (bcrypt verify)
- If store-scoped: Membership exists with `status = 'active'`
- Rate limit not exceeded (5 attempts per email+IP)

**Postconditions:**
- New session created with `account_id` and `current_tenant_membership_id`
- `account.last_login_at = now()`
- `account.last_login_ip = request IP`
- If "remember me": remember_token rotated and cookie set
- Tenant context resolved and stored in session

**Database Impact:**
| Table | Action | Rows |
|---|---|---|
| `sessions` | INSERT | 1 |
| `accounts` | UPDATE (last_login_at, last_login_ip) | 1 |

**Services Triggered:**
- `MembershipResolutionService` — resolve membership context
- Session regeneration (Laravel default)

**Notifications Triggered:**
- None (login is not notified)
- Future: suspicious login notification (new IP, new device)

**Audit Logs:**
| Event Type | Actor | Target | Detail |
|---|---|---|---|
| `login` | Account ID | Account ID | tenant_id, membership_id, IP, user_agent, remember_me |

**Future Email:** Suspicious login alert (new location, new device).

**Future Webhook:** `account.login` — security audit.

**Future Queue:** Session metadata enrichment (geoip lookup, device fingerprint).

**Rollback Strategy:** Delete the session. No password change needed.

**Failure Handling:**
| Failure | Action |
|---|---|
| Wrong email | Generic "Invalid credentials" (no enumeration). |
| Wrong password | Generic "Invalid credentials". Rate limiter incremented. |
| Account suspended | "Account unavailable." No rate limit increment. |
| Account banned | "Account unavailable." No rate limit increment. |
| Rate limit exceeded | "Too many login attempts. Please try again in {minutes} minutes." |
| No membership in store | "You don't have access to this store." |

---

### 4.21 Logout

**Purpose:** An Account terminates their current session.

**Trigger:** Logout button click, session timeout, password change session revocation.

**Source:** `AuthenticatedSessionController::destroy()`, `NewPasswordController` (revocation).

**Target:** `sessions` — DELETE current session.

**Preconditions:**
- User is authenticated
- (Password change revocation: no precondition — revokes all sessions)

**Postconditions:**
- Current session deleted (or all sessions for password change)
- User redirected to login page or appropriate landing page
- Remember me cookie cleared if present

**Database Impact:**
| Table | Action | Rows |
|---|---|---|
| `sessions` | DELETE | 1 (or N for mass revocation) |

**Services Triggered:**
- Session cleanup

**Notifications Triggered:**
- None.

**Audit Logs:**
| Event Type | Actor | Target | Detail |
|---|---|---|---|
| `logout` | Account ID | Account ID | was_membership_id, session_id |

**Future Email:** —.

**Future Webhook:** `account.logout` — security audit.

**Future Queue:** —.

**Rollback Strategy:** Session is already deleted. User must re-authenticate.

**Failure Handling:**
| Failure | Action |
|---|---|
| Already logged out | No-op. Redirect to login. |
| Session not found | No-op. Redirect to login. |

---

### 4.22 Subscription Activated

**Purpose:** A tenant's subscription becomes active after trial, payment, or manual activation.

**Trigger:** Payment confirmed, trial ends with active payment, SuperAdmin manual activation.

**Source:** Payment gateway callback, `SubscriptionLifecycleService`, SuperAdmin dashboard.

**Target:** `subscriptions` — UPDATE status to `'active'`, SET `starts_at`, `expires_at`.

**Preconditions:**
- Tenant exists
- Subscription exists with status `'trialing'` or `'pending'`
- Payment received or trial period active

**Postconditions:**
- `subscription.status = 'active'`
- `subscription.starts_at = now()` (or trial start date)
- `subscription.expires_at = calculated end date`
- All subscription-gated features enabled
- Tenant access restored (if previously locked)

**Database Impact:**
| Table | Action | Rows |
|---|---|---|
| `subscriptions` | UPDATE | 1 |

**Services Triggered:**
- `FeatureGate::clearCache()` — flush feature gate cache for this tenant
- `SubscriptionLimitService` — recalculate limits

**Notifications Triggered:**
- To owner: "Your {plan_name} subscription is now active."

**Audit Logs:**
| Event Type | Actor | Target | Detail |
|---|---|---|---|
| `subscription_activated` | (system or admin) | Subscription ID | plan_id, billing_interval, amount |

**Future Email:** Same as notification. Invoice email.

**Future Webhook:** `subscription.activated` — billing system, accounting integration.

**Future Queue:** Provisioning tasks (if any plan-specific features need activation).

**Rollback Strategy:** Revert status to previous state. Refund if payment was taken (manual).

**Failure Handling:**
| Failure | Action |
|---|---|
| Subscription not found | 404 error. |
| Already active | No-op. Success response. |
| Plan not found | Error: "Associated plan no longer exists." |

---

### 4.23 Subscription Renewed

**Purpose:** An active subscription is renewed for another billing period.

**Trigger:** Recurring payment success, SuperAdmin manual renewal.

**Source:** Payment gateway webhook, console command `subscriptions:renew` (cron).

**Target:** `subscriptions` — UPDATE `expires_at` to new end date.

**Preconditions:**
- Subscription status is `'active'`
- `expires_at` is in the past (or within the renewal window)
- Payment for the renewal period is successful

**Postconditions:**
- `subscription.expires_at = previous_expires_at + billing_interval`
- Subscription remains `'active'`
- Billing audit log entry created

**Database Impact:**
| Table | Action | Rows |
|---|---|---|
| `subscriptions` | UPDATE (expires_at) | 1 |

**Services Triggered:**
- `SubscriptionLifecycleService::renew()`

**Notifications Triggered:**
- To owner: "Your {plan_name} subscription has been renewed until {date}."

**Audit Logs:**
| Event Type | Actor | Target | Detail |
|---|---|---|---|
| `subscription_renewed` | system | Subscription ID | old_expires_at, new_expires_at, amount |

**Future Email:** Invoice email, receipt.

**Future Webhook:** `subscription.renewed` — billing system, accounting integration.

**Future Queue:** —.

**Rollback Strategy:** Revert `expires_at` to previous value. Manual refund initiation if needed.

**Failure Handling:**
| Failure | Action |
|---|---|
| Payment failed | Subscription status NOT changed. Payment retry queued. Owner notified. |
| Already renewed | No-op. Skip. |
| Tenant locked | Skip renewal. Notify owner. |

---

### 4.24 Subscription Expired

**Purpose:** An active subscription reaches its expiration date without renewal.

**Trigger:** Cron job `subscriptions:process-expired` runs daily, finds subscriptions where `expires_at < now()`.

**Source:** Console command.

**Target:** `subscriptions` — UPDATE status from `'active'` to `'expired'`.

**Preconditions:**
- Subscription status is `'active'`
- `expires_at < now()`
- No successful payment received during grace period (if any)

**Postconditions:**
- `subscription.status = 'expired'`
- Tenant may be locked (depending on plan and grace period settings)
- All subscription-gated features disabled
- Owner notified

**Database Impact:**
| Table | Action | Rows |
|---|---|---|
| `subscriptions` | UPDATE | N |

**Services Triggered:**
- `FeatureGate::clearCache()` — flush cache for affected tenants
- `TenantLockService` — lock tenant (if grace period exceeded)

**Notifications Triggered:**
- To owner: "Your subscription has expired. Renew to restore store access."

**Audit Logs:**
| Event Type | Actor | Target | Detail |
|---|---|---|---|
| `subscription_expired` | system | Subscription ID | tenant_id, grace_period_days, was_plan_id |

**Future Email:** Multiple reminder sequence: 7 days before expiry, 3 days before, day of, 3 days after.

**Future Webhook:** `subscription.expired` — billing system.

**Future Queue:** Grace period expiry job (if grace period configured).

**Rollback Strategy:** Manually set subscription back to `'active'` with new `expires_at`. Admin action.

**Failure Handling:**
| Failure | Action |
|---|---|
| Already expired | No-op. Skip. |
| Tenant already locked | No-op. |

---

### 4.25 Store Locked

**Purpose:** A store is locked due to subscription expiry, violation, or admin action.

**Trigger:** Subscription expiration (automated), SuperAdmin action, abuse detection.

**Source:** `TenantLockService`, SuperAdmin dashboard, `SubscriptionExpiryService`.

**Target:** `tenants` — UPDATE `locked_at = now()`, UPDATE `status` to `'locked'`.

**Preconditions:**
- Tenant exists and is not already locked
- If automated: subscription is expired and grace period exceeded
- If manual: actor has SuperAdmin or platform management permission

**Postconditions:**
- `tenant.locked_at = now()`
- `tenant.status = 'locked'` (or similar)
- Storefront shows locked/unavailable page
- Admin dashboard inaccessible
- All API endpoints for this tenant return 403 or tenant-unavailable
- Scheduled tasks for this tenant paused

**Database Impact:**
| Table | Action | Rows |
|---|---|---|
| `tenants` | UPDATE (locked_at, status) | 1 |

**Services Triggered:**
- `TenantLockService::lock()`

**Notifications Triggered:**
- To owner: "Your store has been locked. {reason}."

**Audit Logs:**
| Event Type | Actor | Target | Detail |
|---|---|---|---|
| `store_locked` | system or admin Account ID | Tenant ID | reason, initiated_by, subscription_id |

**Future Email:** Same as notification.

**Future Webhook:** `store.locked` — platform administration, monitoring.

**Future Queue:** Scheduled tasks pause job, CDN cache purge.

**Rollback Strategy:** Clear `locked_at`, set status back to `'active'`.

**Failure Handling:**
| Failure | Action |
|---|---|
| Already locked | No-op. Success response. |
| Subscription still active | Warning: "Store has an active subscription. Confirm lock?" |

---

### 4.26 Store Unlocked

**Purpose:** A locked store is restored to active status.

**Trigger:** Subscription renewal (automated), SuperAdmin action.

**Source:** `SubscriptionLifecycleService` (on renewal), SuperAdmin dashboard.

**Target:** `tenants` — UPDATE `locked_at = NULL`, UPDATE `status` to `'active'`.

**Preconditions:**
- Tenant is locked (`locked_at IS NOT NULL`)
- If automated: subscription renewed successfully
- If manual: actor has SuperAdmin permission

**Postconditions:**
- `tenant.locked_at = NULL`
- `tenant.status = 'active'`
- Storefront restored to normal
- Admin dashboard accessible
- All API endpoints functional

**Database Impact:**
| Table | Action | Rows |
|---|---|---|
| `tenants` | UPDATE (locked_at, status) | 1 |

**Services Triggered:**
- `TenantLockService::unlock()`

**Notifications Triggered:**
- To owner: "Your store has been unlocked."

**Audit Logs:**
| Event Type | Actor | Target | Detail |
|---|---|---|---|
| `store_unlocked` | system or admin Account ID | Tenant ID | reason, initiated_by |

**Future Email:** Same as notification.

**Future Webhook:** `store.unlocked` — platform administration.

**Future Queue:** Scheduled tasks resume job, CDN cache warm.

**Rollback Strategy:** Re-lock the store.

**Failure Handling:**
| Failure | Action |
|---|---|
| Not locked | No-op. Success response. |
| Subscription still expired | Warning: "Subscription is still expired. Confirm unlock?" |

---

## 5. Event Dependency Graph

### 5.1 Dependency Matrix

```
Event A                          → Event B                          Dependency Type
─────────────────────────────────────────────────────────────────────────────
Account Created                  → Email Verification               SOFT (time-independent)
Account Created                  → Membership Created               STRONG (same transaction)
Account Created                  → Login                            STRONG (post-condition)
Membership Created               → Membership Activated             STRONG (if invited)
Membership Created               → Login                            STRONG (post-condition)
Invitation Sent                  → Invitation Accepted              STRONG (causal)
Invitation Sent                  → Invitation Expired               SOFT (time-dependent)
Owner Transfer Started           → Owner Transfer Completed         STRONG (causal)
Password Reset Requested         → Password Changed                 STRONG (causal)
Subscription Activated           → Subscription Renewed             STRONG (time-dependent)
Subscription Expired             → Store Locked                     STRONG (automated)
Store Locked                     → Store Unlocked                   STRONG (causal)
Merchant Registration            → Account Created                  MERGED (same transaction)
Merchant Registration            → Store Creation                   MERGED (same transaction)
Merchant Registration            → Membership Created               MERGED (same transaction)
Customer Registration            → Account Created (or not)         SOFT (find or create)
Customer Registration            → Membership Created               MERGED (same transaction)
```

### 5.2 Dependency Diagram

```
ACCOUNT LIFECYCLE:

  Account Created ───────────► Email Verification
       │
       ├──────────────────────► Password Reset Requested ──► Password Changed
       │
       ├──────────────────────► Login ──► Logout
       │
       └──────────────────────► (via registration) Membership Created


MEMBERSHIP LIFECYCLE:

  Invitation Sent ───────────► Invitation Accepted ───────► Membership Activated
       │                                                           │
       ├──────────────────────► Invitation Rejected                  ├──► Login
       │                                                           │
       └──────────────────────► Invitation Expired                  ├──► Role Changed
                                                                    │
                                                            Membership Suspended
                                                                    │
                                                            Membership Removed


OWNERSHIP LIFECYCLE:

  Owner Transfer Started ────► Owner Transfer Completed ──► (owner changes)


SUBSCRIPTION & STORE LIFECYCLE:

  Subscription Activated ────► Subscription Renewed ────► (loop)
       │
       └──────────────────────► Subscription Expired ────► Store Locked
                                                                 │
                                                         Store Unlocked


REGISTRATION FLOWS (merged events):

  Merchant Registration:
    Account Created + Store Created + Membership Created + Subscription Created
    (single transaction, all four events fire simultaneously)

  Customer Registration:
    Account Created (maybe) + Membership Created + CustomerProfile Created
    (single transaction, events fire together)
```

### 5.3 Event Ordering Constraints

| Constraint | Explanation |
|---|---|
| Account must exist before Membership | FK constraint on `tenant_memberships.account_id` |
| Tenant must exist before Membership | FK constraint on `tenant_memberships.tenant_id` |
| Role must exist before Membership | FK constraint on `tenant_memberships.role_id` |
| Account must exist before Password Reset | FK constraint on `password_reset_tokens.account_id` |
| Membership must be active before Login | Authorization gate checks membership status |
| Owner transfer requires two memberships | Both current and target must exist and be active |
| Subscription must exist before activation | FK constraint on subscription to tenant |
| Store must be locked before unlock | Precondition check on `tenant.locked_at` |

### 5.4 Optional Events

| Event | Optional? | Reason |
|---|---|---|
| Email Verification | YES | Account can exist without verified email (but MustVerifyEmail gates login) |
| Invitation Rejected | YES | Recipient can let it expire instead of explicitly rejecting |
| Store Locked | NO | Subscription expiry inevitably locks the store (may be deferred by grace period) |
| Password Reset Requested | YES | User may remember their password |
| Permission Changed | YES | Roles can be used with default permissions |

### 5.5 Events That Must Never Execute Twice

| Event | Consequence of Double Execution |
|---|---|
| Account Created | UNIQUE constraint on email prevents duplicates |
| Membership Created | UNIQUE constraint on (account_id, tenant_id) prevents duplicates |
| Owner Transfer Completed | Two owners created (application-level check prevents this) |
| Password Changed | Token consumed after first use (PK constraint prevents reuse) |
| Email Verification | No-op on second execution (already verified check) |

---

## 6. Notification Routing Matrix

### 6.1 Event → Notification Channel Routing

```
Event                        Account          Email         In-App DB      Telegram      Future: Push/SMS
──────────────────────────────────────────────────────────────────────────────────────────────────────
Account Created              ✓ (owner)        ✓ (verify)    —              —             —
Merchant Registration        ✓ (owner)        ✓ (welcome)   ✓             —             —
Customer Registration        ✓ (customer)     ✓ (welcome)   ✓             —             —
Store Creation               ✓ (owner)        —             ✓             —             —
Membership Created           ✓ (inviter)      —             ✓             —             —
Membership Activated         ✓ (both parties) ✓ (accepted)  ✓             —             —
Membership Suspended         ✓ (target)       ✓ (suspended) ✓             ✓ (admin)     —
Membership Removed           ✓ (target)       ✓ (removed)   ✓             ✓ (admin)     —
Invitation Sent              ✓ (invitee)      ✓ (invite)    ✓             —             ✓ (SMS future)
Invitation Accepted          ✓ (inviter)      ✓ (accepted)  ✓             —             —
Invitation Rejected          ✓ (inviter)      —             ✓             —             —
Invitation Expired           ✓ (inviter)      —             ✓             —             —
Owner Transfer Started       ✓ (owner)        —             ✓             —             —
Owner Transfer Completed     ✓ (both parties) ✓ (complete)  ✓             —             —
Role Changed                 ✓ (target)       ✓ (changed)   ✓             —             —
Permission Changed           —                —             —             —             —
Password Reset Requested     ✓ (requester)    ✓ (reset)     —             —             —
Password Changed             ✓ (owner)        ✓ (confirm)   —             —             —
Email Verification           ✓ (owner)        —             —             —             —
Login                        —                —             —             —             —
Logout                       —                —             —             —             —
Subscription Activated       ✓ (owner)        ✓ (activated) ✓             ✓ (admin)     —
Subscription Renewed         ✓ (owner)        ✓ (renewed)   ✓             ✓ (admin)     —
Subscription Expired         ✓ (owner)        ✓ (expired)   ✓             ✓ (admin)     —
Store Locked                 ✓ (owner)        ✓ (locked)    ✓             ✓ (admin)     —
Store Unlocked               ✓ (owner)        ✓ (unlocked)  ✓             ✓ (admin)     —
```

### 6.2 Routing Rules

| Rule | Applies To | Implementation |
|---|---|---|
| Owner-only notifications | Subscription, billing, store lock events | Route to `membership->where('is_owner', true)->account` |
| Admin broadcast | Membership status changes, security events | Route to `memberships->whereHas('role', admin)->accounts` |
| Self-notifications | Personal events (password, role, verification) | Route to the specific Account |
| Both parties | Owner transfer, invitation accepted | Route to both accounts independently |
| No notification | Permission changes, login, logout | These are audit-only events |

### 6.3 Tenant Isolation in Notifications

Every notification payload MUST include `tenant_id` in its data array:

```json
{
    "notification_type": "membership_suspended",
    "tenant_id": 42,
    "tenant_name": "My Store",
    "message": "Your access to My Store has been suspended.",
    "action_url": "/store/my-store/login"
}
```

The frontend filters notifications by `tenant_id` to display only relevant notifications in the current tenant context. Global notifications (password reset, email verification) have `tenant_id = null`.

---

## 7. Audit Log Schema

### 7.1 Audit Log Event Types

All identity events write to `activity_log` with the following structure:

| Field | Value | Example |
|---|---|---|
| `causer_type` | `App\Models\Account` | — |
| `causer_id` | Account ID of the actor | 42 |
| `subject_type` | Entity type being acted upon | `App\Models\TenantMembership` |
| `subject_id` | Entity ID | 101 |
| `event` | Event name string | `membership_suspended` |
| `properties` | JSON with event-specific data | See below |
| `log_name` | Always `'identity'` | — |
| `description` | Human-readable summary | "Admin suspended membership of User 55 in Tenant 12" |

### 7.2 Event-Specific Properties

```json
// Account Created
{
    "event": "account_created",
    "properties": {
        "email": "user@example.com",
        "source": "merchant_registration",
        "ip_address": "192.168.1.1"
    }
}

// Membership Suspended
{
    "event": "membership_suspended",
    "properties": {
        "account_id": 55,
        "tenant_id": 12,
        "previous_status": "active",
        "reason": "payment_failure",
        "initiated_by": 42
    }
}

// Owner Transfer Completed
{
    "event": "owner_transfer_completed",
    "properties": {
        "tenant_id": 12,
        "previous_owner_id": 42,
        "new_owner_id": 55,
        "initiated_at": "2026-07-07T10:00:00Z"
    }
}

// Password Changed
{
    "event": "password_changed",
    "properties": {
        "via_reset": true,
        "sessions_revoked_count": 3,
        "ip_address": "192.168.1.1"
    }
}
```

### 7.3 Standard Properties for All Events

| Property | Present? | Description |
|---|---|---|
| `ip_address` | Always (if available) | Request IP |
| `user_agent` | Always (if available) | Request user agent |
| `tenant_id` | If scoped | Tenant context ID |
| `membership_id` | If authenticated | Current membership ID |

---

## 8. Idempotency & Retry Policy

### 8.1 Idempotency Table

| Event | Idempotent | Key | Duplicate Behavior |
|---|---|---|---|
| Account Created | YES | `accounts.email` UNIQUE | Duplicate email → validation error |
| Merchant Registration | NO (composite) | `tenants.slug` UNIQUE + `accounts.email` UNIQUE | Cannot repeat exactly |
| Customer Registration | YES | `tm.(account_id, tenant_id)` UNIQUE | Duplicate membership → error |
| Store Creation | NO (composite) | Same as Merchant Registration | Cannot repeat exactly |
| Membership Created | YES | `tm.(account_id, tenant_id)` UNIQUE | Duplicate → constraint violation |
| Membership Activated | YES | `tm.id` status check | Already active → no-op |
| Membership Suspended | YES | `tm.id` status check | Already suspended → no-op |
| Membership Removed | YES | `tm.id` status check | Already removed → no-op |
| Invitation Sent | YES (per invite) | `tm.(account_id, tenant_id)` UNIQUE | Duplicate → "already invited" |
| Invitation Accepted | YES | Token consumed | Token not found → error |
| Invitation Rejected | NO | User intent | Double rejection → no-op |
| Invitation Expired | YES | `tm.id` expiry check | Already expired → no-op |
| Owner Transfer Started | NO | Session-bound | Second initiation overwrites first |
| Owner Transfer Completed | NO | State machine | Second execution skipped (owner changed) |
| Role Changed | YES | Same value → no-op | Same role_id → no change |
| Permission Changed | YES | Same permission → no-op | Already assigned → no-op |
| Password Reset Requested | YES | UPSERT by `account_id` | Second request overwrites token |
| Password Changed | YES | Token consumed | Token not found → error |
| Email Verification | YES | Already verified → no-op | Already `email_verified_at` set → no-op |
| Login | NO | Creates unique session | Each login creates a new session |
| Logout | YES | Session not found → no-op | Already logged out → no-op |
| Subscription Activated | YES | Status check | Already active → no-op |
| Subscription Renewed | YES | `expires_at` check | Already renewed → no-op |
| Subscription Expired | YES | Status check | Already expired → no-op |
| Store Locked | YES | `locked_at` check | Already locked → no-op |
| Store Unlocked | YES | `locked_at` check | Not locked → no-op |

### 8.2 Retry Policy

| Can Retry | Max Retries | Backoff | Notes |
|---|---|---|---|
| Email Notification | 3 | Exponential (30s, 2m, 5m) | Email service transient failure |
| Database Transaction | 2 | Immediate (deadlock retry) | MySQL deadlock resolution |
| Webhook Dispatch | 5 | Exponential (1m, 5m, 15m, 30m, 1h) | Third-party endpoint failure |
| Audit Log Write | 2 | Immediate | Activity log write failure |
| Subscription Renewal | 3 Daily | 24h | Payment gateway retry |

### 8.3 Must Not Retry

| Event | Why |
|---|---|
| Login | Each attempt is independent. Retry = new login attempt. |
| Password Change | Token consumed. Retry requires new token. |
| Owner Transfer | State mutation. Retry could double-execute. |
| Invitation Accept | Token consumed. Retry requires new invitation. |

---

## 9. Failure Handling & Rollback

### 9.1 Transaction Failure Recovery

If a database transaction fails mid-event:

```
BEGIN TRANSACTION
    ├── Mutation 1 (e.g., INSERT INTO accounts)     ◄── SUCCEEDS
    ├── Mutation 2 (e.g., INSERT INTO tm)            ◄── FAILS
    └── ROLLBACK                                     ◄── Automatic
```

**Result:** All mutations in the transaction are rolled back. The application receives a `Throwable` and should return an appropriate error response.

**Compensating actions:** If a notification was dispatched BEFORE the transaction (should never happen — notifications are always after commit), a compensating action may be needed. To prevent this, notifications are ALWAYS queued after commit:

```php
DB::transaction(function () {
    // Mutations
});

// AFTER commit: dispatch notifications
dispatch(new SendInvitationNotification($membership));
```

### 9.2 Event-Specific Failure Scenarios

| Event | Failure | Recovery |
|---|---|---|
| Merchant Registration | Tenant created, Account creation fails | Tenant rollback via FK CASCADE |
| Merchant Registration | Account created, Role creation fails | Account deleted, Tenant deleted |
| Merchant Registration | Account + Tenant created, Subscription fails | Tenant exists without subscription. Manual intervention. |
| Customer Registration | Account created, Membership creation fails | Account deleted (if new), error returned |
| Owner Transfer | Transfer completes, notification fails | Transfer is NOT rolled back (state change is durable). Notification is retried via queue. |
| Password Changed | Password updated, session revocation fails | Sessions partially revoked. Retry session cleanup job. |
| Store Locked | Tenant locked, notification fails | Tenant IS locked (durable). Retry notification. |

### 9.3 Partial Failure Prevention

To prevent partial failures, every event that mutates multiple tables MUST use a database transaction. The transaction guarantees atomicity:

```php
DB::transaction(function () {
    // All mutations inside the transaction
    $account = Account::create([...]);
    $membership = TenantMembership::create([...]);
    CustomerProfile::create([...]);
});  // Either all succeed or all fail
```

### 9.4 Dead-Letter Queue

Events that fail after all retry attempts are deposited in a dead-letter queue:

| Failed Event | Dead-Letter Action | Resolution |
|---|---|---|
| Email notification | Logged to `failed_jobs` table | Admin manually resends |
| Webhook dispatch | Logged to `failed_jobs` table | Admin manually triggers or retries |
| Session revocation | Logged to `failed_jobs` table | Admin runs session cleanup command |

---

## 10. Future Readiness

### 10.1 Webhook Events (Future)

Each event maps to a webhook event name for third-party integrations:

```json
{
    "event": "account.created",
    "data": { "account_id": 1, "email": "..." }
}
```

| Internal Event | Webhook Event Name | Payload Includes |
|---|---|---|
| Account Created | `account.created` | account_id, email, created_at |
| Merchant Registration | `store.created` | tenant_id, tenant_name, owner_account_id |
| Customer Registration | `customer.created` | account_id, tenant_id, membership_id |
| Membership Created | `membership.created` | membership_id, account_id, tenant_id, role_id |
| Membership Activated | `membership.activated` | membership_id, joined_at |
| Membership Suspended | `membership.suspended` | membership_id, reason |
| Membership Removed | `membership.removed` | membership_id, reason |
| Invitation Sent | `invitation.sent` | membership_id, target_email, role |
| Invitation Accepted | `invitation.accepted` | membership_id, accepted_at |
| Invitation Rejected | `invitation.declined` | membership_id |
| Invitation Expired | `invitation.expired` | membership_id |
| Owner Transfer Completed | `ownership.transferred` | tenant_id, previous_owner_id, new_owner_id |
| Role Changed | `membership.role_changed` | membership_id, old_role, new_role |
| Password Changed | `password.changed` | account_id (no password data) |
| Email Verification | `account.email_verified` | account_id, verified_at |
| Login | `account.login` | account_id, ip_address |
| Subscription Activated | `subscription.activated` | tenant_id, plan_id, expires_at |
| Subscription Renewed | `subscription.renewed` | tenant_id, plan_id, new_expires_at |
| Subscription Expired | `subscription.expired` | tenant_id, plan_id |
| Store Locked | `store.locked` | tenant_id, reason |
| Store Unlocked | `store.unlocked` | tenant_id |

### 10.2 Queue Job Events (Future)

Each event may trigger queued jobs:

| Event | Queue Job | Priority | Delay |
|---|---|---|---|
| Account Created | SendVerificationEmail | HIGH | Immediate |
| Account Created | WelcomeEmailJob | LOW | 1 hour |
| Account Created | AbandonedCartSetupJob | LOW | 24 hours |
| Merchant Registration | TenantProvisioningJob | HIGH | Immediate |
| Merchant Registration | OnboardingSequenceJob | LOW | 24 hours |
| Invitation Sent | InvitationReminderJob | LOW | 3 days |
| Subscription Expired | GracePeriodReminderJob | LOW | 3, 7, 14 days |
| Store Locked | StorageCleanupJob | LOW | Immediate |

### 10.3 Billing Events (Future)

Some identity events trigger billing system actions:

| Identity Event | Billing Action |
|---|---|
| Owner Transfer Completed | Re-assign billing ownership. Remove old owner's payment methods. |
| Membership Suspended | No billing impact (subscription is tenant-level, not membership-level). |
| Store Locked | Pause subscription billing (if applicable). |
| Store Unlocked | Resume subscription billing. |

### 10.4 Subscription Events (Future)

| Identity Event | Subscription Impact |
|---|---|
| Membership Created | Count toward staff slot limit (plan-based). |
| Membership Removed | Release staff slot. |
| Owner Transfer Completed | Verify new owner can manage billing. |

---

## 11. Engineering Self Review

### 11.1 Missing Events Identified

| Missing Event | Added? | Justification |
|---|---|---|
| Account Suspended | Covered under Membership Suspended | Account-level suspension is different from membership-level. Account suspension gates ALL tenants. Added to lifecycle diagram but no separate event needed — Account status change is an admin action, not an identity flow event. |
| Account Deleted (soft) | NOT a separate event | Soft-delete is captured by existing events (account created, login blocked). No new event needed. |
| Membership Expired (distinct from invitation) | NOT needed | Memberships don't expire independently (they are tied to tenant subscription). Only invitations expire. |
| Store Created (as standalone) | MERGED into Merchant Registration | Store creation is always part of merchant registration or existing owner creating a second store. No standalone "create store" event. |
| Email Changed | Future | Account email change is not in scope for Phase 1-8. Noted as future event. |

### 11.2 Circular Dependencies

| Dependency Chain | Circular? | Resolution |
|---|---|---|
| Account Created → Email Verified → Login | No | Linear chain. |
| Merchant Registration → Account Created + Store Created + Membership Created | NO (merged) | All three happen in the same transaction. No circular dependency. |
| Subscription Expired → Store Locked → (renewal) → Store Unlocked → Subscription Active | CYCLE | This is intentional — it represents the subscription lifecycle loop. The cycle is broken by business logic (payment must be received between expired and active). |
| Owner Transfer Started → Owner Transfer Completed → (new owner) → Owner Transfer Started | CYCLE | Intentional — ownership can be transferred multiple times. Each transfer is an independent event sequence. |

### 11.3 Race Conditions Identified

| Race Condition | Risk | Mitigation |
|---|---|---|
| Concurrent registration with same email | LOW | UNIQUE constraint on `accounts.email` prevents duplicates. One registration succeeds, the other gets validation error. |
| Concurrent owner transfer initiation | MEDIUM | Only the first initiation should proceed. Use database locking or optimistic locking on tenant row. |
| Concurrent membership suspension and role change | LOW | Both update `tenant_memberships`. Last write wins. No data corruption risk. |
| Concurrent invitation acceptance from multiple tabs | LOW | Status check + update in same transaction. Only one succeeds. |
| Concurrent password reset and login | MEDIUM | Password change revokes all sessions. Login after revocation requires new password. No data loss. |

**Mitigation for owner transfer race condition:**
```php
DB::transaction(function () use ($tenant, $newOwnerId) {
    // Lock the tenant row to prevent concurrent transfers
    $tenant = Tenant::where('id', $tenant->id)->lockForUpdate()->first();

    // Verify current owner still exists
    $currentOwner = $tenant->memberships()->where('is_owner', true)->first();
    if (! $currentOwner) {
        throw new \Exception('No owner found for this tenant.');
    }

    // Execute transfer
    $currentOwner->update(['is_owner' => false]);
    $newOwner->update(['is_owner' => true]);
});
```

### 11.4 Duplicate Events Identified

| Potential Duplicate | Status | Resolution |
|---|---|---|
| Merchant Registration vs. Store Creation | MERGED | Both go through `TenantBootstrapService`. They differ only in whether the Account already exists. Single event handler. |
| Membership Created vs. Membership Activated | DISTINCT | Created = row inserted. Activated = status changed from 'invited' to 'active'. Different lifecycle phases. |
| Password Reset Requested vs. Password Changed | DISTINCT | Requested = token generated. Changed = token consumed. Two phases of the same flow. |

### 11.5 Future SaaS Scalability Risks

| Risk | Impact | Mitigation |
|---|---|---|
| **Audit log table growth** | At scale (100K+ tenants), activity_log grows by millions of rows per month. Identity events are ~20% of all events. | Archive activity_log > 90 days. Partition by month. Use read replicas for audit queries. |
| **Notification table growth** | Each identity event can generate 1-N notifications. At scale, this table grows fastest. | Archive notifications > 90 days. Purge read notifications > 30 days. Use database partitioning. |
| **Queue job volume** | Events like login (every authenticated request) should not dispatch queue jobs at scale. | Login is NOT queued. Only subscription, invitation, and notification events are queued. Login audit is synchronous (single INSERT). |
| **Webhook delivery backlog** | If a third-party integration is down, webhook delivery retries could accumulate. | Per-tenant webhook delivery queues. Independent retry schedules. Dead-letter after 5 retries. |
| **Event ordering across microservices** | If the platform moves to microservices, events must be ordered. | Use a single database transaction for all mutations. The event log is the source of truth. Future: event sourcing on activity_log table. |
| **Multi-region deployment** | Events triggered in different regions could arrive out of order. | Not a concern for Phase 1-8. Future: use centralized event bus with sequence IDs. |

---

*This event flow specification is the single source of truth for all identity lifecycle events. Every notification, audit log, email, webhook, billing event, and automation must follow this specification. Approved by Principal Architect on 2026-07-07.*
