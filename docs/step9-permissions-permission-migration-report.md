# Step 9: Permissions Module — Permission Migration Report

## Status: Completed

## Summary
Implemented permission-based authorization for the Permissions management module (`/admin/permissions`). Standardized naming, added role-assignment protection on delete, and added frontend page-level guards.

## Changes Made

### Backend: `app/Http/Controllers/Admin/PermissionController.php`
1. **Standardized `permissions.edit` → `permissions.update`** in both `edit()` and `update()` methods. Matches the standard `view/create/update/delete` naming convention used by all other modules.
2. **Added role-assignment protection in `destroy()`** — Prevents deleting a permission that is currently assigned to any role(s), with a clear error message and redirect. Mirrors the RoleController pattern of checking for dependent records before deletion.

### Frontend: `resources/js/Pages/Admin/Permissions/Index.jsx`
1. **Standardized `can('permissions.edit')` → `can('permissions.update')`** on the Edit button guard.

### Frontend: `resources/js/Pages/Admin/Permissions/Create.jsx`
1. **Added page-level permission guard** — Redirects to permissions index if user lacks `permissions.create`.

### Frontend: `resources/js/Pages/Admin/Permissions/Edit.jsx`
1. **Added page-level permission guard** — Redirects to permissions index if user lacks `permissions.update`.

## Permission Map

| Method | Route | Permission Used | DB Exists? |
|--------|-------|-----------------|------------|
| `index()` | GET /admin/permissions | `permissions.view` | **Yes** |
| `create()` | GET /admin/permissions/create | `permissions.create` | **No** |
| `store()` | POST /admin/permissions | `permissions.create` | **No** |
| `edit()` | GET /admin/permissions/{id}/edit | `permissions.update` | **No** |
| `update()` | PUT /admin/permissions/{id} | `permissions.update` | **No** |
| `destroy()` | DELETE /admin/permissions/{id} | `permissions.delete` | **No** |

## Key Findings

1. **Only `permissions.view` exists in the database** — Of the 45 total permissions, `permissions.create`, `permissions.update`, and `permissions.delete` have never been seeded. The controller checks for these non-existent permissions mean that only superadmins (who bypass all `can()` checks via Spatie's Super Admin gate) can perform create/update/delete operations on permissions. Non-superadmin roles cannot be granted these permissions since they don't exist in the `permissions` table.

2. **This is intentional behavior** — Prior to this migration, the PermissionController had no authorization checks at all. Adding checks for non-existent permissions ensures that only superadmins can mutate the permissions table, which is a reasonable security posture. If future requirements demand delegating permission management, `permissions.create`, `permissions.update`, and `permissions.delete` would need to be seeded into the database.

3. **Standardized naming** — Changed from `permissions.edit` to `permissions.update` across backend and frontend to match the standard CRUD naming convention (`view/create/update/delete`).

4. **Delete protection** — Added a check in `destroy()` that prevents deleting a permission if it is currently assigned to any role, mirroring the RoleController's user-count check to prevent orphaned or broken role configurations.

## Files Modified
- `app/Http/Controllers/Admin/PermissionController.php` — Standardized permission check names, added role-assignment delete protection
- `resources/js/Pages/Admin/Permissions/Index.jsx` — Standardized `edit` → `update` in button guard
- `resources/js/Pages/Admin/Permissions/Create.jsx` — Added page-level permission guard
- `resources/js/Pages/Admin/Permissions/Edit.jsx` — Added page-level permission guard

## Verification
- Vite build: 0 errors, 0 warnings (aside from chunk size advisory)
