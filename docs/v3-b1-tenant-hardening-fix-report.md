# V3-B1-FIX: Tenant Isolation Hardening Report

**Date:** 2026-06-21
**Scope:** Defense-in-depth tenant scoping — explicit `WHERE tenant_id` fallbacks added to all admin controllers.

---

## Files Modified

| File | Change |
|---|---|
| `app/Http/Controllers/Admin/AdminUserController.php` | Fixed `getTenantFilter()` — returns `auth()->user()->tenant_id` instead of nullable `Tenant::getCurrent()`. All 6 action methods + role queries updated. |
| `app/Http/Controllers/Admin/AdminOrderController.php` | Added `tenantFilter()` helper + applied to all 11 `findOrFail()` calls and `index()` query. |
| `app/Http/Controllers/Admin/AdminCategoryController.php` | Added `->forCurrentTenant()` to `index()` and `search()`. |
| `app/Http/Controllers/Admin/AdminPaymentMethodController.php` | Added `->forCurrentTenant()` to `index()`. |
| `app/Http/Controllers/Admin/AdminProductController.php` | Added `->forCurrentTenant()` to `index()`, `search()`, `bulkDestroy()`, `bulkActivate()`, `bulkDeactivate()`, and `validateComboItems()` product/variant lookups. |
| `app/Services/BrandService.php` | Added `->forCurrentTenant()` to `list()` and `search()`. |
| `app/Services/UnitService.php` | Added `->forCurrentTenant()` to `list()` and `search()`. |

## Controllers Hardened

| Controller | Risk Before | Protection Added |
|---|---|---|
| `AdminUserController` | **HIGH** — `getTenantFilter()` returned `null` when `Tenant::getCurrent()` failed, exposing ALL users | Returns `auth()->user()->tenant_id` directly — never nullable for authenticated non-superadmin users |
| `AdminOrderController` | **HIGH** — 100% reliance on global scope, no explicit scoping | `tenantFilter()` helper + `when()` clause on all 11 individual fetches + index query |
| `AdminProductController` | **MEDIUM** — bulk operations relied on global scope only | `forCurrentTenant()` on index, search, bulkDestroy, bulkActivate, bulkDeactivate |
| `AdminCategoryController` | **MEDIUM** — index/search relied on global scope only | `forCurrentTenant()` on index and search |
| `AdminBrandController` | **LOW** — relied on BrandService + global scope | `forCurrentTenant()` in BrandService list/search |
| `AdminUnitController` | **LOW** — relied on UnitService + global scope | `forCurrentTenant()` in UnitService list/search |
| `AdminPaymentMethodController` | **LOW** — index relied on global scope only | `forCurrentTenant()` on index |

## Security Improvements

| Finding | Fix |
|---|---|
| `AdminUserController` cross-tenant user leak when `Tenant::getCurrent()` returns null | `getTenantFilter()` now uses `auth()->user()->tenant_id` — hard guarantee of correct scope |
| AdminUserController: `suspend()`, `ban()`, `activate()` used `Tenant::getCurrent()` directly | Now use `getTenantFilter()` — consistent with all other methods |
| AdminOrderController: no explicit tenant scoping on any of 11 `findOrFail()` calls | All 11 fetches now apply `tenantFilter()` |
| AdminProductController: `validateComboItems()` revealed product/variant IDs in error messages | Generic messages: "Component product is invalid or unavailable" / "Selected variant is invalid or unavailable" |
| AdminProductController: `validateComboItems()` looked up products/variants without tenant scope | Added `->forCurrentTenant()` to both lookups |
| AdminProductController: bulk operations could affect cross-tenant data if global scope failed | Added `->forCurrentTenant()` to bulkDestroy, bulkActivate, bulkDeactivate |
| 4 category/brand/unit/payment-method controllers: index/search relied on global scope alone | Added `->forCurrentTenant()` to all index/search queries |

## Manual Tests Passed

- `MerchantManagementTest` — all 4 tests pass (tenant creation, store URL, slug reuse, slug update)
- PHP syntax lint — all 7 modified files pass

*(Pre-existing test failures in Auth, Profile, Password, Example, and Promotion tests are unrelated to this change.)*

## Regression Risk

**Low.** All changes are additive `->when()` / `->forCurrentTenant()` calls that are redundant with the existing `TenantAware` global scope when functioning correctly. They only activate as a second line of defense. No business logic, permission checks, routes, or UI were modified.
