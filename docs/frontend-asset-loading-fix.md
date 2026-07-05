# Frontend Asset Loading Fix

## Root Cause Analysis

### The Bug

Frontend assets (CSS/JS) loaded via HTTPS (`https://localhost:8000/build/assets/*`) instead of HTTP (`http://localhost:8000/build/assets/*`). Since `php artisan serve` only serves HTTP, the browser received `ERR_CONNECTION_CLOSED`.

### Why the Image Upload Refactor Appears Related

The refactor did **NOT** cause the bug. The refactor triggered it by running `php artisan optimize` (to verify that services resolve correctly from the container). This created/refreshed a cached config file at `bootstrap/cache/config.php`.

### The Complete Chain of Failure

```
1. php artisan optimize (run during refactor verification)
   └─ Config cached to bootstrap/cache/config.php
   └─ After caching, Laravel flushes the $_ENV superglobal
   └─ env() helper stops reading from .env — returns NULL for all calls

2. AppServiceProvider::boot() (line 198):
   if (env('APP_ENV') !== 'local') {
   → NULL !== 'local' → TRUE  ← BUG

3. URL::forceScheme('https') called

4. @vite(['resources/js/app.jsx']) generates asset tags using asset() helper
   → asset() reads config('app.url')
   → config('app.url') was 'https://rehire-stubble-willing.ngrok-free.dev'
   → Combined with forceScheme('https'): https://ngrok-free.dev/build/assets/*

5. Browser on http://localhost:8000 tries to load:
   https://rehire-stubble-willing.ngrok-free.dev/build/assets/app-*.css
   https://rehire-stubble-willing.ngrok-free.dev/build/assets/app-*.js
   → ngrok tunnel closed or inaccessible
   → ERR_CONNECTION_CLOSED
```

### Why `env()` vs `config()` Matters

| Expression | Before config cache | After config cache |
|-----------|-------------------|-------------------|
| `env('APP_ENV')` | `'local'` | `null` ← flushed |
| `config('app.env')` | `'local'` | `'local'` ← cached |
| `app()->environment('local')` | `true` | `true` ← reads cached config |
| `env('APP_URL')` | `'https://...ngrok...'` | `null` ← flushed |
| `config('app.url')` | `'https://...ngrok...'` | `'https://...ngrok...'` ← cached |

### Other Contributors

- **`.env` had `APP_URL=https://rehire-stubble-willing.ngrok-free.dev`** — a stale ngrok URL that was inactive. Even without the `forceScheme` bug, asset URLs pointed to a non-routable domain.
- **`APP_URL=http://localhost` was commented out** — the correct local dev value was disabled.

---

## Changes Made

### File 1: `app/Providers/AppServiceProvider.php`

**What**: Line 198 — replaced `env('APP_ENV')` with `app()->environment()`

```diff
- if (env('APP_ENV') !== 'local') {
+ if (!app()->environment('local')) {
      URL::forceScheme('https');
  }
```

**Why**: `env()` returns `null` after `php artisan config:cache` because Laravel flushes the env array. `app()->environment()` reads from the cached config (`config('app.env')`), which survives caching. This is the documented Laravel best practice: https://laravel.com/docs/deployment#optimization

**No regression**: `app()->environment('local')` returns `true` both before and after config caching when `APP_ENV=local`. The behavior is identical in both states.

### File 2: `.env`

**What**: Switched `APP_URL` from the ngrok URL to `http://localhost:8000`

```diff
- #APP_URL=http://localhost
- APP_URL=https://rehire-stubble-willing.ngrok-free.dev
+ APP_URL=http://localhost:8000
+ # For production / ngrok tunnels, override APP_URL in .env or set env var:
+ # APP_URL=https://your-domain.ngrok-free.dev
```

**Why**: `config('app.url')` is used by `asset()` and `@vite()` to generate absolute URLs. For local development with `php artisan serve`, it must point to `http://localhost:8000`. The ngrok URL was stale (tunnel closed) and made assets unresolvable.

**No regression**: In production (Railway deployment), `APP_URL` is overridden by the production environment variable, not by `.env`. The `.env` file change only affects local development.

### File 3: Config cache cleared

**What**: Ran `php artisan optimize:clear` and `php artisan config:clear`

**Why**: The cached config at `bootstrap/cache/config.php` contained stale values. Clearing it ensures the new `.env` and code changes take effect immediately.

---

## Verification Results

| Check | Before | After |
|-------|--------|-------|
| `env('APP_ENV')` | `null` | `'local'` |
| `app()->environment('local')` | `true` | `true` |
| `URL::forceScheme('https')` | Called (incorrectly) | Not called (correctly) |
| `config('app.url')` | `https://...ngrok...` | `http://localhost:8000` |
| Vite CSS URL | `https://ngrok.dev/build/assets/app-*.css` | `http://localhost:8000/build/assets/app-*.css` |
| Vite JS URL | `https://ngrok.dev/build/assets/app-*.js` | `http://localhost:8000/build/assets/app-*.js` |
| `npm run build` | N/A | 2513 modules, 0 errors |
| `php -l AppServiceProvider.php` | N/A | No syntax errors |

---

## Regression Risk

| Risk | Mitigation |
|------|-----------|
| Production `forceScheme('https')` breaks | Only fires when `!app()->environment('local')`. In production, `APP_ENV=production` → `local` is false → `forceScheme('https')` called — **same as before**. |
| `.env` change affects Railway deployment | Railway uses environment variables (not `.env`). The `.env` file is local-only. **No impact.** |
| `APP_URL=http://localhost:8000` breaks webhook callbacks | If external services send callbacks to the ngrok URL, they'll now fail because ngrok tunnel is closed. To test webhooks locally, use a new ngrok tunnel and set `APP_URL` to the new ngrok URL. This was already broken (stale ngrok). |

---

## Manual QA Checklist

### Local Dev (`php artisan serve`)

- [ ] Open `http://localhost:8000` in browser
- [ ] Page loads without console errors
- [ ] `GET http://localhost:8000/build/assets/app-*.css` returns 200
- [ ] `GET http://localhost:8000/build/assets/app-*.js` returns 200
- [ ] Favicon loads (links use `http://localhost:8000/storage/...` or Cloudinary URL)
- [ ] Navigate to a page with evidence images (Admin > Billing) — images load correctly
- [ ] Verify no mixed content warnings in browser console

### After Config Caching

- [ ] Run `php artisan optimize`
- [ ] Page still loads correctly
- [ ] Asset URLs still use `http://localhost:8000/...` (not HTTPS)
- [ ] `php artisan tinker` confirms `!app()->environment('local')` = `false`

### ImageService Independence

- [ ] Upload an image → `ImageService::url()` returns correct URL
- [ ] `ImageService::url()` uses `http://localhost:8000/storage/...` for local disk
- [ ] `ImageService::url()` uses `https://res.cloudinary.com/...` for Cloudinary URLs
- [ ] Image asset loading is completely independent of frontend asset loading

### Production Parity

- [ ] Set `APP_ENV=production` in `.env` temporarily
- [ ] Restart `php artisan serve`
- [ ] `URL::forceScheme('https')` fires correctly
- [ ] Reset `APP_ENV=local`
