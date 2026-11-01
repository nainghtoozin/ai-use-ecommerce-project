# Sprint 4B-5B — Merchant Data Export Readiness

**Date:** 2026-10-22  
**Scope:** Merchant-owned data export coverage, tenant isolation, asset manifest verification, and export security.  
**Out of scope:** Restore drills, purge/deletion, retention scheduling, subscription lifecycle changes, schema changes, and destructive data operations.

## Summary

The existing export covered 14 curated datasets and listed asset storage paths without verification. Sprint 4B-5B expands this to 48 tenant-scoped datasets, adds customers without orders, adds computed inventory levels, expands storefront/content/configuration coverage, and replaces the asset path list with a verified manifest.

No database migrations, schema changes, data mutations, asset deletions, or retention/lifecycle behavior changes were made.

## Coverage Matrix

### Complete

- Products, variants, combos, inventory levels, inventory movements
- Orders, order items, order coupons, order override history
- Customers, customer addresses, wishlists
- Categories, brands, units
- Coupons and coupon product/category links
- Promotions, promotion product/category links, promotion usages, promotion banners
- Flash sales and flash sale products
- Delivery services, delivery pricing, COD rules, packaging options, warehouses
- Payment methods, cities, townships
- Storefronts, content, design tokens, homepage sections, media, navigation, navigation items, revisions, theme config, checkout config, product display config
- Website FAQs, store profile/settings, additional non-sensitive settings, import history

### Partial

- `inventories` table has only `id`, `tenant_id`, and timestamps. Current stock is exported through computed `inventory_levels` and `inventory_movements`.
- Customer profiles are folded into the `customers` dataset rather than exported as a separate identity dataset.
- Themes are global reference data; only theme name/slug is referenced through storefronts.
- Store settings are curated and exclude sensitive setting keys.

### Platform-only / Protected

- Subscriptions, subscription audit/payment/cancellation records
- Invoices, payment intents, payment transactions, payment evidence/reviews/timeline/comments
- Ledger entries, webhook logs, billing payment methods, platform receipts
- Roles, permissions, tenant memberships, staff identity records
- Activity logs, notifications, messages, Telegram integrations, social accounts
- Tenant cleanup records and platform administration tables

### Requires Separate Approval

- Exporting platform billing records to merchants
- Exporting identity, staff, audit, security, or communication records
- Adding a dedicated export permission and persistent export-audit storage
- Bundling large local media binaries or fetching remote Cloudinary assets

## Tenant Isolation and Security

- All business datasets use tenant-scoped queries with explicit `tenant_id` filters.
- Datasets without direct `tenant_id` (`flash_sale_products`, `delivery_pricing`) are scoped through their tenant-owned parent records.
- Legacy `/admin/data-export` routes now include `tenant.access`; storefront routes already included it.
- Exports remain gated by `billing.view`.
- Export requests are logged with tenant, user, category, and format metadata.
- Sensitive setting keys matching password/secret/token/API-key/credential patterns are excluded.
- No passwords, session data, API keys, Telegram credentials, or payment gateway credentials are exported.
- Archives remain under the private local export disk with delayed deletion and stale-file cleanup.
- Asset paths are normalized and rejected when absolute, traversal-based, or non-local schemes are used.

## Asset Manifest

`assets.json` now includes:

- Schema version, generation timestamp, disk, and bundle status
- Summary counts for present, missing, external, unverified, and invalid assets
- Per-asset type, record ID, field, path, status, disk, size, and SHA-256

Verified sources include product and variant images, SEO images, brand logos/banners, category images, promotion banners, payment QR images, website branding/hero/footer images, and storefront media.

Binaries are not embedded. Local files are verified; remote or non-local files are explicitly marked `external` or `unverified`. Missing and invalid paths are explicit rather than silently omitted.

## Archive Manifest

`manifest.json` now includes:

- Schema and archive versions
- Tenant name/slug and generation timestamp
- Category row counts
- Per-file row counts, byte sizes, and SHA-256
- Asset summary
- Explicit notes about excluded platform records, formula-injection protection, and asset verification

## Verification

- `php -l` passed for all modified PHP files.
- `php artisan test tests/Feature/MerchantDataExportTest.php`: 16 passed, 145 assertions.
- Read-only smoke run over all 48 datasets completed without query errors.
- `npm run build` completed successfully.
- `git diff --check` reported only a pre-existing CRLF warning in `tests/Feature/SubscriptionRenewalCancellationTest.php`.
- Full project test suite was not run.

## Remaining Follow-ups

- Add a dedicated export permission and persistent export-audit table through an approved migration.
- Decide whether platform billing documents should be exportable to merchants.
- Decide whether customer messages and other communication records belong in merchant export.
- Consider optional local-media bundling if archive size and request time limits are acceptable.
- Add restore drill and deletion workflow only in a later approved phase.
