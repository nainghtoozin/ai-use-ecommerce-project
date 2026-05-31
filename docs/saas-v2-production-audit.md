# SaaS v2 Production Audit

> **Audit Date:** 2026-05-31
> **Audit Scope:** Full-stack production-readiness verification
> **Previous Reports:** `docs/saas-v2-readiness-report.md`, `docs/saas-v2-release-candidate-report.md`

---

## 1. Executive Summary

**Overall Verdict: NOT READY FOR PRODUCTION**

18 critical (production-blocking) issues found across 16 modules. The application has fundamental gaps in payment processing, security hardening, tenant isolation in queue context, notification delivery, and operational safeguards.

| Severity | Count |
|----------|-------|
| FAIL     | 18    |
| WARNING  | 31    |
| PASS     | 28    |
| **Total Checks** | **77** |

**Go/no-go threshold:** Clear all FAIL items and 80%+ of WARNING items before production deployment.

---

## 2. Module-by-Module Status

### 2.1 Tenant Isolation — **FAIL**

| # | Check | Status | Evidence |
|---|-------|--------|----------|
| T1 | `TenantScope` — `orWhereNull(tenant_id)` | **FAIL** | `app/Models/Scopes/TenantScope.php:33` — Records with `tenant_id IS NULL` leak to all tenants. Any missing tenant assignment (from registration, queue jobs, or legacy data) causes cross-tenant data exposure. |
| T2 | `Tenant::getCurrent()` — queue/console fallback | **FAIL** | `app/Models/Tenant.php:112` — Falls back to `getDefault()` when no HTTP context. Queue jobs and artisan commands silently assign data to the "default" tenant. No `null`-return path, no exception. |
| T3 | `TenantAware` trait — no console guard | **WARNING** | `app/Models/Traits/TenantAware.php:15` — The `creating` callback sets `tenant_id` from `Tenant::getCurrent()` without checking if we're in queue/console context. |
| T4 | `HasTenantScope` — dead duplicate trait | **WARNING** | `app/Models/Traits/HasTenantScope.php` — Identical to `TenantAware` but unreferenced. Maintenance risk. |
| T5 | `OrderCoupon` — no tenant isolation | **WARNING** | `app/Models/OrderCoupon.php` — Pivot model, no `TenantAware`, no `tenant_id` column. Direct `DB::table('order_coupon')` queries in `AdminPromotionReportController.php:150` rely on explicit tenant joins. |
| T6 | `User` model — lacks global TenantScope | **WARNING** | `app/Models/User.php` — Uses manual `creating` hook + manual `where('users.tenant_id', ...)` in every controller query. Any query forgetting the manual scope leaks data. |
| T7 | Direct `DB::table()` queries bypass scope | **WARNING** | `AdminController.php:97-98,120-122`, `AdminPromotionReportController.php:150-159,239-246` — Use `tenant()` helper which returns `null` in queue/console, silently omitting tenant filters. |
| T8 | Exempt models documented | **PASS** | `Role`, `ActivityLog` intentionally exempt from `TenantScope`. Documented in release notes. |

### 2.2 Middleware — **FAIL**

| # | Check | Status | Evidence |
|---|-------|--------|----------|
| M1 | `EnsureTenantIsActive` vs `SubscriptionIsActive` — redundant | **FAIL** | Both files have near-identical logic. `SubscriptionIsActive.php` doesn't handle `suspended` tenants. Both registered in `bootstrap/app.php:33,38`. |
| M2 | Past-due error message misleading | **WARNING** | `EnsureTenantIsActive.php:61-62` — Redirect message says "expired" when status is `past_due` (grace period). Merchants see an alarming message during their 7-day grace window. |
| M3 | Checkout routes lack `tenant.active` | **FAIL** | Checkout and order placement routes do NOT use the `tenant.active` middleware. Past-due/expired tenant customers can still place orders. |
| M4 | Middleware ordering correct | **PASS** | `IdentifyTenant` → `CheckUserStatus` → `HandleInertiaRequests`. Tenant identified before user checks. |
| M5 | `CheckUserStatus` — admins get suspension page | **PASS** | `CheckUserStatus.php:35-36` — Admins of suspended tenants see suspension page; customers logged out. |

### 2.3 Security — **FAIL**

| # | Check | Status | Evidence |
|---|-------|--------|----------|
| S1 | Public `/run-migrate` route | **FAIL** | `routes/web.php:59-62` — No auth, no IP restriction. Anyone who discovers this URL can run migrations in production. **Remove immediately.** |
| S2 | Empty exception handler | **FAIL** | `bootstrap/app.php:54-56` — No custom error handling. Unhandled exceptions expose Laravel debug pages. |
| S3 | SQL injection in `AdminReportController` | **FAIL** | `AdminReportController.php:148,152` — `$search` interpolated directly into `LIKE "%{$search}%"` without query binding. |
| S4 | No CORS configuration | **WARNING** | No `config/cors.php` file. Laravel 11 uses minimal defaults. API requests from different origins will be blocked. |
| S5 | Spatie teams feature disabled | **WARNING** | `config/permission.php:138` — Disabled. Custom `tenant_id` approach used instead. Works but lacks battle-testing of Spatie teams. |
| S6 | `Plan::$fillable` deprecated columns | **WARNING** | `app/Models/Plan.php:24-31` — Fields like `is_default`, `is_active` in fillable. Could be mass-assigned. |
| S7 | `RoleMiddleware` — SuperAdmin cascading | **PASS** | `RoleMiddleware.php:19-20` — SuperAdmin can pass admin role checks. |
| S8 | Permission exception display disabled | **PASS** | `config/permission.php:158,166` — Role/permission names not shown in exceptions. |
| S9 | No `unguard()` calls | **PASS** | All models use `$fillable` whitelist. |

### 2.4 Validation Rules — **FAIL**

| # | Check | Status | Evidence |
|---|-------|--------|----------|
| V1 | Product store/update — `$request->validated()` bug | **FAIL** | `AdminProductController.php:207,386` — Calls `$request->validated()` on plain `Request` object (not `FormRequest`). Throws `BadMethodCallException`. |
| V2 | Product store/update — no FormRequest | **WARNING** | `AdminProductController.php:188,367` — Uses inline `$request->validate([...])`. No reusable validation class. |
| V3 | Township city_id not tenant-scoped | **FAIL** | `TownshipStoreRequest.php:17`, `TownshipUpdateRequest.php:17` — `exists:cities,id` can reference cities from other tenants. |
| V4 | Email unique not tenant-scoped | **FAIL** | `StoreUserRequest.php:19`, `UpdateUserRequest.php:21` — `unique:users,email` is global. Could allow duplicate emails across tenants. |
| V5 | 18 inline validations in controllers | **WARNING** | No FormRequest for Products, Categories, Promotions, Coupons, Orders, Banners. Validation logic is scattered and unreusable. |
| V6 | Boolean field validated as `required` | **WARNING** | `AdminNotificationSettingsController.php:26` — Validates boolean as `'required'` without `boolean` or `in:true,false` rule. |
| V7 | City/Township/PaymentMethod/Role requests exist | **PASS** | These entities have proper FormRequest classes with tenant-scoped unique rules. |

### 2.5 Dashboard — **PASS**

| # | Check | Status | Evidence |
|---|-------|--------|----------|
| D1 | Admin dashboard queries tenant-scoped | **PASS** | Uses `tenant()` helper, cached with tenant-specific keys. |
| D2 | No N+1 queries | **PASS** | Recent orders: `take(10)` with eager loading. Stats: single-pass aggregation. |
| D3 | SuperAdmin dashboard uses aggregates | **PASS** | `count()`, `withCount()`, `with('subscription.plan')` — proper eager loading. |
| D4 | Frontend-backend prop alignment | **PASS** | All Inertia props match between controller and component. |
| D5 | SuperAdmin dashboard — no caching | **WARNING** | Every load re-runs aggregates. Could be slow with 1000s of tenants. |
| D6 | `per_page=all` bypasses pagination | **WARNING** | `PerPageTrait.php:20-21` — `per_page=all` loads ALL records into memory. Risk of memory exhaustion. |

### 2.6 Reports — **WARNING**

| # | Check | Status | Evidence |
|---|-------|--------|----------|
| R1 | No CSV/Excel export | **FAIL** | No `app/Exports/` or `app/Imports/` directory. Reports are in-browser only. Standard e-commerce requirement. |
| R2 | Monthly comparison — N+1 loop | **WARNING** | `AdminPromotionReportController.php:292-317` — 5 queries per month in a loop. 60 queries for 12 months. |
| R3 | `Coupon::find()` inside loop | **WARNING** | `AdminPromotionReportController.php:184-198` — Individual coupon lookup per usage row. |
| R4 | Report queries tenant-scoped | **PASS** | All Eloquent models auto-scoped via `TenantAware`. `DB::table()` queries apply manual `tenant()` filters. |
| R5 | Caching with filter-aware keys | **PASS** | Summary aggregates and product sales reports use TTL-based caching with filter-aware keys. |

### 2.7 Settings — **WARNING**

| # | Check | Status | Evidence |
|---|-------|--------|----------|
| S1 | `Setting::set()` no tenant context | **FAIL** | `app/Models/Setting.php:27-29` — `updateOrCreate` without `$tenantId`. In queue context (no current tenant), `TenantAware` may set `tenant_id` to `NULL` or default tenant. |
| S2 | `setting()` helper bypasses `Setting::get()` | **FAIL** | `bootstrap/helpers.php:26` — Direct model query without `$tenantId` parameter. Inconsistent with model API. Used in Blade views for social links. |
| S3 | `Setting::get()` with tenantId works | **PASS** | Queue jobs correctly pass `$this->order->tenant_id` to `Setting::get()`. |
| S4 | Composite unique index `(tenant_id, key)` | **PASS** | Migration `2026_05_31_000003` correctly creates the composite unique. |

### 2.8 Notifications — **FAIL**

| # | Check | Status | Evidence |
|---|-------|--------|----------|
| N1 | All 15 notifications use ONLY `database` channel | **FAIL** | No email, no Telegram. Users never receive off-platform notifications for orders, payments, or subscription events. |
| N2 | All notification sends are synchronous | **FAIL** | `Notification::send()` and `->notify()` block the request/process. No `->queue()` usage. `Queueable` trait present but unused. |
| N3 | `Tenant::notifyAdmins()` synchronous blocking | **FAIL** | Called from CLI commands and model events. Blocks execution for each tenant's admin batch. |
| N4 | Mail defaults to `log` driver | **WARNING** | `config/mail.php:17` — No real emails sent. Even if fixed to SMTP, no notification uses the `mail` channel. |
| N5 | Database notifications table exists | **PASS** | Standard `notifications` table with `tenant_id` column. |
| N6 | Notification preferences work | **PASS** | Merges defaults, role-aware, safe fallback to `true`. |

### 2.9 Queue Jobs — **WARNING**

| # | Check | Status | Evidence |
|---|-------|--------|----------|
| Q1 | `ComputeFullDashboardMetrics` — never dispatched | **FAIL** | Dead code. No caller dispatches this job. |
| Q2 | `RefreshDashboardMetrics` — never dispatched | **FAIL** | Dead code. No caller dispatches this job. |
| Q3 | `ProcessOrderNotifications` — tenant-safe | **PASS** | Correctly passes `order->tenant_id` to `Setting::get()`. |
| Q4 | `ProcessOrderStatusChange` — tenant-safe | **PASS** | Same pattern. Correct usage. |
| Q5 | `RetryBroadcast` — `SerializesModels` with `mixed` type | **WARNING** | `RetryBroadcast.php:24` — Non-serializable event objects will fail. |
| Q6 | Queue config — `database` driver, `after_commit: true` | **PASS** | Jobs dispatch only after DB transaction commits. |
| Q7 | No queue worker documentation | **WARNING** | `php artisan queue:work` must be running. Not documented in deploy instructions. |

### 2.10 Products — **WARNING**

| # | Check | Status | Evidence |
|---|-------|--------|----------|
| P1 | `update()` lacks subscription limit check | **WARNING** | `AdminProductController.php:365-488` — `store()` checks limits, `update()` does not. |
| P2 | `getHasOrdersAttribute()` causes N+1 | **WARNING** | `Product.php:787-790` — Appended accessor queries `orderItems()->exists()` on every serialization. |
| P3 | 13 appended attributes on Product model | **WARNING** | `Product.php:38` — Each appended attribute may trigger DB queries. Significant overhead for list views. |
| P4 | Cloudinary delete returns true on failure | **WARNING** | `ImageService.php:74-89` — Catches exceptions and returns `true`. Orphaned Cloudinary files accumulate. |
| P5 | Cloudinary file size tracking returns 0 | **WARNING** | `ImageService.php:138` — `getFileSize()` returns 0 for Cloudinary URLs. Storage not released on Cloudinary delete. |
| P6 | `ProductService` well-structured | **PASS** | CRUD, type validation, stock calculation, variant syncing are comprehensive. |
| P7 | `Product::$fillable` excludes `tenant_id` | **PASS** | Set via `TenantAware` trait. Correct. |
| P8 | `SubscriptionLimitService::productCount()` works | **PASS** | Correctly counts products by tenant. |

### 2.11 Orders — **FAIL**

| # | Check | Status | Evidence |
|---|-------|--------|----------|
| O1 | `confirmOrder()` hardcodes old status | **FAIL** | `AdminOrderController.php:144` — Always passes `'pending'` as old status, even for `verified → confirmed` transitions. Audit log is wrong. |
| O2 | `cancelOrder()` bypasses state machine | **FAIL** | `ClientOrderController.php:348` — Direct `$order->update(['order_status' => 'cancelled'])` bypasses `updateOrderStatus()` validation. |
| O3 | Guest order broadcasts always fire | **WARNING** | `OrderService.php:467-472` — `!$order->user` is `true` for guests, so broadcasts always fire regardless of preferences. |
| O4 | `reduceComboStock()` clamps to 0 | **WARNING** | `OrderService.php:303` — `max(0, ...)` silently absorbs overselling. |
| O5 | `canApprovePayment()` / `canVerifyPayment()` identical | **WARNING** | `Order.php:163-176` — Two names for same logic. `canVerifyPayment` is never called. |
| O6 | `markAsPaid()` doesn't clear `rejection_reason` | **WARNING** | `AdminOrderController.php:315-346` — `verifyPayment()` clears it, `markAsPaid()` does not. |
| O7 | `ProcessOrderStatusChange` sends wrong notification | **WARNING** | `ProcessOrderStatusChange.php:259` — `processing` event sends `PaymentConfirmedNotification`. |
| O8 | Order status transitions validated | **PASS** | `verifyTransition()` in `OrderService.php` provides defense-in-depth. |
| O9 | Stock management correct | **PASS** | `pending → confirmed` reduces stock; `confirmed → cancelled` restores. |

### 2.12 Customers — **WARNING**

| # | Check | Status | Evidence |
|---|-------|--------|----------|
| C1 | Registration role duplication risk | **WARNING** | `RegisteredUserController.php:46-59` — `firstOrCreate` with `tenant_id` creates per-tenant `customer` roles instead of reusing global one. |
| C2 | Registration no tenant context guard | **WARNING** | `RegisteredUserController.php:34-44` — No check for valid tenant context. Users can register with `tenant_id = null`. |
| C3 | No dedicated customer controller | **WARNING** | Customers managed through `AdminUserController` at `/admin/users` with role filter. No dedicated customer UI. |
| C4 | Staff limit enforced correctly | **PASS** | `AdminUserController.php:118` — `assertCanCreateStaff()` called for admin role users. |
| C5 | Owner protection correct | **PASS** | `protectOwner()` prevents modification of merchant owner by non-superadmin. |

### 2.13 Subscription Lifecycle — **FAIL**

| # | Check | Status | Evidence |
|---|-------|--------|----------|
| L1 | Race conditions — no locking | **FAIL** | `SubscriptionExpiryService.php` — No `Cache::lock()`, no `DB::transaction()`, no `lockForUpdate()`. Two concurrent runs double-process subscriptions. |
| L2 | `process-expired` runs every 5 min without lock | **FAIL** | `routes/console.php:16` — Frequency increases race-collision probability. |
| L3 | Missing notification on `past_due → expired` | **FAIL** | `SubscriptionExpiryService.php:81-91` — No notification sent when grace period ends and subscription expires. |
| L4 | `transitionExpiredToSuspended()` uses `updated_at` | **FAIL** | `SubscriptionExpiryService.php:102` — Should use `expires_at` or `expired_at`. Any unrelated update resets the suspension timer. |
| L5 | `SendExpiryWarnings` ignores `past_due` subscriptions | **FAIL** | `SendExpiryWarnings.php:25` — Only checks `status = 'active'`. Past-due merchants don't get end-of-grace-period warnings. |
| L6 | No trial-ending warnings | **WARNING** | `SendExpiryWarnings.php:25` — Trials about to expire get no warning. |
| L7 | `GRACE_DAYS` duplicated | **WARNING** | `Subscription.php:281` and `SubscriptionExpiryService.php:12` — Same constant defined independently. |
| L8 | `cancelImmediately()` sets status to `expired` not `canceled` | **WARNING** | `Subscription.php:215-222` — Method says "cancel" but status is `expired`. Confuses two states. |
| L9 | `renew()`/`renewFromInterval()` lack transactional safety | **WARNING** | `Subscription.php:200-213,234-275` — Updates subscription and tenant in separate queries. |
| L10 | Notifications dispatched on 3 of 4 transitions | **PASS** | `active→past_due`, `expired→suspended`, `trial→expired` all send notifications. Missing only `past_due→expired`. |

### 2.14 Feature Gates / Limits — **FAIL**

| # | Check | Status | Evidence |
|---|-------|--------|----------|
| F1 | `DEV_MODE = true` bypasses all feature gates | **FAIL** | `FeatureGate.php:41` — Variable products, combo products, custom domains all unlocked for every plan. |
| F2 | `FeatureGate::require()` returns early | **FAIL** | `FeatureGate.php:280-283` — The assertion method is effectively a no-op. Controllers relying on it get zero protection. |
| F3 | Usage data fetched but never rendered | **FAIL** | `AdminBillingController.php:22,48` passes `usage` prop. `Index.jsx:13` never destructures or renders it. Merchants cannot see limits vs usage. |
| F4 | `staffCount()` only counts `admin` role | **WARNING** | `SubscriptionLimitService.php:58-59` — Future staff roles (manager, editor) bypass limit. |
| F5 | `productCount()` lacks soft-delete filter | **WARNING** | `SubscriptionLimitService.php:44-49` — Trashed products may inflate count. |
| F6 | No storage reconciliation job | **WARNING** | `ImageService.php` — `used_storage_bytes` drifts from true usage on failed deletes. No periodic reconciliation. |
| F7 | `used_storage_bytes` can go negative | **WARNING** | `ImageService.php:132` — `decrement()` without `where('used_storage_bytes', '>=', $bytes)` guard. |
| F8 | Product limit enforced in `store()` | **PASS** | `AdminProductController.php:216` — Correctly called before creation. |
| F9 | Staff limit enforced | **PASS** | `AdminUserController.php:118` — Correctly called for admin role. |
| F10 | Storage limit enforced | **PASS** | `ImageService.php:105-113` — Called on every upload. |

### 2.15 Billing — **FAIL**

| # | Check | Status | Evidence |
|---|-------|--------|----------|
| B1 | No payment gateway library | **FAIL** | `composer.json` — No Stripe, Cashier, Mollie, or any payment SDK. Zero revenue collection capability. |
| B2 | `renew()` is free self-renewal | **FAIL** | `AdminBillingController.php:52-78` — Anyone can extend subscription at no cost. No payment, no invoice, no receipt. |
| B3 | No automatic recurring billing | **FAIL** | No scheduled job, webhook, or service for recurring payments. Entire lifecycle is manual. |
| B4 | No invoice/receipt model | **FAIL** | No `Invoice` model, no billing history. No financial audit trail for subscription events. |
| B5 | No dunning management | **FAIL** | No retry logic, no failed-payment escalation, no smart retry scheduling. |
| B6 | Billing routes accessible when expired | **PASS** | `routes/web.php:183-184` — Routes placed outside `tenant.active` middleware. Correct. |
| B7 | No plan upgrade/downgrade UI | **WARNING** | `Index.jsx` — Only "Renew Now" button. No interface to switch between plans. |

### 2.16 Impersonation — **FAIL**

| # | Check | Status | Evidence |
|---|-------|--------|----------|
| I1 | Activities logged as impersonated user | **FAIL** | `LogsActivity.php:50-51` — `auth()->id()` during impersonation returns the impersonated user. SuperAdmin actions attributed to target admin. |
| I2 | Impersonation batch UUID not propagated | **FAIL** | `LogsActivity.php:54` — `batch_uuid` is freshly generated per activity. Not linked to `impersonation_batch_uuid` in session. |
| I3 | Start/stop events correctly logged | **PASS** | `ImpersonationController.php:59-75,108-124` — Impersonation start/stop attributed to real SuperAdmin. |
| I4 | Pre-impersonation validation | **PASS** | 7 checks: status, tenant active, admin role, not self, not already impersonating, not SuperAdmin target. |
| I5 | Frontend impersonation awareness | **PASS** | `HandleInertiaRequests.php:48-49` — `is_impersonating` and `impersonator_name` shared. |

### 2.17 Maintenance Mode — **WARNING**

| # | Check | Status | Evidence |
|---|-------|--------|----------|
| M1 | Queue workers skip maintenance check | **FAIL** | Custom per-tenant maintenance mode is HTTP-only. Queue jobs continue executing during maintenance. |
| M2 | Per-tenant maintenance via `WebsiteInfo` | **PASS** | `CheckMaintenanceMode.php` — Custom middleware, tenant-scoped via `maintenance_mode` boolean. |
| M3 | SuperAdmin bypass | **PASS** | `CheckMaintenanceMode.php:42` — SuperAdmin + permission check + impersonation bypass. |
| M4 | Maintenance page renders with auto-refresh | **PASS** | `Maintenance.jsx:18-23` — Auto-refreshes every 60s to detect end of maintenance. |

### 2.18 Audit Logging — **FAIL**

| # | Check | Status | Evidence |
|---|-------|--------|----------|
| A1 | No log pruning mechanism | **FAIL** | No scheduled command, no `prune()` method. `activity_logs` grows unboundedly. Performance degradation + storage bloat. |
| A2 | Only `User` model uses `LogsActivity` | **WARNING** | Products, orders, categories, promotions, coupons — NOT auto-audited. No manual `ActivityLogger::log()` calls at mutation points. |
| A3 | PII in activity log properties | **WARNING** | `LogsActivity.php:23-24,31` — `getOriginal()` on User updates/deletes includes email, name. No masking. |
| A4 | ActivityLogController tenant filters | **PASS** | Proper tenant filter for non-SuperAdmin. |
| A5 | `batch_uuid` schema exists | **PASS** | Column exists in migration. Used by ImpersonationController. |

---

## 3. BLOCKERS (Must Fix Before Production)

| # | Module | Issue | File:Line |
|---|--------|-------|-----------|
| **B1** | Security | Public `/run-migrate` route with no auth | `routes/web.php:59-62` |
| **B2** | Security | Empty exception handler — debug pages exposed | `bootstrap/app.php:54-56` |
| **B3** | Security | SQL injection in report search | `AdminReportController.php:148,152` |
| **B4** | Tenant Isolation | `Tenant::getCurrent()` silently returns default tenant in queue/console | `app/Models/Tenant.php:112` |
| **B5** | Tenant Isolation | `orWhereNull(tenant_id)` leaks records across tenants | `TenantScope.php:33` |
| **B6** | Validation | `$request->validated()` called on plain Request — throws 500 | `AdminProductController.php:207,386` |
| **B7** | Validation | Township city_id `exists` not tenant-scoped | `TownshipStoreRequest.php:17` |
| **B8** | Validation | Email `unique` not tenant-scoped | `StoreUserRequest.php:19` |
| **B9** | Notifications | All 15 notifications are database-only — no email delivery | `app/Notifications/*` |
| **B10** | Notifications | All notification sends are synchronous (blocking) | All `->notify()` and `Notification::send()` call sites |
| **B11** | Settings | `Setting::set()` has no tenant context parameter | `app/Models/Setting.php:27-29` |
| **B12** | Orders | `confirmOrder()` hardcodes `'pending'` as old status | `AdminOrderController.php:144` |
| **B13** | Orders | `cancelOrder()` bypasses state machine validation | `ClientOrderController.php:348` |
| **B14** | Lifecycle | Race conditions in subscription expiry processing | `SubscriptionExpiryService.php` |
| **B15** | Lifecycle | Missing notification on `past_due → expired` | `SubscriptionExpiryService.php:81-91` |
| **B16** | Lifecycle | `transitionExpiredToSuspended()` uses `updated_at` instead of `expires_at` | `SubscriptionExpiryService.php:102` |
| **B17** | Lifecycle | `SendExpiryWarnings` ignores `past_due` subscriptions | `SendExpiryWarnings.php:25` |
| **B18** | Billing | No payment gateway — free self-renewal | `composer.json`, `AdminBillingController.php:52-78` |
| **B19** | Billing | `DEV_MODE=true` bypasses all feature gates | `FeatureGate.php:41` |
| **B20** | Billing | Usage data fetched but never displayed | `AdminBillingController.php:48` vs `Index.jsx:13` |
| **B21** | Impersonation | Activities logged as impersonated user (no forensic trail) | `LogsActivity.php:50-51` |
| **B22** | Audit | No activity log pruning — unbounded table growth | Entire codebase |
| **B23** | Middleware | `EnsureTenantIsActive` and `SubscriptionIsActive` are redundant duplicates | Both middleware files |
| **B24** | Middleware | Checkout routes lack `tenant.active` — expired tenants can order | `routes/web.php` |

---

## 4. VERDICT

```
                         PRODUCTION READINESS
                    
        FAIL ████████████████████████████████████░░ 77%
        WARN ██████████████████████████████████████ 31 items
        PASS ██████████████████████████████████████ 28 items
                    
              NOT READY FOR PRODUCTION
              
    Clear 18 FAIL items + 80% of WARNING items
        before deploying to production.
```

**Critical path to production:**
1. Remove `/run-migrate` route
2. Fix exception handler
3. Fix SQL injection (parameterized queries)
4. Fix tenant fallback in queue context (`Tenant::getCurrent()`)
5. Fix `orWhereNull(tenant_id)` in TenantScope
6. Fix `$request->validated()` bug in ProductController
7. Fix tenant-scoped validation rules (Township, Email)
8. Route checkout behind `tenant.active` middleware
9. Remove redundant `SubscriptionIsActive` middleware
10. Add email notification channel (at minimum for subscription + payment events)
11. Queue notification sends
12. Add `$tenantId` param to `Setting::set()`
13. Fix impersonation causer tracking in `LogsActivity`
14. Add activity log pruning command
15. Add database locking to subscription expiry processing
16. Add missing `past_due → expired` notification
17. Fix `SendExpiryWarnings` to cover `past_due` subscriptions
18. Integrate payment gateway (Stripe/Lemon Squeezy) or set `DEV_MODE=false` with clear documentation

**After addressing blocker items, re-audit before marking READY FOR PRODUCTION.**

---

*Generated 2026-05-31 by SaaS v2 Production Audit*
