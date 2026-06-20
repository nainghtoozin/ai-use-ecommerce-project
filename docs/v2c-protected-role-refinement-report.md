# V2-C: Protected Role Refinement Report

## Status: Completed

## Summary
Removed `customer` from all protected role lists. Only `superadmin` and `admin` remain protected. The `customer` role is now fully editable (rename, permissions, delete) while keeping defense-in-depth for the two system-protected roles.

## Files Modified (5)

### Frontend (2 files)

| File | Change |
|------|--------|
| `resources/js/Pages/Admin/Roles/Index.jsx` | `protectedRoles` → `['superadmin', 'admin']`; edit/delete guards updated |
| `resources/js/Pages/Admin/Roles/Show.jsx` | `protectedRoles` → `['superadmin', 'admin']`; all 4 inline arrays updated |

### Backend (3 files)

| File | Change |
|------|--------|
| `app/Http/Requests/StoreRoleRequest.php` | `not_in` rule → `superadmin,admin` |
| `app/Http/Requests/UpdateRoleRequest.php` | `authorize()` check → `['superadmin', 'admin']`; `not_in` rule → `superadmin,admin` |
| `app/Http/Controllers/Admin/RoleController.php` | All 3 `in_array` checks → `['superadmin', 'admin']` |

## Protected Roles
- `superadmin` — cannot edit, delete, rename, or modify permissions
- `admin` — cannot edit, delete, rename, or modify permissions

## Editable Roles
- `customer` — can edit, rename, assign/remove permissions, delete
- `cashier`, `manager`, `staff`, any custom tenant-created role — no restrictions

## Unchanged (verified)
| Component | Status |
|-----------|--------|
| `CreateStoreController.php` (`foreach ['admin', 'customer']`) | UNCHANGED ✓ (bootstrap logic) |
| `SyncTenantRoles.php` (`foreach ['admin', 'customer']`) | UNCHANGED ✓ (bootstrap logic) |
| `TenantController.php` (`foreach ['admin', 'customer']`) | UNCHANGED ✓ (bootstrap logic) |
| Store creation flow | UNCHANGED ✓ |
| Tenant bootstrap | UNCHANGED ✓ |
| Permissions architecture | UNCHANGED ✓ |
| Authorization system | UNCHANGED ✓ |
| All other admin modules | UNCHANGED ✓ |

## Verification Results
- **Vite build:** 0 errors
- **Files modified:** 5
- **Stale protection arrays:** 0 remaining (grep confirms `superadmin,admin,customer` no longer exists in any protection context)
