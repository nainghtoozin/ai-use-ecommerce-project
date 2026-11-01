# Sprint 4B-5C - Export Integrity and Security Review

**Date:** 2026-10-22
**Scope:** Merchant data export correctness, integrity, tenant isolation, authorization, sensitive-data handling, and archive operations.
**Out of scope:** Restore drills, purge/deletion workflows, retention scheduling, subscription lifecycle changes, schema changes, asset bundling, and destructive data operations.

## Summary

The existing export was reviewed against the 48 configured datasets, tenant model, archive generation, asset manifest, authorization middleware, and current tests. Four confirmed integrity or security defects were fixed with bounded changes:

1. Concurrent archive requests could reuse the same ZIP and CSV working paths.
2. Failed archive generation could leave partial archives and CSV files behind.
3. `assets.json` was included in the archive but was not covered by the manifest hash list.
4. Local asset verification did not reject files resolving outside the configured local disk root.

No migrations, schema changes, data mutations, asset deletions, retention changes, lifecycle changes, or destructive test setup were performed.

## Safety Guardrails

- No tenant deletion, purge, anonymization, or hard-delete workflow was added.
- No automatic retention or cleanup scheduler was added or changed.
- No subscription lifecycle behavior was changed.
- No business data or assets were deleted or moved.
- No database was refreshed or reset.
- No restore drill was performed.
- No dedicated export permission or export-audit table was introduced.
- No commit, push, branch operation, reset, or discard was performed.
- `tenant_auto_delete_enabled` and retention eligibility behavior were left unchanged.

## Inventory Reviewed

- `app/Services/Export/MerchantDataExportService.php`
- `app/Http/Controllers/Admin/DataExportController.php`
- `app/Exports/MerchantDatasetExport.php`
- `config/exports.php`
- `routes/web.php`
- `routes/storefront-admin.php`
- `tests/Feature/MerchantDataExportTest.php`
- `app/Auth/IdentityResolver.php`
- `app/Services/StockCalculationService.php`
- `app/Services/StockMovementService.php`
- `app/Models/StockMovement.php`
- `app/Models/Order.php`, `app/Models/OrderItem.php`, `app/Models/OrderCoupon.php`
- `app/Models/Account.php`, `app/Models/TenantMembership.php`, `app/Models/CustomerProfile.php`
- Relevant migrations for orders, order items, stock movements, tenant memberships, settings, storefront media, and delivery pricing.

## 48-Dataset Coverage Matrix

| # | Category | Source | Tenant basis |
|---|----------|--------|--------------|
| 1 | products | `products` | direct `tenant_id` |
| 2 | product_variants | `product_variants` | direct `tenant_id` |
| 3 | product_combos | `product_combos` | direct `tenant_id` |
| 4 | inventory_levels | `stock_movements` aggregate | direct `tenant_id` |
| 5 | inventory_movements | `stock_movements` | direct `tenant_id` |
| 6 | orders | `orders` | direct `tenant_id` |
| 7 | order_items | `order_items` | direct `tenant_id` |
| 8 | order_coupons | `order_coupon` | direct `tenant_id` |
| 9 | order_override_logs | `order_override_logs` | direct `tenant_id` |
| 10 | customers | `accounts`/`users`, memberships, orders | tenant membership or direct `tenant_id` |
| 11 | customer_addresses | `customer_addresses` | direct `tenant_id` |
| 12 | wishlists | `wishlists` | direct `tenant_id` |
| 13 | categories | `categories` | direct `tenant_id` |
| 14 | brands | `brands` | direct `tenant_id` |
| 15 | units | `units` | direct `tenant_id` |
| 16 | coupons | `coupons` | direct `tenant_id` |
| 17 | coupon_products | `coupon_product` join | explicit tenant filters on pivot, coupon, product |
| 18 | coupon_categories | `coupon_category` join | explicit tenant filters on pivot, coupon, category |
| 19 | promotions | `promotions` | direct `tenant_id` |
| 20 | promotion_products | `promotion_product` join | explicit tenant filters on pivot, promotion, product |
| 21 | promotion_categories | `promotion_category` join | explicit tenant filters on pivot, promotion, category |
| 22 | promotion_usages | `promotion_usages` | direct `tenant_id` |
| 23 | promotion_banners | `promotion_banners` | direct `tenant_id` |
| 24 | flash_sales | `flash_sales` | direct `tenant_id` |
| 25 | flash_sale_products | `flash_sale_products` join | parent `flash_sales.tenant_id` and product tenant |
| 26 | delivery_services | `delivery_services` | direct `tenant_id` |
| 27 | delivery_pricing | `delivery_pricing` join | parent `delivery_services.tenant_id` |
| 28 | cod_rules | `cod_rules` | direct `tenant_id` |
| 29 | packaging_options | `packaging_options` | direct `tenant_id` |
| 30 | warehouses | `warehouses` | direct `tenant_id` |
| 31 | payment_methods | `payment_methods` | direct `tenant_id` |
| 32 | cities | `cities` | direct `tenant_id` |
| 33 | townships | `townships` | direct `tenant_id` |
| 34 | storefronts | `storefronts` | direct `tenant_id` |
| 35 | storefront_contents | `storefront_contents` | direct `tenant_id` |
| 36 | storefront_design_tokens | `storefront_design_tokens` | direct `tenant_id` |
| 37 | storefront_homepage_sections | `storefront_homepage_sections` | direct `tenant_id` |
| 38 | storefront_media | `storefront_media` | direct `tenant_id` |
| 39 | storefront_navigations | `storefront_navigations` | direct `tenant_id` |
| 40 | storefront_navigation_items | `storefront_navigation_items` | direct `tenant_id` |
| 41 | storefront_revisions | `storefront_revisions` | direct `tenant_id` |
| 42 | storefront_theme_configs | `storefront_theme_configs` | direct `tenant_id` |
| 43 | storefront_checkout_configs | `storefront_checkout_configs` | direct `tenant_id` |
| 44 | storefront_product_display_configs | `storefront_product_display_configs` | direct `tenant_id` |
| 45 | website_faqs | `website_faqs` | direct `tenant_id` |
| 46 | store_settings | `website_infos` | direct `tenant_id` |
| 47 | settings | `settings` | direct `tenant_id` |
| 48 | import_histories | `import_histories` | direct `tenant_id` |

### Coverage Limitations

- `inventories` contains only `id`, `tenant_id`, and timestamps, so it is not a material dataset. Stock is exported through `inventory_levels` and `inventory_movements`.
- `themes` are global reference data. Only theme name/slug is referenced through storefronts.
- `payment_methods` with a null tenant are global fallback records and are not exported by the tenant-scoped query.
- Customer profiles are folded into `customers`; staff and role records are not exported.
- Order payment screenshots, payment proofs, payer name, sender account number, transaction ID, and payment note are intentionally not included in the order header export.

## Tenant Isolation Review

- All 48 dataset queries either filter `tenant_id` directly or join through a tenant-owned parent with explicit parent, child, and referenced-entity tenant filters.
- `flash_sale_products` and `delivery_pricing` have no direct `tenant_id`; both are constrained through their tenant-owned parent records.
- Related model loads use Eloquent relationships while the current tenant is set by the export request. A cross-tenant foreign-key anomaly therefore resolves to a null related value rather than another tenant's record during normal controller execution.
- Customer export scopes accounts through `tenant_memberships.tenant_id` and legacy users through `users.tenant_id`.
- Export tests verify product and settings isolation between two tenants, including sensitive-key exclusion.
- Raw join datasets use explicit tenant predicates on both pivot and referenced tables.

## Authorization and Lifecycle Review

- Six routes are registered: three under `/admin/data-export` and three under `/store/{store_slug}/admin/data-export`.
- Both prefixes require `billing.view`.
- Legacy `/admin/data-export` routes now include `tenant.access`; storefront routes inherit `tenant.access` from the storefront admin group.
- Export routes intentionally remain outside `tenant.active`, so they stay accessible during expiry, grace, suspension, and scheduled cancellation, consistent with account-level billing access.
- SuperAdmin behavior remains governed by the existing authorization layer. No new bypass or permission was added.
- No dedicated `data.export` permission was created because that would require a migration and approval.

## Sensitive and Platform Data Review

- Platform billing, subscriptions, invoices, payment intents, payment evidence, ledger entries, webhook logs, receipts, audit logs, activity logs, roles, permissions, staff identity, notifications, Telegram integrations, and platform administration records are not exported.
- `settings` excludes keys matching password, secret, token, API-key, credential, or private-key patterns.
- Store profile settings are curated through `WebsiteInfo` instead of exporting every column.
- `payment_methods` includes merchant-owned bank account name, account number, bank, branch, instructions, and QR path. These are merchant receiving details, not platform credentials.
- `import_histories` can include merchant-supplied error and warning payloads and report paths; these are merchant-owned operational records.
- Setting values under non-sensitive keys are not content-scanned. A secret stored under an innocuous key would still be exported. This remains a residual risk rather than a confirmed defect.
- Export request logs contain tenant ID, user ID, category, and format; they do not contain exported values or credentials.

## Archive and Asset Integrity

- Archives are generated under the private local export disk, not the public web root.
- Each archive run now uses a random run identifier for its ZIP and CSV working files, preventing same-second concurrent requests from sharing paths.
- Temporary CSV files and partial ZIPs are removed if generation throws.
- `manifest.json` includes schema/archive versions, tenant name/slug, generation timestamp, category row counts, per-CSV bytes and SHA-256, asset summary, and notes.
- `assets.json` is now hashed and byte-sized in `manifest.json`.
- `manifest.json` itself is not hashed because it cannot contain its own final hash.
- `assets.json` verifies local assets with size and SHA-256 and marks paths as `present`, `missing`, `external`, `unverified`, or `invalid`.
- Local asset paths are rejected when they are absolute, contain traversal, use a drive letter or scheme, or resolve outside the configured local disk root.
- Media binaries remain intentionally unbundled. External and non-local assets remain unverified.
- CSV and XLSX values are protected against spreadsheet formula injection; JSON remains structured data rather than a spreadsheet format.

## Defects Fixed

| Defect | Impact | Fix |
|--------|--------|-----|
| Archive path collision | Concurrent requests in the same second could overwrite or delete each other's ZIP and CSV files. | Random run identifier added to working paths in `MerchantDataExportService::downloadArchive`. |
| Partial archive cleanup | Exceptions could leave partial ZIPs and CSV files containing tenant data behind. | Generation wrapped in `try/catch`; temporary CSVs and partial ZIP are removed on failure; CSV handles are closed with `finally`. |
| Unhashed asset manifest | `manifest.json` did not allow verification of `assets.json`. | `assets.json` hash and byte size added to `manifest.json`. |
| Symlink escape in asset verification | A local path resolving outside the disk root could be hashed or sized. | `realpath` containment check added before size and hash calculation. |

## Tests Added or Updated

- Archive asset test now verifies the `assets.json` SHA-256 and byte size recorded in `manifest.json`.
- New test verifies external and traversal-invalid asset paths are reported explicitly.
- New test verifies two archive requests use distinct working paths.
- New test verifies sensitive and cross-tenant settings are excluded.
- New test verifies `inventory_levels` matches the signed sum of stock movements.
- Existing tests continue to cover CSV scoping, JSON scoping, XLSX streaming, formula injection, full archive generation, all-category execution, both route prefixes, authorization, unknown categories, unsupported formats, customer-without-orders inclusion, and stale archive cleanup.

## Verification

- `php artisan test tests/Feature/MerchantDataExportTest.php`: **20 passed, 163 assertions**.
- `php artisan route:list --name=data-export`: **6 routes** across both prefixes.
- `php artisan tinker --execute="dump(count(config('exports.categories')));"`: **48 categories**.
- `php -l app/Services/Export/MerchantDataExportService.php`: passed.
- `php -l tests/Feature/MerchantDataExportTest.php`: passed.
- `git diff --check`: only the pre-existing CRLF warning in `tests/Feature/SubscriptionRenewalCancellationTest.php`.
- `npm run build` was not run because no frontend files were changed.
- Full project test suite was not run to avoid unrelated pre-existing test failures and unnecessary database load.

## Remaining Risks and Follow-Ups

- Restore drill has not been performed. Archive presence and hashes are not proof of restore readiness.
- Media binaries are not included; external and non-local assets are not integrity-verified.
- No dedicated export permission, persistent export-audit table, rate limit, or asynchronous export queue exists.
- Large archives can still hit PHP request, memory, or execution-time limits.
- Guest order aggregates matching a registered customer's email are deduplicated in the customer export. This avoids duplicate customer rows but may omit historical guest-order aggregates from the registered row. Changing this requires a product decision on customer identity merging.
- Global payment methods with a null tenant are excluded even though they may be visible to merchants through fallback behavior.
- Sensitive values stored under non-sensitive setting keys are not content-scanned.
- The review was code and schema based; it did not exhaustively scan production data for cross-tenant foreign-key anomalies.
- Symlink containment was code-reviewed but not exercised on Windows because creating test symlinks may require elevated privileges.

## Verdicts

- **Query-complete only:** Yes. All 48 configured datasets execute, but this does not prove every business rule or historical edge case.
- **Integrity-verified:** Yes for generated archive structure, category counts, CSV and asset-manifest hashes, local asset hashes, and unique working paths. Manifest self-hashing is intentionally absent.
- **Tenant-isolation-verified:** Yes for the reviewed queries and focused two-tenant tests. No exhaustive production data anomaly scan was performed.
- **Ready for separately planned restore drill:** Yes as a separate future activity. Restore readiness itself remains unverified.
