# Step 10c: Dashboard + Billing + Payments Hardening Report

## Status: Completed

## Summary
Added authorization for Dashboard (`dashboard.view`), Billing (`billing.view`, `billing.renew`), and refined Payment Method permissions (`payments.create`, `payments.update`, `payments.delete`). All three controllers now have proper permission checks, sidebar/frontend visibility guards match backend, and tenant isolation is verified.

## Files Modified (9)

### Controllers (3)
| Controller | Methods Protected | Permission(s) Used |
|-----------|-----------------|-------------------|
| `AdminController.php` | `index()` | `dashboard.view` |
| `AdminBillingController.php` | `index()`, `renew()` | `billing.view`, `billing.renew` |
| `AdminPaymentMethodController.php` | `create()`, `store()`, `edit()`, `update()`, `destroy()`, `toggle()` | `payments.create`, `payments.update`, `payments.delete` |

### Seeder
| File | Permissions Added |
|------|------------------|
| `database/seeders/PermissionSeeder.php` | `billing.view`, `billing.renew`, `payments.create`, `payments.update`, `payments.delete` |

### Frontend (5)
| File | Change |
|------|--------|
| `resources/js/Components/AdminSidebar.jsx` | Billing menu now guarded by `billing.view` |
| `resources/js/Pages/Admin/Billing/Index.jsx` | Renew button now guarded by `billing.renew` |
| `resources/js/Pages/Admin/PaymentMethods/Index.jsx` | Add button `payments.create`, Edit `payments.update`, Delete `payments.delete`, Toggle `payments.update` |
| `resources/js/Pages/Admin/PaymentMethods/Create.jsx` | Page-level guard with `payments.create` |
| `resources/js/Pages/Admin/PaymentMethods/Edit.jsx` | Page-level guard with `payments.update` |

## Permission Mapping

### Dashboard
| Action | Permission | Backend | Sidebar |
|--------|-----------|---------|---------|
| View dashboard | `dashboard.view` | ✓ `can()` | ✓ `can()` |

### Billing
| Action | Permission | Backend | Sidebar/Frontend |
|--------|-----------|---------|-----------------|
| View billing page | `billing.view` | ✓ `can()` | ✓ Sidebar |
| Renew subscription | `billing.renew` | ✓ `can()` | ✓ Renew button |

### Payment Methods
| Action | Permission (before) | Permission (after) | Backend | Frontend |
|--------|-------------------|-------------------|---------|----------|
| List payment methods | `payments.view` | `payments.view` | ✓ | ✓ |
| Create payment method | `payments.view` | `payments.create` | ✓ | ✓ |
| Edit payment method | `payments.view` | `payments.update` | ✓ | ✓ |
| Toggle active status | `payments.view` | `payments.update` | ✓ | ✓ |
| Delete payment method | `payments.view` | `payments.delete` | ✓ | ✓ |

## Tenant Safety

| Model/Data | Protection | Verified |
|-----------|-----------|---------|
| `PaymentMethod` | `TenantAware` trait — TenantScope auto-filters by `tenant_id` | ✓ |
| Billing/Subscription | Fetched via `auth()->user()->tenant->subscription` — cannot cross tenants | ✓ |

## Verification Results

### Build
- **Vite build:** 0 errors, 0 warnings (excluding chunk size advisory)
- **Files modified:** 9 (3 controllers, 1 seeder, 1 sidebar, 4 frontend pages)

### Unchanged Modules (verified)
- Products — UNCHANGED ✓
- Orders — UNCHANGED ✓
- Users — UNCHANGED ✓
- Roles — UNCHANGED ✓
- Permissions — UNCHANGED ✓
- Storefront — UNCHANGED ✓
- Checkout — UNCHANGED ✓
- Authentication — UNCHANGED ✓
- All previous step changes — UNCHANGED ✓

## Manual Test Matrix

| Role | Granted Permissions | Can Access | Cannot Access |
|------|-------------------|------------|---------------|
| A (Dashboard) | `dashboard.view` | Dashboard | Billing page |
| B (Billing view) | `billing.view` | Billing page (read-only) | Renew subscription |
| C (Billing full) | `billing.view`, `billing.renew` | Billing page + renew | — |
| D (Payments view) | `payments.view` | View payment methods | Create, Edit, Delete, Toggle |
| E (Payments create+update) | `payments.view`, `payments.create`, `payments.update` | Create + Edit | Delete |
| F (Payments full) | `payments.view`, `payments.create`, `payments.update`, `payments.delete` | All payment operations | — |

## Remaining Risks
- `billing.renew` permission exists but there's no lower-level guard in the sidebar/billing page (renew button visibility is the only frontend guard — backend 403 protects the action)
- Dashboard.jsx billing alert banner is not permission-guarded (it links to /admin/billing which will 403 if user lacks `billing.view`)
- `permissions.edit` vs `permissions.update` inconsistency still exists in PermissionSeeder (pre-existing, not in scope)
- `settings.edit` in PermissionSeeder is unused (pre-existing, not in scope)
