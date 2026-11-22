# Order After-Sales System — Test Report

Date: 2026-11-22
Environment: Windows, PHP 8.2, Laravel 12, PHPUnit 11
Test database: MySQL `ecommerc_test` via `phpunit.xml` (`DB_CONNECTION=mysql`)
Queue: `sync`; Mail: `array`; Broadcasting: default (no Pusher credentials in test)
Identity flag from `.env`: `IDENTITY_USE_ACCOUNTS=true` (tests that resolve User
admins set `identity.use_accounts=false` explicitly)

No `migrate:fresh`, `db:wipe`, `migrate:rollback`, or Git destructive command was
used. `RefreshDatabase` performs its normal per-run migration inside the test
database only.

## 1. Syntax and Build

| Check | Command | Result |
|-------|---------|--------|
| PHP syntax (all touched files) | `php -l <file>` | PASS |
| Frontend build | `npm run build` | PASS (built in ~13s, no errors) |
| Routes | `php artisan route:list --name=returns` | PASS: 25 routes, both `/admin/*` and `/store/{slug}/admin/*`, plus `returns.tracking` |

Files linted include: `OrderReturn`, `OrderReturnService`, `OrderRefundService`,
`OrderCancellationService`, `AfterSalesNotificationService`,
`AdminReturnController`, `StorefrontCustomerController`, `AfterSalesStatusMail`,
`SendAfterSalesExternalNotificationJob`, `AfterSalesStatusUpdated`, both route
files, the tracking migration, and both after-sales test classes.

## 2. After-Sales Suite

Command: `php artisan test --filter=AfterSales`

Result: **54 passed, 0 failed (185 assertions)**, duration ~42s.

Covers `OrderCancellationAfterSalesTest`, `OrderReturnRefundAfterSalesTest`,
`AfterSalesSecurityTest`.

Key assertions:

- Cancellation: instant vs approval modes, policy limits, delivered-order
  redirect, duplicate request block, stock restore once, refund without shipping
  fees, merchant notification on customer cancellation.
- Return: eligibility window, full/partial flows, write-off without restock,
  duplicate open return block, invalid/repeated transitions, tracking persistence,
  tracking update without status change, unsafe URL rejection, raw URL hidden,
  update blocked after completion.
- Refund: unpaid rejection, over-refund guard, partial refund status transitions,
  processing/failure notifications.
- Security: cross-tenant 404s, permission 403s, owner-only customer access,
  `returns.view-own` enforcement, customer sees own tracking only, unsafe tracking
  URL validation error.

## 3. Relevant Regression Suites

| Suite set | Command | Result |
|-----------|---------|--------|
| Inventory + notifications | `php artisan test tests/Feature/InventoryDashboardTest.php tests/Feature/OrderStatusNotificationTest.php tests/Feature/OrderBroadcastDedupTest.php tests/Feature/NotificationPreferenceEnforcementTest.php tests/Feature/NotificationTenantContextTest.php tests/Feature/NotificationReadTest.php` | 25 passed (65 assertions) |
| Cart/checkout/storefront order + manual payment | `php artisan test tests/Feature/StorefrontCartCheckoutTest.php tests/Feature/StorefrontOrderCustomerTest.php tests/Feature/CheckoutIntegrationTest.php tests/Feature/ManualPaymentServiceTest.php` | 54 passed, 12 failed (pre-existing `ManualPaymentServiceTest` schema isolation failures: "Table 'tenants' already exists" in `createMinimalSchema`, unrelated to after-sales) |

`StorefrontOrderCustomerTest` and `CheckoutIntegrationTest` pass, confirming the
order/payment paths used by cancellations and returns are unaffected.

## 4. Full Suite

Command: `php artisan test`
Log: `storage/logs/after-sales-final-suite.log`

Result: **1722 passed, 287 failed, 7 skipped (7338 assertions)**.
Run 1 (before the last three tests and refund/cancel transaction hardening):
1719 passed, 287 failed, 7 skipped. Failure count is unchanged across both runs;
the delta is the added after-sales tests. After-sales suites are fully green in
both runs.

### Failure categories (all pre-existing, unrelated to after-sales)

The 287 failures are concentrated in suites that do not touch after-sales code.
Representative groups from the run log:

| Count | Suite | Nature |
|-------|-------|--------|
| 30 | `CodFulfillmentTest` | obsolete COD fixtures / missing rows |
| 14 | `TrialLifecycleTest` | subscription trial fixture drift |
| 12 | `StorefrontMediaMerchandisingTest`, `StorefrontProductDetailTest` | media URL/schema drift |
| 12 | `OtherLocationCheckoutTest`, `ManualPaymentServiceTest` | checkout/location schema drift, test schema isolation |
| 11 | `CodWithoutPaymentRowTest`, `MarketingFeatureTest`, `UserManagementTest` | legacy fixture drift |
| 10 | `PromotionReportTest`, `StorefrontCustomerTest` | report/schema drift; test inserts `orders.city` which no longer exists |
| 9 | `PaymentMethodOnboardingTest`, `StorefrontHomepageTest`, `PromotionApiTest` | legacy expectations |
| 8 | `CodAvailabilityModeTest`, `RoleManagementTest` | fixture drift |
| rest | Auth, Storefront*, Cod*, CategoryV2, Settings, Customer*, BuyNow, etc. | individual assertions |

No `AfterSales` test appears in the failure list. Baseline before this finishing
work was 1699 passed / 287 failed / 7 skipped; the failure count did not increase.

Example pre-existing failure root causes observed:

- `StorefrontCustomerTest`: `SQLSTATE[42S22] Unknown column 'city' in 'field list'`
  from the test's own order insert.
- `ManualPaymentServiceTest`: `SQLSTATE[42S01] Table 'tenants' already exists`
  from `createMinimalSchema()` running against an already-migrated test database.
- Multiple storefront tests expect legacy markup that the current build no longer
  renders.

These are outside the after-sales scope and were not modified.

## 5. Skipped Tests

7 skipped in the full suite, same as the pre-change baseline (environment-gated
tests, unchanged by this work).

## 6. Visual / Responsive Verification

Not performed in a real browser in this environment. The layouts were written with
mobile-first Tailwind and verified only by successful compilation:

- Tables switch to card lists at `< sm`.
- Wide tables use contained `overflow-x-auto`.
- Modal panels are bottom sheets on mobile and centered dialogs at `sm+`, with
  `max-h-[90vh] overflow-y-auto`.
- Grid layouts collapse from 3 columns to 1 at `lg`.

Viewports requiring manual visual confirmation: 360, 390, 430, 768, 1024, 1440 px.

## 7. Not Verified Automatically

- Real Pusher delivery (no credentials; event construction is covered indirectly).
- Real Telegram delivery (no tenant chat configured in tests; dispatch path is safe
  no-op).
- Real SMTP delivery (`MAIL_MAILER=array`); mail rendering is exercised only if an
  email test is run with a configured mailer.
- Production queue worker behavior (tests use `sync`).

## 8. Verdict

Implementation is a testable release candidate. After-sales functionality and its
security/notification tests pass. The full suite still contains 287 pre-existing
failures outside after-sales scope, so the repository as a whole is not "all green"
and this report does not claim otherwise.
