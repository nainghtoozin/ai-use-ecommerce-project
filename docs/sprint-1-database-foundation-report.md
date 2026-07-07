# Sprint 1: Database Foundation Report

**Status:** COMPLETE  
**Date:** 2026-07-08  
**Project:** Multi-tenant SaaS E-commerce Platform  
**Governed by:** `docs/identity-architecture-lock-v2.md`  
**Blueprint:** `docs/identity-database-blueprint-v1.md`  

---

## Summary

Successfully implemented 11 migrations to establish the Identity Database Foundation. All tables, columns, indexes, and foreign keys match the blueprint specification exactly.

---

## Migration Manifest

### New Tables (7)

| # | Migration | Table | Purpose |
|---|---|---|---|
| 1 | `2026_07_08_000001` | `accounts` | Root identity: email, password, verification, global status |
| 2 | `2026_07_08_000002` | `password_reset_tokens_new` | New password resets keyed by `account_id` (alongside legacy table) |
| 3 | `2026_07_08_000003` | `tenant_memberships` | Links Account to Tenant with Role, Ownership, Membership status |
| 4 | `2026_07_08_000004` | `customer_profiles` | Customer-scoped profile data (name, phone, metadata) |
| 5 | `2026_07_08_000005` | `staff_profiles` | Staff-scoped profile data (position, department) |
| 6 | `2026_07_08_000006` | `merchant_profiles` | Owner-scoped business info (business_name, tax_id, address) |
| 7 | `2026_07_08_000007` | `social_accounts` | OAuth provider links per Account |

### Modified Tables (4)

| # | Migration | Table | Changes |
|---|---|---|---|
| 8 | `2026_07_08_000008` | `sessions` | Added `account_id` (FK→accounts CASCADE) + `current_tenant_membership_id` (FK→tenant_memberships SET NULL) |
| 9 | `2026_07_08_000009` | `orders` | Added `tenant_membership_id` (FK→tenant_memberships SET NULL, nullable) |
| 10 | `2026_07_08_000010` | `customer_addresses` | Added `tenant_membership_id` (FK→tenant_memberships SET NULL, nullable) |
| 11 | `2026_07_08_000011` | `wishlists` | Added `tenant_membership_id` (FK→tenant_memberships SET NULL, nullable) |

### Tables with No Changes in Sprint 1

| Table | Reason |
|---|---|
| `users` | Deprecated — no schema changes until Phase 8 |
| `tenants` | No identity changes needed |
| `roles` (Spatie) | No schema changes needed |
| `permissions` (Spatie) | No schema changes needed |
| `model_has_roles` (Spatie) | Used for legacy User records during transition |
| `role_has_permissions` (Spatie) | No schema changes needed |
| `notifications` | Blueprint: no schema change needed |
| `activity_log` | Used as-is with causer_type change |

---

## Foreign Key Summary

| Foreign Key | Parent | On Delete | Justification |
|---|---|---|---|
| `password_reset_tokens_new.account_id` | `accounts.id` | CASCADE | Reset token dies with account |
| `tenant_memberships.account_id` | `accounts.id` | SET NULL | Preserve audit trail on account deletion |
| `tenant_memberships.tenant_id` | `tenants.id` | CASCADE | Memberships die with tenant |
| `tenant_memberships.role_id` | `roles.id` | RESTRICT | Prevent deletion of assigned roles |
| `tenant_memberships.invited_by` | `accounts.id` | SET NULL | Preserve invitation record |
| `customer_profiles.tenant_membership_id` | `tenant_memberships.id` | CASCADE | Profile dies with membership |
| `staff_profiles.tenant_membership_id` | `tenant_memberships.id` | CASCADE | Profile dies with membership |
| `merchant_profiles.tenant_membership_id` | `tenant_memberships.id` | CASCADE | Profile dies with membership |
| `social_accounts.account_id` | `accounts.id` | CASCADE | Provider link dies with account |
| `sessions.account_id` | `accounts.id` | CASCADE | Session dies with account |
| `sessions.current_tenant_membership_id` | `tenant_memberships.id` | SET NULL | Session context lost if membership removed |
| `orders.tenant_membership_id` | `tenant_memberships.id` | SET NULL | Preserve order if membership removed |
| `customer_addresses.tenant_membership_id` | `tenant_memberships.id` | SET NULL | Preserve address if membership removed |
| `wishlists.tenant_membership_id` | `tenant_memberships.id` | SET NULL | Preserve wishlist if membership removed |

---

## Index Summary

| Table | Index Name | Columns | Type |
|---|---|---|---|
| `accounts` | `accounts_email_unique` | `email` | UNIQUE |
| `accounts` | `accounts_status_index` | `status` | INDEX |
| `accounts` | `accounts_deleted_at_index` | `deleted_at` | INDEX |
| `tenant_memberships` | `tm_account_id_tenant_id_unique` | `(account_id, tenant_id)` | UNIQUE |
| `tenant_memberships` | `tm_tenant_id_account_id_index` | `(tenant_id, account_id)` | INDEX |
| `tenant_memberships` | `tm_tenant_id_is_owner_index` | `(tenant_id, is_owner)` | INDEX |
| `tenant_memberships` | `tm_account_id_index` | `(account_id)` | INDEX |
| `tenant_memberships` | `tm_tenant_id_status_index` | `(tenant_id, status)` | INDEX |
| `tenant_memberships` | `tm_role_id_index` | `(role_id)` | INDEX |
| `tenant_memberships` | `tm_invited_by_index` | `(invited_by)` | INDEX |
| `customer_profiles` | `customer_profiles_tenant_membership_id_unique` | `(tenant_membership_id)` | UNIQUE |
| `staff_profiles` | `staff_profiles_tenant_membership_id_unique` | `(tenant_membership_id)` | UNIQUE |
| `merchant_profiles` | `merchant_profiles_tenant_membership_id_unique` | `(tenant_membership_id)` | UNIQUE |
| `social_accounts` | `sa_provider_provider_id_unique` | `(provider, provider_id)` | UNIQUE |
| `social_accounts` | `sa_account_id_index` | `(account_id)` | INDEX |

---

## Verification

- ✅ All 11 migrations run successfully (`php artisan migrate`)
- ✅ All 11 migrations roll back successfully (`php artisan migrate:rollback --step=11`)
- ✅ Re-migrated successfully (second pass confirms idempotency)
- ✅ All 7 new tables exist in database
- ✅ All 4 modified tables have their new columns
- ✅ All required indexes created
- ✅ All foreign keys created with correct ON DELETE rules
- ✅ Old `password_reset_tokens` table preserved (backward compatibility)
- ✅ No existing tables dropped or renamed

### Rollback Test Results

| Step | Operation | Result |
|---|---|---|
| 1 | `php artisan migrate` | 11/11 DONE |
| 2 | `php artisan migrate:rollback --step=11` | 11/11 DONE |
| 3 | `php artisan migrate` (re-migrate) | 11/11 DONE |

---

## Deviations from Blueprint

| Blueprint Spec | Actual | Reason |
|---|---|---|
| `password_reset_tokens` RECREATED | Created as `password_reset_tokens_new` alongside legacy | Per Zero-Downtime Strategy: "Do NOT drop the old table in Phase 1" |
| `notifications` modification | NO-OP in Sprint 1 | Blueprint: "No schema change needed — only application-level changes" |
| `tenant_memberships.account_id` NOT NULL | Made NULLABLE | Required for SET NULL FK rule (v2 design decision) |

---

## Next Steps

### Sprint 2: Account Models & Migrations Commands
- [ ] Create `app/Models/Account.php` (extends Authenticatable)
- [ ] Create `app/Models/TenantMembership.php`
- [ ] Create `app/Models/CustomerProfile.php`
- [ ] Create `app/Models/StaffProfile.php`
- [ ] Create `app/Models/MerchantProfile.php`
- [ ] Create `app/Models/SocialAccount.php`
- [ ] Define all relationships, casts, and accessors
- [ ] Generate `php artisan model:show` verification for each
- [ ] Document model decisions in `docs/sprint-2-models-report.md`
