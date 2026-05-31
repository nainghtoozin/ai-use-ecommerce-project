# SaaS v2 Release Candidate Notes

> **Version:** 2.0.0-RC.2
> **Previous RC:** 2.0.0-RC.1
> **Status:** All previously identified production-blocking issues resolved. Subscription/billing system requires completion before paid multi-plan launch.

---

## 1. What's New in RC.2

### 1.1 TenantScope Data Leak Fix
**Before:** `TenantScope` applied `WHERE tenant_id = ? OR tenant_id IS NULL` to all queries — any record with a null `tenant_id` leaked to all tenants.
**After:** The `orWhereNull` clause is gated behind `allowsNullTenantFallback()`, which defaults to `false`. No model in the codebase overrides it. Null-`tenant_id` records are no longer shared.

### 1.2 Tenant::getCurrent() Queue/Console Fix
**Before:** `getCurrent()` fell back to `getDefault()` when no tenant binding existed in the container — queue jobs and artisan commands silently operated under the "default" tenant.
**After:** `getCurrent()` returns `null` when no tenant binding exists. Callers handle `null` explicitly. Queue jobs pass `$tenantId` as constructor parameters where needed.

### 1.3 Comprehensive Subscription Lifecycle
The subscription engine has been rebuilt with a full lifecycle pipeline:

```
ACTIVE → PAST_DUE (7-day grace) → EXPIRED (1 day) → SUSPENDED
```

Key components:
- **Grace period enforcement** — `SubscriptionExpiryService::transitionActiveToPastDue()` gives 7 days
- **Suspension pipeline** — `transitionExpiredToSuspended()` sets `Tenant::status = 'suspended'`
- **Expiry warnings** — `SendExpiryWarnings` command runs daily, warns at 7, 3, and 1 days before expiry
- **Lifecycle notifications** — `SubscriptionExpiringSoon`, `SubscriptionExpired`, `SubscriptionSuspended`, `SubscriptionRenewed`
- **Race condition protection** — `Cache::lock()` used in subscription expiry processing
- **Middleware enforcement** — `EnsureTenantIsActive` (`tenant.active`) applied to all operations routes

### 1.4 Checkout Subscription Gating
**Before:** Expired/past-due subscription tenants could freely place orders.
**After:** `OrderController::store()` blocks expired subscriptions with a clear error message. `ClientOrderController` mutation methods (cancel, upload payment proof, confirm payment) also block expired subscriptions. The checkout page shows distinct warning banners for `expired` (red, blocking) and `past_due` (amber, warning) states.

### 1.5 Impersonation Forensic Audit Trail
**Before:** All activity during impersonation was attributed to the impersonated merchant — no audit trail back to the SuperAdmin.
**After:**
- New `impersonator_id` and `impersonated_user_id` columns on `activity_logs` table
- `LogsActivity` trait and `ActivityLogger` service detect impersonation via `session('impersonator_id')` and set `causer_id` to the real SuperAdmin
- Activity Log UI shows "Performed By: SuperAdmin" and "Acting As: Merchant Name"
- Impersonation start/stop events also populate the new columns

### 1.6 Checkout Past-Due Warning
The `HandleInertiaRequests` middleware now shares `subscription_past_due` and `subscription_expired` flags. The checkout page displays an amber warning for past-due subscriptions and a red blocking warning for expired subscriptions.

---

## 2. Known Limitations (RC.2)

### 2.1 Feature Gates Disabled (DEV_MODE = true)
`FeatureGate.php:41`: `protected const DEV_MODE = true;` — All plan feature restrictions are bypassed. Every tenant has access to all features regardless of plan tier. This is by design until a payment gateway is integrated.

### 2.2 No Payment Gateway
No revenue collection exists. `AdminBillingController::renew()` calls `renewFromInterval()` which extends the subscription without any payment. No Stripe, Lemon Squeezy, or any payment processing library is installed.

### 2.3 No Email Delivery
All 15 notification types use the `database` channel only. Users never receive email notifications for orders, payments, or subscription events. Notifications are sent synchronously (`->send()` not `->queue()`).

### 2.4 Empty Exception Handler
`bootstrap/app.php:54-56` has an empty exception handler callback. In production, unhandled exceptions may expose Laravel debug pages. A custom handler should be added.

### 2.5 Legacy Database Columns
Two migrations added legacy columns that are now superseded:
- `tenants.subscription_plan_id` and `tenants.expires_at` (migration `2026_05_28_000001`)
- `users.plan_id`, `users.plan_started_at`, `users.plan_expires_at`, `users.plan_status` (migration `2026_05_26_300002`)

Current code uses the `subscriptions` table, but the legacy columns remain and could diverge.

---

## 3. Configuration Checklist

### Required Before Production Testing

- [ ] **Run all migrations** — `php artisan migrate`
- [ ] **Sync roles per tenant** — `php artisan tenants:sync-roles`
- [ ] **Set `APP_ENV=production`** — Disables debug mode
- [ ] **Set `APP_DEBUG=false`** — Prevents debug page exposure
- [ ] **Configure queue connection** — Set `QUEUE_CONNECTION` to `database` or a production driver
- [ ] **Start queue worker** — `php artisan queue:work` (configure as system service)
- [ ] **Run migration rollback test** — `php artisan migrate:rollback --step=9 && php artisan migrate`

### Recommended Before Production Testing

- [ ] **Add exception handler** — Custom error pages (403, 404, 500)
- [ ] **Set up error monitoring** — Sentry, Flare, or similar
- [ ] **Set up database backups** — Automated daily
- [ ] **Configure SMTP** — Even if only for admin alerts
- [ ] **Review `per_page=all` behavior** — Add cap to prevent memory exhaustion

---

## 4. Migration Guide (Single-Tenant → Multi-Tenant)

### Data Migration

1. Run `php artisan migrate` — Creates new tables, adds columns, modifies indexes
2. Run `php artisan tenants:sync-roles` — Copies system roles to each existing tenant
3. Verify `activity_logs` table has new `impersonator_id` + `impersonated_user_id` columns
4. Verify unique indexes are compound `UNIQUE(tenant_id, column)` for 5 tables

### Code Changes

The following breaking changes were introduced in RC.2:

| Change | Impact |
|--------|--------|
| `RefreshDashboardMetrics` now requires `$tenantId` | Update dispatchers |
| `ComputeFullDashboardMetrics` now requires `$tenantId` | Update dispatchers |
| `DashboardService` methods require tenant parameter | Update callers |
| `User::create()` rejects `tenant_id`, `is_owner`, `plan_*` | Use model methods instead |
| `Subscription::create()` rejects `tenant_id` | Set via `TenantAware` trait |
| `City::getActiveWithTownships()` cache key changed | Cache will invalidate automatically |
| `ActivityLog` model has new fillable fields | `impersonator_id`, `impersonated_user_id` |

### Zero Downtime Considerations

All 9 migrations in RC.2 are additive (new columns, new indexes) or drop-then-recreate unique indexes. The `down()` methods are reversible. For production:

1. Run migrations during low-traffic window
2. Verify index creation completes before new code deploy
3. Deploy code after migration confirms success

---

## 5. Rollback Plan

```bash
# Step 1: Roll back all RC.2 migrations
php artisan migrate:rollback --step=9

# Step 2: Deploy previous code
git checkout <previous-tag>

# Step 3: Verify rollback
php artisan migrate:status
```

---

## 6. Architecture Decisions

### Why Dedicated Columns for Impersonation Instead of JSON?
Dedicated `impersonator_id` and `impersonated_user_id` columns were chosen over storing in `properties` JSON because:
1. Efficient querying for audit reports (no JSON parsing)
2. Foreign key constraints prevent orphaned references
3. Native indexing for JOIN queries
4. Self-documenting schema

### Why Session-Based Impersonation Detection Instead of Request Attributes?
Session detection was chosen over request attributes (`request()->attributes->set()`) because:
1. The `LogsActivity` trait fires from model events that may not have access to the current request
2. Session is available anywhere within the request lifecycle
3. Queue jobs naturally have no session, so impersonation detection correctly defaults to "not impersonating"

### Why ClientOrderController::store() Was Not Changed
The `store()` method in `ClientOrderController` handles a JSON API-style checkout that was identified as an alternate checkout path. It is not directly routed for POST requests in `routes/web.php`. Adding the check is recommended for defense-in-depth but is not a production blocker.

---

*End of Release Candidate Notes*
*Generated: 2026-05-31*
