# UI Identity Display Improvements

## Problem

The top navigation and sidebar displayed a hardcoded role label — `"Administrator"` in the header, `"Admin"` in the sidebar — regardless of the authenticated user's actual role. The display name was either the raw `name` column (User) or the email (Account), with no resolution through membership profiles.

## Requirements

| Element | Before | After |
|---------|--------|-------|
| User name | `auth.user.name` (email for Account, raw column for User) | `auth.user.display_name` resolved via priority chain |
| Role label | `"Administrator"` / `"Admin"` (hardcoded) | `auth.user.role_label` from current membership |

## Display Name Priority Chain

```
1. Account.name              (Account model — email as fallback)
2. MerchantProfile.business_name  (via current TenantMembership)
3. CustomerProfile.name      (via current TenantMembership)
4. Account.email             (last resort)
```

## Role Label Resolution

```
Current Tenant → Current Membership → Current Role
                                         ↓
                    is_owner=true? → "Owner"
                    isSuperAdmin?  → "Super Admin"
                    role name:
                      admin    → "Admin"
                      customer → "Customer"
                      staff    → "Staff"
                      other    → str()->title()
```

For Legacy mode (User model), `User.name` column is used directly. Role label uses `is_owner` column and Spatie's `getRoleNames()`.

## Backend Changes

### Account Model — `app/Models/Account.php`

| Method | Behavior |
|--------|----------|
| `getNameAttribute()` | Now calls `getDisplayName()` instead of returning `$this->email`. This means `auth.user.name` in the frontend is now the resolved display name. |
| `getDisplayName()` | Priority: MerchantProfile.business_name → CustomerProfile.name → email |
| `getRoleLabel()` | Returns "Owner", "Super Admin", "Admin", "Customer", "Staff", or title-cased custom role. Resolved from current TenantMembership. |

### User Model — `app/Models/User.php`

| Method | Behavior |
|--------|----------|
| `getDisplayName()` | Returns `$this->name ?: $this->email` |
| `getRoleLabel()` | Returns "Super Admin", "Owner", or formatted first role name. |

### HandleInertiaRequests — `app/Http/Middleware/HandleInertiaRequests.php`

Added two fields to the shared `auth.user` object:

```php
$userData = [
    'name' => $displayName,          // Overrides old name field
    'display_name' => $displayName,  // Explicit display name field
    'role_label' => $roleLabel,      // Formatted role label
    // ... rest unchanged
];
```

## Frontend Changes

| File | Line | Before | After |
|------|------|--------|-------|
| `AdminHeader.jsx` | 91 | `<span>Administrator</span>` | `<span>{auth?.user?.role_label \|\| 'Administrator'}</span>` |
| `AdminSidebar.jsx` | 372 | `<p>Admin</p>` | `<p>{auth?.user?.role_label \|\| 'Admin'}</p>` |

The `auth.user.name` field now uses the resolved display name (not email for Account, not raw column for User). All existing references to `auth.user.name` (avatar initials, dropdown text, impersonation banner) automatically benefit.

## What Did NOT Need Changes

- **AppLayout.jsx** — Uses `auth?.user?.name` and `auth?.user?.is_admin`. Both work correctly with the updated name resolution.
- **PlatformNavbar.jsx, ShopNavbar.jsx** — Use `auth.user.name` for avatar and dropdown. No role labels displayed.
- **Blade templates** (`layouts/navigation.blade.php`, `admin/partials/navbar.blade.php`, `admin/partials/sidebar.blade.php`) — Use `Auth::user()->name`. In Legacy mode, User.name returns the display name. In Account mode (if Blade is used), `Account->name` accessor now returns profile-based name. No hardcoded role labels found.
- **Users/Show.jsx, Users/Index.jsx** — The "Owner" badge displayed for the *page subject* (not the authenticated user) is correct. It checks `user.is_owner` which is the correct field for that context.

## Legacy Mode Compatibility

When `config('identity.use_accounts')` is `false`:

- `User::getDisplayName()` returns `$this->name ?: $this->email` — same as before (User.name column).
- `User::getRoleLabel()` returns formatted role from Spatie's global `getRoleNames()` or `is_owner` column.
- `auth.user.name` is unchanged.
- `auth.user.display_name` and `auth.user.role_label` are new fields, ignored by existing frontend if not used.

## Files Modified

| File | Change |
|------|--------|
| `app/Models/Account.php` | Added `getDisplayName()`, `getRoleLabel()`. Updated `getNameAttribute()`. |
| `app/Models/User.php` | Added `getDisplayName()`, `getRoleLabel()`. |
| `app/Http/Middleware/HandleInertiaRequests.php` | Added `display_name` and `role_label` to shared user data. |
| `resources/js/Components/AdminHeader.jsx` | Replaced hardcoded `"Administrator"` with `auth?.user?.role_label`. |
| `resources/js/Components/AdminSidebar.jsx` | Replaced hardcoded `"Admin"` with `auth?.user?.role_label`. |

## Verification

| Scenario | Display Name | Role Label |
|----------|-------------|------------|
| Account = Owner in Store A | MerchantProfile.business_name | "Owner" |
| Account = Customer in Store B | CustomerProfile.name | "Customer" |
| Account = Admin/Staff in Store C | MerchantProfile.business_name | "Admin" / "Staff" |
| Account = SuperAdmin (root) | Account.email | "Super Admin" |
| User (Legacy mode) — Owner | User.name column | "Owner" |
| User (Legacy mode) — Admin | User.name column | "Admin" |
| User (Legacy mode) — SuperAdmin | User.name column | "Super Admin" |
