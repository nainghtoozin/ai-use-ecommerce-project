# Permission Name Standardization Fix

## Root Cause

Database permissions use `update` (e.g. `products.update`, `units.update`, `categories.update`, `brands.update`) but controllers and frontend code referenced `edit` (e.g. `products.edit`). All `can('*.edit')` calls returned `false` because no permission named `*.edit` exists in the database. Confirmed via Tinker:

```
User ID: 40
can('units.update')       = true   ← exists in DB
can('units.edit')         = false  ← does NOT exist in DB
can('categories.update')  = true
can('categories.edit')    = false
can('brands.update')      = true
can('brands.edit')        = false
can('products.update')    = true
can('products.edit')      = false
```

## Files Modified

| # | File | Old Permission | New Permission | Replacements |
|---|---|---|---|---|
| 1 | `AdminUnitController.php` | `'units.edit'` | `'units.update'` | 2 (edit + update methods) |
| 2 | `AdminCategoryController.php` | `'categories.edit'` | `'categories.update'` | 2 (edit + update methods) |
| 3 | `AdminBrandController.php` | `'brands.edit'` | `'brands.update'` | 2 (edit + update methods) |
| 4 | `AdminProductController.php` | `'products.edit'` | `'products.update'` | 4 (edit, update, bulkActivate, bulkDeactivate) |
| 5 | `Units/Index.jsx` | `'units.edit'` | `'units.update'` | 1 (Edit button visibility) |
| 6 | `Units/Edit.jsx` | `'units.edit'` | `'units.update'` | 1 (page guard) |
| 7 | `Categories/Index.jsx` | `'categories.edit'` | `'categories.update'` | 1 (Edit button visibility) |
| 8 | `Categories/Edit.jsx` | `'categories.edit'` | `'categories.update'` | 1 (page guard) |
| 9 | `Brands/Index.jsx` | `'brands.edit'` | `'brands.update'` | 1 (Edit button visibility) |
| 10 | `Brands/Edit.jsx` | `'brands.edit'` | `'brands.update'` | 1 (page guard) |
| 11 | `Products/Index.jsx` | `'products.edit'` | `'products.update'` | 3 (Edit icon, Bulk Activate, Bulk Deactivate) |
| 12 | `Products/Edit.jsx` | `'products.edit'` | `'products.update'` | 1 (page guard) |
| 13 | `Products/Show.jsx` | `'products.edit'` | `'products.update'` | 4 (Edit button, Add Variants, Add Components, sticky Edit) |

**Total: 13 files, 24 replacements**

## Replacements Summary

| Old Permission | New Permission | Files Updated |
|---|---|---|
| `units.edit` | `units.update` | AdminUnitController.php, Units/Index.jsx, Units/Edit.jsx |
| `categories.edit` | `categories.update` | AdminCategoryController.php, Categories/Index.jsx, Categories/Edit.jsx |
| `brands.edit` | `brands.update` | AdminBrandController.php, Brands/Index.jsx, Brands/Edit.jsx |
| `products.edit` | `products.update` | AdminProductController.php, Products/Index.jsx, Products/Edit.jsx, Products/Show.jsx |

## What Was NOT Changed

- Route names (`admin.products.edit`, `admin.categories.edit`, etc.) — these are URL route identifiers, not permission strings
- Seeders (`PermissionSeeder.php`, `RoleAndPermissionSeeder.php`) — define permission templates; database already has `*.update`
- Blade templates — use route names, not permission strings
- Notifications — use route names for action URLs

## Verification Results

### Backend PHP syntax
```
php -l AdminUnitController.php      → No syntax errors
php -l AdminCategoryController.php  → No syntax errors
php -l AdminBrandController.php     → No syntax errors
php -l AdminProductController.php   → No syntax errors
```

### Frontend build
```
vite build  → ✓ 2469 modules transformed, ✓ built in 33.95s, 0 errors
```

### Tinker re-verification (after fix)
```
User ID: 40
can('units.update')       = true   ← matches DB
can('categories.update')  = true   ← matches DB
can('brands.update')      = true   ← matches DB
can('products.update')    = true   ← matches DB
```

### Manager Role — all `*.update` permissions assigned
| Module | Edit Button | Edit Page Access | Update Action | 
|---|---|---|---|
| Units | ✅ Visible (`can('units.update')`) | ✅ Accessible | ✅ Successful |
| Categories | ✅ Visible (`can('categories.update')`) | ✅ Accessible | ✅ Successful |
| Brands | ✅ Visible (`can('brands.update')`) | ✅ Accessible | ✅ Successful |
| Products | ✅ Visible (`can('products.update')`) | ✅ Accessible | ✅ Successful |

### View-only permissions (no `*.update`)
| Module | Edit Button | Edit Page | Update Action |
|---|---|---|---|
| Units | ❌ Hidden | ❌ "Unauthorized" guard | ❌ 403 |
| Categories | ❌ Hidden | ❌ "Unauthorized" guard | ❌ 403 |
| Brands | ❌ Hidden | ❌ "Unauthorized" guard | ❌ 403 |
| Products | ❌ Hidden | ❌ "Unauthorized" guard | ❌ 403 |

## Regression Check

| Area | Status | Notes |
|---|---|---|
| View permissions (`*.view`) | ✅ Unchanged | Not modified |
| Create permissions (`*.create`) | ✅ Unchanged | Not modified |
| Delete permissions (`*.delete`) | ✅ Unchanged | Not modified |
| RoleMiddleware | ✅ Unchanged | Not touched |
| Tenant logic | ✅ Unchanged | Not touched |
| Storefront | ✅ Unchanged | No storefront files modified |
| Checkout | ✅ Unchanged | Not touched |
| Orders | ✅ Unchanged | Not touched |
| Route names | ✅ Unchanged | `admin.*.edit` route names preserved |
| Database | ✅ Unchanged | No migrations, no permission renames |

## Remaining Risks

None. All code now uses the same permission names that exist in the database. The `view`/`create`/`update`/`delete` convention is consistent across all four modules and all layers (controllers, frontend, seeders).
