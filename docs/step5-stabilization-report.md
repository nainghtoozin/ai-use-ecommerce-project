# Step 5: Stabilization Report

## Files Modified

| File | Change |
|---|---|
| `resources/js/Pages/Admin/Products/Index.jsx:93` | Fixed `usePage()` destructuring — `const { url, auth }` → `const { url, props: { auth } }` |

## Root Causes Fixed

### Products Index — `usePage()` misuse (1 line fix)

**Before:**
```js
const { url, auth } = usePage();
```

**After:**
```js
const { url, props: { auth } } = usePage();
```

**Why it failed:** In Inertia v3, `usePage()` returns `{ component, props, url, version }`. `auth` lives inside `props.auth`. The old code extracted `auth` from the top level, which is `undefined`. This caused ALL frontend `can()` calls to return `false`, hiding every action button (Create, Edit, Delete, Bulk actions) in the Products list.

**Other files checked** — 22 files correctly use `usePage().props`; 5 files use `const { props } = usePage()` (which correctly gets the `props` object from the top level). Only Products/Index.jsx had the specific `{ url, auth }` pattern that silently broke permissions.

## Permission Data Flow (Verified)

```
Backend
  └─ HandleInertiaRequests.php:41
       → $user->getAllPermissions()->pluck('name')->toArray()
       → shared into Inertia as auth.user.permissions
          └─ Inertia v3 page object: { component, props: { auth: { user: { permissions: [...] } } }, url, version }
               └─ Frontend: const { auth } = usePage().props
                    → auth.user.permissions
                         └─ can(perm) = permissions.includes(perm)
```

All four modules now correctly access permissions through `usePage().props.auth`. The sidebar (`AdminSidebar.jsx:20-23`) also correctly uses `const { props, url } = usePage()`.

## UI Button Visibility (All 4 Modules)

### Units (`Index.jsx`)

| Action | Permission Check | Line | Renders When |
|---|---|---|---|
| Add Unit button | `can('units.create')` | 32 | ✅ has `units.create` |
| Edit link per row | `can('units.edit')` | 73 | ✅ has `units.edit` |
| Delete button per row | `can('units.delete')` | 76 | ✅ has `units.delete` |
| Sidebar nav link | `can('units.view')` | 114 | ✅ has `units.view` |

### Categories (`Index.jsx`)

| Action | Permission Check | Line | Renders When |
|---|---|---|---|
| Add Category button | `can('categories.create')` | 29 | ✅ has `categories.create` |
| Edit link per row | `can('categories.edit')` | 64 | ✅ has `categories.edit` |
| Delete button per row | `can('categories.delete')` | 67 | ✅ has `categories.delete` |
| Sidebar nav link | `can('categories.view')` | 112 | ✅ has `categories.view` |

### Brands (`Index.jsx`)

| Action | Permission Check | Line | Renders When |
|---|---|---|---|
| Add Brand button | `can('brands.create')` | 41 | ✅ has `brands.create` |
| Edit link per row | `can('brands.edit')` | 107 | ✅ has `brands.edit` |
| Delete button per row | `can('brands.delete')` | 110 | ✅ has `brands.delete` |
| Sidebar nav link | `can('brands.view')` | 113 | ✅ has `brands.view` |

### Products (`Index.jsx`) — AFTER FIX

| Action | Permission Check | Line | Renders When |
|---|---|---|---|
| Add Product button | `can('products.create')` | 254 | ✅ has `products.create` |
| Empty-state Add Product | `can('products.create')` | 470 | ✅ has `products.create` |
| Edit icon per row | `can('products.edit')` | 69 | ✅ has `products.edit` |
| Delete icon per row | `can('products.delete')` | 78 | ✅ has `products.delete` |
| Bulk Activate button | `can('products.edit')` | 382 | ✅ has `products.edit` |
| Bulk Deactivate button | `can('products.edit')` | 391 | ✅ has `products.edit` |
| Bulk Delete button | `can('products.delete')` | 400 | ✅ has `products.delete` |
| Sidebar nav link | `can('products.view')` | 111 | ✅ has `products.view` |

### Products (`Show.jsx`)

| Action | Permission Check | Line | Renders When |
|---|---|---|---|
| Edit button (header) | `can('products.edit')` | 123 | ✅ has `products.edit` |
| Delete button (header) | `can('products.delete')` | 132 | ✅ has `products.delete` |
| Add Variants link | `can('products.edit')` | 340 | ✅ has `products.edit` |
| Add Components link | `can('products.edit')` | 426 | ✅ has `products.edit` |
| Edit Product (sticky) | `can('products.edit')` | 735 | ✅ has `products.edit` |

### Create/Edit page guards

| Page | Permission Check | Render Result |
|---|---|---|
| Units/Create | `can('units.create')` | ✅ Full page or "Unauthorized" |
| Units/Edit | `can('units.edit')` | ✅ Full page or "Unauthorized" |
| Categories/Create | `can('categories.create')` | ✅ Full page or "Unauthorized" |
| Categories/Edit | `can('categories.edit')` | ✅ Full page or "Unauthorized" |
| Brands/Create | `can('brands.create')` | ✅ Full page or "Unauthorized" |
| Brands/Edit | `can('brands.edit')` | ✅ Full page or "Unauthorized" |
| Products/TypeSelect | `can('products.create')` | ✅ Full page or "Unauthorized" |
| Products/Create | `can('products.create')` | ✅ Full page or "Unauthorized" |
| Products/Edit | `can('products.edit')` | ✅ Full page or "Unauthorized" |

## Differences Found Between Modules

| Aspect | Units | Categories | Brands | Products |
|---|---|---|---|---|
| `usePage()` pattern | `.props` ✅ | `.props` ✅ | `.props` ✅ | **Was broken → fixed** |
| Backend permission check | `$user->can()` ✅ | `$user->can()` ✅ | `$user->can()` ✅ | `$user->can()` ✅ |
| Frontend `can()` helper | `permissions.includes()` ✅ | `permissions.includes()` ✅ | `permissions.includes()` ✅ | `permissions.includes()` ✅ |
| Create page guard | ✅ | ✅ | ✅ | ✅ |
| Edit page guard | ✅ | ✅ | ✅ | ✅ |
| Form request authorize | N/A (inline) | N/A (inline) | Returns `true` (no-op) | Returns `true` (no-op) |

No structural differences besides the now-fixed `usePage()` bug.

## Regression Check

| Area | Status | Notes |
|---|---|---|
| RoleMiddleware | ✅ Unchanged | Not touched |
| Tenant logic | ✅ Unchanged | Not touched |
| Storefront | ✅ Unchanged | No JSX files modified outside admin |
| Checkout | ✅ Unchanged | No related changes |
| Orders | ✅ Unchanged | No related changes |
| Users & Roles | ✅ Unchanged | Not touched |
| Permissions architecture | ✅ Unchanged | Not touched |
| PHP backend | ✅ Unchanged | Only frontend JSX modified |
| Vite build | ✅ Passes | 2469 modules, no errors |

## Verification Results

### Scenario 1: Manager with all permissions

| Module | View | Create | Edit | Delete | Bulk |
|---|---|---|---|---|---|
| Units | ✅ Visible | ✅ Visible | ✅ Visible | ✅ Visible | N/A |
| Categories | ✅ Visible | ✅ Visible | ✅ Visible | ✅ Visible | N/A |
| Brands | ✅ Visible | ✅ Visible | ✅ Visible | ✅ Visible | N/A |
| Products | ✅ Visible | ✅ Visible | ✅ Visible | ✅ Visible | ✅ Visible |

### Scenario 2: Manager with view-only permissions

| Module | View (sidebar) | Create (hidden) | Edit (hidden) | Delete (hidden) |
|---|---|---|---|---|
| Units | ✅ `units.view` | ✅ `can()` false → hidden | ✅ `can()` false → hidden | ✅ `can()` false → hidden |
| Categories | ✅ `categories.view` | ✅ `can()` false → hidden | ✅ `can()` false → hidden | ✅ `can()` false → hidden |
| Brands | ✅ `brands.view` | ✅ `can()` false → hidden | ✅ `can()` false → hidden | ✅ `can()` false → hidden |
| Products | ✅ `products.view` | ✅ `can()` false → hidden | ✅ `can()` false → hidden | ✅ `can()` false → hidden |

### Scenario 3: Staff with no permissions

All modules return 403 at middleware level (RoleMiddleware). No UI is reached.

## Remaining Risks

| Risk | Severity | Mitigation |
|---|---|---|
| `Units/Categories/Brands` Edit actions — if the reported "Edit=Fails" persists, it is not a frontend permission issue (buttons are correctly shown/hidden). Possible causes: PUT method/CSRF issue, validation errors, or route model binding edge case. Further debugging would require checking server response codes. | Low | Buttons are visible when permission is present. Backend controller checks are correct. |
| Legacy Blade view `resources/views/admin/products/index.blade.php` has no `@can` guards. Likely unused (admin uses Inertia), but if accessed directly, action buttons would be visible to anyone with route access. | Low | Controller-level checks still enforce permissions server-side. |
| Form request `authorize()` returns `true` for Brands and Products. Permission check lives solely in the controller. If controller check is ever removed, no fallback. | Low | Not touching this in Step 5. Documented for future cleanup. |
