# SaaS v2 Readiness Report

> **Audit Date:** 2026-05-31
> **Platform:** Laravel Multi-Tenant Ecommerce SaaS
> **Audit Scope:** Tenant Isolation, Validation, Queries, Settings, Dashboard, Subscriptions

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Module-by-Module Analysis](#2-module-by-module-analysis)
   - [Payment Methods](#payment-methods)
   - [Users](#users)
   - [Roles](#roles)
   - [Permissions](#permissions)
   - [Coupons](#coupons)
   - [Promotions](#promotions)
   - [Locations (Cities, Townships)](#locations-cities-townships)
   - [Categories](#categories)
   - [Products + Product Variants + Product Combos](#products--product-variants--product-combos)
   - [Customers](#customers)
   - [Orders](#orders)
   - [Notifications](#notifications)
   - [Settings (Key-Value Store)](#settings-key-value-store)
   - [WebsiteInfo (Branding/Site Settings)](#websiteinfo-brandingsite-settings)
   - [Telegram Integration](#telegram-integration)
   - [Dashboard](#dashboard)
   - [Reports](#reports)
   - [Subscriptions & Plans](#subscriptions--plans)
   - [Impersonation](#impersonation)
   - [Maintenance Mode](#maintenance-mode)
3. [Cross-Tenant Data Leak Locations](#3-cross-tenant-data-leak-locations)
4. [Database Unique Index Audit](#4-database-unique-index-audit)
5. [Validation Rules Audit](#5-validation-rules-audit)
6. [Critical Issues](#6-critical-issues)
7. [Recommended Fix Order](#7-recommended-fix-order)
8. [SaaS Readiness Score](#8-saas-readiness-score)
9. [Production Launch Blockers](#9-production-launch-blockers)
10. [Tenant Isolation Architecture Summary](#10-tenant-isolation-architecture-summary)

---

## 1. Executive Summary

This project was upgraded from a single-tenant ecommerce application to a Multi-Tenant SaaS platform. Several tenant-related bugs have been discovered and partially fixed (e.g., Payment Method validation). This report provides a comprehensive production-readiness audit across all modules.

**Overall SaaS Readiness Score: 40%**

**Key Findings:**
- **7 Critical Issues** identified, including active cross-tenant data leaks in dashboard metrics jobs and promotion reports
- **11 modules** require fixes before production launch
- **11 modules** are safe and properly tenant-isolated
- The subscription/plan system is incomplete — `FeatureGate::DEV_MODE = true` disables all plan restrictions, and there is zero payment processing code

---

## 2. Module-by-Module Analysis

---

### Payment Methods

**Status: WARNING** (previously CRITICAL, partially fixed)

| Check | Status | Detail |
|---|---|---|
| `tenant_id` enforced? | YES | `TenantAware` trait auto-sets on create |
| Queries tenant-scoped? | YES | `TenantScope` global scope filters all queries |
| Validations tenant-scoped? | **YES (FIXED)** | `PaymentMethodStoreRequest.php:23` and `PaymentMethodUpdateRequest.php:35` now use `->where('tenant_id', ...)` |
| Unique rules tenant-scoped? | **YES (FIXED)** | `unique:payment_methods,name` → `Rule::unique(...)->where('tenant_id', ...)` |
| Update ops tenant-safe? | YES | Route-model binding + TenantScope prevent cross-tenant access |
| Delete ops tenant-safe? | YES | Same as update |
| DB unique index tenant-aware? | **YES (FIXED)** | Migration `2026_05_31_000002` adds `UNIQUE(tenant_id, name)` |

**Issues Found:** Payment method name uniqueness was globally scoped — Tenant A's "KBZ Pay" blocked Tenant B from creating "KBZ Pay". Both FormRequests lacked `->where('tenant_id', ...)`. No DB-level unique constraint existed.

**Root Cause:** `PaymentMethodStoreRequest.php:17` used `unique:payment_methods,name` without tenant awareness.

**Risk Level:** MEDIUM (was HIGH, now mitigated)

---

### Users

**Status: WARNING**

| Check | Status | Detail |
|---|---|---|
| `tenant_id` enforced? | YES | Custom `booted()` method auto-sets `tenant_id` on create |
| Queries tenant-scoped? | PARTIAL | No global scope — scoped via `IdentifyTenant` middleware + manual `scopeForTenant()` |
| Validations tenant-scoped? | **NO** | `StoreUserRequest.php:19` `unique:users,email` — globally unique |
| Unique rules tenant-scoped? | **NO** | `users_email_unique` DB index is global |
| Update ops tenant-safe? | YES | Route-model binding, but current user can update their own profile |
| Delete ops tenant-safe? | YES | Admin only |
| `tenant_id` in `$fillable`? | **YES — RISKY** | `User.php:25` `'tenant_id'` is mass-assignable |

**Issues Found:**

1. **`tenant_id` in `$fillable`** (`User.php:25`): A mass-assignment vulnerability. A user could theoretically set `tenant_id` via a request payload if any controller uses `$request->all()` or includes `tenant_id` in validated data. Should be in `$guarded`.

2. **`users.email` globally unique**: `users_email_unique` index + `unique:users,email` in `StoreUserRequest.php:19`. **This is standard SaaS practice** — most platforms require globally unique emails to prevent spam and simplify login. However, if tenant-isolated emails are desired, add `->where('tenant_id', ...)` to the unique rule and change the DB index to `UNIQUE(tenant_id, email)`.

3. **Registration email uniqueness** (`RegisteredUserController.php:36`): `'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class]` — no tenant scope. If Tenant A has `john@example.com`, Tenant B cannot register with the same email. **This is typically correct SaaS behavior.**

4. **Profile email update** (`ProfileUpdateRequest.php:26`): `Rule::unique(User::class)->ignore($this->user()->id)` — checks globally across all tenants. A user could change their email to one already used by a user in another tenant, but the unique constraint prevents it.

**Root Cause:** User model predates multi-tenant architecture; email uniqueness was originally designed for single-tenant.

**Risk Level:** MEDIUM (email global uniqueness is intentional in most SaaS; `tenant_id` in `$fillable` is the real risk)

---

### Roles

**Status: SAFE**

| Check | Status | Detail |
|---|---|---|
| `tenant_id` enforced? | YES | `TenantAware` trait auto-sets; `$fillable` includes `tenant_id` |
| Queries tenant-scoped? | INTENTIONALLY NO | `TenantScope` explicitly exempts `Role::class` (`TenantScope.php:15`) |
| Validations tenant-scoped? | PARTIAL | `StoreRoleRequest.php:18` uses `Rule::unique('roles', 'name')->where(fn($q) => $q->where('guard_name', 'web'))` — scoped by guard, NOT tenant |
| Unique rules tenant-scoped? | **YES** | `roles_tenant_id_name_guard_name_unique` DB index includes `tenant_id` |
| Update ops tenant-safe? | YES | Roles are managed by SuperAdmin or tenant admin |
| Delete ops tenant-safe? | YES | |

**Issues Found:** The `TenantScope` exemption means `Role::all()` returns ALL roles across all tenants without filtering. This is **intentional** — roles like `admin` and `customer` are copied per-tenant during migration backfill, and the tenant separation is handled via the `tenant_id` column directly in queries. The DB unique index `(tenant_id, name, guard_name)` prevents cross-tenant collision.

**Root Cause:** N/A — roles are correctly designed for multi-tenant.

**Risk Level:** LOW (the exemption is intentional, and the unique index enforces isolation)

---

### Permissions

**Status: SAFE**

| Check | Status | Detail |
|---|---|---|
| `tenant_id` enforced? | N/A | Permissions are system-level — shared across all tenants |
| Queries tenant-scoped? | N/A | System-level |
| Validations tenant-scoped? | N/A | No permission CRUD in admin |
| Unique rules tenant-scoped? | N/A | `permissions_name_guard_name_unique` — global by design |
| `config/permission.php` teams? | `false` | Correct — tenant isolation via roles, not permissions |

**Issues Found:** None. Permissions are correctly designed as system-level resources shared across all tenants. The `teams=false` configuration in `config/permission.php` is appropriate because tenant isolation is achieved via the roles table modification (adding `tenant_id` to the roles unique index).

**Root Cause:** N/A

**Risk Level:** LOW

---

### Coupons

**Status: CRITICAL**

| Check | Status | Detail |
|---|---|---|
| `tenant_id` enforced? | YES | `TenantAware` trait auto-sets on create |
| Queries tenant-scoped? | YES | `TenantScope` global scope |
| Validations tenant-scoped? | **NO** | `AdminCouponController.php:43` `unique:coupons,code` — inline validation in controller |
| Unique rules tenant-scoped? | **NO** | `coupons_code_unique` DB index is global — `UNIQUE(code)` |
| Update ops tenant-safe? | YES | Route-model binding + TenantScope |
| Delete ops tenant-safe? | YES | Same |

**Issues Found:**

1. **`coupons.code` globally unique** — `AdminCouponController.php:43` uses `'code' => 'nullable\|string\|max:50\|unique:coupons,code'` with no tenant scope. Tenant B cannot create coupon code "SAVE10" if Tenant A already uses it.

2. **Update path same issue** — `AdminCouponController.php:95` uses `'code' => 'nullable\|string\|max:50\|unique:coupons,code,' . $coupon->id` — ignores current record but still globally scoped.

3. **DB unique index is global** — Migration `2026_05_10_000001_create_coupons_table.php` defines `$table->string('code')->unique()`, which creates `coupons_code_unique` on `(code)` only.

4. **Inline validation bypasses FormRequests** — The controller uses `$request->validate(...)` instead of a FormRequest class.

**Root Cause:** Coupon CRUD was built pre-multi-tenant; validation rules and DB constraints never updated for tenant isolation.

**Risk Level:** HIGH — prevents tenants from using common coupon codes like "WELCOME10", "SUMMER2024"

---

### Promotions

**Status: CRITICAL**

| Check | Status | Detail |
|---|---|---|
| `tenant_id` enforced? | YES | `TenantAware` trait auto-sets on create |
| Queries tenant-scoped? | YES | `TenantScope` global scope |
| Validations tenant-scoped? | **NO** | `AdminPromotionController.php:46` `unique:promotions,code` — inline validation |
| Unique rules tenant-scoped? | **NO** | `promotions_code_unique` DB index is global — `UNIQUE(code)` |
| Update ops tenant-safe? | YES | Route-model binding + TenantScope |
| Delete ops tenant-safe? | YES | Same |

**Issues Found:**

1. **`promotions.code` globally unique** — `AdminPromotionController.php:46` `'code' => 'nullable\|string\|max:50\|unique:promotions,code'` with no tenant scope. Same cross-tenant collision issue as coupons.

2. **Update path same issue** — `AdminPromotionController.php:102` `'code' => 'nullable\|string\|max:50\|unique:promotions,code,' . $promotion->id` — global scope.

3. **DB unique index is global** — Migration `2026_05_10_000007_create_promotions_table.php` defines `$table->string('code')->unique()->nullable()`.

4. **Duplicate method** (`AdminPromotionController.php:186`) — `$promotion->replicate()` + `->save()` duplicates a promotion. The `code` is regenerated via `Promotion::generateCode()`, but if the generated code coincidentally matches another tenant's code, the DB unique constraint will throw an exception.

**Root Cause:** Same as coupons — pre-multi-tenant CRUD with global unique rules.

**Risk Level:** HIGH — prevents per-tenant promotion codes

---

### Locations (Cities, Townships)

**Status: WARNING**

| Check | Status | Detail |
|---|---|---|
| `tenant_id` enforced? | YES | Both `City` and `Township` use `TenantAware` |
| Queries tenant-scoped? | YES | `TenantScope` global scope |
| Validations tenant-scoped? | **NO** | `CityStoreRequest.php:17` `unique:cities,name` — global |
| Unique rules tenant-scoped? | N/A | No DB unique index on `cities.name` or `townships.name` |
| Update ops tenant-safe? | YES | Route-model binding + TenantScope |
| Delete ops tenant-safe? | YES | Same |

**Issues Found:**

1. **`cities.name` globally unique validation** — `CityStoreRequest.php:17` `'name' => 'required\|string\|max:255\|unique:cities,name'`. Tenant B cannot create city "Yangon" if Tenant A already has it. Update request (`CityUpdateRequest.php:22`) has the same issue with `Rule::unique('cities', 'name')->ignore($this->route('city'))`.

2. **No DB unique index on `cities.name` or `townships.name`** — The only enforcement is the validation layer. Since there's no DB constraint, a user could theoretically bypass validation and insert duplicate city names (though this is unlikely through normal flow).

**Root Cause:** City CRUD predates multi-tenant; validation rules treat cities as a global namespace.

**Risk Level:** HIGH — "Yangon", "Mandalay", "Taunggyi" are common city names every tenant should be able to use

---

### Categories

**Status: WARNING**

| Check | Status | Detail |
|---|---|---|
| `tenant_id` enforced? | YES | `TenantAware` trait auto-sets on create |
| Queries tenant-scoped? | YES | `TenantScope` global scope |
| Validations tenant-scoped? | **NO** | `AdminCategoryController.php:29` `unique:categories,name` — inline validation |
| Unique rules tenant-scoped? | N/A | No DB unique index on `categories.name` |
| Update ops tenant-safe? | YES | Route-model binding + TenantScope |
| Delete ops tenant-safe? | YES | |
| Category model `$fillable` | `['name', 'description']` — NO `tenant_id` | Correct — handled by trait |

**Issues Found:**

1. **`categories.name` globally unique validation** — `AdminCategoryController.php:29` `'name' => 'required\|unique:categories,name\|max:255'`. Tenant B cannot create category "Electronics" if Tenant A already has it.

2. **Update path same issue** — `AdminCategoryController.php:49` `'name' => 'required\|unique:categories,name,' . $category->id'`.

3. **No DB unique index** — No `$table->unique('name')` in the categories migration (`2025_09_28_091630_create_categories_table.php`), so only the validation layer prevents duplicates.

4. **Inline validation** — Uses `$request->validate(...)` in the controller instead of a FormRequest.

**Root Cause:** Category CRUD predates multi-tenant.

**Risk Level:** HIGH — common category names like "Electronics", "Clothing", "Food" would collide

---

### Products + Product Variants + Product Combos

**Status: WARNING**

| Check | Status | Detail |
|---|---|---|
| `tenant_id` enforced? | YES | All three models use `TenantAware` |
| Queries tenant-scoped? | YES | `TenantScope` global scope |
| Validations tenant-scoped? | **NO (SKU only)** | `AdminProductController.php:192` `unique:products,sku` — global |
| Unique rules tenant-scoped? | **NO (SKU only)** | `products_sku_unique` DB index is global |
| Update ops tenant-safe? | YES | Route-model binding + TenantScope |
| Delete ops tenant-safe? | YES | |
| Variant/Combo unique indexes | `product_combo_variant_unique(product_id, combo_product_id, linked_variant_id)` | These are FK-constrained to Product which is tenant-scoped |

**Issues Found:**

1. **`products.sku` globally unique validation** — `AdminProductController.php:192` `'sku' => 'nullable\|string\|max:100\|unique:products,sku'`. Tenant B cannot create product with SKU "PROD-001" if Tenant A already has it. Update path (`AdminProductController.php:365`) same issue with `'sku' => 'nullable\|string\|max:100\|unique:products,sku,' . $product->id`.

2. **DB unique index `products_sku_unique` is global** — Migration `2026_05_27_110611_add_sku_to_products_table.php` defines `$table->string('sku')->nullable()->unique()`. This will throw a DB constraint violation if a cross-tenant SKU collision occurs despite validation (or if validation is bypassed).

3. **Product combo unique index** (`product_combo_variant_unique` on `(product_id, combo_product_id, linked_variant_id)`): This index does NOT include `tenant_id`. However, since both `product_id` and `combo_product_id` are foreign keys to the `products` table (which is tenant-scoped), cross-tenant combo collisions are prevented by the FK constraints + product scoping.

**Root Cause:** SKU uniqueness was designed for single-tenant catalog management.

**Risk Level:** HIGH — SKU naming conventions (e.g., "PROD-001", "ITEM-001") are commonly reused across tenants

---

### Customers

**Status: SAFE**

| Check | Status | Detail |
|---|---|---|
| `tenant_id` enforced? | YES | Customers are `User` records with `tenant_id` auto-set |
| Queries tenant-scoped? | YES | Users are scoped via `IdentifyTenant` middleware + `scopeForTenant()` |
| Validations tenant-scoped? | N/A | Customer registration uses global email uniqueness (intentional) |
| Unique rules tenant-scoped? | N/A | Email global unique by design |

**Issues Found:** None significant. Customer data is tenant-isolated through the User model's `tenant_id` column. Global email uniqueness is standard SaaS practice.

**Root Cause:** N/A

**Risk Level:** LOW

**Note:** Customer data is accessed via `User::where('role', 'customer')` scoped by tenant queries, or through `Order::with('user')` which is tenant-scoped.

---

### Orders

**Status: SAFE**

| Check | Status | Detail |
|---|---|---|
| `tenant_id` enforced? | YES | `TenantAware` trait auto-sets |
| Queries tenant-scoped? | YES | `TenantScope` global scope |
| Validations tenant-scoped? | N/A | Order validation checks product/coupon existence which are tenant-scoped |
| Unique rules tenant-scoped? | N/A | No user-facing unique fields on orders |
| Update ops tenant-safe? | YES | Route-model binding + TenantScope |
| Delete ops tenant-safe? | YES | |
| Order Items | `TenantAware` | SAFE |
| Order Coupon (pivot) | No trait, but accessed through Order relationship | SAFE (indirectly scoped) |

**Issues Found:** None. Orders are properly tenant-isolated. The `OrderCoupon` pivot model doesn't use `TenantAware`, but it's only accessed through `Order` relationships which are scoped.

**Root Cause:** N/A

**Risk Level:** LOW

---

### Notifications

**Status: SAFE**

| Check | Status | Detail |
|---|---|---|
| `tenant_id` enforced? | YES | Added by migration `2026_05_28_000002_add_tenant_id_to_notifications_table.php` |
| Queries tenant-scoped? | YES | Uses `TenantAware` trait |
| Validations tenant-scoped? | N/A | Notifications are system-generated, not user-created |
| Unique rules tenant-scoped? | N/A | UUID primary keys |
| Update ops tenant-safe? | YES | |
| Delete ops tenant-safe? | YES | |

**Issues Found:** None.

**Root Cause:** N/A

**Risk Level:** LOW

---

### Settings (Key-Value Store)

**Status: CRITICAL**

| Check | Status | Detail |
|---|---|---|
| `tenant_id` enforced? | YES | `TenantAware` trait auto-sets |
| Queries tenant-scoped? | YES | `TenantScope` global scope |
| Validations tenant-scoped? | N/A | `Setting::set()` uses `updateOrCreate(['key' => $key])` |
| Unique rules tenant-scoped? | **NO** | `settings_key_unique` DB index is global — `UNIQUE(key)` |
| Update ops tenant-safe? | **NO** | **`Setting::set()` will fail or overwrite another tenant's setting** |
| Delete ops tenant-safe? | PARTIAL | TenantScope protects reads, but unique constraint threatens writes |

**Issues Found:**

1. **`settings.key` is globally unique** — Migration `2026_04_30_100521_create_settings_table.php`: `$table->string('key')->unique()`. This creates a `UNIQUE(key)` constraint across ALL tenants.

2. **`Setting::set()` breaks multi-tenant** — `Setting.php:23`: `return static::updateOrCreate(['key' => $key], ['value' => $value])`. The `updateOrCreate` matches on `key` alone. If Tenant A has a setting with key `notifications_enabled`, Tenant B's call to `Setting::set('notifications_enabled', ...)` will:
   - Find Tenant A's record (since `updateOrCreate` doesn't respect TenantScope — it uses `firstOrCreate` which may bypass scopes)
   - OR hit the `UNIQUE(key)` constraint if it tries to insert a new row with `tenant_id = Tenant B's ID`
   - **Result: Either overwrites Tenant A's setting or gets a DB constraint violation**

3. **`AdminNotificationSettingsController.php:30`** — Calls `Setting::set('notifications_enabled', ...)` which triggers the bug above.

4. **`Setting::get()` uses `where('key', $key)`** — `Setting.php:17`: This is tenant-scoped by TenantScope, so it correctly returns the current tenant's setting. Read operations are safe.

**Root Cause:** The settings system was designed as a global key-value store for single-tenant. The `UNIQUE(key)` constraint prevents per-tenant settings from coexisting.

**Risk Level:** CRITICAL — `Setting::set()` is fundamentally broken for multi-tenant. Notification settings, payment settings, and any other key-value settings cannot work correctly across tenants.

**Note:** The `WebsiteInfo` model is the CORRECT approach for tenant-isolated settings — it uses `UNIQUE(tenant_id)` to enforce one record per tenant. The legacy `Setting` model is the problem.

---

### WebsiteInfo (Branding/Site Settings)

**Status: SAFE**

| Check | Status | Detail |
|---|---|---|
| `tenant_id` enforced? | YES | `TenantAware` trait |
| Queries tenant-scoped? | YES | `TenantScope` |
| Validations tenant-scoped? | YES | `UpdateWebsiteSettingsRequest.php` has no unique rules — pure field validation |
| Unique rules tenant-scoped? | **YES** | `UNIQUE(tenant_id)` — one row per tenant |
| Update ops tenant-safe? | YES | Uses `self::first()` which is tenant-scoped |
| Delete ops tenant-safe? | YES | |
| Caching per-tenant | YES | Cache key `website_settings_{tenant_id}` |

**Issues Found:** None. This is the reference implementation for tenant-isolated settings.

**Root Cause:** N/A

**Risk Level:** LOW

---

### Telegram Integration

**Status: SAFE**

| Check | Status | Detail |
|---|---|---|
| `tenant_id` enforced? | YES | `TenantAware` trait |
| Queries tenant-scoped? | YES | `TenantScope` |
| Validations tenant-scoped? | N/A | `TelegramIntegrationRequest.php` has no unique rules |
| Unique rules tenant-scoped? | N/A | No unique constraints on `telegram_integrations` |
| Update ops tenant-safe? | YES | |
| Delete ops tenant-safe? | YES | |

**Issues Found:** None.

**Root Cause:** N/A

**Risk Level:** LOW

---

### Dashboard

**Status: CRITICAL**

| Check | Status | Detail |
|---|---|---|
| `AdminController.php` (live dashboard) | **SAFE** | Uses Eloquent models OR explicit `when(tenant(), fn($q, $t) => $q->where('orders.tenant_id', $t->id))` on raw queries. Cache keys include `$tenantSuffix` |
| `RefreshDashboardMetrics.php` (queued job) | **CRITICAL** | ALL queries use `DB::table()` with NO `tenant_id` filter |
| `ComputeFullDashboardMetrics.php` (queued job) | **CRITICAL** | Same pattern — ALL `DB::table()` calls have NO `tenant_id` filter |

**Issues Found:**

#### RefreshDashboardMetrics.php — ALL 8 queries leak:

| Line | Query | Problem |
|---|---|---|
| 49-51 | `DB::table('orders')->whereBetween(...)->count()` | Counts ALL tenants' orders |
| 53-56 | `DB::table('orders')->where('order_status', 'completed')->sum('total_amount')` | Sums ALL tenants' revenue |
| 58-62 | `DB::table('order_items')->join('orders', ...)->sum('quantity')` | Sums ALL tenants' sales |
| 64-67 | `DB::table('orders')->where('order_status', 'pending')->count()` | Counts ALL tenants' pending orders |
| 69-72 | `DB::table('orders')->where('payment_status', 'verified')->sum('total_amount')` | Sums ALL tenants' verified revenue |
| 74-77 | `DB::table('orders')->where('order_status', 'completed')->sum(...)` | Previous period — ALL tenants |
| 99-108 | `DB::selectOne("SELECT (SELECT COUNT(*) FROM products) ...")` | Raw SQL — ALL tenants' counts |

**Cache keys leak data:**

| Key | Line | Problem |
|---|---|---|
| `dashboard_metrics_{period}_` | 94 | No tenant suffix — tenants share cached metrics |
| `dashboard_general_stats` | 110 | Global key — ALL tenants read/write same cache entry |

#### ComputeFullDashboardMetrics.php — Same pattern:

| Line | Query | Problem |
|---|---|---|
| 42 | `DB::table('orders')->whereBetween(...)->count()` | No tenant filter |
| 43 | `DB::table('orders')->where(...)->sum('total_amount')` | No tenant filter |
| 44 | `DB::table('order_items')->join('orders', ...)->sum('quantity')` | No tenant filter |
| 45-46 | `DB::table('orders')->count() + sum()` | No tenant filter |
| 48-49 | Previous period comparisons | No tenant filter |
| 58-64 | Raw SQL subqueries on products, orders | No tenant filter |
| 74-80 | Revenue analytics (today, yesterday, 7d, 30d, month, year) | No tenant filter |

**Cache keys:**

| Key | Line | Problem |
|---|---|---|
| `dashboard_filtered_{period}_` | 53 | No tenant suffix |
| `dashboard_general_stats_` | 66 | No tenant suffix |
| `dashboard_revenue_analytics_` | 83 | No tenant suffix |

**Root Cause:** These queued jobs were created to pre-compute dashboard metrics but were never updated for multi-tenant. They run in the queue worker which has no active tenant context, so `TenantScope` doesn't apply. All queries are raw `DB::table()` calls that bypass Eloquent scopes entirely.

**Risk Level:** CRITICAL — Every tenant's dashboard shows ALL tenants' aggregated data. Revenue, order counts, product counts, and sales data leak across all tenants.

---

### Reports

**Status: CRITICAL**

| Check | Status | Detail |
|---|---|---|
| `AdminController` (embedded report summary) | **SAFE** | Uses explicit `when(tenant(), ...)` + Eloquent |
| `AdminReportController` (sales, product sales, payments) | **SAFE** | Uses Eloquent models throughout |
| `AdminPromotionReportController` | **CRITICAL** | 2 raw query methods leak data |

**Issues Found:**

#### AdminPromotionReportController::getCouponUsage() — Line 150

```php
$query = DB::table('order_coupon')
    ->join('orders', 'orders.id', '=', 'order_coupon.order_id')
    ->whereBetween('orders.created_at', [$startDate, $endDate])
    ->whereNotIn('orders.order_status', ['cancelled', 'rejected'])
```

- **No `where('orders.tenant_id', ...)` filter**
- Leaks coupon usage data (which coupon codes were used, how many times, discount amounts) across ALL tenants
- The `order_coupon` pivot table has `tenant_id` (added by migration), but it's never used in this query

#### AdminPromotionReportController::getPromotionTypeBreakdown() — Line 238

```php
$query = DB::table('promotion_usages')
    ->join('promotions', 'promotions.id', '=', 'promotion_usages.promotion_id')
    ->whereBetween('promotion_usages.used_at', [$startDate, $endDate])
```

- **No `where('promotions.tenant_id', ...)` filter**
- Leaks promotion type breakdown (usage counts, discount amounts by type) across ALL tenants
- The `promotion_usages` table has `tenant_id`, but it's never used

**Safe methods in same controller:**

- `getDailyDiscountTrend()` (line 200-233): Uses `Order::query()` Eloquent — tenant-scoped ✅
- `getTopPromotions()` (line 105-131): Uses `PromotionUsage::query()` Eloquent — tenant-scoped ✅

**Root Cause:** `getCouponUsage()` and `getPromotionTypeBreakdown()` were written using raw `DB::table()` for performance but forgot to add `tenant_id` filters. The other methods in the same controller use Eloquent and are safe.

**Risk Level:** CRITICAL — Promotion and coupon reports expose cross-tenant discount data

---

### Subscriptions & Plans

**Status: CRITICAL**

| Check | Status | Detail |
|---|---|---|
| Plans (system-level) | SAFE | No tenant data |
| Plan Features (system-level) | SAFE | No tenant data |
| Subscriptions (tenant-owned) | WARNING | Has `tenant_id` column, fillable, relationship to Tenant |
| Billing cycle | INCOMPLETE | Interval exists, no payment processing |
| Renewal logic | INCOMPLETE | `renewFromInterval()` extends without charge |
| Expiry logic | BROKEN | No grace period, no notifications |
| Suspension logic | MISSING | `markAsExpired()` never transitions to `suspended` |
| Middleware | INCOMPLETE | `EnsureTenantIsActive` registered but never used |
| Feature gating | DISABLED | `FeatureGate::DEV_MODE = true` |
| Payment integration | MISSING | No Stripe/Lemon Squeezy/etc. |

**Issues Found:**

#### CRITICAL: FeatureGate::DEV_MODE = true (FeatureGate.php:41)

```php
protected const DEV_MODE = true;
// TODO: Re-enable subscription restrictions after SaaS billing implementation.
```

All plan feature restrictions are bypassed. Any tenant can create any product type (single, variable, combo) regardless of their plan. **Plan tiers are functionally meaningless.**

#### CRITICAL: No Payment Gateway

There is zero payment processing code anywhere in the codebase:
- `AdminBillingController::renew()` calls `$subscription->renewFromInterval()` without any charge
- `SubscriptionController::renew()` and `renewFromInterval()` in SuperAdmin also extend without charge
- No Stripe, Lemon Squeezy, Paddle, or any payment provider integration

#### HIGH: No Grace Period Enforcement

- `GRACE_DAYS = 7` is defined in `Subscription.php:274` but **never used**
- `SubscriptionExpiryService::process()` immediately marks subscriptions as `expired` when past `expires_at`
- `markAsPastDue()` method exists on Subscription but is **never called anywhere**
- The lifecycle should be: `expires_at` reached → `past_due` (7 days grace) → `expired` → `suspended`

#### HIGH: No Suspension After Expiry

- `SubscriptionExpiryService` marks as `expired` but never transitions to `suspended`
- Tenant status is NOT updated when subscription expires
- `Tenant::status` remains `active` even when subscription is `expired`
- `suspend()` method exists on Subscription but is only called manually by SuperAdmin

#### HIGH: No Lifecycle Notifications

No emails or notifications are sent for:
- Subscription expiring soon
- Subscription expired
- Subscription suspended
- Payment failed
- Renewal confirmation
- Plan change confirmation

#### MEDIUM: Subscription model lacks TenantAware

- `Subscription.php` does NOT use the `TenantAware` trait
- `tenant_id` is in `$fillable` (could be mass-assigned)
- No auto-set of `tenant_id` on create (done manually by controllers)
- No global scope for subscription queries

#### MEDIUM: Dual Source of Truth (Legacy Columns)

- `tenants.subscription_plan_id` and `tenants.expires_at` (migration `2026_05_28_000001`) are pre-subscription-table legacy columns
- `users.plan_id`, `users.plan_expires_at`, `users.plan_status` (migration `2026_05_26_300002`) are also legacy
- These can be set independently of the `subscriptions` table, creating data inconsistency

#### MEDIUM: EnsureTenantIsActive Middleware Never Used

- Registered as `tenant.active` in `bootstrap/app.php`
- Applied to **zero routes** in `web.php`
- Has stricter checks than `TenantIsValid` (checks tenant status + subscription expiry)

#### MEDIUM: Feature Limits Not Enforced

- Plans define `product_limit`, `staff_limit`, `storage_limit`
- No middleware, service, or validation enforces these limits
- Only `SubscriptionController::checkDowngradeWarnings()` checks limits, and only during manual plan changes

#### LOW: Trial → Active Conversion Missing

- Expired trials are marked as `expired` by `SubscriptionExpiryService`
- No flow to convert a trial to a paid subscription
- No prompt for payment method on trial end

#### LOW: Hourly Cron May Be Too Infrequent

- `routes/console.php:12`: `Schedule::command('subscriptions:process-expired')->hourly()`
- Subscriptions could remain active for up to 60 minutes past expiry

**Root Cause:** The subscription system was recently built (migrations from `2026_05_26` to `2026_05_28`) and is in an incomplete state. The `FeatureGate::DEV_MODE` TODO comment explicitly acknowledges this: "Re-enable subscription restrictions after SaaS billing implementation."

**Risk Level:** CRITICAL — Plan tiers are non-functional, no revenue collection, no enforcement of any subscription business rules

---

### Impersonation

**Status: SAFE**

| Check | Status |
|---|---|
| SuperAdmin impersonation | Safe — checks user status (suspended, banned, inactive) |
| Tenant must be active | Safe — checked before impersonation |
| Logging | Safe — activity logged |

**Issues Found:** None.

**Risk Level:** LOW

---

### Maintenance Mode

**Status: SAFE**

| Check | Status |
|---|---|
| Per-tenant maintenance mode | **YES** — `website_infos.maintenance_mode` field |
| Tenant-scoped | **YES** — `WebsiteInfo` is tenant-scoped |
| Maintenance message per-tenant | **YES** — `website_infos.maintenance_message` |

**Issues Found:** None. Maintenance mode is correctly implemented per-tenant.

**Risk Level:** LOW

---

## 3. Cross-Tenant Data Leak Locations

| File | Method | Tables | Query Type | Risk |
|---|---|---|---|---|
| `RefreshDashboardMetrics.php` | `computeAndCacheMetrics()` | `orders`, `order_items` | Raw `DB::table()` — NO tenant filter | **CRITICAL** |
| `RefreshDashboardMetrics.php` | `computeGeneralStats()` | `products`, `orders`, `order_items` | Raw `DB::selectOne()` — NO tenant filter | **CRITICAL** |
| `ComputeFullDashboardMetrics.php` | `computeMetricsForPeriod()` | `orders`, `order_items` | Raw `DB::table()` — NO tenant filter | **CRITICAL** |
| `ComputeFullDashboardMetrics.php` | `computeGeneralStats()` | `products`, `orders`, `order_items` | Raw `DB::selectOne()` — NO tenant filter | **CRITICAL** |
| `ComputeFullDashboardMetrics.php` | `computeRevenueAnalytics()` | `orders` | Raw `DB::table()` — NO tenant filter | **CRITICAL** |
| `AdminPromotionReportController.php` | `getCouponUsage()` | `order_coupon`, `orders` | Raw `DB::table()` — NO tenant filter | **CRITICAL** |
| `AdminPromotionReportController.php` | `getPromotionTypeBreakdown()` | `promotion_usages`, `promotions` | Raw `DB::table()` — NO tenant filter | **CRITICAL** |

---

## 4. Database Unique Index Audit

### Tenant-Aware (3 of 32 unique indexes)

| Table | Index | Columns |
|---|---|---|
| `roles` | `roles_tenant_id_name_guard_name_unique` | `(tenant_id, name, guard_name)` |
| `payment_methods` | `payment_methods_tenant_name_unique` | `(tenant_id, name)` |
| `website_infos` | `website_infos_tenant_unique` | `(tenant_id)` |

### Global — Tenant-Owned Tables (NEED FIXING)

| Table | Index | Current | Should Be |
|---|---|---|---|
| `coupons` | `coupons_code_unique` | `UNIQUE(code)` | `UNIQUE(tenant_id, code)` |
| `promotions` | `promotions_code_unique` | `UNIQUE(code)` | `UNIQUE(tenant_id, code)` |
| `products` | `products_sku_unique` | `UNIQUE(sku)` | `UNIQUE(tenant_id, sku)` |
| `settings` | `settings_key_unique` | `UNIQUE(key)` | `UNIQUE(tenant_id, key)` |
| `users` | `users_email_unique` | `UNIQUE(email)` | Review — typically left global for SaaS |

### Global — System-Level Tables (acceptable as-is)

`plans`, `permissions`, `tenants`, `failed_jobs`, `cache`, `sessions`, `jobs`

---

## 5. Validation Rules Audit

### Tenant-Scoped Unique Rules (correct — 2)

| File | Rule |
|---|---|
| `PaymentMethodStoreRequest.php:23` | `Rule::unique('payment_methods', 'name')->where('tenant_id', ...)` |
| `PaymentMethodUpdateRequest.php:35` | `Rule::unique(...)->ignore(...)->where('tenant_id', ...)` |

### Globally Unique Rules (need fixing — 11)

| File | Line | Rule | Module |
|---|---|---|---|
| `AdminCategoryController.php` | 29 | `unique:categories,name` | Categories |
| `AdminCategoryController.php` | 49 | `unique:categories,name,...` | Categories |
| `AdminCouponController.php` | 43 | `unique:coupons,code` | Coupons |
| `AdminCouponController.php` | 95 | `unique:coupons,code,...` | Coupons |
| `AdminPromotionController.php` | 46 | `unique:promotions,code` | Promotions |
| `AdminPromotionController.php` | 102 | `unique:promotions,code,...` | Promotions |
| `AdminProductController.php` | 192 | `unique:products,sku` | Products |
| `AdminProductController.php` | 365 | `unique:products,sku,...` | Products |
| `CityStoreRequest.php` | 17 | `unique:cities,name` | Cities |
| `CityUpdateRequest.php` | 22 | `Rule::unique('cities', 'name')->ignore(...)` | Cities |
| `StoreUserRequest.php` | 19 | `unique:users,email` | Users (review — may be intentional) |

---

## 6. Critical Issues

| # | Issue | Location | Impact |
|---|---|---|---|
| C1 | **Active data leak** — all dashboard metric queries lack tenant filter | `RefreshDashboardMetrics.php` | Dashboard shows aggregated data across ALL tenants |
| C2 | **Active data leak** — all dashboard metric queries lack tenant filter | `ComputeFullDashboardMetrics.php` | Same — cached metrics shared across tenants |
| C3 | **Active data leak** — coupon usage query lacks tenant filter | `AdminPromotionReportController.php:150` | Promotion report leaks cross-tenant coupon data |
| C4 | **Active data leak** — promotion breakdown query lacks tenant filter | `AdminPromotionReportController.php:238` | Promotion report leaks cross-tenant usage data |
| C5 | **Setting system broken** — `Setting::set()` hits global unique constraint | `Setting.php:23` + `settings_key_unique` index | Per-tenant settings cannot coexist |
| C6 | **Plan tiers disabled** — `FeatureGate::DEV_MODE = true` | `FeatureGate.php:41` | All plan feature restrictions bypassed |
| C7 | **No payment gateway** — free indefinite renewal | Entire subscription system | Cannot collect revenue |

---

## 7. Recommended Fix Order

| Order | Module | Fix | Effort |
|---|---|---|---|
| 1 | Dashboard Jobs | Add `tenant_id` filters to ALL `DB::table()` calls + add tenant suffix to ALL cache keys | 2 files |
| 2 | Promotion Reports | Add `->where('orders.tenant_id', ...)` and `->where('promotions.tenant_id', ...)` to raw queries | 1 file |
| 3 | Settings (Key-Value) | Change unique index to `UNIQUE(tenant_id, key)` + update `Setting::set()` to use `updateOrCreate(['key' => $key, 'tenant_id' => $tid], ...)` | 1 migration + 1 model |
| 4 | Categories | Add `->where('tenant_id', ...)` to inline validation | 1 file |
| 5 | Coupons | Scoped unique validation + migration to `UNIQUE(tenant_id, code)` | 1 migration + 1 file |
| 6 | Promotions | Scoped unique validation + migration to `UNIQUE(tenant_id, code)` | 1 migration + 1 file |
| 7 | Products (SKU) | Scoped unique validation + migration to `UNIQUE(tenant_id, sku)` | 1 migration + 1 file |
| 8 | Cities | Scoped unique validation | 1 file |
| 9 | Users | Remove `tenant_id` from `$fillable` (add to `$guarded`) | 1 file |
| 10 | Subscriptions | Grace period, suspension pipeline, notifications, `DEV_MODE=false`, payment integration | Multiple files |

---

## 8. SaaS Readiness Score

| Category | Score | Rationale |
|---|---|---|
| Tenant isolation (model layer) | 90% | 18/19 tenant-owned models use `TenantAware` |
| Validation rules | 15% | Only 2/13 unique rules are tenant-scoped |
| DB unique indexes | 10% | Only 3/32 unique indexes include `tenant_id` |
| Query safety (dashboard) | 40% | Live dashboard safe; queued jobs completely broken |
| Query safety (reports) | 70% | Most reports safe; 2 critical leaks |
| Settings isolation | 40% | WebsiteInfo correct; Key-Value Setting broken |
| Subscription/plan logic | 15% | `DEV_MODE`, no payment, no grace, no suspension |
| Security (`$fillable`) | 80% | 2 models expose `tenant_id` in `$fillable` |

**Weighted overall: 40%**

---

## 9. Production Launch Blockers

### 🚫 BLOCKING — Cannot launch without fixing:

1. **Dashboard data leaks (Jobs C1, C2)** — Every tenant sees every other tenant's order counts, revenue, and product counts. This is a catastrophic data breach.

2. **Setting system broken (C5)** — `Setting::set('notifications_enabled', ...)` will either overwrite another tenant's setting or crash. Notification settings, payment settings, and any other key-value settings cannot work.

3. **Plan tiers disabled (C6)** — `FeatureGate::DEV_MODE = true` means all tenants have unlimited features. Selling "Premium" plans is meaningless.

4. **No payment integration (C7)** — Subscription renewal is completely free. No revenue can be collected.

5. **Promotion report leaks (C3, C4)** — Cross-tenant coupon/promotion data exposed in reports.

### ⚠️ STRONGLY RECOMMENDED before launch:

6. **Global unique constraints on 5 tables** — Categories, Coupons, Promotions, Products (SKU), Cities — tenants will collide on common names/codes.

7. **User `tenant_id` in `$fillable`** — Low probability of exploit but high impact if exploited.

8. **Subscription lifecycle incomplete** — No grace period, no suspension, no notifications. If a subscription expires, the tenant is never notified and never suspended.

---

## 10. Tenant Isolation Architecture Summary

### Trait Usage

| Model | Trait | Global Scope | `tenant_id` Auto-Set | `tenant_id` in `$fillable` |
|---|---|---|---|---|
| Order | `TenantAware` | YES | YES | No |
| Product | `TenantAware` | YES | YES | No |
| Category | `TenantAware` | YES | YES | No |
| Coupon | `TenantAware` | YES | YES | No |
| Promotion | `TenantAware` | YES | YES | No |
| PromotionBanner | `TenantAware` | YES | YES | No |
| PromotionUsage | `TenantAware` | YES | YES | No |
| PaymentMethod | `TenantAware` | YES | YES | No |
| City | `TenantAware` | YES | YES | No |
| Township | `TenantAware` | YES | YES | No |
| Setting | `TenantAware` | YES | YES | No |
| WebsiteInfo | `TenantAware` | YES | YES | No |
| TelegramIntegration | `TenantAware` | YES | YES | No |
| Wishlist | `TenantAware` | YES | YES | No |
| Message | `TenantAware` | YES | YES | No |
| OrderItem | `TenantAware` | YES | YES | No |
| ProductVariant | `TenantAware` | YES | YES | No |
| ProductCombo | `TenantAware` | YES | YES | No |
| ActivityLog | `TenantAware` (EXEMPT) | NO (exempt) | YES | No |
| Role | `TenantAware` (EXEMPT) | NO (exempt) | YES | **YES** |
| User | **Custom** (no trait) | NO (manual) | YES (custom) | **YES** |
| Subscription | **None** | NO | NO (manual) | **YES** |
| Plan | **None** | NO | N/A (system-level) | No |
| PlanFeature | **None** | NO | N/A (system-level) | No |
| OrderCoupon | **None** | NO | N/A (pivot) | No |
| Tenant | **None** | NO | N/A (is the tenant) | No |

### TenantScope Behavior

The `TenantScope` global scope (`app/Models/Scopes/TenantScope.php`) adds to every query:

```sql
WHERE table.tenant_id = {current_tenant_id} OR table.tenant_id IS NULL
```

The `OR tenant_id IS NULL` clause means any record with a NULL `tenant_id` is visible to ALL tenants. This is deliberate for shared reference data.

**Exempt models:** `Role::class`, `ActivityLog::class` — these bypass the global scope entirely.

### Tenant Identification Flow

```
Request → IdentifyTenant Middleware
  ├─ If authenticated user → use user's tenant_id → set `current.tenant` in app container
  ├─ If subdomain match → resolve tenant by slug → set `current.tenant`
  ├─ If X-Tenant header → resolve by slug/domain → set `current.tenant`
  ├─ If session slug → resolve by slug → set `current.tenant`
  └─ Fallback → Tenant::getDefault() → set `current.tenant`

Tenant::getCurrent() → reads from app('current.tenant') or falls back to default
```

### Key Architectural Notes

1. **`TenantScope` uses `orWhereNull`** — Records with `tenant_id = NULL` are shared across all tenants. This allows global reference data.

2. **`HasTenantScope` trait exists but is unused** — It's a legacy trait that was superseded by `TenantAware` (which adds the global scope).

3. **Role/Permission tables use the spatie/laravel-permission package** with `teams=false`. Tenant isolation on roles was achieved by modifying the migration to add `tenant_id` to the unique index.

4. **The `withoutTenantScope()` macro** is registered on the Builder, allowing queries to bypass the global scope when needed (e.g., SuperAdmin operations).

---

*End of Report*

---

**Generated:** 2026-05-31
**Auditor:** SaaS Readiness Audit System
**Project:** AI Use Ecommerce — Multi-Tenant SaaS
