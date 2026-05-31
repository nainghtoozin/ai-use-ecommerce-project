# SaaS v2 Production Checklist

> **Version:** 2.0.0 Release Candidate
> **Audit Status:** All 20 prior fix areas verified. Subscription/billing system requires completion before paid multi-plan launch.

---

## PHASE 1 — Pre-Deployment Verification (All Items REQUIRED)

### 1.1 Data Isolation Verification

- [x] **TenantScope** — `orWhereNull(tenant_id)` gated behind `allowsNullTenantFallback()`
- [x] **Tenant::getCurrent()** — Returns `null` in queue/console context (no default tenant fallback)
- [x] **TenantAware trait** — Correctly auto-sets `tenant_id` on create for all tenant-owned models
- [x] **User model** — `tenant_id` removed from `$fillable`, set via `booted()` hook
- [x] **Subscription model** — `TenantAware` added, `tenant_id` removed from `$fillable`
- [x] **Role model** — `tenant_id` removed from `$fillable`

### 1.2 Query Isolation Verification

- [x] **Dashboard jobs** — All `DB::table()` calls in `RefreshDashboardMetrics` and `ComputeFullDashboardMetrics` include `->where('tenant_id', ...)` 
- [x] **Promotion reports** — `getCouponUsage()` uses `->where('orders.tenant_id', ...)` and `getPromotionTypeBreakdown()` uses `->where('promotions.tenant_id', ...)`
- [x] **ActivityLogController** — Both `index()` and `show()` apply tenant filter
- [x] **TelegramRecipientResolver** — Queue context uses `->where('tenant_id', $order->tenant_id)`

### 1.3 Validation Isolation Verification

- [x] **Coupons** — Store and update use `Rule::unique(...)->where('tenant_id', tenant()?->id)`
- [x] **Promotions** — Store and update use scoped unique rule
- [x] **Categories** — Store and update use scoped unique rule
- [x] **Products (SKU)** — Store and update FormRequests use scoped unique rule
- [x] **Cities** — Store and update FormRequests use scoped unique rule

### 1.4 Database Index Verification

- [x] **Payment Methods** — `UNIQUE(tenant_id, name)`
- [x] **Settings** — `UNIQUE(tenant_id, key)`
- [x] **Coupons** — `UNIQUE(tenant_id, code)`
- [x] **Promotions** — `UNIQUE(tenant_id, code)`
- [x] **Products** — `UNIQUE(tenant_id, sku)`

### 1.5 Cache Key Verification

- [x] **Dashboard** — All cache keys use `$tenantSuffix = '_' . (tenant()?->id ?? 'global')`
- [x] **Cities** — Cache key `active_cities_with_townships_{tenant_id}`
- [x] **WebsiteInfo** — Cache key `website_settings_{tenant_id}`
- [x] **Categories** — Cache key `categories_{tenant_id}`
- [x] **Unread notifications** — Cache key `unread_notifications_{user_id}`

### 1.6 Security Verification

- [x] **`/run-migrate` route** — Protected behind `['auth', 'role:superadmin']`
- [x] **`optimize`, `storage:link`** — Removed from routes
- [x] **Mass assignment** — No `tenant_id` in any model's `$fillable` (except Role by design)
- [x] **Exception handler** — ⚠️ EMPTY — Add custom error handling before production
- [x] **SQL injection** — All queries use parameterized bindings

### 1.7 Middleware Verification

- [x] **`tenant.valid`** — Applied to all admin routes
- [x] **`tenant.active`** — Applied to all operations routes (products, orders, categories, etc.)
- [x] **Account routes (dashboard, billing)** — Outside `tenant.active` — accessible when expired/suspended
- [x] **SuperAdmin bypass** — All tenant middleware bypassed for SuperAdmin

### 1.8 Impersonation Verification

- [x] **Dedicated columns** — `impersonator_id` + `impersonated_user_id` in `activity_logs` table
- [x] **LogsActivity trait** — Detects impersonation via `session('impersonator_id')`, sets `causer_id` to SuperAdmin
- [x] **ActivityLogger service** — Same detection as trait
- [x] **ImpersonationController** — Start/stop events set dedicated columns
- [x] **Frontend** — Activity Log shows "Performed By" + "Acting As"
- [ ] **Test**: Start impersonation → edit product → verify activity log shows SuperAdmin as causer

### 1.9 Checkout Subscription Gating

- [x] **OrderController::store()** — Blocks expired subscriptions from placing orders
- [x] **ClientOrderController::uploadPaymentProof()** — Blocks expired
- [x] **ClientOrderController::cancelOrder()** — Blocks expired
- [x] **ClientOrderController::confirmPayment()** — Blocks expired
- [ ] **ClientOrderController::store()** — ⚠️ Missing check (route not publicly wired, but should be added for defense-in-depth)
- [x] **Checkout page** — Shows past_due warning banner
- [x] **Checkout page** — Shows expired warning banner

---

## PHASE 2 — Subscription/Billing Completion (Required Before Paid Launch)

### 2.1 Payment Integration

- [ ] **Select payment provider** (Recommendation: Lemon Squeezy for Laravel SaaS)
- [ ] **Integrate payment SDK** via composer
- [ ] **Create PaymentService** contract/interface
- [ ] **Implement webhook handlers** for subscription events
- [ ] **Add payment method collection** during registration / trial activation

### 2.2 Feature Gate Activation

- [ ] **Set `DEV_MODE = false`** in `FeatureGate.php:41`
- [ ] **Verify all gate checks** work correctly:
  - `canCreateProduct()` — Check `product_limit`
  - `canCreateStaff()` — Check `staff_limit`
  - `canUploadFile()` — Check `storage_limit`
  - `canUseFeature()` — Check feature flags per plan
- [ ] **Add middleware/validation** for feature limits at controller level

### 2.3 Billing Lifecycle

- [ ] **Create Invoice model** — Billing history and audit trail
- [ ] **Implement recurring billing** — Scheduled job or webhook-driven
- [ ] **Implement dunning** — Failed payment retry logic
- [ ] **Build plan upgrade/downgrade UI** — With proration logic
- [ ] **Display usage vs limits** in billing dashboard

### 2.4 Notification Enhancement

- [ ] **Add email channel** — At minimum for subscription events (expiring, expired, suspended, renewed)
- [ ] **Queue notification sends** — Use `->queue()` instead of `->send()` for off-platform notifications
- [ ] **Configure SMTP** — Update `config/mail.php` for production mail driver

---

## PHASE 3 — Production Hardening (Strongly Recommended)

### 3.1 Operational Safeguards

- [ ] **Add exception handler** — Custom error pages (403, 404, 500) in `bootstrap/app.php`
- [ ] **Set up error monitoring** — Sentry, Flare, or similar
- [ ] **Configure queue worker** — Ensure `php artisan queue:work` runs as a service
- [ ] **Add activity log pruning** — Configure `activity_logs` cleanup (e.g., retain 90 days)
- [ ] **Set up database backups** — Automated daily backups
- [ ] **Configure CORS** — `config/cors.php` if API expands beyond same-origin

### 3.2 Performance Optimization

- [ ] **Add index on `activity_logs.created_at`** — For efficient pruning queries
- [ ] **Review product model appended attributes** — Mitigate N+1 on list views
- [ ] **Add `per_page` cap** — Prevent `per_page=all` memory exhaustion (PerPageTrait)
- [ ] **Add SuperAdmin dashboard caching** — Currently re-runs aggregates every load

### 3.3 Cleanup Tasks

- [ ] **Remove dead `HasTenantScope` trait** — Superseded by `TenantAware`
- [ ] **Remove `SubscriptionIsActive` middleware** — Superseded by `EnsureTenantIsActive`
- [ ] **Remove legacy plan columns** — `tenants.subscription_plan_id`, `tenants.expires_at`, `users.plan_*`
- [ ] **Remove duplicate `GRACE_DAYS` constant** — Keep only in `SubscriptionExpiryService`

---

## PHASE 4 — Smoke Test Checklist

### 4.1 Tenant Isolation

| Test | Expected Result |
|------|----------------|
| Tenant A creates "SAVE10" coupon | ✅ Success |
| Tenant B creates "SAVE10" coupon | ✅ Success (no collision) |
| Tenant A's admin views dashboard | ✅ Only Tenant A's data shown |
| Tenant A's admin views reports | ✅ Only Tenant A's data shown |
| Tenant A's admin views orders | ✅ Only Tenant A's orders shown |

### 4.2 Subscription Lifecycle

| Test | Expected Result |
|------|----------------|
| New tenant registers | ✅ Active subscription created |
| Subscription expires (past expires_at) | ✅ Transitions to past_due |
| Past due for 7+ days | ✅ Transitions to expired |
| Expired for 1+ day | ✅ Transitions to suspended |
| Suspended tenant access dashboard | ✅ Dashboard accessible with renewal prompt |
| Suspended tenant access products | ✅ Blocked — suspension page |
| Admin renews subscription | ✅ Restored to active, access resumed |

### 4.3 Impersonation

| Test | Expected Result |
|------|----------------|
| SuperAdmin impersonates merchant | ✅ Impersonation banner appears |
| SuperAdmin edits a product | ✅ Activity log: Performed By = SuperAdmin, Acting As = Merchant |
| SuperAdmin views activity logs | ✅ Shows "(via)" indicator |
| SuperAdmin stops impersonation | ✅ Returned to SuperAdmin dashboard |
| Normal user action logging | ✅ causer = normal user, no impersonation fields |

### 4.4 Checkout Subscription Gating

| Test | Expected Result |
|------|----------------|
| Expired subscription user visits checkout | ✅ Red warning banner shown |
| Expired subscription user tries to order | ✅ Error: "Your subscription has expired" |
| Past due subscription user visits checkout | ✅ Amber warning banner shown |
| Past due subscription user places order | ✅ Success (grace period) |
| Active subscription user places order | ✅ Success |

### 4.5 Cache Isolation

| Test | Expected Result |
|------|----------------|
| Tenant A updates dashboard | ✅ Tenant B's cache not affected |
| Tenant A adds active city | ✅ Tenant B doesn't see it |
| Tenant A changes settings | ✅ Tenant B's settings unchanged |

---

## 5. Go/No-Go Decision Matrix

| Criteria | Status | Required For |
|----------|--------|-------------|
| All tenant isolation fixes verified | ✅ PASS | Production Testing |
| All validation rules tenant-scoped | ✅ PASS | Production Testing |
| All DB indexes tenant-aware | ✅ PASS | Production Testing |
| Subscription gating on checkout | ✅ PASS | Production Testing |
| Impersonation forensic audit | ✅ PASS | Production Testing |
| Custom exception handler | ⚠️ NEEDS FIX | Production Testing |
| `DEV_MODE = false` | ❌ NEEDS FIX | Paid Launch |
| Payment gateway integration | ❌ NEEDS FIX | Paid Launch |
| Email notification channel | ⚠️ RECOMMENDED | Paid Launch |

---

*End of Production Checklist*
*Generated: 2026-05-31*
