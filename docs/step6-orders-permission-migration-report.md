# Step 6 — Orders Permission Migration Report

## Summary
Migrated the Orders admin module from no permission enforcement to permission-based authorization using existing database permissions.

## Database Permissions Used
| Permission | Exists in DB | Used For |
|---|---|---|
| `orders.view` | Yes | Listing and viewing order details |
| `orders.update-status` | Yes | All status changes, payment actions, and delete |
| `orders.override-status` | Yes | Super admin order status override (no changes) |
| `orders.override-payment` | Yes | Super admin payment status override (no changes) |

**Note:** `orders.update` and `orders.delete` do **not** exist in the database. `orders.update-status` was used as the closest equivalent for mutation operations.

## Changes Made

### Backend — `AdminOrderController.php`
Added `abort(403)` permission checks to every action method:

| Method | Permission |
|---|---|
| `index()` | `orders.view` |
| `show()` | `orders.view` |
| `updateOrderStatus()` | `orders.update-status` |
| `confirmOrder()` | `orders.update-status` |
| `processOrder()` | `orders.update-status` |
| `shipOrder()` | `orders.update-status` |
| `deliverOrder()` | `orders.update-status` |
| `cancelOrder()` | `orders.update-status` |
| `verifyPayment()` | `orders.update-status` |
| `rejectPayment()` | `orders.update-status` |
| `markAsPaid()` | `orders.update-status` |
| `destroy()` | `orders.update-status` |
| `search()` | Delegates to `index()` — covered |

Pattern used throughout:
```php
if (!auth()->user()->can('orders.view')) {
    abort(403, 'Unauthorized');
}
```

### Frontend — `Orders/Index.jsx`
- Added `usePage` import from `@inertiajs/react`
- Added permission helpers: `can = (perm) => permissions.includes(perm)`
- Wrapped "View" link with `can('orders.view')`
- Wrapped "Delete" button with `can('orders.update-status')` (combined with existing cancelled-only check)
- Uses correct pattern: `auth?.user?.permissions` (matches all other admin modules)

### Frontend — `Orders/Show.jsx`
**Bug fix:** The permission access pattern was previously broken — it read `props.permissions` which is always `undefined` (permissions live at `auth.user.permissions`). Fixed to use the standard pattern:
```jsx
const { auth } = usePage().props;
const permissions = auth?.user?.permissions || [];
const can = (perm) => permissions.includes(perm);
```

**Permission guards added:**
- `renderNextActionButton()` — returns `null` if no `orders.update-status` (hides Confirm/Process/Ship/Deliver buttons)
- `renderPaymentActions()` — wraps Verify/Reject buttons with `can('orders.update-status')`
- Danger Zone — Cancel and Delete buttons wrapped with `can('orders.update-status')`
- Override section — unchanged (already had `canOverrideStatus`/`canOverridePayment` checks; now they actually work since the permission access was fixed)

### No Changes Needed
- **AdminOrderOverrideController** — Already had `orders.override-status` and `orders.override-payment` checks
- **OrderDetailModal.jsx** — Read-only view component, no mutation buttons
- **Client-side controllers** (OrderController, ClientOrderController, StorefrontCustomerController) — Scoped to `auth()->id()` only
- **Routes** — No route changes needed
- **Migration/Seeder** — No new permissions created

## Verification
- Vite build: passes (0 errors, 0 warnings)
- Pattern matches existing modules (Units, Brands, Categories, Products): `auth()->user()->can()` on backend, `permissions.includes()` on frontend

## Permission Mapping Rationale
Since `orders.update` and `orders.delete` do not exist in the database:
- All mutation actions (status changes, payment actions, delete) use `orders.update-status`
- This is consistent — any admin trusted to update order status is trusted to perform other order mutations
- If a future `orders.delete` permission is added, the `destroy()` method and frontend delete buttons should be updated to use it

## Test Scenarios
1. **Manager with `orders.view` only** — Can see order list and details, cannot perform any actions
2. **Manager with `orders.view` + `orders.update-status`** — Can perform all workflow actions and delete cancelled orders
3. **Super admin** — Gets all permissions, plus override capabilities
