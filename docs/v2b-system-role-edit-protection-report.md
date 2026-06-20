# V2-B: System Role Edit Protection Report

## Status: Completed

## Summary
Added backend and frontend protection preventing modification of system roles (`superadmin`, `admin`, `customer`). Renaming, permission changes, and permission overwrite are now blocked at both layers. Users can view system role details but cannot edit or delete them.

## Protected Roles
- `superadmin` — now protected against edit/rename/permission modification
- `admin` — now protected against edit/rename/permission modification
- `customer` — now protected against edit/rename/permission modification

## Files Modified (5)

### Backend (3 files)

| File | Change | Protection Type |
|------|--------|-----------------|
| `app/Http/Requests/StoreRoleRequest.php` | Added `not_in:superadmin,admin,customer` validation rule | Prevents creating roles with protected names |
| `app/Http/Requests/UpdateRoleRequest.php` | Added `authorize()` check for protected roles; added `not_in` validation to name rule | Prevents updating protected roles AND prevents renaming any role to a protected name |
| `app/Http/Controllers/Admin/RoleController.php` | `edit()`: 403 for protected roles. `update()`: redirect with error for protected roles. | Blocks edit page access and update submission |

### Frontend (2 files)

| File | Change | Effect |
|------|--------|--------|
| `resources/js/Pages/Admin/Roles/Index.jsx` | Edit button hidden for protected roles via `!['superadmin', 'admin', 'customer'].includes(role.name)` | No edit icon shown in role list |
| `resources/js/Pages/Admin/Roles/Show.jsx` | Edit button hidden; "Protected system role" label shown instead | Read-only view with clear indicator |

## What Each Layer Prevents

### Backend

**StoreRoleRequest:**
- `name` field rejected if it equals `superadmin`, `admin`, or `customer`
- Error message: "Cannot create a role with a protected system name."

**UpdateRoleRequest:**
- `authorize()` returns `false` if the role being edited is a protected role → 403
- `name` field rejected if renamed to `superadmin`, `admin`, or `customer`
- Error message: "Cannot rename a role to a protected system name."

**RoleController::edit():**
- Returns 403 "This role is a protected system role and cannot be edited."

**RoleController::update():**
- Redirects with error: "The '{name}' role is a protected system role and cannot be modified."

### Frontend

**Roles/Index.jsx:**
- Edit icon (pencil) hidden for system roles in the action column

**Roles/Show.jsx:**
- "Edit" button hidden for system roles
- "Protected system role" italic label shown
- "Delete" button hidden (from V2-A)
- "System role — protected" label shown (from V2-A)

## Protection Layers (Defense in Depth)

| Layer | superadmin | admin | customer |
|-------|-----------|-------|----------|
| Frontend: Edit button hidden | ✓ | ✓ | ✓ |
| Frontend: Delete button hidden | ✓ | ✓ | ✓ |
| Backend: Edit page access blocked | ✓ | ✓ | ✓ |
| Backend: Update endpoint blocked | ✓ | ✓ | ✓ |
| Backend: Create with protected name | ✓ | ✓ | ✓ |
| Backend: Rename TO protected name | ✓ | ✓ | ✓ |
| Backend: Delete endpoint blocked | ✓ (existing) | ✓ (existing) | ✓ (existing) |

## Verification Results

### Build
- **Vite build:** 0 errors, 0 warnings (excluding chunk size advisory)
- **Files modified:** 5 (2 backend requests, 1 backend controller, 2 frontend pages)

### Unchanged (verified)
| Component | Status |
|-----------|--------|
| Role model | UNCHANGED ✓ |
| Permission model | UNCHANGED ✓ |
| Permissions CRUD | UNCHANGED ✓ |
| All other admin modules | UNCHANGED ✓ |
| User controller | UNCHANGED ✓ |
| Routes | UNCHANGED ✓ |
