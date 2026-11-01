# System Backup Security & Recovery — Completion Report (4B-5I)

Date: 2026-10-24
Final audit: 2026-10-24 (independent defect audit, focused and full-suite execution, operational smoke checks); full-suite failure classification and clean-database re-verification: 2026-10-25.
Scope: encryption, key management, operator restore, off-site copy, scheduling/health/retention, safeguards, tests.
Design baseline: `docs/sprint-4b.5i-backup-security-recovery-plan.md` (D-01–D-24).

This report states what exists, what was executed, and what remains an operator decision. **It is not a production-readiness certification.** Items marked blocked, not implemented or partially verified have no successful execution evidence and must not be relied on until the operator actions in this document are completed.

---

## Status Summary

Status vocabulary: Implemented and Verified / Implemented but Partially Verified / Blocked by External Configuration / Requires Operator Decision / Not Implemented / Failed.

| Area | Status | Basis |
|------|--------|-------|
| SBX1 authenticated encryption + key ring | Implemented and Verified | Round-trip, tamper, truncation, trailing-data, header-edit, wrong-key, missing-key and rewrap tests; real encrypted smoke create/inspect/verify/decrypt |
| Encrypted creation pipeline (no plaintext fallback) | Implemented and Verified | Tests prove no key ⇒ no archive; `.work` empty; plaintext removed after verification |
| Key rotation (`backup:rewrap`) / decryption (`backup:decrypt`) / inspection (`backup:inspect`) | Implemented and Verified | Command tests plus real inspect `--verify` on a created `.sbx` |
| Decrypt output confinement (no plaintext in web root/backup dir) | Implemented and Verified | New guard + regression test; real decrypt to a temp directory succeeded |
| Operator restore (CLI only, guarded, isolated) | Implemented but Partially Verified | Guard tests and full drill against disposable MySQL `drill_*` databases; no production-scale rehearsal |
| Off-site copy with read-back verification | Blocked by External Configuration | Verified only against local/fake disks; no S3 driver or provider credentials available |
| Scheduler entries (opt-in) | Implemented and Verified | `schedule:list` with and without opt-in variables |
| Health reporting (`backup:health`) | Implemented and Verified | Exit codes 0/1, JSON output, stale/missing-key/recent-failure tests, real smoke run |
| Retention (`backup:prune`, dry-run default) | Implemented and Verified | Dry-run and refusal tests; real dry-run on the development host deleted nothing |
| Locking | Implemented but Partially Verified | `flock` verified on one host only; separate create/restore locks do not exclude each other |
| Disk-space preflight, partial-artifact cleanup, permissions | Implemented and Verified | Tests and post-smoke cleanup inspection |
| Monitoring/alert delivery integration | Not Implemented | Audit log + exit code only; no pager/email/webhook delivery |
| Production key escrow/KMS and custody policy | Requires Operator Decision | Code supports multiple key files; escrow/custody is unverified policy |
| Production-scale restore, RPO/RTO sign-off | Blocked by External Configuration | No isolated production-sized host or authorized dataset |
| Multi-host lock coordination | Not Implemented | Lock is host-local (`flock`); single designated backup host required |
| Backup test suites (focused + drills) | Implemented and Verified | 64 passed / 974 assertions on the final code and a freshly migrated `ecommerc_test`; gated drills pass with `SYSTEM_RESTORE_DRILL=1` / `RESTORE_DRILL=1` and skip cleanly without them |
| Full project test suite | Failed (environmental and pre-existing) | `php artisan test` on 2026-10-24: 1356 passed, 498 failed, 2 skipped, 6044 assertions, exit 2; no backup production-code failure except a drill teardown defect that was fixed (see "Full project suite") |

---

## Final audit changes and confirmed defects fixed

All changes are minimal and preserve the SBX1 format, the plain-zip compatibility path and all tenant/subscription/billing behavior. No archive format changed, so existing `.sbx`/`.zip` archives remain compatible.

| # | Defect / risk | Root cause | Fix | Regression test |
|---|---|---|---|---|
| 1 | `backup:create --label` rejected every normal label because the pattern required a control character | Inverted character class `/^[\p{C}]{1,100}$/u` | Pattern corrected to `/^[^\p{C}]{1,100}$/u` in `CreateSystemBackup` | Existing command tests exercise labels; invalid-label path still refuses |
| 2 | Restore schema-compatibility scan could miss migration names split across short ZIP stream reads, producing a false "schema unknown/newer/older" refusal | Scanner assumed full reads and split at an arbitrary offset | 512-byte deferred tail buffer and delimiter-safe scanning in `SystemRestoreService::migrationNames()` | `SystemRestoreDrillTest` (restore, encrypted restore, rollback) |
| 3 | `backup:upload` with an unconfigured off-site disk returned failure without an audit record or state entry | Missing failure wiring | Audit + `recordFailure` added | `SystemBackupOpsTest::test_upload_command_reports_failure_without_claiming_offsite_protection` |
| 4 | `backup:upload` refusing a plaintext archive returned failure without an audit record or state entry | Missing failure wiring | Audit + `recordFailure('offsite_encryption_required')` added | `SystemBackupOpsTest::test_upload_command_records_a_plaintext_refusal_as_a_failure` |
| 5 | **Data loss / security:** `EncryptedContainer::decryptToFile()` deleted a pre-existing destination file when decryption failed (including the "destination exists" refusal), and `process()` did the same in its catch block | Cleanup unlinked `$destination` without tracking whether this run created it | `process()` tracks `$destinationCreated`; cleanup only removes a file the run created, and the outer catch no longer unlinks | `SystemBackupEncryptionTest::test_decryption_never_deletes_or_overwrites_a_preexisting_destination_file` (asserts the operator file survives both the overwrite refusal and a tampered-container failure, and that a newly created partial file is removed) |
| 6 | **Security:** `backup:decrypt --output` could write a plaintext archive inside the public web root or the system backup directory | No output location guard; only overwrite/`--confirm` checks | `DecryptSystemBackup::isExposedPath()` refuses `public_path()` and the configured backup directory (normalized, case-insensitive on Windows) | `SystemBackupOpsTest::test_decrypt_command_refuses_plaintext_output_in_the_public_root_and_backup_directory` |
| 7 | Test helper lost numeric key ids | `array_merge` renumbered integer keys | `[$id => $path] + (array) Config::get(...)` | Encryption/ops suites pass repeated key configurations |
| 8 | Gated restore drills errored instead of skipping when `SYSTEM_RESTORE_DRILL` was unset: `tearDown()` read an uninitialized typed property | `$temporaryDirectory` is only assigned after the skip guard; PHP 8 throws on uninitialized typed property access | `tearDown()` deletes the directory only when `isset($this->temporaryDirectory) && is_dir(...)` | `SystemRestoreDrillTest` now reports **5 skipped, 0 errors** without the gate and **5 passed / 46 assertions** with it |

Audit conclusions that did **not** require code changes:

- AES-256-GCM record nonces are 4 zero bytes + 8-byte big-endian index under a unique per-archive DEK; record AAD binds the full header, index and type; the authenticated end record plus trailing-data check detects truncation and append attacks.
- The archive validator hashes every entry against the manifest before extraction, so an entry whose real size differs from the manifest fails validation before any restore write. This bounds restore media writes by declared sizes; a malicious plaintext zip can still consume CPU during validation (integrity only, not authenticity).
- Creation cleanup (`SystemBackupService`) and media staging cleanup (`SystemRestoreService`) only remove paths created by the run; archive names use random suffixes plus `ZipArchive::EXCL`/`fopen('xb')`, so pre-existing files cannot be overwritten.
- Restore creates and drops only databases it created, refuses production, application-configured databases, non-`restore_`/`drill_` names and mismatched confirmations, and rejects an existing media target.

---

## Implemented

### Encryption container (SBX1)

- `app/Services/Backup/Encryption/EncryptedContainer.php`
  - 80-byte authenticated header (magic `SBX1`, version, cipher suite, flags, chunk size, file salt, payload bytes, payload SHA-256, created-at).
  - 64-byte key envelope: random per-archive DEK wrapped with the configured KEK using AES-256-GCM and associated data binding header + key id + wrap suite.
  - 1 MiB AES-256-GCM payload records with a nonce built from a 4-byte zero prefix + 8-byte big-endian record index; AAD binds header raw bytes + record index + record type.
  - Authenticated end record; trailing data, truncation, header edits, envelope edits and wrong keys all fail closed.
  - `verify()` streams and hashes without writing; `decryptToFile()` streams to the destination while authenticating every record and, on any failure, removes only the destination file it created (a pre-existing file is never deleted or overwritten); `inspect()` never decrypts the payload; `rewrap()` rewrites only the envelope and restores the original envelope if verification fails.
- `app/Services/Backup/Encryption/BackupKeyRing.php`
  - Keys are read only from configured files: `BACKUP_ENCRYPTION_KEY_FILE` + `BACKUP_ENCRYPTION_KEY_ID`, or the `BACKUP_ENCRYPTION_KEYS` JSON map of id → path for retired keys.
  - Accepts 32 raw bytes, 64 hex characters or base64 for 32 bytes; numeric ids 1–65535.
  - Refuses key files inside the backup directory or the public web root; no key generation, printing, logging or committing.
- `app/Services/Backup/SystemBackupArchive.php`
  - Detects format by magic: `PK…` = plain zip, `SBX1` = encrypted. Plain zips remain readable; when encryption is enabled the service never writes or keeps a plaintext artifact.

### Creation pipeline

- `app/Services/Backup/SystemBackupService.php`
  - Builds the zip in `backups/.work` when encryption is enabled, validates it against the manifest, encrypts to `.sbx`, verifies the container, deletes the plaintext, and sets `0600`.
  - Space preflight requires twice the payload for encrypted runs and checks temporary space before dumping; failure removes only artifacts created by the run.
  - Result and audit context include `encrypted`, `container_version`, `key_id`, `payload_sha256`, `payload_bytes`.
  - No silent plaintext fallback: encryption enabled without a usable key or key id fails before writing.

### Operator commands

| Command | Purpose |
|---------|---------|
| `backup:create [--label=] [--upload]` | Create a plain `.zip` or encrypted `.sbx`; optional off-site upload |
| `backup:inspect <archive> [--verify]` | Inspect header/manifest without extracting; optional full stream verification |
| `backup:decrypt <archive> --output= --confirm` | Write a plaintext zip for inspection/restore; refuses to overwrite, and refuses output inside the public web root or the backup directory |
| `backup:rewrap <archive> --to-key=` | Move the DEK envelope to another configured key without rewriting the payload |
| `backup:restore <archive> --target-database= --confirm-database= [--media-target= --confirm-media-target=] [--dry-run]` | Restore into a new disposable database and absent media target |
| `backup:upload <archive> [--label=]` | Copy to the configured off-site disk and verify |
| `backup:health [--json] [--verify]` | Freshness, key availability, state, configuration warnings; exit code reflects health |
| `backup:prune [--dry-run] [--confirm] [--keep-last=] [--max-age-days=]` | Retention preview (default) or deletion under explicit policy |

### Restore safety

- `app/Services/Backup/RestoreTargetGuard.php`
  - Production environment refused.
  - Target database must match `^[a-z0-9_]{1,64}$`, start with `restore_` or `drill_`, and not be any configured application database or a reserved system schema.
  - Database confirmation must repeat the target exactly.
  - Media target must be absolute, absent, have an existing parent, and be outside `base_path()`, `public_path()`, local disk roots and the backup directory; confirmation must repeat it exactly.
- `app/Services/Backup/DatabaseRestorer.php`
  - Connects to the server without selecting a database to check/create/drop the target.
  - Imports via a configured `mysql` client when available (credentials through a temporary defaults file), otherwise through PDO with multi-statements and a configurable inline size limit.
  - Verifies restored table count against the manifest and requires migration rows.
- `app/Services/Backup/SystemRestoreService.php`
  - Order: environment → name → confirmation → application-database check → media target → archive format/key/space → validation → schema compatibility → target absence → create → import → verify → media staging → atomic `rename` commit.
  - Refuses unsupported drivers, non-SQL snapshots, archives with no migration records, newer or older schemas.
  - Rollback drops only a database created by the run, deletes staging directories, and removes a committed media target if a later step fails; failures carry `phase`, `cleanup` and a typed reason.
  - Migration compatibility scans the SQL stream in bounded chunks and defers scanning the tail so names split across stream reads are never truncated (fixed during this sprint).
- `app/Console/Commands/RestoreSystemBackup.php` — CLI only; requires both confirmations; no HTTP/UI route exists.

### Off-site copy

- `app/Services/Backup/Offsite/RemoteBackupStore.php`
  - Works with any configured Laravel filesystem disk; no credentials in code, logs or archives.
  - Requires an encrypted archive by default.
  - Refuses to overwrite a remote object; streams the upload; reads back remote size, then streams the object again to compare SHA-256 unless `verify_readback` is disabled; deletes the partial object on failure.
  - Returns `verified_by` so callers cannot report verification that did not happen.
- Local backup creation remains independent: when off-site is not configured, `backup:create` succeeds and states that off-site is not configured; `--upload` fails but keeps the local archive and explicitly says off-site protection is unconfirmed.

### Scheduling, health and retention

- `routes/console.php`
  - When `backups.schedule.enabled` is true: `backup:create` daily at the configured time (`--upload` added only when off-site is enabled with a disk) with `withoutOverlapping`, plus `backup:health` daily.
  - Pruning is scheduled only when `retention.enabled` and `retention.scheduled` are both true.
  - Invalid times fall back to `02:30` / `07:00`.
- `app/Services/Backup/BackupStateStore.php` — atomic `.state/backup-state.json` with last attempt/success/failure and an upload ledger keyed by archive basename.
- `app/Services/Backup/BackupHealthService.php` — reports no archive, unreadable archive, stale archive, verification failure, recent failure, key unavailable, active key unavailable; warns about unverified/absent off-site, disabled encryption and disabled retention. Does not claim health when off-site was requested but never verified.
- `app/Services/Backup/BackupRetention.php` — strictly matches `system-backup-YYYYMMDD-HHMMSS-xxxxxxxx.(zip|sbx)` in the backup directory, keeps the newest `keep_last`, deletes only files older than `max_age_days`, optionally requires a recorded verified off-site copy, and refuses deletion when retention is not enabled.
- `app/Services/Backup/SystemBackupLock.php` — named per-operation `flock` locks inside `backups/.locks`: `system-backup.lock` for creation and `system-backup-restore.lock` for restore. They serialize two runs of the same operation on one host but **do not exclude each other**; a creation and a restore can run concurrently (they use separate work/temp directories and both only read the existing archive). Stale locks are released by process exit. The lock is host-local and does not coordinate multiple hosts.

### Audit

- `app/Services/Backup/SystemBackupAuditLogger.php`
  - Dedicated `backups` log channel; whitelisted context fields only.
  - Redacts password/secret/token shapes, DSN credentials and MySQL `user@host` denial messages; truncates messages.
  - Records create, failure, restore start/completion/failure, upload failure/success, decrypt, rewrap, health and prune events.

---

## Verified (executed in this sprint)

Focused suites, final code state, first run on 2026-10-24 and re-confirmed on 2026-10-25 against a freshly migrated `ecommerc_test` (MySQL, XAMPP MariaDB 10.4.28), with `php artisan test`:

| Command / suite | Result |
|---|---|
| `php artisan test tests/Feature/SystemBackupEncryptionTest.php` | **10 passed / 74 assertions** |
| `php artisan test tests/Feature/SystemBackupOpsTest.php` | **20 passed / 116 assertions** |
| `php artisan test tests/Feature/SystemRestoreGuardTest.php` | **7 passed / 44 assertions** |
| `php artisan test tests/Feature/SystemBackupTest.php tests/Feature/SystemBackupCommandTest.php tests/Feature/SystemBackupHardeningTest.php` (combined run with the files above) | remainder of the combined total: **20 passed / 172 assertions** |
| Combined non-drill run: `php artisan test tests/Feature/SystemBackupTest.php tests/Feature/SystemBackupCommandTest.php tests/Feature/SystemBackupHardeningTest.php tests/Feature/SystemBackupEncryptionTest.php tests/Feature/SystemBackupOpsTest.php tests/Feature/SystemRestoreGuardTest.php` | **57 passed, 406 assertions** |
| `SYSTEM_RESTORE_DRILL=1 php artisan test tests/Feature/SystemRestoreDrillTest.php` | **5 passed, 46 assertions** (dry run, real restore, encrypted restore, existing-target refusal, rollback) |
| Focused total | **62 passed, 452 assertions** |

Covered failure/security paths: tampered/truncated/trailing container payloads, header edits, wrong and missing keys, unsafe key locations, encryption-enabled-without-key, plaintext upload refusal **with audit/state recording**, remote size/checksum mismatch, existing remote object, missing off-site configuration, retention without opt-in, non-backup files, required off-site copies, stale archives, recent failures, unavailable keys, decrypt overwrite/confirmation **and public-root/backup-directory output refusal**, pre-existing decrypt destination preservation, rewrap to unknown key, production refusal, invalid/prefixed/configured restore targets, confirmation mismatch, media target inside the app/public/backup roots, existing target database, and rollback after verification failure.

### Operational smoke evidence (development host, non-destructive)

Executed against the development database with a disposable key in the system temp directory (key material never printed):

- `php artisan backup:create --label=final-audit-smoke` with encryption enabled and a temp key: exit 0, `backups/system-backup-20261024-093035-w0gpasw6.sbx`, 14,173,948 bytes, container v1, key id 1.
- `php artisan backup:inspect <archive> --verify`: exit 0, key available, envelope verified, payload 14,173,504 bytes, SHA-256 `b4d10855…`, "Payload verified: 14 records, SHA-256 matched."
- `php artisan backup:health --json`: `healthy=true`, `archive_count=1`.
- `php artisan backup:decrypt <archive> --output=<temp>\smoke.zip --confirm`: exit 0, plaintext SHA-256 equal to the container payload SHA-256. The temp output and key were deleted afterwards.
- Cleanup: the smoke `.sbx`, `.work`, `.state` and `.locks` artifacts and the temp key/decrypt files were removed; `storage/app/private/backups` is empty and `backup:health` returned `healthy=false` with `no_archive` (exit 1) again. No `.zip/.sbx/.key` file exists under `public/`.
- `php artisan backup:prune --dry-run`: exit 0, 0 candidates, nothing deleted. `php artisan backup:prune --confirm`: exit 1, "retention is not enabled".
- `php artisan list --raw`: all eight `backup:*` commands registered (`create`, `inspect`, `decrypt`, `rewrap`, `restore`, `upload`, `health`, `prune`).
- `php artisan schedule:list`: no backup entries by default; with `BACKUP_SCHEDULE_ENABLED=true`, `BACKUP_RETENTION_ENABLED=true`, `BACKUP_RETENTION_SCHEDULED=true` the list shows `backup:create` (02:30), `backup:health` (07:00) and `backup:prune --confirm` (04:30) only.
- `php -l` on all new/modified PHP files: no syntax errors.
- `git diff --check`: exit 0; only the pre-existing CRLF warning for `tests/Feature/SubscriptionRenewalCancellationTest.php`.
- No `drill_*`/`restore_*` databases remained after the drill (checked via `information_schema`).
- No temporary `audit-smoke-*`, `sbx-keys-*`, `backup-ops-*` or `system-backup-db-*` artifacts remained in the system temp directory.

### Full project suite

Command (project root, default `phpunit.xml`): `php artisan test`

- Execution: started 2026-10-24 15:57, finished 20:44 (local), duration 17,441.40 s (≈4 h 50 min), exit status **2**.
- Environment: `APP_ENV=testing`, `DB_CONNECTION=mysql`, `DB_DATABASE=ecommerc_test`. There is no `.env.testing`; the `phpunit.xml` `<env>` values are authoritative. The effective connection was confirmed with a throwaway probe test asserting `testing` + `mysql` + `ecommerc_test`, and the development database `ecommerc_db2` kept 97 tables / 30 tenants before and after every run in this audit. No development, staging or production data was touched.
- Result: **1356 passed, 498 failed, 2 skipped, 6044 assertions** → **FAIL**.
- Baseline quality: this run started against a **dirty** `ecommerc_test` left behind by an earlier terminated run (partially migrated, seeded, residual rows). It is recorded as a failure, not dismissed, but it is **not a valid regression baseline**.

Failure classification (47 failing classes, 498 tests):

| Category | Evidence | Classification |
|---|---|---|
| Schema DDL collisions | 344 `Table ... already exists` occurrences. `WebhookArchitectureTest` (27/27), `FeatureGateTest` (19/19), `CatalogV2FoundationTest`, `TransactionFoundationTest` and `ManualPaymentFoundationTest` call `Schema::create(...)` unconditionally after `parent::setUp()` | Pre-existing test-isolation defect |
| Unique-key collisions | 134 `Duplicate entry` occurrences such as `plans_slug_unique` = `free`, tenant slugs `store-a`/`store-b`, permission names; caused by seeding migrations plus residual rows from the aborted run | Test-data collision (environmental) |
| Missing tenant/membership context | 13 `Your account is not associated with any store` redirects, mostly `Tenant\*IsolationTest` and `Storefront*` classes | Test-data collision (environmental) |
| Schema drift in tests | `Field 'category_id' doesn't have a default value` for product inserts; `Failed asserting that null is false` in `BrandV2Test` | Pre-existing test drift (baseline uncertain — see clean-database subset) |
| Behavioural drift | `PromotionReportTest` expects 200 but receives 302; `Auth\*` login/verification/reset expectations; `BrandV2Test` banner accessor expects `null` but receives the placeholder data URI | Pre-existing; no evidence these passed on this branch |
| Backup drill teardown | `SystemRestoreDrillTest` ×5: `Typed property ...::$temporaryDirectory must not be accessed before initialization` at `tests/Feature/SystemRestoreDrillTest.php:56` | **Confirmed defect in this sprint's test code; fixed** |

No `SystemBackup*`, `MerchantExport*` or other backup production-code test failed in the full run. The only backup-related failures were the five drill teardown errors, which occur when the drill gate is unset.

### Fix applied after the full run

`SystemRestoreDrillTest::tearDown()` now removes its temporary directory only when the property is initialized and the directory exists, so gated-off runs skip instead of erroring in teardown.

| Command | Result |
|---|---|
| `php artisan test tests/Feature/SystemRestoreDrillTest.php` (gate unset) | **5 skipped, 0 errors, 0 assertions** |
| `SYSTEM_RESTORE_DRILL=1 php artisan test tests/Feature/SystemRestoreDrillTest.php` | **5 passed, 46 assertions** |

### Clean-database subset (2026-10-25)

`ecommerc_test` was rebuilt with `APP_ENV=testing DB_DATABASE=ecommerc_test php artisan migrate:fresh` (target printed from `config('database.connections.mysql.database')` before executing; `ecommerc_db2` untouched). Representative failing classes were re-run to separate environmental collisions from genuine defects:

| Class | Full-run failures | Clean-database result | Conclusion |
|---|---|---|---|
| `BrandV2Test` | 27 | 23 passed / 4 failed | Mostly environmental. Remaining 4: `category_id` strict-mode inserts (×2), banner accessor expectation, "existing records" assertion — pre-existing test drift |
| `WebhookArchitectureTest` | 27 | 27 failed (`Table 'tenants' already exists`) | Pre-existing test defect: `createMinimalSchema()` unconditionally creates migrated tables |
| `FeatureGateTest` | 19 | 19 failed (`Duplicate entry 'free' for key plans_slug_unique`) | Pre-existing test defect: migrations already seed the plans |
| `StorefrontCatalogTest` | 50 | 50 failed (`Field 'category_id' doesn't have a default value`) | Pre-existing test drift: product inserts omit a column added by a later migration |
| `PromotionReportTest` | 10 | 10 failed (302 instead of 200) | Pre-existing behavioural drift |
| `Feature\Auth\*` (4 classes) | 8 | 18 passed / 8 failed | Pre-existing, unrelated to backup |
| `SystemRestoreDrillTest` | 5 | 5 passed gated / 5 skipped ungated | Fixed in this audit |

On a seeded database the same classes fail earlier and differently (`PermissionAlreadyExists`, duplicate tenant slugs), which confirms these are baseline/test-isolation problems rather than a single database-state accident. Because only a test file changed after the full run, a second ~5 h full run was not executed; it would still fail on the pre-existing defects above. The full-suite verdict therefore remains **FAIL (environmental + pre-existing)**, and the backup verdict rests on the focused suites re-run on the final code and the clean database.

### Focused suites re-run on final code and clean database (2026-10-25)

| Command | Result |
|---|---|
| `php artisan test tests/Feature/SystemBackupEncryptionTest.php tests/Feature/SystemBackupOpsTest.php tests/Feature/SystemRestoreGuardTest.php` | **37 passed / 234 assertions** |
| `php artisan test tests/Feature/SystemBackupTest.php tests/Feature/SystemBackupCommandTest.php tests/Feature/SystemBackupHardeningTest.php tests/Feature/SystemBackupRecoveryDrillTest.php tests/Feature/MerchantExportRestoreDrillTest.php` | **20 passed / 172 assertions, 2 skipped** (gated drills) |
| `SYSTEM_RESTORE_DRILL=1 php artisan test tests/Feature/SystemRestoreDrillTest.php` | **5 passed / 46 assertions** |
| `SYSTEM_RESTORE_DRILL=1 php artisan test tests/Feature/SystemBackupRecoveryDrillTest.php` | **1 passed / 296 assertions** |
| `RESTORE_DRILL=1 php artisan test tests/Feature/MerchantExportRestoreDrillTest.php` | **1 passed / 226 assertions** |
| Focused total, non-drill (matches the 2026-10-24 figure) | **57 passed / 406 assertions** |
| Focused total including every gated drill | **64 passed / 974 assertions** |

Post-run cleanup: no `drill_*`/`restore_*` database remained (checked via `information_schema`) and no `system-restore-drill-*` directory remained in the system temp directory.



---

## Not verified / Blocked / Not implemented

- **Off-site provider — Blocked by External Configuration.** No S3 driver package and no provider account or credentials are available in this environment; `league/flysystem-aws-s3-v3` is not in `composer.json` and was not installed. The adapter is verified only against local and fake disks. Operator prerequisites:
  1. Provision a dedicated bucket/account with versioning and object lock; use write-only credentials on the application host and keep delete rights on a separate break-glass identity.
  2. Install a compatible driver (for example `composer require league/flysystem-aws-s3-v3`) and define a disk in `config/filesystems.php`.
  3. Set `BACKUP_OFFSITE_ENABLED=true`, `BACKUP_OFFSITE_DISK=<disk>`, optionally `BACKUP_OFFSITE_PATH`.
  4. Run one uniquely named `backup:create --upload`; confirm the printed `(size_and_sha256)` and `backup:health --json` → `last_offsite.verified_by=size_and_sha256`, then delete only that test object.
  Until that observed run exists, do not claim off-site protection.
- **Production-scale restore — Blocked by External Configuration.** Drills used synthetic archives and small row sets against disposable `drill_*` databases on the local MariaDB. No isolated production-sized host or authorized dataset is available, so restore duration, peak disk usage, table counts and RTO/RPO remain unmeasured. Required procedure is documented under "RPO / RTO and scale validation"; record wall-clock timings and counts, then repeat after schema changes.
- **Key custody and escrow — Requires Operator Decision.** The code supports a key file plus a retired-key map, but custody, two-person access, offline escrow, KMS/HSM choice and the recovery owner are policy (D-01–D-04, D-20). Losing every copy of a KEK makes every archive wrapped by it permanently unrecoverable. Key-file permissions are not enforced by the application (Windows and POSIX `chmod` differ); operators must set `0600`/`0700` themselves.
- **Alert delivery — Not Implemented.** Health and failure outcomes are written to the dedicated `backups` audit channel and reflected in exit codes. Paging/email/webhook delivery and a dead-man's-switch are not implemented and were not verified.
- **Multi-host coordination — Not Implemented.** The `flock` lock and state file are host-local. Concurrent creation from two hosts writing to a shared backup target is not prevented; run the scheduler and every backup command on exactly one designated host. Off-site upload is the only durable cross-host copy.
- **Encrypted key rotation reminders and escrow drills — Not Implemented.** `backup:rewrap` exists and is tested, but no scheduled reminder or canary/escrow drill was executed.
- **Plaintext zip authenticity — residual risk, documented.** A plain zip is integrity-checked against its own manifest but is not authenticated: an attacker who can rewrite both the archive and the manifest can produce a self-consistent archive. Restoring an untrusted plain zip is therefore unsafe; encrypted archives are the only authenticated path.
- **Decrypted plaintext on disk — by design.** `backup:decrypt` and the restore temp file write plaintext zips (mode `0600`); they are removed after restore, but an interrupted operator decrypt leaves plaintext that must be deleted manually. Output inside the public root and backup directory is now refused.
- **Windows vs Linux.** Development verification ran on Windows/PHP 8.2.4 with XAMPP MariaDB 10.4.28. POSIX permission bits (`0600`/`0700`), atomic `rename` semantics across filesystems, cron execution and `mysql` client availability must be re-confirmed on the deployment OS.

---

## Operator configuration

Encryption (opt-in):

```dotenv
BACKUP_ENCRYPTION_ENABLED=true
BACKUP_ENCRYPTION_KEY_ID=1
BACKUP_ENCRYPTION_KEY_FILE=/secure/outside/the/app/backup-key-1
# Optional retired keys for verification/rewrap: {"2":"/secure/backup-key-2"}
BACKUP_ENCRYPTION_KEYS=
BACKUP_ENCRYPTION_MAX_PAYLOAD_BYTES=51539607552
```

Off-site (opt-in, encrypted-only by default):

```dotenv
BACKUP_OFFSITE_ENABLED=true
BACKUP_OFFSITE_DISK=s3
BACKUP_OFFSITE_PATH=system-backups
BACKUP_OFFSITE_REQUIRE_ENCRYPTION=true
BACKUP_OFFSITE_VERIFY_READBACK=true
```

Scheduling and retention (both opt-in):

```dotenv
BACKUP_SCHEDULE_ENABLED=true
BACKUP_SCHEDULE_TIME=02:30
BACKUP_SCHEDULE_HEALTH_TIME=07:00
BACKUP_SCHEDULE_MAX_AGE_HOURS=26

BACKUP_RETENTION_ENABLED=true
BACKUP_RETENTION_KEEP_LAST=7
BACKUP_RETENTION_MAX_AGE_DAYS=30
BACKUP_RETENTION_SCHEDULED=false
BACKUP_RETENTION_REQUIRE_OFFSITE=true
```

Other operational settings: `BACKUP_DB_CONNECTION`, `BACKUP_DUMP_BINARY`, `BACKUP_MYSQL_BINARY`, `BACKUP_DISK_SAFETY_MARGIN`, `BACKUP_MIN_FREE_BYTES`, `BACKUP_TEMP_MIN_FREE_BYTES`, `BACKUP_RESTORE_INLINE_SQL_BYTES`, `BACKUP_LOG_CHANNEL`.

Scheduling requires the existing cron entry plus a queue worker as described in `AGENTS.md`; backup commands themselves run synchronously.

---

## Manual acceptance checklist

Status of the checklist below, as of the final audit: **PASS** = executed during this audit with supporting output; **PARTIAL** = covered by automated tests but not executed as a manual end-to-end run; **PENDING** = requires operator/external infrastructure; **N/A** = not implementable here.

| # | Item | Status | Evidence |
|---|------|--------|----------|
| 1 | Plain creation | PARTIAL | Covered by `SystemBackupTest`/`SystemBackupCommandTest`; the audit smoke run used encryption |
| 2 | Encrypted creation, `.work` empty, inspect verified | PASS | Smoke: `.sbx` 14,173,948 bytes, `inspect --verify` = 14 records, SHA-256 matched; `.work`/`.state` cleaned |
| 3 | Missing key ⇒ refusal, nothing written | PASS | `SystemBackupEncryptionTest` (key unavailable / not configured, empty `backups` and `.work`) |
| 4 | `inspect --verify` | PASS | Smoke run on the created `.sbx` |
| 5 | Decrypt confirm/overwrite rules and plaintext location | PASS | `SystemBackupOpsTest` incl. new public-root/backup-dir refusal test; smoke decrypt SHA matched |
| 6 | Rewrap to another key | PARTIAL | `SystemBackupEncryptionTest` + `SystemBackupOpsTest`; not executed against the operator key ring |
| 7 | Off-site disabled messaging | PASS | `SystemBackupOpsTest` (`not requested`, `--upload` keeps local archive) |
| 8 | Real remote upload with read-back | PENDING | Blocked by External Configuration (no provider/driver/credentials) |
| 9 | Remote failure keeps local archive, no success recorded | PARTIAL | Fake-disk size/checksum failure tests; real outage not simulated |
| 10 | Restore dry-run | PASS | `SystemRestoreDrillTest` (real command against disposable DB), no database created |
| 11 | Real restore into new database | PASS | `SystemRestoreDrillTest`: created, imported, verified, media staged |
| 12 | Existing/application target refused | PASS | `SystemRestoreGuardTest`, `SystemRestoreDrillTest` (existing DB left untouched) |
| 13 | Media target guards | PASS | Guard tests; drill staged media into an absent temp target outside the app tree |
| 14 | Injected failure rolls back | PASS | Drill rollback test drops the created database and staging |
| 15 | Health exit codes / stale archive | PASS | Real `backup:health` (exit 1 `no_archive`; healthy after smoke) plus stale/missing-key tests |
| 16 | Prune dry-run and opt-in refusal | PASS | Real `backup:prune --dry-run` (0 candidates, nothing deleted) and `--confirm` refused; automated tests |
| 17 | Retention requires verified off-site copy | PARTIAL | Covered by `SystemBackupOpsTest`; no real off-site copy exists |
| 18 | Concurrent creation refused | PARTIAL | Covered by hardening tests; not reproduced with two live OS processes |
| 19 | Permissions and web-root exclusion | PARTIAL | No archive/key in `public/`; POSIX `0600`/`0700` not measurable on Windows |
| 20 | Production-scale restore + RPO/RTO | PENDING | Blocked by External Configuration |
| 21 | Alert delivery | PENDING | Not Implemented |
| 22 | Multi-host coordination | PENDING | Not Implemented (single designated host required) |
| 23 | Full project test suite (regression baseline) | FAIL (classified) | 1356 passed / 498 failed / 2 skipped / 6044 assertions. Failures are test-isolation and test-drift defects (47 classes), reproduced on a clean migrated database; the one backup test defect (drill teardown) was fixed and re-verified. Not a valid regression baseline until those classes are repaired |

The numbered procedures below remain the operator runbook for the pending items.

Creation and encryption

1. `php artisan backup:create --label=acceptance` → exit 0, `.zip` when encryption is disabled.
2. Enable encryption with a key file outside the repo → `backup:create` produces `.sbx`; `backups/.work` is empty; `backup:inspect <archive>` shows key id and envelope verified.
3. Remove `BACKUP_ENCRYPTION_KEY_FILE` / `BACKUP_ENCRYPTION_KEY_ID` → `backup:create` fails with `encryption_key_unavailable` / `encryption_not_configured` and writes nothing.

Inspection, decryption, rotation

4. `backup:inspect <sbx> --verify` → "Payload verified".
5. `backup:decrypt <sbx> --output=<new path>` without `--confirm` → refused; with `--confirm` → plaintext zip with matching SHA-256; existing output refused.
6. Configure key id 2, run `backup:rewrap <sbx> --to-key=2` → envelope moves, `backup:inspect` shows key id 2, `--verify` still passes; remove key 1 and confirm decryption still works with key 2 only.

Off-site

7. With off-site disabled, `backup:create` succeeds and prints "Off-site: not requested"; `backup:create --upload` reports failure and keeps the local archive.
8. With a real remote disk configured, `backup:create --upload` prints `uploaded ... (size_and_sha256)` and the object exists remotely with the same size/SHA-256. `backup:health --json` shows `last_offsite.verified_by = size_and_sha256`.
9. Stop/deny the remote service → upload fails, the local archive remains, and no success is recorded.

Restore

10. `backup:restore <archive> --target-database=restore_accept --confirm-database=restore_accept --dry-run` → validates without creating anything.
11. Repeat without `--dry-run` (non-production environment) → new database created; `backup:restore` prints table and migration counts; app configuration is untouched.
12. Point `--target-database` at an existing or application database → refused; the database is unchanged.
13. Provide a media target inside the app or an existing directory → refused. Provide a valid external absent path → media restored there and checksums match.
14. Inject a failure after import (for example a manifest table-count mismatch in a test archive) → the database created by the run is dropped and staging is removed.

Health and retention

15. `backup:health` → exit 0 with a recent archive; `--json` parses. Make the newest archive older than `max_age_hours` → exit 1 with `stale_archive`.
16. `backup:prune` → lists candidates, deletes nothing. `backup:prune --confirm` without retention enabled → refused. With retention enabled, only matching old archives are deleted; notes and exports remain.
17. With `BACKUP_RETENTION_REQUIRE_OFFSITE=true`, archives without a verified upload are skipped.

Concurrency and permissions

18. Start a second `backup:create` while one is running → refused with `backup_in_progress`.
19. On Linux, confirm `0600` archives and `0700` key/state directories; confirm the backup directory is outside the public web root.

---

## Isolated restore procedure (production)

Restore is deliberately not a one-command production rollback. Use this sequence:

1. Provision an isolated host with the same PHP/MySQL versions and a copy of the code at the backup's schema revision.
2. Place the archive and the key file (or a key from escrow) on that host; keep the key outside the app tree and back it up separately.
3. Run `backup:inspect <archive>` and `backup:inspect --verify`; confirm key id and payload SHA-256.
4. Run `backup:restore ... --dry-run` against a new `restore_*` database on the isolated server.
5. Run the restore for real; verify table and migration counts.
6. Point an isolated application instance at the restored database and a restored media copy; run smoke checks (login, storefront, product listing, order read).
7. Only after sign-off, decide how to move data to production: a controlled maintenance window and a reviewed migration/import plan. This code does not switch the live application to a restored database.
8. Destroy the restored copies and plaintext decrypt outputs when finished.

## Single-host vs multi-server

- Single host: the local lock, scheduler and state file are sufficient; the queue worker and scheduler must both be running.
- Multiple hosts: run the scheduler and backup commands on exactly one designated host. The lock does not coordinate across hosts, and two hosts writing to the same target can race. Off-site upload is the only cross-host durable copy; ensure retention requires verified off-site copies before deleting local archives.

## RPO / RTO and scale validation

- RPO is bounded by the backup schedule and by whether the off-site copy succeeded. A daily 02:30 run with a verified upload supports roughly a 24-hour RPO; tighter targets require more frequent runs or database replication, which this feature does not provide.
- RTO depends on archive size, link speed and restore hardware; no production measurement exists yet. Measure it during the production-scale rehearsal and record the result.
- Production-scale verification steps: run one create + inspect `--verify` + off-site upload on a copy of production data; restore that archive on an isolated host; record wall-clock durations, peak disk usage (creation needs about 2× payload when encrypted), and the final table/migration counts. Repeat after major schema changes.

---

## Open security and data-loss risks

1. **Plaintext zip authenticity** — a plain archive is integrity-checked but not authenticated. Only encrypted `.sbx` archives are protected against a rewritten archive+manifest. Do not restore plaintext zips from untrusted storage.
2. **Key loss** — losing all copies of a KEK permanently destroys every archive wrapped by it. No escrow exists yet; this is the largest operational risk of enabling encryption.
3. **Key file permissions** — not enforced by the application. A key readable by other users or stored inside the app tree (outside the backup/public roots, which are refused) weakens confidentiality.
4. **Host-local locking** — two hosts targeting the same backup location can race; state and dedup rely on the same host.
5. **Off-site is unverified** — no remote copy exists, so there is no disaster-recovery copy and retention with `require_offsite` would skip everything.
6. **Interrupted decrypt leaves plaintext** — `backup:decrypt` writes a plaintext zip (mode `0600`) by design; if the operator does not delete it, it persists outside the protected backup directory.
7. **No automated alerting** — a failed or missing backup is only visible in the audit log and the health exit code.

## Remaining operator actions and recommended execution order

1. Decide and document key custody/escrow (D-01–D-04); create the KEK offline with two-person control and record its location.
2. Place the key outside the repository, the backup directory and the web root with `0600`; set `BACKUP_ENCRYPTION_ENABLED=true`, `BACKUP_ENCRYPTION_KEY_ID`, `BACKUP_ENCRYPTION_KEY_FILE`; run `backup:create`, then `backup:inspect <archive> --verify`.
3. Provision the off-site bucket (versioning, object lock, write-only application credentials), install the driver, configure the disk, then run one uniquely named `backup:create --upload` and verify `last_offsite.verified_by=size_and_sha256` before deleting only that test object.
4. Run the production-scale rehearsal on an isolated host with representative data: record archive size, create/verify/restore durations, peak disk usage, table and migration counts, and application smoke checks; sign off RPO/RTO.
5. Execute the restore drill in the deployment OS (`SYSTEM_RESTORE_DRILL=1 php artisan test tests/Feature/SystemRestoreDrillTest.php`) against a disposable `drill_*` database, then confirm no disposable databases remain.
6. Approve the retention matrix (D-15), keep `BACKUP_RETENTION_SCHEDULED=false` until the first verified off-site copy exists, and start with `backup:prune --dry-run`.
7. Enable scheduling only after steps 1–6, on exactly one designated backup host, with the cron entry and a running queue worker; confirm `schedule:list` shows the three backup entries.
8. Add alert delivery (pager/email/webhook, dead-man's-switch on 26-hour freshness) or explicitly accept log/exit-code-only monitoring.
9. Re-run `php artisan test` and this report's smoke checks after any backup code change.

## Remaining issues / planned work

- Monitoring/alert-delivery integration on top of the audit channel.
- Multi-host coordination for the backup lock (or an enforced single-host deployment guarantee).
- Key rotation reminders, canary/escrow drill workflow.
- Production-scale timed drill and signed RPO/RTO values.
- Off-site provider choice, driver installation and first verified upload.
- Optional enforcement of key-file permissions on POSIX hosts.

## Completion summary

Encryption, key handling, inspection/rotation, guarded CLI restore, off-site verification, opt-in scheduling, health reporting, retention, locking, disk-space safeguards and failure cleanup are implemented and covered by focused tests (62 passed / 452 assertions on the final code; 64 passed / 974 assertions including every gated drill, on a freshly migrated `ecommerc_test`), including failure and security paths. Eight confirmed defects were fixed, among them a data-loss defect that could delete a pre-existing destination file during failed decryption and a plaintext-output path that could write inside the web root.

The full project suite was executed once and returned **FAIL**: 1356 passed, 498 failed, 2 skipped, 6044 assertions (exit 2, ≈4 h 50 min, `ecommerc_test`). All failures are classified above; 47 classes fail from test-isolation and test-drift defects that reproduce on a clean migrated database, plus 5 backup drill teardown errors that are now fixed. The suite is **not** a green regression baseline, and the backup work is not implicated in the remaining failures.

Off-site provider integration, production-scale restore timing, monitoring delivery, key custody/escrow policy and multi-host coordination are **not delivered or not verified**. They are recorded above with the exact operator prerequisites and execution order.

**This report is not a production-readiness certification.** Acceptance is limited to: the isolated pre-production rehearsal (step 4 of the operator actions) plus the off-site and alerting decisions. Until those have recorded evidence, the system must be treated as ready for isolated pre-production acceptance only — not for production reliance.
