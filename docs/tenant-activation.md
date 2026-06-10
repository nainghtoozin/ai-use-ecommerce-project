# Tenant Activation Flow

When a store owner verifies their email address, the tenant and subscription are automatically activated.

## Activation Flow

```
User clicks verification link (signed URL)
  │
  ├─ GET /verify-email/{id}/{hash}
  │    (no auth required — signed middleware + hash check)
  │
  ├─ VerifyEmailController
  │    ├─ hash_equals(sha1(email), $hash)  → 403 if mismatch
  │    ├─ markEmailAsVerified()             → sets email_verified_at
  │    ├─ fires Verified event
  │    └─ redirect to storefront admin login
  │
  ├─ ActivateTenantOnVerified (listener, auto-discovered)
  │    ├─ check: user is_owner && tenant status === 'pending'
  │    ├─ tenant.status       = 'active'
  │    ├─ tenant.activated_at = now()
  │    ├─ subscription.status  = 'active'
  │    ├─ subscription.starts_at = now()
  │    ├─ Tenant::clearDefaultCache()
  │    └─ send WelcomeOwner notification
  │
  └─ User redirected to /store/{slug}/admin/login
       with status=email-verified
```

## Files Changed

### Migration
- `database/migrations/xxxx_xx_xx_xxxxxx_add_activated_at_to_tenants_table.php`
  - Adds `activated_at` (timestamp, nullable) to `tenants` table

### Model
- `app/Models/Tenant.php`
  - `activated_at` added to `$fillable` and `$casts` (as `datetime`)

### Listener
- `app/Listeners/ActivateTenantOnVerified.php`
  - Sets `activated_at = now()` on tenant
  - Sends `WelcomeOwner` notification after activation

### Notifications
- `app/Notifications/WelcomeOwner.php`
  - Subject: "Your Store is Active — {store name}"
  - Contains login link to `/store/{slug}/admin/login`

### Controller
- `app/Http/Controllers/Auth/VerifyEmailController.php`
  - After verification: redirects to `storefront.admin.login` (not main login)
  - Uses `tenant` relationship to build the redirect URL

### Middleware
- `app/Http/Middleware/EnsureTenantIsActive.php`
  - Added `pending` check before `suspended` check
  - For `pending` status: redirects to `admin.suspended` with error "Please verify your email first."
  - Catches admin users who have not verified email but try to access operations routes

### Login Controller
- `app/Http/Controllers/StorefrontLoginController.php`
  - Added `pending` check: blocks admin login for pending tenants
  - Shows: "Please verify your email first."

## Middleware Changes

`EnsureTenantIsActive` (`tenant.active`) now checks status in this order:

1. **pending** → "Please verify your email first." → redirect to suspend page
2. **suspended** → redirect to suspension page
3. **banned / inactive** → "Your account is currently restricted."
4. **active / trialing** → proceed (normal flow)
5. Subscription checks follow (expired, canceled, etc.)

`StorefrontLoginController@store` now checks for `pending` status **before** checking `suspended`, specifically for admin users. Customer login is not blocked by pending status (customers can browse the storefront even when pending — the email verification is for the owner only).

## Test Checklist

### Positive Cases
- [ ] Owner clicks verification link → redirected to `/store/{slug}/admin/login?status=email-verified`
- [ ] Tenant status changes from `pending` to `active`
- [ ] `activated_at` timestamp is set
- [ ] Subscription status changes from `pending` to `active`
- [ ] `subscription.starts_at` is set to activation timestamp
- [ ] Welcome email is sent to owner
- [ ] Welcome email contains correct store admin login link
- [ ] After activation, admin can log in successfully
- [ ] After activation, admin can access operations routes

### Negative Cases
- [ ] Pending tenant admin tries to log in → "Please verify your email first."
- [ ] Pending tenant admin accesses protected route → "Please verify your email first."
- [ ] Second click on verification link → redirected to admin login (email already verified)
- [ ] Tampered hash → 403
- [ ] Expired signed URL → 403 (signed middleware)
- [ ] Owner verifies on different browser (no session) → works (no auth required)
- [ ] Existing verified owner clicks link → handled (already verified, redirect)
- [ ] Suspended tenant admin login → "Your account has been suspended."
- [ ] Non-owner user verification → does NOT trigger tenant activation

### Edge Cases
- [ ] SuperAdmin is never blocked by pending check
- [ ] Customer login is not blocked by tenant `pending` status
- [ ] Default tenant (slug: `default`) already has `status=active`, so unaffected
- [ ] `activated_at` stays null for tenants created before this migration (backward compatible)
