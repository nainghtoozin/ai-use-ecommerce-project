# Sprint 4B-5H - Backup Operational Verification & Preflight Hardening

**Date:** 2026-10-24
**Scope:** Harden the existing 4B-5E/4B-5F/4B-5G system backup with a disk-space preflight, post-creation verification, and a single-host lock. No artifact format change, no restore execution, no new surface.
**Out of scope:** Restore, HTTP/UI, scheduling, retention/purge/cleanup, encryption, off-host copy, alerting, and any change to tenant, subscription, billing, identity, or merchant export logic.

## 1. What Changed

| File | Status | Change |
|---|---|---|
| `app/Services/Backup/SystemBackupPreflight.php` | Added | Free-space preflight with configurable margin; fails closed when space cannot be read |
| `app/Services/Backup/SystemBackupLock.php` | Added | `flock`-based single-host lock with explicit release |
| `app/Services/Backup/BackupOperationException.php` | Added | Typed failure carrying a machine-readable `reason` |
| `tests/Feature/SystemBackupHardeningTest.php` | Added | 5 focused tests (25 assertions) |
| `app/Services/Backup/SystemBackupService.php` | Changed | Acquires the lock, runs the space preflight, validates the finished archive, cleans up only its own archive |
| `app/Services/Backup/DatabaseDumper.php` | Changed | Added read-only `estimateBytes()` for the preflight (MySQL information_schema sum, SQLite file size) |
| `config/backups.php` | Changed | Added `disk_space.safety_margin` and `disk_space.min_free_bytes` |
| `docs/sprint-4b.5h-backup-operational-verification.md` | Added | This report |

Unchanged: archive layout, `manifest.json` schema, `schema_version`/`archive_version` (still `1`), `SystemBackupValidator` rules, audit channel/fields, `backup:create` command contract, merchant export, routes, models, migrations, frontend.

## 2. Backup Creation Sequence (current)

```
resolve private local disk + backup directory
ensure directory exists
acquire lock            storage/app/private/backups/.locks/system-backup.lock   (flock LOCK_EX | LOCK_NB)
  ├─ enumerate application media (bytes)                       <- no writes
  ├─ disk-space preflight on the backup volume                 <- no writes
  ├─ database dump to a system temp file
  ├─ build ZIP (database entry + media + manifest.json)
  ├─ delete the temp dump
  ├─ compute archive bytes + SHA-256
  ├─ SystemBackupValidator::validate(archive)                  <- post-creation verification
  │    └─ on failure: unlink this archive only, throw BackupOperationException
release lock            finally, always
return disk/path/absolute/bytes/sha256/manifest
```

Failure at any step: only the temp dump and the archive created by that call are removed. No pre-existing archive, media file, or directory is touched.

## 3. Disk-Space Preflight

Config (`config/backups.php`):

```php
'disk_space' => [
    'safety_margin' => (float) env('BACKUP_DISK_SAFETY_MARGIN', 1.5),
    'min_free_bytes' => (int) env('BACKUP_MIN_FREE_BYTES', 268435456),
],
```

Required space is computed as:

```
payload  = media bytes (enumerated) + database bytes (driver estimate, 0 if unavailable)
required = ceil(payload * max(1.0, safety_margin)) + min_free_bytes
```

- MySQL/MariaDB estimate: `SUM(data_length + index_length)` for the connection's own schema.
- SQLite estimate: source file size.
- `estimateBytes()` is read-only and returns `null` (treated as 0) instead of throwing, so an estimation failure never becomes a credential-bearing error; the fixed `min_free_bytes` headroom still applies.
- Free space is read from the nearest existing ancestor of the backup directory with `disk_free_space()`.
- Unknown or unreadable space (including an invalid path) throws `BackupOperationException` with reason `disk_space_unknown` and a path-free message.
- Insufficient space throws `BackupOperationException` with reason `insufficient_disk_space` and a message containing only the required and available megabytes - no host, path, user, or connection data.
- The preflight runs after the lock is taken and before any dump or archive write.

## 4. Post-Creation Verification

After `ZipArchive::close()`, the service calls the existing `SystemBackupValidator::validate($archivePath)` (no target directory). That re-checks: archive readability, safe entry names, manifest presence/shape, numeric schema/archive versions against the config allowlists, exact entry set, single database entry, per-entry byte sizes, and SHA-256 hashes.

If validation fails - for any exception, not only `BackupValidationException` - the service unlinks exactly the archive it just created and throws:

```
BackupOperationException(
  'The new system backup archive failed post-creation verification: <reason>.',
  reason: 'post_creation_verification_failed'
)
```

The command therefore exits `1` and audits a failure; success is only returned after the archive passes structural and checksum verification. The artifact format itself is unchanged.

## 5. Locking

- File: `storage/app/private/backups/.locks/system-backup.lock` (inside the private disk, outside the archive listing and excluded from media).
- `fopen('c')` + `flock(LOCK_EX | LOCK_NB)`; the PID is written for operator debugging.
- A second concurrent operation throws `BackupOperationException` with reason `backup_in_progress`.
- Release happens in a `finally` block (`LOCK_UN` + `fclose`); the OS also releases an `flock` if the process dies, so a crash cannot leave a permanent lock.
- The empty lock file is intentionally never deleted (deleting it would reintroduce a race); it is 1 file of a few bytes.

## 6. Tests and Actual Results

| Command | Result |
|---|---|
| `php artisan test tests/Feature/SystemBackupHardeningTest.php` | **5 passed, 25 assertions** (1.04s) |
| `php artisan test tests/Feature/SystemBackupTest.php tests/Feature/SystemBackupCommandTest.php` | **15 passed, 143 assertions** (2.22s) |
| `php -l` on the 4 new PHP files, `SystemBackupService.php`, `DatabaseDumper.php`, `config/backups.php`, the new test | No syntax errors |
| `git diff --check` | Only the pre-existing CRLF warning in `tests/Feature/SubscriptionRenewalCancellationTest.php` |
| Post-run scan `storage/app/private/backups` | 0 files/directories created by this phase |
| Post-run `Test-Path storage/logs/system-backups.log` | `False` (no real audit file written by tests) |
| 4B-5F recovery drill | Not re-run - no archive format, dump mechanism, or validator rule changed; the drill's create path is additionally covered by the tests above |
| `npm run build` / full suite | Not run (no frontend change; per phase rules) |

What the focused tests actually prove:

1. **Preflight failure:** forcing `min_free_bytes` to 1 PB raises `insufficient_disk_space`, the message names required/available MB and contains no path, no `.zip` is created, the system temp dump count is unchanged, and the lock is immediately re-acquirable (failure path released it).
2. **Unknown space:** an invalid target path raises `disk_space_unknown` and fails closed.
3. **Verification failure:** a mocked validator (`checksum_mismatch`) makes `create()` throw `post_creation_verification_failed`; the newly written archive is deleted, a pre-existing `backups/previous.zip` is byte-identical afterwards, the temp dump count is unchanged, and the lock is released.
4. **Lock contention:** with a lock held by the test, `create()` is refused with `backup_in_progress` and the existing archive is untouched; after release, `create()` succeeds, the checksum matches, and both archives are present.
5. **Lock release:** after a successful backup the lock can be acquired again immediately, the lock file exists at `backups/.locks/system-backup.lock`, and `files('backups')` still contains only the archive (the lock does not appear in the archive listing).
6. **Verification success:** a normal backup is re-validated independently and returns the same file map, with `type = system_backup`.

## 7. Limitations

- The space check is an estimate: InnoDB `data_length + index_length` is approximate, compression/duplication is not modelled, and the ZIP is written while the source media still occupies the same volume. The margin (default 1.5x + 256 MB) is a safety buffer, not a guarantee.
- Free space is checked only on the backup volume. The database dump first lands in the system temp directory (`sys_get_temp_dir()`), which may be a different volume; that volume's free space is not checked.
- No reservation: another process can consume the space between the check and the write.
- The lock is single-host only. It is not safe on NFS/SMB or across multiple application servers; a shared/distributed lock is still required for horizontal deployments.
- The lock is per backup directory; changing `backups.path` yields a different lock file.
- Verification reads the whole archive a second time (hashing), which roughly doubles archive I/O; acceptable for operator-run backups, not benchmarked at production size.
- Verification proves structure, entry set, sizes, and checksums only. It does not prove the dump is semantically restorable - that remains the 4B-5F drill boundary.
- `estimateBytes()` ignores views, routines, triggers, and events (they are not counted in `data_length` for all engines and are absent from the project's migrations).
- The command's failure path for lock/space errors is only tested at service level; the command's generic `Throwable` handler and audit assertions are covered by the 4B-5G tests, not by a dedicated lock-contention command test.
- No alerting, no scheduling, no retention, no encryption, no off-host copy.

## 8. Non-Production Manual Verification Checklist

Run on a staging or disposable host only. Never run step 5's failure simulations against production, and never point the command at production data.

**A. Real private-disk write**

1. `php artisan config:clear` (required after adding `disk_space` config).
2. Note the free space on the backup volume and the size of `storage/app/public` and of the database.
3. `php artisan backup:create --label=staging-disk-verification` -> expect exit code `0`, a printed path, bytes, and SHA-256.
4. Confirm the file is inside `storage/app/private/backups/` and that `storage/app/public/backups` and `public/backups` do not exist.
5. Request `https://<staging-host>/storage/backups/<file>.zip` -> expect `403`/`404`; confirm `storage/app/private` is outside the document root.
6. Compare `Get-FileHash <file> -Algorithm SHA256` (or `sha256sum`) with the printed value.
7. Confirm `storage/app/private/backups/.locks/system-backup.lock` exists and leave it in place.
8. Confirm a second `backup:create` still succeeds (lock was released).

**B. Real audit-log write**

1. After step A.3, inspect `storage/logs/system-backups.log` (`Get-Content ... -Tail 3`).
2. Confirm the record contains `event=system_backup.created`, `status=success`, `path`, `bytes`, `sha256`, `database_method`, `media_files`, `occurred_at`, `duration_ms`, `environment`.
3. Confirm the line contains none of: `password`, `username`, `DB_`, `mysql://`, host name, socket path, `.env` values.
4. Confirm file permissions are restrictive (`600` recommended) and that the log is outside the document root.

**C. Failure handling (staging only, disposable host)**

1. Space refusal: temporarily set `BACKUP_MIN_FREE_BYTES` far above the volume's free space, run `backup:create` -> expect exit `1`, an `error` record with `status=failure`, no new `.zip`, and no leftover `system-backup-db-*` in the temp directory. Restore the setting afterwards.
2. Lock refusal: hold the lock with a second PHP process (or the test harness) and run `backup:create` -> expect exit `1` and a `backup_in_progress` failure record. Release the lock and confirm the next run succeeds.
3. Do not simulate failures by breaking the live database, deleting archives, or filling the production volume.

**D. Post-run hygiene**

1. `Get-ChildItem $env:TEMP -Filter "system-backup-*"` -> expect no drill/dump leftovers.
2. Confirm no archive was written into the repository working tree (`git status` unchanged except the expected untracked files).

## 9. Encryption Prerequisites (unchanged from 4B-5G)

No encryption was added and no key exists. Before encryption at rest can be implemented, the project needs:

1. An approved key source (environment secret or external KMS) and key-holder policy - `BACKUP_ENCRYPTION_KEY` does not exist and must not be invented.
2. A versioned chunked AEAD format (AES-256-GCM or libsodium secretstream) with nonce/framing rules, because archives can exceed memory and `ZipArchive` password encryption is not authenticated.
3. A rotation and loss-recovery policy, plus a restore-side decryptor - restore execution is still not implemented, so an encrypted archive would currently be unopenable.
4. A decision on whether the key lives off-host from the archive.

Until then the operational mitigations are the private disk, OS permissions, and no web exposure.

## 10. Status Summary

| Item | Status |
|---|---|
| Disk-space preflight (configurable margin, fails closed) | Implemented, Verified |
| Post-creation archive verification with the existing validator | Implemented, Verified |
| Cleanup limited to the operation's own archive/temp file | Implemented, Verified |
| Single-host lock with guaranteed release | Implemented, Verified |
| Focused tests (failure, verification, lock, cleanup) | Implemented, Verified |
| Archive format | Unchanged (`schema_version`/`archive_version` = 1) |
| Real private-disk and real audit-log run | Not executed in this phase - checklist provided (section 8) |
| Encryption at rest | Blocked - prerequisites in section 9 |
| Restore, scheduling, retention, alerting, off-host copy | Not implemented (intentional) |
| Production readiness | Not claimed |

## Final Confirmation

- No restore was executed; no database, table, row, media file, or pre-existing archive was created, overwritten, moved, or deleted by this phase. Tests used `DatabaseTransactions` plus fake `local`/`public` disks, and the post-run scan shows 0 entries under `storage/app/private/backups` and no `storage/logs/system-backups.log`.
- No migration was created or run; no schema, tenant, subscription, billing, identity, or merchant export logic was modified.
- The backup artifact format and manifest schema are unchanged; no encryption key was invented and no encryption was implemented.
- No archive, dump, media copy, credential, or `.env` value was written into the repository.
- No commit, push, branch, reset, discard, or stash occurred; all pre-existing uncommitted and untracked work is preserved.
- Claims are limited to the recorded command output in section 6. Production readiness is not claimed.
