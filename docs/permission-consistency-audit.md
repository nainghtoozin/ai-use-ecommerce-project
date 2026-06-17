# Permission Consistency Audit

## Root Causes Found

### Cause 1: Products Index — `usePage()` misuse (CONFIRMED BUG)

**File:** `resources/js/Pages/Admin/Products/Index.jsx:94`

```js
// BUG: In Inertia v3, usePage() returns { component, props, url, version }
// auth is inside props.auth, NOT at the top level
const { url, auth } = usePage();
```

**Effect:** `auth` is `undefined`, so `permissions` evaluates to `[]`, and every `can()` call returns `false`. This hides **all** action buttons in the Products list — Add Product, Edit, Delete, Bulk Actions, and the empty-state "Add Product" link.

**Contrast with working modules:**

| Module | File | Line | Pattern | Status |
|---|---|---|---|---|
| Units | `Index.jsx` | 7 | `const { auth } = usePage().props` | ✅ Correct |
| Categories | `Index.jsx` | 7 | `const { auth } = usePage().props` | ✅ Correct |
| Brands | `Index.jsx` | 8 | `const { auth } = usePage().props` | ✅ Correct |
| Products | `Index.jsx` | 94 | `const { url, auth } = usePage()` | ❌ `auth` is undefined |

All four **Edit.jsx** and **Create.jsx** pages correctly use `usePage().props`:
- `Units/Edit.jsx:6` — `const { auth } = usePage().props;` ✅
- `Categories/Edit.jsx` — `const { auth } = usePage().props;` ✅
- `Brands/Edit.jsx` — `const { auth } = usePage().props;` ✅
- `Products/Edit.jsx:21` — `const { auth } = usePage().props;` ✅
- `Products/Create.jsx` — `const { auth } = usePage().props;` ✅

This explains **four** of the seven reported failures:
- Products: Create=Fails, Edit=Fails, Delete=Fails
- All because frontend action buttons are hidden

### Cause 2: Edit failure pattern across all four modules (POTENTIAL)

Units, Categories, and Brands show correct frontend permissions (buttons visible), but the user reports "Edit=Fails" for all three. Possible contributing factors investigated:

#### a) ALL controllers check `*.edit` consistently ✅
No naming mismatch. Every `edit()` and `update()` method checks the correct permission string (`units.edit`, `categories.edit`, `brands.edit`, `products.edit`).

#### b) No per-route middleware blocking edit routes ✅
Routes are inspected: no `->middleware('can:*.edit')` anywhere. All routes share the same inherited middleware (`auth`, `role:admin`, tenant middleware).

#### c) Form requests are non-blocking ✅
All form requests either don't exist (Units, Categories) or return `true` from `authorize()` (Brands, Products).

#### d) No policies or gates interfere ✅
No policy class or Gate definition exists for any of the four models. The Spatie permission system is the sole authorization layer after RoleMiddleware.

## Affected Modules

| Module | View | Create | Edit | Delete | Root Cause |
|---|---|---|---|---|---|
| **Units** | ✅ Works | ✅ Works | ❌ Reported Fails | ✅ Works | Not the `usePage()` bug. Likely a different issue (see below). |
| **Categories** | ✅ Works | ✅ Works | ❌ Reported Fails | ✅ Works | Same as Units. |
| **Brands** | ✅ Works | ✅ Works | ❌ Reported Fails | ✅ Works | Same as Units. |
| **Products** | ✅ Works | ❌ Fails | ❌ Fails | ❌ Fails | **Cause 1 confirmed**: `usePage()` bug hides all action buttons. |

## Permission Matrix

| Module | Action | Permission Required | Controller Check | Policy | Frontend Check | Status |
|---|---|---|---|---|---|---|
| Units | view | `units.view` | `$user->can('units.view')` | None | `permissions.includes('units.view')` | ✅ |
| Units | create | `units.create` | `$user->can('units.create')` | None | `permissions.includes('units.create')` | ✅ |
| Units | edit | `units.edit` | `$user->can('units.edit')` | None | `permissions.includes('units.edit')` | ✅ |
| Units | delete | `units.delete` | `$user->can('units.delete')` | None | `permissions.includes('units.delete')` | ✅ |
| Categories | view | `categories.view` | `$user->can('categories.view')` | None | `permissions.includes('categories.view')` | ✅ |
| Categories | create | `categories.create` | `$user->can('categories.create')` | None | `permissions.includes('categories.create')` | ✅ |
| Categories | edit | `categories.edit` | `$user->can('categories.edit')` | None | `permissions.includes('categories.edit')` | ✅ |
| Categories | delete | `categories.delete` | `$user->can('categories.delete')` | None | `permissions.includes('categories.delete')` | ✅ |
| Brands | view | `brands.view` | `$user->can('brands.view')` | None | `permissions.includes('brands.view')` | ✅ |
| Brands | create | `brands.create` | `$user->can('brands.create')` | None | `permissions.includes('brands.create')` | ✅ |
| Brands | edit | `brands.edit` | `$user->can('brands.edit')` | None | `permissions.includes('brands.edit')` | ✅ |
| Brands | delete | `brands.delete` | `$user->can('brands.delete')` | None | `permissions.includes('brands.delete')` | ✅ |
| Products | view | `products.view` | `$user->can('products.view')` | None | `permissions.includes('products.view')` | ✅ |
| Products | create | `products.create` | `$user->can('products.create')` | None | `permissions.includes('products.create')` | ❌ Bug |
| Products | edit | `products.edit` | `$user->can('products.edit')` | None | `permissions.includes('products.edit')` | ❌ Bug |
| Products | delete | `products.delete` | `$user->can('products.delete')` | None | `permissions.includes('products.delete')` | ❌ Bug |

**Legend:** ✅ = Check exists and matches permission name. ❌ = Check missing or broken.

## Naming Consistency Check

All permission strings were cross-referenced between `PermissionSeeder.php`, `RoleAndPermissionSeeder.php`, controllers, frontend components, and RBAC UI:

| Permission | Seeder | Role Assignment | Controller | Frontend | Match? |
|---|---|---|---|---|---|
| `units.view` | ✅ | ✅ | `AdminUnitController@index`, `@search` | `Units/Index.jsx` sidebar | ✅ |
| `units.create` | ✅ | ✅ | `AdminUnitController@create`, `@store` | `Units/Index.jsx`, `Units/Create.jsx` | ✅ |
| `units.edit` | ✅ | ✅ | `AdminUnitController@edit`, `@update` | `Units/Index.jsx`, `Units/Edit.jsx` | ✅ |
| `units.delete` | ✅ | ✅ | `AdminUnitController@destroy` | `Units/Index.jsx` | ✅ |
| `categories.view` | ✅ | ✅ | `AdminCategoryController@index`, `@search` | `Categories/Index.jsx` sidebar | ✅ |
| `categories.create` | ✅ | ✅ | `AdminCategoryController@create`, `@store` | `Categories/Index.jsx`, `Categories/Create.jsx` | ✅ |
| `categories.edit` | ✅ | ✅ | `AdminCategoryController@edit`, `@update` | `Categories/Index.jsx`, `Categories/Edit.jsx` | ✅ |
| `categories.delete` | ✅ | ✅ | `AdminCategoryController@destroy` | `Categories/Index.jsx` | ✅ |
| `brands.view` | ✅ | ✅ | `AdminBrandController@index`, `@search` | `Brands/Index.jsx` sidebar | ✅ |
| `brands.create` | ✅ | ✅ | `AdminBrandController@create`, `@store` | `Brands/Index.jsx`, `Brands/Create.jsx` | ✅ |
| `brands.edit` | ✅ | ✅ | `AdminBrandController@edit`, `@update` | `Brands/Index.jsx`, `Brands/Edit.jsx` | ✅ |
| `brands.delete` | ✅ | ✅ | `AdminBrandController@destroy` | `Brands/Index.jsx` | ✅ |
| `products.view` | ✅ | ✅ | `AdminProductController@index`, `@search`, `@show` | `Products/Index.jsx`, `Products/Show.jsx` sidebar | ✅ |
| `products.create` | ✅ | ✅ | `AdminProductController@typeSelect`, `@create`, `@store` | `Products/Index.jsx`, `Products/Create.jsx`, `Products/TypeSelect.jsx` | ✅ |
| `products.edit` | ✅ | ✅ | `AdminProductController@edit`, `@update`, `@bulkActivate`, `@bulkDeactivate` | `Products/Index.jsx`, `Products/Edit.jsx`, `Products/Show.jsx` | ✅ |
| `products.delete` | ✅ | ✅ | `AdminProductController@destroy`, `@bulkDestroy` | `Products/Index.jsx`, `Products/Show.jsx` | ✅ |

**No naming mismatches found.** The permission string is identical across every layer.

## Duplicate Authorization Check

Each action's authorization path was traced:

| Module | Route Middleware | Controller `can()` | Form Request `authorize()` | Policy | Gate | Total Layers |
|---|---|---|---|---|---|---|
| Units | `role:admin` | ✅ `units.*` | N/A (no form request) | None | None | 2 |
| Categories | `role:admin` | ✅ `categories.*` | N/A (no form request) | None | None | 2 |
| Brands | `role:admin` | ✅ `brands.*` | ❌ Returns `true` (no-op) | None | None | 2 |
| Products | `role:admin` | ✅ `products.*` | ❌ Returns `true` (no-op) | None | None | 2 |

No duplicate authorization detected. Each action has exactly two layers: RoleMiddleware (broad gate) and controller `$user->can()` (specific check). The form requests for Brands and Products are effectively dead code for authorization.

## Why Each Failing Action Fails (with Manager role)

### Products: Create = Fails
Confirmed root cause: `Products/Index.jsx:94` — `const { url, auth } = usePage()` — `auth` is `undefined`, so `can('products.create')` returns `false`. The "Add Product" button is hidden. The TypeSelect, Create, and Store flows are never reached. **The underlying server authorization would succeed if the button were visible.**

### Products: Edit = Fails
Same root cause as Create. The Edit button is hidden in the Index page.

### Products: Delete = Fails
Same root cause. The Delete button is hidden.

### Units/Categories/Brands: Edit = Fails
These modules correctly use `usePage().props` and show the Edit button. The reported failure requires further investigation:

Possible scenarios (in order of likelihood):
1. **PUT method issue**: The edit form submits via PUT. If there's a CSRF token mismatch or method spoofing issue, the server returns 419 (Page Expired) rather than 403. This would appear as a generic failure.
2. **Validation error not surfaced**: If `update()` validation fails (e.g., unique constraint because tenant scoping doesn't include the current model's ID), the form returns with validation errors that may or may not be displayed.
3. **Model not found (404)**: If route model binding fails due to tenant scoping, the `edit()` route returns 404 before the permission check runs. But this would also affect `show()` and `destroy()` routes that use the same model binding.
4. **Session timeout**: If the user's session expires between loading the edit form and submitting it, the request fails.

## Recommended Fix Order

### Fix 1: Products Index.jsx `usePage()` bug
**File:** `resources/js/Pages/Admin/Products/Index.jsx`

Change:
```js
const { url, auth } = usePage();
```
To:
```js
const { url, auth } = usePage();  // url is fine from top level
```
And add:
```js
const props = usePage().props;
const auth = props.auth;
```
Or the one-liner:
```js
const { url } = usePage();
const permissions = usePage().props.auth?.user?.permissions || [];
```

**Impact:** Fixes 4 failures (Products create, edit, delete, bulk).

### Fix 2: Investigate Edit failures on Units/Categories/Brands
Likely not a permission issue. Check:
- Verify that PUT requests are not being blocked by CSRF middleware
- Check that `edit()` and `update()` have `$user->can('*.edit')` checks (they do)
- Add logging to determine exact response status code when Edit fails
- Test with a superadmin user to rule out permission issues

### Fix 3 (Optional): Remove dead `authorize()` from form requests
Brands and Products form requests have `authorize() { return true; }`. Either delete these methods or move the permission check from the controller into the form request for cleaner separation.

## Modules with No Issues

- **Dashboard**: Uses `dashboard.view` — consistent across all layers
- **Orders**: Uses `orders.view`, `orders.update-status`, `orders.cancel-any` — consistent
- **Payments**: Uses `payments.view`, `payments.verify` — consistent
- **Settings**: Uses `settings.view`, `settings.edit` — consistent
- **Role Management**: Uses `roles.view`, `roles.create`, `roles.update`, `roles.delete` — consistent
- **User Management**: Uses `users.*` permissions — consistent

## Unchanged Files (No modification needed)

| Module | Routes | Controllers | Policies | Gates | Middleware | Seeders |
|---|---|---|---|---|---|---|
| Units | ✅ | ✅ | N/A | N/A | ✅ | ✅ |
| Categories | ✅ | ✅ | N/A | N/A | ✅ | ✅ |
| Brands | ✅ | ✅ | N/A | N/A | ✅ | ✅ |
| Products | ✅ | ✅ | N/A | N/A | ✅ | ✅ |

## Audit Summary

| Metric | Count |
|---|---|
| Files with definitive bugs | 1 (`resources/js/Pages/Admin/Products/Index.jsx`) |
| Confirmed permission naming mismatches | 0 |
| Missing controller permission checks | 0 |
| Missing frontend permission checks | 0 (but bug makes them non-functional) |
| Policy gaps (no policy class for model) | 4 (Unit, Category, Brand, Product) |
| Unused form request `authorize()` | 4 (StoreBrandRequest, UpdateBrandRequest, StoreProductRequest, UpdateProductRequest) |
| Unused Blade `@can` in legacy views | 1 (`resources/views/admin/products/index.blade.php` — no guards) |
| Gate definitions for these modules | 0 |
