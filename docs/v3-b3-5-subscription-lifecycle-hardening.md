# V3-B3-5: Subscription Lifecycle Hardening

## Status: Completed

## Changes

### 1. Downgrade Enforcement (`SubscriptionController::changePlan()`)
- **Before**: `checkDowngradeWarnings()` flashed warnings only — downgrade proceeded silently even if tenant exceeded new plan limits.
- **After**: If tenant exceeds new plan's product/staff limits and the change is a downgrade (free plan or lower price), the request is **blocked** with an error message listing what needs to be reduced. Never silently downgrades.

### 2. Grace Period Duplicate Protection (`SubscriptionExpiryService`)
- Wrapped each batch processing step in a `DB::transaction()` so overlapping cron runs cannot double-process a subscription.
- Each step's WHERE clause (e.g., `status = 'active' AND expires_at < now()`) naturally prevents re-processing since the status transitions to `past_due` → `expired` → `suspended`. The DB transaction provides an additional safety net against race conditions.
- Added `$sub->refresh()` inside the transaction to ensure latest state.

### 3. Subscription Audit Log

**New table**: `subscription_audit_logs`
- `subscription_id`, `tenant_id`, `event`, `actor_type`, `actor_id`, `old_plan_id`, `new_plan_id`, `old_status`, `new_status`, `reason`, `metadata` (JSON)

**New model**: `App\Models\SubscriptionAuditLog`
- Relationships: `subscription()`, `tenant()`, `oldPlan()`, `newPlan()`, `actor()` (morphTo)

**New service**: `App\Services\SubscriptionAuditService`
- Static `log()` method automatically detects actor type: `superadmin`, `merchant`, or `system`.

**Events tracked**:
| Event | Where |
|---|---|
| `subscription_created` | `SubscriptionController::assign()` |
| `trial_started` | `TenantBootstrapService::createSubscription()` |
| `plan_changed` | `SubscriptionController::changePlan()` |
| `renewed` | `SubscriptionController::renew()`, `renewFromInterval()`, `AdminBillingController::renew()` |
| `canceled` | `SubscriptionController::cancel()` |
| `suspended` | `SubscriptionController::suspend()`, `SubscriptionExpiryService` (auto-suspend) |
| `activated` | `SubscriptionController::activate()`, `ActivateTenantOnVerified` |
| `past_due` | `SubscriptionExpiryService` (enter grace period) |
| `expired` | `SubscriptionExpiryService` (grace end, trial end) |
| `trial_ended` | `SubscriptionExpiryService::transitionTrialToExpired()` |

### 4. SuperAdmin Subscription Show Page
- New **Audit Log** table below the History table.
- Columns: Date, Event (colored badge), Actor, From Status, To Status, Plan change, Reason.
- Loads 50 most recent entries.

### 5. Tenant Billing Page
- New **Activity History** section showing recent audit log entries (20 most recent).
- Each entry shows event type, timestamp, and reason.

### 6. `Subscription` Model
- Added `auditLogs()` hasMany relationship.

## Files Changed

| File | Change |
|---|---|
| `database/migrations/2026_06_28_000002_create_subscription_audit_logs_table.php` | New migration |
| `app/Models/SubscriptionAuditLog.php` | New model |
| `app/Models/Subscription.php` | Added `auditLogs()` relationship |
| `app/Services/SubscriptionAuditService.php` | New audit logging service |
| `app/Services/SubscriptionExpiryService.php` | Added audit logging + DB transaction wrapper |
| `app/Services/TenantBootstrapService.php` | Logs `trial_started` |
| `app/Listeners/ActivateTenantOnVerified.php` | Logs `activated` |
| `app/Http/Controllers/SuperAdmin/SubscriptionController.php` | Blocks downgrade; logs all lifecycle actions; includes audit logs in `show()` |
| `app/Http/Controllers/Admin/AdminBillingController.php` | Includes audit logs in response; logs self-service renewals |
| `resources/js/Pages/SuperAdmin/Subscriptions/Show.jsx` | Removed downgrade warnings section; added Audit Log table |
| `resources/js/Pages/Admin/Billing/Index.jsx` | Added Activity History section |
| `tests/Feature/TrialLifecycleTest.php` | Added `subscription_audit_logs` table to `createMinimalSchema()` |

## Test Results

All relevant test suites pass (53 tests, 142 assertions):
- TrialLifecycleTest: 8/8
- SubscriptionLimitServiceTest: 17/17
- FeatureGateTest: 19/19
- PlatformSettingsTest: 9/9

Pre-existing failures (unrelated to this work):
- RoleManagementTest, UserManagementTest: SQLite `notifications` JOIN syntax error
- StorefrontCustomerTest: missing `city_id`/`township_id` fields

## Production Readiness

- Migration is backward-compatible (no existing data changes).
- Audit logs are append-only — no impact on existing subscription logic.
- Downgrade enforcement only affects the change-plan flow; cancel/renew/suspend/activate unchanged.
- All changes behind existing authentication/authorization guards.
- No payment gateway integration or billing architecture redesign.
