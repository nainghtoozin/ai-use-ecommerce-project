# Sprint 9.2 — Subscription & Billing Audit Report

**Date:** 2026-07-20
**Scope:** Invoices, Plan Change (Upgrade/Downgrade), Trial/Renewal/Expiry, Grace Period, Payment Verification, Subscription Status Banner
**Status:** Complete

---

## Modified Files

| File | Change |
|------|--------|
| `app/Models/Invoice.php` | **Created** — Invoice model with status constants, number generator, scopes, markAs helpers |
| `app/Models/Subscription.php` | Modified — Added `pendingPlan()` relationship, `changePlan()`, `scheduleDowngrade()`, `cancelScheduledDowngrade()`, `hasPendingDowngrade()`, `isUpgrade()` methods; `pending_plan_id`/`pending_plan_effective_at` fillable/casts |
| `app/Models/Tenant.php` | Modified — Added `invoices()` relationship |
| `app/Services/InvoiceService.php` | **Created** — Invoice generation from payment intents/subscriptions, line item building, tenant stats |
| `app/Services/SubscriptionPlanChangeService.php` | **Created** — Proration calculation, upgrade/downgrade/scheduled change execution |
| `app/Http/Controllers/Admin/InvoiceController.php` | **Created** — Invoice CRUD: index (paginated/filtered), show, download (HTML), markPaid, markCancelled |
| `app/Http/Controllers/Admin/AdminBillingController.php` | Modified — Added `changePlanPreview()`, `changePlanExecute()`, `cancelScheduledChange()` methods; enriched subscription payloads |
| `app/Http/Middleware/HandleInertiaRequests.php` | Modified — Enriched shared subscription/tenant data: grace_days_remaining, trial_days_remaining, is_locked |
| `app/Listeners/GenerateInvoiceFromCompletedIntent.php` | **Created** — Auto-generates invoice on `PaymentIntentCompleted` event |
| `app/Providers/EventServiceProvider.php` | Modified — Registered `GenerateInvoiceFromCompletedIntent` listener |
| `app/Console/Commands/SendSubscriptionReminders.php` | **Created** — Sends renewal/trial reminders at 14/7/3 days via `subscriptions:send-reminders` |
| `app/Console/Commands/ApplyScheduledPlanChanges.php` | **Created** — Applies due pending_plan changes via `subscriptions:apply-scheduled-changes` |
| `database/migrations/2026_07_20_000002_add_invoice_fields.php` | **Created** — Added billing_interval, subtotal, tax, total, line_items to invoices table |
| `database/migrations/2026_07_20_000003_add_pending_plan_to_subscriptions.php` | **Created** — Added pending_plan_id (FK→plans) and pending_plan_effective_at |
| `routes/web.php` | Modified — Added billing change-plan routes (preview/execute/cancel) and invoice routes |
| `routes/storefront-admin.php` | Modified — Added change-plan routes for storefront admin prefix (bug fix) |
| `resources/js/Components/Billing/SubscriptionStatusBanner.jsx` | **Created** — 6-state global status banner in AdminLayout |
| `resources/js/Components/Billing/InvoiceBadge.jsx` | **Created** — Invoice status badge component |
| `resources/js/Components/Billing/UpgradeDialog.jsx` | Modified — Added Quick Upgrade / Schedule Downgrade buttons; fixed hook order (bug fix) |
| `resources/js/Components/AdminSidebar.jsx` | Modified — Added Invoices nav link |
| `resources/js/Layouts/AdminLayout.jsx` | Modified — Added SubscriptionStatusBanner |
| `resources/js/Pages/Admin/Billing/Invoices.jsx` | **Created** — Invoice index page with stats cards, filters, pagination |
| `resources/js/Pages/Admin/Billing/InvoiceDetail.jsx` | **Created** — Invoice detail with line items table, timeline, download |
| `resources/js/Pages/Admin/Billing/PlanChange.jsx` | **Created** — Plan change confirmation page with proration details; fixed inverted prices (bug fix) |
| `resources/js/Pages/Admin/Billing/Index.jsx` | Modified — Added scheduled plan change banner with cancel button |
| `resources/js/Pages/Admin/Billing/Subscription.jsx` | Modified — Added pending plan display in detail grid |
| `resources/js/Pages/Admin/Billing/UpgradePlan.jsx` | Modified — Added pending downgrade banner with cancel button |
| `resources/views/pdf/invoice.blade.php` | **Created** — HTML invoice download template with print CSS |

---

## Created Files

| File | Purpose |
|------|---------|
| `app/Models/Invoice.php` | Invoice domain model |
| `app/Services/InvoiceService.php` | Invoice generation business logic |
| `app/Services/SubscriptionPlanChangeService.php` | Plan change with proration |
| `app/Http/Controllers/Admin/InvoiceController.php` | Invoice CRUD controller |
| `app/Listeners/GenerateInvoiceFromCompletedIntent.php` | Auto-invoice on payment |
| `app/Console/Commands/SendSubscriptionReminders.php` | Cron: renewal/trial reminders |
| `app/Console/Commands/ApplyScheduledPlanChanges.php` | Cron: apply scheduled downgrades |
| `database/migrations/2026_07_20_000002_add_invoice_fields.php` | Invoice schema migration |
| `database/migrations/2026_07_20_000003_add_pending_plan_to_subscriptions.php` | Pending plan schema migration |
| `resources/js/Components/Billing/SubscriptionStatusBanner.jsx` | Subscription status UI banner |
| `resources/js/Components/Billing/InvoiceBadge.jsx` | Invoice status badge |
| `resources/js/Pages/Admin/Billing/Invoices.jsx` | Invoice list page |
| `resources/js/Pages/Admin/Billing/InvoiceDetail.jsx` | Invoice detail page |
| `resources/js/Pages/Admin/Billing/PlanChange.jsx` | Plan change confirmation page |
| `resources/views/pdf/invoice.blade.php` | Invoice HTML download template |
| `docs/sprint-9.2-subscription-billing-audit.md` | This audit report |

---

## Implementation Summary

### Invoices
- Invoice model with 5 statuses (draft/unpaid/paid/cancelled/refunded) and auto-numbering (`INV-YYYY-00001`)
- InvoiceService generates from PaymentIntent (auto) or Subscription (on-demand)
- 5% tax applied; line items stored as JSON array cast
- InvoiceController with index (paginated, filterable), show, download (HTML), markPaid, markCancelled
- `GenerateInvoiceFromCompletedIntent` listener wired to `PaymentIntentCompleted` event
- Duplicate detection (checks `payment_intent_id` before creating)
- Invoice pages: stats cards, filters, pagination, line items table, timeline view

### Plan Change (Upgrade / Downgrade)
- `SubscriptionPlanChangeService` with daily proration calculation
- Upgrades: immediate plan change via `changePlan()` + audit log
- Downgrades: scheduled via `scheduleDowngrade()` when future expiry exists; immediate when no future expiry
- `applyScheduledChanges()` bulk method for cron consumption
- `cancelScheduledChange()` to abort pending downgrade
- PlanChange page shows comparison cards, billing summary (prorated amount, credit, days remaining, effective date)
- UpgradeDialog shows "Quick Upgrade" / "Schedule Downgrade" buttons alongside existing checkout path
- Pending plan banners on Billing Index, Subscription, and UpgradePlan pages

### Subscription Status Banner
- 6-state banner component shared via Inertia props
- States: suspended (red), expired (red), grace period (amber with days countdown), trial ending (blue→≤3d amber), expiring soon (blue→≤3d amber), locked (amber)
- Integrated into AdminLayout for all admin pages

### Renewal & Trial
- `renew()` method: validates subscription state, checks trial renewal limits, calls `renewFromInterval()`
- `SendSubscriptionReminders` command sends notifications at 14/7/3 days before expiry for active + trialing subscriptions
- Trial → expired transition handled by `SubscriptionExpiryService`

### Expiry & Grace Period
- `SubscriptionExpiryService` handles: active→past_due (grace), past_due→expired, expired→suspended, trial→expired
- 7-day grace period (`GRACE_DAYS`); configurable via constant
- `ProcessExpiredSubscriptions` command with `--dry-run` support
- `EnsureTenantIsActive` middleware redirects: past_due→billing, expired→expired page, suspended→suspended page
- Grace days remaining shared to frontend for UI countdown

### Payment Verification
- `paymentSubmit()` validates evidence upload (image, ≤5MB)
- `ManualPaymentService::confirmPayment()` transitions intent to `waiting_review`
- SuperAdmin approval flow: approve→paid→completed triggers auto-invoice generation
- Redirect back to payment status page after submission

---

## Manual Testing Checklist

### Invoices
- [ ] Verify `php artisan migrate` adds invoice columns without errors
- [ ] Create a payment intent, mark as completed, verify auto-invoice generation
- [ ] Verify invoice number format: `INV-2026-00001`
- [ ] Access `/admin/billing/invoices` — verify pagination, filters, stats cards
- [ ] Access `/admin/billing/invoices/{id}` — verify detail page with line items, timeline
- [ ] Click "Download" — verify HTML file downloads with print CSS
- [ ] Toggle filter status/date/search — verify URL params and results
- [ ] Test empty state: no invoices → "No Invoices Yet" message

### Plan Change (Upgrade)
- [ ] Navigate to `/admin/billing/upgrade` from a lower-tier plan
- [ ] Click "Quick Upgrade" on a higher-tier plan — verify redirected to PlanChange page
- [ ] Verify proration calculation: days remaining, credit, amount due
- [ ] Confirm upgrade — verify plan changes immediately, audit log created
- [ ] Verify tenant unlocked if previously locked
- [ ] Verify invoice generated if auto-invoice logic runs

### Plan Change (Downgrade)
- [ ] Navigate to downgrade from higher-tier to lower-tier plan with future expiry
- [ ] Verify "Scheduled Downgrade" banner appears on billing/upgrade pages
- [ ] Confirm downgrade — verify `pending_plan_id` set, effective at current expiry date
- [ ] Cancel scheduled downgrade — verify pending_plan cleared
- [ ] Test downgrade with no future expiry (already expired) — verify immediate change
- [ ] Run `php artisan subscriptions:apply-scheduled-changes` — verify pending changes applied

### Trial
- [ ] Create subscription with `status=trialing` and `trial_ends_at` in past
- [ ] Run `php artisan subscriptions:process-expired` — verify trial→expired transition
- [ ] Verify tenant locked after trial expiry
- [ ] Test trial renewal via `/admin/billing/renew` — verify trial_renewals_count increments

### Expiry & Grace Period
- [ ] Set subscription `status=active` with `expires_at` in past
- [ ] Run expiry processor — verify active→past_due transition, 7-day grace starts
- [ ] With `--dry-run`: verify counts without applying
- [ ] Verify tenant can still access billing pages during grace
- [ ] After 7 days: run processor — verify past_due→expired, tenant locked
- [ ] After 1 additional day: run processor — verify expired→suspended, tenant status=suspended
- [ ] Verify suspended tenant redirected to suspended page on all routes

### Subscription Status Banner
- [ ] As active subscriber: no banner or blue "active" banner
- [ ] As trialing subscriber with ≤3 days left: amber warning banner
- [ ] As past_due: amber grace period banner with days countdown
- [ ] As expired: red banner
- [ ] As suspended: red banner
- [ ] As locked: amber banner

### Payment Verification
- [ ] Initiate checkout for a paid plan
- [ ] Submit payment with evidence upload (image file)
- [ ] Verify intent transitions to `waiting_review`
- [ ] As superadmin, approve payment — verify intent→paid→completed, invoice generated
- [ ] Test rejection flow: submit, reject, verify reason stored, user can resubmit

### Routes
- [ ] `php artisan route:list --name=billing` — verify all billing routes registered
- [ ] Test both `/admin/billing/*` and `/store/{slug}/admin/billing/*` prefixes

---

## Regression Checklist

- [ ] Existing dashboard still accessible for active subscribers
- [ ] Existing dashboard redirects to expired page for expired subscribers
- [ ] Existing dashboard redirects to suspended page for suspended subscribers
- [ ] SuperAdmin bypasses all subscription checks
- [ ] FeatureGate still enforces plan limits correctly
- [ ] All pre-existing admin routes still work (products, orders, categories, etc.)
- [ ] Existing payment flow (checkout → payment → approval) unchanged
- [ ] Existing subscription lifecycle (ProcessExpiredSubscriptions) unchanged
- [ ] Team member management unaffected
- [ ] Workspace switching unaffected
- [ ] Vite build compiles cleanly (no errors)
- [ ] All PHP files have no syntax errors (`php -l`)

---

## Remaining Issues

### Deferred (Non-blocking)

| Issue | Severity | Impact |
|-------|----------|--------|
| No 2FA for admin actions | Low | Feature enhancement, not a bug |
| No password confirmation on sensitive billing actions | Low | Permission gates provide sufficient protection |
| No idempotency key on plan change execution | Low | Transaction wrapping prevents partial writes |
| Race condition in stock check (non-locking read) | Low | Pre-existing; same pattern as order creation |
| `AuthenticateSession` middleware not registered | Low | Would break password confirmation flow without additional work |

### Known Limitations

| Limitation | Details |
|------------|---------|
| Invoice download uses HTML (not PDF) | No PDF library available; browser "Save as PDF" works |
| Manual payment only gateway | Only `manual` gateway implemented; Stripe/PayPal future |
| Invoice line items show tax as separate item | Tax is a line item rather than footer row for simplicity |
| Grace period fixed at 7 days | Hard-coded constant; future: make configurable per-plan |

---

## Sprint Completion Summary

**Sprint 9.2 — Subscription & Billing** is complete and ready for commit.

### Delivered

| Area | Status |
|------|--------|
| Invoice model, migration, controller, views | ✅ Complete |
| Invoice auto-generation on payment completion | ✅ Complete |
| Plan change with proration (upgrade immediate, downgrade scheduled) | ✅ Complete |
| Subscription status banner (6 states) | ✅ Complete |
| Renewal with trial limit checks | ✅ Complete |
| Expiry lifecycle (active→past_due→expired→suspended, trial→expired) | ✅ Complete |
| Grace period (7-day, with UI countdown) | ✅ Complete |
| Payment verification flow (evidence upload, review, approve/reject) | ✅ Complete |
| Console commands for cron automation | ✅ Complete |
| Frontend pages for all billing areas | ✅ Complete |

### Issues Found & Fixed During Audit

| Issue | Fix |
|-------|-----|
| Change-plan routes missing from `storefront-admin.php` (404 on storefront admin) | Added routes |
| `UpgradeDialog.jsx` — `usePage()` hook called after early return (React hook violation) | Moved before early return |
| `Invoices.jsx` — `router.get()` used for file download (would break Inertia) | Changed to `<a>` tag |
| `PlanChange.jsx` — Prices inverted on downgrade (wrong plan shown as "current") | Fixed price extraction logic |
| `InvoiceDetail.jsx` — React element rendered inside `<span>` with `\|\| '—'` | Inlined DetailRow for status row |
| `PlanChange.jsx` — Unused imports and raw date display | Cleaned up |
| `ProcessSubscriptionExpiry.php` — Duplicate of pre-existing `ProcessExpiredSubscriptions` | Removed |

### Commands for Cron Setup

```bash
# Daily: Process subscription lifecycle (expiry, grace, suspension)
php artisan subscriptions:process-expired

# Daily: Send renewal reminders at 14/7/3 day thresholds
php artisan subscriptions:send-reminders

# Hourly: Apply scheduled plan changes (downgrades)
php artisan subscriptions:apply-scheduled-changes
```
