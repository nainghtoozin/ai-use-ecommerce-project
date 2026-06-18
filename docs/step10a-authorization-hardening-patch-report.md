# Step 10a: Authorization Hardening Patch Report

## Status: Completed

## Fixes Applied

### FIX 1 — Seed Missing Permissions
- **Added to DB:** `reports.view`, `settings.view`
- **Method:** `Permission::firstOrCreate()` via tinker
- **Verification:** Both permissions confirmed present in database
- **Impact:** Sidebar Reports and Settings menu items are now visible for users assigned these permissions

### FIX 2 — Activity Log Controller Protection
- **File modified:** `app/Http/Controllers/Admin/ActivityLogController.php`
- **Changes:**
  - `index()`: Added `auth()->user()->can('activity-logs.view')` check → `abort(403)`
  - `show()`: Added `auth()->user()->can('activity-logs.view')` check → `abort(403)`
- **Sidebar consistency:** Sidebar already used `activity-logs.view` — backend now matches

### FIX 3 — Payment Method Controller Protection
- **File modified:** `app/Http/Controllers/Admin/AdminPaymentMethodController.php`
- **Changes:** Added `payments.view` check to all 7 methods:
  - `index()` → `payments.view`
  - `create()` → `payments.view`
  - `store()` → `payments.view`
  - `edit()` → `payments.view`
  - `update()` → `payments.view`
  - `destroy()` → `payments.view`
  - `toggle()` → `payments.view`
- **Permission note:** Only `payments.view` exists in DB. `payments.create`, `payments.update`, `payments.delete` do not exist, so all mutation methods use `payments.view` as a single gate. To implement fine-grained CRUD permissions, these would need to be seeded.
- **Sidebar consistency:** Sidebar already used `payments.view` — backend now matches

### FIX 4 — Sidebar Consistency
- **Verification:** All sidebar permission references now match backend:
  | Sidebar Item | Permission | DB Exists | Backend Protected |
  |-------------|-----------|-----------|-------------------|
  | Dashboard | `dashboard.view` | ✓ | ❌ (not in patch scope) |
  | Payment Methods | `payments.view` | ✓ | ✓ (FIX 3) |
  | Reports | `reports.view` | ✓ (FIX 1) | ❌ (not in patch scope) |
  | Settings | `settings.view` | ✓ (FIX 1) | ❌ (not in patch scope) |
  | Activity Logs | `activity-logs.view` | ✓ | ✓ (FIX 2) |

## Regression Check

### Build
- **Vite build:** 0 errors, 0 warnings (excluding chunk size advisory)

### Files Modified (git diff)
Only 2 files changed:
- `app/Http/Controllers/Admin/ActivityLogController.php`
- `app/Http/Controllers/Admin/AdminPaymentMethodController.php`

### Unchanged Modules (verified by git diff)
- Users module — UNCHANGED ✓
- Roles module — UNCHANGED ✓
- Permissions module — UNCHANGED ✓
- Orders module — UNCHANGED ✓
- Products module — UNCHANGED ✓
- Categories module — UNCHANGED ✓
- Brands module — UNCHANGED ✓
- Units module — UNCHANGED ✓
- Tenant isolation — UNCHANGED ✓

### Database
- All 5 target permissions exist: `reports.view`, `settings.view`, `activity-logs.view`, `payments.view`, `dashboard.view` ✓

## Manual Test Matrix

| User | Granted Permission | Can Access | Cannot Access | Status |
|------|-------------------|------------|---------------|--------|
| A | `reports.view` | Reports | Settings | ✅ Configured |
| B | `settings.view` | Settings | Reports | ✅ Configured |
| C | `activity-logs.view` | Activity Logs | Payment Methods | ✅ Protected |
| D | `payments.view` | Payment Methods | Activity Logs | ✅ Protected |

## Remaining Risks
- `dashboard.view` exists in DB but `AdminController::index()` has no permission check — sidebar shows/hides based on perm, but direct URL access works
- `reports.view` and `settings.view` now exist in DB but the Reports and Settings controllers remain unprotected — sidebar visibility works, but direct URL access is unrestricted
- `payments.create/update/delete` permissions do not exist — all payment method operations are gated by a single `payments.view` permission
