# Sprint 4B-5F - System Backup Recovery Drill

**Date:** 2026-10-24
**Scope:** Prove that a 4B-5E system backup artifact restores into a verified disposable database and an isolated media directory, using synthetic data and the existing backup service.
**Out of scope:** Production restore command, UI, scheduling, automatic recovery, media collision policy, encryption, off-host replication, and any change to merchant export, billing, subscriptions, retention, or identity logic.

## 1. Backup and Restore Flow

The drill runs only when `SYSTEM_RESTORE_DRILL=1`; otherwise the test is skipped.

```
create disposable source DB  ecommerc_backup_src_4b5f
  └─ migrate --database=backup_drill_source --force   (source DB only)
  └─ synthetic fixtures (existing DrillFixture: 2 tenants, customers, products, variant,
     combo, stock movements, order + items + coupon, storefront, settings, media asset)

SystemBackupService::create()
  └─ DatabaseDumper: PDO REPEATABLE READ + START TRANSACTION WITH CONSISTENT SNAPSHOT
  └─ media enumeration from the local 'public' disk
  └─ ZIP: database/database.sql + media/public/... + manifest.json (bytes + SHA-256)

SystemBackupValidator::validate(archive, restoreDirectory)     <- preflight before any restore
  └─ entry allowlist, supported versions, sizes, hashes, path containment

create disposable target DB  ecommerc_backup_dst_4b5f          (asserted absent first)
  └─ test-only ArchiveRestorer::restoreDatabase()  (refuses a non-empty target)
  └─ verify table set, per-table row counts, relationships, inventory, settings, tenant scoping

delete the fixture media asset, then
  └─ ArchiveRestorer::restoreMedia() into %TEMP%\system-restore-drill-<random>
  └─ verify extracted file hash against the manifest and the restore directory boundary

optional mysqldump path (binary detected):
  └─ SystemBackupService::create() with BACKUP dump binary
  └─ validate again, restore into ecommerc_backup_dst_4b5f_dump, repeat row-count verification

tearDown: purge drill connections, drop only DBs created by the drill, delete restore dir,
          delete archive files, remove fixture asset
```

No application database is targeted at any point. The only DDL executed against the test connection is `CREATE DATABASE` / `DROP DATABASE` for the drill's own three disposable databases.

## 2. Isolation Safeguards

| Safeguard | Implementation |
|---|---|
| Source safety | Asserts `testing` environment, MySQL driver, database name `ecommerc_test` before any work |
| Target naming | All drill databases must start with `ecommerc_backup_` and must not equal the configured app database, `ecommerc_test`, or `ecommerc_db2` |
| Refuse existing DB | `SHOW DATABASES` checked before every `CREATE DATABASE`; the drill fails rather than reuse a database |
| Empty restore target | `ArchiveRestorer::restoreDatabase` aborts if the target already contains tables (verified by a second restore attempt that throws) |
| Database connection isolation | Source and both targets use dedicated connections (`backup_drill_source`, `backup_drill_target`, `backup_drill_target_dump`) built from the MySQL config with only the database name replaced |
| Media isolation | `Storage::fake('public')` and `Storage::fake('local')`; restores land in a random system temp directory, never in application storage |
| Media containment | Every entry resolved through `SystemBackupValidator::mediaTargetPath()` before writing |
| Preflight order | Validation completes before the target database is created or any file is extracted |
| Cleanup on failure | All cleanup runs in `tearDown`; only databases recorded in `createdDatabases` are dropped, plus the drill's own archive/temp files |
| No restore surfaces | No route, controller, command, job, or UI was added |

## 3. Files Added

| File | Role |
|---|---|
| `tests/Feature/SystemBackupRecoveryDrillTest.php` | Gated drill: DB + media restore, verification, mysqldump variant, cleanup |
| `tests/Support/SystemRestoreDrill/ArchiveRestorer.php` | Test-only restore helper: empty-target refusal, multi-statement DB apply, contained media extraction with checksum verification |
| `docs/sprint-4b.5f-system-backup-recovery-drill.md` | This report |

Changed: none. The backup service, dumper, validator, merchant export, and all application code are untouched.

## 4. What Was Restored and Verified

Database (both the PDO snapshot archive and the mysqldump archive):

- Table name sets identical to the source (all base tables created by the migrations, including `migrations`).
- Per-table row counts identical to the source for every table, not only a sample.
- Relationships: order -> tenant/user, product -> category/brand/unit, variant -> product, combo -> both products, category parent chain, storefront -> published revision, order items (2) and order coupon (1).
- Inventory: product-level signed movement sum equals 7, variant-level equals 4, matching the fixture stock.
- Settings and website info restored verbatim, including the tenant `smtp_password` value. This is expected for a full system backup and is exactly why the artifact is operator-only.
- Multi-tenant fidelity: tenant B's product and category are present with tenant B ownership, not collapsed into tenant A.

Media:

- Fixture asset was deleted before restoration, so the restored file can only come from the archive.
- Exactly one media file was extracted into the isolated temp directory; its content, byte size, and SHA-256 match the manifest.
- Restored path stays inside the designated restore directory; nothing was written to application storage.

## 5. Commands and Results

| Command | Result |
|---|---|
| `$env:SYSTEM_RESTORE_DRILL = "1"; php artisan test tests/Feature/SystemBackupRecoveryDrillTest.php` | **1 passed, 296 assertions** (37.48s), including the mysqldump variant |
| `php artisan test tests/Feature/SystemBackupTest.php` | **10 passed, 66 assertions** (1.70s) |
| `php -l tests/Feature/SystemBackupRecoveryDrillTest.php` | No syntax errors |
| `php -l tests/Support/SystemRestoreDrill/ArchiveRestorer.php` | No syntax errors |
| `git diff --check` | Only the pre-existing CRLF warning in `tests/Feature/SubscriptionRenewalCancellationTest.php` |
| Post-run database scan | 0 `ecommerc_backup_*` databases remain |
| Post-run temp scan | 0 `system-restore-drill-*` directories and 0 `system-backup-*` files remain |
| Merchant export suite | Not re-run - no export file or shared export code was changed |
| Full test suite | Not run (per phase rules) |

The first drill attempt failed on a test-local assertion that assumed the restored target contained only tenant A data; tenant B was correctly present. The assertion was corrected to verify tenant B ownership, and the drill was re-run once. No application code changed.

**mysqldump path:** verified in this environment. `C:\xampp\mysql\bin\mysqldump.exe` (MariaDB 10.4.28) was detected, the archive manifest recorded `method = mysqldump`, and that archive restored with identical row counts. It remains environment-specific and is not guaranteed on a deployment host.

## 6. Status

| Item | Status |
|---|---|
| Preflight before restore | Verified |
| PDO consistent-snapshot archive restore | Verified |
| mysqldump archive restore | Verified in this environment |
| Database restore into disposable target | Verified |
| Media extraction with containment and checksums | Verified |
| Cleanup of drill resources | Verified (post-run scan) |
| Production restore executor / CLI / UI | Planned - not implemented |
| Restore under production data volume | Not verified |

## 7. Limitations

- The restore executor is test support only. There is no application restore service, command, or route.
- The database restore uses a single multi-statement `PDO::exec` with the SQL held in memory. This is fine for the drill archive (well under 1 MB) but is not a streaming restore and was not tested at production size.
- The PDO snapshot omits views, stored routines, triggers, and events (none exist in the current migrations). The mysqldump path includes them.
- Verification compares table names, row counts, and sampled relationships; it does not compare every column value across every table, nor does it re-validate foreign-key integrity with a full scan.
- Media restoration uses an empty target directory; no collision, overwrite, or merge policy exists.
- Remote media disks (Cloudinary) are still not bundled, so those assets remain unrestorable from this artifact.
- The archive contains tenant secrets by design; there is no encryption, access control, or off-host replication yet.
- No point-in-time recovery, no binary-log metadata, and no backup scheduling, retention, or alerting exist.
- The drill runs single-threaded with no concurrent write load, so snapshot behaviour under sustained writes is not proven.

## 8. Remaining Blockers Before Production Restore

1. Operator-only restore command/service with authorization, audit logging, and no HTTP surface.
2. Streaming database restore with statement-level error reporting, plus a hard guard that refuses the configured application database at the service layer (today the guard lives in test support).
3. Version and migration-state compatibility gate, refusing archives from an incompatible codebase.
4. Media restore executor with containment, collision policy, and a Cloudinary/remote asset strategy.
5. Artifact protection: encryption at rest, private access control, off-host replication, retention and rotation policy.
6. Production-scale rehearsal (dump/restore duration, lock impact, memory) and documented RTO/RPO.
7. Backup failure monitoring/alerting, and a documented operator runbook.

## Final Confirmation

- No production, staging, or development database was restored into, overwritten, or dropped. Only the drill's own three disposable databases were created, restored into, and dropped; `ecommerc_test` received no row writes.
- Existing media and backup archives were not deleted or moved. Storage was faked for the drill, and restores landed in a system temp directory that was removed.
- No schema migration file was added or modified. Existing migrations ran only inside the drill's own disposable source database; target databases were created from the dump itself.
- Tenant retention, subscriptions, billing, cancellation, and identity/ownership logic were untouched.
- No commit, push, branch, reset, discard, or stash occurred; all pre-existing uncommitted and untracked work is preserved.
- Claims are limited to the recorded output: the drill, `SystemBackupTest`, lint, and cleanup scans above. Production restore readiness is not claimed.
