# Phase 6C-5 — Tenant Purge Failure/Retry Safety Audit

**Date:** 2026-10-15
**Phase:** 6C (Subscription Cancellation / Data Retention / tenant auto-deletion)
**Status:** Complete — review done, three gaps hardened, focused suites green
**Verdict:** No data-loss or corruption defect found. Production auto-deletion remains **DISABLED / NO-GO**.

---

## Scope

Review the tenant data purge failure and retry paths for correctness and safety, add focused regression coverage, and harden the identified gaps. This phase is **non-destructive** — no purge was executed against real data.

Production auto-deletion continues to require **both** gates ON and they remain OFF:
- config `billing.tenant_cleanup.purge_enabled` (env `TENANT_CLEANUP_PURGE_ENABLED`)
- PlatformSetting `tenant_auto_delete_enabled`

## Method / environment note

- Focused suites were run from the dev project (`ai-use-ecommerce-project`). The staging copy's scoped DB user (`ecommerc_staging_user`) is granted only `ecommerc_staging.*` and cannot reach the pinned test database `ecommerc_test`, so its tests fail with `SQLSTATE[HY000] [1044] Access denied`. This is an environment limitation, not a code defect.
- The reviewed purge code is **byte-identical** between dev and staging (SHA-256 verified for `TenantDataPurgeService.php`, `ImageService.php`, the test suites, `PurgeTenantData.php`, and `TenantDeletionService.php`), so running the suites in dev validates the same code.
- No full test suite was run; only the three targeted suites below.

## Modified files

| File | Change |
|------|--------|
| `app/Services/TenantDataPurgeService.php` | attempts increment + cap, empty-directory pruning, side-effect-free dry-run |
| `config/billing.php` | new `tenant_cleanup.max_purge_attempts` setting |
| `tests/Feature/TenantRetentionSafetyTest.php` | 3 new regression tests |

## Created files

| File | Purpose |
|------|---------|
| `docs/phase-6C-5-purge-retry-safety-audit.md` | this report |

The staging copy mirrors the three modified files (hash-verified SAME) so the purge subsystem stays identical between dev and staging.

## Implementation summary

### 1. Attempts increment + cap
- New config: `billing.tenant_cleanup.max_purge_attempts` (`TENANT_CLEANUP_MAX_PURGE_ATTEMPTS`, default `5`).
- `bumpAttempts()` increments `tenant_cleanups.attempts` through a **query-builder** update (`DB::raw('attempts + 1')`) because the model's `integer` cast rejects a raw expression on `Model::update()`.
- Increment is now applied on **both** previously-uncovered failure paths:
  - file-verification failure (deleted DB rows, files not verified),
  - file-inventory collection failure (`recordCollectionFailure`).
- `process()` excludes cleanups with `attempts >= max_purge_attempts` from automatic retries and logs a `parked for manual review` warning. Parked cleanups keep `status=pending_deletion` / `deletion_status=failed` and require operator action (by design — no endless loop).

### 2. Empty-directory pruning
- `pruneEmptyDirectories()` collects the parent chains of successfully-processed local paths, then removes **only empty** directories deepest-first, stopping at shared prefixes (`defaults`, `placeholder(s)`, `platform-settings`, `seed(s)`).
- Best-effort: a pruning error is logged and never fails the purge. Shared/other-tenant directories are preserved because non-empty directories are never deleted.

### 3. Side-effect-free dry-run
- `purgeTenant($id, dryRun = true)` no longer cancels the cleanup when an active subscription is present. Cancellation of a stale deletion still happens for real (non-dry) runs.

## Tests added (`TenantRetentionSafetyTest`)

- `test_service_level_single_tenant_dry_run_is_non_mutating` — `purgeTenant($id, true)` on an eligible tenant returns true while leaving products, subscription, cleanup state, tenant status/lock, and audit log untouched.
- `test_repeated_purge_failures_are_parked_after_max_attempts` — with `max_purge_attempts=2`, two failing runs increment attempts to 2; the third run selects nothing (candidates=0) and data is preserved.
- `test_empty_tenant_directories_are_pruned_but_referenced_dirs_are_kept` — an emptied tenant directory is removed while a directory holding other files and shared `defaults/` remain.

## Test results

| Suite | Result |
|-------|--------|
| `TenantRetentionSafetyTest` | **28 passed** (150 assertions) |
| `TenantRetentionDeletionTest` | **12 passed** (64 assertions) |
| `CloudinaryDeletionTest` | **10 passed** (17 assertions) |
| **Total** | **50 passed** |

`php -l` clean on all modified PHP files.

## Manual testing checklist

- [x] `php -l` on each modified PHP file
- [x] Focused suites only (no full suite), each within the phase command budget
- [x] Dry-run (`purgeTenant($id, true)`) proven non-mutating on an eligible tenant
- [x] Attempts cap parks repeatedly-failing cleanups and preserves data
- [x] Empty tenant directories pruned; shared and non-empty dirs preserved
- [x] Collection-failure and file-failure retry paths still recover on a later run
- [x] Cloudinary unconfigured path still records `metadata.cloudinary_skipped` and finalizes
- [x] Gates confirmed OFF (`purgeAllowed() = false`) — no destructive run performed

## Regression checklist

- [x] Success purge idempotent, protected records preserved, audit logged once
- [x] File-verification failure retains durable inventory and retries
- [x] Shared/local files of other tenants preserved
- [x] Pivot rows removed for the target tenant only
- [x] Cross-tenant location references block city/township deletion
- [x] Concurrency: second purge blocked by lock
- [x] Manual deletion dry-run changes nothing; active subscription refused
- [x] Purge subsystem files identical between dev and staging

## Remaining issues / known gaps

| Item | Notes |
|------|-------|
| Cloudinary remote deletion unverified when unconfigured | Assets are skipped and recorded in `metadata.cloudinary_skipped`; the purge still finalizes. Requires real Cloudinary creds (or a test account) to verify end-to-end. |
| Lock fallback | If the cache lock store is unavailable, only the row-level lock guards the DB phase; file deletion/finalization are idempotent, so the worst case is duplicate work. |
| Parked cleanups require manual action | Once `attempts >= max_purge_attempts` a cleanup is no longer auto-retried; an operator must investigate/reset. Intended behavior. |
| Dry-run with active subscription | Returns `false` without cancelling the cleanup (intended, side-effect-free). |
| `attempts` on the `process()` thrown-exception path | Still incremented via the pre-existing query-builder update (`process()` catch); unchanged. |

## Completion summary

The purge failure/retry paths were reviewed end-to-end and are safe: destructive work is transaction-wrapped, collection failures abort before any deletion, and all retry/finalization steps are idempotent. The three identified gaps (missing attempts tracking/cap, lingering empty directories, mutating dry-run on active subscription) are now fixed with focused regression tests. Production auto-deletion remains disabled pending real-Cloudinary verification.
