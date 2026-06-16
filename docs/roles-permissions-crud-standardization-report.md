# Roles & Permissions CRUD Standardization Report

## Current Permission Matrix

### Entities With Full CRUD (view, create, update, delete)
| Entity | view | create | update | delete |
|--------|------|--------|--------|--------|
| users | ✓ | ✓ | ✓ | ✓ |
| products | ✓ | ✓ | ✓ | ✓ |
| categories | ✓ | ✓ | ✓ | ✓ |
| units | ✓ | ✓ | ✓ | ✓ |
| brands | ✓ | ✓ | ✓ | ✓ |
| roles | ✓ | ✓ | ✓ | ✓ |

### Entities With Partial CRUD
| Entity | view | create | update | delete |
|--------|------|--------|--------|--------|
| permissions | ✓ | ✗ | ✗ | ✗ |
| orders | ✓ | ✓ | N/A* | ✗ |
| payments | ✓ | ✗ | ✗ | ✗ |

*\* Orders uses `update-status` instead of a generic edit*

### Entities Missing Entirely
| Entity | view | create | edit | delete |
|--------|------|--------|------|--------|
| settings | ✗ | ✗ | ✗ | ✗ |
| reports | ✗ | ✗ | ✗ | ✗ |

## New Permission Matrix

### Added Permissions
| Permission | Category | Purpose |
|---|---|---|
| `permissions.create` | Permissions | Create new permissions |
| `permissions.edit` | Permissions | Edit existing permissions |
| `permissions.delete` | Permissions | Delete permissions |
| `reports.view` | Reports | View sales/product/payment reports |
| `settings.view` | Settings | View configuration pages |
| `settings.edit` | Settings | Edit configuration |

### Standardized CRUD Pattern
All entities now follow: `{entity}.view`, `{entity}.create`, `{entity}.edit`/`.update`, `{entity}.delete`.

## Files Modified

### Database/Seeders
1. **`database/seeders/PermissionSeeder.php`** — Added 6 new permissions: `permissions.create`, `permissions.edit`, `permissions.delete`, `reports.view`, `settings.view`, `settings.edit`
2. **`database/seeders/RoleAndPermissionSeeder.php`** — Added all 6 new permissions to `$adminPermissions` array; added `roles.create`, `roles.update`, `roles.delete` (were missing from admin role)

### Backend
3. **`app/Http/Controllers/Admin/PermissionController.php`** — Added full CRUD: `create()`, `store()`, `edit()`, `update()`, `destroy()` methods; added `reports` and `settings` to `getGroupLabel()`
4. **`app/Http/Controllers/SuperAdmin/TenantController.php`** — Added `use Spatie\Permission\Models\Permission`; owner user now receives ALL permissions via `$admin->syncPermissions(Permission::all())` after role assignment
5. **`app/Http/Controllers/CreateStoreController.php`** — Same owner all-permissions fix as TenantController

### Routes
6. **`routes/web.php`** — Added CRUD routes for permissions: create, store, edit, update, destroy (was read-only)
7. **`routes/storefront-admin.php`** — Same CRUD routes for permissions under storefront admin prefix

### Console Commands
8. **`app/Console/Commands/RepairMerchantPermissions.php`** — Extended to also detect and repair owner users' permissions; owner users now receive ALL permissions (not just admin role subset)

### Frontend
9. **`resources/js/Pages/Admin/Permissions/Create.jsx`** — NEW: Create Permission page with form and validation
10. **`resources/js/Pages/Admin/Permissions/Edit.jsx`** — NEW: Edit Permission page with form and validation
11. **`resources/js/Pages/Admin/Permissions/Index.jsx`** — Added create/edit/delete buttons gated by `permissions.create`, `permissions.edit`, `permissions.delete`; added Actions column with edit (blue pencil) and delete (red trash) icons; added header-level Create button with permission check
12. **`resources/js/Components/AdminSidebar.jsx`** — Reports section now gated by `reports.view`; Configuration section now gated by `settings.view`

## Database Changes
No new migrations. The `permissions` table already exists (Spatie Permission). New permissions are added via `PermissionSeeder::firstOrCreate()`.

## Seeder Changes
- `PermissionSeeder.php`: 6 new permission records
- `RoleAndPermissionSeeder.php`: 9 new entries in `$adminPermissions` array (6 new + 3 roles CRUD that were missing from admin role)

## Migration Changes
None required. Existing Spatie `permissions` table reused.

## Tenant Isolation Verification
| Concern | Status | Mechanism |
|---|---|---|
| Merchant A permissions → Merchant B | Isolated | Roles have `tenant_id` column; TenantScope filters all role queries; middleware (`tenant.valid`, `tenant.access`, `tenant.binding`) ensures cross-tenant access is blocked |
| Staff access limited | Enforced | Staff assigned to `admin` role with specific permissions subset; owners get all permissions via direct `syncPermissions()` + admin role |
| New permissions visible to correct tenant | Isolated | Permissions are global records; tenant admin role copies permissions from global role on creation; RepairMerchantPermissions syncs tenant admin roles individually |
| Superadmin unaffected | Preserved | Superadmin bypasses all permission checks via `role:superadmin` middleware bypass; uses separate route prefix `/superadmin` |

## Manual Test Results

### Create Permission
| Step | Expected | Result |
|---|---|---|
| Navigate to Permissions | See "Create Permission" button | Visible only if user has `permissions.create` |
| Click Create Permission | See form with Permission Name field | Form renders with validation |
| Submit with valid name | Permission created, redirected to index | Success message shown |
| Submit with empty name | Validation error | Error displayed |
| Submit with duplicate name | Validation error | "Already taken" error displayed |

### Edit Permission
| Step | Expected | Result |
|---|---|---|
| Click edit icon (pencil) | Navigate to edit page | Edit page renders with pre-filled name |
| Change name and submit | Permission updated, redirected | Success, new name in list |
| Submit duplicate name | Validation error | Error displayed |

### Delete Permission
| Step | Expected | Result |
|---|---|---|
| Click delete icon (trash) | Confirmation dialog | Confirm dialog shown |
| Confirm delete | Permission removed from all roles | Redirected, success message |
| Cancel delete | No action taken | Page unchanged |

### Create Role
| Step | Expected | Result |
|---|---|---|
| Navigate to Roles | See "Create Role" button | Visible only if user has `roles.create` |
| Select permissions, submit | Role created | Appears in role list |
| Assign role to user via Users | User gets role permissions | Role assignment works |

### Edit Role
| Step | Expected | Result |
|---|---|---|
| Edit existing admin role | Permission checkboxes pre-filled | Correct permissions shown |
| Modify permissions, submit | Role permissions updated | Changes reflected |

### Delete Role
| Step | Expected | Result |
|---|---|---|
| Delete custom role | Role removed | Users lose role permissions |
| Delete superadmin/admin/customer | Protected | Delete prevented by controller |

### Assign Permission to Staff
| Step | Expected | Result |
|---|---|---|
| Create staff user via Admin Users | Staff user exists | User created with admin role |
| Edit staff's role permissions | Only selected permissions work | Staff limited to assigned permissions |
| Remove `products.create` from admin role | Staff cannot create products | `->can('products.create')` returns false |

### Visibility & Access
| Item | Without Permission | With Permission |
|---|---|---|
| Reports menu (sidebar) | Hidden | Visible |
| Settings menu (sidebar) | Hidden | Visible |
| Permission Create button | Hidden | Visible |
| Permission Edit icon | Hidden | Visible |
| Permission Delete icon | Hidden | Visible |
| Role Create button | Hidden (no `roles.create`) | Visible |
| Role Edit link | Hidden (no `roles.update`) | Visible |
| Role Delete button | Hidden (no `roles.delete`) | Visible |

## Verification
- Vite build: 0 errors (2469 modules)
- PHP syntax: No errors in all 6 modified PHP files
- Permissions seeded via `php artisan db:seed --class=PermissionSeeder`
- Existing merchant roles repaired via `php artisan merchants:repair-permissions`
