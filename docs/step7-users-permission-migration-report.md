# Step 7 — Users Permission Migration Report

## Summary
Migrated the Users admin module from role-based (`isAdmin()`) and superadmin-only checks to permission-based authorization using existing database permissions.

## Files Modified

| File | Change |
|---|---|
| `app/Http/Controllers/Admin/AdminUserController.php` | Added `can()` + `abort(403)` to all 10 action methods |
| `app/Http/Requests/StoreUserRequest.php` | Changed `authorize()` from `isAdmin()` to `can('users.create')` |
| `app/Http/Requests/UpdateUserRequest.php` | Changed `authorize()` from `isAdmin()` to `can('users.update')` |
| `resources/js/Pages/Admin/Users/Index.jsx` | Added permission helpers; wrapped all action buttons |
| `resources/js/Pages/Admin/Users/Show.jsx` | Added permission helpers; wrapped Edit/Delete buttons |

## Permissions Used

| Permission | Exists in DB | Used For |
|---|---|---|
| `users.view` | Yes | List users, view user details |
| `users.create` | Yes | Show create form, store new user |
| `users.update` | Yes | Edit form, update user, change status (suspend/ban/activate) |
| `users.delete` | Yes | Delete user |

## Methods Protected

### AdminUserController — Methods & Permission Mapping

| Method | Permission | Route |
|---|---|---|
| `index()` | `users.view` | `GET /admin/users` |
| `create()` | `users.create` | `GET /admin/users/create` |
| `store()` | `users.create` | `POST /admin/users` |
| `show()` | `users.view` | `GET /admin/users/{user}` |
| `edit()` | `users.update` | `GET /admin/users/{user}/edit` |
| `update()` | `users.update` | `PUT /admin/users/{user}` |
| `destroy()` | `users.delete` | `DELETE /admin/users/{user}` |
| `suspend()` | `users.update` | `POST /admin/users/{user}/suspend` |
| `ban()` | `users.update` | `POST /admin/users/{user}/ban` |
| `activate()` | `users.update` | `POST /admin/users/{user}/activate` |

## Special Actions Protected

| Action | Permission | Notes |
|---|---|---|
| Suspend user | `users.update` | Also protected by `protectOwner()` and self-suspend check |
| Ban user | `users.update` | Also protected by `protectOwner()` and self-ban check |
| Activate user | `users.update` | No owner protection (activation is safe) |
| Role assignment | `users.update` | Handled inside `update()` method — changing role requires `users.update` |

## Frontend Visibility

| Element | Page | Guard |
|---|---|---|
| Create User button | Index.jsx | `can('users.create')` |
| View (eye icon) | Index.jsx | `can('users.view')` |
| Edit (pencil icon) | Index.jsx | `can('users.update')` |
| Suspend button | Index.jsx | `can('users.update')` + `isSuperAdmin \|\| !user.is_owner` + active status |
| Ban button | Index.jsx | `can('users.update')` + `isSuperAdmin \|\| !user.is_owner` + active status |
| Activate button | Index.jsx | `can('users.update')` + non-active status |
| Delete button | Index.jsx | `can('users.delete')` + `isSuperAdmin \|\| !user.is_owner` |
| Edit button | Show.jsx | `can('users.update')` |
| Delete button | Show.jsx | `can('users.delete')` + `isSuperAdmin \|\| !user.is_owner` |

## Tenant Isolation Verification

The controller already had tenant scoping via `getTenantFilter()`:

| Method | Tenant Scope |
|---|---|
| `index()` | `->when($this->getTenantFilter(), fn($q, $t) => $q->where('users.tenant_id', $t->id))` |
| `show()` | Same pattern |
| `edit()` | Same pattern |
| `update()` | Same pattern |
| `destroy()` | Same pattern |
| `create()` | Scopes available roles by tenant |
| `store()` | Creates user without explicit tenant_id (relies on model default) |
| `suspend()` | `User::when(Tenant::getCurrent(), ...)` — slightly different but equivalent for non-superadmins |
| `ban()` | Same as `suspend()` |
| `activate()` | Same as `suspend()` |

**Finding:** `getTenantFilter()` returns `false` for superadmins (bypasses tenant scoping). For non-superadmins, all user queries are scoped to the current tenant. The `suspend()`, `ban()`, `activate()` methods use `Tenant::getCurrent()` directly rather than `getTenantFilter()`, which means superadmins are **not** bypassed for these three methods. This is a pre-existing inconsistency, not introduced by this change.

## Manual Test Results

| Scenario | Permission | Expected | Result |
|---|---|---|---|
| Manager with `users.view` only | View users | Can see list/details, no buttons | ✓ Backend blocks, frontend hides buttons |
| Manager with `users.view` + `users.create` + `users.update` | Create, edit, change status | Full management except delete | ✓ Create/edit buttons visible, delete hidden |
| Manager with `users.*` | All | Full user management | ✓ All buttons visible |

## Regression Check

| Module | Status | Notes |
|---|---|---|
| Orders | Unchanged | No order files modified |
| Products | Unchanged | No product files modified |
| Units/Categories/Brands | Unchanged | No files modified |
| Roles module | Unchanged | No files modified |
| Permissions module | Unchanged | No files modified |
| Authentication | Unchanged | No auth files modified |
| Tenant logic | Unchanged | Pre-existing tenant scoping preserved |
| Vite build | Passes | 0 errors |

## Remaining Risks

1. **`store()` missing tenant_id assignment:** When creating a user via the admin panel, `tenant_id` is not explicitly set. If the User model doesn't have a database default or model event to set `tenant_id`, the user may be created without tenant association. This is a pre-existing issue.
2. **Superadmin bypass inconsistency:** `getTenantFilter()` bypasses tenant scoping for superadmins, but `suspend()/ban()/activate()` use `Tenant::getCurrent()` directly which applies tenant scoping even for superadmins if a tenant context exists. Pre-existing issue, not introduced here.
3. **FormRequest `authorize()` vs controller check:** Both `StoreUserRequest` and the controller `store()` method check `users.create`; similarly for `UpdateUserRequest` and `update()`. This is redundant but harmless — the controller check is the primary gate.
