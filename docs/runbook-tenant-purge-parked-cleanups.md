# Runbook — Tenant Purge Cleanups Parked at `max_purge_attempts`

**Applies to:** Subscription Cancellation / Data Retention / tenant auto-deletion (Phase 6C).
**Audience:** Platform operations / SuperAdmin engineers.
**Contains no secrets.** Never paste API keys or credentials into tickets or logs.

> **Production status:** automatic purge is **OFF / NO-GO**. Both gates default OFF. This runbook is for investigation and controlled retry only — enabling gates requires change-control approval.

---

## 1. How a cleanup becomes "parked"

- Destructive purge runs only when **both** gates are ON:
  - `config('billing.tenant_cleanup.purge_enabled')` (env `TENANT_CLEANUP_PURGE_ENABLED`), and
  - PlatformSetting `tenant_auto_delete_enabled`.
- Commands: `tenants:process-cleanup` (lifecycle) and `tenants:purge {--dry-run} {--limit=} {--tenant=}` (purge; also scheduled weekly, no-op while gates are OFF).
- `attempts` on `tenant_cleanups` increments on:
  - a thrown exception during `process()`,
  - a **file-verification** failure (DB rows already purged, some files not verified deleted),
  - a **file-inventory collection** failure (aborts before any deletion).
- `billing.tenant_cleanup.max_purge_attempts` (env `TENANT_CLEANUP_MAX_PURGE_ATTEMPTS`, default **5**). When `attempts >= max`, `process()` **excludes** the cleanup from automatic retries and logs `Tenant purge parked for manual review after repeated failures`. It is not retried automatically.
- A direct `tenants:purge --tenant=<id>` bypasses the cap (operator-initiated).

---

## 2. Identify parked cleanups (read-only)

```sql
SELECT id, tenant_id, status, deletion_status, attempts, last_error,
       retention_ends_at, deleted_at, updated_at
FROM tenant_cleanups
WHERE status = 'pending_deletion'
  AND attempts >= 5            -- use your configured max_purge_attempts
ORDER BY attempts DESC, updated_at ASC;
```

All pending cleanups:

```sql
SELECT id, tenant_id, status, deletion_status, attempts, last_error
FROM tenant_cleanups
WHERE status = 'pending_deletion'
ORDER BY updated_at ASC;
```

---

## 3. Inspect errors and file inventory (read-only)

```sql
SELECT JSON_PRETTY(metadata) FROM tenant_cleanups WHERE tenant_id = <id>;
SELECT last_error FROM tenant_cleanups WHERE tenant_id = <id>;
```

Metadata keys and meaning:

| Key | Meaning | First action |
|-----|---------|--------------|
| `collection_error`, `collection_failed_at` | File inventory could not be collected; **no deletion happened** (aborted) | Fix DB/schema/disk access, then retry |
| `failed_files` | Files/URLs not verified deleted; DB rows **already purged** | Resolve the file source, then retry |
| `pending_files` | Full inventory still to verify; retry re-uses it | Retry |
| `cloudinary_skipped` | Cloudinary unconfigured; remote assets left in place | See §6 escalation |
| `skipped_tables` | Per-table delete errors (e.g., cross-tenant FK reference) | Investigate the referenced rows |
| `purged_at`, `purged_tables` | DB purge already committed | Informational |

Logs: search the application log for the tenant id and these messages:
- `Tenant purge parked for manual review after repeated failures.`
- `Tenant purge aborted before deletion: file inventory collection failed.`
- `Tenant purge deleted database records but some files remain unverified.`
- `Tenant Cloudinary deletion failed verification; will be retried.`
- `Tenant purge skipped a table.`

Expected file sources (used by the inventory collector): product `photo1/photo2/seo_image/gallery_images`, variant `image`, category `image`, brand `logo/banner`, promotion banner `image`, storefront media `path`, website info images, order `payment_screenshot/payment_proof`, import history `error_report_path`, and `import-errors/{tenant_id}` on the `local` disk.

---

## 4. Safe dry-run (always first)

Dry-runs are non-mutating and safe even with gates OFF:

```bash
php artisan tenants:purge --dry-run --tenant=<id>
```

Expect `Eligible: yes` or `Eligible: no (skipped)`. If **no**, check:
- tenant has an **active/trialing** subscription (purge is cancelled/refused),
- `retention_ends_at` is in the future,
- cleanup status is not `pending_deletion` / is already `deleted`.

---

## 5. Safely retry

Preconditions: the above dry-run returns `Eligible: yes`; gates are ON **only** under change-control (never on production without approval); audit records remain protected by design.

Single tenant (bypasses the cap):

```bash
php artisan tenants:purge --tenant=<id>
```

Re-enable automatic processing for a parked cleanup (this is a **data change** — only with approval):

```sql
UPDATE tenant_cleanups
SET attempts = 0
WHERE tenant_id = <id> AND status = 'pending_deletion';
```

Then re-run the single-tenant command (or wait for the scheduler).

Verify success:

```sql
SELECT status, deletion_status, attempts, deleted_at, metadata
FROM tenant_cleanups WHERE tenant_id = <id>;
SELECT status, locked_at FROM tenants WHERE id = <id>;
```

Expected: cleanup `status=deleted`, `deletion_status=deleted`, `deleted_at` set, metadata has `purged_at`/`purged_tables` and **no** `failed_files`/`pending_files`; tenant `status=deleted` with `locked_at` set.

If files still fail: retry **once**; the cap will re-park it. Then escalate (§6).

---

## 6. Escalate unresolved Cloudinary failures

**Trigger:** `metadata.failed_files` contains `res.cloudinary.com` URLs, or `metadata.cloudinary_skipped` is present (Cloudinary not configured).

Gather (no secrets):
- `public_id` (parse the URL with the application's Cloudinary public-id helper) and the expected `resource_type` (`image`/`video`/`raw`).
- Admin API `asset(public_id)` result before/after any attempt.
- The `failed_files` list and `last_error` value.

Escalate to platform engineering / the Cloudinary account owner. Resolution options:
- Correct the (non-production) Cloudinary credentials/config, or
- Delete the asset manually via the Cloudinary dashboard, then re-run `tenants:purge --tenant=<id>`.

**Do NOT:** mark a cleanup `deleted` while assets remain unverified; use production/shared credentials for testing; paste API keys into tickets.

Required evidence to close a Cloudinary verification (per Phase 6C-6 audit): a non-production Cloudinary cloud, one tenant-unique asset plus a shared asset, before/after `asset()` results (present → absent), the shared asset still present, purge metadata free of `cloudinary_skipped`/`failed_files`, cleanup finalized `deleted`, no `failed verification` log line, and a second run proving idempotency.

---

## 7. Safety rules

- Never enable either purge gate on production without approval — purge is destructive and irreversible.
- Never edit tenant data outside the documented retry path.
- Never paste secrets into logs or tickets.
- Protected records (subscriptions, invoices, payment records, audit logs) are preserved by design — do not delete them manually.
