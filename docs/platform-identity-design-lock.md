# Platform Identity Design Lock

**Sprint**: 6.3.1
**Date**: 2026-07-12
**Status**: LOCKED — Architecture Reference
**Authority**: Principal Laravel SaaS Architect

> This document is the **official architecture reference** for the platform's identity system.
> Every future implementation must follow this document.
> Only minimal code/config updates are permitted to align with this architecture.
> The Platform Identity refactor is NOT implemented in this sprint — only defined and locked.

---

## 1. Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         PLATFORM LAYER                                  │
│                                                                         │
│  ┌──────────────────────────┐    ┌──────────────────────────────────┐  │
│  │    accounts (CANONICAL)   │    │   model_has_roles               │  │
│  │  - Platform identity root │◄───│   (Spatie — superadmin global)  │  │
│  │  - Authentication root    │    └──────────────┬───────────────────┘  │
│  │  - SuperAdmin resolution  │                   │                      │
│  └───────────┬──────────────┘            ┌───────▼──────────┐          │
│              │                           │      roles       │          │
│              │ has many                  │  (global + per-  │          │
│              │                           │   tenant scoped) │          │
│              │                           └───────┬──────────┘          │
│              │                           ┌───────▼──────────┐          │
│              │                           │   permissions    │          │
│              │                           │   (global)       │          │
│              │                           └──────────────────┘          │
│  ┌───────────▼──────────────────────────────────────────────────────┐  │
│  │                    tenant_memberships                             │  │
│  │  - account_id (FK → accounts)                                    │  │
│  │  - tenant_id  (FK → tenants)                                     │  │
│  │  - role_id    (FK → roles)                                       │  │
│  │  - is_owner   (boolean)                                          │  │
│  │  - status     (active, suspended, etc.)                          │  │
│  └───────────┬──────────────┬───────────────────────────────────────┘  │
│              │              │                                          │
│  ┌───────────▼───┐  ┌──────▼──────────────────┐                      │
│  │   profiles    │  │   sessions              │                      │
│  │  - customer   │  │   - user_id (nullable)  │                      │
│  │  - staff      │  │   - account_id (null.)  │                      │
│  │  - merchant   │  └─────────────────────────┘                      │
│  └───────────────┘                                                    │
└─────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────┐
│                         TENANT LAYER                                    │
│                                                                         │
│  ┌────────────────┐                                                     │
│  │    tenants      │  All business data tables carry tenant_id FK:      │
│  │  - id           │  products, orders, categories, brands, units,     │
│  │  - slug         │  coupons, promotions, payment_methods, cities,    │
│  │  - domain       │  townships, settings, website_infos, messages,    │
│  │  - status       │  notifications, wishlists, customer_addresses,   │
│  │  - subscription │  telegram_integrations, activity_logs, etc.       │
│  └────────────────┘                                                     │
└─────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────┐
│                         LEGACY LAYER (DEPRECATED)                       │
│                                                                         │
│  ┌──────────────────┐                                                   │
│  │     users         │  Being deprecated. Still stores identity records │
│  │  - tenant_id      │  for backward compatibility. Will be removed    │
│  │  - is_owner       │  after all relationships are migrated to        │
│  │  - is_admin       │  polymorphic Account-based lookups.             │
│  └──────────────────┘                                                   │
└─────────────────────────────────────────────────────────────────────────┘
```

### Identity Resolution Flow

```
Request → IdentifyTenant Middleware
  │
  ├─ Auth::guard('web')->check()   → User model (legacy)
  │   └─ user.tenant_id → Tenant
  │
  ├─ Auth::guard('accounts')->check() → Account model (canonical)
  │   ├─ SuperAdmin? → bypass tenant (platform identity)
  │   └─ Account → TenantMembership → Tenant
  │
  └─ No auth → subdomain / header / session / Default Store fallback
```

### Authorization Resolution Flow

```
Account::hasRole('admin')
  │
  ├─ SuperAdmin (model_has_roles)? → true (global bypass)
  │
  └─ Account → getCurrentMembership() → TenantMembership
       ├─ membership.role.name matches? → true
       └─ membership.is_owner? → implies admin → true

Account::hasPermissionTo('products.create')
  │
  ├─ SuperAdmin? → true (global bypass)
  │
  └─ Account → getCurrentMembership() → TenantMembership
       ├─ is_owner? → true (owner has all permissions)
       └─ membership.role → role_has_permissions → check
```

---

## 2. Login Matrix

| Login Path | Guard | Model | Tenant Context | Purpose |
|---|---|---|---|---|
| `/superadmin/login` | `web` / `accounts` | `User` / `Account` | None | Platform SuperAdmin login |
| `/admin/login` | `web` / `accounts` | `User` / `Account` | None | Legacy admin login (fallback) |
| `/store/{slug}/login` | `web` / `accounts` | `User` / `Account` | Storefront | Tenant customer login |
| `/store/{slug}/admin/login` | `web` / `accounts` | `User` / `Account` | Storefront | Tenant admin/owner login |

**Guard Selection**: The `config('identity.use_accounts')` flag determines which guard is active. When `true`, the `accounts` guard is used; when `false`, the `web` guard is used. Both guards use session-based authentication.

**Login Credentials**:
- SuperAdmin: `admin@shop.com` / `password`
- Tenant Owners: `owner@{slug}.com` / `password` (demo data)
- Customers: `john@example.com` / `password` (demo data)

---

## 3. Redirect Matrix

| Identity | Login Route | Post-Login Redirect | Session Guard |
|---|---|---|---|
| **SuperAdmin** | `/superadmin/login` | `/superadmin` (SuperAdmin Dashboard) | `accounts` or `web` |
| **Tenant Owner/Admin** | `/store/{slug}/admin/login` | `/store/{slug}/admin/dashboard` | `accounts` or `web` |
| **Tenant Staff** | `/store/{slug}/admin/login` | `/store/{slug}/admin/dashboard` | `accounts` or `web` |
| **Customer** | `/store/{slug}/login` | `/store/{slug}/customer/account` | `accounts` or `web` |
| **Guest** | `/store/{slug}/register` | Email verification → `/store/{slug}/onboarding/complete` | N/A |

**Fallback Redirects**:
- Expired subscription → `/admin/expired` or `/store/{slug}/admin/expired`
- Suspended tenant → `/admin/suspended` or `/store/{slug}/admin/suspended`
- Locked store → read-only access, mutation blocked with error message
- Cross-tenant access attempt → logout + redirect to login with error

---

## 4. Guard Matrix

### Active Guards

| Guard | Driver | Provider | Model | Purpose | Session Column |
|---|---|---|---|---|---|
| `web` | `session` | `users` | `App\Models\User` | Legacy identity authentication | `user_id` |
| `accounts` | `session` | `accounts` | `App\Models\Account` | Canonical identity authentication | `account_id` |

### Guard Selection Logic

The `IdentifyTenant` middleware resolves which guard is active:

```php
if (Auth::guard('web')->check()) {
    Auth::shouldUse('web');
} elseif (Auth::guard('accounts')->check()) {
    Auth::shouldUse('accounts');
}
```

### Password Brokers

| Broker | Provider | Token Table |
|---|---|---|
| `users` | `users` | `password_reset_tokens` |
| `accounts` | `accounts` | `password_reset_tokens_new` |

### Architectural Decision: Platform Guard (Future)

A dedicated `platform` guard is **recommended but not required** for Phase 7. The current architecture achieves platform isolation through:
- SuperAdmin role check (`isSuperAdmin()`)
- Route-level middleware (`role:superadmin`)
- Controller-level authorization

A `platform` guard would provide:
- Dedicated session namespace (no session collision with tenant guards)
- Dedicated password broker
- Cleaner middleware declarations

**Decision**: Deferred to Phase 7 implementation. Current architecture is functionally correct.

---

## 5. Database Ownership Matrix

### Platform Tables (No `tenant_id`)

| Table | Owner | Purpose | Canonical Source | Legacy Status |
|---|---|---|---|---|
| `accounts` | Platform | Identity root — authentication, global roles (superadmin) | **CANONICAL** | No |
| `users` | Platform (legacy) | Original identity table — being deprecated | Legacy | **DEPRECATED** |
| `tenants` | Platform | Tenant/store container | **CANONICAL** | No |
| `tenant_memberships` | Platform | Links Account → Tenant with role | **CANONICAL** | No |
| `roles` | Platform | Spatie roles (global + per-tenant via `tenant_id`) | **CANONICAL** | No |
| `permissions` | Platform | Spatie permissions (global) | **CANONICAL** | No |
| `model_has_roles` | Platform | Spatie pivot (global roles: superadmin) | **CANONICAL** | No |
| `model_has_permissions` | Platform | Spatie pivot (direct permissions) | **CANONICAL** | No |
| `password_reset_tokens` | Platform | Legacy password resets (users) | Legacy | **DEPRECATED** |
| `password_reset_tokens_new` | Platform | Account password resets | **CANONICAL** | No |
| `sessions` | Platform | Auth sessions — stores both `user_id` and `account_id` | **CANONICAL** | No |
| `social_accounts` | Platform | OAuth/social login links | **CANONICAL** | No |
| `platform_settings` | Platform | System-wide platform settings | **CANONICAL** | No |
| `plans` | Platform | Subscription plan definitions | **CANONICAL** | No |
| `plan_features` | Platform | Plan feature definitions | **CANONICAL** | No |
| `subscriptions` | Platform | Tenant subscriptions | **CANONICAL** | No |
| `subscription_audit_logs` | Platform | Subscription change audit trail | **CANONICAL** | No |
| `billing_payment_methods` | Platform | Platform billing methods | **CANONICAL** | No |
| `payment_intents` | Platform | Platform payment intents | **CANONICAL** | No |
| `payment_transactions` | Platform | Platform payment records | **CANONICAL** | No |
| `payment_evidences` | Platform | Platform payment proof | **CANONICAL** | No |
| `payment_reviews` | Platform | Platform payment verification | **CANONICAL** | No |
| `payment_timeline_events` | Platform | Platform payment timeline | **CANONICAL** | No |
| `payment_comments` | Platform | Platform payment comments | **CANONICAL** | No |
| `ledger_entries` | Platform | Financial ledger | **CANONICAL** | No |
| `webhook_logs` | Platform | Webhook audit trail | **CANONICAL** | No |
| `reference_numbers` | Platform | Reference number generation | **CANONICAL** | No |

### Tenant Tables (With `tenant_id`)

| Table | Owner | Purpose | Canonical Source | Legacy Status |
|---|---|---|---|---|
| `customer_profiles` | Tenant | Customer display name, phone | **CANONICAL** | No |
| `staff_profiles` | Tenant | Staff position, department | **CANONICAL** | No |
| `merchant_profiles` | Tenant | Business name, tax ID | **CANONICAL** | No |
| `products` | Tenant | Product catalog | **CANONICAL** | No |
| `product_variants` | Tenant | Product variants | **CANONICAL** | No |
| `product_combos` | Tenant | Product bundles | **CANONICAL** | No |
| `orders` | Tenant | Customer orders | **CANONICAL** | No |
| `order_items` | Tenant | Order line items | **CANONICAL** | No |
| `categories` | Tenant | Product categories | **CANONICAL** | No |
| `brands` | Tenant | Product brands | **CANONICAL** | No |
| `units` | Tenant | Product units | **CANONICAL** | No |
| `coupons` | Tenant | Discount coupons | **CANONICAL** | No |
| `promotions` | Tenant | Promotional campaigns | **CANONICAL** | No |
| `promotion_banners` | Tenant | Promotional banners | **CANONICAL** | No |
| `payment_methods` | Tenant | Tenant payment methods | **CANONICAL** | No |
| `cities` | Tenant | Shipping cities | **CANONICAL** | No |
| `townships` | Tenant | Shipping townships | **CANONICAL** | No |
| `settings` | Tenant | Tenant settings | **CANONICAL** | No |
| `website_infos` | Tenant | Storefront website info | **CANONICAL** | No |
| `messages` | Tenant | Chat messages | **CANONICAL** | No |
| `notifications` | Tenant | User notifications | **CANONICAL** | No |
| `wishlists` | Tenant | Customer wishlists | **CANONICAL** | No |
| `customer_addresses` | Tenant | Customer shipping addresses | **CANONICAL** | No |
| `telegram_integrations` | Tenant | Telegram bot integrations | **CANONICAL** | No |
| `activity_logs` | Tenant | Audit trail | **CANONICAL** | No |

### Relationship Tables

| Table | Owner | Purpose | Canonical Source | Legacy Status |
|---|---|---|---|---|
| `order_coupon` | Tenant | Order-coupon pivot | **CANONICAL** | No |
| `coupon_product` | Tenant | Coupon-product pivot | **CANONICAL** | No |
| `coupon_category` | Tenant | Coupon-category pivot | **CANONICAL** | No |
| `promotion_product` | Tenant | Promotion-product pivot | **CANONICAL** | No |
| `promotion_category` | Tenant | Promotion-category pivot | **CANONICAL** | No |
| `promotion_usages` | Tenant | Promotion usage tracking | **CANONICAL** | No |
| `order_override_logs` | Tenant | Order override audit | **CANONICAL** | No |

---

## 6. Seeder Strategy

### Production Seeders (Required for all environments)

| Seeder | Data Type | Creates | Notes |
|---|---|---|---|
| `PermissionSeeder` | Production | Spatie permissions (global) | Required — defines all permission names |
| `RoleAndPermissionSeeder` | Production | Global roles (`superadmin`, `admin`, `customer`) + SuperAdmin Account | Required — creates `admin@shop.com` |
| `PlanSeeder` | Production | Subscription plans and features | Required — defines available plans |
| `PlatformSettingSeeder` | Production | Platform settings record | Required — singleton platform config |
| `BillingPaymentMethodSeeder` | Production | Platform billing payment methods | Required — billing infrastructure |

### Tenant Bootstrap Seeders (Run per-tenant)

| Seeder | Data Type | Creates | Notes |
|---|---|---|---|
| `TenantSeeder` | Demo | Default Store (ID=1) + 2 test tenants | Creates tenants + backfills `tenant_id` |
| `MembershipSeeder` | Demo | Owner memberships for each tenant | Creates `tenant_memberships` + tenant-scoped roles |
| `WebsiteSettingsSeeder` | Demo | Website info for Default Store | Tenant-scoped |
| `PaymentMethodSeeder` | Demo | Payment methods for Default Store | Tenant-scoped |
| `CategorySeeder` | Demo | Product categories | Tenant-scoped |
| `UnitSeeder` | Demo | Product units | Tenant-scoped |
| `BrandSeeder` | Demo | Product brands | Tenant-scoped |

### Demo Data Seeders (Optional — development/testing only)

| Seeder | Data Type | Creates | Notes |
|---|---|---|---|
| `UserSeeder` | Demo | 10 customer users + accounts | Creates both `users` and `accounts` records |
| `ProductSeeder` | Demo | Sample products | Tenant-scoped |
| `OrderSeeder` | Demo | Sample orders | Tenant-scoped |
| `DemoDataSeeder` | Demo | Wrapper for User + Product + Order seeders | Convenience seeder |
| `LocationSeeder` | Demo | Cities and townships | Tenant-scoped |

### Default Store Strategy

**Default Store** (`tenants.id=1`, `slug='default'`):
- **Purpose**: Migration artifact — provides fallback tenant for legacy data
- **Production Status**: Should be phased out — no legitimate owner
- **Demo Status**: Used as primary demo tenant
- **Owner**: Created by `MembershipSeeder` as `owner@defaultstore.com`
- **Subscription**: No subscription assigned (free plan by default)

**Test Tenants** (`khine`, `gadget`):
- **Purpose**: Multi-tenant testing
- **Production Status**: Must NOT exist in production
- **Demo Status**: Created by `TenantSeeder`
- **Owner**: Created by `MembershipSeeder` as `owner@khine.com`, `owner@gadget.com`

### Seeder Execution Order

```
1. PermissionSeeder          → permissions table
2. RoleAndPermissionSeeder   → roles + superadmin account
3. PlanSeeder                → plans + plan_features
4. LocationSeeder            → cities + townships (demo)
5. PlatformSettingSeeder     → platform_settings
6. BillingPaymentMethodSeeder → billing_payment_methods
7. WebsiteSettingsSeeder     → website_infos (demo)
8. PaymentMethodSeeder       → payment_methods (demo)
9. CategorySeeder            → categories (demo)
10. UnitSeeder               → units (demo)
11. BrandSeeder              → brands (demo)
12. TenantSeeder             → tenants + tenant_id backfill
13. MembershipSeeder         → tenant_memberships + tenant roles
```

---

## 7. Identity Projection Rules

### Canonical Display Name

| Identity | Resolution Order | Source |
|---|---|---|
| **SuperAdmin** | `accounts.name` → "Super Admin" | `Account::getDisplayName()` |
| **Owner** | `accounts.name` → `merchant_profiles.business_name` → `accounts.email` | Via `getCurrentMembership()` |
| **Admin/Staff** | `accounts.name` → `staff_profiles.name` → `accounts.email` | Via `getCurrentMembership()` |
| **Customer** | `accounts.name` → `customer_profiles.name` → `accounts.email` | Via `getCurrentMembership()` |
| **Legacy User** | `users.name` → `users.email` | Direct column |

### Canonical Email

| Identity | Source | Sync |
|---|---|---|
| Account | `accounts.email` | Syncs to `users.email` via `SyncsIdentity` trait |
| Legacy User | `users.email` | Syncs to `accounts.email` via `SyncsIdentity` trait |

### Canonical Role

| Identity | Resolution Path |
|---|---|
| **SuperAdmin** | `accounts → model_has_roles → roles WHERE name='superadmin'` (global, `tenant_id=NULL`) |
| **Owner** | `accounts → tenant_memberships WHERE is_owner=true` → implies `admin` role |
| **Admin** | `accounts → tenant_memberships → roles WHERE name='admin'` (tenant-scoped) |
| **Staff** | `accounts → tenant_memberships → roles WHERE name='staff'` (tenant-scoped) |
| **Customer** | `accounts → tenant_memberships → roles WHERE name='customer'` (tenant-scoped) |

### Canonical Avatar

| Identity | Source | Fallback |
|---|---|---|
| Account | `accounts.profile_image` via `ImageService::url()` | Default avatar |
| Legacy User | `users.profile_image` via `ImageService::url()` | Default avatar |

### Canonical Permission Source

| Identity | Permission Resolution |
|---|---|
| **SuperAdmin** | All permissions (global bypass) |
| **Owner** | All permissions (membership `is_owner` bypass) |
| **Admin** | `tenant_memberships → roles → role_has_permissions → permissions` |
| **Staff** | `tenant_memberships → roles → role_has_permissions → permissions` |
| **Customer** | `tenant_memberships → roles → role_has_permissions → permissions` |

---

## 8. Platform vs Tenant Responsibilities

### Platform Responsibilities

| Area | Responsibility | Owner |
|---|---|---|
| **Identity** | Account creation, authentication, session management | Platform |
| **SuperAdmin** | Platform administration, tenant management | Platform |
| **Plans** | Subscription plan definitions and features | Platform |
| **Subscriptions** | Tenant subscription lifecycle | Platform |
| **Platform Settings** | System-wide configuration | Platform |
| **Billing** | Platform billing methods, payment processing | Platform |
| **Roles (global)** | SuperAdmin role definition | Platform |
| **Permissions** | Permission definitions (global) | Platform |
| **Audit** | Webhook logs, ledger entries, payment timeline | Platform |

### Tenant Responsibilities

| Area | Responsibility | Owner |
|---|---|---|
| **Storefront** | Store configuration, branding, website info | Tenant |
| **Products** | Product catalog, variants, combos | Tenant |
| **Orders** | Order lifecycle, fulfillment | Tenant |
| **Customers** | Customer management, profiles | Tenant |
| **Staff** | Staff management, role assignment | Tenant |
| **Payments** | Payment methods, verification | Tenant |
| **Promotions** | Coupons, promotions, banners | Tenant |
| **Shipping** | Cities, townships, delivery zones | Tenant |
| **Communication** | Messages, notifications, Telegram integration | Tenant |
| **Roles (tenant-scoped)** | Admin, Staff, Customer role definitions per tenant | Tenant |
| **Activity Logs** | Tenant-scoped audit trail | Tenant |

### Shared Responsibilities

| Area | Platform Role | Tenant Role |
|---|---|---|
| **Roles** | Defines global roles (superadmin) | Defines tenant-scoped roles (admin, staff, customer) |
| **Permissions** | Defines permission names | Assigns permissions to tenant roles |
| **Users** | Manages Account identity | Manages membership and role assignment |

---

## 9. Architectural Invariants

These rules are **permanent** and must **never** be violated:

### INV-1: SuperAdmin is Platform-Only
- SuperAdmin has **no** `tenant_memberships` record
- SuperAdmin has **no** `tenant_id` binding
- SuperAdmin bypasses all tenant isolation middleware
- SuperAdmin accesses `/superadmin/*` routes only
- SuperAdmin must **never** be assigned as Owner/Admin/Staff of any tenant

### INV-2: Account is the Canonical Identity
- `accounts` table is the canonical identity root
- `users` table is deprecated — maintained only for backward compatibility
- All new identity features must target `Account` model
- `SyncsIdentity` trait keeps `User` and `Account` in sync during migration

### INV-3: TenantMembership is the Tenant Authorization Root
- All tenant-scoped authorization flows through `tenant_memberships`
- Role resolution: `Account → TenantMembership → Role → Permission`
- Owner (`is_owner=true`) implicitly has all permissions
- No direct `model_has_roles` entries for tenant-scoped roles (only for superadmin)

### INV-4: Tenant Isolation is Mandatory
- All business data tables carry `tenant_id` FK
- `TenantAware` trait enforces tenant scoping via global scope
- `ValidateTenantBinding` middleware ensures route model binding respects tenant
- `CheckTenantAccess` middleware blocks cross-tenant access
- SuperAdmin is the **only** exception to tenant isolation

### INV-5: Guard Selection is Config-Driven
- `config('identity.use_accounts')` selects active guard
- When `true`: `accounts` guard, `Account` model, `password_reset_tokens_new`
- When `false`: `web` guard, `User` model, `password_reset_tokens`
- Both guards cannot be active simultaneously for the same session

### INV-6: Permissions are Global, Roles are Tenant-Scoped
- Permission names are defined globally in `permissions` table
- Roles are created per-tenant (`roles.tenant_id` is set)
- The `superadmin` role has `tenant_id=NULL` (global)
- Role-permission assignments are global (`role_has_permissions`)

### INV-7: Sessions Store Active Guard
- `sessions` table has both `user_id` and `account_id` columns
- Only one is populated per session, based on which guard authenticated
- `IdentifyTenant` middleware detects active guard from session

### INV-8: Default Store is a Migration Artifact
- Default Store (`tenants.id=1`, `slug='default'`) exists for backward compatibility
- It provides fallback tenant context for legacy data
- It should be phased out in production
- It has a seeded owner via `MembershipSeeder`

---

## 10. Forbidden Patterns

These patterns are **permanently forbidden** and must **never** be implemented:

### FORBID-1: SuperAdmin Tenant Membership
```
❌ SuperAdmin must NEVER have a tenant_memberships record
❌ SuperAdmin must NEVER be assigned Owner/Admin/Staff of any tenant
❌ SuperAdmin must NEVER be scoped to a single tenant
```
**Reason**: SuperAdmin is a platform identity. Tenant membership would defeat tenant isolation and create ambiguity.

### FORBID-2: Direct model_has_roles for Tenant Users
```
❌ Tenant users must NEVER have entries in model_has_roles for tenant-scoped roles
❌ Tenant role resolution must NEVER bypass TenantMembership
```
**Reason**: `model_has_roles` is reserved for global roles (superadmin only). Tenant roles are resolved through `tenant_memberships.role_id`.

### FORBID-3: Cross-Tenant Data Access
```
❌ Tenant A must NEVER access Tenant B's data
❌ Business queries must NEVER omit tenant_id filtering
❌ TenantAware trait must NEVER be removed from tenant-scoped models
```
**Reason**: Tenant isolation is a core security guarantee. Violation exposes customer data across stores.

### FORBID-4: Login-Time User Mutation
```
❌ Login must NEVER mutate user/account records (e.g., setting tenant_id)
❌ Authentication must NEVER have side effects on identity data
```
**Reason**: Login is a read operation. Mutating identity data during authentication creates race conditions and data corruption.

### FORBID-5: Removing Legacy Compatibility Prematurely
```
❌ users table must NOT be dropped until all relationships are migrated
❌ User model must NOT be removed until polymorphic migration is complete
❌ password_reset_tokens must NOT be dropped until legacy broker is removed
```
**Reason**: 13+ model relationships still reference `User::class`. Dropping the `users` table would break all business model relationships.

### FORBID-6: Tenant-Scoped SuperAdmin Routes
```
❌ SuperAdmin routes must NEVER be under /store/{slug}/ prefix
❌ SuperAdmin dashboard must NEVER require tenant context
```
**Reason**: SuperAdmin operates at the platform level, not within any tenant.

### FORBID-7: Hardcoding Guard Names
```
❌ Controllers must NEVER hardcode 'web' or 'accounts' guard names
❌ Use auth()->user() or Auth::user() — let the middleware resolve the guard
```
**Reason**: Guard selection is config-driven. Hardcoding prevents the feature flag from working.

### FORBID-8: Tenant Creation Without Owner
```
❌ Every tenant MUST have exactly one owner membership
❌ Tenants must NEVER exist without a legitimate owner account
```
**Reason**: Ownerless tenants have no legitimate administrator and create orphaned data.

---

## 11. Future Development Rules

### Rule 1: New Identity Features Target Account Model
All new authentication, authorization, and identity features must be implemented against the `Account` model. The `User` model is deprecated and maintained only for backward compatibility.

### Rule 2: New Business Relationships Use Polymorphic
New model relationships that reference identity must use polymorphic relations (`user_type` + `user_id`) to support both `Account` and `User` during migration. Example:
```php
public function user(): MorphTo
{
    return $this->morphTo();
}
```

### Rule 3: New Tenant-Scoped Tables Require tenant_id
Every new table that stores tenant data must include a `tenant_id` foreign key and use the `TenantAware` trait for automatic scoping.

### Rule 4: New Roles Must Be Tenant-Scoped
New role definitions must include `tenant_id` (except global platform roles). The `superadmin` role is the only role with `tenant_id=NULL`.

### Rule 5: New Permissions Are Global
New permission names must be added to `PermissionSeeder`. Permissions are global and assigned to roles via `role_has_permissions`.

### Rule 6: Middleware Must Bypass for SuperAdmin
Any new middleware that enforces tenant restrictions must check `$user->isSuperAdmin()` and bypass for SuperAdmin.

### Rule 7: Seeders Must Create TenantMemberships
Any seeder that creates user/account records must also create corresponding `tenant_memberships` records. Seeders must never leave accounts without memberships (except SuperAdmin).

### Rule 8: Login Must Not Mutate Identity
Authentication controllers must never mutate user/account records during login. Identity data changes must happen in dedicated update endpoints.

### Rule 9: Config Flag Controls Architecture
The `config('identity.use_accounts')` flag must be respected in all code paths that branch between User and Account. New code must support both modes until the flag is permanently set to `true`.

### Rule 10: Document Architectural Decisions
Any deviation from this design lock must be documented as an Architectural Decision Record (ADR) with:
- Problem statement
- Options considered
- Decision made
- Consequences
- Migration path

---

## 12. Phase 7 Readiness

### Prerequisites for Phase 7

Before Phase 7 implementation begins, the following must be completed:

| # | Prerequisite | Status | Notes |
|---|---|---|---|
| 1 | TenantMembership creation in registration flow | Required | `RegisteredUserController` must create membership |
| 2 | TenantMembership seeder for customers | Required | `MembershipSeeder` must handle all test tenants |
| 3 | Owner TenantMembership for Default Store | Required | `MembershipSeeder` creates this |
| 4 | SuperAdmin display name fix | Required | `Account::getDisplayName()` must return "Super Admin" |
| 5 | Account/User ID synchronization | Required | Seeders must enforce `account.id = user.id` |
| 6 | Polymorphic fallback on business models | Required | `Order`, `Message`, `Wishlist` etc. need `account_id` FK |

### Phase 7 Scope

Phase 7 will implement the **Platform Identity refactor** based on this design lock:

1. **Platform Guard** (optional) — Dedicated `platform` guard for SuperAdmin
2. **Account Registration Flow** — TenantMembership creation during registration
3. **Seeder Alignment** — All seeders create proper Account + Membership records
4. **Model Relationship Migration** — Add `account_id` FK to business tables
5. **Dual-Write Strategy** — Write to both `user_id` and `account_id` during transition
6. **Read Migration** — Switch reads from `user_id` to `account_id`
7. **Legacy Removal** — Drop `users` table and `User` model (final step)

### Phase 7 Constraints

- **Do NOT** remove the `users` table until all relationships are migrated
- **Do NOT** remove the `web` guard until all code paths support `accounts`
- **Do NOT** change the `IDENTITY_USE_ACCOUNTS` flag default until Phase 7 is verified
- **Do NOT** modify SuperAdmin's platform-only status
- **Do NOT** add tenant_id to SuperAdmin's identity records
- **Do NOT** remove TenantAware from any tenant-scoped model

### Architecture Verification Checklist

Before Phase 7 is marked complete, verify:

- [ ] Every tenant has exactly one owner membership
- [ ] SuperAdmin has no tenant memberships
- [ ] All business models support polymorphic identity (Account + User)
- [ ] All seeders create Account + Membership records
- [ ] Login flow creates TenantMembership for new registrations
- [ ] Display name resolution works for all identity types
- [ ] Permission resolution works through TenantMembership
- [ ] Cross-tenant access is blocked for all non-SuperAdmin identities
- [ ] Default Store is phased out or has a legitimate owner
- [ ] `IDENTITY_USE_ACCOUNTS=true` works in all environments

---

## Appendix A: Middleware Stack

### Global Middleware (Web Group)

| Order | Middleware | Purpose |
|---|---|---|
| 1 | `IdentifyTenant` | Resolves active guard and tenant context |
| 2 | `HandleInertiaRequests` | Shares data with Inertia frontend |
| 3 | `CheckUserStatus` | Blocks suspended/banned users |
| 4 | `CheckMaintenanceMode` | Shows maintenance page when enabled |

### Route Middleware Aliases

| Alias | Class | Purpose |
|---|---|---|
| `role` | `RoleMiddleware` | Role-based access control |
| `tenant.active` | `EnsureTenantIsActive` | Tenant health check (status + subscription) |
| `tenant.locked` | `CheckStoreLocked` | Blocks mutations on locked stores |
| `tenant.valid` | `TenantIsValid` | Structural tenant validation |
| `storefront` | `Storefront` | Resolves tenant from URL `store_slug` |
| `tenant.access` | `CheckTenantAccess` | Cross-tenant access guard |
| `tenant.binding` | `ValidateTenantBinding` | Route model binding tenant validation |

### Admin Route Middleware Stack

```
/auth → role:admin → tenant.valid → tenant.binding
  └─ Account routes (billing, expired, suspended)
  └─ tenant.active → tenant.locked
       └─ Operations routes (dashboard, products, orders, etc.)
```

### SuperAdmin Route Middleware Stack

```
/auth → role:superadmin
  └─ All SuperAdmin routes
```

---

## Appendix B: Model Reference

### Core Identity Models

| Model | Table | Purpose |
|---|---|---|
| `Account` | `accounts` | Canonical identity — authentication root |
| `User` | `users` | Legacy identity — deprecated |
| `Tenant` | `tenants` | Tenant/store container |
| `TenantMembership` | `tenant_memberships` | Account-Tenant authorization link |
| `Role` | `roles` | Spatie roles (extends `SpatieRole`) |
| `Permission` | `permissions` | Spatie permissions (global) |

### Profile Models

| Model | Table | Purpose |
|---|---|---|
| `CustomerProfile` | `customer_profiles` | Customer display info |
| `StaffProfile` | `staff_profiles` | Staff position/department |
| `MerchantProfile` | `merchant_profiles` | Business name/tax ID |

### Key Traits

| Trait | Used By | Purpose |
|---|---|---|
| `HasRoles` | `Account`, `User` | Spatie role/permission integration |
| `SyncsIdentity` | `Account`, `User` | Keeps dual-table records in sync |
| `TenantAware` | `Role`, business models | Tenant scoping via global scope |
| `LogsActivity` | `Account`, `User` | Activity audit trail |

---

## Appendix C: Configuration Reference

### `config/auth.php`

```php
'guards' => [
    'web' => ['driver' => 'session', 'provider' => 'users'],
    'accounts' => ['driver' => 'session', 'provider' => 'accounts'],
],
'providers' => [
    'users' => ['driver' => 'eloquent', 'model' => App\Models\User::class],
    'accounts' => ['driver' => 'eloquent', 'model' => App\Models\Account::class],
],
'passwords' => [
    'users' => ['provider' => 'users', 'table' => 'password_reset_tokens'],
    'accounts' => ['provider' => 'accounts', 'table' => 'password_reset_tokens_new'],
],
```

### `config/identity.php`

```php
'use_accounts' => env('IDENTITY_USE_ACCOUNTS', false),      // Master flag
'use_gate_before' => env('IDENTITY_USE_GATE_BEFORE', false),  // Gate migration
'migrate_notifications' => env('IDENTITY_MIGRATE_NOTIFICATIONS', false),
'migrate_billing' => env('IDENTITY_MIGRATE_BILLING', false),
'migrate_payments' => env('IDENTITY_MIGRATE_PAYMENTS', false),
'migrate_orders' => env('IDENTITY_MIGRATE_ORDERS', false),
```

---

**END OF DESIGN LOCK DOCUMENT**

This document is effective immediately and supersedes all previous architectural notes.
Any changes require an Architectural Decision Record (ADR) approved by the project lead.
