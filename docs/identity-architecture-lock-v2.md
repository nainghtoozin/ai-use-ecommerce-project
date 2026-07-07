# Identity Architecture Lock — v2

**Status:** FINAL — Architecture Locked  
**Date:** 2026-07-07  
**Version:** 2.0  
**Project:** Multi-tenant SaaS E-commerce Platform  
**Preserves:** All v1 architectural decisions  
**Refines:** Owner strategy, permission overrides, notification scope, backward compatibility, testing strategy  
**Supersedes:** `docs/identity-architecture-lock-v1.md`

---

## Table of Contents

1. Executive Summary
2. Vision
3. Architecture Principles (Unchanged from v1)
4. Final Identity Model (Unchanged from v1)
5. Final Membership Model (Unchanged from v1)
6. Final Tenant Model (Unchanged from v1)
7. Final Role Model (Unchanged from v1)
8. Final Permission Model (Unchanged from v1)
9. Owner Strategy — REFINED
10. Permission Override Strategy — REFINED
11. Authentication Strategy (Unchanged from v1)
12. Authorization Strategy (Unchanged from v1)
13. Business Rules (Unchanged from v1)
14. Table Responsibilities (Unchanged from v1)
15. Relationship Design (Unchanged from v1)
16. Registration Flows (Unchanged from v1)
17. Login Flows (Unchanged from v1)
18. Password Reset Strategy (Unchanged from v1)
19. Email Verification Strategy (Unchanged from v1)
20. Invitation Strategy (Unchanged from v1)
21. Store Switching Strategy (Unchanged from v1)
22. Ownership Transfer Strategy — REFINED
23. Notification Strategy — EXPANDED
24. Session Strategy (Unchanged from v1)
25. OAuth Readiness (Unchanged from v1)
26. API Readiness (Unchanged from v1)
27. Backward Compatibility Matrix — NEW
28. Testing Strategy — NEW
29. Security Considerations (Unchanged from v1)
30. Migration Risks (Unchanged from v1)
31. Trade-offs (Unchanged from v1)
32. Phase-by-Phase Roadmap — REFINED
33. Self Review — NEW
34. Final Engineering Recommendation (Unchanged from v1)

---

## Executive Summary

This document is Version 2 of the Identity Architecture Lock. It preserves every architectural decision from v1 (Account → Membership → Tenant → Role → Permission hierarchy, single auth guard, direct role_id FK, custom Gate::before(), account-level password and verification, signed URLs for invitations) and refines five areas where the v1 specification was incomplete.

The refinements are:

1. **Owner Strategy** — Defines ownership as a legal/business responsibility, not a permission role. The `is_owner` flag is locked as the single source of truth. Owner responsibilities (subscription, billing, store deletion, transfer) are documented explicitly.

2. **Permission Override Strategy** — The `staff_profiles.permissions_overrides` JSON field from v1 is moved to Version 5. It is excluded from Phase 1-5 implementation to keep the authorization system simple and auditable. Version 3 uses strict role-based permissions only.

3. **Notification Scope** — The notification architecture is expanded with tenant-isolated routing, per-role visibility rules, cross-store notification boundaries, and channel readiness (database, email, push, mobile).

4. **Backward Compatibility Matrix** — Every existing project module is audited against the identity migration. Each module lists its current dependency on the User model, migration impact, compatibility risk, required changes, and regression risk.

5. **Testing Strategy** — A complete implementation validation checklist covering 25 identity scenarios with pass criteria, designed to catch regressions during and after each implementation phase.

---

## Vision

*Unchanged from v1.* A single natural person should own multiple stores, shop as a customer in other stores, work as staff, switch between tenant contexts without re-authentication — all with one email and one password.

---

## Architecture Principles

*Unchanged from v1.*

1. Identity is global.
2. Membership is per-tenant.
3. Authentication proves Identity.
4. Authorization checks Membership.
5. Role is per-Membership.
6. Profile extends Membership.
7. Tenant owns business data.
8. SuperAdmin is platform-level.

---

## Final Identity Model

*Unchanged from v1.* Account model owns email, password, verification, global status. No tenant_id, no is_owner, no tenant-specific data.

---

## Final Membership Model

*Unchanged from v1.* TenantMembership links Account to Tenant with role_id, is_owner, status. UNIQUE(account_id, tenant_id).

---

## Final Tenant Model

*Unchanged from v1.*

---

## Final Role Model

*Unchanged from v1.*

---

## Final Permission Model

*Unchanged from v1.* Global permissions, assigned to roles only.

---

## 9. Owner Strategy — REFINED

### 9.1 Why is_owner is NOT a Role

The v1 specification used `is_owner` as a boolean flag on TenantMembership. This section justifies and locks that decision.

| Dimension | Role-based | Ownership (is_owner) |
|---|---|---|
| **Assignability** | Assigned by any authorized admin | Only transferred by current owner |
| **Revocability** | Revocable by owner or authorized admin | Cannot be revoked by anyone except the owner themselves (via transfer) |
| **Scope** | Defines permission boundaries | Defines legal and financial responsibility |
| **Implied permissions** | Whatever the Spatie role grants | **All** permissions implicitly, bypassing role checks |
| **Business responsibility** | None | Subscription billing, store deletion, ownership transfer, legal liability |
| **Count per tenant** | Multiple members can share the same role | Exactly one owner at any time |
| **Delegation** | Role can be delegated | Ownership cannot be delegated — only transferred |

**Conclusion:** Ownership is a **business responsibility flag**, not a permission role. It must remain as a dedicated boolean column on TenantMembership. Conflating it with a Spatie role would:

- Allow accidental creation of multiple owners (if someone assigns the "owner" role to a second member)
- Make ownership transfer indistinguishable from a role change in audit logs
- Allow the owner's permissions to be modified by editing the role's permissions (owner should always have full access)

### 9.2 Ownership Responsibilities

The owner of a tenant holds exclusive authority over:

| Responsibility | Exclusive to Owner? | Details |
|---|---|---|
| Subscription management | **Yes** | Upgrade, downgrade, cancel subscription |
| Billing ownership | **Yes** | Payment method management, invoice access, billing contact |
| Store deletion | **Yes** | Initiate and confirm store deletion |
| Ownership transfer | **Yes** | Transfer ownership to another active member |
| Staff appointment | **Yes** | Invite, promote, demote, remove staff members |
| Store domain management | **Yes** | Custom domain configuration |
| Legal representation | **Yes** | Platform communication regarding legal/compliance matters |
| Store closure / lock | **Yes** | Voluntary store lock or closure |
| Platform-level notifications | **Yes** | Critical platform announcements, terms of service changes |

**Authorized staff via roles** may manage day-to-day operations (products, orders, customers, promotions) but cannot perform any of the above owner-exclusive actions.

### 9.3 Single Source of Truth

The single source of truth for ownership is:

```
tenant_memberships.is_owner = true
```

There is **no** `owner_id` column on the `tenants` table. Ownership is always resolved through the membership pivot. This ensures:

- Ownership is always tied to a membership (which includes tenant_id, account_id, role_id)
- When ownership is transferred, only the Membership records change (not the Tenant record)
- Audit trail is preserved through membership changes
- No redundant foreign key that could become inconsistent

### 9.4 Owner in Gate::before()

The Gate::before() method from v1 is preserved. The owner bypass:

```php
if ($membership->is_owner) {
    return true;  // Owner has implicit access to ALL permissions
}
```

This means the owner's Spatie role is irrelevant for authorization. The owner could have the `customer` role and still access admin functions. This is intentional — the `admin` role assignment to the owner is a convention for Spatie compatibility, not an authorization mechanism.

### 9.5 Owner in Data Migration

During Phase 3 data migration, the existing `users.is_owner` column maps directly to `tenant_memberships.is_owner`. Each tenant must have exactly one Membership with `is_owner = true` after migration. If a tenant has no owner (data integrity issue), the migration must assign the oldest admin user as owner and log a warning.

---

## 10. Permission Override Strategy — REFINED

### 10.1 Decision: Defer to Version 5

The v1 specification included a `staff_profiles.permissions_overrides` JSON field for exception-based permission additions. **This feature is moved to Version 5.**

### 10.2 Rationale

| Concern | Impact | Why Deferred |
|---|---|---|
| **Complexity in Gate::before()** | High | The Gate::before() logic must merge role permissions with overrides, check StaffProfile existence, handle edge cases (e.g., override grants permission that role explicitly denies). This doubles the authorization logic complexity. |
| **Audit trail gaps** | Medium | Override-based permissions are not tracked through Spatie's role_has_permissions table. Auditing "who has what permission" requires checking both roles and StaffProfile overrides. |
| **StaffProfile dependency** | High | The permission checking Gate::before() would need to load the StaffProfile relationship on every request, adding a database query or eager loading complexity to all authenticated routes. |
| **UI complexity** | High | The staff management UI must include a permission override editor, permission search, and validation. This is a significant frontend effort. |
| **Version 3 scope** | Critical | Phase 5 (Authorization) is already the most complex phase. Adding permission overrides would make it高风险 (high risk) and delay the entire identity migration. |
| **Business value** | Low-Medium | Permission overrides are an edge case. Most staff members fit into predefined roles. Overrides can be handled by creating a custom role. |

### 10.3 Version 3 Behavior (Locked)

- All authorization is strictly **role-based**
- A staff member's effective permissions = the permissions of their Spatie role
- Granting additional permissions requires creating a new Spatie role or modifying the existing one
- The `staff_profiles` table still exists (for position, department) but `permissions_overrides` is either omitted from the migration or included as a nullable JSON column that is **never read** by authorization logic

### 10.4 Version 5 Behavior (Future)

When permission overrides are implemented:

1. The `staff_profiles.permissions_overrides` JSON column stores an array of permission names
2. Gate::before() merges `$rolePermissions` + `$overridePermissions`
3. An audit log entry records when overrides are added or removed
4. A UI for managing overrides is built (checkboxes for each permission)
5. The `$membership->hasPermission()` helper method includes overrides

---

## 11-26. Unchanged Sections

The following sections are preserved verbatim from v1. Refer to v1 document for full content.

- Authentication Strategy (Single guard, accounts provider, session payload)
- Authorization Strategy (Middleware stack, Gate::before(), Spatie configuration)
- Business Rules (Account, Membership, Role, Tenant rules)
- Table Responsibilities (accounts, tenant_memberships, tenants, profiles, sessions, etc.)
- Relationship Design (Core structure, cardinalities, unique constraints, FK rules)
- Registration Flows (Merchant, Customer, Staff Invitation, Acceptance)
- Login Flows (Admin, Customer, Multi-tenant, SuperAdmin)
- Password Reset Strategy (Account-level, keyed by account_id)
- Email Verification Strategy (Account-level, covers all memberships)
- Invitation Strategy (Signed URLs, 7-day expiry, membership-based)
- Store Switching Strategy (POST /select-tenant, session update)
- Session Strategy (account_id + membership_id in session, concurrent tabs)
- OAuth Readiness (social_accounts table, provider linking)
- API Readiness (Sanctum tokens, X-Tenant header)
- Security Considerations
- Migration Risks
- Trade-offs

---

## 22. Ownership Transfer Strategy — REFINED

### 22.1 Preconditions (Unchanged from v1)

1. Current owner has active Membership with `is_owner = true`
2. Target Account has active Membership in the same tenant
3. Target Account is not the current owner
4. Current owner initiates the transfer

### 22.2 Flow (Unchanged from v1)

```
1. Current owner initiates transfer via /store/{slug}/admin/transfer-ownership
2. Validate: current is_owner, target exists, target membership active
3. Log audit event (initiation)
4. Confirm with current owner
5. BEGIN TRANSACTION
   - Current Membership: is_owner = false
   - Target Membership: is_owner = true
   - Log audit event (completion)
   COMMIT
6. Notify both parties
7. Redirect
```

### 22.3 Post-Transfer Behavior — REFINED

The v1 document stated "previous owner retains admin role." This is refined:

- Previous owner's Membership remains with the same `role_id` (typically `admin`)
- Previous owner loses **owner-exclusive** abilities:
  - Subscription management
  - Billing ownership
  - Store deletion
  - Staff appointment/demotion
  - Domain management
- Previous owner retains **role-based** abilities:
  - Product management (if role is admin)
  - Order management (if role is admin)
  - All permissions granted by their Spatie role

- Previous owner CAN be removed or demoted by the new owner
- Previous owner CANNOT transfer ownership again (they no longer own the store)

### 22.4 Ownership Transfer vs. Account Deletion

If the previous owner's Account is later soft-deleted:
- Their Membership is retained (CASCADE on accounts → tenant_memberships would lose the audit trail)
- But `deleted_at` on Account prevents authentication
- The Tenant still has an owner (the new owner)
- The previous owner's Membership record still exists for audit purposes

**Recommendation:** Do NOT use CASCADE delete for `tenant_memberships.account_id`. Instead, use SET NULL or handle in soft-delete logic. The v1 specification says CASCADE — this is overridden here.

### 22.5 Ownership and Subscription

When ownership is transferred:
- Subscription remains with the **Tenant**, not the owner
- The new owner gains billing ownership
- Payment methods associated with the previous owner's Account should be removed from the tenant's active payment methods
- Subscription history stays with the Tenant

---

## 23. Notification Strategy — EXPANDED

### 23.1 Notification Architecture Overview

```
Notification Source
    │
    ▼
Notification Router (determines channel + recipients)
    │
    ├── Database Channel (in-app notifications)
    ├── Email Channel (transactional emails)
    ├── Telegram Channel (admin alerts)
    ├── Push Channel (future: mobile/web push)
    └── SMS Channel (future: order updates)
    │
    ▼
Recipient Resolution
    ├── Specific Account (by ID)
    ├── Admin memberships of a Tenant (by role filter)
    ├── Customer memberships of a Tenant (by role filter)
    └── All members of a Tenant (by tenant scope)
```

### 23.2 Notification Types and Visibility

Each notification type has a defined scope. No notification should leak across tenant boundaries.

| Notification Type | Scope | Visible To | Routing Method | Tenant Isolated? |
|---|---|---|---|---|
| `new_order` | Tenant | All admin memberships of that tenant | `Tenant.notifyAdmins()` | Yes |
| `order_status_changed` | Account + Tenant | The specific customer Account + all admin memberships | Account notification + admin broadcast | Yes |
| `order_placed` | Account | The specific customer Account | Account notification | Yes |
| `payment_verified` | Tenant + Account | Admin memberships + the paying Account | Both channels | Yes |
| `payment_rejected` | Tenant + Account | Admin memberships + the paying Account | Both channels | Yes |
| `payment_proof_uploaded` | Tenant | Admin memberships of that tenant | `Tenant.notifyAdmins()` | Yes |
| `new_message` | Account | The specific recipient Account | Account notification | N/A (chat is Account-level) |
| `invitation_received` | Account | The specific Account | Account notification | N/A (invitation includes tenant context) |
| `password_reset` | Account | The specific Account | Account notification | N/A |
| `email_verification` | Account | The specific Account | Account notification | N/A |
| `ownership_transfer` | Account | Previous owner + new owner | Account notification | Yes (notification body includes tenant name) |
| `low_stock` | Tenant | Admin memberships of that tenant | `Tenant.notifyAdmins()` | Yes |
| `subscription_expiring` | Tenant | Owner membership only | Account notification to owner | Yes |
| `subscription_expired` | Tenant | Owner membership only | Account notification to owner | Yes |
| `store_suspended` | Tenant | Owner membership only | Account notification to owner | Yes |

### 23.3 Notification Routing Strategy

#### Routing to Admin Memberships

```php
// Tenant::notifyAdmins() — sends to all admin-role members
$tenant->memberships()
    ->whereHas('role', fn($q) => $q->where('name', 'admin'))
    ->where('status', 'active')
    ->get()
    ->each(fn($m) => $m->account->notify($notification));
```

**Tenant isolation guarantee:** The `memberships()` query is scoped to a specific `$tenant` instance. The notification is only sent to accounts that have an admin membership in that specific tenant. An admin of Store A never receives a notification for Store B's actions.

#### Routing to Customer Account

```php
// Direct to Account — always tenant-isolated by the caller
$order->customerMembership->account->notify($notification);
```

The caller must provide the correct membership context. The notification body should include the tenant name for disambiguation (since one Account can be a customer in multiple stores).

#### Routing to Owner Only

```php
// Owner-only notifications (billing, subscription, legal)
$tenant->memberships()
    ->where('is_owner', true)
    ->where('status', 'active')
    ->first()
    ?->account->notify($notification);
```

Owner-only notifications must never be sent to non-owner members, even if they have admin role.

### 23.4 Cross-Store Notification Boundaries

An Account with memberships in Store A (admin) and Store B (customer):

| Notification Source | Should Receive? | Visible in Which UI? |
|---|---|---|
| Store A: new order | **Yes** (admin role) | Store A notification list only |
| Store B: order status change | **Yes** (customer role) | Store B notification list only |
| Store A: low stock | **Yes** (admin role) | Store A notification list only |
| Store B: new order | **No** (customer role in Store B) | N/A |
| Store A: invitation to someone else | **No** | N/A |
| Global: password reset | **Yes** | Account-level (not tenant-scoped) |

### 23.5 Notification Storage (Database Channel)

The `notifications` table stores all in-app notifications:

| Column | Value | Purpose |
|---|---|---|
| `id` | UUID | Primary key |
| `type` | String | Notification class name |
| `notifiable_id` | Account ID | The recipient Account |
| `notifiable_type` | `App\Models\Account` | Morph to Account |
| `data` | JSON | Notification payload (includes `tenant_id` for scoping) |
| `read_at` | Timestamp|null | Read status |
| `created_at` | Timestamp | |

**Tenant isolation in notification list queries:**

```php
// An admin viewing their notifications for Store A
$account->notifications()
    ->where('data->tenant_id', $currentTenantId)
    ->get();

// A customer viewing their notifications for Store B
$account->notifications()
    ->where('data->tenant_id', $currentTenantId)
    ->get();

// Account viewing global notifications (password reset, etc.)
$account->notifications()
    ->whereNull('data->tenant_id')
    ->get();
```

Every notification payload includes a `tenant_id` field. Null `tenant_id` means the notification is global (password reset, email verification). The frontend filters by current tenant context.

### 23.6 Notification Preferences

Stored as JSON on `accounts.notification_preferences`:

```json
{
    "order_placed": true,
    "order_status_changed": true,
    "payment_verified": true,
    "payment_rejected": true,
    "new_message": true,
    "new_order": true,
    "payment_proof_uploaded": true,
    "low_stock": true,
    "order_cancelled": true,
    "notification_sound": false
}
```

**Per-tenant preferences (future):** When needed, a `notification_preferences` JSON column on TenantMembership can override the Account-level defaults for that specific tenant.

### 23.7 Channel Readiness

| Channel | Phase | Status |
|---|---|---|
| Database (in-app) | Phase 7 | Current implementation with Account model |
| Email | Phase 7 | Current via Laravel Mail / Notifications |
| Telegram | Phase 7 | Current via TelegramIntegration model (admin-only) |
| Push (web) | Future | Requires Service Worker + VAPID keys |
| Push (mobile) | Future | Requires Firebase Cloud Messaging or APNs |
| SMS | Future | Requires SMS provider integration |

---

## 27. Backward Compatibility Matrix — NEW

### 27.1 Scope

This matrix audits every existing project module against the identity migration (Phase 1-8). Each module is rated for migration impact, compatibility risk, and regression risk.

**Scale:**
- Migration Impact: None / Low / Medium / High
- Compatibility Risk: None / Low / Medium / High
- Regression Risk: None / Low / Medium / High

### 27.2 Modules

#### Authentication

| Aspect | Detail |
|---|---|
| **Current Dependency** | `Auth::user()` returns `App\Models\User`. `users` table with global unique email. Auth guard provider is `users`. |
| **Migration Impact** | **High** — Guard provider changes from `users` to `accounts`. All auth controllers updated. Session payload changes. |
| **Compatibility Risk** | **High** — Existing sessions invalidated. Password reset broker changes. Email verification model changes. |
| **Required Changes** | Update `config/auth.php`. Update `AuthenticatedSessionController`. Update `StorefrontLoginController`. Update `RegisteredUserController`. Update `CreateStoreController`. Update `LoginRequest`. Update password reset controllers. Update `NewPasswordController`. Update email verification controllers. |
| **Regression Risk** | **High** — Incorrect migration breaks login for all existing users. |
| **Expected QA Scope** | Full auth test suite (6 test files). Manual login for each role (superadmin, admin, customer). Password reset end-to-end. Email verification. Session persistence. |

#### Billing

| Aspect | Detail |
|---|---|
| **Current Dependency** | `$user->can()`, `$user->tenant->subscription`, `$user->hasActiveSubscription()`, `$user->getActivePlan()` on User model. |
| **Migration Impact** | **Medium** — All `$user->*` calls become `$account->currentMembership->tenant->*` or use helpers. |
| **Compatibility Risk** | **Medium** — If Gate::before() is not properly implemented, billing authorization checks may fail (403 on payment pages). |
| **Required Changes** | Update `BillingNotificationService`. Update `SubscriptionLimitService`. Audit all billing controllers for `$user->can()` calls. Update `Checkout.jsx`, `PlanCards.jsx`, `UpgradePlan.jsx`, `Payment.jsx`, `Subscription.jsx`. |
| **Regression Risk** | **Medium** — Billing is the most sensitive module. A regression could overcharge or undercharge merchants. |
| **Expected QA Scope** | Full billing test suite. Manual payment flow. Subscription status verification. Payment proof upload. |

#### Subscription

| Aspect | Detail |
|---|---|
| **Current Dependency** | `$user->tenant->subscription`, `$user->hasActiveSubscription()`, `$user->hasFeature()`. |
| **Migration Impact** | **Medium** — Feature checks go through Account → currentMembership → tenant → subscription. |
| **Compatibility Risk** | **Medium** — `FeatureGate` service checks user features. Must be updated to use Account/membership context. |
| **Required Changes** | Update `FeatureGate` service. Update `SubscriptionLifecycleService`. Update `SubscriptionExpiryService`. Update `SubscriptionLimitService`. Update console commands (`subscriptions:process-expired`, `subscriptions:send-expiry-warnings`). |
| **Regression Risk** | **Medium** — Incorrect feature gating could grant paid features to free plan merchants or block features for paying merchants. |
| **Expected QA Scope** | Subscription lifecycle tests (TrialLifecycleTest, SubscriptionLimitTest, SubscriptionLockModeTest). Manual feature gate verification. |

#### Products

| Aspect | Detail |
|---|---|
| **Current Dependency** | Minimal. Products are tenant-scoped via TenantAware trait. `$product->created_by` references User ID but is informational (not used for authorization). |
| **Migration Impact** | **Low** — `created_by` column on products references `users.id`. This becomes a reference to `accounts.id` (1:1 mapping during migration). No functional change. |
| **Compatibility Risk** | **Low** — Product CRUD works identically. Authorization is through tenant membership, not product-user relationship. |
| **Required Changes** | None for core product operations. Optional: update `created_by` foreign key to reference `accounts.id`. |
| **Regression Risk** | **Low** — Product listing, creation, editing, and deletion should be unaffected. |
| **Expected QA Scope** | Product CRUD for each role (owner, admin staff, customer). Verify tenant isolation. |

#### Orders

| Aspect | Detail |
|---|---|
| **Current Dependency** | `$order->user_id` references `users.id`. `$order->customerMembership` (new in v2). `$user->orders()` relationship. |
| **Migration Impact** | **Medium** — `user_id` on orders table becomes a reference to `accounts.id`. New `tenant_membership_id` column for customer membership context (migration path: order → user → tenant_membership). |
| **Compatibility Risk** | **Medium** — Order queries by `user_id` must continue working. Add `tenant_membership_id` column for new queries. |
| **Required Changes** | Add `tenant_membership_id` FK to orders table (nullable, backward compatible). Update `Order` model relationships. Update `OrderService`, `OrderWorkflow`, `OrderNotificationService`. Update `StorefrontCheckoutController::store()`. Update `OrderController::store()`. Update `ClientOrderController::store()`. |
| **Regression Risk** | **Medium** — 3 separate order creation controllers are known to have duplicate logic. All three must be updated consistently. |
| **Expected QA Scope** | Order creation for guest, customer, and admin. Order listing for customer and admin. Order status updates. Tenant isolation (Store A orders not visible in Store B). |

#### Customers

| Aspect | Detail |
|---|---|
| **Current Dependency** | `CustomerAddress` has `user_id` referencing `users.id`. `Wishlist` has `user_id`. Customer registration creates User with `customer` role. |
| **Migration Impact** | **Medium** — `CustomerAddress.user_id` and `Wishlist.user_id` become references to `accounts.id`. New `tenant_membership_id` columns for tenant-scoped customer data. |
| **Compatibility Risk** | **Medium** — Customer addresses and wishlists are accessed through `$user->addresses()` and `$user->wishlistItems()`. These relationships must be preserved on the Account model or through Membership. |
| **Required Changes** | Add `tenant_membership_id` to `customer_addresses` (nullable). Add `tenant_membership_id` to `wishlists` (nullable). Update `CustomerAddressPolicy`. Update `StorefrontCustomerController`. Update `WishlistController`. Update frontend components (Addresses.jsx, Wishlist.jsx). |
| **Regression Risk** | **Medium** — Cross-tenant wishlist/address leaks if tenant_membership_id scoping is incorrect. |
| **Expected QA Scope** | Address CRUD for customer. Wishlist add/remove. Tenant isolation for customer data. |

#### Notifications

| Aspect | Detail |
|---|---|
| **Current Dependency** | `Notification::send($users, $notification)` where `$users` is a collection of User models. `$user->notifications()` relationship. `$user->wantsNotification()`. |
| **Migration Impact** | **Medium** — Notification recipients become Account models. `notifiable_id` in notifications table references User IDs → must become Account IDs. |
| **Compatibility Risk** | **High** — Existing notification records reference `App\Models\User` as `notifiable_type`. After migration, new notifications reference `App\Models\Account`. The `notifications` table has polymorphic relationship — querying must handle both types during transition. |
| **Required Changes** | Update `NotificationPreferenceService`. Update `OrderNotificationService`. Update `BillingNotificationService`. Update `Tenant::notifyAdmins()`. Update `NotificationController`. Update frontend notification components. Add `account_id` to notification payloads for tenant-scoped filtering. |
| **Regression Risk** | **Medium** — Users may miss notifications if migration is incomplete. Notification preferences may not apply correctly. |
| **Expected QA Scope** | In-app notification delivery. Email notification delivery. Notification preference toggling. Tenant-scoped notification list. |

#### Payment

| Aspect | Detail |
|---|---|
| **Current Dependency** | `PaymentIntent` has `user_id` referencing `users.id`. `PaymentTransaction` has `user_id`. `PaymentEvidence` has `user_id`. Payment controllers use `Auth::user()`. |
| **Migration Impact** | **Medium** — All `user_id` references in payment tables become `account_id`. Payment authorization checks use Account/membership. |
| **Compatibility Risk** | **Medium** — Payment flows are time-sensitive (checkout, proof upload, verification). Regression could cause payment failures or double-charges. |
| **Required Changes** | Update `PaymentIntentService`. Update `PaymentTransactionService`. Update `PaymentEvidenceService`. Update `PaymentReviewService`. Update all payment controllers. Update payment gateway integrations (6 adapters). Update `PaymentService`. |
| **Regression Risk** | **High** — Payment failures directly impact revenue. Double-charges erode merchant trust. |
| **Expected QA Scope** | Full payment flow for each gateway. Payment proof upload. Payment verification. Payment timeline. Refund flow. |

#### Image System

| Aspect | Detail |
|---|---|
| **Current Dependency** | Minimal. `ImageUploadService` tracks `uploaded_by` referencing User ID. `ImageService` has no identity dependency. |
| **Migration Impact** | **Low** — `uploaded_by` becomes a reference to `accounts.id`. |
| **Compatibility Risk** | **None** — Image URLs, storage paths, and access are unaffected by identity changes. |
| **Required Changes** | Update `ImageUploadService` to reference Account instead of User. |
| **Regression Risk** | **Low** — Image upload and retrieval are independent of auth guard. |
| **Expected QA Scope** | Image upload for each role. Image display. Storage quota tracking. Tenant image isolation. |

#### Currency

| Aspect | Detail |
|---|---|
| **Current Dependency** | None. Currency config is read from `WebsiteInfo` (merchant-level) and `PlatformSetting` (platform-level). No User/identity dependency. |
| **Migration Impact** | **None** — Currency is a display setting, not an identity concern. |
| **Compatibility Risk** | **None** |
| **Required Changes** | None for currency logic itself. |
| **Regression Risk** | **None** |
| **Expected QA Scope** | Currency display for all roles. Currency setting changes. |

#### Website Settings

| Aspect | Detail |
|---|---|
| **Current Dependency** | Minimal. `WebsiteInfo` is tenant-scoped. No identity dependency beyond tenant resolution. |
| **Migration Impact** | **Low** — Tenant resolution middleware (IdentifyTenant) must continue working. Website settings are loaded per-tenant regardless of authentication. |
| **Compatibility Risk** | **Low** — Public storefront pages load WebsiteInfo without requiring authentication. |
| **Required Changes** | None for WebsiteSettings model or controllers. |
| **Regression Risk** | **Low** — Website settings are read-only for the public and write-protected by tenant admin auth. |
| **Expected QA Scope** | Public storefront branding. Admin website settings editing. Tenant isolation. |

#### SuperAdmin

| Aspect | Detail |
|---|---|
| **Current Dependency** | `$user->hasRole('superadmin')` check. `$user->isSuperAdmin()`. SuperAdmin User records have `tenant_id = null`. SuperAdmin impersonation uses User model. |
| **Migration Impact** | **High** — SuperAdmin becomes a global role on Account (no tenant_id). Impersonation must work with Account model. All SuperAdmin controllers must use Gate::before() bypass. |
| **Compatibility Risk** | **High** — If SuperAdmin detection fails, platform administrators lose access. |
| **Required Changes** | Update `ImpersonationController` for Account model. Audit all 11 SuperAdmin controllers for User references. Update SuperAdmin dashboard queries. Update SuperAdmin financial reports. Update SuperAdmin tenant management. |
| **Regression Risk** | **High** — SuperAdmin losing access is a P0 production incident. |
| **Expected QA Scope** | SuperAdmin login. Tenant listing. Plan management. Financial reports. Impersonation. Subscription management. |

#### Merchant Dashboard

| Aspect | Detail |
|---|---|
| **Current Dependency** | `Auth::user()` for admin identity. `$user->tenant` for store context. `$user->can()` for permission checks. `$user->isOwner()` for owner-specific UI elements. |
| **Migration Impact** | **Medium** — All `Auth::user()` calls become `Auth::user()` (Account model). The `user` shared in Inertia becomes `account` + `currentMembership`. |
| **Compatibility Risk** | **Medium** — Frontend components expect `$page.props.auth.user` with fields like `name`, `email`, `is_owner`, `tenant_id`. These must be preserved through backward-compatible accessors on Account or merged from Membership. |
| **Required Changes** | Update `HandleInertiaRequests.php` to share Account + currentMembership. Update all admin Inertia pages to use new shared structure (with backward-compatible aliases during transition). Update Admin controllers for Account model. |
| **Regression Risk** | **Medium** — Frontend expects specific user fields. Missing fields cause JavaScript errors and blank pages. |
| **Expected QA Scope** | All admin pages (dashboard, products, orders, categories, brands, units, promotions, coupons, reports). UI rendering after auth migration. |

#### Tenant Resolution

| Aspect | Detail |
|---|---|
| **Current Dependency** | `IdentifyTenant` middleware resolves tenant from URL slug, subdomain, or User's `tenant_id`. `CheckTenantAccess` checks `user.tenant_id`. |
| **Migration Impact** | **High** — Tenant resolution must change from User's single tenant_id to Membership-based membership lookup. `CheckTenantAccess` must query `tenant_memberships` table. |
| **Compatibility Risk** | **High** — If tenant resolution fails, ALL store-scoped routes are inaccessible. |
| **Required Changes** | Update `IdentifyTenant` middleware. Update `CheckTenantAccess` middleware. Create `ResolveMembership` middleware. Update `Storefront` middleware. Update `TenantIsValid` middleware. |
| **Regression Risk** | **High** — Complete site outage for store-scoped pages if tenant resolution breaks. |
| **Expected QA Scope** | Storefront access. Admin dashboard access. Customer account access. Cross-tenant isolation. Public pages without auth. |

#### Spatie Permission

| Aspect | Detail |
|---|---|
| **Current Dependency** | `User` model uses `HasRoles` trait. `$user->can()` checks through Spatie. `model_has_roles` stores `(role_id, model_id, 'App\Models\User')`. `RoleMiddleware` checks `user.hasRole()`. `PermissionController` and `RoleController` manage roles/permissions. |
| **Migration Impact** | **High** — Account model does NOT use HasRoles. `$user->can()` bypasses Spatie and goes through custom Gate::before(). `model_has_roles` is NOT used for Account or Membership. Role/management controllers must work with tenant-scoped roles. |
| **Compatibility Risk** | **High** — If Gate::before() is not correctly implemented, ALL permission checks fail (403 everywhere). |
| **Required Changes** | Set `register_permission_check_method` to false. Implement Gate::before() with owner bypass and role-based check. Update `RoleMiddleware` to check membership.role.name. Update `PermissionController` and `RoleController` for new model context. Remove HasRoles from User. |
| **Regression Risk** | **High** — Every route that uses `$this->authorize()`, `$user->can()`, or `middleware('role:admin')` must continue working. |
| **Expected QA Scope** | Every controller action protected by authorization. Role management CRUD. Permission assignment. RoleMiddleware for all admin/staff routes. |

### 27.3 Module Migration Priority

Based on dependency analysis, modules should be migrated in this order:

| Order | Module | Rationale |
|---|---|---|
| 1 | Tenant Resolution | Must work first; all tenant-scoped routes depend on it |
| 2 | Spatie Permission | Must work before any authorization can function |
| 3 | Authentication | Login must work before any authenticated route is accessible |
| 4 | Merchant Dashboard | First user-facing module after auth |
| 5 | SuperAdmin | Platform administrators must maintain access |
| 6 | Billing | Direct revenue impact |
| 7 | Subscription | Feature gating affects all modules |
| 8 | Orders | Customer-facing, revenue-critical |
| 9 | Customers | Customer-facing, tenant-scoped |
| 10 | Payment | Revenue-critical, time-sensitive |
| 11 | Notifications | User-facing, tenant-scoped |
| 12 | Products | Lower priority, works with tenant scoping |
| 13 | Image System | Minimal identity dependency |
| 14 | Currency | No identity dependency |

---

## 28. Testing Strategy — NEW

### 28.1 Implementation Validation Checklist

Every identity scenario below must pass before Phase 4 (Authentication) is marked complete.

#### Identity Foundation

| # | Scenario | Steps | Expected Result | Phase |
|---|---|---|---|---|
| 1 | Account creation | POST /register with email, password, name | Account created. `accounts` table has 1 new row. Email verification notification sent. | P4 |
| 2 | Duplicate email rejection | POST /register with existing email | Validation error: "The email has already been taken." No Account created. | P4 |
| 3 | Account status: active | Create Account with default status | Account.status = 'active'. Can authenticate. | P4 |
| 4 | Account status: suspended | Update Account.status = 'suspended' | Cannot authenticate. Login returns "Account unavailable." | P4 |
| 5 | Account status: banned | Update Account.status = 'banned' | Cannot authenticate. Login returns "Account unavailable." | P4 |
| 6 | Account soft delete | Soft-delete Account | Account has `deleted_at` set. Cannot authenticate. Memberships preserved. | P4 |

#### Merchant Flows

| # | Scenario | Steps | Expected Result | Phase |
|---|---|---|---|---|
| 7 | Merchant registration | POST /create-store with store name, slug, email, password | Tenant created. Account created. TenantMembership created with is_owner=true, role=admin. Logged in. Redirected to onboarding. | P4 |
| 8 | Merchant login | POST /store/{slug}/admin/login with valid credentials | Authenticated. Current membership set to this tenant. Redirected to admin dashboard. | P4 |
| 9 | Merchant login — wrong tenant | POST /store/{slug}/admin/login with valid credentials but no membership in this tenant | "You don't have access to this store." Not authenticated. | P4 |
| 10 | Merchant login — invited | Membership.status = 'invited'. POST /store/{slug}/admin/login | "Please accept your invitation first." Not authenticated. | P4 |
| 11 | Merchant login — suspended | Membership.status = 'suspended'. POST /store/{slug}/admin/login | "Your access has been suspended." Not authenticated. | P4 |
| 12 | Merchant creates second store | Same Account, POST /create-store with different store name/slug | Second Tenant created. Second TenantMembership created with is_owner=true. Both stores accessible via store switcher. | P6 |

#### Customer Flows

| # | Scenario | Steps | Expected Result | Phase |
|---|---|---|---|---|
| 13 | Customer registration | POST /store/{slug}/register with name, email, password | Account created (or found). TenantMembership created with is_owner=false, role=customer. CustomerProfile created with name. Logged in. | P4 |
| 14 | Customer registration — duplicate in same tenant | Same email, same store | Validation error. "An account with this email already exists in this store." | P4 |
| 15 | Customer registration — same email, different tenant | Same email, different store | Account found (not created). TenantMembership created in new tenant. CustomerProfile created in new tenant. Both memberships active. | P4 |
| 16 | Customer login | POST /store/{slug}/login with valid credentials | Authenticated. Redirected to storefront. | P4 |
| 17 | Customer login — no membership | Valid credentials, no customer membership in this store | Redirected to registration page. | P4 |
| 18 | Customer login — suspended membership | Membership.status = 'suspended' | "Account suspended in this store." Not authenticated. | P4 |

#### Cross-Role Scenarios

| # | Scenario | Steps | Expected Result | Phase |
|---|---|---|---|---|
| 19 | Merchant becomes customer | Account A (merchant in Store A) registers as customer in Store B | Account A gets second TenantMembership with role=customer, is_owner=false. CustomerProfile created. | P6 |
| 20 | Customer joins multiple stores | Same email registers in Store A, Store B, Store C | Three TenantMemberships. Three CustomerProfiles. One Account. Each login scoped to the store URL. | P6 |
| 21 | Merchant + customer + staff | Same Account is owner of Store A, customer in Store B, admin in Store C | Three memberships with different roles and different is_owner values. Unified login. | P6 |
| 22 | Multi-tenant login (no URL context) | Login with 3 memberships via platform /login endpoint | Redirected to /select-tenant. List shows all 3 stores. | P6 |

#### Staff & Invitations

| # | Scenario | Steps | Expected Result | Phase |
|---|---|---|---|---|
| 23 | Invite staff (existing Account) | Owner invites existing Account email | TenantMembership created with status='invited'. Signed URL sent. | P6 |
| 24 | Invite staff (new Account) | Owner invites non-existent email | TenantMembership created with status='invited'. Notification with registration link sent. | P6 |
| 25 | Accept invitation | Click signed URL while authenticated as target Account | Membership.status = 'active'. joined_at = now. Redirected to store dashboard. | P6 |
| 26 | Accept invitation (not authenticated) | Click signed URL while not logged in | Redirected to login. After login, redirected back to accept. | P6 |
| 27 | Accept invitation (wrong Account) | Click signed URL while authenticated as different Account | Error: "This invitation was sent to a different email." | P6 |
| 28 | Accept expired invitation | URL expired (invited_at > 7 days) | Error: "This invitation has expired." | P6 |
| 29 | Invite duplicate membership | Invite email that already has membership in this tenant | Validation error: "This user is already a member of this store." | P6 |

#### Role & Permission

| # | Scenario | Steps | Expected Result | Phase |
|---|---|---|---|---|
| 30 | Role assignment | Membership.role_id points to a Spatie Role | Membership inherits all permissions of that role. | P5 |
| 31 | Permission check (granted) | User with 'orders.view' permission accesses /admin/orders | 200 OK. Page loads. | P5 |
| 32 | Permission check (denied) | User without 'products.edit' permission accesses /admin/products/1/edit | 403 Forbidden. | P5 |
| 33 | Owner bypass | Owner with 'customer' role accesses /admin/orders | 200 OK. Owner bypasses role check. | P5 |
| 34 | SuperAdmin bypass | SuperAdmin accesses any tenant admin route | 200 OK. SuperAdmin bypasses membership + role checks. | P5 |
| 35 | Role middleware | Route with middleware('role:admin') | Only memberships with role.name='admin' can access. | P5 |
| 36 | Permission middleware | Route with middleware('permission:orders.view') | Only memberships with effective permission can access. | P5 |

#### Password & Verification

| # | Scenario | Steps | Expected Result | Phase |
|---|---|---|---|---|
| 37 | Password reset | Submit email on /forgot-password | Reset link sent to email. Token stored in password_reset_tokens (keyed by account_id). | P4 |
| 38 | Password reset — change password | Click reset link, submit new password | Account.password updated. All sessions revoked (except current). Redirected to login. | P4 |
| 39 | Password reset — all memberships affected | After reset, login to Store A and Store B | Same new password works for both. | P4 |
| 40 | Email verification | Click verification link | Account.email_verified_at set. All memberships benefit. | P4 |
| 41 | Email verification — unverified | Attempt login without verifying email | Blocked by MustVerifyEmail middleware. | P4 |

#### Store Switching

| # | Scenario | Steps | Expected Result | Phase |
|---|---|---|---|---|
| 42 | Store switching | POST /select-tenant with valid membership_id | Session updated. Redirected to new store's dashboard. | P6 |
| 43 | Store switching (invalid membership) | POST /select-tenant with membership_id belonging to different Account | 403 Forbidden. | P6 |
| 44 | Store switching (suspended membership) | POST /select-tenant with suspended membership | 403 Forbidden. | P6 |
| 45 | Tenant selector page | GET /select-tenant with 3 memberships | List shows 3 stores with name, logo, role. | P6 |

#### Ownership Transfer

| # | Scenario | Steps | Expected Result | Phase |
|---|---|---|---|---|
| 46 | Ownership transfer | Owner transfers to active admin member | Current.is_owner = false. Target.is_owner = true. Both parties notified. | P6 |
| 47 | Ownership transfer (non-member target) | Owner tries to transfer to non-member | Validation error: "Target user is not a member of this store." | P6 |
| 48 | Ownership transfer (suspended target) | Owner tries to transfer to suspended member | Validation error: "Target membership is not active." | P6 |
| 49 | Post-transfer permissions | Previous owner accesses admin after transfer | 200 OK (still has admin role). But cannot access owner-only features (billing, subscription, staff management). | P6 |

#### Membership Status

| # | Scenario | Steps | Expected Result | Phase |
|---|---|---|---|---|
| 50 | Membership suspension | Owner sets Membership.status = 'suspended' | User cannot login to this tenant. Other tenants unaffected. | P6 |
| 51 | Membership reactivation | Owner sets Membership.status = 'active' | User can login to this tenant again. | P6 |
| 52 | Membership removal | Owner removes Membership | Membership.status = 'removed' or soft-deleted. User cannot access this tenant. Audit trail preserved. | P6 |
| 53 | Cross-tenant status isolation | Membership suspended in Store A, active in Store B | Login to Store B works. Login to Store A fails with suspension message. | P6 |

#### Notifications

| # | Scenario | Steps | Expected Result | Phase |
|---|---|---|---|---|
| 54 | Admin notification | New order placed in Store A | All admin memberships of Store A receive notification. Admin of Store B does not receive it. | P7 |
| 55 | Customer notification | Order status changed | The specific customer Account receives notification regardless of which membership they used. | P7 |
| 56 | Tenant-scoped notification list | Admin views notifications in Store A dashboard | Only notifications with data->tenant_id = Store A are shown. | P7 |
| 57 | Cross-tenant notification isolation | Admin of Store A and Store B views notifications | Store A dashboard shows only Store A notifications. Store B dashboard shows only Store B notifications. | P7 |
| 58 | Account-level notification | Password reset notification | Visible in global notification list (null tenant_id). | P7 |

#### Session

| # | Scenario | Steps | Expected Result | Phase |
|---|---|---|---|---|
| 59 | Concurrent sessions | Login from two browsers | Two separate sessions. Both valid. | P4 |
| 60 | Concurrent tenant tabs | Tab 1: Store A admin. Tab 2: Store B storefront. | Each tab maintains its own tenant context. No interference. | P4 |
| 61 | Session revocation | Revoke all sessions for an Account | All sessions deleted (except current). User must re-authenticate on other devices. | P7 |
| 62 | Session after password reset | Reset password | All sessions revoked (except current). Other devices logged out. | P4 |

#### Tenant Isolation

| # | Scenario | Steps | Expected Result | Phase |
|---|---|---|---|---|
| 63 | Cross-tenant product isolation | Account A (Store A admin) accesses /store/{B}/admin/products | 403 or redirect. No access to Store B data. | P5 |
| 64 | Cross-tenant order isolation | Account A (Store A customer) accesses /store/{B}/orders | 403 or redirect. No access to Store B orders. | P5 |
| 65 | API cross-tenant | API call with X-Tenant header pointing to non-membership tenant | 403 Forbidden. | P5 |
| 66 | Direct URL manipulation | User changes ID in URL to access another tenant's resource | ValidateTenantBinding middleware blocks it. | P5 |

### 28.2 Manual QA Checklist

The following manual tests should be performed after each phase deployment:

#### Phase 4 (Authentication) Manual QA

- [ ] Register as merchant — verify tenant created, membership created, logged in
- [ ] Log out and log in again — verify session persists
- [ ] Register as customer in a store — verify membership with customer role
- [ ] Log in to different store with same email — verify membership in new store
- [ ] SuperAdmin login — verify no tenant context, access to superadmin dashboard
- [ ] Password reset — verify email received, password changed, can login with new password
- [ ] Email verification — verify verification link works, cannot access protected pages before verification
- [ ] Verify "remember me" works across browser restarts

#### Phase 5 (Authorization) Manual QA

- [ ] Admin routes accessible with admin role
- [ ] Customer routes accessible with customer role
- [ ] Staff with limited role — verify only permitted actions work
- [ ] Permission denied for wrong role — verify 403
- [ ] Owner bypass — verify owner can access all admin functions even with customer role
- [ ] SuperAdmin bypass — verify superadmin can access any tenant
- [ ] Suspended membership — verify access denied with clear message

#### Phase 6 (Registration & Invitation) Manual QA

- [ ] Invite staff with existing email — verify notification received, acceptance works
- [ ] Invite staff with new email — verify registration link, acceptance creates membership
- [ ] Invite to wrong Account — verify rejected
- [ ] Expired invitation — verify error message
- [ ] Store switcher — verify list shows correct memberships, switching works
- [ ] Ownership transfer — verify transfer completes, new owner has full access, previous owner loses owner access

#### Phase 7 (Notifications & Audit) Manual QA

- [ ] New order notification received by all admin memberships
- [ ] Customer receives order status notification
- [ ] Notification list filtered by current tenant context
- [ ] Cross-tenant notification isolation verified
- [ ] Audit log entries for login, logout, membership change, role change, invitation, ownership transfer

### 28.3 Regression QA

After each phase deployment, run:

- [ ] Full existing test suite (`phpunit` or equivalent)
- [ ] Auth tests (`tests/Feature/Auth/`)
- [ ] Storefront tests (`tests/Feature/Storefront*`)
- [ ] Billing tests (`tests/Feature/AdminBillingPageTest.php`)
- [ ] Subscription tests (`tests/Feature/Subscription*`)
- [ ] Payment tests (`tests/Feature/TransactionFoundationTest.php`, `ManualPayment*`)
- [ ] Marketing tests (`tests/Feature/MarketingFeatureTest.php`)
- [ ] User management tests (`tests/Feature/UserManagementTest.php`, `MerchantManagementTest.php`)
- [ ] Role management tests (`tests/Feature/RoleManagementTest.php`)

### 28.4 Production QA

Before production deployment:

- [ ] All 66 checklist scenarios pass
- [ ] All 33 existing test files pass
- [ ] Manual QA for each module in the backward compatibility matrix
- [ ] Cross-tenant isolation verified with real data
- [ ] Rollback plan tested on staging
- [ ] Performance benchmark: authentication response time, permission check overhead
- [ ] Security review: OWASP top 10 for auth endpoints
- [ ] Load test: concurrent registrations, logins, password resets

---

## 32. Phase-by-Phase Roadmap — REFINED

The v1 roadmap is preserved with these adjustments:

### Phase 3: Data Migration — Additional Requirements

- **Owner integrity check**: Verify every tenant has exactly one Membership with `is_owner = true` after migration. If a tenant has zero owners (data integrity issue), assign the oldest admin User as owner and log a warning.
- **Notification migration**: Update existing notification records' `notifiable_type` from `App\Models\User` to `App\Models\Account` (or add a backward-compatible database view).
- **Session compatibility**: Existing sessions store `user_id`. Add `account_id` alongside it during transition phase.

### Phase 5: Authorization — Simplified

Permission overrides are removed from this phase. The authorization logic is strictly role-based:

```php
Gate::before(function (Account $account, string $ability) {
    if ($account->hasRole('superadmin')) return true;
    $membership = $account->currentMembership();
    if (! $membership || $membership->status !== 'active') return false;
    if ($membership->is_owner) return true;
    return $membership->role->hasPermissionTo($ability);
});
```

No StaffProfile permission overrides. No merge logic. No additional database queries beyond the role relationship.

### Phase 7: Notifications — Expanded

- Add `tenant_id` to notification payload JSON for all tenant-scoped notifications
- Frontend filters notification list by current tenant context
- Null `tenant_id` in payload = global notification (password reset, email verification)
- Add audit log entries for notification delivery (future: read receipts)

### Phase 9: Production QA — Expanded

- Add the testing strategy from section 28 as the QA checklist
- Add the backward compatibility matrix from section 27 as the regression checklist

---

## 33. Self Review — NEW

### 33.1 Architectural Contradictions

**Contradiction identified and resolved:**

**v1 issue:** `tenant_memberships.account_id` was specified as `CASCADE` on delete. However, if an Account is soft-deleted, CASCADE would delete all Memberships, losing the audit trail of who owned which stores.

**v2 resolution:** `tenant_memberships.account_id` uses `SET NULL` on delete (when hard-deleted, which should never happen). For soft-delete, the Membership is preserved. The `deleted_at` on Account prevents authentication, but the Membership record remains for historical reference.

**Contradiction identified and resolved:**

**v1 issue:** Permission overrides in `staff_profiles` contradicted the principle "Permissions are never assigned directly to Accounts or Memberships." The override field effectively created a second permission assignment mechanism.

**v2 resolution:** Permission overrides are moved to v5. In v3, permission assignment is strictly through roles only. This eliminates the contradiction.

### 33.2 Hidden Complexity

| Area | Complexity | Mitigation |
|---|---|---|
| **Session tenant context** | Medium | Each request must resolve tenant context from URL, session, or membership. The middleware stack handles this, but edge cases (direct URL manipulation, concurrent tabs) must be tested thoroughly. |
| **Notification tenant isolation** | Medium | Every notification payload must include `tenant_id`. Frontend must filter by current context. Migration of existing notifications requires updating their data field. |
| **Data migration** | High | 131 existing migrations. `users` table referenced by many foreign keys. The migration must create Accounts, Memberships, and Profiles while preserving all existing relationships. |
| **Backward compatibility layer** | Medium | The `User` model must continue to work during transition. A wrapper or alias pattern is needed for third-party packages and legacy code references. |
| **Password reset during transition** | Medium | During dual-read phase, password reset must update both `users.password` and `accounts.password`. If only one is updated, the other becomes stale. |

### 33.3 Future Scalability Concerns

| Concern | Assessment | Mitigation |
|---|---|---|
| **Millions of Memberships** | `tenant_memberships` table will grow with platform adoption. Indexes on (account_id, tenant_id) and (tenant_id, is_owner) are critical. | Add composite indexes during Phase 1. Monitor query performance. |
| **Permission check performance** | Each `Gate::before()` call loads the Membership, Role, and Permission relationships. With Spatie's caching, this is a single query per request. | Lazy-load Membership during authentication (not on every Gate call). Use Spatie's permission cache. |
| **Notification storage** | Notifications table grows without bound. | Implement notification archival/cleanup (future). Soft-delete old notifications. |
| **Session table size** | Concurrent sessions for millions of accounts. | Use Redis for session driver (production). Implement session cleanup for expired entries. |

### 33.4 Migration Risks (Additional to v1)

| Risk | Severity | Mitigation |
|---|---|---|
| **Users table FK references** | High | The `users` table is referenced by many tables (orders, customer_addresses, wishlists, etc.). Migration must keep `users` table readable during transition. New records should write to both `accounts` and `users` (dual-write). |
| **Polymorphic notification records** | Medium | Existing notification records reference `App\Models\User`. After migration, new notifications reference `App\Models\Account`. PHP polymorphic queries (where `notifiable_type = $model::class`) will return different model classes. |
| **Session invalidation** | Medium | If session user_id → account_id mapping is incorrect, users are logged out. |
| **Ownerless tenants** | Low | Data integrity issue if migration finds tenants with no owner. |
| **Concurrent migration** | Medium | During migration (Phase 3), if new Users are created via existing registration flow while migration is running, they may be missed or duplicated. |

### 33.5 Security Concerns

| Concern | Assessment | Mitigation |
|---|---|---|
| **Gate::before() bypass** | The owner bypass grants full permissions. If a Membership is incorrectly marked as `is_owner = true`, that Account has unrestricted access. | Strict validation on `is_owner` assignment. Only the owner transfer flow (or SuperAdmin) can set it. |
| **Tenant context confusion** | If the middleware stack incorrectly resolves the tenant (e.g., falls back to a default tenant when none is specified), cross-tenant data access is possible. | Always require explicit tenant context. Never fall back to a default tenant for authenticated routes. |
| **Signed URL replay** | Invitation signed URLs can be replayed until expiry. | Store `invited_at` in the URL and verify it matches the current membership's `invited_at`. |
| **Notification tenant_id spoofing** | If a notification is created with incorrect `tenant_id`, it appears in the wrong tenant's notification list. | The `tenant_id` in notification payload must always come from the Tenant model, never from user input. |

### 33.6 Maintainability Concerns

| Concern | Assessment | Mitigation |
|---|---|---|
| **Three order creation controllers** | The v1 QA audit identified duplicate order creation logic in `OrderController`, `StorefrontCheckoutController`, and `ClientOrderController`. The identity migration does not fix this. | A separate "Order Extraction" refactor should be planned. It is out of scope for this identity architecture. |
| **Inertia shared data** | The `HandleInertiaRequests` middleware shares `auth.user` to all pages. After migration, this becomes `auth.account` + `auth.currentMembership`. All frontend components that access `auth.user.*` fields must be updated. | Provide backward-compatible accessors on Account so that `auth.user.name`, `auth.user.email`, etc. still work during transition. |
| **Spatie dependency** | The project depends on Spatie Permission for role/permission management but bypasses its primary API (HasRoles trait). Custom Gate::before() replaces Spatie's built-in Gate integration. | Document this design decision clearly. Future developers must understand that Spatie is used for data storage (role/permission tables) but not for runtime authorization checks. |

---

## Final Engineering Recommendation

*Unchanged from v1.* The Account + Membership architecture is correct for this multi-tenant platform. Implement Phase 1 and Phase 2 as a merged sprint. Deploy Phase 4 and Phase 5 together. Keep the `users` table for one full release cycle. Use the backward compatibility matrix (Section 27) as the migration checklist and the testing strategy (Section 28) as the QA gate.

---

*This document supersedes `docs/identity-architecture-lock-v1.md`. All future implementation phases must follow this v2 specification without redesigning the foundation. Any proposed changes to this architecture must go through a formal architecture review process.*
