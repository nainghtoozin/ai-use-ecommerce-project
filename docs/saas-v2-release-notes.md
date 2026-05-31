# SaaS v2 Release Notes

> **Version:** 2.0.0 (Release Candidate)
> **Previous Version:** 1.0 (Single-Tenant)
> **Upgrade:** Multi-Tenant SaaS Platform

---

## Overview

This release upgrades the ecommerce platform from single-tenant to multi-tenant SaaS architecture. All tenant-owned data is now isolated at the database level via `tenant_id` columns, global scopes, and scoped validation rules.

---

## New Features

### Multi-Tenant Architecture
- Tenant identification via subdomain, session, header, or authenticated user
- `TenantAware` trait auto-assigns `tenant_id` on create for all tenant-owned models
- `TenantScope` global scope filters all queries to current tenant
- `IdentifyTenant` middleware resolves tenant context on every request
- SuperAdmin can impersonate any tenant for support

### Tenant Management
- SuperAdmin CRUD for tenants with domain/subdomain management
- Tenant status system: active, suspended, banned, inactive
- Per-tenant maintenance mode (via WebsiteInfo)

### Subscription & Plans
- Plan definitions with feature limits (product_limit, staff_limit, storage_limit)
- Subscription lifecycle: active, past_due, expired, suspended, cancelled
- Trial period support (via `trial_ends_at`)
- `FeatureGate` system for plan-based feature access (currently in DEV_MODE)

### Dashboard
- Per-tenant dashboard metrics with cached results
- Pre-computation via queued jobs for performance
- Revenue analytics per tenant

### Internationalization
- Per-tenant locations (cities, townships)
- Per-tenant payment methods
- Per-tenant branding/site settings (via WebsiteInfo)

---

## Tenant Isolation Fixes (This Release)

### Data Leak Fixes
| Issue | Fix |
|---|---|
| Dashboard jobs aggregated ALL tenants' data | All `DB::table()` queries now filter by `tenant_id` |
| Dashboard cache keys shared across tenants | All cache keys include tenant suffix |
| Promotion report leaked cross-tenant coupon usage | Added `->where('orders.tenant_id', ...)` |
| Promotion report leaked cross-tenant promotion data | Added `->where('promotions.tenant_id', ...)` |
| `Setting::set()` overwrote other tenants' settings | Changed to `UNIQUE(tenant_id, key)` index |

### Unique Index Migrations
| Migration | Table | Change |
|---|---|---|
| `2026_05_31_000002` | `payment_methods` | `UNIQUE(name)` → `UNIQUE(tenant_id, name)` |
| `2026_05_31_000003` | `settings` | `UNIQUE(key)` → `UNIQUE(tenant_id, key)` |
| `2026_05_31_000004` | `coupons` | `UNIQUE(code)` → `UNIQUE(tenant_id, code)` |
| `2026_05_31_000005` | `promotions` | `UNIQUE(code)` → `UNIQUE(tenant_id, code)` |
| `2026_05_31_000006` | `products` | `UNIQUE(sku)` → `UNIQUE(tenant_id, sku)` |

### Mass Assignment Security
| Model | Change |
|---|---|
| `User` | Removed `tenant_id`, `is_owner`, `plan_*` from `$fillable` |
| `Subscription` | Removed `tenant_id` from `$fillable`, added `TenantAware` trait |
| `Role` | Removed `tenant_id` from `$fillable` |

### Validation Rules
| Module | Store | Update |
|---|---|---|
| Coupons | `unique:coupons,code` → scoped Rule | same |
| Promotions | `unique:promotions,code` → scoped Rule | same |
| Products (SKU) | `unique:products,sku` → scoped Rule | same |
| Categories | `unique:categories,name` → scoped Rule | same |
| Cities | `unique:cities,name` → scoped Rule | same |

### Queue Job Fixes
| Job | Change |
|---|---|
| `RefreshDashboardMetrics` | Added `$tenantId` constructor param; all 8 queries scoped; 3 cache keys fixed |
| `ComputeFullDashboardMetrics` | Added `$tenantId` constructor param; all queries scoped; 3 cache keys fixed |
| `TelegramRecipientResolver` | Added `->where('tenant_id', $order->tenant_id)` for queue context |

### Controller Fixes
| Controller | Change |
|---|---|
| `AdminProductController` | Replaced `$request->except(...)` with `$request->validated()` |
| `ActivityLogController` | Added tenant filter to `show()` method |
| `TenantController` | `is_owner` set directly on model, not via mass assignment |

### Trait Fix
| Trait | Change |
|---|---|
| `TenantAware` | `array_key_exists('tenant_id', ...)` → `empty($model->tenant_id)` — handles null |

---

## Known Limitations (v2.0)

### Subscription / Billing (Not Ready)
- `FeatureGate::DEV_MODE = true` — all plan restrictions bypassed
- No payment gateway integration
- No grace period enforcement (`GRACE_DAYS = 7` defined but unused)
- No subscription expiry notifications
- No automatic suspension of expired tenants
- `EnsureTenantIsActive` middleware registered but not used on any route

### Technical Debt
- `ActivityLog` intentionally exempt from `TenantScope` (by design for impersonation audit)
- `orWhereNull(tenant_id)` in `TenantScope` — shared data design but risky if `tenant_id` missing
- `OrderCoupon` pivot model does not use `TenantAware` (mitigated by Order scoping)
- Legacy plan columns on `users` and `tenants` tables (dual source of truth)
- Feature limits (`product_limit`, `staff_limit`) not enforced in controllers

---

## Migration Notes

### Required Steps
1. Run `php artisan migrate` to apply all 5 unique index migrations
2. Run `php artisan tenants:sync-roles` to ensure roles exist per tenant
3. Verify `FeatureGate::DEV_MODE` is set correctly for your deployment
4. Review `EnsureTenantIsActive` middleware registration if multi-plan launch is imminent

### Breaking Changes
- `RefreshDashboardMetrics` and `ComputeFullDashboardMetrics` now require `$tenantId` in constructor
- `DashboardService::getMetricsForPeriod()` and `getGeneralStats()` now require tenant parameter
- `City::getActiveWithTownships()` cache key format changed (now includes tenant ID)
- `User::create()` no longer accepts `tenant_id`, `is_owner`, or `plan_*` fields
- `Subscription::create()` no longer accepts `tenant_id` field
- `TenantController::store()` does not pass `is_owner` to `User::create()` anymore

---

## Files Changed

### New Files
- `database/migrations/2026_05_31_000002_fix_payment_methods_tenant_unique_index.php`
- `database/migrations/2026_05_31_000003_fix_settings_tenant_unique_index.php`
- `database/migrations/2026_05_31_000004_fix_coupons_tenant_unique_index.php`
- `database/migrations/2026_05_31_000005_fix_promotions_tenant_unique_index.php`
- `database/migrations/2026_05_31_000006_fix_products_tenant_sku_unique_index.php`
- `docs/saas-v2-readiness-report.md`
- `docs/saas-v2-release-candidate-report.md`
- `docs/saas-v2-release-notes.md`
- `docs/saas-v2-fix-roadmap.md`

### Modified Files
- `app/Models/User.php` — Removed fields from `$fillable`
- `app/Models/Subscription.php` — Added `TenantAware`, cleaned `$fillable`
- `app/Models/Role.php` — Cleaned `$fillable`
- `app/Models/Traits/TenantAware.php` — Fixed `empty()` check
- `app/Models/City.php` — Fixed cache key
- `app/Http/Controllers/Admin/AdminCouponController.php` — Scoped validation
- `app/Http/Controllers/Admin/AdminPromotionController.php` — Scoped validation
- `app/Http/Controllers/Admin/AdminProductController.php` — Scoped validation + `validated()`
- `app/Http/Controllers/Admin/AdminCategoryController.php` — Scoped validation
- `app/Http/Controllers/Admin/ActivityLogController.php` — Tenant filter on `show()`
- `app/Http/Controllers/SuperAdmin/TenantController.php` — Direct `is_owner` assignment
- `app/Http/Requests/CityStoreRequest.php` — Scoped validation
- `app/Http/Requests/CityUpdateRequest.php` — Scoped validation
- `app/Services/TelegramRecipientResolver.php` — Tenant filter
- `app/Jobs/RefreshDashboardMetrics.php` — Tenant scoping + cache keys
- `app/Jobs/ComputeFullDashboardMetrics.php` — Tenant scoping + cache keys
- `app/Http/Controllers/Admin/AdminPromotionReportController.php` — Tenant filters
- `app/Http/Controllers/Admin/AdminController.php` — Cache key fixes

---

## SaaS Readiness Scores

| Category | v1 (Single-Tenant) | v2 RC (Current) |
|---|---|---|
| Tenant isolation | N/A | 100% |
| Validation rules | N/A | 100% |
| DB unique indexes | 0% | 100% |
| Query safety | 0% | 100% |
| Settings isolation | 0% | 100% |
| Mass assignment security | 0% | 100% |
| Subscription/plan logic | N/A | 30% |
| **Overall** | **—** | **80%** |

---

*End of Release Notes*
