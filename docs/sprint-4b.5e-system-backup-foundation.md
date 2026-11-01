# Sprint 4B-5E - System Backup Foundation

**Date:** 2026-10-24
**Scope:** Build the operator-owned system backup artifact (database snapshot + application-owned media + checksum manifest) and a fail-closed preflight validator.
**Out of scope:** Production restore endpoint, UI, automatic restore, tenant overwrite, media restore executor, backup scheduling/retention, encryption, off-host replication, and any change to the 48-category merchant export.

## 1. Final Architecture

| | Merchant business export (unchanged) | System backup (this phase) |
|---|---|---|
| Owner | Merchant admin (`billing.view`) | Authorized operator only (no route exists) |
| Contract | 48 tenant-scoped CSVs + `assets.json` + `manifest.json`, `schema_version=1`, `archive_version=1` | DB snapshot + `media/{disk}/...` + `manifest.json`, `schema_version=1`, `archive_version=1` |
| Scope | One tenant's business data | Whole database and application-owned media |
| Restore | Isolated drill (4B-5D), test-only | Preflight validated; execution still test-only / Planned |

The two artifacts share no code path and no manifest schema. Neither substitutes for the other: the merchant export excludes platform billing, identity, audit and media binaries; the system backup includes the full database but is never merchant-facing.

## 2. Artifact Structure and Version Contract

```
manifest.json
database/database.sql          (mysql / mariadb)
database/database.sqlite       (sqlite)
media/{disk}/{relative path}   (one entry per bundled local media file)
```

`manifest.json`:

```
type              : "system_backup"
schema_version    : 1                       (int, required)
archive_version   : 1                       (int, required)
generated_at      : ISO 8601
app               : { name, environment, laravel, php }
database          : { driver, server_version, method, consistency, tables, entry }
media             : { disks[], files, bytes }
files             : { "<entry>": { bytes, sha256 } }     // every entry except manifest.json
excluded          : { paths[], skipped[{disk,path,reason}] }
notes             : 3 fixed statements + driver-specific consistency notes
```

Rules:

- Every archive entry except `manifest.json` is listed in `files` with exact byte size and SHA-256; `manifest.json` is not self-hashed (it cannot contain its own final hash).
- Preflight accepts only the numeric versions listed in `config/backups.php` (`supported_schema_versions`, `supported_archive_versions`). Anything newer, older, non-numeric, or missing fails closed.
- Exactly one `database/*` entry is permitted. Media entries must live under `media/`. Any other entry is rejected as unexpected.
- No credentials, host names, DSNs, `.env` values, `.env` files, logs, caches, temporary exports, or previous backup archives are included.

## 3. Files Added or Changed

| File | Status |
|---|---|
| `config/backups.php` | Added - disk/path, versions, DB connection and dump binary, media disks/excludes, supported restore versions |
| `app/Services/Backup/DatabaseDumper.php` | Added - mysqldump preferred, PDO consistent-snapshot fallback, SQLite `VACUUM INTO` |
| `app/Services/Backup/SystemBackupService.php` | Added - orchestration, media collection, ZIP assembly, manifest, failure cleanup |
| `app/Services/Backup/SystemBackupValidator.php` | Added - fail-closed preflight and media path containment |
| `app/Services/Backup/BackupValidationException.php` | Added - typed failure with a machine-readable reason |
| `tests/Feature/SystemBackupTest.php` | Added - 10 focused tests |
| `docs/sprint-4b.5e-system-backup-foundation.md` | Added - this report |

Changed: none. No tracked file was modified. No migration, route, controller, model, frontend, or export file was touched.

## 4. Implemented versus Planned

| Item | Status | Notes |
|---|---|---|
| Versioned system backup artifact | Implemented, Verified | `schema_version`/`archive_version` = 1, enforced by preflight |
| MySQL database snapshot | Implemented, Verified | PDO consistent snapshot exercised; mysqldump path implemented but not exercised in this environment |
| SQLite consistent snapshot | Implemented, Verified | `VACUUM INTO`, tested against a temporary file DB |
| Application-owned media bundling | Implemented, Verified | Local disks only; remote disks recorded as skipped |
| Checksum manifest (bytes + SHA-256) | Implemented, Verified | Streaming verification, no manifest self-hash |
| Preflight validation | Implemented, Verified | Structure, versions, entries, sizes, hashes, traversal, containment |
| Private archive storage with unique names | Implemented, Verified | `local` disk guard, `storage/app/private/backups`, unique suffix |
| Failure and temp cleanup | Implemented, Verified | Partial ZIP and dump removed on failure; dump removed on success |
| Media restore execution | Planned | No executor, no UI, no production path |
| Database restore execution | Planned | Preflight only; 4B-5D merchant drill remains the only executed restore |
| Backup scheduling / retention / rotation | Planned (intentionally absent) | No command, no scheduler, no deletion policy |
| Encryption / off-host replication | Planned | Local disk only |
| Operator authorization and audit | Planned | Service-level only; no route or CLI wired |

## 5. Safety and Isolation Checks Performed

- **Private storage only:** the service refuses any backup disk whose driver is not `local`; archives are written under `storage/app/private/backups` with a unique name (`system-backup-Ymd-His-<random>.zip`).
- **No credentials in artifacts:** the manifest's `database` block is asserted to contain exactly `consistency, driver, entry, method, server_version, tables`. The mysqldump path passes credentials through a temporary `--defaults-extra-file` (mode 0600, deleted in `finally`) and uses array-form `proc_open`, so no shell interpolation and no credentials in the process arguments.
- **No credential strings in manifest:** the encoded manifest is asserted free of `db_password`, `db_username`, `defaults-extra-file`, and `mysql:host=`.
- **No production restore:** no endpoint, command, job, or UI calls the validator with a database target. Preflight is read-only; nothing is extracted, dropped, or applied.
- **Media containment:** every manifest entry is checked against the configured restore directory; `..`, absolute paths, drive letters, schemes, backslashes, and directory entries are rejected before any extraction could occur.
- **No data touched:** tests use `DatabaseTransactions`, `Storage::fake('local')`, and `Storage::fake('public')`; the only real filesystem writes are inside the test framework's fake disks and system temp files, which are removed.
- **No migrations run:** the schema was read via `SHOW CREATE TABLE`; no `migrate` invocation was part of this phase.
- **No repository artifacts:** no archive, dump, media copy, or credential exists in the working tree; `git status` shows only the new source, test, config, and documentation files.

## 6. Database Backup Mechanism and Consistency

| Driver | Mechanism | Recorded consistency |
|---|---|---|
| MySQL/MariaDB with `BACKUP_DUMP_BINARY` | `mysqldump --single-transaction --quick --skip-lock-tables --no-tablespaces --routines --events --triggers --hex-blob --default-character-set=utf8mb4` | `innodb_consistent_snapshot` |
| MySQL/MariaDB without binary | Own PDO connection, `SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ`, `START TRANSACTION WITH CONSISTENT SNAPSHOT`, base-table dump (`DROP TABLE IF EXISTS` + `SHOW CREATE TABLE` + batched `INSERT`s) | `innodb_consistent_snapshot` (InnoDB only) |
| SQLite | `VACUUM INTO` | `consistent` |

The dump is logical, contains no `CREATE DATABASE`/`USE`, and never reads through Laravel's request connection, so an active application transaction is neither committed nor leaked into the artifact.

Consistency limitations (recorded per archive, not claimed globally):

- InnoDB tables are snapshot-consistent; MyISAM or other non-transactional tables are not guaranteed under either mechanism.
- The PDO fallback omits views, stored routines, triggers, and events; a repository grep confirms no migration currently defines any. The mysqldump path includes them.
- No binary-log position or point-in-time-recovery metadata is captured.
- Dump duration and memory were validated only against the test database, not production scale.

## 7. Media Backup and Limitations

- Bundled: files on configured local disks (`backups.media.disks`, default `public`), enumerated with `allFiles()` and resolved individually.
- Excluded: generated `exports`, previous `backups`, `tmp`, `temp`, `cache`, `logs`, plus `*.tmp` and `*.log`; exclusion matches the full relative path or any single path segment.
- Skipped and reported in `excluded.skipped`: remote disks (for example Cloudinary), files resolving outside the disk root, and unreadable files. Nothing is silently followed outside the disk root.
- Media hashes are computed before ZIP assembly; a file modified in the narrow window before `ZipArchive::close()` would produce an artifact whose stored hash no longer matches. Restore preflight detects that case and fails closed.
- Media binaries are archived with their relative paths; no deduplication, re-encoding, or external-object storage snapshot is performed.

## 8. Restore Readiness Boundary

- Preflight (`SystemBackupValidator::validate`) is the only restore-adjacent capability implemented. It validates versions, entry allowlist, sizes, hashes, and target-directory containment, and never writes to a database.
- Restore execution for the system artifact is **Planned**. The only executed restore in this project remains the 4B-5D merchant export drill against a disposable database.
- Future operator-only restore must: run from a CLI or authenticated operator context with an audit record; refuse any target that is the configured application database; require an explicit empty/disposable target; apply the database dump, restore media into the configured directory, re-verify counts and checksums after restore, and never overwrite a live tenant.

## 9. Commands and Results

| Command | Result |
|---|---|
| `php artisan test tests/Feature/SystemBackupTest.php` | **10 passed, 66 assertions** (1.48s) |
| `php artisan test tests/Feature/MerchantDataExportTest.php` | **20 passed, 163 assertions** (5.71s) |
| `php -l` on `config/backups.php` and all 4 `app/Services/Backup/*.php` files | No syntax errors |
| `php -l tests/Feature/SystemBackupTest.php` | No syntax errors |
| `git diff --check` | Only the pre-existing CRLF warning in `tests/Feature/SubscriptionRenewalCancellationTest.php` |
| Leftover artifact scan (`storage/app/private/backups`, `%TEMP%\system-backup-*`) | No files left behind |
| `npm run build` | Not run - no frontend file changed |
| Full test suite | Not run (per phase rules) |

Test coverage in the focused run:

- Archive creation, manifest fields, unique private path, entry set equals manifest plus `manifest.json`.
- Database dump contains table DDL and committed rows, and no `CREATE DATABASE`/`USE`.
- Media file bundled with exact bytes and SHA-256; excluded `tmp` file absent.
- Preflight accepts a valid archive and resolves media paths inside the target directory.
- SQLite snapshot uses `VACUUM INTO` and produces a real SQLite file.
- Rejection: unsupported `archive_version`, unsupported `schema_version`, missing listed entry, tampered entry (`checksum_mismatch`), unexpected entry, malformed manifest, traversal entry (`path_traversal`), absolute/drive/backslash entry (`invalid_entry`).
- Manifest exposes no credential fields or credential-like strings.

One test-only assertion fix was required during the run (Windows separator normalization when comparing the fake disk path). No production code changed as a result.

## 10. Remaining Blockers Before Production Restore

1. **Operator authorization and audit:** an authenticated CLI/operator context, an audit record for backup and restore, and no HTTP exposure.
2. **Restore executor:** dependency-ordered database apply into an explicitly disposable database, with a hard refusal for the configured application database, dry-run output, and post-restore count/checksum verification.
3. **Media restore:** extraction with containment enforcement, collision policy, and a strategy for remote disks (Cloudinary) that are currently skipped.
4. **Artifact hardening:** encryption at rest, off-host replication, retention/rotation policy, and integrity signing; none exist today.
5. **Compatibility gate:** mapping `schema_version` to a migration state and refusing archives from an incompatible codebase.
6. **Scale validation:** dump/restore timing and lock behaviour on production-sized data; confirming the mysqldump binary on the deployment target (this environment exercised the PDO fallback, not the binary path).
7. **Operational proof:** a staging-sized restore rehearsal, documented RTO/RPO, and alerting when a backup fails.
8. **PITR decision:** whether binary-log point-in-time recovery is required; the current artifact is a logical point-in-time dump only.

## 11. Status Summary

- Merchant export contract: **unchanged and verified** (20 passed, 163 assertions).
- System backup creation: **implemented and verified** (PDO snapshot and SQLite; mysqldump path implemented, not exercised here).
- Preflight validation: **implemented and verified**, fail-closed.
- Media bundling: **implemented and verified** for local disks; remote disks **documented as skipped**.
- Restore execution: **not implemented** by design.
- Production readiness: **not claimed**.

## Final Confirmation

- No existing data was restored, overwritten, or deleted. No database received a dump.
- No existing asset was deleted or moved; media was read-only, and test files lived in Laravel fake disks.
- No schema migration was created or executed; the schema was only read.
- Tenant retention, cancellation, subscription, billing, and identity/ownership logic were not touched.
- `tenant_auto_delete_enabled` behavior is unchanged and no tenant auto-delete, purge, deletion, anonymization, or retention scheduling exists or was added.
- No commit, push, branch operation, reset, discard, or stash occurred; all pre-existing uncommitted and untracked work is preserved.
- Production restore readiness is **not** claimed; it requires the separately approved operator restore process listed in section 10.
