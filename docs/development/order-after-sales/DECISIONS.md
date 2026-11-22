# Order After-Sales System — Decisions

Architecture decisions for the finishing work. Each entry states the constraint
and the chosen approach so a future agent does not redesign it.

## D1. Tracking lives on `order_returns`, not history notes

The legacy merchant reference was stored in `after_sales_histories.note`. That is
not queryable or editable. Structured columns (`carrier_name`, `tracking_number`,
`tracking_url`, `shipped_at`, `tracking_note`) were added through a new additive
migration. Existing history notes are untouched and remain readable.

## D2. Tracking URL is sanitized on write and read

`OrderReturn::sanitizeTrackingUrl()` accepts only `http://` and `https://` URLs
that pass `FILTER_VALIDATE_URL`. The controller applies the same check as a
validation rule, and `trackingPayload()` re-sanitizes. The raw column is `$hidden`
so an unsafe stored value can never leak through default serialization.

## D3. Tracking edits do not create shipment transitions

`markInTransit()` performs `awaiting_shipment -> in_transit` and stamps
`shipped_at`. `updateTracking()` only edits fields and records a
`return_tracking_updated` history entry with no status change. This prevents
duplicate shipment transitions when a merchant corrects a tracking number.

## D4. Tracking permission reuses `returns.receive`

No new permission was added, so no tenant role backfill is needed. `returns.receive`
already represents physical shipment handling; tracking edits are the same trust
level. Customer endpoints continue to require ownership plus `returns.view-own`
when an RBAC role exists.

## D5. Database notifications stay synchronous

Existing after-sales tests assert `Notification::assertSentTo` without queue
processing, and the app treats the in-app bell as authoritative state. Keeping the
database channel synchronous preserves that contract. Only external channels are
queued.

## D6. External channels go through one job

`SendAfterSalesExternalNotificationJob` handles email, Telegram, and broadcast in
one place, dispatched with `->afterCommit()` from the service. This guarantees no
external send before the transaction commits, gives the queue worker a single
retry/timeout policy (`tries=3`, `timeout=60`, backoff `10/30/60`), and keeps
failure handling (`failed()` logging) uniform.

## D7. Customer email requires a linked identity and preference

Email is sent only when the order has an email and a linked user/account, and the
identity has not disabled `order_status_changed`. Legacy guest orders are skipped
safely rather than guessed at.

## D8. Telegram is merchant-only

Customers have no Telegram destination in this platform. `notifyMerchants` queues
Telegram for the tenant; if no chat is configured, `SendTelegramMessageJob` is a
safe no-op. Merchant external notification no longer short-circuits when no
database admin rows resolve, because Telegram configuration is tenant-level.

## D9. Pusher uses the existing broadcast stack

`AfterSalesStatusUpdated` implements `ShouldBroadcast`, resolves the customer
private channel via `IdentityResolver::notificationChannelForOrderUser`, and is
fired through `BroadcastService` so existing dedup/throttle behaviour applies.
When no channel resolves (guest order), the event broadcasts on no channels.

## D10. Refund completion remains explicit

A refund is never completed as a side effect of creation. `create()` yields a
pending record; `complete()` requires an authorized action and writes reference,
notes, processor, and timestamp. `complete()` refuses failed refunds, and
`fail()` refuses completed ones.

## D11. Source-linked refunds do not emit duplicate notifications

When a refund is created as part of a cancellation or return, the parent flow
already notifies the customer with the refund context. `create()` therefore emits
"Refund initiated" / "Refund requires processing" only for standalone refunds
(`source === null`), avoiding double delivery for the same event.

## D12. Payment status reflects completed refunds only

`syncPaymentStatus()` compares completed refund totals against the effective paid
amount. Pending or failed refunds never change `payment_status`, so a partial
refund cannot masquerade as fully refunded.

## D13. Stock restoration is delegated to `StockMovementService`

Cancellation and return inspection both restore through the existing service and
guard with `orders.stock_reduced` and `order_return_items.stock_restored_at`.
Repeated calls are no-ops. Damaged items are written off without restock.

## D14. Reports read refund records

`AdminReportController` aggregates `order_refunds` with status `completed` for
refunded amount/count. Cancelled order totals are never reported as refunds.

## D15. Additive-only database changes

Only one new migration was created. The eight original after-sales migrations were
not edited. No destructive database or git command was used.
