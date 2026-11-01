# Sprint 4B.5A - Lifecycle and Retention Eligibility Alignment

## Summary

Aligned the retention evaluator with the existing subscription lifecycle without adding destructive behavior, schema changes, schedulers, queues, or cleanup jobs.

The evaluator now:

- Treats verified lifecycle-generated suspensions as ended subscriptions.
- Keeps manual/superadmin suspensions fail-closed.
- Ignores operational tenant status lag for ended subscriptions.
- Resolves multiple subscriptions deterministically.
- Treats cancellations as historical after the paid-through date.
- Ignores expired waiting-payment intents while keeping submitted reviews actionable.
- Flags completed payments that were never marked as applied.
- Returns explicit end, grace, and retention-review dates.
- Uses a configurable protected-tenant rule instead of a hard-coded slug check.

## Files Changed

- `app/Services/TenantRetentionEligibilityService.php` - lifecycle-aware eligibility, subscription selection, cancellation/payment handling, deadlines, protected tenants.
- `config/retention.php` - retention timezone and protected tenant IDs/slugs/settings key.
- `tests/Feature/TenantRetentionEligibilityTest.php` - expanded coverage for lifecycle suspension, cancellations, payments, multiple subscriptions, protected tenants, deadlines, and tenant-status lag.

No other application files were modified in this phase.

## Resulting Lifecycle and Eligibility Rules

### Subscription Selection

- The latest subscription by ID is authoritative.
- More than one open subscription (`pending`, `active`, `trialing`, `past_due`) requires manual review.
- If exactly one open subscription exists but the latest subscription is not open, the tenant requires manual review.
- Unknown subscription statuses require manual review.
- Multiple ended subscriptions use the latest ended subscription deterministically.

### Ended Subscription Detection

- `expired` and `canceled` subscriptions are ended.
- `suspended` is treated as ended only when all of the following are true:
  - `suspended_at` exists.
  - The applicable expiry (trial end for trials, expiry otherwise) is not in the future.
  - `suspended_at` is not before the applicable expiry.
  - The latest `suspended` audit event has `old_status = expired`.
  - The audit actor is `system` or null.
- Missing, manual, or legacy suspension evidence remains `manual_review_required` with `ambiguous_subscription_status` and `suspension_origin_unverified`.

### Tenant Status and Operational Lag

- Tenant `status` and `locked_at` are not authoritative for retention.
- `active`, `trialing`, or `pending` tenant status no longer conflicts with an ended subscription.
- A suspended tenant with an open subscription still conflicts and requires manual review.
- Tenant `expires_at` mismatches are only considered while the subscription is still open.

### Deadlines

The evaluator returns:

- `subscription_ended_at`
- `applicable_expiry_at`
- `grace_ends_at`
- `retention_review_at`
- `grace_days`, `retention_days`, `review_after_days`

Grace ends at the start of day plus 7 days. Retention review occurs at the start of day plus 22 days (7-day grace plus 15-day retention). Timezone comes from `config('retention.timezone')`, defaulting to the application timezone.

### Cancellation Handling

- Effective cancellation statuses are `scheduled`, `approved`, and `pending`.
- A cancellation request blocks retention until the applicable paid-through date.
- After paid-through, the request is treated as historical and does not block eligibility forever.
- Requests linked to another subscription are ignored; tenant-level requests remain fail-closed until paid-through.

### Payment Handling

- `draft`, `pending`, and `waiting_payment` block only when `expires_at` is null or not past.
- `waiting_review` always requires manual review.
- `waiting_review` adds `payment_review_unresolved` only when no approved or rejected review exists.
- `approved` and `paid` always require manual review.
- Completed intents without `metadata.subscription_applied` block retention when relevant to the authoritative subscription, plan, or renewal/upgrade/plan-change type.
- Completed intents marked `subscription_applied` do not block.
- Pending and completed payment reasons are deduplicated and deterministic.

### Plan Changes

- Any pending plan change requires manual review.
- A pending plan change whose effective date has passed additionally receives `stale_plan_change`.

### Protected Tenants

- Protected tenant IDs come from `config('retention.protected_tenant_ids')`.
- Protected tenant slugs come from `config('retention.protected_tenant_slugs')`.
- A tenant setting `retention_protected = true` also protects the tenant.
- The old `default` slug behavior is configurable rather than hard-coded in the service.
- `default_tenant` remains as a constant alias; the emitted reason code is `protected_tenant`.

## Tests Run

- `php artisan test --filter=TenantRetentionEligibilityTest` - 41 passed, 102 assertions.
- `php artisan test --filter=SubscriptionRenewalCancellationTest` - 36 passed, 210 assertions.
- `php artisan test --filter=SubscriptionCancellationNotificationsTest` - 9 passed, 50 assertions.
- `php artisan test --filter=SubscriptionLifecycleNotificationsTest` - 4 passed, 14 assertions.
- `php artisan test --filter=ManualSubscriptionRenewalTest` - 21 passed, 176 assertions.
- `php artisan test --filter=tenant_delete` - 3 passed, 8 assertions.
- `php -l app\Services\TenantRetentionEligibilityService.php` - no syntax errors.
- `php -l config\retention.php` - no syntax errors.
- `php -l tests\Feature\TenantRetentionEligibilityTest.php` - no syntax errors.

Known unrelated test-isolation failures:

- `SubscriptionLockModeTest` full class: 17 failed, 2 passed due `Spatie\Permission\Exceptions\RoleAlreadyExists`.
- `MerchantManagementTest` full class: 8 failed due the same role duplication issue.
- The targeted tenant-delete tests pass.

No frontend build was run because no frontend files changed.

## Unresolved Decisions and Limitations

- Trial access behavior is unchanged: the lifecycle locks at trial expiry and suspends after one day, while retention waits for the 7-day grace and 15-day retention period. The evaluator now uses the trial expiry and verified lifecycle suspension for retention.
- `SubscriptionExpiryService` still uses `updated_at` for the expired-to-suspended cutoff, so unrelated subscription updates can reset that timer. Changing this would alter access behavior and was not done.
- Legacy suspended subscriptions without a reliable `suspended` audit event require manual review.
- Completed payments from before `subscription_applied` metadata existed may require manual review.
- No hold persistence exists; hold status remains externally supplied and fail-closed.
- `tenant_cleanups` is unused and was not modified.
- Protected tenants are config/settings based; there is no schema marker.
- Timezone is configured by `RETENTION_TIMEZONE` or the application timezone, not per-tenant timezone.
- Export completeness remains a separate deferred workstream.

## Safety Confirmation

- No application data was modified.
- No uploaded files, Cloudinary assets, or exports were deleted or changed.
- Automatic deletion remains disabled; no scheduler, queue job, archive, purge, anonymization, or cleanup workflow was added.
- No database migrations were created.
- No all-tenant dry-run was executed.
- No commit, push, branch, or merge was performed.
