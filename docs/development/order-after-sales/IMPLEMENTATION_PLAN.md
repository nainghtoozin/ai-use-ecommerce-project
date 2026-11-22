# Order After-Sales System — Implementation Plan

Status: active development (testable release candidate)
Scope: order cancellations, returns, refunds, notifications, tracking, reporting.
Stack: Laravel 12, React 19, Inertia 3, Tailwind 3, MySQL.

This plan describes the architecture that already exists in the codebase and the finishing work applied on top of it. It is not a greenfield design.

## 1. Domain Model

| Table | Model | Purpose |
|-------|-------|---------|
| `cancellation_settings` | `CancellationSetting` | Per-tenant policy: enable flags, per-status mode, instant window, limits, refund shipping fee |
| `return_settings` | `ReturnSetting` | Per-tenant return window, resolutions, evidence rules, shipping responsibility |
| `order_refunds` | `OrderRefund` | Manual refund records with status lifecycle and audit fields |
| `order_cancellations` | `OrderCancellation` | Cancellation requests, decisions, stock/refund linkage |
| `order_returns` | `OrderReturn` | RMA header, resolution, decision, shipment tracking |
| `order_return_items` | `OrderReturnItem` | Per-line requested/received quantities, condition, restock flag |
| `after_sales_histories` | `AfterSalesHistory` | Polymorphic, customer-visible audit timeline |

All business models use `App\Models\Traits\TenantAware` (global `TenantScope`, auto `tenant_id` on create).

### Tracking columns (additive migration)

`2026_11_22_000001_add_tracking_fields_to_order_returns_table.php` adds to `order_returns`:

- `carrier_name` varchar(100) nullable
- `tracking_number` varchar(100) nullable (indexed with `tenant_id`)
- `tracking_url` varchar(500) nullable (http/https only, sanitized on write and read)
- `shipped_at` timestamp nullable
- `tracking_note` varchar(500) nullable

`tracking_url` is `$hidden` on the model; the safe value is exposed through `trackingPayload()` / `safe_tracking_url`.

## 2. State Machines

### Cancellation (`OrderCancellation`)

```
requested -> approved -> completed
requested -> rejected
requested -> completed          (instant mode)
completed is terminal; duplicate active requests are blocked
```

Decision paths use `OrderCancellationService::approve()` / `reject()`; both require the request to still be `requested` and run inside a transaction with a row lock. Instant cancellation runs `request()` -> `finalize()` directly per policy mode.

`finalize()` (single implementation):
1. Locks the order, transitions to `cancelled` through `OrderStatusTransitionService`.
2. Restores stock exactly once when `orders.stock_reduced` is true (`StockMovementService`, source `cancellation`).
3. Creates a pending refund for paid orders using the refundable balance minus shipping fees when the tenant policy excludes them.
4. Records history, dispatches `ProcessOrderStatusChange`, notifies the counterparty.

### Return (`OrderReturn`)

Transition map (`OrderReturn::canTransitionTo`):

```
requested          -> approved | rejected | cancelled | expired
approved           -> awaiting_shipment | in_transit | received | cancelled | expired
awaiting_shipment  -> in_transit | received | cancelled
in_transit         -> received | cancelled
received           -> inspection | completed | cancelled
inspection         -> completed
```

`approve()` moves `requested` -> `awaiting_shipment`. `markInTransit()` only
accepts `awaiting_shipment`. `updateTracking()` edits shipment fields without a
status change and is allowed in `approved`, `awaiting_shipment`, `in_transit`,
`received`, `inspection`.

`receive()` records per-line received quantities. `inspect()` sets condition
(`resellable`/`damaged`) and restock decision, restoring stock once via
`StockMovementService` (source `return`). `complete()` creates either a pending
refund (resolution `refund`) or a stock-checked replacement order (resolution
`exchange`/`replacement`).

### Refund (`OrderRefund`)

```
pending -> processing -> completed
pending -> completed
pending|processing -> failed
completed is terminal; failed refunds must be recreated
```

`create()` rejects unpaid orders, zero/negative amounts, and amounts above
`refundableBalance()` (paid amount minus reserved/completed refunds), so multiple
partial refunds are supported without over-refunding. `complete()` refuses to
advance a failed refund. `syncPaymentStatus()` sets `refunded` only when completed
refunds cover the paid amount, otherwise `partially_refunded`.

## 3. Permissions

Merchant/admin (`web` guard, Spatie):

- `cancellations.view`, `cancellations.manage`, `cancellations.approve`, `cancellations.reject`, `cancellations.settings`
- `returns.view`, `returns.approve`, `returns.reject`, `returns.receive`, `returns.inspect`, `returns.settings`
- `refunds.view`, `refunds.manage`, `refunds.process`

Customer:

- `returns.view-own`, `returns.create`

Tracking display/update for merchants reuses `returns.receive`; no new permission
was introduced, so no tenant role backfill is required. `SyncAfterSalesPermissions`
remains available for existing tenants.

Customer endpoints enforce `ensureReturnsAccess()`: when the identity has any RBAC
role, `returns.view-own` / `returns.create` is required; role-less legacy
identities fall back to order ownership.

## 4. Notifications

`AfterSalesNotificationService` is the single entry point:

- Database channel is synchronous (`AfterSalesStatusNotification`, `via(['database'])`).
- Email, Telegram, and Pusher are queued through
  `SendAfterSalesExternalNotificationJob` dispatched with `afterCommit()`.
- Customer email: order email plus `order_status_changed` preference check;
  skipped when the order has no linked identity.
- Merchant Telegram: `SendTelegramMessageJob::dispatchForTenant` when the tenant
  has Telegram configured; no-op otherwise.
- Pusher: `AfterSalesStatusUpdated` (`ShouldBroadcast`) fired through
  `BroadcastService` on the customer's private channel when a channel can be resolved.
- Failures are caught per channel and logged; the business transaction never fails
  because of an external notification.

Events covered: cancellation requested/approved/rejected/completed, return
requested/approved/rejected/in-transit/tracking-updated/received/inspected/completed,
refund initiated/processing/completed/failed.

Never sent to customers: `merchant_note`, internal inspection notes, payment
evidence internals. Customer timelines are filtered by `is_customer_visible`.

## 5. Reporting and Stock

- `AdminReportController` computes `refunded_amount` / `refunded_count` from
  `order_refunds` rows with status `completed` only. Cancelled order totals are not
  treated as refunded amounts.
- Stock restoration is idempotent through `StockMovementService` and
  `order_return_items.stock_restored_at`; repeated inspection/approval never
  double-restores.
- Flash-sale counters and combo/variant stock flow through the same service used by
  order placement.

## 6. UI

Merchant: `Admin/Cancellations/Index`, `Admin/Returns/Index`, `Admin/Returns/Show`
(includes shipment tracking card and update modal), `Admin/Refunds/Index`,
`Admin/Settings/CancellationPolicy`, `Admin/Settings/ReturnPolicy`,
`Admin/Orders/Show` via `Components/Admin/AfterSalesPanel`.

Customer: `Storefront/Returns`, `Storefront/ReturnShow` (timeline, evidence,
tracking), `Storefront/OrderShow` cancellation/return entry points.

Shared UI primitives: `Components/Admin/AfterSalesUI.jsx` (PageShell, Card,
StatCard, StatusBadge, FilterBar, EmptyState, BackLink).

## 7. Required Environment

- `QUEUE_CONNECTION=database` in production with a persistent worker on
  `default,broadcasts`; tests use `sync`.
- Mail configured for customer email; `MAIL_MAILER=array` in tests.
- `BROADCAST_CONNECTION=pusher` plus `PUSHER_APP_*` and matching
  `VITE_PUSHER_APP_*` for real-time delivery; frontend rebuild required after
  env changes.
- Telegram configured per tenant for merchant alerts.
- Tests: MySQL `ecommerc_test` via `phpunit.xml`; never point tests at the dev DB.

## 8. Verification Commands

```bash
php -l app/Services/OrderReturnService.php
php artisan test --filter=AfterSales
php artisan route:list --name=returns
npm run build
php artisan test
```

## 9. Remaining Work

See `PROGRESS.md` for the live checklist and `TEST_REPORT.md` for executed
results. Open items are limited to manual browser verification of responsive
viewports and production queue/broadcast configuration.
