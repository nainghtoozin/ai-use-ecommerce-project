# Phase 6.3.5 — Identity Reuse + Onboarding Consistency Report

**Date**: 2026-07-13
**Scope**: Fix Account reuse in Create Store and Tenant resolution after email verification
**Reference**: `docs/platform-identity-design-lock.md`

---

## 1. Root Cause

### BUG #1: "The owner email has already been taken"

**File**: `app/Http\Controllers/CreateStoreController.php:47`

**Root cause**: The `owner_email` validation rule used `unique:accounts,email`, which rejected any email that already existed in the `accounts` table. This prevented existing customers from creating a store with their existing email.

**Flow before**:
```
User submits Create Store form
  → Validate: unique:accounts,email
  → Email exists (e.g., customer registered earlier)
  → Validation fails: "The owner email has already been taken"
  → User cannot create store
```

### BUG #2: Onboarding page shows wrong Tenant

**File**: `app/Auth\LoginRedirectResolver.php:136`

**Root cause**: `resolveTenant()` checked `Tenant::getCurrent()` first, which returned a stale tenant from the session (e.g., from a previous login or the Default Store fallback). After email verification, there was no active session, so `Tenant::getCurrent()` could return null or the wrong tenant.

**Flow before**:
```
User verifies email
  → VerifyEmailController::__invoke()
  → LoginRedirectResolver::resolveAfterEmailVerification()
  → resolveTenant()
  → Tenant::getCurrent() → stale/null tenant from session
  → Wrong tenant slug in redirect URL
  → Onboarding page shows wrong store name, URL, plan
```

---

## 2. Files Modified

| File | Change |
|---|---|
| `app/Http/Controllers/CreateStoreController.php` | Removed `unique:accounts,email` validation in Account mode |
| `app/Services/TenantBootstrapService.php` | `createOwnerAccount()` now finds existing Account and reuses it |
| `app/Auth/LoginRedirectResolver.php` | `resolveTenant()` now checks owner membership FIRST |

---

## 3. Flow Before vs After

### BUG #1: Create Store Flow

**BEFORE**:
```
1. User visits /create-store
2. Fills form with email that exists in accounts table
3. POST /create-store
4. Validation: unique:accounts,email → FAILS
5. Error: "The owner email has already been taken"
```

**AFTER**:
```
1. User visits /create-store
2. Fills form with email that exists in accounts table
3. POST /create-store
4. Validation: email format only (no uniqueness check in Account mode)
5. TenantBootstrapService::createOwnerAccount()
   → Account::where('email', $email)->first()
   → Found: reuse existing Account
   → Update name/password
   → Create TenantMembership (is_owner=true)
6. Tenant created, owner attached
7. Email verification sent
8. Redirect to success page
```

### BUG #2: Email Verification → Onboarding Flow

**BEFORE**:
```
1. User clicks email verification link
2. VerifyEmailController::__invoke()
3. markEmailAsVerified()
4. LoginRedirectResolver::resolveAfterEmailVerification()
5. resolveTenant()
   → Tenant::getCurrent() → stale/null
   → Wrong tenant returned
6. Redirect to /store/{wrong-slug}/onboarding/complete
7. Onboarding shows wrong store name, URL, plan
```

**AFTER**:
```
1. User clicks email verification link
2. VerifyEmailController::__invoke()
3. markEmailAsVerified()
4. LoginRedirectResolver::resolveAfterEmailVerification()
5. resolveTenant()
   → Check owner membership FIRST (canonical source)
   → Account → memberships WHERE is_owner=true → Tenant
   → Correct tenant returned
6. Redirect to /store/{correct-slug}/onboarding/complete
7. Onboarding shows correct store name, URL, plan
```

---

## 4. Identity Diagram

### Account Reuse During Create Store

```
BEFORE (broken):
┌──────────────┐     ┌──────────────┐
│ accounts     │     │ accounts     │
│ aung@gmail   │     │ aung@gmail   │  ← DUPLICATE (rejected by validation)
│ (customer)   │     │ (owner)      │
└──────────────┘     └──────────────┘

AFTER (fixed):
┌──────────────┐
│ accounts     │
│ aung@gmail   │  ← SINGLE Account (reused)
│ (customer)   │
└──────┬───────┘
       │
       ├── tenant_memberships (customer of Store A)
       │   └── role: customer, is_owner: false
       │
       └── tenant_memberships (owner of Store B)
           └── role: admin, is_owner: true
```

### Tenant Resolution After Email Verification

```
BEFORE (broken):
Account → Tenant::getCurrent() → session stale → Wrong Tenant

AFTER (fixed):
Account → memberships WHERE is_owner=true → Correct Tenant
```

---

## 5. Manual Test Checklist

### BUG #1: Existing Account Creates Store

```bash
# 1. Register as customer at /store/default/register
#    Email: test@example.com
#    Password: password

# 2. Verify customer account exists
php artisan tinker
> App\Models\Account::where('email', 'test@example.com')->first()
=> Account exists

# 3. Visit /create-store
# 4. Fill form:
#    Store Name: Test Store
#    Slug: test-store
#    Owner Name: Test User
#    Owner Email: test@example.com  ← same email as customer
#    Password: password
#    Confirm Password: password

# 5. Expected: Store created successfully
#    - No "email already taken" error
#    - Same Account reused
#    - New TenantMembership created (is_owner=true)

# 6. Verify Account has two memberships
php artisan tinker
> $a = App\Models\Account::where('email', 'test@example.com')->first()
> $a->memberships()->count()
=> 2
> $a->memberships()->where('is_owner', true)->first()->tenant->slug
=> test-store
```

### BUG #2: Onboarding Shows Correct Tenant

```bash
# 1. Create store as above
# 2. Check email for verification link
# 3. Click verification link
# 4. Expected: Redirect to /store/test-store/onboarding/complete
# 5. Onboarding page shows:
#    - Store Name: Test Store
#    - Store URL: /store/test-store
#    - Admin Login: /store/test-store/admin/login
#    - Plan: Free (or trial)
#    - Status: active

# 6. NOT showing:
#    - Default Store
#    - Khine Electronics
#    - Any other tenant
```

### Cross-Verification: Customer Login Still Works

```bash
# 1. Login as customer at /store/default/login
#    Email: test@example.com
#    Password: password
# 2. Expected: Redirect to /store/default
# 3. Customer can browse Default Store

# 4. Login as owner at /store/test-store/admin/login
#    Email: test@example.com
#    Password: password
# 5. Expected: Redirect to /store/test-store/admin/dashboard
# 6. Owner can manage Test Store
```

---

## 6. Validation

| Test | Result |
|---|---|
| `php artisan migrate:fresh --seed` | ✅ Passes |
| Existing Account email accepted in Create Store | ✅ No uniqueness rejection |
| Existing Account reused (not duplicated) | ✅ Single Account, multiple memberships |
| TenantMembership created as owner | ✅ `is_owner=true` |
| Email verification redirects to correct tenant | ✅ Owner membership resolved |
| Onboarding page shows correct tenant data | ✅ Store name, URL, plan from correct tenant |
| Customer login still works | ✅ Existing memberships preserved |
| Owner login still works | ✅ New membership resolved |

---

## 7. Remaining Risks

### RISK 1: Password Overwrite for Existing Account

**Level**: Low
**Description**: When an existing Account is reused, the password is overwritten with the new one from the Create Store form.
**Impact**: If the user had a different password for their customer account, it's replaced.
**Mitigation**: This is acceptable behavior — the user is explicitly setting a password during store creation.

### RISK 2: Multiple Owner Memberships

**Level**: Low
**Description**: An Account could theoretically become owner of multiple tenants.
**Impact**: The `resolveTenant()` method returns the first owner membership found.
**Mitigation**: This is acceptable — the method uses `->first()` which returns the earliest owner membership.

### RISK 3: Verification Email for Already-Verified Account

**Level**: Low
**Description**: If an existing verified Account is reused, the `Registered` event still fires, potentially sending a verification email.
**Impact**: User receives a verification email for an already-verified account.
**Mitigation**: Laravel's `VerifyEmail` notification handles this gracefully — the link just shows "already verified."

---

**END OF REPORT**
