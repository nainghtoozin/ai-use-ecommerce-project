# Billing Payment Method Separation Audit

## Executive Summary

The `payment_methods` table previously served two distinct domains: **Store Payment Methods** (customer checkout) and **Platform Billing Payment Methods** (merchant subscription billing). This violated SaaS domain boundaries. The architecture has been corrected by creating a dedicated `billing_payment_methods` table and domain, completely isolating billing payment methods from store payment methods.

---

## Architecture Before

```
payment_methods (single table)
    ├── Store Payment Methods (tenant-scoped)
    │   ├── Customer Checkout displays these
    │   ├── Admin CRUD manages these
    │   └── Order.payment_method_id FK references this
    │
    └── Billing Payment Methods (mixed)
        ├── AdminBillingController@payment reads these
        └── SuperAdmin manages these as "global" Payment Methods
```

## Architecture After

```
payment_methods (store only)
    ├── Tenant-scoped (via TenantAware trait)
    ├── Customer Checkout
    ├── Admin CRUD (AdminPaymentMethodController)
    └── Order.payment_method_id FK

billing_payment_methods (platform billing only)
    ├── Platform-scoped (no tenant)
    ├── Merchant Upgrade (AdminBillingController reads these)
    ├── SuperAdmin CRUD (BillingPaymentMethodController)
    ├── Manual Payment flow
    └── Future gateway integrations
```

---

## Files Modified

### New Files Created

| File | Purpose |
|------|---------|
| `database/migrations/2026_07_04_000001_create_billing_payment_methods_table.php` | New table migration |
| `app/Models/BillingPaymentMethod.php` | Eloquent model (no TenantAware, uses SoftDeletes) |
| `app/Services/BillingPaymentMethodService.php` | Service layer with image handling |
| `app/Policies/BillingPaymentMethodPolicy.php` | Authorization policy |
| `app/Http/Controllers/SuperAdmin/BillingPaymentMethodController.php` | CRUD controller |
| `resources/js/Pages/SuperAdmin/BillingPaymentMethods/Index.jsx` | List page |
| `resources/js/Pages/SuperAdmin/BillingPaymentMethods/Create.jsx` | Create form |
| `resources/js/Pages/SuperAdmin/BillingPaymentMethods/Edit.jsx` | Edit form |
| `database/seeders/BillingPaymentMethodSeeder.php` | Default KBZ Bank seed |

### Existing Files Modified

| File | Change |
|------|--------|
| `routes/web.php` | Replaced `SuperAdminPaymentMethodController` routes with `BillingPaymentMethodController` under `billing-payment-methods` prefix |
| `app/Http/Controllers/Admin/AdminBillingController.php` | Changed import from `PaymentMethod` to `BillingPaymentMethod`; query now uses `supports_manual_payment` flag; validation uses `billing_payment_methods` table |
| `database/seeders/PermissionSeeder.php` | Added `billing-payment-method.*` permissions (view, create, update, delete) |
| `database/seeders/DatabaseSeeder.php` | Added `BillingPaymentMethodSeeder::class` to call list |
| `resources/js/Components/AdminSidebar.jsx` | Updated SuperAdmin sidebar link from `/superadmin/payment-methods` to `/superadmin/billing-payment-methods` |

---

## Database Changes

### New Table: `billing_payment_methods`

| Column | Type | Notes |
|--------|------|-------|
| id | bigint unsigned | PK |
| display_name | varchar(255) | Human-readable name |
| type | varchar(255) | bank_transfer, cod, or gateway |
| account_name | varchar(255) | Nullable |
| account_number | varchar(255) | Nullable |
| bank_name | varchar(255) | Nullable |
| branch | varchar(255) | Nullable |
| qr_image | varchar(255) | Nullable |
| instructions | text | Nullable |
| currency | varchar(3) | 3-letter ISO code |
| sort_order | integer | Default 0 |
| is_default | boolean | Default false |
| is_active | boolean | Default true |
| supports_manual_payment | boolean | Default true |
| supports_gateway | boolean | Default false |
| gateway_code | varchar(255) | Nullable, for future gateway integration |
| metadata | json | Nullable |
| created_by | bigint unsigned | FK -> users, nullable |
| updated_by | bigint unsigned | FK -> users, nullable |
| deleted_at | timestamp | Soft deletes |
| created_at | timestamp | |
| updated_at | timestamp | |

**Data Migration:** None. Store Payment Methods remain untouched in the `payment_methods` table.

---

## Services Updated

### AdminBillingController (merchant billing)

- `payment()` method: Now queries `BillingPaymentMethod::active()->where('supports_manual_payment', true)` instead of `PaymentMethod::active()`
- `paymentSubmit()` method: Validation now checks `exists:billing_payment_methods,id` instead of `exists:payment_methods,id`
- Model import changed from `PaymentMethod` to `BillingPaymentMethod`

### No other services were modified.

---

## Regression Results

### Verified: Store Payment Methods (Zero Regression)

| Component | Status | Notes |
|-----------|--------|-------|
| `payment_methods` table | ✅ Unchanged | All data intact, no migration |
| `PaymentMethod` model | ✅ Unchanged | Still uses TenantAware |
| `AdminPaymentMethodController` | ✅ Unchanged | Routes still active |
| `CheckoutController` | ✅ Unchanged | Uses `PaymentMethod` |
| `StorefrontCheckoutController` | ✅ Unchanged | Uses `PaymentMethod` |
| `ClientController` | ✅ Unchanged | Uses `PaymentMethod` |
| `AdminReportController` | ✅ Unchanged | Uses `PaymentMethod` |
| `OrderService` | ✅ Unchanged | Uses `PaymentMethod` |
| `PaymentMethodService` | ✅ Unchanged | Uses `PaymentMethod` |
| Store routes (admin prefix) | ✅ Unchanged | 7 routes intact |
| Store routes (storefront prefix) | ✅ Unchanged | 7 routes intact |
| Admin sidebar (store) | ✅ Unchanged | Links to `admin.payment-methods` |

### Verified: Billing Payment Methods (Isolated)

| Component | Status | Notes |
|-----------|--------|-------|
| `billing_payment_methods` table | ✅ Created | Schema matches requirements |
| `BillingPaymentMethod` model | ✅ Created | No TenantAware |
| `BillingPaymentMethodController` | ✅ Created | SuperAdmin CRUD |
| React pages (Index/Create/Edit) | ✅ Created | Full CRUD UI |
| AdminBillingController | ✅ Updated | Reads from new table |
| SuperAdmin routes | ✅ Migrated | Under `billing-payment-methods` |
| Sidebar navigation | ✅ Updated | Points to new URL |
| Permissions | ✅ Added | `billing-payment-method.*` |
| Default seed data | ✅ Seeded | KBZ Bank for Demo Company |
| Frontend build | ✅ Passed | No compilation errors |

---

## Manual QA Checklist

- [ ] **SuperAdmin CRUD**: Navigate to SuperAdmin → Billing & Finance → Billing Payment Methods. Create, edit, toggle, and archive billing payment methods. Verify that NO store payment methods appear.
- [ ] **Merchant Upgrade**: Log in as a merchant, go to Billing → Upgrade Plan → checkout → Payment page. Verify that ONLY billing payment methods (from `billing_payment_methods`) are displayed. Previously this showed store payment methods mixed in.
- [ ] **Store Checkout**: As a customer, proceed through checkout. Verify that store payment methods (from `payment_methods`) still display correctly.
- [ ] **SuperAdmin Old URL**: Verify `/superadmin/payment-methods` returns 404 (routes removed).
- [ ] **Manual Payment Submit**: Merchant submits payment evidence. Verify it correctly references `billing_payment_methods` ID.

---

## Future Gateway Readiness

The `billing_payment_methods` table includes gateway-ready fields:
- `supports_gateway` (boolean) - Flag for gateway-based methods
- `gateway_code` (string) - Identifier for gateway integration
- `supports_manual_payment` (boolean) - Flag for manual/bank transfer methods
- `metadata` (json) - Extensible configuration

This enables future automated gateway integrations (Stripe, PayPal, etc.) without modifying the schema.

---

## Remaining Recommendations

1. **Run seeders** after deployment: `php artisan db:seed --class=BillingPaymentMethodSeeder` and `php artisan db:seed --class=PermissionSeeder`
2. **Clear permissions cache**: `php artisan permission:cache-reset`
3. **Clean up**: The old `SuperAdminPaymentMethodController` and its views can be removed after verifying no regressions in staging
4. **Update any monitoring/logging** that references the old `payment-methods` routes
5. **Consider adding a database constraint** to prevent accidental cross-domain FK references
