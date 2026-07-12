# Platform Identity Audit — Architecture Verification Report

**Date**: 2026-07-12
**Scope**: Sprint A — Platform Identity Architecture Verification
**Role**: Lead Laravel SaaS Architect

---

## 1. Current Architecture Diagram

```
                        ┌──────────────────────────────────────┐
                        │           PLATFORM LAYER              │
                        │                                      │
                        │  ┌──────────┐    ┌─────────────────┐ │
                        │  │  users    │    │   accounts       │ │
                        │  │ (legacy)  │    │  (new canonical) │ │
                        │  └─────┬────┘    └────────┬────────┘ │
                        │        │                  │           │
                        │        │   ┌──────────────┘           │
                        │        │   │                          │
                        │  ┌─────▼───▼──────────────────┐      │
                        │  │   model_has_roles           │      │
                        │  │   (Spatie - global role     │      │
                        │  │    assignment table)        │      │
                        │  └────────────┬───────────────┘      │
                        │               │                        │
                        │        ┌──────▼─────────────┐         │
                        │        │      roles          │         │
                        │        │  (Spatie - global)  │         │
                        │        └──────┬─────────────┘         │
                        │               │                        │
                        │        ┌──────▼─────────────┐         │
                        │        │    permissions      │         │
                        │        │  (Spatie - global)  │         │
                        │        └────────────────────┘         │
                        └──────────────────────────────────────┘

                        ┌──────────────────────────────────────┐
                        │           TENANT LAYER                │
                        │                                      │
                        │  ┌────────────┐                      │
                        │  │   tenants   │                      │
                        │  │ (Default    │                      │
                        │  │  Store=ID1) │                      │
                        │  └──────┬─────┘                      │
                        │         │                             │
                        │  ┌──────▼──────────────┐             │
                        │  │  tenant_memberships   │             │
                        │  │  - account_id         │             │
                        │  │  - tenant_id          │             │
                        │  │  - role_id            │             │
                        │  │  - is_owner           │             │
                        │  └──────┬──────┬───────┘             │
                        │         │      │                       │
                        │  ┌──────▼┐ ┌───▼──────────────┐      │
                        │  │ roles │ │ customer_profiles │      │
                        │  │(per   │ │ staff_profiles    │      │
                        │  │tenant)│ │ merchant_profiles │      │
                        │  └───────┘ └──────────────────┘      │
                        │                                      │
                        │   Data entities (tenant_id FK):       │
                        │   products, orders, categories,       │
                        │   coupons, promotions, settings,      │
                        │   payment_methods, website_infos,     │
                        │   telegram_integrations, etc.         │
                        └──────────────────────────────────────┘
```

---

## 2. Target Architecture Diagram

```
                        ┌──────────────────────────────────────┐
                        │           PLATFORM LAYER              │
                        │                                      │
                        │  ┌──────────────────────────┐       │
                        │  │    accounts (CANONICAL)   │       │
                        │  │  - Platform identity      │       │
                        │  │  - Authentication root    │       │
                        │  │  - SuperAdmin resolution  │       │
                        │  └───────────┬──────────────┘       │
                        │              │                        │
                        │              │ (has many)             │
                        └──────────────┼────────────────────────┘
                                       │
                        ┌──────────────┼────────────────────────┐
                        │              │                         │
                        │  ┌───────────▼──────────────────┐    │
                        │  │    tenant_memberships        │    │
                        │  │  - account_id (FK accounts)  │    │
                        │  │  - tenant_id (FK tenants)    │    │
                        │  │  - role_id (FK roles)        │    │
                        │  │  - is_owner (boolean)        │    │
                        │  └───────────┬──────┬──────────┘    │
                        │              │      │                │
                        │     ┌────────▼┐ ┌──▼─────────┐     │
                        │     │  roles  │ │  profiles  │     │
                        │     │(tenant- │ │(customer,  │     │
                        │     │ scoped) │ │ staff,     │     │
                        │     │         │ │ merchant)  │     │
                        │     └────┬────┘ └────────────┘     │
                        │          │                           │
                        │     ┌────▼──────────┐               │
                        │     │  permissions   │               │
                        │     │  (global)      │               │
                        │     └───────────────┘               │
                        └─────────────────────────────────────┘

              LEGACY ────► users table (deprecated, remove once
                            model relationships migrated)
```

---

## 3. Database Ownership Matrix

| Table | Primary Owner | Purpose | Canonical? | Legacy? | Foreign Keys |
|-------|--------------|---------|-----------|---------|-------------|
| `accounts` | Platform | Identity root — authentication, global roles (superadmin) | **YES** | No | `social_accounts.account_id` |
| `users` | Platform (legacy) | Original identity table — being deprecated | No | **YES** | `tenant_id → tenants`, `plan_id → plans` |
| `tenants` | Platform | Tenant/store container | **YES** | No | None to identity tables |
| `tenant_memberships` | Platform | Links Account → Tenant with role | **YES** | No | `account_id → accounts`, `tenant_id → tenants`, `role_id → roles` |
| `roles` | Platform | Spatie roles (global + per-tenant via `tenant_id`) | **YES** | No | `tenant_id → tenants` (nullable) |
| `permissions` | Platform | Spatie permissions (global) | **YES** | No | None |
| `model_has_roles` | Platform | Spatie pivot (global roles: superadmin) | **YES** | Partial | `role_id → roles`, `model_id` (polymorphic) |
| `model_has_permissions` | Platform | Spatie pivot (direct permissions) | **YES** | No | `permission_id → permissions` |
| `password_reset_tokens` | Platform | Legacy password resets (users) | No | **YES** | None |
| `password_reset_tokens_new` | Platform | Account password resets | **YES** | No | None |
| `sessions` | Platform | Auth sessions — stores both `user_id` and `account_id` | **YES** | No | `user_id → users` (nullable), `account_id → accounts` (nullable) |
| `social_accounts` | Platform | OAuth/social login links | **YES** | No | `account_id → accounts` |
| `customer_profiles` | Tenant | Customer display name, phone | **YES** | No | `tenant_membership_id → tenant_memberships` |
| `staff_profiles` | Tenant | Staff position, department | **YES** | No | `tenant_membership_id → tenant_memberships` |
| `merchant_profiles` | Tenant | Business name, tax ID | **YES** | No | `tenant_membership_id → tenant_memberships` |
| `activity_logs` | Platform | Audit trail | **YES** | Partial | `causer_id` (polymorphic — only `User` today) |

---

## 4. Identity Ownership Matrix

| Identity | Canonical Table | Lookup Path | Legacy Fallback |
|----------|----------------|-------------|----------------|
| **Platform User** | `accounts` | `Account → model_has_roles → Role` for superadmin | `User → model_has_roles → Role` |
| **Tenant User** | `accounts → tenant_memberships` | `Account → TenantMembership (scoped to tenant)` | `User(user.tenant_id)` |
| **Owner** | `tenant_memberships.is_owner` | `Account → TenantMembership where is_owner=true` | `User.is_owner` |
| **Customer** | `tenant_memberships → roles` | `Account → TenantMembership → Role(name=customer)` | `User(model_has_roles → Role)` |
| **Staff** | `tenant_memberships → roles` | `Account → TenantMembership → Role(name=staff)` | `User(model_has_roles → Role)` |
| **SuperAdmin** | `accounts → model_has_roles → Role` | `Account → model_has_roles → Role(name=superadmin)` | `User → model_has_roles → Role(name=superadmin)` |

### Conflict: `users` table still has tenant-scoped data

- `users.tenant_id` — User model is treated as both platform and tenant identity
- `users.is_owner` — Duplicate of `tenant_memberships.is_owner`
- `users.is_admin` — Redundant with Spatie role assignment
- Result: Account mode (`IDENTITY_USE_ACCOUNTS=true`) creates **dual records** — one in `users`, one in `accounts`

---

## 5. Seeder Audit

| Seeder | Creates Legacy? | Creates Account? | Issues |
|--------|----------------|-----------------|--------|
| `PermissionSeeder` | No | No | Clean — only creates Spatie permissions |
| `RoleAndPermissionSeeder` | **YES** | Conditional | Creates superadmin in `users` table; optionally creates matching `Account` only when `IDENTITY_USE_ACCOUNTS=true`. SuperAdmin role assigned to **both** tables. Creates roles: `superadmin`, `admin`, `customer` — all without `tenant_id`. |
| `PlanSeeder` | No | No | Clean — creates plans/features |
| `TenantSeeder` | **YES** | No | **Creates Default Store (tenant ID=1)** as hardcoded data. Backfills all business tables' `null tenant_id` to tenant 1. Creates 2 test tenants (`Khine Electronics`, `Gadget World`). |
| `UserSeeder` | **YES** | Conditional | Creates customers in `users` table; optionally creates matching `Account` only when `IDENTITY_USE_ACCOUNTS=true`. Customer role assigned via Spatie to `users` table. |
| `DemoDataSeeder` | **YES** | No | Wraps UserSeeder + ProductSeeder + OrderSeeder |
| `ProductSeeder` | No | No | Uses factory — creates products |
| `OrderSeeder` | **YES** | No | `User::role('customer')->get()` — queries only `users` table |
| `CategorySeeder` | No | No | Creates categories |
| `PaymentMethodSeeder` | No | No | Creates payment methods |
| `WebsiteSettingsSeeder` | No | No | Creates website settings |
| `UnitSeeder` | No | No | Creates units |
| `BrandSeeder` | No | No | Creates brands |
| `LocationSeeder` | No | No | Creates cities/townships |
| `PlatformSettingSeeder` | No | No | Creates platform settings |
| `BillingPaymentMethodSeeder` | No | No | Creates billing payment methods |

### Key Finding: No Seeder creates `TenantMembership` records

No seeder creates `tenant_memberships` for the SuperAdmin or any customer. In Account mode, the SuperAdmin Account exists without any membership. The Owner membership for the Default Store's owner is never created.

---

## 6. Authentication Audit

### Guards

| Guard | Provider | Model | Purpose |
|-------|----------|-------|---------|
| `web` | `users` | `App\Models\User` | Legacy identity login |
| `accounts` | `accounts` | `App\Models\Account` | New identity login |

### Providers

| Provider | Driver | Model |
|----------|--------|-------|
| `users` | eloquent | `User` (env `AUTH_MODEL`) |
| `accounts` | eloquent | `Account` (env `AUTH_MODEL_ACCOUNT`) |

### Password Brokers

| Broker | Provider | Token Table |
|--------|----------|-------------|
| `users` | `users` | `password_reset_tokens` |
| `accounts` | `accounts` | `password_reset_tokens_new` |

### Switch Logic

The `config('identity.use_accounts')` flag controls which guard is used everywhere:

- `LoginRequest::authenticate()` — switches guard based on flag
- `AuthenticatedSessionController::store()` — reads Account or User based on flag
- `StorefrontLoginController::store()` — same pattern
- `AuthenticatedSessionController::destroy()` — uses flag-selected guard for logout

### Tenant Isolation Issues

1. **StorefrontLoginController:114-116** — In legacy mode (non-accounts), if the user has no `tenant_id`, it **mutates the user record** during login: `$user->update(['tenant_id' => $tenant->id])`. This is a side-effect in the login flow.

2. **IdentifyTenant middleware** — Resolves tenant context. For Account users, it queries `TenantMembership` directly. For User users, it reads `user.tenant_id`. Falls back to subdomain → header → session → Default Store.

3. **CheckTenantAccess middleware** — For Account users, checks `TenantMembership` exists. For User users, compares `user.tenant_id` to current tenant ID. SuperAdmin always bypasses.

### Login Isolation

| Login Path | Guard | Tenant Context | Purpose |
|-----------|-------|---------------|---------|
| `/superadmin/login` | web/accounts | None | SuperAdmin platform login |
| `/admin/login` | web/accounts | None | Legacy admin login (fallback) |
| `/store/{slug}/login` | web/accounts | Storefront | Tenant-specific customer login |
| `/store/{slug}/admin/login` | web/accounts | Storefront | Tenant-specific admin login |

**Finding**: Platform and Tenant login are NOT fully isolated in the routing layer. Both `/superadmin/login` and `/admin/login` route to `AuthenticatedSessionController`. The `StorefrontLoginController` adds tenant-specific validation (blocks cross-tenant and unverified users), but the underlying authentication still goes through `LoginRequest` which uses the same guard for both platform and tenant.

### Missing: Separate Platform Guard

There is no dedicated `platform` guard. Platform and tenant authentication share the same guard mechanism — the only difference is which controller validates the login request.

---

## 7. Relationship Diagram

```
accounts
  ├── social_accounts (hasMany, FK: account_id)
  ├── tenant_memberships (hasMany, FK: account_id)
  │     ├── tenants (belongsTo, FK: tenant_id)
  │     ├── roles (belongsTo, FK: role_id)
  │     ├── customer_profiles (hasOne, FK: tenant_membership_id)
  │     ├── staff_profiles (hasOne, FK: tenant_membership_id)
  │     └── merchant_profiles (hasOne, FK: tenant_membership_id)
  ├── model_has_roles (morphMany, for superadmin global role)
  │     └── roles (morphToMany via Spatie pivot)
  └── sessions (hasMany through auth, FK: account_id)

users (legacy)
  ├── tenants (belongsTo, FK: tenant_id)
  ├── model_has_roles (morphMany, Spatie)
  │     └── roles (morphToMany via Spatie pivot)
  └── sessions (hasMany through auth, FK: user_id)

tenants
  ├── tenant_memberships (hasMany)
  ├── users (hasMany, legacy)
  ├── products (hasMany)
  ├── orders (hasMany)
  ├── subscriptions (hasMany)
  └── [all business tables]

roles
  ├── tenant_memberships (hasMany)
  ├── model_has_roles (hasMany, Spatie pivot)
  ├── accounts (morphedByMany)
  └── role_has_permissions (hasMany)
        └── permissions (belongsToMany)

permissions
  └── role_has_permissions (hasMany)
```

---

## 8. Legacy Dependency List

### Critical — Still references `User` model directly

| File | Line | Dependency |
|------|------|-----------|
| `app/Models/Order.php` | 87 | `belongsTo(User::class)` |
| `app/Models/Message.php` | 29,34 | `belongsTo(User::class, 'sender_id')`, `belongsTo(User::class, 'receiver_id')` |
| `app/Models/CustomerAddress.php` | 37 | `belongsTo(User::class)` |
| `app/Models/Wishlist.php` | 15 | `belongsTo(User::class)` |
| `app/Models/PromotionUsage.php` | 39 | `belongsTo(User::class)` |
| `app/Models/Promotion.php` | 76 | `belongsTo(User::class, 'created_by')` |
| `app/Models/TelegramIntegration.php` | 64 | `belongsTo(User::class)` |
| `app/Models/OrderOverrideLog.php` | 30 | `belongsTo(User::class)` |
| `app/Models/BillingPaymentMethod.php` | 66,71 | `belongsTo(User::class, 'created_by')` |
| `app/Models/ActivityLog.php` | 48,53 | `belongsTo(User::class, 'impersonator_id')` |
| `app/Models/Tenant.php` | 48 | `hasMany(User::class)` |
| `app/Models/Plan.php` | 104 | `hasMany(User::class)` |

### Authentication — Dual-table credential storage

| File | Description |
|------|-------------|
| `database/seeders/RoleAndPermissionSeeder.php` | Creates SuperAdmin in both `users` AND `accounts` |
| `database/seeders/UserSeeder.php` | Creates customers in both `users` AND `accounts` |
| `database/seeders/OrderSeeder.php` | Queries only `User::role('customer')` |

### Config — Feature flag controls architecture

| File | Key | Default |
|------|-----|---------|
| `config/identity.php` | `use_accounts` | `false` |

The entire architecture hangs on a single `.env` flag. If `false`, everything uses legacy `User`. If `true`, everything switches to `Account`. The flag controls which guard, which provider, which password broker, and which model to use in controllers. Emergency break-glass is easy but also means the architecture is never fully committed.

---

## 9. Platform Dependency List

### Platform-exclusive tables (no tenant_id)

| Table | Purpose |
|-------|---------|
| `accounts` | Platform identity root |
| `users` | Legacy identity — being deprecated |
| `platform_settings` | System-wide settings |
| `plans` | Subscription plans |
| `plan_features` | Plan feature definitions |
| `subscriptions` | Tenant subscriptions |
| `subscription_audit_logs` | Subscription changes |
| `billing_payment_methods` | Platform billing |
| `payment_intents` | Platform payments |
| `payment_transactions` | Payment records |
| `payment_evidences` | Payment proof |
| `payment_reviews` | Payment verification |
| `payment_timeline_events` | Payment timeline |
| `payment_comments` | Payment comments |
| `ledger_entries` | Financial ledger |
| `webhook_logs` | Webhook audit trail |
| `reference_numbers` | Reference number generation |

### Tenant-scoped tables (with tenant_id)

All business data: `products`, `orders`, `categories`, `brands`, `units`, `coupons`, `promotions`, `promotion_banners`, `payment_methods`, `cities`, `townships`, `settings`, `website_infos`, `messages`, `notifications`, `telegram_integrations`, `wishlists`, `customer_addresses`, `order_items`, `order_coupon`, `coupon_product`, `coupon_category`, `promotion_product`, `promotion_category`, `promotion_usages`, `product_variants`, `product_combos`, `activity_logs`.

---

## 10. Default Store Analysis

### What is Default Store?

- A `tenants` record with `id=1`, `slug='default'`, `name='Default Store'`
- Created in **migration** `2026_05_27_150000_create_tenants_table.php`
- Re-ensured in **seeder** `TenantSeeder.php`
- Used as fallback when no tenant context can be resolved (`Tenant::getDefault()`)

### Why does Default Store exist?

To support the migration from single-tenant to multi-tenant. Before tenants existed, all data existed in the database without `tenant_id`. The Default Store (ID=1) was created to:
1. Assign `tenant_id=1` to all existing data (backfilled by `TenantSeeder`)
2. Provide a fallback when no tenant is resolvable from subdomain/header/session

### Who owns Default Store?

**No one**. No Account has an owner membership for Default Store. No User has `is_owner=true` with `tenant_id=1`. The migration creates the tenant but creates no owner.

### Should it exist?

**Probably not in production**. Default Store is a migration artifact:

- **Pros**: Provides a fallback for requests that cannot resolve a tenant context
- **Cons**: It is an anonymous tenant with no legitimate owner. The SuperAdmin (`admin@shop.com`) is intended as the platform administrator, not the Default Store's merchant. No legitimate business operates on an unnamed "Default Store."

### Who should own it?

If kept, either:
- **SuperAdmin** (platform level) — for system-level demo/fallback, OR
- **Its own Owner Account** — but this adds complexity and makes it a first-class merchant

### Recommendation

**Phase out Default Store**:
1. Remove fallback from `Tenant::getDefault()` once all tenants are properly created
2. Ensure every tenant has a registered owner via `TenantMembership`
3. Convert the backfill logic in `TenantSeeder` to not assume ID=1
4. Move demo/test data to a dedicated test tenant or remove entirely
5. Priority: **Medium** — not blocking, but risky in production with no owner

---

## 11. SuperAdmin Analysis

### Where SuperAdmin lives

| Location | Exists? | Purpose |
|----------|---------|---------|
| `accounts` | Yes (via seeder) | Platform identity — `email=admin@shop.com` |
| `users` | Yes (via seeder) | Legacy identity — same email, different record |
| `model_has_roles` | Yes | Global role assignment: `role_id=superadmin`, `model_type=App\Models\Account` or `App\Models\User` |
| `roles` | Yes | Spatie role: `name=superadmin`, `guard_name=web`, `tenant_id=NULL` |
| `tenant_memberships` | **No** | SuperAdmin has NO memberships — intentionally |

### Should SuperAdmin ever belong to a Tenant?

**NO.** Absolutely not.

SuperAdmin is a **platform-level** identity:
- They operate the platform (manage tenants, plans, subscriptions, platform settings)
- They should NEVER be scoped to a single tenant
- They should NEVER have a membership with an Owner/Admin/Staff role inside a tenant
- Cross-tenant access is by design — SuperAdmin bypasses all tenant isolation
- A membership would defeat tenant isolation and create ambiguity (tenant admin vs platform admin)

### Verification

The `Account::hasRole()` override correctly checks `model_has_roles` for `superadmin` first and returns `true` immediately, bypassing the membership check. The middleware `CheckTenantAccess` and `IdentifyTenant` both bypass for SuperAdmin.

### Potential Issue

SuperAdmin creation is **duplicated** between `users` and `accounts` when `IDENTITY_USE_ACCOUNTS=true`. The seeder `RoleAndPermissionSeeder` creates:
1. A User record with email `admin@shop.com` and assigns `superadmin` role
2. An Account record with email `admin@shop.com` and assigns `superadmin` role

Both records exist independently. The `CompatibilityBridge` suggests they should be linked by matching ID, but the seeder does NOT enforce `account.id == user.id`. They could diverge.

---

## 12. Canonical Source for Every Identity Field

| Field | Canonical Source | Fallback | Concerns |
|-------|-----------------|----------|----------|
| **Display Name** | `accounts.name` → `merchant_profiles.business_name` → `customer_profiles.name` → `accounts.email` | Same logic for `User` model | `Account::getDisplayName()` reads from profiles via membership — expensive per-request if not eager-loaded |
| **Email** | `accounts.email` / `users.email` | None | Both tables store independently — no sync mechanism |
| **Avatar** | `accounts.profile_image` / `users.profile_image` | None | ImageService::url() for both |
| **Profile** | Profile models (`customer_profiles`, `staff_profiles`, `merchant_profiles`) via `tenant_membership_id` | None | Cannot exist without membership |
| **Role** | For Account: `tenant_memberships.role_id → roles.name` (plus `is_owner` = admin) | For User: `model_has_roles → roles.name` | SuperAdmin always global |
| **Permission** | `roles → permissions` via membership | For User: `model_has_roles → roles → permissions` | SuperAdmin gets all |
| **Store Name** | `tenants.name` | None | Single source of truth |
| **Status** | `accounts.status` / `users.status` | None | Dual tables = dual statuses |
| **Membership Status** | `tenant_memberships.status` | None | Separate from account status |

---

## 13. Conflicts Found

### CONFLICT 1: Dual-table identity (Critical)

`users` and `accounts` tables store **the same people** with **different primary keys** when `IDENTITY_USE_ACCOUNTS=true`. The seeders create matching records but do NOT enforce `account.id = user.id`. Over time:
- Password changes in one table won't sync to the other
- Status changes (suspend/ban) in one table won't sync
- Email verification in one table won't sync

**Root cause**: Migration design chose to create a separate `accounts` table instead of migrating the `users` table in place.

### CONFLICT 2: SuperAdmin has no TenantMembership (High)

SuperAdmin is correctly global but:
- `IdentifyTenant` early-returns for SuperAdmin (no tenant context)
- `Account::getCurrentMembership()` returns null for SuperAdmin
- `Account::hasRole()` checks `model_has_roles` for superadmin — correct
- But `Account::getDisplayName()` calls `getCurrentMembership()` which returns null → falls back to `$this->email`
- SuperAdmin display name is always the email address, never "Super Admin"

### CONFLICT 3: Default Store has no owner (High)

Tenant ID=1 (Default Store) has:
- No owner membership
- No subscription
- Business data assigned to it via backfill
- No legitimate administrator

### CONFLICT 4: `sessions` table stores both `user_id` and `account_id` (Medium)

The sessions table references both `user_id` (nullable) and `account_id` (nullable). When a user logs in, which column gets populated depends on which guard authenticated them. Session-based features (cart, current tenant) could become orphaned depending on which guard was used to create the session.

### CONFLICT 5: `Tenant::getDefault()` singleton risk (Medium)

The Default Store is cached indefinitely via `Cache::rememberForever`. If the tenant record is deleted or slug changes, the stale cache could cause tenant resolution failures.

### CONFLICT 6: Login mutates user.tenant_id (Medium)

`StorefrontLoginController::store() line 114-116`: In legacy mode, the login flow mutates `user.tenant_id` if it's null. This is a side effect during authentication.

### CONFLICT 7: Seeder creates no memberships (High)

The entire seeder suite creates **zero** `tenant_memberships` records. In Account mode:
- Customers created by `UserSeeder` get Account records but no membership
- SuperAdmin created by `RoleAndPermissionSeeder` gets no membership (intentionally)
- No Owner membership exists for any tenant
- `tenant_memberships` table is empty after `php artisan db:seed`

### CONFLICT 8: Model relationships hardcoded to User::class (Critical)

13+ model relationships (Orders, Messages, Wishlists, etc.) are hardcoded to `App\Models\User::class`. In Account mode, these relationships silently return empty collections because `accounts.id` ≠ `users.id`.

### CONFLICT 9: No sync between User and Account (Critical)

The `CompatibilityBridge` exists but nothing calls it during login or registration. When a user registers in Account mode:
- `RegisteredUserController` creates an Account + User record
- But business logic like `Order`, `Wishlist`, `Message` still references `users.id`
- The User and Account records drift apart

### CONFLICT 10: `users.role` column removed but `User` model still has deprecated constants (Low)

Migration `2026_05_19_122613_drop_role_column_from_users_table.php` removes the old `role` column. The `User` model still has `ROLE_CUSTOMER`, `ROLE_ADMIN`, `ROLE_SUPERADMIN` constants. Not actively harmful but shows incomplete cleanup.

---

## 14. Recommended Fixes

### CRITICAL (Blocks Account mode from working)

| # | Fix | Rationale |
|---|-----|-----------|
| C1 | Create `TenantMembership` for customers and owners during registration/seed | Without memberships, Account mode has no role resolution for tenant users |
| C2 | Enforce `account.id == user.id` when creating dual records | Prevents identity drift between User and Account |
| C3 | Add `tenant_memberships` seeder to create Owner membership for Default Store | Default Store needs a legitimate owner |
| C4 | Add `users.account_id` FK or enforce ID sync | Links legacy User records to Account records for model relationships |

### HIGH (Correctness)

| # | Fix | Rationale |
|---|-----|-----------|
| H1 | Create platform-specific guard (`platform`) | Separates platform auth from tenant auth |
| H2 | Add `$user->account_id` fallback on Order/Wishlist/Message models | Allows dual-mode relationship resolution without full polymorphic migration |
| H3 | Create `TenantMembership` seeder | Seeders must create memberships for test tenants |
| H4 | Remove `StorefrontLoginController::114-116` login mutation | Login should not mutate user data |
| H5 | Fix SuperAdmin display name to show "Super Admin" | `Account::getDisplayName()` returns email for superadmin |

### MEDIUM (Architecture consistency)

| # | Fix | Rationale |
|---|-----|-----------|
| M1 | Add cache invalidation for `Tenant::getDefault()` on tenant changes | Prevents stale cache |
| M2 | Document the `IDENTITY_USE_ACCOUNTS` flag and removal plan | Currently a single point of failure |
| M3 | Remove deprecated constants from `User` model | Cleanup |
| M4 | Evaluate whether Default Store should be removed | Migration artifact — not needed in production |
| M5 | Add Account-aware user query methods to services | ChatController, SuperAdmin controllers still query `User` only |

### LOW (Nice to have)

| # | Fix | Rationale |
|---|-----|-----------|
| L1 | Add DB index on `accounts.email` | Already has unique — need explicit index for auth lookups |
| L2 | Add DB index on `sessions.account_id` | Query performance for session-based features |
| L3 | Standardize `created_by`/`updated_by` across models | Mixes User and Account references |
| L4 | Add `@throws` docblocks to Account overrides | HasRole overrides silently return empty |

---

## 15. Fix Priority

| Priority | Count | Key Items |
|----------|-------|-----------|
| **CRITICAL** | 4 | Missing TenantMembership creation, ID sync, default store owner, account_id link |
| **HIGH** | 5 | Platform guard, relationship fallback, seeder fix, login mutation, SuperAdmin name |
| **MEDIUM** | 5 | Cache invalidation, documentation, cleanup, Default Store eval, Account-aware queries |
| **LOW** | 4 | Indexes, standardization, docblocks |

---

## 16. Migration Safety Analysis

### Risk Level: HIGH

The migration from User → Account is at **high risk** because:

1. **Dual tables with no sync**: Users and Accounts are fully independent. Any migration must handle:
   - Password sync
   - Status sync
   - Email verification sync
   - Remember token sync

2. **Schema changes required**: The 13 model relationships hardcoded to `User::class` require schema migration. This is the highest-risk component because it affects every business entity.

3. **Feature flag dependency**: `IDENTITY_USE_ACCOUNTS=false` by default. All Account mode code paths are effectively untested in production.

4. **No membership creation in seeders**: Fresh deployments or test environments running Account mode will have zero `tenant_memberships` records. Every Account-mode feature that depends on membership resolution will return empty/incorrect results.

5. **SuperAdmin identity duplication**: Two records (`users` + `accounts`) for the same person with different primary keys. Any system component that uses `auth()->id()` will get different values depending on which guard authenticated.

### Safe Migration Path

```
Phase 1: Create TenantMembership during registration (seeders + controllers)
Phase 2: Sync User.id = Account.id for all matching email records
Phase 3: Add account_id nullable FK to business tables (orders, messages, etc.)
Phase 4: Dual-write to both user_id and account_id
Phase 5: Switch reads from user_id to account_id
Phase 6: Drop user_id columns, migrate remaining User->Account relationships
Phase 7: Remove users table entirely
```

---

## 17. Final Verdict

**Is the Platform Identity architecture ready?**

### NO

The Platform Identity architecture is **not ready** for production use in Account mode.

### Blockers (must be resolved before any code changes)

1. **No TenantMembership creation in seeders or registration flow** — Account mode creates Account records but never creates TenantMembership records. Since Account resolves roles through TenantMembership, every Account user in the system has no role, no permissions, and no access. This is the single biggest blocker.

2. **No User/Account ID synchronization** — Account and User records for the same person have different primary keys. Model relationships (`Order.user_id`, `Message.sender_id`, etc.) reference `users.id` (legacy). In Account mode, `accounts.id` differs from `users.id`, so all relationship queries silently return empty.

3. **Default Store has no owner** — Tenant ID=1 has no Owner membership, no subscription, and no legitimate administrator. All backfilled business data is technically ownerless.

4. **SuperAdmin has no display name** — `Account::getDisplayName()` returns email for SuperAdmin because `getCurrentMembership()` returns null. The UI shows the admin's email instead of "Super Admin."

### Conditional Ready

The architecture is **correct by design** — the Account → TenantMembership → Role → Permission flow is properly modeled. The `Account` model's Spatie method overrides are well-implemented and secure. The middleware layer handles Account vs User branching correctly.

However, the implementation is **incomplete**. The critical path from seeder → registration → login → authorization lacks the essential TenantMembership creation step. Without it, the architecture is a skeleton with no connective tissue.

### What to fix before any other code changes:

| Order | Fix | Area |
|-------|-----|------|
| 1 | Add TenantMembership creation to Account registration | Registration flow (`RegisteredUserController`) |
| 2 | Add TenantMembership seeder for customers + test tenants | Seeders |
| 3 | Add Owner TenantMembership for Default Store | Seeders |
| 4 | Enforce `account.id = user.id` when creating dual records | Seeders + registration |
| 5 | Add `account_id` polymorphic fallback to business model relationships | Models (Orders, Messages, etc.) |
| 6 | Fix SuperAdmin display name resolution | `Account::getDisplayName()` |

After those six fixes, the Platform Identity architecture will be ready for verification and then production use.
