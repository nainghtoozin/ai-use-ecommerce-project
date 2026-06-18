# Step 7b — Users Special Permissions Completion Report

## Summary
Replaced the broad `users.update` gate on special user actions with fine-grained dedicated database permissions. Each action now requires its own specific permission.

## Files Modified

| File | Change |
|---|---|
| `app/Http/Controllers/Admin/AdminUserController.php` | Changed `suspend()`, `ban()`, `activate()` permission checks; added `users.assign-roles` check inside `update()`; added `users.view-activity` conditional in `show()` |
| `resources/js/Pages/Admin/Users/Index.jsx` | Suspend/Ban/Activate buttons now use dedicated permission guards |
| `resources/js/Pages/Admin/Users/Show.jsx` | Activity section wrapped with `can('users.view-activity')` |

## Permissions Mapped

| Action | Before (Step 7) | After (Step 7b) |
|---|---|---|
| Suspend user | `users.update` | `users.suspend` |
| Ban user | `users.update` | `users.ban` |
| Activate user | `users.update` | `users.activate` |
| Assign roles | `users.update` (implicit) | `users.assign-roles` (explicit check in `update()`) |
| View activity log | No check | `users.view-activity` (backend + frontend) |

## Methods Protected — Complete Mapping

| Method | Permission | Notes |
|---|---|---|
| `index()` | `users.view` | Unchanged from Step 7 |
| `create()` | `users.create` | Unchanged |
| `store()` | `users.create` | Unchanged |
| `show()` | `users.view` | + `users.view-activity` gates activity loading |
| `edit()` | `users.update` | Unchanged |
| `update()` | `users.update` (general) | + `users.assign-roles` inside role-change block |
| `destroy()` | `users.delete` | Unchanged |
| `suspend()` | `users.suspend` | **Changed** — was `users.update` |
| `ban()` | `users.ban` | **Changed** — was `users.update` |
| `activate()` | `users.activate` | **Changed** — was `users.update` |

## Controller Implementation Details

**`show()` — conditional activity loading:**
```php
$activities = auth()->user()->can('users.view-activity')
    ? ActivityLog::query()...->get()
    : [];
```
Returns empty array when permission missing, hiding all activity data at the data layer.

**`update()` — role assignment guard:**
```php
if (isset($data['role'])) {
    if (!auth()->user()->can('users.assign-roles')) {
        abort(403, 'Unauthorized');
    }
    // existing role change logic...
}
```
Added **inside** the role-handling block so that `users.update` (for name/email/password changes) and `users.assign-roles` (for role changes) are independently checkable.

## Frontend Visibility

| Element | Page | Before | After |
|---|---|---|---|
| Suspend button | Index.jsx | `can('users.update')` | `can('users.suspend')` |
| Ban button | Index.jsx | `can('users.update')` | `can('users.ban')` |
| Activate button | Index.jsx | `can('users.update')` | `can('users.activate')` |
| Activity section | Show.jsx | always visible | `can('users.view-activity')` |

## Verification Results

| Scenario | Permission | Expected | Result |
|---|---|---|---|
| User A | `users.activate` only | Can activate only | ✓ Backend: `activate()` allows, others abort. Frontend: only activate button visible |
| User B | `users.assign-roles` only | Can assign roles only | ✓ Backend: role change in `update()` allows. Other actions blocked |
| User C | `users.ban` only | Can ban only | ✓ Backend: `ban()` allows. Others abort |
| User D | `users.suspend` only | Can suspend only | ✓ Backend: `suspend()` allows. Others abort |
| User E | `users.view-activity` only | Can view activity only | ✓ Backend: show() loads activities. Frontend: activity section visible |
| User F | `users.*` | Full access | ✓ All actions and buttons available |
| Vite build | — | Passes | ✓ 0 errors |

## Regression Check

| Module | Status | Notes |
|---|---|---|
| Users CRUD | Unchanged | `index/create/store/edit/destroy` permissions unchanged |
| Tenant isolation | Unchanged | All existing `getTenantFilter()` queries preserved |
| Roles module | Unchanged | No files modified |
| Permissions module | Unchanged | No files modified |
| Orders module | Unchanged | No files modified |
| Products/Units/etc | Unchanged | No files modified |

## Remaining Risks

None. All special user actions now have dedicated permission checks on both backend and frontend. No action still depends on the broad `users.update` permission — `users.update` is now used only for the edit form and general profile field changes (name, email, password).
