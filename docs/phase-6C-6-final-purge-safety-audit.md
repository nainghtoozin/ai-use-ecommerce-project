# Phase 6C-6 — Final Purge Safety Audit

**Date:** 2026-10-15
**Phase:** 6C (Subscription Cancellation / Data Retention / tenant auto-deletion)
**Type:** Read-only audit (no code, configuration, database, or tenant-record changes)
**Verdict:** **NO-GO for production auto-deletion.** Keep both gates OFF.

---

## Scope

Final audit of the tenant purge stack after the Phase 6C-5 hardening: `TenantDataPurgeService`, `ImageService`, the `tenants:purge` command, and cleanup configuration. This phase is **read-only** — no purge was executed, no data/files deleted, no gates/scheduler enabled, no shared/production Cloudinary credentials used, no full test suite run.

**Parity check (objective 4):** the Phase 6C-5 artifacts are **identical** in `ai-use-ecommerce-project` (dev) and `ai-use-ecommerce-project-staging` (staging). SHA-256 `SAME` for: `app/Services/TenantDataPurgeService.php`, `app/Services/ImageService.php`, `app/Console/Commands/PurgeTenantData.php`, `app/Console/Commands/ProcessTenantCleanup.php`, `config/billing.php`, `app/Models/TenantCleanup.php`, `app/Services/TenantDeletionService.php`, `tests/Feature/TenantRetentionSafetyTest.php`, `tests/Feature/TenantRetentionDeletionTest.php`, `tests/Unit/CloudinaryDeletionTest.php`. Neither copy was modified.

---

## 1. Confirmed fixes and test evidence

### Code confirmed (file:line in `app/Services/TenantDataPurgeService.php` unless noted)

| Safety property | Location |
|-----------------|----------|
| Double gate (`config purge_enabled` AND PlatformSetting `tenant_auto_delete_enabled`) | `purgeAllowed()` 128-139; `process()` guard 148-151; `purgeTenant()` guard 237-239 |
| Dry-run is non-mutating (active subscription is NOT cancelled on dry-run) | 219-235 — active-sub branch guarded by `if (!$dryRun)`; dry-run returns at 233 before gates/lock/`performPurge` |
| `attempts` incremented on thrown-exception path | 187-193 (query-builder raw) |
| `attempts` incremented on file-verification failure | 408 via `bumpAttempts()` 827-832 |
| `attempts` incremented on collection failure | 853 via `bumpAttempts()` |
| Attempts cap parks cleanups (no endless retry) | 153-168 (`attempts < maxPurgeAttempts`) + parked warning; `maxPurgeAttempts()` 822-825; config `billing.tenant_cleanup.max_purge_attempts` |
| Collection failure aborts BEFORE any deletion | `performPurge` catch 373-377; transaction 274-372; `recordCollectionFailure` 834-859 |
| Partial file deletion retains durable inventory and is retry-idempotent | 383-416 (`failed_files` + `pending_files` retained; DB rows already committed; retry finalizes) |
| Protected financial data preserved | `PROTECTED_TABLES` 19-50 (`subscriptions`, `invoices`, `subscription_payments`, `payment_intents/evidences/transactions/timeline_events`, `subscription_audit_logs`, `receipts`) |
| Empty-dir pruning cannot cross shared/unrelated paths | 570 (call), 710-749 (`pruneEmptyDirectories`), 751-762 (`isSharedPath`), 764-779 (`isDeletablePath`) |
| Concurrency guard | per-tenant cache lock 241-264 (`LOCK_TTL_SECONDS=600`); command lock `PurgeTenantData.php:28` (3600s) |
| Cloudinary unconfigured → skip + record | 650-667 (`metadata.cloudinary_skipped`, returns true) |

### Test evidence

Focused suites recorded during Phase 6C-5; the working tree is unchanged since (verified via `git status` + parity hashes), so no tests were re-run in 6C-6 per the "do not repeat" instruction.

| Suite | Result |
|-------|--------|
| `TenantRetentionSafetyTest` | 28 passed (150 assertions) |
| `TenantRetentionDeletionTest` | 12 passed (64 assertions) |
| `CloudinaryDeletionTest` | 10 passed (17 assertions) |
| **Total** | **50 passed** |

Staging rehearsal evidence (Phase 6C-4, tenant id 5 `zzz-purge-rehearsal`):
- `preflight_6c4.txt`: `PREFLIGHT=ALL_PASS` — env=staging, DB denied on dev DB, exactly one pending cleanup, retention passed, no active/trialing subscription, both gates OFF, backup dump checksum matched.
- `post_purge_verify.txt`: `POST_PURGE=ALL_PASS` — all 44 deletable tables zero for tenant 5; protected rows preserved (`subscription_payments`=1); tenant shell `status=deleted` + locked; cleanup `status=deleted`, `attempts=0`, `metadata` has `purged_at`/`purged_tables`, no `failed_files`/`pending_files`; all 21 tenant files absent; `purgeAllowed()=false`, both gates OFF.

---

## 2. Missing test coverage

| # | Required scenario | Status | Test(s) |
|---|-------------------|--------|---------|
| a | Dry-run with an active subscription causes no state changes | **PARTIAL — gap** | Active-sub *refusal* covered by `test_manual_deletion_refuses_active_subscription` (manual path, `TenantDeletionService`), but `TenantDataPurgeService::purgeTenant($id, true)` with an active subscription is **not** asserted (the 6C-5 dry-run fix at :219-224 relies on code inspection) |
| b | Collection and file-verification failures increment attempts | **PARTIAL — gap** | Collection increment covered via `test_repeated_purge_failures_are_parked_after_max_attempts`; **file-verification** increment (:408) is **not** asserted by any test |
| c | Attempts cap prevents further automatic processing | Covered | `test_repeated_purge_failures_are_parked_after_max_attempts` (3rd `process()` → candidates 0; attempts==2) |
| d | Partial file deletion followed by retry completes safely | Covered | `test_failed_file_purge_retains_durable_inventory_and_retry_completes`, `test_retry_completes_using_pending_files_when_failed_files_never_persisted`, `test_retry_after_collection_failure_recovers_and_purges`, `test_completed_purge_is_idempotent_and_does_not_redelete_files` |
| e | Concurrent purge attempts cannot overlap destructively | Covered | `test_concurrent_purge_is_blocked_by_lock` |
| f | Protected financial data remains preserved | Covered | `test_financial_payment_history_is_preserved_by_purge`, `test_purge_deletes_tenant_data_but_preserves_protected_records` |
| g | Empty-directory pruning cannot cross shared/unrelated paths | Covered | `test_empty_tenant_directories_are_pruned_but_referenced_dirs_are_kept`, plus `test_purge_deletes_only_owned_files_and_keeps_shared_assets`, `test_shared_file_is_preserved_during_retry` |

**Gaps (a) and (b) were NOT closed** because 6C-6 is read-only (adding tests would modify code). They are low-risk: both behaviors are directly guaranteed by the inspected code paths. Recommended as a small follow-up in a future non-read-only phase.

No relevant *runnable* targeted test was missing, so none was run.

---

## 3. Confirmed defects

**None.** No data-loss or corruption defect was found. The destructive path is transaction-wrapped, aborts before any deletion on collection failure, and all finalization/retry steps are idempotent.

Non-blocking observations (by design / conservative, not defects):
- A tenant whose DB rows were deleted but whose files fail verification stays `pending_deletion` with `status=deleted` unset until verification succeeds; if attempts reach the cap it is parked for manual review. Intentional.
- `isSharedPath`/`isDeletablePath` do loose prefix/substring matching (`seed`, `..`) — over-conservative (may skip a legitimate path), never over-aggressive.
- Concurrency fallback: if the cache lock store is unavailable the code proceeds with the row-level lock only; file work remains idempotent, so worst case is duplicate work.

---

## 4. Remaining infrastructure limitations

- **Cloudinary remote deletion is unverifiable in the current environments.** The staging env has Cloudinary unconfigured; the dev `.env` holds shared/real credentials that must not be used for verification. When unconfigured, `deleteCloudinaryAsset()` returns `true` and records `metadata.cloudinary_skipped` (purge still finalizes), so remote assets would silently orphan.
- No dedicated non-production Cloudinary account/cloud was available for the rehearsal.
- Cross-repo test execution: staging's scoped DB user (`ecommerc_staging_user`) cannot reach the pinned test DB `ecommerc_test`; focused suites are therefore executed in the dev project (same code).
- `routes/console.php:34` schedules `tenants:purge --limit=50` weekly. Safety therefore rests entirely on the double gate — both currently OFF. Any host running `schedule:run` still no-ops while the gates are off.
- Production requires a persistent queue worker for notification jobs (general requirement, not purge-specific).

### Cloudinary verification — exact evidence required

To verify remote deletion end-to-end (without production/shared credentials):
1. Provision an isolated (non-production) Cloudinary cloud with its own key/secret; configure it only in the isolated staging env.
2. Seed one tenant with a tenant-unique Cloudinary asset (e.g., `products/zzz-verify-<uniq>.jpg`) and a second tenant sharing a *different* asset.
3. Run `tenants:purge --tenant=<id>` with gates enabled in the isolated env, then capture:
   - Admin API `asset(publicId)` for the tenant-unique id **before** (present) and **after** (404/absent).
   - The shared asset still present (`asset()` returns it).
   - `tenant_cleanups.metadata` contains **no** `cloudinary_skipped` and **no** `failed_files`; cleanup finalizes `status=deleted`.
   - Log: no "Cloudinary deletion failed verification" entries (the code re-checks absence via the Admin API and resolves `resource_type` image/video/raw).
4. Re-run once to confirm idempotency (asset stays absent; no second destroy call).

---

## 5. Is another isolated staging rehearsal necessary?

**No.** Rationale:
- The 6C-5 hardening does not change the **successful** purge path: the rehearsal left `attempts=0` and the expected terminal state, and the attempts cap only affects failing retries.
- The only new behavior on a real purge is empty-directory pruning, which is non-destructive (removes only empty directories, never shared/referenced ones) and is covered by a focused test.
- The 6C-4 rehearsal already proved the destructive path end-to-end (DB, protected audit preservation, 21 files removed) plus backup/restore integrity.
- Re-rehearsing would require re-seeding tenant 5 (now `deleted`) and another destructive run — not warranted for these cosmetic/retry-only changes.

Optional follow-up (not required): a targeted staging spot-check that empty rehearsal directories are pruned after the hardening.

---

## 6. Final GO/NO-GO

**NO-GO for production auto-deletion.** Automatic purge must remain disabled:
- `config('billing.tenant_cleanup.purge_enabled')` = **false** (env unset/dev, `false`/staging)
- PlatformSetting `tenant_auto_delete_enabled` = **false**
- Confirmed: `purgeAllowed() = false` in both dev and staging contexts.

To reach GO, all of the following must hold:
1. True Cloudinary credentials available, with remote deletion verified per §4 evidence.
2. Optional: close the two coverage gaps (§2) in a non-read-only phase.
3. Operator runbook for parked cleanups (manual review after `max_purge_attempts`).
4. A phased enablement on a single disposable tenant with monitoring before any broad rollout.
5. Queue worker + scheduler operating per AGENTS.md.

Until then, the safe posture is **keep both gates OFF**.
