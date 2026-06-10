# Mailpit Local Email Dev Setup

**Date:** 2026-06-09  
**Scope:** Local SMTP email catcher for development

---

## Overview

This project uses **maildev** (npm package) as a local SMTP email catcher — functionally equivalent to Mailpit. It intercepts all outbound emails from the Laravel app and displays them in a local web UI, eliminating the need to send real emails during development.

Maildev runs on:
- **SMTP port:** `1025` — receives emails from the application
- **Web UI:** `http://localhost:1080` — view captured emails

---

## .env Values

| Key | Old Value | New Value |
|-----|-----------|-----------|
| `MAIL_MAILER` | `log` | `smtp` |
| `MAIL_HOST` | `127.0.0.1` | `127.0.0.1` (unchanged) |
| `MAIL_PORT` | `2525` | `1025` |
| `MAIL_USERNAME` | `null` | `null` (unchanged) |
| `MAIL_PASSWORD` | `null` | `null` (unchanged) |
| `MAIL_FROM_ADDRESS` | `"hello@example.com"` | `"hello@example.com"` (unchanged) |
| `MAIL_FROM_NAME` | `"${APP_NAME}"` | `"${APP_NAME}"` (unchanged) |

The changed lines in `.env`:

```diff
-MAIL_MAILER=log
-MAIL_PORT=2525
+MAIL_MAILER=smtp
+MAIL_PORT=1025
```

---

## Commands Used

### 1. Install & start maildev

```powershell
npx maildev
```

First run downloads and caches the package. Subsequent runs are instant.

### 2. Clear config cache

```bash
php artisan config:clear
```

Required after changing `.env` values.

### 3. Send test email

```bash
php artisan tinker
Mail::raw('Hello from ' . config('app.name'), function($m) {
    $m->to('test@example.com')->subject('Test Email');
});
```

Or visit the test route:

```
GET http://localhost:8000/test-email
GET http://localhost:8000/test-email?to=admin@example.com
```

---

## Test Route

**File:** `routes/web.php`

```php
if (app()->environment('local')) {
    Route::get('/test-email', function () {
        $to = request('to', 'test@example.com');
        Mail::raw(
            'This is a test email from ' . config('app.name') . '.',
            function ($message) use ($to) {
                $message->to($to)
                    ->subject('Test Email — ' . config('app.name'));
            }
        );
        return response()->json([
            'message' => 'Test email sent to ' . $to,
            'to' => $to,
        ]);
    })->name('test-email');
}
```

- **Only available in `local` environment** — guarded by `app()->environment('local')`
- Default recipient: `test@example.com`
- Override with query param: `?to=someone@example.com`

---

## Verification

1. **Start maildev:** `npx maildev` (runs in terminal, keeps SMTP + web UI active)
2. **Send an email** via the test route or `php artisan tinker`
3. **Open browser:** `http://localhost:1080`
4. **Check inbox:** the email should appear with subject, recipient, and body

**Verified:** Test email sent via `php artisan tinker` appeared in maildev web UI at `http://localhost:1080`.

---

## Troubleshooting

### Port already in use (EADDRINUSE)

```
Error: listen EADDRINUSE: address already in use :::1025
Error: listen EADDRINUSE: address already in use :::1080
```

**Solution:** Kill the existing maildev process:

```powershell
# Find and kill the Node process using port 1080
netstat -ano | findstr :1080
# Find the PID, then:
taskkill /PID <PID> /F

# Or kill all Node processes:
taskkill /F /IM node.exe
```

### Emails not appearing in maildev

1. **Check maildev is running** — open `http://localhost:1080`; if it doesn't load, restart maildev
2. **Verify `.env` values** — run `php artisan tinker --execute="echo config('mail.default') . PHP_EOL . config('mail.mailers.smtp.host') . ':' . config('mail.mailers.smtp.port');"`
3. **Clear config cache** — `php artisan config:clear`
4. **Check SMTP connection** — `Test-NetConnection -ComputerName 127.0.0.1 -Port 1025` should show `TcpTestSucceeded: True`

### Production note

The `MAIL_MAILER=smtp` / `MAIL_HOST=127.0.0.1` / `MAIL_PORT=1025` values are for **local development only**. In production, replace with your real SMTP provider (Mailgun, SES, Postmark, etc.):

```dotenv
MAIL_MAILER=ses         # or mailgun, postmark, resend
MAIL_HOST=              # provider-specific
MAIL_PORT=587           # provider-specific
MAIL_USERNAME=...
MAIL_PASSWORD=...
```

---

## Files Changed

| File | Change |
|------|--------|
| `.env` | `MAIL_MAILER=log` → `smtp`, `MAIL_PORT=2525` → `1025` |
| `routes/web.php` | Added `GET /test-email` route (local-only) |

## Verification

- `php artisan config:clear` — config cache cleared
- `php artisan tinker` — test email sent and confirmed in maildev web UI
- `php artisan route:list --name=test-email` — test route registered
