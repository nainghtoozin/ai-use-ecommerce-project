# Phase 6 — Authentication Architecture Audit

**Status:** AUDIT COMPLETE — No code modified  
**Date:** 2026-07-08  
**Version:** 1.0  
**Auditor:** Principal Laravel Authentication Architect  
**Project:** Multi-tenant SaaS E-commerce Platform  
**Phase:** Phase 6 Readiness Assessment (Account Authentication)

---

## Executive Summary

This document is a complete audit of the existing authentication architecture in preparation for Phase 6 (Account Authentication). The audit covers every authentication component — login flow, guards, providers, sessions, middleware, controllers, events, and the existing identity foundation.

**Key Finding:** The existing authentication system is a standard Laravel Breeze (Inertia stack) scaffold with significant multi-tenant customizations. It uses a single `web` guard with a single `users` provider backed by the `App\Models\User` model. The Account model (`App\Models\Account`) is fully built — implements `Authenticatable`, `MustVerifyEmail`, uses bcrypt hashed passwords — but is **entirely dormant** with zero database rows and zero guard/provider configuration.

**Critical Issues Found:**
1. `ActivateTenantOnVerified` listener exists but is **not registered** in any service provider — email verification will NOT activate a pending tenant
2. `Account` model is missing the `MustVerifyEmail` trait (implements the interface but doesn't `use` the trait) and missing `sendPasswordResetNotification()` — it cannot participate in email verification or password reset as-is
3. `IdentityResolver::supportsAccount()` hardcoded to `false` — the entire identity resolution chain is dormant
4. No event auto-discovery configured (`bootstrap/app.php` has no `withEvents()` or `discoverEvents()`)
5. The `accounts` table, `tenant_memberships` table, and all 9 supporting migration tables exist but contain **zero rows**

**Recommended Strategy:** Implement Phase 6 in 8 additive, flag-gated steps. The `IDENTITY_USE_ACCOUNTS` env flag (already defined in `config/identity.php`) serves as the master switch. All modifications are additive — existing User-based auth continues working unchanged.

---

## Current Authentication Architecture

### Package Identity

| Aspect | Detail |
|---|---|
| **Scaffold** | Laravel Breeze (Inertia stack) — `laravel/breeze:^2.3` in `composer.json` |
| **RBAC** | `spatie/laravel-permission:^6.25` — 145 permissions, 3 roles (superadmin, admin, customer) |
| **API Auth** | None — no Sanctum, no Passport, no API tokens |
| **Hashing** | bcrypt, 12 rounds (`.env: BCRYPT_ROUNDS=12`) — no `config/hashing.php` exists (Laravel defaults) |
| **Session** | `file` driver (from `.env`), `database` (config default) — 120 min lifetime |
| **Multi-tenant** | Custom tenant middleware stack (IdentifyTenant, CheckTenantAccess, etc.) |

### File Inventory

| Category | Files |
|---|---|
| **Login Controllers** | `AuthenticatedSessionController`, `StorefrontLoginController` |
| **Registration Controllers** | `RegisteredUserController`, `CreateStoreController` |
| **Password Controllers** | `PasswordResetLinkController`, `NewPasswordController`, `ConfirmablePasswordController`, `PasswordController` |
| **Email Verification** | `VerifyEmailController`, `EmailVerificationPromptController`, `EmailVerificationNotificationController` |
| **Auth Middleware** | `IdentifyTenant`, `CheckUserStatus`, `RoleMiddleware`, `CheckTenantAccess`, `TenantIsValid` |
| **Auth Requests** | `LoginRequest` (form request with `authenticate()`) |
| **Auth Events** | `Verified` (Laravel), `PasswordReset` (Laravel), `Registered` (Laravel) |
| **Auth Listeners** | `ActivateTenantOnVerified` (listens to `Verified`, but **NOT registered**) |
| **Auth Routes** | `routes/auth.php` (62 lines — Breeze standard + custom) |
| **Auth Config** | `config/auth.php` (115 lines — single guard/provider) |
| **Identity Foundation** | 8 classes in `app/Auth/`, 6 contracts in `app/Contracts/` (all dormant) |

---

## Login Flow Diagram

```
REQUEST: POST /login  or  POST /store/{slug}/login
    │
    ▼
IdentifyTenant (global web middleware)
    ├─ Resolves current tenant from user, subdomain, header, session, or default
    └─ Sets app('current.tenant')
    │
    ▼
HandleInertiaRequests (global web middleware)
    └─ Shares user data + tenant data to Inertia
    │
    ▼
CheckUserStatus (global web middleware)
    └─ Blocks suspended/banned users, tenant-suspended users
    │
    ▼
┌────────────────────────────────────────────────────────────────────┐
│  STOREFRONT LOGIN (/store/{slug}/login)                           │
│  Controller: StorefrontLoginController                            │
│                                                                    │
│  1. Resolve tenant from Tenant::getCurrent()                      │
│  2. Find User by email                                              │
│  3. Check User status (active/suspended/banned/inactive)           │
│  4. Check tenant status (pending/suspended)                        │
│  5. Verify user->tenant_id matches current tenant->id              │
│  6. $request->authenticate() → Auth::attempt()                     │
│  7. Auto-assign tenant_id for legacy users (null → current tenant) │
│  8. Regenerate session                                              │
│  9. Log activity                                                    │
│  10. Redirect: admin → storefront.admin.dashboard                  │
│                customer → storefront.index                         │
└────────────────────────────────────────────────────────────────────┘
    │
    ▼
┌────────────────────────────────────────────────────────────────────┐
│  ROOT LOGIN (/login)                                               │
│  Controller: AuthenticatedSessionController                        │
│                                                                    │
│  1. Find User by email                                              │
│  2. BLOCK tenant users: "Please login through your store URL."     │
│  3. Check User status (active/suspended/banned/inactive)           │
│  4. Check tenant suspension                                         │
│  5. $request->authenticate() → Auth::attempt()                     │
│  6. Regenerate session                                              │
│  7. Log activity                                                    │
│  8. Redirect: admin → admin.dashboard or storefront.admin.dashboard│
│                other → client.dashboard                            │
└────────────────────────────────────────────────────────────────────┘
    │
    ▼
LoginRequest::authenticate()
    ├─ ensureIsNotRateLimited() — 5 attempts/min, keyed by email|ip
    ├─ Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))
    │   ├─ EloquentUserProvider::retrieveByCredentials() → User::where('email', $email)
    │   ├─ EloquentUserProvider::validateCredentials() → Hash::check(password, user->password)
    │   ├─ SessionGuard::login() → stores user.id in session
    │   ├─ Updates remember_token if "remember" is checked
    │   └─ Regenerates session ID
    └─ On failure: RateLimiter::hit(), throw ValidationException
    │
    ▼
SESSION CREATED (web guard)
    ├─ session('auth.password_confirmed_at') = null (fresh login)
    ├─ Session stored in file (or database) with user_id
    └─ Redirect to appropriate dashboard
```

---

## Session Lifecycle

### Creation (Login)

```
Auth::attempt() → SessionGuard::login()
    ├─ Calls $this->updateSession($user->getAuthIdentifier())
    │   └─ session()->put($this->getName(), $user->getAuthIdentifier())  // login_web_XXX
    ├─ session()->regenerate()  (called in controller after authenticate())
    ├─ If remember: $user->remember_token updated, cookie set
    └─ session()->migrate(true)  (implicit, via regenerate)
```

### State (Mid-request)

```
Session contains:
    ├─ login_web_<hash> = 1  (user_id from Auth::id())
    ├─ password_confirmed_at = timestamp  (after password confirmation)
    ├─ current_tenant_slug = 'my-store'  (set by storefront middleware)
    ├─ cart = [...]  (session-based cart)
    ├─ impersonator_id = null  (set during impersonation)
    ├─ impersonator_name = null
    ├─ _token = csrf-token
    ├─ _flash = success/error/warning messages
    └─ _previous = previous URL
```

### Regeneration (Login/Logout)

| Event | Session Action |
|---|---|
| Login | `$request->session()->regenerate()` — new session ID, data preserved |
| Logout | `Auth::guard('web')->logout()` → `session()->invalidate()` → `session()->regenerateToken()` |
| Password Confirm | `$request->session()->put('auth.password_confirmed_at', time())` |

### Expiry

- Lifetime: 120 minutes (from config)
- No "remember me" session extension beyond standard cookie refresh
- Driver: `file` (writes to `storage/framework/sessions/`)

### Session Table (database driver)

The `sessions` table already has columns for the identity migration:

```sql
-- Existing columns (from Laravel default migration):
user_id, ip_address, user_agent, payload, last_activity

-- Additional columns (from custom migration 2026_07_08_000008):
account_id, current_tenant_membership_id  -- NULLABLE, ready for Phase 6
```

---

## Guards

### Current Configuration (`config/auth.php`)

```php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
],
```

| Property | Value |
|---|---|
| Guard name | `web` |
| Driver | `session` |
| Provider | `users` (Eloquent, `App\Models\User`) |
| Default guard | `web` (via `AUTH_GUARD` env, defaults to `web`) |

**Only one guard exists.** No `account` guard, no API guard, no custom guard.

### Guard Lifecycle

```
Auth::attempt() → Auth::guard('web')->attempt()
    ├─ Resolves 'web' guard from config
    ├─ Guard driver: 'session' → SessionGuard class
    ├─ Guard provider: 'users' → EloquentUserProvider with App\Models\User
    ├─ SessionGuard::attempt():
    │   ├─ Calls provider->retrieveByCredentials(['email' => ..., 'password' => ...])
    │   ├─ Calls provider->validateCredentials(user, ['password' => ...])
    │   ├─ If valid: calls $this->login(user)
    │   │   ├─ Sets session: session()->put($this->getName(), user->id)
    │   │   ├─ Handles remember token
    │   │   ├─ Regenerates session
    │   │   └─ Fires Illuminate\Auth\Events\Login
    │   └─ Returns true/false
    └─ Auth::user() returns the authenticated User model
```

### Account Guard Readiness

The `Account` model is fully compatible with the `session` guard driver:
- Extends `Illuminate\Foundation\Auth\User` (same base class as User)
- Implements `Illuminate\Contracts\Auth\Authenticatable` (all required methods)
- Uses `'password' => 'hashed'` cast (same bcrypt format)
- Has `email`, `password`, `remember_token` columns (same schema pattern)

**What's missing:** An `accounts` provider entry and an `accounts` guard entry in `config/auth.php`.

---

## Providers

### Current Configuration

```php
'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model' => env('AUTH_MODEL', App\Models\User::class),
    ],
],
```

| Property | Value |
|---|---|
| Provider name | `users` |
| Driver | `eloquent` |
| Model | `App\Models\User` (default, overridable via `AUTH_MODEL` env) |

### Provider Lifecycle

```
EloquentUserProvider::retrieveByCredentials(['email' => '...', 'password' => '...'])
    ├─ Ignores non-credential fields (password, remember)
    ├─ Queries User::where('email', '...')->first()
    └─ Returns User model or null

EloquentUserProvider::validateCredentials(User, ['password' => '...'])
    ├─ Hash::check('plain-text-password', User->getAuthPassword())
    │   ├─ getAuthPassword() returns $this->password from User model
    │   ├─ stored hash is bcrypt with 12 rounds
    │   └─ Hash::check() uses PHP password_verify()
    └─ Returns bool

EloquentUserProvider::retrieveById(1)
    ├─ User::find(1)
    └─ Used for session-to-user restoration (every request)
```

### Account Provider Readiness

Adding an `accounts` provider requires only a config entry:

```php
'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model' => App\Models\User::class,
    ],
    'accounts' => [
        'driver' => 'eloquent',
        'model' => App\Models\Account::class,
    ],
],
```

The `Account` model is fully compatible with `EloquentUserProvider`:
- Has `getAuthIdentifier()` (returns `$this->id`) — inherited from Authenticatable
- Has `getAuthPassword()` (returns `$this->password`) — inherited, `'hashed'` cast applied
- Has `getRememberToken()` / `setRememberToken()` / `getRememberTokenName()` — inherited
- Uses `'password' => 'hashed'` cast — same bcrypt format as User
- `find()` and `where('email')` queries work on the `accounts` table

---

## Password Verification

### Mechanism

| Layer | Implementation |
|---|---|
| Hashing algorithm | bcrypt |
| Rounds | 12 (`.env: BCRYPT_ROUNDS=12`) |
| User cast | `'password' => 'hashed'` — auto-hashes on set |
| Account cast | `'password' => 'hashed'` — identical |
| Login verification | `Hash::check(plaintext, user->password)` via EloquentUserProvider |
| Direct verification | `password_verify(plaintext, user->getAuthPassword())` in IdentityResolver |
| Change password | `Hash::make($newPassword)` in PasswordController |
| Reset password | `Hash::make($newPassword)` in NewPasswordController |

### Compatibility

Both User and Account models:
- Store passwords as bcrypt hashes with 12 rounds
- Use `'password' => 'hashed'` cast
- `getAuthPassword()` returns `$this->password`

**Result:** A User's password hash is directly copyable to an Account record and vice versa. No migration transformation needed. The `CompatibilityBridge::userToAccount()` already copies `password` as-is.

---

## Remember Me

### Current Implementation

```
LoginRequest::authenticate()
    → Auth::attempt(credentials, $this->boolean('remember'))
```

- Controlled by "remember me" checkbox on login forms
- `Auth::attempt()` → `SessionGuard::attempt()`:
  - If `$remember` is true: updates `user->remember_token` with random 60-char string
  - Sets cookie containing user ID + remember token hash
  - On subsequent requests without session: `SessionGuard::user()` checks the remember cookie
- Both User and Account models have `remember_token` in `$fillable` and `$hidden`

### Account Compatibility

The `Account` model has `remember_token` in both `$fillable` and `$hidden` — identical to User. No changes needed for remember-me support.

---

## Email Verification

### Current State

| Component | User Model | Account Model |
|---|---|---|
| Implements `MustVerifyEmail` | ✅ Yes | ✅ Yes |
| Uses `MustVerifyEmail` trait | ✅ Yes (`use \Illuminate\Auth\MustVerifyEmail`) | ❌ **Missing** |
| `sendEmailVerificationNotification()` | ✅ Inherited from trait | ❌ **Will cause error** |
| `hasVerifiedEmail()` | ✅ Inherited from trait | ❌ **Will cause error** |
| `markEmailAsVerified()` | ✅ Inherited from trait | ❌ **Will cause error** |
| `getEmailForVerification()` | ✅ Inherited | ❌ **Will cause error** |

### Critical Gap

The `Account` model declares `implements MustVerifyEmail` but does NOT `use \Illuminate\Auth\MustVerifyEmail`. The `MustVerifyEmail` interface requires:
- `hasVerifiedEmail(): bool`
- `markEmailAsVerified(): bool`
- `sendEmailVerificationNotification(): void`
- `getEmailForVerification(): string`

Without the trait, any code that calls these methods on an Account instance will get a fatal error. **This must be fixed before Phase 6 can proceed.**

### Verification Flow (User)

```
1. Register → event(new Registered($user))
2. Notification sent: $user->sendEmailVerificationNotification()
3. User clicks link: GET /verify-email/{id}/{hash}
4. VerifyEmailController:
   ├─ User::findOrFail($id)
   ├─ hash_equals(sha1(user->getEmailForVerification()), hash)
   ├─ user->markEmailAsVerified() → sets email_verified_at = now()
   ├─ event(new Verified($user))  ← This triggers ActivateTenantOnVerified
   └─ Redirect to onboarding or login
```

### Account Verification Gap

The `VerifyEmailController` hardcodes `User::findOrFail($id)`. For Account verification, a separate controller or guard-aware lookup is needed.

### Event Listener Gap

**Critical:** `ActivateTenantOnVerified` listener exists at `app/Listeners/ActivateTenantOnVerified.php` but is **NOT registered** in `EventServiceProvider.php` and event auto-discovery is **NOT configured** in `bootstrap/app.php`. This means:

1. When a User verifies their email, `event(new Verified($user))` fires
2. No listener catches this event
3. The tenant stays in `pending` status forever
4. The owner never gets the `WelcomeOwner` notification

This is a pre-existing production bug affecting the existing User-based auth, not just the new Account auth.

---

## Password Reset

### Current State

| Component | User Model | Account Model |
|---|---|---|
| `sendPasswordResetNotification($token)` | ✅ Custom — generates tenant-aware reset URL | ❌ **Missing** — inherited base would use generic URL |
| Password broker | `users` provider → `password_reset_tokens` table | ❌ **Not configured** |
| Reset table | `password_reset_tokens` (existing) | `password_reset_tokens_new` exists (migration applied, table ready) |

### User Password Reset Flow

```
1. Request: POST /forgot-password (email)
2. PasswordResetLinkController → Password::sendResetLink()
3. Password broker looks up User by email → generates token
4. Token stored in password_reset_tokens table (expires 60min)
5. Notification sent: User->sendPasswordResetNotification($token)
   └─ Custom: includes store slug in reset URL for tenant users
6. User clicks link: GET /reset-password/{token}?email=...
7. NewPasswordController → Password::reset()
   ├─ Validates token, email, new password
   ├─ Updates user->password = Hash::make($newPassword)
   ├─ Updates user->remember_token
   ├─ Fires event(new PasswordReset($user))
   └─ Redirects: tenant user → /store/{slug}/login, others → /login
```

### Account Password Reset Readiness

The `password_reset_tokens_new` table already exists with `account_id` as the primary key (migration `2026_07_08_000002`). An `accounts` password broker entry in `config/auth.php` would use this table:

```php
'passwords' => [
    'users' => [
        'provider' => 'users',
        'table' => 'password_reset_tokens',
        'expire' => 60,
        'throttle' => 60,
    ],
    'accounts' => [
        'provider' => 'accounts',
        'table' => 'password_reset_tokens_new',
        'expire' => 60,
        'throttle' => 60,
    ],
],
```

However, the `Account` model needs `sendPasswordResetNotification()` to generate tenant-aware reset URLs.

---

## Authentication Middleware

### Global Web Middleware Stack (appended in order)

```
1. IdentifyTenant::class
2. HandleInertiaRequests::class
3. CheckUserStatus::class
4. CheckMaintenanceMode::class
```

### Alias Middleware

| Alias | Class | Purpose | Account Impact |
|---|---|---|---|
| `role` | `RoleMiddleware` | Spatie role check (superadmin bypass for admin) | ❌ Hardcodes `$user->hasRole()` — User-specific |
| `tenant.active` | `EnsureTenantIsActive` | Tenant status + subscription health | ✅ Tenant-based, not user-based |
| `tenant.locked` | `CheckStoreLocked` | Blocks mutations on locked tenants | ✅ Tenant-based |
| `tenant.valid` | `TenantIsValid` | Ensures user has valid tenant_id | ❌ Checks `$user->tenant_id` |
| `storefront` | `Storefront` | Resolves tenant from URL slug | ✅ Tenant-based |
| `tenant.access` | `CheckTenantAccess` | Cross-tenant guard | ❌ Compares `$user->tenant_id` |
| `tenant.binding` | `ValidateTenantBinding` | Route model binding tenant check | ✅ Checks model tenant_id |

### Middleware Account Readiness

| Middleware | User-specific Code | Fix Needed for Account |
|---|---|---|
| `IdentifyTenant` | `$user->tenant_id` → Tenant::find() | Use IdentityContext + MembershipResolver for Account users |
| `RoleMiddleware` | `$user->hasRole()`, `$user->getAllPermissions()` | Use AuthorizationResolver for Account users |
| `CheckUserStatus` | `$user->status`, `$user->hasRole()` | Check Account status, resolve role via membership |
| `CheckTenantAccess` | `$user->tenant_id !== $currentTenant->id` | Check active membership tenant |
| `TenantIsValid` | `$user->tenant_id` existence | Check active membership existence |
| `HandleInertiaRequests` | `$request->user()` → User properties | Branch on User vs Account for shared data |

---

## Authentication Components

### Complete Component Map

```
┌─────────────────────────────────────────────────────────────┐
│                    AUTHENTICATION SYSTEM                      │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  CONFIGURATION                                                │
│  ├─ config/auth.php          — Guards, providers, passwords  │
│  ├─ config/identity.php      — Feature flags (all false)     │
│  └─ .env                     — BCRYPT_ROUNDS=12, SESSION_*   │
│                                                               │
│  CONTROLLERS (Breeze + Custom)                                │
│  ├─ AuthenticatedSessionController   — Root /login           │
│  ├─ StorefrontLoginController         — /store/{slug}/login   │
│  ├─ RegisteredUserController          — Register new users    │
│  ├─ CreateStoreController             — Register new stores   │
│  ├─ VerifyEmailController             — Email verification    │
│  ├─ EmailVerificationPromptController  — Verify notice        │
│  ├─ EmailVerificationNotificationController — Resend verify   │
│  ├─ PasswordResetLinkController       — Forgot password       │
│  ├─ NewPasswordController             — Reset password        │
│  ├─ ConfirmablePasswordController     — Password confirmation  │
│  └─ PasswordController                — Change password       │
│                                                               │
│  REQUESTS                                                    │
│  └─ LoginRequest                      — authenticate()        │
│                                                               │
│  MIDDLEWARE                                                  │
│  ├─ IdentifyTenant          (global web)                     │
│  ├─ HandleInertiaRequests   (global web)                     │
│  ├─ CheckUserStatus         (global web)                     │
│  ├─ RoleMiddleware          (alias: role)                    │
│  ├─ CheckTenantAccess       (alias: tenant.access)           │
│  ├─ TenantIsValid           (alias: tenant.valid)            │
│  ├─ Storefront              (alias: storefront)              │
│  ├─ EnsureTenantIsActive    (alias: tenant.active)           │
│  ├─ CheckStoreLocked        (alias: tenant.locked)           │
│  └─ ValidateTenantBinding   (alias: tenant.binding)          │
│                                                               │
│  MODELS                                                      │
│  ├─ User    — Current auth model, HasRoles, MustVerifyEmail  │
│  └─ Account — Future auth model, dormant, missing traits     │
│                                                               │
│  EVENTS                                                      │
│  ├─ Verified      (Laravel) — fires on email verification    │
│  ├─ PasswordReset (Laravel) — fires on password reset        │
│  └─ Registered    (Laravel) — fires on registration          │
│                                                               │
│  LISTENERS                                                   │
│  └─ ActivateTenantOnVerified — EXISTS but NOT REGISTERED     │
│                                                               │
│  IDENTITY FOUNDATION (Phases 3-5, all dormant)               │
│  ├─ IdentityResolver                                         │
│  ├─ MembershipResolver                                       │
│  ├─ CurrentRoleResolver                                      │
│  ├─ AuthorizationResolver                                    │
│  ├─ TenantContextResolver                                    │
│  ├─ IdentityContext                                          │
│  ├─ AuthorizationContext                                      │
│  ├─ CompatibilityBridge                                      │
│  └─ 6 contracts                                             │
│                                                               │
└─────────────────────────────────────────────────────────────┘
```

---

## Identity Foundation Integration

### Current State of Phases 3-5

All 8 classes and 6 contracts are built, tested, and registered in the service container. They are fully dormant behind `config('identity.use_accounts')` which returns `false`.

| Class | Current Behavior | When `use_accounts=true` |
|---|---|---|
| `IdentityResolver` | `supportsAccount()` returns `false` | Returns `true` — enables Account lookups |
| `MembershipResolver` | Short-circuits at `use_accounts` check → returns `null` | Finds Account by email → resolves active TenantMembership |
| `CurrentRoleResolver` | `$user->getRoleNames()` via Spatie | Also resolves from `TenantMembership->role` via `resolveFromMembership()` |
| `AuthorizationResolver` | `Auth::user()->can()` via Spatie | Also checks `TenantMembership->hasPermission()` via `canViaMembership()` |
| `TenantContextResolver` | `Tenant::getCurrent()` — always works | No change needed |
| `IdentityContext` | Immutable value object — always works | No change needed |
| `AuthorizationContext` | Immutable value object — always works | No change needed |
| `CompatibilityBridge` | Maps User↔Account attributes | Used for data migration |

### What the Identity Foundation Provides

Phase 6 does NOT need to rewrite authentication logic. The foundation already provides:

1. **Membership resolution**: `MembershipResolver::resolve(Account)` → active `TenantMembership`
2. **Role resolution from membership**: `CurrentRoleResolver::resolveFromMembership(TenantMembership)` → role name
3. **Permission check via membership**: `AuthorizationResolver::canViaMembership(Membership, 'permission')` → bool
4. **Identity context creation**: `IdentityResolver::createContextFromIdentity(Account)` → hydrated IdentityContext
5. **User→Account mapping**: `CompatibilityBridge::userToAccount(User)` → Account with same attributes

---

## Feature Flag Integration Strategy

### The `IDENTITY_USE_ACCOUNTS` Flag

```php
// config/identity.php
'use_accounts' => env('IDENTITY_USE_ACCOUNTS', false),
```

This single env variable is the master switch for Phase 6. When `false` (production default), **nothing changes** — the entire existing auth system runs as-is. When `true`, the Account authentication chain activates.

### Flag Scope

| Flag | Affects | When `true` |
|---|---|---|
| `IDENTITY_USE_ACCOUNTS` | Auth guard, registration, login, tenant resolution | New registrations create Account + Membership. Login authenticates via Account. Tenant resolves from Membership. |

### Per-Component Gating Pattern

Every Phase 6 change follows this pattern:

```php
if (config('identity.use_accounts')) {
    // New Account-based behavior
} else {
    // Legacy User-based behavior (unchanged)
}
```

This ensures:
- Zero impact when the flag is `false`
- Clean toggle when the flag is flipped
- Easy rollback by setting the flag back to `false`
- No branching complexity in production — the flag stays `false` until the full chain is validated

---

## Risks

### Risk 1: Account Model Missing MustVerifyEmail Trait

**Severity:** HIGH  
**Description:** The Account model declares `implements MustVerifyEmail` but does not `use \Illuminate\Auth\MustVerifyEmail`. Any code that calls `$account->hasVerifiedEmail()`, `$account->markEmailAsVerified()`, `$account->sendEmailVerificationNotification()`, or `$account->getEmailForVerification()` will trigger a fatal error.

**Mitigation:** Add `use \Illuminate\Auth\MustVerifyEmail;` to the Account model before enabling the feature flag. Without this fix, Account registration + email verification is non-functional.

### Risk 2: ActivateTenantOnVerified Listener Not Registered

**Severity:** HIGH (pre-existing)  
**Description:** The `ActivateTenantOnVerified` listener exists at `app/Listeners/ActivateTenantOnVerified.php` but is not registered in `EventServiceProvider.php` or via auto-discovery. When a tenant owner verifies their email, the tenant stays in `pending` status forever. This affects both User-based and Account-based auth.

**Mitigation:** Register the listener in `EventServiceProvider.php`:
```php
protected $listen = [
    \Illuminate\Auth\Events\Verified::class => [
        \App\Listeners\ActivateTenantOnVerified::class,
    ],
    // ... existing listeners
];
```

### Risk 3: VerifyEmailController Hardcodes User Model

**Severity:** MEDIUM  
**Description:** `VerifyEmailController::__invoke($request, $id, $hash)` uses `User::findOrFail($id)`. For Account verification, this will fail if the ID happens to exist in users but not accounts, or worse, verify the wrong model.

**Mitigation:** Create a guard-aware verification controller or branch logic based on whether the ID exists in the `accounts` table.

### Risk 4: Dual-Identity Session Conflicts

**Severity:** MEDIUM  
**Description:** A user with both a User record and an Account record could theoretically be authenticated on both the `web` guard (User) and `accounts` guard (Account) simultaneously with different session data.

**Mitigation:** The `LoginRequest` should attempt only one guard per login attempt. If authenticating via the `accounts` guard, do not fall back to the `users` guard. The session table's existing `account_id` column enables per-guard session tracking.

### Risk 5: Middleware Hardcodes User-Specific Checks

**Severity:** MEDIUM  
**Description:** Five middleware components reference `$user->tenant_id`, `$user->hasRole()`, `$user->status`, and other User-specific properties. When the authenticated identity is an Account (via the `accounts` guard), `Auth::user()` returns an Account, which lacks these properties.

**Mitigation:** Each middleware needs an `instanceof` check or method_exists() guard to handle both User and Account identities. The `CurrentRoleResolver` already provides `resolveFromMembership()` for this purpose.

### Risk 6: IdentityResolver::supportsAccount() Hardcoded to False

**Severity:** LOW  
**Description:** `IdentityResolver::supportsAccount()` returns `false` unconditionally. The `MembershipResolver` checks `config('identity.use_accounts')` directly, so it's not blocked by this, but the public API is inconsistent.

**Mitigation:** Update `supportsAccount()` to return the actual config value:
```php
public function supportsAccount(): bool
{
    return config('identity.use_accounts');
}
```

### Risk 7: No Existing Account Records

**Severity:** LOW  
**Description:** The `accounts` table has zero rows. Flipping the flag to `true` before a data migration runs means new registrations create Accounts while existing users remain on Users. All existing sessions continue working on the `web` guard.

**Mitigation:** This is by design. Phase 6 covers NEW registrations only. Existing User migration is a separate phase. No migration script is needed for Phase 6.

---

## Compatibility Review

### What Stays Unchanged

| Component | Guarantee |
|---|---|
| `Auth::user()` for existing sessions | Continues returning `User` on `web` guard |
| `auth()->user()->can('permission')` | Continues working for User-based sessions |
| `auth()->user()->hasRole('admin')` | Continues working for User-based sessions |
| `@can('permission')` in Blade | Continues working for User-based sessions |
| `role:admin` middleware | Continues working for User-based sessions |
| All existing controllers | Untouched — no modifications |
| All existing policies | Untouched — no modifications |
| All existing routes | Untouched — no route changes |
| `config/auth.php` defaults | `web` guard, `users` provider remain default |
| `config/permission.php` | Untouched — Spatie unchanged |
| All existing middleware | Untouched — modifications are additive |
| Login form UX | Untouched — same forms, same fields |
| Session lifetime | Untouched — 120 minutes |
| Password hashing | Untouched — bcrypt, 12 rounds |

### What Changes (Flag-Gated)

| Component | Change | Gate |
|---|---|---|
| `config/auth.php` | Add `accounts` guard + provider | Always added (no runtime gate needed — unused entries are harmless) |
| `LoginRequest::authenticate()` | Try `accounts` guard first, fall back to `web` | `config('identity.use_accounts')` |
| `StorefrontLoginController` | Support Account-based tenant verification | `config('identity.use_accounts')` |
| `RegisteredUserController` | Create Account + TenantMembership instead of User | `config('identity.use_accounts')` |
| `CreateStoreController` | Create Account-based owner | `config('identity.use_accounts')` |
| `TenantBootstrapService` | Create Account + Membership during bootstrap | `config('identity.use_accounts')` |
| `IdentifyTenant` | Resolve tenant from Account membership | `config('identity.use_accounts')` |
| `HandleInertiaRequests` | Share Account data + membership roles | `config('identity.use_accounts')` |
| `RoleMiddleware` | Check roles via membership | `config('identity.use_accounts')` |
| `CheckUserStatus` | Check Account status | `config('identity.use_accounts')` |
| `CheckTenantAccess` | Check membership tenant match | `config('identity.use_accounts')` |
| `TenantIsValid` | Check membership existence | `config('identity.use_accounts')` |
| `RoleMiddleware` | Check Account roles via CurrentRoleResolver | `config('identity.use_accounts')` |
| `Account` model | Add missing `MustVerifyEmail` trait | Always (bug fix, not gated) |
| `IdentityResolver` | Update `supportsAccount()` to check config | Always (return value changes with config) |
| `ActivateTenantOnVerified` | Register in EventServiceProvider | Always (bug fix, not gated) |

---

## Recommended Implementation Strategy

### Principle

Implement Phase 6 in 8 ordered steps. Each step is independently deployable behind the `IDENTITY_USE_ACCOUNTS` flag. Steps 1-2 are pre-requisites (bug fixes, config). Steps 3-8 can be implemented incrementally.

### Implementation Order

#### Step 1: Bug Fixes (Not Flag-Gated)

**Files:** `app/Models/Account.php`, `app/Providers/EventServiceProvider.php`, `app/Auth/IdentityResolver.php`

| Fix | Description | Risk |
|---|---|---|
| Add `MustVerifyEmail` trait to Account | `use \Illuminate\Auth\MustVerifyEmail;` | None — required for Account auth to function |
| Register `ActivateTenantOnVerified` | Add `Verified::class => [ActivateTenantOnVerified::class]` to EventServiceProvider | None — fixes pre-existing production bug |
| Update `supportsAccount()` | Return `config('identity.use_accounts')` instead of `false` | None — method was always intended to be dynamic |

#### Step 2: Auth Configuration (Always Applied)

**Files:** `config/auth.php`

Add dormant guard, provider, and password broker entries:

```php
'guards' => [
    'web' => ['driver' => 'session', 'provider' => 'users'],
    'accounts' => ['driver' => 'session', 'provider' => 'accounts'],
],
'providers' => [
    'users' => ['driver' => 'eloquent', 'model' => App\Models\User::class],
    'accounts' => ['driver' => 'eloquent', 'model' => App\Models\Account::class],
],
'passwords' => [
    'users' => ['provider' => 'users', 'table' => 'password_reset_tokens'],
    'accounts' => ['provider' => 'accounts', 'table' => 'password_reset_tokens_new'],
],
```

**Rationale:** Unused config entries in `auth.php` are harmless. Laravel ignores them until referenced by name. Adding them now means the `accounts` guard/provider are available but unused — the flag gate determines when they're actively used.

#### Step 3: Dual-Guard LoginRequest (Flag-Gated)

**Files:** `app/Http/Requests/Auth/LoginRequest.php`

Modify `authenticate()` to try `Auth::guard('accounts')->attempt()` when the flag is true. On failure, fall back to the existing `Auth::attempt()` (web guard).

```php
public function authenticate(): void
{
    $this->ensureIsNotRateLimited();

    $authenticated = false;

    if (config('identity.use_accounts')) {
        $authenticated = Auth::guard('accounts')
            ->attempt($this->only('email', 'password'), $this->boolean('remember'));
    }

    if (! $authenticated) {
        $authenticated = Auth::attempt($this->only('email', 'password'), $this->boolean('remember'));
    }

    if (! $authenticated) {
        RateLimiter::hit($this->throttleKey());
        throw ValidationException::withMessages([...]);
    }

    RateLimiter::clear($this->throttleKey());
}
```

#### Step 4: Dual-Model Login Controllers (Flag-Gated)

**Files:** `app/Http/Controllers/StorefrontLoginController.php`, `app/Http/Controllers/Auth/AuthenticatedSessionController.php`

- `StorefrontLoginController::store()`: When `use_accounts=true`, find Account by email for pre-checks, verify membership tenant match post-auth.
- `AuthenticatedSessionController::store()`: When `use_accounts=true`, allow Account-based superadmins (root login gateway).
- `destroy()`: When `use_accounts=true`, logout from the correct guard.

#### Step 5: Dual-Model Registration (Flag-Gated)

**Files:** `app/Http/Controllers/Auth/RegisteredUserController.php`, `app/Http/Controllers/CreateStoreController.php`

- `RegisteredUserController::store()`: When `use_accounts=true`, create Account + TenantMembership (+ `customer` role on membership) instead of User. Log into `accounts` guard.
- `CreateStoreController::store()`: When `use_accounts=true`, create Account + TenantMembership (+ `admin` role and `is_owner=true`) during store bootstrap.

#### Step 6: TenantBootstrapService Account Support (Flag-Gated)

**Files:** `app/Services/TenantBootstrapService.php`

- `createOwner()`: When `use_accounts=true`, create Account instead of User, create TenantMembership with admin role.
- `ensureCustomerRole()`: When `use_accounts=true`, create TenantMembership with customer role for new customer registrations.

#### Step 7: Identity-Aware Middleware (Flag-Gated)

**Files:** `app/Http/Middleware/IdentifyTenant.php`, `app/Http/Middleware/CheckUserStatus.php`, `app/Http/Middleware/RoleMiddleware.php`, `app/Http/Middleware/CheckTenantAccess.php`, `app/Http/Middleware/TenantIsValid.php`

Each middleware needs an `instanceof` check or `method_exists()` guard:

```php
$user = Auth::user();

if (config('identity.use_accounts') && $user instanceof \App\Models\Account) {
    // Resolve via IdentityContext / MembershipResolver
} else {
    // Legacy User-based logic (unchanged)
}
```

The identity foundation (Phases 3-5) already provides the resolution methods — middleware just needs to call them.

#### Step 8: Inertia Auth Sharing (Flag-Gated)

**Files:** `app/Http/Middleware/HandleInertiaRequests.php`

When `use_accounts=true` and `$request->user()` is an Account:
- Use `CurrentRoleResolver` to resolve roles
- Use `AuthorizationContext` to determine admin/superadmin/customer status
- Share Account data (email, status, etc.) instead of User data in `$page.props.auth.user`

### Post-Implementation Validation

After each step, test with both `IDENTITY_USE_ACCOUNTS=false` and `IDENTITY_USE_ACCOUNTS=true`:

| Test | false | true |
|---|---|---|
| SuperAdmin login (root /login) | ✅ Works as before | ✅ Works (superadmin Account or User) |
| Storefront admin login | ✅ Works as before | ✅ Works (admin Account or User) |
| Customer registration | ✅ Creates User | ✅ Creates Account + Membership |
| Email verification | ✅ Works (after fixing listener) | ✅ Works (after fixing Account trait) |
| Password reset | ✅ Works as before | ✅ Works for Account users |
| Session handling | ✅ User sessions | ✅ Account sessions (account_id in session) |
| Tenant resolution | ✅ From user->tenant_id | ✅ From TenantMembership |
| Role checking | ✅ Spatie hasRole() | ✅ Membership->role + CurrentRoleResolver |
| Permission checking | ✅ Spatie can() | ✅ AuthorizationResolver::can() |
| Logout | ✅ Session cleared, redirected | ✅ Session cleared from correct guard |

---

## Phase 6 Readiness

### Pre-Existing Infrastructure

| Component | Status | Phase |
|---|---|---|
| Account model | ✅ Exists | Phase 2 |
| TenantMembership model | ✅ Exists | Phase 2 |
| Account migration | ✅ Applied | Phase 1 |
| TenantMembership migration | ✅ Applied | Phase 1 |
| Session account_id column | ✅ Applied (2026_07_08_000008) | Phase 1 |
| Password reset new table | ✅ Applied (2026_07_08_000002) | Phase 1 |
| IdentityResolver | ✅ Built, dormant | Phase 3 |
| MembershipResolver | ✅ Built, dormant | Phase 4 |
| CurrentRoleResolver | ✅ Built, dormant | Phase 5 |
| AuthorizationResolver | ✅ Built, dormant | Phase 5 |
| TenantContextResolver | ✅ Built, dormant | Phase 4 |
| CompatibilityBridge | ✅ Built, dormant | Phase 3 |
| Feature flag (use_accounts) | ✅ Defined, defaults to false | Phase 3 |
| Service provider registrations | ✅ Done | Phase 4 |

### Blockers

| Blocker | Affects | Fix |
|---|---|---|
| Account model missing `MustVerifyEmail` trait | Email verification for Account users | Add `use \Illuminate\Auth\MustVerifyEmail;` to Account model |
| `ActivateTenantOnVerified` listener not registered | Tenant activation on email verification | Register in EventServiceProvider |
| `VerifyEmailController` hardcodes `User::findOrFail()` | Account email verification | Add Account-aware lookup or separate controller |
| Account model missing `sendPasswordResetNotification()` | Password reset for Account users | Add method to Account model (copy pattern from User) |
| All middleware hardcodes User-specific properties | All middleware | Add `instanceof` guards |

### Effort Estimate

| Step | Files | Complexity | Risk |
|---|---|---|---|
| 1. Bug fixes | 3 | Low | None |
| 2. Auth config | 1 | Low | None |
| 3. Dual-guard LoginRequest | 1 | Low | Low |
| 4. Dual-model login controllers | 2 | Medium | Low |
| 5. Dual-model registration | 2 | Medium | Low |
| 6. TenantBootstrapService | 1 | Medium | Low |
| 7. Identity-aware middleware | 5 | Medium | Low |
| 8. Inertia auth sharing | 1 | Low | Low |

---

## Final Recommendation

**Proceed with Phase 6 implementation following the 8-step strategy outlined above.**

### Rationale

1. **The foundation is fully built.** Phases 1-5 have completed all prerequisites: database migrations, models, relationships, authentication context, membership resolution, and authorization layer. The only missing piece is the glue that connects the existing User-based auth to the dormant Account infrastructure.

2. **The feature flag is already defined.** `IDENTITY_USE_ACCOUNTS` in `config/identity.php` is the master switch. All Phase 6 changes are gated behind it. Production deployments continue with the flag `false` until the full chain is validated.

3. **Bug fixes benefit both systems.** The `ActivateTenantOnVerified` registration bug and the `Account::MustVerifyEmail` trait gap affect both current and future auth. Fixing them in Step 1 is a net improvement regardless of Phase 6.

4. **Additive-only modifications.** No existing code is removed or refactored. All changes are `if (config('identity.use_accounts')) { ... } else { ... }` branches. Rollback is a single env variable toggle.

5. **The identity foundation provides ready-made resolution.** `MembershipResolver`, `CurrentRoleResolver`, `AuthorizationResolver`, and `AuthorizationContext` are already built and container-registered. Middleware changes in Step 7 are primarily wiring these existing services into the middleware logic.

### Pre-Implementation Checklist

Before writing any Phase 6 code:

- [ ] Fix Account model: add `MustVerifyEmail` trait
- [ ] Fix Account model: add `sendPasswordResetNotification()` method
- [ ] Register `ActivateTenantOnVerified` in `EventServiceProvider`
- [ ] Update `IdentityResolver::supportsAccount()` to return `config('identity.use_accounts')`
- [ ] Add `accounts` guard, provider, and password broker to `config/auth.php`
- [ ] Verify all 9 identity support migrations are applied (they are, per Phase 1)

### Go/No-Go Criteria

| Criteria | Status |
|---|---|
| All Phase 1-5 deliverables complete | ✅ Verified |
| Account model exists and implements Authenticatable | ✅ Verified |
| TenantMembership model exists with all relationships | ✅ Verified |
| IdentityResolver/MembershipResolver/AuthorizationResolver in container | ✅ Verified |
| Feature flag defined and defaults to false | ✅ Verified |
| Session table has account_id column | ✅ Verified |
| Password reset new table exists | ✅ Verified |
| Blockers identified and documented | ✅ Documented (4 blockers) |
| Implementation strategy reviewed | ✅ Documented (8 steps) |
| All 5 identity foundation documents followed | ✅ Verified |

**Decision: READY FOR PHASE 6 IMPLEMENTATION**
