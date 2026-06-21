# V3-A7-FIX-2 Payment Method System Default UX Fix

## Status: Completed

---

## Root Cause

The Payment Methods Index page (`resources/js/Pages/Admin/PaymentMethods/Index.jsx`) renders Edit and Delete buttons for every payment method based solely on the user's `payments.update` / `payments.delete` permissions. There is no check to distinguish system-default methods (`Cash`, `Cash On Delivery`) from user-created methods, so the buttons appear for all methods even though system defaults should be protected.

---

## Files Modified

| File | Change |
|------|--------|
| `resources/js/Pages/Admin/PaymentMethods/Index.jsx` | Added `SYSTEM_METHODS` constant and `isSystemMethod()` helper. Wrapped Edit/Delete button conditions with `&& !isSystemMethod(pm.name)`. |

---

## System Methods Protected

| Method | Edit Button | Delete Button |
|--------|------------|--------------|
| Cash | Hidden | Hidden |
| Cash On Delivery | Hidden | Hidden |

## Custom Methods Editable

| Method | Edit Button | Delete Button |
|--------|------------|--------------|
| Bank Transfer | Visible (if `can('payments.update')`) | Visible (if `can('payments.delete')`) |
| KBZ Pay | Visible | Visible |
| (any custom method) | Visible | Visible |

---

## Implementation

```jsx
const SYSTEM_METHODS = ['Cash', 'Cash On Delivery'];

// Inside component:
const isSystemMethod = (name) => SYSTEM_METHODS.includes(name);

// Edit button (line 82):
{can('payments.update') && !isSystemMethod(pm.name) && (
    <Link ...>Edit</Link>
)}

// Delete button (line 85):
{can('payments.delete') && !isSystemMethod(pm.name) && (
    <button ...>Delete</button>
)}
```

---

## Tests Passed

| Test Suite | Results |
|------------|---------|
| `MerchantManagementTest` (4) | ✅ All passed |
| `StorefrontRegistrationTest` (5) | ✅ All passed |

Manual test scenarios verified by code trace:

| Scenario | Expected | Verified |
|----------|----------|----------|
| Cash | No Edit, No Delete | `isSystemMethod('Cash')` → `true` → buttons hidden ✅ |
| Cash On Delivery | No Edit, No Delete | `isSystemMethod('Cash On Delivery')` → `true` → buttons hidden ✅ |
| Bank Transfer | Edit + Delete visible | `isSystemMethod('Bank Transfer')` → `false` → gated by permissions only ✅ |

---

## Regression Risk

**Low.** The change is purely frontend/UI and only affects button rendering. No payment logic, backend code, routes, controllers, database schema, or tenant bootstrap code was modified. The `SYSTEM_METHODS` list is static and uses the same name-based identification as `TenantBootstrapService::createDefaultPaymentMethods()`.
