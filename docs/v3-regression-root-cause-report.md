# V3 Regression Root Cause Analysis Report

## 1. Executive Summary

A single line removal in `TenantBootstrapService::bootstrap()` during commit `ce90332` ("version 3 QA test") caused a cascading permission failure for newly created merchants. The removed line (`$this->assignOwnerPermissions($owner)`) was the only mechanism giving merchant owners direct access to all permissions. Without it, merchants inherit only a subset of permissions through their tenant-scoped admin role, which lacks critical permissions for Billing, Settings, and other features.

## 2. Observed Symptoms

| Symptom | Status | Root Cause |
|---|---|---|
| Billing menu missing | ✗ | `billing.view` not in admin role template |
| Settings sub-menus missing (Website Info, Telegram, Notification Settings) | ✗ | `settings.website`, `settings.telegram`, `settings.notifications` not in admin role template |
| Roles page 404 | ✗ | Route exists; possible cause: `adminUrl` redirects to storefront-admin route but controller queries in `RoleController::index()` filter by `Tenant::getCurrent()` which may be null in some navigation paths |
| Merchant permissions incomplete | ✗ | Owner only gets permissions from admin role template, not ALL permissions |

## 3. Merchant Journey Trace

**Working flow (before V3):**

```
Landing → CreateStoreController::store()
  → Tenant::create() [status: 'pending']
  → TenantBootstrapService::bootstrap()
    → createRoles(tenant)         # admin, customer roles created with permissions synced from global templates
    → createSubscription(tenant)   # Subscription created
    → createOwner(tenant, opts)    # User created with is_owner=true
    → assignOwnerRole(owner)      # Admin role assigned to owner
    → assignOwnerPermissions(owner) ← REMOVED in ce90332
    → createDefaultUnits/Categories/Brands/PaymentMethods
    → TenantCreated::dispatch()
  → event(new Registered($admin))
  → Login → Dashboard → All menus visible
```

**Current broken flow:** Step `assignOwnerPermissions` is skipped, so owner has only role-based permissions.

## 4. Merchant Identity Audit

During `TenantBootstrapService::createOwner()`:
- User created with `is_owner = true`
- `tenant_id` set to the new tenant ID
- `status` set to `active`

During `assignOwnerRole()`:
- Owner is assigned the tenant-scoped **admin** role (`name = 'admin', tenant_id = {tenant_id}`)
- This role synced permissions from **global** admin role template during `createRole()`

Identity: **Owner**, role: **admin**, guard: **web**

## 5. Database Comparison

**SuperAdmin:**
- Role: `superadmin` (global, `tenant_id = null`)
- Permissions: ALL (via `Permission::all()` in RoleAndPermissionSeeder)

**Merchant Owner (after V3):**
- Role: `admin` (tenant-scoped, `tenant_id = {id}`)
- Permissions: Only what's in `RoleAndPermissionSeeder::$adminPermissions`

**Manual Admin User (created via Admin panel):**
- Same as Merchant Owner

## 6. Bootstrap Audit

**`TenantBootstrapService` audit:**

| Aspect | Status |
|---|---|
| Is it executed? | Yes |
| Called from expected location? | Yes (`CreateStoreController::store()`) |
| Does execution complete? | Yes |
| Does it stop early? | No |
| Does an event fail? | No |
| Swallowed exception? | No |
| Listener removed? | N/A (EventServiceProvider has empty $listen) |

**Critical finding:** `assignOwnerPermissions($owner)` on line 65 was the ONLY place where `$owner->syncPermissions(Permission::all())` was called. This method still exists (line 190-193) but is **never invoked**.

## 7. Permission Audit

**Permissions NOT in admin role template** (`RoleAndPermissionSeeder::$adminPermissions`):

```
billing.view                                   → Billing menu hidden
billing.renew                                  → Billing renew 403
settings.website                               → Website Info menu hidden
settings.telegram                              → Telegram menu hidden
settings.notifications                         → Notification Settings hidden
settings.payment-methods                       → Payment settings hidden
settings.shipping                              → Shipping settings hidden
settings.seo                                   → SEO settings hidden
reports.sales                                  → Sales report hidden
reports.products                               → Product sales hidden
reports.payments                               → Payments report hidden
coupons.*                                      → Coupons menu hidden (also needs feature gate)
promotions.*                                   → Promotions hidden (also needs feature gate)
cities.*                                       → Cities menu hidden
townships.*                                    → Townships menu hidden
payments.create, payments.update, payments.delete → Payment method CRUD 403
permissions.create, permissions.edit, permissions.delete → Permission mgmt 403
```

**These permissions exist in `PermissionSeeder`** (they are in the `permissions` table) but are **NOT assigned** to the global admin role template, so they never reach tenant-scoped admin roles.

## 8. Navigation Audit

See `resources/js/Components/AdminSidebar.jsx` lines 104-169.

| Menu Item | Gate | In Admin Role? |
|---|---|---|
| Dashboard | `dashboard.view` | ✅ |
| Billing | `billing.view` | ❌ |
| Products | `products.view` | ✅ |
| Categories | `categories.view` | ✅ |
| Brands | `brands.view` | ✅ |
| Units | `units.view` | ✅ |
| Orders | `orders.view` | ✅ |
| Payment Methods | `payments.view` | ✅ |
| Coupons | `coupons.view` + `hasFeature('coupons')` | ❌ |
| Promotions | `promotions.view` + `hasFeature('promotions')` | ❌ |
| Sales Report | `reports.sales` + `hasFeature('reports')` | ❌ |
| Product Sales | `reports.products` + `hasFeature('reports')` | ❌ |
| Payments Report | `reports.payments` + `hasFeature('reports')` | ❌ |
| Cities | `cities.view` | ❌ |
| Townships | `townships.view` | ❌ |
| Users | `users.view` | ✅ |
| Roles & Permissions | `roles.view` | ✅ |
| Activity Logs | `activity-logs.view` | ✅ |
| Website Info | `settings.website` | ❌ |
| Notification Settings | `settings.notifications` | ❌ |
| Telegram Integration | `settings.telegram` | ❌ |
| Settings | `settings.view` | ✅ |

## 9. Route Audit

Routes exist for all needed pages in both `web.php` and `storefront-admin.php`:

- `/admin/roles` and `/store/{slug}/admin/roles` → `RoleController@index` ✅
- `/admin/billing` and `/store/{slug}/admin/billing` → `AdminBillingController@index` ✅
- `/admin/website-info/edit` and `/store/{slug}/admin/website-info/edit` → `SettingsController@edit` ✅
- `/admin/settings/notifications` → `AdminNotificationSettingsController@edit` ✅
- `/admin/settings/telegram-integration` → `TelegramIntegrationController@edit` ✅

The 404 on Roles page is not from missing routes. The route exists. The 404 may be from:
1. `ValidateTenantBinding` middleware when model binding fails
2. Inertia page component path mismatch
3. Navigation from a context where the store slug detection fails (`adminUrl` helper)

However, the **controller action** in `RoleController@index` checks `auth()->user()->can('roles.view')`. Since `roles.view` IS in the admin role template, this permission check **passes**. The 404 is likely a routing/navigation issue rather than a permission issue.

## 10. Seeder Audit

| Seeder | Executes in `migrate:fresh --seed` | Executes during merchant creation |
|---|---|---|
| `PermissionSeeder` | ✅ Yes | ❌ No (run once) |
| `RoleAndPermissionSeeder` | ✅ Yes | ❌ No (run once) |
| `PlanSeeder` | ✅ Yes | ❌ No (run once) |
| `PlatformSettingSeeder` | ✅ Yes | ❌ No (run once) |
| `LocationSeeder` | ✅ Yes | ❌ No (run once) |
| `WebsiteSettingsSeeder` | ✅ Yes | ❌ No (run once) |
| `PaymentMethodSeeder` | ✅ Yes | ❌ Run in TenantBootstrapService |
| `CategorySeeder` | ✅ Yes | ❌ Run in TenantBootstrapService |
| `UnitSeeder` | ✅ Yes | ❌ Run in TenantBootstrapService |
| `BrandSeeder` | ✅ Yes | ❌ Run in TenantBootstrapService |
| `TenantSeeder` | ✅ Yes | ❌ No (run once) |

**Key insight:** During `migrate:fresh --seed`, `RoleAndPermissionSeeder` creates global roles (superadmin, admin, customer) with their permission sets. During merchant creation, `TenantBootstrapService::createRole()` copies permissions from the global role templates to tenant-scoped roles. The global admin template is the SOURCE OF TRUTH for merchant permissions, and it's missing critical permissions.

## 11. Middleware Audit

Middleware execution order for admin routes:

**`web.php` (legacy admin):**
```
auth → role:admin → tenant.valid → tenant.binding
  → [tenant.active → tenant.locked] (operations only)
```

**`storefront-admin.php` (storefront admin):**
```
storefront → auth → role:admin → tenant.valid → tenant.access → tenant.binding
  → [tenant.active → tenant.locked] (operations only)
```

The middleware order has NOT changed. The `role:admin` middleware allows access if:
1. User has `superadmin` role (bypass)
2. User has `admin` role (match)
3. User has any permission (fallback)

All three conditions should pass for the merchant owner (they have the admin role and permissions).

## 12. Regression Timeline

| Commit | Date | Change | Effect |
|---|---|---|---|
| `92488a5` | - | Initial `TenantBootstrapService` created with `assignOwnerPermissions` | Working |
| `9d28d53` | - | Added default data methods, preserved `assignOwnerPermissions` | Working |
| `82dc85f` | - | Added trial/subscription lifecycle, preserved `assignOwnerPermissions` | Working |
| **`ce90332`** | **2026-06-30** | **REMOVED `assignOwnerPermissions`** | **BROKEN** |

**Last known good state:** Commit `82dc85f` ("feat: complete trial lifecycle and subscription lifecycle hardening")

**First broken state:** Commit `ce90332` ("version 3 QA test")

## 13. Root Cause

**Primary Root Cause (100% certainty):**

In commit `ce90332` ("version 3 QA test"), the line `$this->assignOwnerPermissions($owner);` was removed from `TenantBootstrapService::bootstrap()` at `app/Services/TenantBootstrapService.php:65`.

The removed method was:
```php
protected function assignOwnerPermissions(User $owner): void
{
    $owner->syncPermissions(Permission::all());
}
```

This method gave the merchant owner ALL existing permissions directly (stored in `model_has_permissions` table). Without it, the owner only receives permissions through the tenant-scoped admin role, which is synced from the global admin role template (`RoleAndPermissionSeeder::$adminPermissions`).

**Secondary Root Cause (contributing factor):**

The global admin role template (`RoleAndPermissionSeeder::$adminPermissions`) has never included many permissions that the sidebar and controllers check:
- `billing.view`, `billing.renew`
- `settings.website`, `settings.telegram`, `settings.notifications`
- `reports.sales`, `reports.products`, `reports.payments`
- `coupons.*`, `promotions.*`
- `cities.*`, `townships.*`
- `payments.create`, `payments.update`, `payments.delete`
- `permissions.create`, `permissions.edit`, `permissions.delete`

These missing permissions were previously masked by `assignOwnerPermissions` which bypassed role-based permission assignment entirely.

## 14. Evidence

### Evidence 1: Git Diff (Commit ce90332)

```
diff --git a/app/Services/TenantBootstrapService.php b/app/Services/TenantBootstrapService.php
index 005cd1c..857bf7a 100644
--- a/app/Services/TenantBootstrapService.php
+++ b/app/Services/TenantBootstrapService.php
@@ -62,7 +62,6 @@ public function bootstrap(Tenant $tenant, array $options = []): ?User
                 $owner = $this->createOwner($tenant, $options);
 
                 $this->assignOwnerRole($owner, $tenant);
-                $this->assignOwnerPermissions($owner);
 
                 $this->createDefaultUnits($tenant);
                 $this->createDefaultCategories($tenant);
```

### Evidence 2: Current state of TenantBootstrapService

The `assignOwnerPermissions` method still exists at line 190-193 but is **not called** anywhere:
```php
protected function assignOwnerPermissions(User $owner): void
{
    $owner->syncPermissions(Permission::all());
}
```

### Evidence 3: Admin role template permissions

`database/seeders/RoleAndPermissionSeeder.php` `$adminPermissions` array does NOT include:
- `billing.view`, `billing.renew`
- `settings.website`, `settings.telegram`, `settings.notifications`
- (and all other missing permissions listed in section 13)

### Evidence 4: Sidebar permission gates

`resources/js/Components/AdminSidebar.jsx` lines 104-169 show menu items gated by:
- `can('billing.view')` → line 109
- `can('settings.website')` → line 163
- `can('settings.telegram')` → line 165
- `can('settings.notifications')` → line 164
- `can('roles.view')` → line 155 (this one works, thus Roles menu IS visible)
- `can('reports.sales')` → line 139
- etc.

## 15. Minimal Safe Fix Recommendation

### Option A: Restore assignOwnerPermissions (Recommended)

Add back the removed line in `TenantBootstrapService::bootstrap()`:

```php
$this->assignOwnerRole($owner, $tenant);
$this->assignOwnerPermissions($owner);  // ← RESTORE THIS LINE
```

**Risk:** Very low. This restores previous behavior exactly.

**Impact:** New merchant owners will again receive ALL permissions directly.

### Option B: Expand Admin Role Template

Add missing permissions to `RoleAndPermissionSeeder::$adminPermissions`:

```php
'billing.view',
'billing.renew',
'settings.website',
'settings.telegram',
'settings.notifications',
// ... all other missing permissions
```

**Risk:** Low-to-medium. Changes permission model.

**Impact:** Admin role (and thus all admin users, not just owners) gets these permissions.

### Option C: Both (Preferred for production)

Restore `assignOwnerPermissions` for immediate fix AND plan to update the admin role template properly later.

## 16. Files That Must NOT Be Modified

- `app/Services/FeatureGate.php`
- `app/Services/SubscriptionLimitService.php`
- `app/Models/Permission.php` (Spatie)
- `config/permission.php`
- `database/migrations/` (any)
- `app/Models/Scopes/TenantScope.php`
- `app/Models/Traits/TenantAware.php`
- `app/Http/Middleware/` (any)

## 17. Risk Assessment

| Factor | Rating | Notes |
|---|---|---|
| Severity | **Critical** | Merchant cannot access billing, settings, reports; 404 on roles navigation path |
| Scope | **All new merchants** | Every merchant created after commit `ce90332` |
| Detectability | **High** | Immediately visible on first merchant login |
| Fix Complexity | **Minimal** | Single line restoration |
| Regression Risk | **Very Low** | Restores previously working behavior |
| Data Loss Risk | **None** | No data is modified |

**Overall Risk: CRITICAL** — but fix is trivial and low-risk.
