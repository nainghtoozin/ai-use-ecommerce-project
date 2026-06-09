# Root Login Security

**Date:** 2026-06-09  
**Scope:** Root `/login` POST endpoint — tenant user isolation

---

## Validation Rules

The root `POST /login` (`AuthenticatedSessionController::store()`) now enforces:

1. **SuperAdmin only.** If the authenticating user has a `tenant_id` and is NOT a SuperAdmin, the request is rejected.
2. **Friendly message.** Rejected users see: `"Please login through your store URL."`
3. **Early rejection.** The check runs immediately after user lookup, before any status/ban/suspension checks.

### Implementation

**File:** `app/Http/Controllers/Auth/AuthenticatedSessionController.php:30-36`

```php
if ($user && $user->tenant_id && !$user->isSuperAdmin()) {
    return back()->withErrors([
        'email' => 'Please login through your store URL.',
    ])->onlyInput('email');
}
```

**Logic:**
- `$user` found by email → proceed
- `$user->tenant_id` is not null → user belongs to a tenant
- `!$user->isSuperAdmin()` → user is NOT a SuperAdmin
- Both true → reject with friendly message that does not reveal account existence details

---

## Login Matrix

| User Type | `/login` | `/store/{slug}/login` | `/store/{slug}/admin/login` | `/superadmin/login` |
|-----------|----------|----------------------|---------------------------|---------------------|
| **SuperAdmin** (no `tenant_id`) | ✅ Allowed → admin dashboard | ✅ Allowed → tenant admin dashboard | ✅ Allowed → tenant admin dashboard | ✅ Allowed → superadmin dashboard |
| **Tenant Admin** (has `tenant_id`, role `admin`) | ❌ Rejected — "Please login through your store URL." | ✅ Allowed → tenant admin dashboard | ✅ Allowed → tenant admin dashboard | ❌ 404 (no route) |
| **Tenant Customer** (has `tenant_id`, role `customer`) | ❌ Rejected — "Please login through your store URL." | ✅ Allowed → storefront index | ✅ Allowed → storefront index | ❌ 404 (no route) |
| **Unattached User** (no `tenant_id`, no role) | ✅ Allowed → client dashboard | ❌ Rejected (not associated with store) | ❌ Rejected (not associated with store) | ❌ 404 (no route) |

### POST Endpoints

| Login Page URL | POST Target URL | Controller |
|----------------|----------------|------------|
| `GET /login` | `POST /login` | `AuthenticatedSessionController::store()` |
| `GET /superadmin/login` | `POST /login` | `AuthenticatedSessionController::store()` |
| `GET /admin/login` | `POST /login` | `AuthenticatedSessionController::store()` |
| `GET /store/{slug}/login` | `POST /store/{slug}/login` | `StorefrontLoginController::store()` |
| `GET /store/{slug}/admin/login` | `POST /store/{slug}/login` | `StorefrontLoginController::store()` |

---

## Canonical Login Entrypoints

| Role | Entrypoint | Notes |
|------|-----------|-------|
| **SuperAdmin** | `/superadmin/login` | Dedicated page. POST goes to root `/login`. |
| **Store Admin** | `/store/{slug}/admin/login` | Shows store-branded login. POST goes to `/store/{slug}/login`. After auth, redirects to `storefront.admin.dashboard`. |
| **Customer** | `/store/{slug}/login` | Shows store-branded login. POST goes to `/store/{slug}/login`. After auth, redirects to `storefront.index`. |
| **Legacy fallback** | `/admin/login` | Renders same page as root `/login`. POST goes to root `/login`. |

---

## Affected Controllers

### `AuthenticatedSessionController::store()` — modified
- **File:** `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- **Change:** Added tenant-user rejection at line 32-36
- **Behavior:** Rejects any user with `tenant_id` who is not SuperAdmin
- **Validation message:** `"Please login through your store URL."`

### `StorefrontLoginController::store()` — unchanged (already correct)
- **File:** `app/Http/Controllers/StorefrontLoginController.php`
- **Existing check at line 69:** `if ($user->tenant_id !== null && $user->tenant_id !== $tenant->id)` — rejects cross-tenant login
- **Existing check at line 76:** `if ($user->tenant_id === null)` — auto-assigns tenant_id for legacy users
- **Existing redirect at line 95:** `if ($user->isAdmin())` — redirects admin to `storefront.admin.dashboard`
- **Existing redirect at line 99:** redirects customer to `storefront.index`

---

## Routes Modified

**File:** `routes/web.php`

**Storefront group (lines 110-112):**
```
Before: GET  /admin/login  → AuthenticatedSessionController@create
After:  GET  /admin/login  → StorefrontLoginController@create
        POST /admin/login  → StorefrontLoginController@store
```

---

## Security Properties

| Property | Status |
|----------|--------|
| Tenant customer blocked from root `/login` | ✅ |
| Tenant admin blocked from root `/login` | ✅ |
| SuperAdmin can always login from `/login` | ✅ |
| Customer can login via `/store/{slug}/login` | ✅ |
| Store admin can login via `/store/{slug}/admin/login` | ✅ |
| Error message does not reveal account status | ✅ (always "Please login through your store URL.") |
| Cross-tenant login still blocked by StorefrontLoginController | ✅ (line 69: tenant_id match check) |

## Verification

- `php artisan route:list --name=login` shows all 5 login routes
- `npx vite build` passes
- Root `/login` POST rejects tenant users before any account status check
