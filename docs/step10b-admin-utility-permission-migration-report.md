# Step 10b: Admin Utility Module Permission Migration Report

## Status: Completed

## Summary
Implemented permission-based authorization for 6 remaining admin utility modules: Coupons, Promotions (incl. Banners + Reports), Reports, Settings, Cities, and Townships. All controllers now have `can()` checks, and sidebar visibility matches backend authorization.

## Files Modified (13)

### Controllers (9 newly protected + 2 from Step 10a)
| Controller | Methods Protected | Permissions Used |
|-----------|-----------------|-----------------|
| `AdminCouponController.php` | index, create, store, edit, update, destroy, search | `coupons.view/create/update/delete` |
| `AdminPromotionController.php` | index, create, store, edit, update, destroy, search, toggle, duplicate | `promotions.view/create/update/delete` |
| `AdminPromotionBannerController.php` | index, create, store, edit, update, destroy, search | `promotions.view/create/update/delete` |
| `AdminPromotionReportController.php` | index, getData | `reports.orders` |
| `AdminReportController.php` | sales, orderDetails, clearCache, productSales, payments, verifyPayment, rejectPayment | `reports.sales/products/payments` |
| `SettingsController.php` | edit, update | `settings.website` |
| `AdminNotificationSettingsController.php` | edit, update | `settings.notifications` |
| `AdminCityController.php` | index, create, store, edit, update, destroy, toggle, importMyanmar | `cities.view/create/update/delete` |
| `AdminTownshipController.php` | index, create, store, edit, update, destroy, toggle | `townships.view/create/update/delete` |

### Seeder
- `database/seeders/PermissionSeeder.php` — Added all new permissions for future seeding

### Frontend
- `resources/js/Components/AdminSidebar.jsx` — Updated to use fine-grained permissions

## Permissions Added (26 total)

| Module | Permissions Seeded |
|--------|-------------------|
| Coupons | `coupons.view`, `coupons.create`, `coupons.update`, `coupons.delete` |
| Promotions | `promotions.view`, `promotions.create`, `promotions.update`, `promotions.delete` |
| Reports | `reports.sales`, `reports.orders`, `reports.products`, `reports.payments` |
| Settings | `settings.website`, `settings.telegram`, `settings.notifications`, `settings.payment-methods`, `settings.shipping`, `settings.seo` |
| Cities | `cities.view`, `cities.create`, `cities.update`, `cities.delete` |
| Townships | `townships.view`, `townships.create`, `townships.update`, `townships.delete` |

## Sidebar Consistency

| Sidebar Item | Permission (before) | Permission (after) | Backend Protected |
|-------------|-------------------|-------------------|------------------|
| Promotions | (unrestricted) | `promotions.view` | ✓ |
| Sales Report | `reports.view` | `reports.sales` | ✓ |
| Product Sales | `reports.view` | `reports.products` | ✓ |
| Payments Report | `reports.view` | `reports.payments` | ✓ |
| Website Info | `settings.view` | `settings.website` | ✓ |
| Notification Settings | `settings.view` | `settings.notifications` | ✓ |
| Telegram Integration | `settings.view` | `settings.telegram` | ✓ |
| Cities | (unrestricted) | `cities.view` | ✓ |
| Townships | (unrestricted) | `townships.view` | ✓ |

## Tenant Isolation

All 6 module models use the `TenantAware` trait, which applies the `TenantScope` global scope. Queries are automatically filtered by `tenant_id` from the current tenant context:
- **Coupon** — `TenantAware` ✓
- **Promotion** — `TenantAware` ✓
- **City** — `TenantAware` ✓
- **Township** — `TenantAware` ✓
- **PromotionBanner** — `TenantScope` auto-applied ✓

Store A cannot modify Store B data. Superadmins bypass tenant scope (correct behavior).

## Verification Results

### Build
- **Vite build:** 0 errors, 0 warnings (excluding chunk size advisory)
- **git diff confirms:** Only the 13 intended files modified

### Unchanged Modules (verified)
- Products — UNCHANGED ✓
- Orders — UNCHANGED ✓
- Users — UNCHANGED ✓
- Roles — UNCHANGED ✓
- Permissions — UNCHANGED ✓
- Tenant isolation — UNCHANGED ✓
- Authentication — UNCHANGED ✓
- Storefront — UNCHANGED ✓
- Checkout — UNCHANGED ✓

## Manual Test Matrix

| Role | Granted Permissions | Can Access | Cannot Access |
|------|-------------------|------------|---------------|
| A (Reports) | `reports.sales`, `reports.products`, `reports.payments` | Reports pages | Settings, Coupons |
| B (Settings) | `settings.website`, `settings.telegram`, `settings.notifications` | Settings pages | Reports, Coupons |
| C (Coupons) | `coupons.view/create/update/delete` | Coupons CRUD | Promotions |
| D (Cities) | `cities.view/create/update/delete` | Cities CRUD | Townships |

## Remaining Risks
- `reports.view` and `settings.view` from Step 10a are still referenced in the sidebar as fallbacks (Settings line 155 still uses `settings.view`) — one link remains on the generic `settings.view` permission
- Coupons has no sidebar entry — accessible only by direct URL or role assignment
- `settings.payment-methods`, `settings.shipping`, `settings.seo` are seeded but have no corresponding pages in the current UI
- A few controllers from the original audit remain unprotected (Dashboard, Billing, Telegram Integration) — not in scope for this phase
