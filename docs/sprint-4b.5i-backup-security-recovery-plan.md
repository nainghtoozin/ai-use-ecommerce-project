# Sprint 4B-5I - Backup Security & Recovery Plan

**Date:** 2026-10-24
**Type:** Planning and documentation only. No production code, key, schema, or data change was made.
**Inputs reviewed:** `docs/sprint-4b.5e-system-backup-foundation.md`, `docs/sprint-4b.5f-system-backup-recovery-drill.md`, `docs/sprint-4b.5g-production-backup-operations.md`, `docs/sprint-4b.5h-backup-operational-verification.md`, plus the current code in `app/Services/Backup/`, `app/Console/Commands/CreateSystemBackup.php`, `config/backups.php`, `config/logging.php`.

## 1. Current State (verified in 4B-5E to 4B-5H)

| Capability | State | Notes |
|---|---|---|
| Artifact | Implemented | `manifest.json` + one `database/*` entry + `media/{disk}/...`, `schema_version=1`, `archive_version=1` |
| Creation | Implemented | `SystemBackupService`, mysqldump or PDO consistent snapshot, SQLite `VACUUM INTO` |
| Operator CLI | Implemented | `php artisan backup:create [--label=]`, exit 0/1 |
| Audit | Implemented | Dedicated `backups` channel, whitelisted fields, credential redaction |
| Integrity preflight | Implemented | `SystemBackupValidator`: versions, entry allowlist, sizes, SHA-256, traversal, containment |
| Space preflight | Implemented | 1.5x payload + 256 MB headroom, fail-closed |
| Concurrency | Implemented | `flock` lock at `backups/.locks/system-backup.lock` |
| Restore | Drill only | Test-only `ArchiveRestorer`, disposable databases, media to temp dir |
| Encryption | Not implemented | No key, no format, no decryption path |
| Off-site, scheduling, retention, alerting | Not implemented | Intentionally absent |

Environment facts relevant to the design: PHP 8.2.4 64-bit with `openssl`, `hash`, and `zip`; no `sodium` extension loaded. `ZipArchive` is the only archive writer. MySQL/MariaDB 10.4 in XAMPP, `mysqldump` available at `C:\xampp\mysql\bin\mysqldump.exe`.

## 2. Goals, Non-Goals, and Threat Model

**Goals of the target design**

1. Confidentiality at rest for archives that leave the application host (and ideally for local archives too).
2. Authenticated integrity of the whole archive: no undetected bit flip, truncation, reordering, or entry substitution.
3. Streaming operation: constant memory, archives far larger than RAM.
4. Key rotation without rewriting archive payloads.
5. Operator-only recovery that cannot target the live database.
6. Reversible failure: restore either completes or cleans up what it created.
7. Full audit trail without secrets.

**Non-goals for the next phases**

- Merchant-facing export encryption (separate contract, unchanged).
- Application-level encryption of live data, password-manager features, HSM integration.
- Automatic restore, automatic failover, PITR/binlog, cross-region replication.
- Automatic retention deletion until a separate approval.

**Threat model**

| Threat | Mitigation in this plan |
|---|---|
| Stolen archive file or off-site bucket | AEAD encryption with keys never stored beside archives |
| Tampered or truncated archive | Chunked AES-256-GCM with authenticated header, record index, end record |
| Replay/substitution of an older archive | Operator confirms archive identity; optional detached signature (decision D-14) |
| Key leak via logs or manifest | Key material never logged; only `key_id` (non-secret) may appear |
| Lost key | Escrow + rotation policy + key-availability preflight before any restore |
| Operator mistake during restore | Target must be created by the command, name-pattern enforced, explicit confirmation token |
| Accidental deletion of live media | Restore writes to a new/staging directory; no merge in v1 |
| Ransomware deleting off-site copies | Object lock/WORM bucket, write-only credentials, versioning |

## 3. Authenticated Encryption Design (v1 container, proposed)

### 3.1 Shape

The existing ZIP stays exactly as it is (v1 artifact, unchanged). It is wrapped in a new encrypted container with extension `.sbx`:

```
system-backup-YYYYMMDD-HHMMSS-<random>.sbx
├── immutable header        (80 bytes, authenticated as AAD of every record)
├── key envelope            (64 bytes, authenticated under the Key Encryption Key)
└── data records            (u32 length + ciphertext + 16-byte tag, repeatedly)
    └── final zero-length "end" record (authenticated, prevents truncation)
```

Encryption is opt-in (`backups.encryption.enabled`, default `false`). Plaintext `.zip` archives remain readable by the existing validator and drills; nothing existing is invalidated.

### 3.2 Immutable header (80 bytes, big-endian)

| Field | Size | Meaning |
|---|---|---|
| magic | 4 | `SBX1` |
| container_version | 1 | `1` |
| cipher_suite | 1 | `1` = AES-256-GCM + HKDF-SHA256 |
| flags | 2 | reserved, `0` |
| chunk_size | 4 | plaintext bytes per record (1 MiB default) |
| reserved | 4 | `0` |
| file_salt | 16 | random per archive |
| payload_bytes | 8 | plaintext ZIP size |
| payload_sha256 | 32 | SHA-256 of the plaintext ZIP |
| created_at | 8 | Unix seconds |

Every data record uses `AAD = header || record_index (u64) || record_type (u8)` (`1` = chunk, `2` = end). Any header change therefore invalidates all data tags; any reorder, drop, or splice fails authentication.

### 3.3 Key envelope (64 bytes) and envelope encryption

| Field | Size | Meaning |
|---|---|---|
| key_id | 2 | identifier of the KEK used for this archive |
| wrap_suite | 1 | `1` = AES-256-GCM key wrap |
| reserved | 1 | `0` |
| wrap_nonce | 12 | random per wrap/rewrap |
| wrapped_dek | 32 | per-archive Data Encryption Key, encrypted |
| wrap_tag | 16 | GCM tag over the DEK |

- Per archive: a random 32-byte DEK is generated with `random_bytes(32)` and never leaves the process except wrapped.
- KEKs live in a key registry (file-based now, KMS-capable later). The active KEK wraps new archives.
- Wrap AAD = immutable header || key_id || wrap_suite, so the envelope cannot be moved to another archive.
- Data keys are derived from the DEK: `HKDF-SHA256(ikm=DEK, salt=file_salt, info="system-backup/v1/data") -> 32-byte AES key`.
- Per-record nonce = 12 bytes: 4 zero bytes + 8-byte big-endian record index. Nonce reuse is impossible because each archive has a unique DEK.
- **Rotation becomes cheap:** `rewrap` unwraps the DEK with the old KEK and re-wraps it with the new KEK, updating only the 64-byte envelope and its tag. Payload records and their AAD are untouched.

### 3.4 Limits

- GCM per key is safe to ~64 GiB. Because each archive has its own DEK, this is a **per-archive** limit.
- Proposed v1 guard: refuse to encrypt a payload larger than **48 GiB** and document it. Larger archives require segment keys (derive a fresh key every 32 GiB from `DEK || segment_index`), which is a v2 format decision. Approval item D-06.
- 1 MiB chunks: ~20 bytes overhead per MiB (0.002%), ~1 million records per TiB, well inside counter space.

### 3.5 Streaming API (proposed classes, not created)

| Proposed file | Responsibility |
|---|---|
| `app/Services/Backup/Encryption/BackupKeyRing.php` | Load KEKs by id from configured files; validate 32-byte length; active key selection |
| `app/Services/Backup/Encryption/BackupKeyResolver.php` | Interface for file-based keys; later KMS/secret-manager adapter |
| `app/Services/Backup/Encryption/EncryptedContainerWriter.php` | Write header/envelope/records while reading the plaintext ZIP |
| `app/Services/Backup/Encryption/EncryptedContainerReader.php` | Verify header/envelope/records; stream plaintext to a sink |
| `app/Services/Backup/Encryption/RewrapCommand` | `backup:rewrap {archive} --to-key=` (envelope-only rotation) |
| `app/Console/Commands/InspectSystemBackup.php` | Show container metadata (no key material), validate envelope |
| `app/Console/Commands/DecryptSystemBackup.php` | Operator-only decrypt-to-file for restore/drill |

Key loading rules: keys come from files (mode `0600`, outside the repository and outside the backup directory) or a secret manager; never inline in committed config; never in the manifest, audit log, or CLI output; never echoed by exceptions.

### 3.6 Creation sequence with encryption enabled

```
existing 5H pipeline (lock -> media -> space preflight -> dump -> zip -> validate)
  ├─ hash plaintext zip (SHA-256, already computed by the service)
  ├─ stream-encrypt zip -> new .sbx (header, envelope, records, end record)
  ├─ stream-decrypt .sbx and hash plaintext in memory; compare bytes + SHA-256
  ├─ on mismatch: delete only this .sbx and throw BackupOperationException('encryption_verification_failed')
  ├─ delete plaintext zip only after ciphertext verification succeeds
  └─ audit: container_version, key_id, ciphertext_sha256, payload_sha256, bytes
```

Disk-space impact: plaintext ZIP and ciphertext coexist, so the 5H formula must become `required = ceil((payload * 2) * safety_margin) + min_free_bytes` when encryption is enabled. This is a deliberate, documented change to the 5H preflight, not to the archive format.

Crash window: if the process dies between "ciphertext written" and "plaintext unlinked", a plaintext ZIP remains. Mitigation: restrictive permissions, and the operator runbook (`docs`) explains how to confirm and remove it manually. The code must not auto-delete unknown files.

Verification step rationale: re-validating the ZIP after encryption would need a plaintext temp copy (3x payload). Instead, the plaintext ZIP is fully validated before encryption (5H), and the ciphertext is verified by streaming decryption + hash equality. This proves the ciphertext decrypts to exactly the validated artifact.

### 3.7 Key source and storage

| Option | Suitability | Notes |
|---|---|---|
| Key file on the host (`/etc/<app>/backup-keys/<id>.key`, `0600`, root/deploy group) | Recommended v1 | Simple, offline-capable, KMS-free |
| Secret manager / KMS (`AWS KMS`, `GCP KMS`, Vault Transit) | Recommended target | Requires SDK/HTTP integration and a network dependency |
| Key in `.env` / config | Rejected | Appears in process env, config cache, and often in backups of config |
| Key in the repository | Forbidden | Would be exfiltration by definition |

Registry shape (proposed config, no secrets inline):

```php
'encryption' => [
    'enabled' => env('BACKUP_ENCRYPTION_ENABLED', false),
    'active_key_id' => env('BACKUP_ENCRYPTION_ACTIVE_KEY_ID'),
    'keys' => [
        // key id => absolute path to a 32-byte key file (mode 0600)
    ],
    'chunk_size' => 1048576,
    'extension' => 'sbx',
    'max_payload_bytes' => 51539607552, // 48 GiB
],
```

### 3.8 Rotation

1. Generate a new KEK outside the application (operator, two-person control).
2. Register it in the registry as the new `active_key_id`; keep the old key listed.
3. New backups use the new KEK automatically.
4. Rewrap existing archives when convenient: `backup:rewrap <archive> --to-key=<new id>`; envelope-only, cheap, verifiable.
5. Retire the old KEK only after every archive wrapped by it has been rewrapped or has reached the end of retention (retention does not exist yet, so this is a hard dependency).
6. Rotate immediately on suspected compromise; document the affected key id and rewrap instead of re-encrypting.

### 3.9 Escrow and key loss

- Escrow the **KEKs**, not the per-archive DEKs (DEKs are inside the archives).
- Escrow medium: sealed offline copy in an operator safe or a separate secret manager under a different account, with two-person access and an access log.
- Key loss = permanent loss of every archive wrapped by that key. This is the single largest operational risk of encryption and must be accepted explicitly before enabling it.
- Mitigations: at least two independent escrow copies, quarterly escrow-drill (unwrap a test archive), key-availability preflight before restore, and never enabling encryption until escrow is verified.
- Key compromise: rotate, rewrap, invalidate the old key, and treat all archives under it as potentially exposed; re-encrypt (decrypt + fresh encrypt) if the payload itself must be re-protected.

## 4. Operator-Only Restore Workflow (design only)

### 4.1 Command shape (proposed)

```
php artisan backup:restore {archive}
    --target-database=<name>  --confirm-database=<same name>
    [--media-target=<absolute dir> --confirm-media-target=<same path>]
    [--dry-run]
    [--keep-failed-target]
```

No HTTP route, no UI, no queue job, no scheduled restore, no automatic restore on boot.

### 4.2 Phases

| Phase | Action | Failure behavior |
|---|---|---|
| 1. Read archive | Verify container magic/version, unwrap DEK with `key_id`, verify envelope tag | Abort before any write |
| 2. Decrypt | Stream plaintext ZIP to a private temp file (`backups/.tmp`, mode `0600`, unique name) | Delete temp, abort |
| 3. Validate | Existing `SystemBackupValidator` on the temp ZIP (versions, entry set, checksums, traversal) | Delete temp, abort |
| 4. Compatibility | Compare archive `migrations` rows against `database/migrations/*.php` names on disk; compare manifest `database.tables` with expectations | Abort or require `--allow-older-schema` (decision D-10) |
| 5. Target guard | Validate DB name pattern, absence, and non-collision with every configured connection | Abort before create |
| 6. Media guard | Target must not exist or must be empty; staging directory created as a sibling | Abort before create |
| 7. Space | Reuse 5H preflight logic against the restore volume, using header `payload_bytes` and DB size | Abort |
| 8. Create | Create the database and staging media directory, record them as owned | Abort |
| 9. Apply | Import SQL; extract media with containment checks and per-file hash verification | Roll back owned resources |
| 10. Verify | Table count vs manifest, `migrations` non-empty, media hash equality, spot-check tenant rows | Roll back owned resources |
| 11. Commit | Move staging media into the final target directory (must not exist); keep the restored DB | Report + audit success |
| 12. Cleanup | Delete the decrypted temp ZIP and staging leftovers in `finally` | Always runs |

### 4.3 Target-database safeguards (enforced in code, not just docs)

1. `--target-database` must match `^[a-z0-9_]{1,64}$` and start with `restore_` or `drill_`.
2. Refuse if the name equals the configured application database, any other configured connection database, the test database, or a name in a small deny-list (`mysql`, `information_schema`, `performance_schema`, `sys`).
3. Refuse if the database already exists (the command creates it and therefore owns it).
4. `--confirm-database` must exactly equal `--target-database`.
5. Refuse in `APP_ENV=production` in v1. A production restore requires a separate approved runbook (decision D-12).
6. Never `DROP` a database the command did not create in this run.
7. Connections are built from the existing config with only the database name replaced; credentials are never printed.

### 4.4 Media collision handling

- v1 default: **no collisions possible** - the media target must not exist; extraction goes to a staging sibling, then a single move.
- Refuse to restore into `storage/app/public` or any path inside `public/` for now; drills restore into a temp directory. Restoring live media is a separate approved operation.
- Future merge mode (decision D-13): `--media-mode=merge --confirm-media-merge`, with `--existing=fail|skip|overwrite`. Overwrite requires snapshotting the files it replaces into `<target>/.restore-previous-<timestamp>/` and auditing every replaced path, so rollback can restore them.
- Remote disks (Cloudinary) are skipped and reported explicitly, as today.
- Every extracted path passes `SystemBackupValidator::mediaTargetPath()` before writing; restored files are hashed against the manifest.

### 4.5 Audit events (reuse 4B-5G logger)

| Event | Level | Fields |
|---|---|---|
| `system_backup.restore_started` | info | archive basename, container_version, key_id, target_database, media_target, dry_run, occurred_at |
| `system_backup.restore_completed` | info | duration_ms, database, tables_restored, media_files, media_bytes, key_id, occurred_at |
| `system_backup.restore_failed` | error | duration_ms, phase, error_class, redacted error_message, cleanup (`dropped_database`/`deleted_staging`/`left_for_inspection`), occurred_at |

Rules unchanged: no credentials, no DSNs, no key material, no file contents; messages redacted and truncated.

### 4.6 Rollback and failure behavior

- Database: created by this run -> `DROP DATABASE` on failure (after re-verifying the exact name and the prefix pattern). `--keep-failed-target` leaves it for debugging and audits `left_for_inspection`.
- Media: staging directory deleted; the final target never receives partial content because the move happens only after full extraction and verification.
- Temp decrypted ZIP: deleted in `finally`, even on failure.
- Lock: a dedicated `backups/.locks/system-backup-restore.lock` prevents concurrent restores; creation lock is unaffected.
- Idempotency: a failed run leaves no half-restored database; a successful run leaves a database the operator must review and then drop manually (the command must not auto-drop on success).
- No automatic re-run, no retries against production, no timeout-based cleanup beyond the `finally` block.

### 4.7 Post-restore verification

- `SHOW TABLES` count equals manifest `database.tables`.
- `migrations` table exists and is non-empty.
- Media files restored: count and SHA-256 equal to the manifest.
- Optional sample checks (configurable, read-only): tenant count, order count, product count.
- Failure of any check is a restore failure, not a warning.

## 5. Off-Site, Scheduling, Retention, Monitoring, Scale Testing

### 5.1 Off-site copy

- Upload **only** the encrypted `.sbx` (never the plaintext ZIP, never a key, never the audit log).
- Transport: operator tooling (`rclone` or S3-compatible client) initially, not application code. Application-driven upload requires approval (D-18).
- Bucket controls: dedicated account/project, versioning on, object lock/compliance mode, write-only credentials for the application host, delete permission only for a separate break-glass identity.
- Verify after upload: object size, then a periodic download of a random object and ciphertext SHA-256 comparison against the recorded value.
- Never delete the local copy until the remote object is verified and that verification is recorded.
- Server-side encryption is additional, never a substitute for client-side encryption.

### 5.2 Scheduling

Prerequisites before any scheduler entry: monitoring, retention policy, verified off-site copy, multi-host-safe locking (or a single designated backup host), and an approved runbook.

- One `schedule:run` entry, daily at an approved window, as a dedicated OS user, `--label=daily`.
- Rely on the existing 5H lock for overlap; add jitter if multiple hosts ever run the scheduler.
- Never schedule restore, rewrap, retention deletion, or off-site pruning.
- Guard against backup during long migrations; document the maintenance window.

### 5.3 Retention

- Policy must be approved first (example: 7 daily, 4 weekly, 12 monthly). Retention depends on off-site verification and on the encryption key still being available.
- Deletion is explicit and never automatic: `backup:prune --dry-run` then `backup:prune --confirm --keep-last=...`, with a quarantine move (for example 14 days) before permanent deletion.
- Every deletion is audited with path, bytes, and reason; the last verified off-site copy of each class is never deleted.
- Retention applies only to system backups; merchant exports and their existing TTL handling are untouched.

### 5.4 Monitoring and alerting

- Dead-man's-switch on freshness: alert if no `system_backup.created` entry in 26 hours.
- Alert on `system_backup.failed`, `insufficient_disk_space`, `disk_space_unknown`, `backup_in_progress` beyond a threshold, encryption/decryption verification failures, and off-site upload failures.
- Track duration, bytes, free space, and table/media counts from the audit log; no new monitoring stack is introduced without approval (D-19).
- Monthly key-availability check (unwrap a canary archive) and quarterly escrow drill.
- Alert channels: reuse the existing operator notification path if approved; do not couple to merchant or billing notifications.

### 5.5 Production-scale recovery testing

- Requires an approved staging environment sized like production, and approval if production-derived data is used (sanitize or use synthetic volumes).
- Measure and publish RTO/RPO, restore duration, disk requirements, and lock impact; run it after every restore-code change and before any production restore approval.
- The 4B-5F drill remains the automated regression; the scale test is a periodic operator exercise, not a CI test.
- Success criteria: application boots against the restored database, login and storefront smoke tests pass, media renders, counts match, and the drill database is dropped afterwards.

## 6. Decisions Requiring Explicit Operator Approval

| ID | Decision | Recommended default |
|---|---|---|
| D-01 | Enable encryption at all (vs. filesystem-only protection) | Yes, after escrow is proven |
| D-02 | Key source: file-based registry vs. KMS/secret manager | File-based v1, KMS as target |
| D-03 | Key custody: who holds escrow, two-person rule, access log | Operator + one security owner |
| D-04 | Escrow medium and locations (safe, secondary secret manager) | Two independent copies |
| D-05 | Rotation interval and rewrap window | Annual, plus on incident |
| D-06 | Max archive size (48 GiB) vs. segment-key v2 | 48 GiB cap; segment keys deferred |
| D-07 | Encrypt local archives too, or only off-site copies | Encrypt everything by default |
| D-08 | Chunk size and container extension (`.sbx`) | 1 MiB, `.sbx` |
| D-09 | Restore scope: disposable/drill only vs. staging vs. production | Disposable + staging in v1 |
| D-10 | Older-archive compatibility policy (refuse vs. `--allow-older-schema`) | Refuse by default |
| D-11 | Restore target naming prefix (`restore_`, `drill_`) | Both prefixes |
| D-12 | Whether production restore is ever allowed, and with which approvals | Refuse in v1 |
| D-13 | Media merge/overwrite policy and snapshot retention | Refuse collisions in v1 |
| D-14 | Detached signature (Ed25519) in addition to AEAD | Defer |
| D-15 | Retention matrix and quarantine duration | 7/4/12, 14-day quarantine |
| D-16 | Off-site provider, bucket, object lock, credential model | Dedicated account, WORM |
| D-17 | Scheduling window and designated backup host | Daily off-peak |
| D-18 | Application-driven upload vs. operator tooling | Operator tooling first |
| D-19 | Alert channel and on-call ownership | Existing operator alert path |
| D-20 | Key-availability/escrow drill cadence | Quarterly |
| D-21 | Whether the audit log may contain `key_id` | Yes (identifier only) |
| D-22 | RPO/RTO targets | Operator-defined before scheduling |
| D-23 | Whether the manifest becomes v2 (migration list/row counts) | Defer; use restored `migrations` table |
| D-24 | Restore requires maintenance mode / app shutdown? | Yes for staging restores |

## 7. Dependencies and Blockers

| Blocker | Blocks | Resolution owner |
|---|---|---|
| No approved key source or escrow procedure | Any encryption implementation | Operator (D-02, D-03, D-04) |
| No retention policy or pruning mechanism | Key retirement, safe offline rotation, rewrap completion | Operator (D-15) |
| No off-site storage provisioned | Encryption-at-rest value, disaster recovery | Operator (D-16, D-18) |
| No restore executor or target guard in application code | Staging/production restore | Implementation phase 5K |
| No migration-state compatibility field in manifest v1 | Strict version gate on restore | Workaround: restored `migrations` table; v2 later (D-23) |
| No production-like staging environment | Production-scale recovery test, RTO/RPO | Operator |
| No monitoring/alerting pipeline for audit events | Scheduling, dead-man's-switch | Operator (D-19) |
| `sodium` extension absent | libsodium secretstream option | Not a blocker: AES-256-GCM via `openssl` is available |
| Encryption doubles peak disk usage | Space preflight formula change | Implementation phase 5J |
| No multi-host lock | Multi-server deployments | Phase 5L (distributed lock or single backup host) |

## 8. Proposed Implementation Roadmap (approval-gated)

**Phase 5J - Encryption implementation**
Exit criteria: container writer/reader, key ring from files, envelope rewrap, inspect/decrypt commands, creation integration behind `encryption.enabled=false` by default, preflight formula update, tests for tamper/truncation/wrong-key/rewrap/round-trip, no plaintext leftover on success, no key material in logs. Requires D-01 to D-08.

**Phase 5K - Operator restore**
Exit criteria: `backup:restore --dry-run` and real restore into a disposable DB + staging media, target guards proven by tests (live DB refused, existing DB refused, confirmation required), rollback on injected failures, audit events, drill extended to encrypted archives. Requires D-09 to D-14 and D-24.

**Phase 5L - Operations**
Exit criteria: off-site upload + verification recorded, scheduling enabled behind approval, retention with dry-run/quarantine, freshness alerting, production-scale rehearsal report. Requires D-15 to D-22.

Each phase separately approved; no phase starts automatically.

## 9. Limitations of This Plan

- The container format is a proposal, not a standard; it must be reviewed by someone competent in AEAD constructions before implementation. No code exists yet.
- Key rotation is envelope-only; a compromised DEK or a compromised payload still requires full re-encryption.
- Encryption verifies that ciphertext decrypts to the validated ZIP; it does not make the ZIP restorable - that remains the restore phase.
- The 48 GiB cap is a design choice driven by AES-GCM per-key limits, not a measured production constraint.
- Restore compatibility checking uses the restored `migrations` table because manifest v1 has no migration list; a malicious archive could contain a crafted `migrations` table (AEAD authenticity mitigates this only if the archive was produced by the trusted key holder).
- The plan assumes operator discipline (custody, escrow drills, credential separation); none of it is technically enforceable from application code alone.
- No claim is made that the current backups are confidential at rest; today they are only protected by the private disk and OS permissions.

## 10. Safety and Status

- **No implementation:** no encryption, decrypt, rewrap, restore, prune, or scheduling code was added. Only this document was created.
- **No keys:** no key was generated, stored, derived, or invented; no key path was created.
- **No data access:** no production database, archive, or media was read or written.
- **No schema or migration change;** no tenant, subscription, billing, identity, or merchant export change.
- **No repository mutation** beyond the new document: no reset, discard, stash, commit, push, or branch operation.
- Existing uncommitted and untracked work is preserved.

**Status:** plan complete, awaiting operator review. Implementation of 5J/5K/5L must not start until the decisions in section 6 are answered and the blockers in section 7 are assigned.
