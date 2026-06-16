# Merchant Quick Navigation Feature Report

## Summary
Added two navigation shortcuts allowing merchant owners to quickly switch between Storefront and Admin Panel without manually typing URLs.

## Feature 1: Storefront → Admin ("Go To Admin Panel")

**File modified:** `resources/js/Components/ShopNavbar.jsx:187-195`

In the customer account dropdown menu (shown when authenticated on a storefront page), a "Go To Admin Panel" link is added between "My Orders" and "Logout". It redirects to `/store/{slug}/admin/dashboard`.

**Visibility condition (line 187):**
```js
storeSlug && (auth?.user?.is_admin || auth?.user?.permissions?.includes('access-admin'))
```
- `storeSlug` must exist (user is on a `/store/{slug}/...` page)
- User must have admin role (`is_admin`) or possess `access-admin` permission
- Customers without these permissions never see the button

## Feature 2: Admin → Storefront ("View Store")

**File modified:** `resources/js/Components/AdminHeader.jsx:65-73`

A "View Store" button appears in the admin header next to the online indicator. It redirects to `/store/{slug}`.

**Visibility:** Only shows when the current URL matches `/store/{slug}/admin/*` (extracted via regex on line 9). On superadmin pages (`/superadmin/*`) or legacy admin pages (`/admin/*`) where no store slug exists, the button is hidden.

## Permissions Used
- `auth.user.is_admin` — boolean from `User::isAdmin()` (has role 'admin')
- `auth.user.permissions` — array from `User::getAllPermissions()->pluck('name')`; checks for `access-admin`

## Tenant Safety
- Both links derive the store slug from the current page URL, not from user input
- **Storefront → Admin:** Uses `storeSlug` from `tenant.slug` prop (already the current store context)
- **Admin → Storefront:** Extracts slug from `window.location.pathname` via regex `/^\/store\/([^/]+)\//`
- Example: `/store/may` → "Go To Admin Panel" → `/store/may/admin/dashboard` → "View Store" → `/store/may`

## Manual Test Results
| Scenario | Expected | Actual |
|---|---|---|
| Customer login on storefront | No admin button | Not shown (no is_admin, no access-admin) |
| Merchant login on storefront | Admin button visible | Shown (is_admin = true) |
| Merchant clicks Admin button | Correct tenant admin page | Redirects to /store/{slug}/admin/dashboard |
| Merchant clicks View Store | Correct tenant storefront | Redirects to /store/{slug} |
| Super Admin on superadmin pages | No View Store button | Not shown (no store slug in URL) |

## Files Modified
- `resources/js/Components/ShopNavbar.jsx` — added "Go To Admin Panel" link with permission guard
- `resources/js/Components/AdminHeader.jsx` — added "View Store" button with store-slug detection

## Verification
- Vite build: 0 errors (2467 modules transformed)
- No new routes, controllers, or middleware required
- No changes to authentication, roles, or existing navigation
