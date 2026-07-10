# Phase 6 — Notification Authentication Fix Report

## Bug

After login via `store/{store_slug}/login` with `IDENTITY_USE_ACCOUNTS=true`,
Axios requests to `/notifications/preferences` and `/notifications/fetch?per_page=50`
returned HTTP 401 Unauthorized.

## Root Cause

`Route::middleware('auth')` at `routes/web.php:183` checks only the **default guard**
(`web`). Although `IdentifyTenant` middleware calls `Auth::shouldUse('accounts')` to
switch the default guard before route middleware runs, this mechanism fails in
real HTTP requests — the guard-resolver chain (`$request->user()` → `Auth::guard(null)`)
does not reliably pick up the changed default guard in a fresh request process.

The same pattern (`auth` without guard specification) was repeated in **7 other
route groups** across `routes/web.php` and `routes/auth.php`.

## Fix

Changed every occurrence of the bare `'auth'` middleware to `'auth:web,accounts'`
across all route files, matching the pattern already used in storefront customer
routes (`routes/web.php:154`):

| File | Line(s) | Route Group |
|------|---------|-------------|
| `routes/web.php` | 183 | Profile, Chat, Notifications, Orders, Checkout, Telegram, Broadcast |
| `routes/web.php` | 277 | Admin dashboard & operations |
| `routes/web.php` | 478 | Impersonation leave |
| `routes/web.php` | 485 | SuperAdmin dashboard & operations |
| `routes/web.php` | 553 | Payment gateways & checkout |
| `routes/web.php` | 569 | Coupon / Promotion cart actions |
| `routes/web.php` | 577 | Wishlist |
| `routes/auth.php` | 45 | Email verification, password, logout |



## Validation

| Mode | Endpoint | Status | 
|------|----------|--------|
| `IDENTITY_USE_ACCOUNTS=true` | `/notifications/preferences` | HTTP 200 |
| `IDENTITY_USE_ACCOUNTS=true` | `/notifications/fetch` | HTTP 200 |
| `IDENTITY_USE_ACCOUNTS=false` | `/notifications/preferences` | HTTP 200 |
| `IDENTITY_USE_ACCOUNTS=false` | `/notifications/fetch` | HTTP 200 |

## How It Works

The `auth:web,accounts` middleware iterates over both guards:

1. Check `Auth::guard('web')->check()` — succeeds for Legacy `User` sessions
2. If not, check `Auth::guard('accounts')->check()` — succeeds for Account sessions

When a guard succeeds, `Auth::shouldUse()` is called against that guard, making it
the default for the remainder of the request. This ensures `Auth::user()` and
`$request->user()` return the correct authenticatable model without hardcoding
any guard inside controllers or services.

## Files Changed

- `routes/web.php` — 7 edits
- `routes/auth.php` — 1 edit
- `routes/storefront-admin.php` — 1 edit
