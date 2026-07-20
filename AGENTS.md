# Production Readiness — Anchored Summary

Date: 2026-07-20

## Sprint 9.2 — Subscription & Billing (Complete)

| Area | Status |
|------|--------|
| Invoices (model, migration, service, controller, auto-generation listener, pages) | ✅ Complete |
| Plan change with proration (upgrade immediate, downgrade scheduled) | ✅ Complete |
| Subscription status banner (6 states in AdminLayout) | ✅ Complete |
| Renewal with trial limit checks | ✅ Complete |
| Expiry lifecycle (active→past_due→expired→suspended, trial→expired) | ✅ Complete |
| Grace period (7-day, UI countdown) | ✅ Complete |
| Payment verification flow (evidence upload, review, approve/reject) | ✅ Complete |
| Console commands (`subscriptions:send-reminders`, `subscriptions:apply-scheduled-changes`) | ✅ Complete |

**Audit report:** `docs/sprint-9.2-subscription-billing-audit.md`

## 9-Point Production Readiness Checklist

### 1. Logout & Session Cleanup ✅
- `AuthenticatedSessionController::destroy()` calls `session()->invalidate()` + `regenerateToken()` after logout
- Captures tenant slug before session invalidation for redirect routing
- Activity logged on logout

### 2. Tenant Context Verification ✅
- `IdentifyTenant` middleware resolves tenant from session → membership → subdomain → header
- `CheckTenantAccess` middleware enforces tenant membership; logs out on mismatch
- `TenantAware` trait used by 35+ models (automatic global scope on `tenant_id`)

### 3. Permission Cache Clearing ✅
- `RoleObserver` + `PermissionObserver` clear Spatie cache via `PermissionCacheService` on model changes
- `PermissionCacheService::clearAll()` / `clearForRole()` / `clearForTenant()` available
- Cache TTL: 24 hours; cleared immediately on role/permission CRUD (observers handle it)
- **Note:** Team membership changes (role assignment, suspend, remove) do NOT require cache flush — Spatie reads user-role pivot from DB directly, role→permission mapping in cache is unaffected

### 4. Session Consistency ⚠️ FIXED
- **Password change now invalidates sessions** — `PasswordController::update()` (`app/Http/Controllers/Auth/PasswordController.php:28-33`) calls `Auth::logout()`, `session()->invalidate()`, `regenerateToken()` after password update
- AuthenticateSession middleware not in web group (password.confirm routes defined but never used)

### 5. Tenant Isolation ✅
- `TenantAware` trait provides global scope filtering by `tenant_id` across all business models
- `TenantScope` enforces `$table.tenant_id = ?` on every query
- `withoutTenantScope()` available for admin/cross-tenant operations

### 6. Frontend Authorization ✅
- Sidebar, buttons, and menu items use `can(perm)` from `auth.user.permissions` (Inertia-shared)
- Every sensitive action has server-side `abort(403)` backup in controller
- `IdentityProjection` sends full permission list to client (design choice, not a bug)

### 7. Performance 🔍
- Reports use single-pass aggregation queries with `DB::raw` CASE expressions
- Tenant data loaded eager via relationships
- **Critical:** Order creation was missing stock validation and database transaction — **FIXED** (see below)

### 8. Security ⚠️ DEFERRED
- No 2FA implementation — deferred (feature request)
- No password confirmation on sensitive admin actions — deferred (permission gates are sufficient for current threat model)
- PasswordController session invalidation after password change — **FIXED**

### 9. Regression ✅
- Phase 8 audit: 1630 order anomalies confirmed pre-existing (not caused by refactors)
- Overrides table rename (`order_override_logs`) verified
- Order presets table verified

---

## Fixes Applied

### CRITICAL: Password change now invalidates sessions
**File:** `app/Http/Controllers/Auth/PasswordController.php:28-33`
- After password hash update, calls `Auth::logout()`, `session()->invalidate()`, `session()->regenerateToken()`
- Prevents session hijacking after password change — existing sessions (including current) are invalidated
- User redirected to login with success message

### CRITICAL: Order creation wrapped in DB transaction
**File:** `app/Http/Controllers/OrderController.php:184-229`
- `DB::beginTransaction()` / `commit()` / `rollBack()` around order + coupon + promotion + items creation
- Prevents partial writes / orphaned orders on failure
- `ProcessOrderNotifications` dispatch and cart clearing moved outside transaction
- Full error logging with stack trace on failure

### CRITICAL: Stock validation before order placement
**File:** `app/Http/Controllers/OrderController.php:135-138, 293-325`
- `validateStock()` bulk-loads products and variants, checks stock for each cart item
- Checks variant-level stock for variable products, product-level stock for simple products
- Returns user-friendly error messages per item
- Prevents overselling

---

## Deferred Items

| Issue | Reasoning |
|-------|-----------|
| No 2FA | Feature enhancement, not a bug. Requires auth redesign |
| No password confirmation on admin actions | Permission gates + authorization middleware provide sufficient protection for current scope |
| Missing idempotency key on order creation | Race condition window is small; transaction + stock validation reduce risk significantly |
| Race condition in stock check (non-locking read) | `validateStock()` runs before transaction; concurrent orders could race. Future: add `lockForUpdate()` inside transaction |
| AuthenticateSession middleware not registered | Adding it would break password confirmation flow without additional work. Deferred to post-launch hardening |
| Invoice download as HTML (not PDF) | No PDF library (dompdf) available; browser "Save as PDF" is the workaround |
| No Stripe/PayPal gateway integration | Only manual payment gateway implemented; real-time gateways deferred |
| No idempotency key on plan change execution | Transaction wrapping prevents partial writes; race window is small |
| Grace period hard-coded at 7 days | Future: make configurable per-plan or via platform settings |
