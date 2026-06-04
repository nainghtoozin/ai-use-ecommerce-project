# Customer-Side SaaS Isolation Audit

**Date:** 2026-06-03
**Scope:** All customer-facing flows — registration, login, orders, addresses, cart, wishlist, reviews, profile, checkout
**Methodology:** Source code review of controllers, models, middleware, services, routes, and database migrations

---

## Tenant Architecture Overview

| Component | Description |
|---|---|
| **IdentifyTenant middleware** | Global web middleware; resolves current tenant via user→tenant_id, subdomain, header, session, or default |
| **Tenant::getCurrent()** | Returns tenant from `App::make('current.tenant')` binding |
| **TenantAware trait** | Adds `TenantScope` global scope + auto-assigns `tenant_id` on `creating` event |
| **TenantScope** | Global scope applying `WHERE tenant_id = ?` to all queries; exempts `Role`, `ActivityLog`; skips during migrations |
| **User booted()** | Custom `creating` handler; assigns `tenant_id` from `Tenant::getCurrent()` (User does NOT use TenantAware) |

---

## Module Audit Results

### 1. Customer Registration

**Result: PASS**

| Check | Status | Details |
|---|---|---|
| tenant_id assignment | **PASS** | `User::booted()` → `creating` event calls `Tenant::getCurrent()` and sets `tenant_id` automatically |
| tenant filtering | **PASS** | `IdentifyTenant` middleware runs globally (before registration route), so `current.tenant` is already bound |
| validation isolation | **PASS** | Email uniqueness is global (`unique:users`) — correct for SaaS since emails must be globally unique |
| query isolation | **PASS** | `User` model has no global scope, but registration only creates a single user record |
| ownership checks | N/A | Registration creates new user, no ownership check needed |

**Flow:**
1. `IdentifyTenant` middleware runs → resolves tenant (subdomain/header/session/default)
2. `RegisteredUserController::store()` → `User::create([...])`
3. User booted event → `tenant_id = Tenant::getCurrent()->id`
4. `Role::firstOrCreate(['name' => 'customer', 'tenant_id' => Tenant::getCurrent()?->id])` → tenant-scoped role
5. `$user->assignRole($customerRole)` → user gets tenant-specific role

**Concern addressed:** New registrations are assigned to the **current store's tenant** via `Tenant::getCurrent()`, NOT hardcoded to `tenant_id = 1`. Store A registration → tenant A. Store B registration → tenant B.

**Root cause (of original concern):** No issue found. The `Tenant::getCurrent()` resolution path works correctly for multi-tenant registration.

---

### 2. Customer Login

**Result: PASS**

| Check | Status | Details |
|---|---|---|
| tenant_id assignment | N/A | Login does not create records |
| tenant filtering | **PASS** | `AuthenticatedSessionController::store()` checks user status AND tenant status before authenticating |
| validation isolation | **PASS** | Login validation (email/password) is standard auth |
| query isolation | **PASS** | `User::where('email', ...)` query is not tenant-scoped — correct, user can exist in any tenant |
| ownership checks | **PASS** | User's tenant is checked for suspension/ban before allowing login |

**Key checks:**
- User status (suspended/banned/inactive) → blocked
- Tenant status (suspended) → blocked
- SuperAdmin bypasses tenant checks

---

### 3. Customer Orders

**Result: PASS**

| Check | Status | Details |
|---|---|---|
| tenant_id assignment | **PASS** | `Order` model uses `TenantAware` trait; `creating` event auto-assigns `tenant_id` from `Tenant::getCurrent()` |
| tenant filtering | **PASS** | `TenantScope` global scope applies `WHERE orders.tenant_id = ?` to all order queries |
| validation isolation | **PASS** | Order validation checks `user_id` (ownership) within tenant scope |
| query isolation | **PASS** | All order queries in `OrderController`, `ClientOrderController`, `AdminOrderController` are tenant-scoped |
| ownership checks | **PASS** | Customer queries add `->where('user_id', auth()->id())` — double-layered with tenant scope |

**Affected files:**
- `app/Models/Order.php` — uses `TenantAware` (line 12)
- `app/Models/OrderItem.php` — uses `TenantAware` (line 11)
- `app/Http/Controllers/OrderController.php` — tenant scope applies globally
- `app/Http/Controllers/Client/ClientOrderController.php` — tenant scope applies globally

**Note:** `OrderCoupon` model (pivot) does NOT use `TenantAware` — see **Issues** section.

---

### 4. Customer Addresses

**Result: PASS**

| Check | Status | Details |
|---|---|---|
| tenant_id assignment | **PASS** | `City` and `Township` models use `TenantAware` |
| tenant filtering | **PASS** | TenantScope auto-filters city/township queries |
| validation isolation | **PASS** | City/township selection is tenant-scoped during checkout |
| query isolation | **PASS** | All city/township queries are filtered by current tenant |
| ownership checks | N/A | Cities/townships are reference data, not user-owned |

**Note:** No separate Address/CustomerAddress model exists. Addresses are captured as flat fields on the Order record (`first_name`, `last_name`, `phone`, `address`, `city_id`, `township_id`, `postal_code`).

---

### 5. Cart

**Result: PASS (low-severity note)**

| Check | Status | Details |
|---|---|---|
| tenant_id assignment | N/A | Cart is entirely session-based (no database table) |
| tenant filtering | **PASS** | Product/coupon/promotion queries within cart logic are tenant-scoped via `TenantAware` |
| validation isolation | **PASS** | Cart operations validate product existence within tenant scope |
| query isolation | **PASS** | All database queries from cart use models with `TenantAware` |
| ownership checks | N/A | Cart is session-based, not user-owned |

**Note:** Cart session data (`session('cart')`) is NOT tenant-prefixed. If the same browser accesses two different tenant stores, cart data could leak between tenants.

**Risk level:** LOW — each tenant typically runs on a different domain/subdomain with separate sessions.

---

### 6. Wishlist

**Result: PASS**

| Check | Status | Details |
|---|---|---|
| tenant_id assignment | **PASS** | `Wishlist` model uses `TenantAware`; auto-assigns `tenant_id` on create |
| tenant filtering | **PASS** | `TenantScope` global scope applies |
| validation isolation | **PASS** | Product existence validated within tenant scope |
| query isolation | **PASS** | All wishlist queries are tenant-scoped |
| ownership checks | **PASS** | Queries scoped by `user_id` AND tenant scope |

**Affected files:**
- `app/Models/Wishlist.php` — uses `TenantAware` (line 10)
- `app/Http/Controllers/WishlistController.php` — queries auto-filtered

---

### 7. Reviews / Ratings

**Result: N/A (Not Implemented)**

| Check | Status | Details |
|---|---|---|
| Model | N/A | No Review/Rating model exists |
| Controller | N/A | No ReviewController exists |
| Migration | N/A | No reviews database table exists |
| UI | N/A | Feature setting `enable_reviews` exists in `WebsiteInfo` but no backend implementation |

**Note:** The `enable_reviews` boolean field exists in the `WebsiteInfo` model and admin settings page, but the feature is not yet built. When implemented, it must use the `TenantAware` trait for proper isolation.

---

### 8. Customer Profile

**Result: PASS**

| Check | Status | Details |
|---|---|---|
| tenant_id assignment | N/A | Profile update does not change `tenant_id` |
| tenant filtering | **PASS** | `ProfileController` uses `$request->user()` — no cross-tenant queries |
| validation isolation | **PASS** | Profile validation operates on authenticated user only |
| query isolation | **PASS** | All operations use `$request->user()` — no arbitrary user queries |
| ownership checks | **PASS** | User can only edit their own profile (enforced by `$request->user()`) |

**Key protection:** `tenant_id` is NOT in the `$fillable` array on the `User` model — mass assignment protection prevents malicious tenant_id changes via `fill()`.

---

### 9. Checkout

**Result: PASS**

| Check | Status | Details |
|---|---|---|
| tenant_id assignment | **PASS** | `Order::create()` fires `TenantAware` creating event → auto-assigns `tenant_id` |
| tenant filtering | **PASS** | Payment methods, cities, townships, products — all tenant-scoped |
| validation isolation | **PASS** | Cart total, stock, and address validation within tenant scope |
| query isolation | **PASS** | `CheckoutController` and `OrderController::store()` queries all tenant-scoped |
| ownership checks | **PASS** | Order assigned to authenticated user; `user_id` tracked on order |

**Affected files:**
- `app/Http/Controllers/CheckoutController.php` — queries tenant-scoped
- `app/Http/Controllers/OrderController.php` — store method creates tenant-scoped order
- `app/Services/OrderService.php` — `Order::create()` auto-assigns tenant_id

---

## Issues Found

### Issue #1: OrderCoupon Model Lacks TenantAware

| Field | Value |
|---|---|
| **Severity** | MEDIUM |
| **Status** | WARNING |
| **Root Cause** | `app/Models/OrderCoupon.php` does not use `TenantAware` trait |
| **Evidence** | Line 4: `class OrderCoupon extends Pivot { ... }` — no `use TenantAware` |
| **Impact** | Direct queries on `OrderCoupon` bypass tenant filtering. The `order_coupon` table has a `tenant_id` column but the model has no global scope. |
| **Mitigation** | This model is typically accessed through the `Order::coupons()` relationship, which IS tenant-scoped. Direct `OrderCoupon::where(...)` queries would leak across tenants. |
| **Recommendation** | Add `use TenantAware;` to `OrderCoupon` model and ensure `tenant_id` is handled correctly for pivot models. |

### Issue #2: User Model Has No Global Tenant Scope

| Field | Value |
|---|---|
| **Severity** | LOW |
| **Status** | WARNING |
| **Root Cause** | `app/Models/User.php` does not use `TenantAware` trait; has custom `booted()` for tenant_id assignment only |
| **Evidence** | `User` model has no global scope — `User::all()` returns ALL users across all tenants |
| **Impact** | Any query that forgets to call `->forTenant()` on the User model will return users from other tenants |
| **Examples** | `User::role('admin')` queries in `OrderService.php` (line 449) correctly add `->where('users.tenant_id', $tenantId)`, but other queries might not |
| **Recommendation** | Either add the global scope or audit all User queries to ensure `->forTenant()` is called. Given that User is shared across customer/admin/superadmin, the lack of global scope may be intentional. |

### Issue #3: Cart Session Not Tenant-Prefixed

| Field | Value |
|---|---|
| **Severity** | LOW |
| **Status** | WARNING |
| **Root Cause** | Cart stored in `session()->get('cart', [])` — no tenant key prefix |
| **Evidence** | `CartController.php` uses `session('cart')` without tenant context |
| **Impact** | If same browser accesses multiple tenants (e.g., via different subdomains on same domain), cart data could leak |
| **Mitigation** | Different tenants typically use different domains/subdomains → separate sessions in practice |
| **Recommendation** | Prefix cart session key with `cart_{tenant_id}` to prevent any cross-tenant data leakage |

---

## Summary

| Module | Status | Risk |
|---|---|---|
| 1. Customer Registration | **PASS** | None |
| 2. Customer Login | **PASS** | None |
| 3. Customer Orders | **PASS** | None |
| 4. Customer Addresses | **PASS** | None |
| 5. Cart | **PASS** | LOW (session isolation) |
| 6. Wishlist | **PASS** | None |
| 7. Reviews / Ratings | **N/A** | Not implemented |
| 8. Customer Profile | **PASS** | None |
| 9. Checkout | **PASS** | None |
| **OrderCoupon Model** | **WARNING** | MEDIUM |
| **User Model Scope** | **WARNING** | LOW |

**Overall: 9/9 customer-facing modules PASS. 2 warnings (MEDIUM + LOW).**

---

## Key Files Audited

| File | Path |
|---|---|
| User Model | `app/Models/User.php` |
| Order Model | `app/Models/Order.php` |
| OrderItem Model | `app/Models/OrderItem.php` |
| OrderCoupon Model | `app/Models/OrderCoupon.php` |
| Wishlist Model | `app/Models/Wishlist.php` |
| City Model | `app/Models/City.php` |
| Township Model | `app/Models/Township.php` |
| PaymentMethod Model | `app/Models/PaymentMethod.php` |
| Product Model | `app/Models/Product.php` |
| Category Model | `app/Models/Category.php` |
| Coupon Model | `app/Models/Coupon.php` |
| Promotion Model | `app/Models/Promotion.php` |
| WebsiteInfo Model | `app/Models/WebsiteInfo.php` |
| CartController | `app/Http/Controllers/CartController.php` |
| CheckoutController | `app/Http/Controllers/CheckoutController.php` |
| OrderController | `app/Http/Controllers/OrderController.php` |
| ProfileController | `app/Http/Controllers/ProfileController.php` |
| WishlistController | `app/Http/Controllers/WishlistController.php` |
| RegisteredUserController | `app/Http/Controllers/Auth/RegisteredUserController.php` |
| AuthenticatedSessionController | `app/Http/Controllers/Auth/AuthenticatedSessionController.php` |
| ClientOrderController | `app/Http/Controllers/Client/ClientOrderController.php` |
| OrderService | `app/Services/OrderService.php` |
| TenantAware Trait | `app/Models/Traits/TenantAware.php` |
| TenantScope | `app/Models/Scopes/TenantScope.php` |
| IdentifyTenant Middleware | `app/Http/Middleware/IdentifyTenant.php` |
| Tenant Model | `app/Models/Tenant.php` |
| Routes | `routes/web.php` |
| Auth Routes | `routes/auth.php` |
| Middleware Config | `bootstrap/app.php` |

---

## Verification Steps

To manually verify tenant isolation:

1. **Store A Registration:** Register a customer on Store A → verify `users.tenant_id = Store A's tenant ID`
2. **Store B Registration:** Register a customer on Store B → verify `users.tenant_id = Store B's tenant ID`
3. **Store A Login:** Login to Store A → verify only Store A's orders/products are visible
4. **Store B Login:** Login to Store B → verify only Store B's orders/products are visible
5. **Cross-Tenant Access:** Try to access `/orders/{order_id_from_store_a}` while logged into Store B → should return 404 (order not found in tenant scope)
6. **Wishlist Product:** Add product to wishlist → verify `wishlists.tenant_id` matches current tenant
7. **Checkout:** Complete checkout → verify `orders.tenant_id` matches current tenant
