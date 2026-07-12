# Phase 6 – Login Routing Fix

## Completion Status: IMPLEMENTED

---

## Problem

`IDENTITY_USE_ACCOUNTS=true` redirected authenticated users to `/dashboard` instead of resolving the correct destination based on role + tenant context. Legacy mode (`User` model) had custom inline logic in each controller; Account mode had gaps or fell through to `/dashboard`.

Each controller duplicated its own redirect logic:
- `AuthenticatedSessionController::store()` — inline `if admin + tenant → storefront.admin.dashboard, else admin.dashboard, else client.dashboard`
- `StorefrontLoginController::store()` — inline `if admin → storefront.admin.dashboard, else storefront.index`
- `VerifyEmailController` — inline tenant resolution with `instanceof` checks
- `EmailVerificationPromptController` — hardcoded `client.dashboard`
- `EmailVerificationNotificationController` — hardcoded `client.dashboard`
- `ConfirmablePasswordController` — hardcoded `client.dashboard`
- `NewPasswordController` — inline `User/Account instanceof` checks
- `RegisteredUserController` — inline `if admin → storefront.admin.dashboard, else storefront.index`
- `ImpersonationController` — inline `if tenant → storefront.admin.dashboard, else admin.dashboard`

---

## Solution

### Created: `app/Auth/LoginRedirectResolver.php`

Single centralized resolver with these methods:

| Method | Returns | Used By |
|--------|---------|---------|
| `resolveLogin($user, $tenant)` | URL string | Login controllers |
| `intended($user, $tenant)` | `RedirectResponse` (uses `redirect()->intended()`) | StorefrontLogin, Registration, Password Confirm, Email Verify |
| `resolveLogout($user, $storeSlug, $context)` | URL string | Logout |
| `resolveAfterRegistration($user, $tenant)` | URL string | Registration |
| `resolveAfterEmailVerification($user)` | URL string | Email verification |
| `resolveAfterPasswordReset($user)` | URL string | Password reset |
| `resolveAfterImpersonation($user)` | URL string | Impersonation start |
| `resolveAfterImpersonationLeave()` | URL string | Impersonation leave |

### Redirect Rules

```
Authenticatable → isSuperAdmin()         → /superadmin
Authenticatable → isAdmin() + hasTenant  → /store/{slug}/admin/dashboard
Authenticatable → isAdmin() + noTenant   → /admin/dashboard
Authenticatable → customer + hasTenant   → /store/{slug}
Authenticatable → customer + noTenant    → /dashboard
```

### Tenant Resolution

For `Account` model (no `tenant_id` column):
1. `Tenant::getCurrent()` — current request context
2. `getCurrentMembership()->tenant` — cached membership
3. `memberships()->with('tenant')->first()` — first available membership

For `User` model:
1. `$user->tenant` — Eloquent relationship

---

## Files Modified (11 files)

| File | Change |
|------|--------|
| `app/Auth/LoginRedirectResolver.php` | **NEW** — Centralized redirect resolver |
| `app/Http/Controllers/Auth/AuthenticatedSessionController.php` | `store()` → `app(LoginRedirectResolver)->resolveLogin()`; `destroy()` → `app(LoginRedirectResolver)->resolveLogout()`; removed `fallbackLogoutRedirect()` |
| `app/Http/Controllers/StorefrontLoginController.php` | `store()` → `app(LoginRedirectResolver)->intended()` |
| `app/Http/Controllers/Auth/VerifyEmailController.php` | `redirectAfterVerification()` → `app(LoginRedirectResolver)->resolveAfterEmailVerification()` |
| `app/Http/Controllers/Auth/EmailVerificationPromptController.php` | `__invoke()` → `app(LoginRedirectResolver)->intended()` |
| `app/Http/Controllers/Auth/EmailVerificationNotificationController.php` | `store()` → `app(LoginRedirectResolver)->intended()` |
| `app/Http/Controllers/Auth/ConfirmablePasswordController.php` | `store()` → `app(LoginRedirectResolver)->intended()` |
| `app/Http/Controllers/Auth/NewPasswordController.php` | `store()` → `app(LoginRedirectResolver)->resolveAfterPasswordReset()` |
| `app/Http/Controllers/Auth/RegisteredUserController.php` | `store()` → `app(LoginRedirectResolver)->intended()`; `storeAccount()` → `app(LoginRedirectResolver)->resolveAfterRegistration()` |
| `app/Http/Controllers/SuperAdmin/ImpersonationController.php` | `start()` → `app(LoginRedirectResolver)->resolveAfterImpersonation()`; `leave()` → `app(LoginRedirectResolver)->resolveAfterImpersonationLeave()` |

---

## Login Flow Diagram

```
User submits credentials
        │
        ▼
  LoginRequest::authenticate()
  (picks guard from identity.use_accounts)
        │
        ▼
  Controller::store()
        │
        ├── AuthenticatedSessionController
        │   └── LoginRedirectResolver::resolveLogin()
        │
        ├── StorefrontLoginController
        │   └── LoginRedirectResolver::intended()
        │
        ├── RegisteredUserController
        │   ├── store()   → LoginRedirectResolver::intended()
        │   └── storeAccount() → LoginRedirectResolver::resolveAfterRegistration()
        │
        └── ImpersonationController::start()
            └── LoginRedirectResolver::resolveAfterImpersonation()
                 │
                 ▼
      LoginRedirectResolver::resolveLogin()
                 │
                 ├── isSuperAdmin()        → /superadmin
                 ├── isAdmin() + tenant    → /store/{slug}/admin/dashboard
                 ├── isAdmin() + no tenant → /admin/dashboard
                 ├── hasTenant()           → /store/{slug}
                 └── no tenant             → /dashboard
```

---

## Logout Flow Diagram

```
User clicks logout
        │
        ▼
  AuthenticatedSessionController::destroy()
        │
        ├── ActivityLogger::log()
        ├── Auth::guard()->logout()
        ├── Session::invalidate()
        ├── Session::regenerateToken()
        └── LoginRedirectResolver::resolveLogout()
                 │
                 ▼
      Infer context from:
        - POST 'context' field
        - POST 'store_slug' field
        - Tenant::getCurrent()->slug
        - Session 'current_tenant_slug'
        - isSuperAdmin() check
                 │
                 ├── context=superadmin   → /superadmin/login
                 ├── context=admin+slug   → /store/{slug}/admin/login
                 ├── context=admin-no-slug → /admin/login
                 ├── context=storefront+slug → /store/{slug}
                 ├── context=storefront-no-slug → /
                 └── fallback: superadmin → superadmin.login
                              has slug   → storefront.index
                              else       → /
```

---

## Verification

### Route Names Used

| Route Name | Path | Verified |
|-----------|------|----------|
| `superadmin.dashboard` | `GET /superadmin` | ✅ |
| `admin.dashboard` | `GET /admin/dashboard` | ✅ |
| `client.dashboard` | `GET /dashboard` | ✅ |
| `storefront.admin.dashboard` | `GET /store/{slug}/admin/dashboard` | ✅ |
| `storefront.index` | `GET /store/{slug}` | ✅ |
| `superadmin.login` | `GET /superadmin/login` | ✅ |
| `admin.login` | `GET /admin/login` | ✅ |
| `storefront.admin.login` | `GET /store/{slug}/admin/login` | ✅ |
| `storefront.index` | `GET /store/{slug}` | ✅ |
| `storefront.onboarding.complete` | varies | ✅ |

### No Hardcoded `/dashboard` Redirects Remain

Before: `EmailVerificationPromptController`, `EmailVerificationNotificationController`, `ConfirmablePasswordController` all used `redirect()->intended(route('client.dashboard'))`.

After: All use `LoginRedirectResolver::intended()` which resolves the correct path per user role.

### Both Modes Produce Identical Navigation

| Scenario | User Model | Account Model | Same URL? |
|----------|-----------|---------------|-----------|
| SuperAdmin login | `/superadmin` | `/superadmin` | ✅ |
| Admin with tenant | `/store/{slug}/admin/dashboard` | `/store/{slug}/admin/dashboard` | ✅ |
| Admin without tenant | `/admin/dashboard` | `/admin/dashboard` | ✅ |
| Customer login | `/store/{slug}` | `/store/{slug}` | ✅ |
| SuperAdmin logout | `/superadmin/login` | `/superadmin/login` | ✅ |
| Admin logout | `/store/{slug}/admin/login` | `/store/{slug}/admin/login` | ✅ |
| Customer logout | `/store/{slug}` | `/store/{slug}` | ✅ |

---

## Regression Checklist

- [x] SuperAdmin login → `/superadmin`
- [x] Merchant Owner login → `/store/{slug}/admin/dashboard`
- [x] Store Admin login → `/store/{slug}/admin/dashboard`
- [x] Staff login → `/store/{slug}/admin/dashboard`
- [x] Customer login → `/store/{slug}`
- [x] SuperAdmin logout → `/superadmin/login`
- [x] Admin/Staff logout → `/store/{slug}/admin/login`
- [x] Customer logout → `/store/{slug}`
- [x] Remember Me → handled by `LoginRequest::authenticate()` (unchanged)
- [x] Intended redirect → `LoginRedirectResolver::intended()` uses `redirect()->intended()`
- [x] Email Verification → resolved via `resolveAfterEmailVerification()`
- [x] Password Reset → resolved via `resolveAfterPasswordReset()`
- [x] Impersonation → resolved via `resolveAfterImpersonation()`
- [x] Registration → resolved via `resolveAfterRegistration()` / `intended()`

---

## Identity Source Independence

The `LoginRedirectResolver` is completely agnostic to the identity provider. It receives `User|Account` and queries only:

1. `$authenticatable->isSuperAdmin()` — overridden on Account to check `model_has_roles` globally
2. `$authenticatable->isAdmin()` — overridden on Account to resolve through membership or global
3. `$authenticatable->tenant` / `Tenant::getCurrent()` — resolves tenant context

No `auth()->user()`, no `config('identity.use_accounts')`, no mode checks. The resolver is genuinely mode-agnostic.

The only mode-aware code remaining is the pre-authentication validation in `AuthenticatedSessionController::store()` and `StorefrontLoginController::store()` (account status checks, tenant membership validation). These are pre-redirect concerns and correctly belong in the controllers.

---

## Remaining Authentication Issues

| Issue | Location | Severity | Notes |
|-------|----------|----------|-------|
| Blade views use `Auth::user()` directly | Blade navigation/sidebar | Low | Legacy server-rendered pages; cannot use Inertia IdentityProjection |
| ChatController API uses raw `getDisplayName()` | `app/Http/Controllers/ChatController.php` | Low | Already fixed to use `getDisplayName()` in prior round |
| Account mode pre-auth validation is duplicated | `AuthenticatedSessionController::store()` and `StorefrontLoginController::store()` | Medium | Account status checks duplicated in both controllers |
| Login redirect still uses `redirect()->to()` with absolute URL | `NewPasswordController` callback | Low | Works correctly but uses string concatenation instead of `route()` |
| `ImpersonationController` still uses `User` model only | `start(User $user)` | Medium | Only supports legacy User model; Account impersonation deferred |
