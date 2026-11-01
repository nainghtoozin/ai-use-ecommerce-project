# Sprint 4B-5G - Production Backup Operations Hardening

**Date:** 2026-10-24
**Scope:** Add a bounded operator CLI entry point for the existing 4B-5E system backup, with a credential-safe audit record, and document deployment configuration, access restrictions, and a manual verification procedure.
**Out of scope:** Restore execution, HTTP surface, UI, scheduling, retention/purge/cleanup, encryption implementation, off-host replication, and changes to tenant, subscription, billing, identity, or merchant export code.

## 1. What Was Implemented

| Item | Status | Location |
|---|---|---|
| Operator CLI command to create a backup | Implemented, Verified | `app/Console/Commands/CreateSystemBackup.php` |
| Dedicated audit logger with whitelisted fields and redaction | Implemented, Verified | `app/Services/Backup/SystemBackupAuditLogger.php` |
| Dedicated audit log channel (private single file) | Implemented, Verified (config/payload level) | `config/logging.php`, `config/backups.php` |
| Container binding so the command writes to the audit channel | Implemented | `app/Providers/AppServiceProvider.php` |
| Focused tests (CLI, failure, redaction, label, channel) | Implemented, Verified | `tests/Feature/SystemBackupCommandTest.php`, `tests/Support/Backup/CollectingLogger.php` |
| Encryption at rest | Blocked - documented, not implemented | Section 5 |
| Restore execution, scheduling, retention, alerting | Not implemented (by design) | Sections 3 and 7 |

The command is auto-discovered through the existing `routes/console.php` command registration; no new registration, route, controller, job, or scheduler entry was added.

## 2. Command Contract

```
php artisan backup:create [--label=<1-100 printable characters>]
```

Behavior:

1. Trims `--label`. Empty means "no label".
2. Rejects a label longer than 100 characters or containing control characters before any backup work starts. Exit code `1`, audit entry with `status=failure`, `label=null`.
3. Calls the unchanged `SystemBackupService::create()` (4B-5E).
4. On success: writes an audit entry, prints disk, path, bytes and SHA-256, exit code `0`.
5. On any `Throwable`: writes a redacted failure audit entry, prints `System backup failed: <redacted message>`, exit code `1`. No partial archive is left behind by the service's existing cleanup.
6. Nothing is scheduled, queued, or exposed over HTTP.

Example success output:

```
System backup created.
Disk: local
Path: backups/system-backup-20261024-101500-a1b2c3d4.zip
Bytes: 7340032
SHA-256: 9f2c...e41d
```

## 3. Audit Log Design

- Channel: `logging.channels.backups` - driver `single`, path `storage/logs/system-backups.log`, level `info`, `replace_placeholders = false`.
- Selected through `config('backups.log_channel')`, default `backups`, overridable with `BACKUP_LOG_CHANNEL`.
- Injected through `AppServiceProvider` so the command never writes to the default application log for audit records.

Whitelisted context fields only:

| Event | Fields |
|---|---|
| `system_backup.created` (`info`) | `event, status, label, occurred_at, duration_ms, environment, disk, path, bytes, sha256, database_driver, database_method, database_tables, media_files, media_bytes` |
| `system_backup.failed` (`error`) | `event, status, label, occurred_at, duration_ms, environment, error_class, error_message` |

Never logged: database password, username, host, port, socket, DSN/URL, `.env` values, defaults-file path or contents, full manifest, file contents.

Redaction applied to failure messages before logging and before printing:

- `--password=...`, `--password ...`
- `--defaults-extra-file=...`
- any `*password*`, `passwd`, `pwd` assignment (`password=`, `DB_PASSWORD=`, `pwd:`)
- `*api_key*`, `*api_secret*`, `*secret*`, `*token*` assignments
- any `scheme://user:pass@host` URL prefix

Messages are truncated to 400 characters. Successful-path context is built from typed values returned by the service, so it cannot accidentally include a connection array.

## 4. Files Added or Changed

| File | Status | Change |
|---|---|---|
| `app/Console/Commands/CreateSystemBackup.php` | Added | `backup:create` command, label validation, failure handling, redacted console output |
| `app/Services/Backup/SystemBackupAuditLogger.php` | Added | Whitelisted success/failure audit records and redaction |
| `tests/Feature/SystemBackupCommandTest.php` | Added | 5 focused tests (77 assertions) |
| `tests/Support/Backup/CollectingLogger.php` | Added | Test PSR-3 logger that captures records |
| `config/logging.php` | Changed | Added the `backups` channel |
| `config/backups.php` | Changed | Added `log_channel` (env `BACKUP_LOG_CHANNEL`, default `backups`) |
| `app/Providers/AppServiceProvider.php` | Changed | One container binding for the audit logger |
| `docs/sprint-4b.5g-production-backup-operations.md` | Added | This report |

Untouched: `SystemBackupService`, `DatabaseDumper`, `SystemBackupValidator`, `BackupValidationException`, all backup tests from 4B-5E/4B-5F, merchant export code (`config/exports.php`, `app/Services/Export/*`, `app/Exports/*`), routes, migrations, models, frontend.

## 5. Encryption at Rest - Exact Blocker

Not implemented. The project has no backup encryption key, no key-management convention, and no authenticated-decryption test path, so adding one would require inventing key material and an unreviewed crypto format.

Specific gaps that block a safe implementation:

1. **No key exists.** `config/backups.php` has no key, `.env` has no `BACKUP_ENCRYPTION_KEY`, and there is no key-rotation or escrow process. Inventing or hardcoding a key is prohibited by this phase.
2. **No authenticated streaming format.** The archive can reach multi-gigabyte size, so whole-file `openssl_encrypt` is not viable. A correct design needs chunked AES-256-GCM (or libsodium secretstream) with a versioned header, nonce discipline, and authenticated chunk framing.
3. **`ZipArchive` encryption is not acceptable.** Its password support is legacy PKZIP (ZipCrypto) or at best AES-256 without a modern, documented, tested envelope; it is not authenticated in the way the rest of this project's integrity work requires.
4. **Restore path must decrypt, and restore is not implemented.** Adding encryption now would create an artifact that nothing in the codebase can open, and the 4B-5F drill would no longer represent the artifact.
5. **Operational decisions are missing.** Key holder, storage location separate from the archive, rotation interval, and loss-recovery procedure are operator policy, not code choices.

Recommended prerequisite before encryption work: approve a key source (environment secret or external KMS), a versioned chunked AEAD format, a key-rotation policy, and a restore-side decryption step in the future operator restore command. Until then the mitigation is filesystem-level: private disk, OS permissions, and no web exposure.

## 6. Deployment Configuration Requirements

Required (defaults already correct for this repository):

| Setting | Value | Notes |
|---|---|---|
| `FILESYSTEM_DISK` / `backups.disk` | `local` | Service refuses any disk whose driver is not `local` |
| `backups.path` | `backups` | Resolves under `storage/app/private/backups` |
| `BACKUP_DUMP_BINARY` | optional absolute path | e.g. `/usr/bin/mysqldump`; without it the slower PDO snapshot path is used |
| `BACKUP_DB_CONNECTION` | optional connection name | Defaults to the application connection |
| `BACKUP_LOG_CHANNEL` | optional, default `backups` | Must resolve to a private single-file channel |
| `LOG_LEVEL` | irrelevant for this channel | The `backups` channel is fixed at `info` |

Server/OS requirements:

1. `storage/app/private` must not be a web root and must not be symlinked into `public/`. Confirm the web server denies `storage/` and there is no `public/backups` path.
2. Run the command as the application OS user (same user that owns `storage/`). No queue worker is involved.
3. Restrict the backup directory and audit log to the deploy user: the code creates the directory with `0775` and archives with the process umask, so tighten with `chmod 700 storage/app/private/backups` and `chmod 600 storage/logs/system-backups.log` plus a restrictive umask in the deploy unit.
4. After deploying the new channel, run `php artisan config:clear` and `php artisan config:cache` (if config caching is used); otherwise `Log::channel('backups')` is undefined.
5. Ensure `storage/logs` is writable and rotated by the host (the channel is `single`, so rotation is an operator responsibility; the application does not rotate, truncate, or delete it).
6. Do not expose the command through a web route, webhook, job, or scheduler. Only an authorized operator shell may run it. There is no role/permission check inside the command because it is CLI-only; access control is OS/deploy access.
7. Backups are local-only. Off-host copy, retention, and deletion are explicitly not implemented; copying or pruning is an operator procedure and must never be automated by this code.

## 7. Manual Verification Procedure

Run on a non-production host first.

1. `php artisan config:clear`
2. `php artisan backup:create --label=manual-verification`
   - Expect exit code `0` and a printed `Path`, `Bytes`, `SHA-256`.
3. Confirm the file exists under `storage/app/private/backups/` and that it is not under `public/`:
   - `Test-Path public/backups` → `False`
   - `Get-ChildItem storage/app/private/backups`
4. Request the archive URL through the web server (for example `https://<host>/storage/backups/<name>.zip`) and confirm `403`/`404`. Confirm `storage/app/private` is outside the document root.
5. Recompute the checksum and compare with the printed value:
   - PowerShell: `Get-FileHash storage/app/private/backups/<name>.zip -Algorithm SHA256`
   - Linux: `sha256sum storage/app/private/backups/<name>.zip`
6. Inspect the audit record:
   - `Get-Content storage/logs/system-backups.log -Tail 3`
   - Expect `event=system_backup.created`, `status=success`, `path`, `bytes`, `sha256`, `database_method`. Confirm no `password`, `username`, host, DSN, or `.env` values appear.
7. Failure handling:
   - `php artisan backup:create --label=<101 characters>` → exit code `1`, no new archive, an `error` record with `status=failure` and no label.
   - Temporarily point `BACKUP_DUMP_BINARY` at a bad path on a disposable host to confirm the fallback PDO path, and (separately, in a disposable environment) revoke DB access to confirm a failure record. Never do this against production.
8. Structural integrity of the artifact is covered by the existing suites (run only when the backup code itself changes):
   - `php artisan test tests/Feature/SystemBackupTest.php`
   - `$env:SYSTEM_RESTORE_DRILL = "1"; php artisan test tests/Feature/SystemBackupRecoveryDrillTest.php` (disposable MySQL only)

## 8. Commands and Actual Results

| Command | Result |
|---|---|
| `php artisan test tests/Feature/SystemBackupCommandTest.php` | **5 passed, 77 assertions** (1.04s) |
| `php -l` on `CreateSystemBackup.php`, `SystemBackupAuditLogger.php`, `CollectingLogger.php`, `SystemBackupCommandTest.php`, `AppServiceProvider.php`, `config/logging.php`, `config/backups.php` | No syntax errors |
| `php artisan list \| Select-String "backup:"` | `backup:create` registered with its description |
| `git diff --check` | Only the pre-existing CRLF warning in `tests/Feature/SubscriptionRenewalCancellationTest.php` |
| `Test-Path storage/logs/system-backups.log` after the test run | `False` (tests never wrote to the real audit file) |
| Files in `storage/app/private/backups` after the test run | `0` |
| `SystemBackupTest` / recovery drill | Not re-run - no shared backup service, dumper, or validator code changed; their 4B-5E/4B-5F results stand |
| `npm run build` | Not run - no frontend file changed |
| Full test suite | Not run (per phase rules) |

Focused test coverage actually exercised:

1. `backup:create --label=sprint-4b5g` exits `0`, creates exactly one `backups/system-backup-*.zip` on the private fake disk, reports path/bytes/SHA-256, and writes one `info` record whose keys are exactly the 15 whitelisted fields with matching path, byte size, SHA-256, `database_method=pdo_consistent_snapshot`, `database_driver=mysql`, and `media_files=1`.
2. The archive is absent from the `public` fake disk, and `backups.disk` is `local`.
3. A mocked `SystemBackupService` failure produces exit code `1`, prints a redacted message, creates no archive, writes one `error` record with the exact failure field set, `error_class=RuntimeException`, `[redacted]` in `error_message`, and no credential substring in context or console output.
4. Redaction removes a DSN with inline credentials, `password=`, `DB_PASSWORD=`, `--password=`, `api_key=`, and `access_token=` values, keeps the non-secret prefix, and truncates `error_message` to 400 characters.
5. A 101-character label is rejected with exit code `1`, no archive, and a failure record with `label=null`.
6. `backups.log_channel` resolves to the `backups` channel: driver `single`, path `storage/logs/system-backups.log`, outside `public_path()`, placeholder replacement disabled.

## 9. Limitations and Remaining Production Blockers

| Item | Status |
|---|---|
| Operator CLI backup creation | Implemented, Verified in tests |
| Audit record on success and failure | Implemented, Verified at payload level |
| Audit log written to the real file | Not exercised in this phase (payload and channel config asserted; no real file written) |
| Backup on a real private disk | Not exercised in this phase (tests use `Storage::fake`); the service path was exercised for real in 4B-5F |
| Encryption at rest | **Blocked** (section 5) |
| Off-host copy / replication | Not implemented |
| Retention, rotation, purge, automatic cleanup | Not implemented and intentionally absent; the audit log is never pruned by code |
| Failure alerting / monitoring | Not implemented; operator must inspect the log or exit code |
| Scheduling | Not implemented (runs only when an operator invokes it) |
| Operator identity/authorization beyond OS access | Not implemented (CLI-only; no route, no permission gate) |
| Tamper-evident audit (signing, append-only storage) | Not implemented; plain log file |
| Restore executor / production restore | Still Planned (4B-5E/4B-5F boundary unchanged) |
| Production-scale timing, lock impact, disk space checks | Not verified; no pre-flight free-space check exists |
| Concurrency guard (two simultaneous backups) | Not implemented; unique names prevent filename collision, not disk/IO contention |
| Backup verification after write (re-open + validate) | Not implemented; operator procedure uses the checksum and the existing validator suites |

## 10. Status Summary

- Operator-only backup command: **implemented and verified** by focused tests.
- Audit logging: **implemented and verified** at payload level; the dedicated file channel is configured and asserted private.
- Credential safety: **verified** for console output and audit context by the redaction tests.
- Encryption at rest: **blocked and documented**, no key invented or hardcoded.
- Scheduling, retention, deletion, restore, HTTP, and UI: **not added**.
- Production readiness: **not claimed**.

## Final Confirmation

- No restore was executed. No database, table, row, or media file was created, overwritten, or deleted by this phase; tests ran inside `DatabaseTransactions` with fake storage disks, and the post-run scan shows no archive in `storage/app/private/backups` and no `storage/logs/system-backups.log`.
- No migration was created or run; no schema, tenant, subscription, billing, identity, or merchant export logic was modified.
- No archive, dump, media copy, `.env` value, or credential was written into the repository or into a log.
- No commit, push, branch, reset, discard, or stash occurred; all pre-existing uncommitted and untracked work is preserved.
- Claims are limited to the recorded output in section 8.
