# Sprint 4B-5D - Practical Backup and Restore Foundation

**Date:** 2026-10-24
**Scope:** Separate merchant business export from system backup, document a versioned archive contract, add restore preflight validation, and prove a bounded reconstruction path in an isolated disposable database.
**Out of scope:** Production restore endpoint or UI, media binary bundling, tenant overwrite/purge, retention scheduling, schema changes, subscription reactivation, and re-auditing the 48 export datasets.

## 1. Architecture Decision

Two distinct capabilities, deliberately not merged:

| | A. Merchant Business Export | B. System Backup Foundation |
|---|---|---|
| Purpose | Merchant obtains their own business data | Authorized operator recovers the platform |
| Scope | 48 tenant-scoped datasets + asset manifest | Database snapshot + application-owned media + versioned metadata |
| Authority | Merchant admin (`billing.view`) | Trusted operator only (no route exists yet) |
| Current state | Implemented and verified (Sprint 4B-5C) | Contract + preflight + isolated reconstruction verified; production path Planned |
| Substitution | Never a substitute for a database snapshot | Never exposed through merchant export |

The merchant export stays exactly as shipped in 4B-5C. No dataset was added, removed, or re-scoped. A merchant export is explicitly **not** a full-system backup: it excludes platform billing, subscriptions, invoices, payment intents/evidence, ledger entries, roles, permissions, staff identity, audit/activity logs, notifications, Telegram integrations, and admin records, and it does not embed media binaries.

## 2. Existing Functionality Reused

- `app/Services/Export/MerchantDataExportService.php` - archive generation, unique run id, temp cleanup, manifest, asset manifest (unchanged).
- `config/exports.php` - the 48 category contract and disk/path/TTL settings (unchanged).
- `app/Exports/MerchantDatasetExport.php`, `app/Http/Controllers/Admin/DataExportController.php`, routes - unchanged.
- `tests/Feature/MerchantDataExportTest.php` - 20 existing export tests, used as the regression baseline (unchanged).
- `phpunit.xml` MySQL test environment (`ecommerc_test`) - reused as the source database for the drill.
- Migration-created default tenant/storefront - acknowledged, not modified (see 4B-5C coverage notes).

No production code was modified in this phase.

## 3. Files Added or Changed

Added (all new, untracked before this phase):

| File | Role |
|---|---|
| `tests/Feature/MerchantExportRestoreDrillTest.php` | Gated (`RESTORE_DRILL=1`) isolated restore drill and its assertions |
| `tests/Support/MerchantRestoreDrill/ArchiveImporter.php` | Preflight validation, dependency-ordered insert, tenant remap, known-loss handling |
| `tests/Support/MerchantRestoreDrill/DrillFixture.php` | Synthetic two-tenant fixture, asset file, secret setting, expectations |
| `docs/sprint-4b.5d-practical-backup-restore-foundation.md` | This report |

Changed: none. `app/`, `config/`, `routes/`, `database/migrations/`, and all frontend files are untouched. No migration was created or edited.

## 4. Implemented versus Planned

| Item | Status | Evidence |
|---|---|---|
| Two backup types separated and documented | Implemented | Section 1 |
| Versioned archive/metadata contract documented | Implemented | Section 5 |
| Restore preflight validator (entry set, hashes, bytes) | Implemented | `ArchiveImporter::extractArchive` |
| Tenant-preserving reconstruction of representative subset | Implemented | `ArchiveImporter::import` |
| Isolated test-only restore path | Implemented, Verified | Section 8 |
| Archive integrity verified against manifest | Verified | Drill assertions |
| Record counts and relational integrity after restore | Verified | Drill assertions |
| Inventory reconstructed via signed stock-movement sums | Verified | `assertInventoryReconstruction` |
| Tenant isolation after restore | Verified | `assertTenantIsolation` |
| Media binary recovery | Planned (documented limitation) | `assertMediaIsNotRecovered` |
| Explicit numeric `schema_version`/`archive_version` gate | Planned | Importer validates structure, not version numbers |
| Production restore command/service/UI | Planned | Not started by design |
| System backup (DB snapshot + media bundle) | Planned | Requires separate operator-scoped design |

## 5. Archive Structure and Versioning Contract

Archive: single ZIP (`merchant store-data` naming, private local export disk).

Entries are exactly (no extras permitted by the importer):

- `products.csv` ... `import_histories.csv` - the 48 configured categories, one CSV each.
- `assets.json` - asset manifest.
- `manifest.json` - archive manifest.

`manifest.json` fields:

```
schema_version : 1
archive_version: 1
store          : { name, slug }
generated_at   : ISO 8601
app            : config('app.name')
categories     : { <category>: <row count>, ... }   // 48 entries
files          : { "<file>": { rows, bytes, sha256 }, ... }  // 48 CSVs + assets.json
asset_summary  : { total, present, missing, external, unverified, invalid }
notes          : 3 statement lines (media not embedded, formula-injection guard, tenant-scoped/exclusions)
```

`assets.json` fields: `schema_version: 1`, `generated_at`, `disk`, `bundled: false`, `note`, `summary`, `assets[]` with `type`, `id`, `field`, `path`, `status` (`present` / `missing` / `external` / `unverified` / `invalid`), size and SHA-256 where local.

Contract rules:

- `manifest.json` is **not** self-hashed; every other archive entry is covered by `files[].bytes` and `files[].sha256`.
- `assets.json` is hashed and byte-sized in `manifest.json`.
- Restore compatibility is currently enforced structurally: the ZIP entry set must equal the configured 48 categories plus `manifest.json` and `assets.json`, and every listed file must match its recorded byte size and SHA-256. A numeric version gate is Planned.
- Adding or renaming a category is a breaking archive change and must bump `archive_version`.

## 6. Restore Preflight Checks

`ArchiveImporter::extractArchive()` performs, in order:

1. Creates a random extraction directory under the system temp path.
2. Opens the ZIP and refuses it if it cannot be opened.
3. Compares the sorted entry list against the expected set derived from `config('exports.categories')` plus `manifest.json` and `assets.json`; any missing or extra entry fails.
4. Extracts and requires `manifest.json` to decode to an array and `assets.json` to be readable.
5. For every file listed in `manifest['files']`, requires existence, exact byte size, and matching SHA-256; the first mismatch aborts.

Database-side preflight in the drill:

1. Source guard: `app()->environment() === 'testing'`, driver `mysql`, database name `ecommerc_test`.
2. Target guard: `SHOW DATABASES` must not contain `ecommerc_restore_drill_4b5d`; the drill refuses to overwrite an existing database.
3. Target schema: migrate with `--database=restore_drill --force` and assert the `orders` table exists.
4. ID discipline: every restored primary key is checked free (`assertIdFree`) before insert, so no row is silently overwritten.

## 7. Restore Dependency Order (Representative Subset)

`ArchiveImporter::import()` inserts in FK-safe order:

1. Customers (`customers.csv` -> `users`, or `accounts` + `tenant_memberships` + `customer_profiles` when `identity.use_accounts` is enabled) + placeholder order identity.
2. Categories (parents first, then `parent_id` back-fill).
3. Brands, units, then products; then `product_variants`; then `product_combos`.
4. Delivery and payment data: cities -> townships -> delivery services -> delivery pricing -> warehouses -> packaging options -> payment methods -> COD rules.
5. Inventory movements (`stock_movements`).
6. Coupons and promotions plus their product/category pivots.
7. Flash sales plus `flash_sale_products`.
8. Orders -> order items -> order coupons -> order override logs -> promotion usages.
9. Customer relations: `customer_addresses`, `wishlists`.
10. Storefronts -> media -> contents -> design tokens -> homepage sections -> navigations -> navigation items.
11. Website info, settings, FAQs, storefront configs, promotion banners.
12. Import histories.

## 8. Test Environment and Isolation Evidence

| Item | Value |
|---|---|
| Source database | MySQL `ecommerc_test` (from `phpunit.xml`), wrapped in `DatabaseTransactions` |
| Target database | MySQL `ecommerc_restore_drill_4b5d`, created by the drill, migrated from scratch, dropped in `tearDown` |
| Execution gate | `RESTORE_DRILL=1`; otherwise `markTestSkipped` |
| Source guard | environment `testing`, driver `mysql`, database `ecommerc_test` |
| Target guard | existence asserted false before `CREATE DATABASE` |
| Cleanup | connection purged, `DROP DATABASE IF EXISTS`, archive deleted, extraction directory removed, fixture asset deleted |
| Media proof | fixture asset is deleted before restore; after restore the DB path exists but the file is asserted absent |

The target database never existed before the run and does not exist after it. No development, staging, or production database was written to.

## 9. Commands and Results

| Command | Result |
|---|---|
| `php artisan test tests/Feature/MerchantDataExportTest.php` | 20 passed, 163 assertions |
| `$env:RESTORE_DRILL = "1"; php artisan test tests/Feature/MerchantExportRestoreDrillTest.php` | 1 passed, 226 assertions (31.6s) |
| `php -l tests/Feature/MerchantExportRestoreDrillTest.php` | No syntax errors |
| `php -l tests/Support/MerchantRestoreDrill/ArchiveImporter.php` | No syntax errors |
| `php -l tests/Support/MerchantRestoreDrill/DrillFixture.php` | No syntax errors |
| `git diff --check` | Only the pre-existing CRLF warning in `tests/Feature/SubscriptionRenewalCancellationTest.php` |
| `npm run build` | Not run - no frontend file changed |
| Full test suite | Not run (per phase rules) |

Verified after restore: 48/48 category keys in the manifest with per-file hashes; row counts matching the manifest for all 48 categories; order/user/city/township/payment/delivery/packaging/promotion relationships; category parent chain; product -> brand/unit/category; variant -> product; combo -> both products; storefront draft/published revision; banner -> promotion and media; settings value preserved and `smtp_password` absent; inventory equality per product and per variant against the signed movement sum; zero cross-tenant rows across 43 tenant tables.

### Defects found by running the drill (fixed in the harness, not hidden)

1. **Inventory grouping skipped.** CSV round-trip turns SQL `NULL` into `""`, so `whereNull('variant_id')` matched nothing. Rows are now normalized (`'' -> null`) before grouping. This is a real CSV null-fidelity property, recorded as a limitation rather than changed in the export.
2. **Isolation false positive.** A freshly migrated target database contains the migration-created `default` tenant and its storefront row. The cross-tenant scan now excludes that seeded tenant id explicitly. The exclusion is asserted by slug, so an unexpected extra tenant still fails the test.

## 10. Known Limitations

- **Media binaries are not recoverable.** The archive carries paths and hashes only. After restore, media rows point at files that must be restored from separate storage.
- **Guest customer deduplication.** The customer export folds guest order aggregates into a matching registered customer's email; history can be omitted for the registered row. Requires a product decision on identity merging.
- **Global payment methods excluded.** `payment_methods` rows with a null tenant are not exported, even though fallback behavior may surface them to merchants.
- **Excluded platform data.** Billing, subscriptions, invoices, payment intents/evidence, ledger, roles, permissions, staff, audit/activity logs, notifications, Telegram, and admin records are not in the export and therefore not restorable from it.
- **Placeholder order identity.** Restored orders are attached to a generated placeholder identity, not the original customer, because customer credentials and memberships are not exported.
- **Dropped order linkage.** `order_coupon.promotion_id` is not exported and is restored as null; order payment screenshot/proof, idempotency key, and transaction id are absent by design.
- **`storefronts.theme_id` restored as null**, and storefront revision/config tables are restored from their own datasets only.
- **CSV null fidelity.** SQL `NULL` and empty string are indistinguishable after a CSV round-trip.
- **Version gate.** Structural validation only; `schema_version`/`archive_version` are not numerically enforced yet.
- **Secrets.** Sensitive setting keys are excluded and were verified absent after restore; a secret stored under an innocuous key would still be exported.
- **Scope of proof.** One synthetic tenant, one order, one variant, one combo. This is a bounded drill, not proof of full-system restore readiness.

## 11. Remaining Work Before Production

1. Operator-scoped system backup: consistent MySQL dump (or snapshot) plus application-owned media bundle, packaged with the same manifest/checksum discipline and `archive_version`.
2. Explicit numeric version and compatibility gate in the restore preflight, rejecting unsupported or newer archives.
3. Media binary bundling and verification (checksum on extract, path containment on write), or a documented restore-time media import step.
4. Production restore service restricted to a disposable/target database, with dry-run, explicit confirmation, and an audit record; no automatic tenant overwrite.
5. Design decision on restoring customers/identities and order ownership instead of a placeholder identity.
6. Documentation of retention/protected-tenant configuration and backup storage access control.
7. Restore rehearsal on a real staging-sized dataset before any production claim.

## 12. Next Recommended Phase

**Phase 4B-5E - System Backup Artifact Contract and Operator Preflight.** Define and implement the operator-scoped backup artifact (DB dump + media bundle + versioned manifest with checksums) and its preflight validator, reusing the checksum/entry-set discipline proven here. Keep restore execution test-only until that contract exists.

## Safety Confirmation

- No production, staging, or existing development data was restored into or overwritten. The only restore target was `ecommerc_restore_drill_4b5d`, created by the drill and dropped in `tearDown`; the source was the MySQL test database `ecommerc_test` under `DatabaseTransactions`.
- No existing assets were deleted or moved. Only the drill's own synthetic temp asset was created and removed.
- No schema migrations were introduced, edited, or run against any application database. The drill's `migrate` ran only against the disposable drill database.
- No tenant deletion, purge, anonymization, or retention workflow was executed or modified.
- `tenant_auto_delete_enabled` remains disabled. Note: no such key exists in `config/` or `.env`; the flag was only referenced by the 4B-5C report. No tenant auto-delete code path, scheduled command, or retention scheduler exists (`app/Console/Commands` contains no tenant purge command, and `routes/console.php` schedules no retention work). Retention eligibility remains read-only under `TenantRetentionEligibilityService`, and its protections (`retention.protected_tenant_ids`, `protected_tenant_slugs` defaulting to `default`, and the `retention_protected` setting) are unchanged.
- No commits, pushes, branch operations, resets, or discards were performed. Pre-existing uncommitted work was preserved.
