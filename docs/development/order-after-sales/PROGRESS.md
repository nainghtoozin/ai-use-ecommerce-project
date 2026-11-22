# Order After-Sales System — Progress

Last updated: 2026-11-22

## Phase A — Inspection (complete)

- Confirmed existing after-sales implementation, 8 executed migrations, 14 modified
  and 41 untracked files at `HEAD ed9c0cf`; no commits made.
- Confirmed notification architecture: order notifications were database-only;
  mail existed for billing/platform/team; Telegram via `SendTelegramMessageJob`;
  Pusher via `BroadcastService`/`ShouldBroadcast`; preferences via
  `NotificationPreferenceService`.

## Phase B — Structured tracking (complete)

- [x] Additive migration `2026_11_22_000001_add_tracking_fields_to_order_returns_table`
      executed successfully on the dev database.
- [x] `OrderReturn`: fillable fields, `shipped_at` cast, `$hidden` raw URL,
      `sanitizeTrackingUrl`, `safe_tracking_url`, `hasTracking`, `trackingPayload`.
- [x] `OrderReturnService::markInTransit(array $tracking)` persists shipment data.
- [x] `OrderReturnService::updateTracking()` edits data without a status change.
- [x] `AdminReturnController`: `markInTransit` accepts tracking fields; new
      `updateTracking` action plus `trackingUrlRule()` validation.
- [x] Routes `returns.tracking` registered under `/admin/*` and
      `/store/{slug}/admin/*`.
- [x] Customer payload includes `tracking` (safe URL only).
- [x] Merchant UI: tracking card, in-transit form, update-tracking modal.
- [x] Customer UI: shipment tracking card on return detail.

## Phase C — Approval workflow (complete)

- [x] Cancellation request/approve/reject/finalize validated; duplicate approval
      blocked; decision notes recorded; stock restored once.
- [x] Return transitions validated against the existing map; row locks and
      transactions on every critical change; repeated transitions rejected.
- [x] Refund statuses distinguished; completion blocked for failed refunds;
      over-refund and unpaid-order guards retained.
- [x] Merchant actions gated by existing permissions (`returns.approve`,
      `returns.reject`, `returns.receive`, `returns.inspect`, `returns.settings`).

## Phase D — Notifications (complete)

- [x] `AfterSalesNotificationService` sends database notifications synchronously
      and queues external channels via `SendAfterSalesExternalNotificationJob`
      with `afterCommit()`.
- [x] Customer email (`AfterSalesStatusMail`) with HTML and text views, gated by
      `order_status_changed` preference.
- [x] Merchant Telegram via `SendTelegramMessageJob::dispatchForTenant`.
- [x] Pusher event `AfterSalesStatusUpdated` broadcast on the customer's private
      channel through `BroadcastService`.
- [x] Added customer notifications on receive, inspect, tracking update, refund
      processing, and refund failure; merchant notification on customer-initiated
      cancellation.
- [x] Merchant external notifications no longer depend on database admin rows
      existing, so Telegram still fires for configured tenants.

## Phase E — Refund, stock, reporting (verified)

- [x] Full/partial/multiple refunds; over-refund and unpaid-order rejection.
- [x] Payment status becomes `partially_refunded` or `refunded` based on completed
      refunds against paid amount.
- [x] Stock restoration idempotent via `StockMovementService` and per-item
      `stock_restored_at`; damaged items written off without restock.
- [x] Reports use completed `order_refunds` rows, not cancelled order totals.

## Phase F — UI/UX (complete for code)

- [x] Merchant pages use shared `AfterSalesUI` primitives, stat cards, status
      badges, filter bar, pagination, table-to-card mobile layouts, confirmation
      modals, validation messaging.
- [x] Customer pages use storefront tokens, step timeline, evidence preview,
      shipment tracking, friendly status copy, cancel confirmation.

## Phase G — Responsive (partially verified)

- [x] Layouts use mobile-first Tailwind with `sm`/`lg` breakpoints, contained
      horizontal scrolling for tables, and stacked grid layouts.
- [ ] Visual verification at 360/390/430/768/1024/1440 px in a real browser:
      NOT performed in this environment. See `TEST_REPORT.md`.

## Phase H — Focused tests (complete)

- [x] Tracking persistence, update without status change, unsafe URL rejection,
      raw URL hidden, update blocked after completion.
- [x] External notification job queueing per audience; recipient isolation;
      no notification on rejected transitions.
- [x] Cross-tenant tracking update 404; permission denial 403; customer sees own
      tracking only; unsafe URL validation error.
- [x] After-sales suite: 54 passed (185 assertions).

## Phase I — Full suite (executed)

- [x] Full suite executed twice. Final: 1722 passed, 287 failed, 7 skipped.
      Same 287 pre-existing failures as baseline; no after-sales failures.
      Results recorded in `TEST_REPORT.md`.

## Phase J — Documentation (complete)

- [x] `IMPLEMENTATION_PLAN.md`, `PROGRESS.md`, `DECISIONS.md`, `TEST_REPORT.md`.

## Known open items

1. Manual responsive verification in a browser at the six target viewports.
2. Production queue worker and Pusher/Telegram credentials must be configured by
   the operator; code paths degrade safely when absent.
3. Pre-existing full-suite failures unrelated to after-sales still need separate
   cleanup (documented, not fixed here).
