<?php

use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\Admin\AdminCityController;
use App\Http\Controllers\Admin\AdminUnitController;
use App\Http\Controllers\Admin\AdminBrandController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminOrderOverrideController;
use App\Http\Controllers\Admin\AdminPaymentMethodController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\AdminCouponController;
use App\Http\Controllers\Admin\AdminInventoryController;
use App\Http\Controllers\Admin\AdminWarehouseController;
use App\Http\Controllers\Admin\AdminPromotionController;
use App\Http\Controllers\Admin\AdminPromotionBannerController;
use App\Http\Controllers\Admin\AdminPromotionReportController;
use App\Http\Controllers\Admin\AdminTownshipController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminBillingController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\AdminNotificationSettingsController;
use App\Http\Controllers\Admin\AdminReportController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TelegramIntegrationController;
use Illuminate\Support\Facades\Route;

// ============================================================
// STOREFRONT ADMIN ROUTES  (safe migration — runs alongside /admin/*)
// ============================================================
//
// Middleware layering (same semantics as existing /admin/*):
//   storefront     — resolves tenant from URL store_slug parameter
//   auth           — requires authentication
//   role:admin     — requires admin role (superadmin bypasses)
//   tenant.valid    — structural tenant check (user has a valid tenant)
//   tenant.access   — cross-tenant guard (user.tenant_id === current tenant id)
//   tenant.binding  — validates route model binding tenant_id matches current tenant
//
//   tenant.active   — tenant health check (status + subscription expiry)
//                    Applied ONLY to operations routes (inner group).
//
// NOTE: Controllers redirect to route('admin.*') after operations.
//       This means after using the storefront admin, users will be
//       redirected to the legacy /admin/* URLs. This is intentional
//       — existing redirects are preserved during the migration.
//
// ============================================================
Route::prefix('store/{store_slug}/admin')
    ->name('storefront.admin.')
    ->middleware(['storefront', 'auth:web,accounts', 'role:admin', 'tenant.valid', 'tenant.access', 'tenant.binding'])
    ->group(function () {

    // ── Account routes (accessible even when subscription expired/suspended) ──
    Route::get('/expired', fn (\Illuminate\Http\Request $req) => \Inertia\Inertia::render('Standalone/Expired', [
        'store_slug' => $req->route('store_slug'),
    ]))->name('expired');
    Route::get('/suspended', fn (\Illuminate\Http\Request $req) => \Inertia\Inertia::render('Standalone/Suspended', [
        'store_slug' => $req->route('store_slug'),
    ]))->name('suspended');
    Route::get('/billing', [AdminBillingController::class, 'index'])->name('billing');
    Route::get('/billing/subscription', [AdminBillingController::class, 'subscription'])->name('billing.subscription');
    Route::get('/billing/upgrade', [AdminBillingController::class, 'upgrade'])->name('billing.upgrade');
    Route::get('/billing/checkout/{plan}', [AdminBillingController::class, 'checkout'])->name('billing.checkout');
    Route::get('/billing/payment', [AdminBillingController::class, 'payment'])->name('billing.payment');
    Route::post('/billing/payment/submit', [AdminBillingController::class, 'paymentSubmit'])->name('billing.payment.submit');
    Route::get('/billing/payment-history', [AdminBillingController::class, 'paymentHistory'])->name('billing.payment-history');
    Route::get('/billing/settings', [AdminBillingController::class, 'settings'])->name('billing.settings');
    Route::post('/billing/renew', [AdminBillingController::class, 'renew'])->name('billing.renew');
    Route::post('/billing/change-plan/preview', [AdminBillingController::class, 'changePlanPreview'])->name('billing.change-plan.preview');
    Route::post('/billing/change-plan/execute', [AdminBillingController::class, 'changePlanExecute'])->name('billing.change-plan.execute');
    Route::post('/billing/change-plan/cancel', [AdminBillingController::class, 'cancelScheduledChange'])->name('billing.change-plan.cancel');

    // ── Invoice routes ──
    Route::get('/billing/invoices', [InvoiceController::class, 'index'])->name('billing.invoices');
    Route::get('/billing/invoices/{invoice}', [InvoiceController::class, 'show'])->name('billing.invoices.show');
    Route::get('/billing/invoices/{invoice}/download', [InvoiceController::class, 'download'])->name('billing.invoices.download');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ── Operations routes (blocked when expired/suspended/locked) ──
    Route::middleware(['tenant.active', 'tenant.locked'])->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Admin\AdminController::class, 'index'])->name('dashboard');

        // Products
        Route::get('/products', [AdminProductController::class, 'index'])->name('products.index');
        Route::get('/products/type-select', [AdminProductController::class, 'typeSelect'])->name('products.type-select');
        Route::get('/products/create', [AdminProductController::class, 'create'])->name('products.create');
        Route::post('/products', [AdminProductController::class, 'store'])->name('products.store');
        Route::get('/products/search', [AdminProductController::class, 'search'])->name('products.search');
        Route::get('/products/{product}', [AdminProductController::class, 'show'])->name('products.show')->whereNumber('product');
        Route::get('/products/{product}/edit', [AdminProductController::class, 'edit'])->name('products.edit')->whereNumber('product');
        Route::put('/products/{product}', [AdminProductController::class, 'update'])->name('products.update')->whereNumber('product');
        Route::delete('/products/{product}', [AdminProductController::class, 'destroy'])->name('products.destroy')->whereNumber('product');
        Route::post('/products/bulk-delete', [AdminProductController::class, 'bulkDestroy'])->name('products.bulk-delete');
        Route::post('/products/bulk-activate', [AdminProductController::class, 'bulkActivate'])->name('products.bulk-activate');
        Route::post('/products/bulk-deactivate', [AdminProductController::class, 'bulkDeactivate'])->name('products.bulk-deactivate');

        // Orders
        Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/search', [AdminOrderController::class, 'search'])->name('orders.search');
        Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show')->whereNumber('order');
        Route::post('/orders/{order}/confirm', [AdminOrderController::class, 'confirmOrder'])->name('orders.confirm')->whereNumber('order');
        Route::post('/orders/{order}/process', [AdminOrderController::class, 'processOrder'])->name('orders.process')->whereNumber('order');
        Route::post('/orders/{order}/ship', [AdminOrderController::class, 'shipOrder'])->name('orders.ship')->whereNumber('order');
        Route::post('/orders/{order}/deliver', [AdminOrderController::class, 'deliverOrder'])->name('orders.deliver')->whereNumber('order');
        Route::post('/orders/{order}/cancel', [AdminOrderController::class, 'cancelOrder'])->name('orders.cancel')->whereNumber('order');
        Route::post('/orders/{order}/verify-payment', [AdminOrderController::class, 'verifyPayment'])->name('orders.verify-payment')->whereNumber('order');
        Route::post('/orders/{order}/reject-payment', [AdminOrderController::class, 'rejectPayment'])->name('orders.reject-payment')->whereNumber('order');
        Route::post('/orders/{order}/mark-as-paid', [AdminOrderController::class, 'markAsPaid'])->name('orders.mark-as-paid')->whereNumber('order');
        Route::post('/orders/{order}/override-status', [AdminOrderOverrideController::class, 'overrideOrderStatus'])->name('orders.override-status')->whereNumber('order');
        Route::post('/orders/{order}/override-payment', [AdminOrderOverrideController::class, 'overridePaymentStatus'])->name('orders.override-payment')->whereNumber('order');
        Route::delete('/orders/{order}', [AdminOrderController::class, 'destroy'])->name('orders.destroy')->whereNumber('order');

        // Notifications (admin)
        Route::get('/notifications', [NotificationController::class, 'adminPage'])->name('notifications.admin');

        // Admin Chat
        Route::get('/chat/users', [ChatController::class, 'getAdminUsers'])->name('chat.users');
        Route::get('/chat/messages/{userId}/{beforeId?}', [ChatController::class, 'fetchMessages'])->name('chat.messages');
        Route::post('/chat/send', [ChatController::class, 'sendMessage'])->name('chat.send');
        Route::post('/chat/read/{userId}', [ChatController::class, 'markAsRead'])->name('chat.read');
        Route::post('/chat/typing', [ChatController::class, 'typing'])->name('chat.typing');

        // Categories
        Route::get('/categories', [AdminCategoryController::class, 'index'])->name('categories.index');
        Route::get('/categories/create', [AdminCategoryController::class, 'create'])->name('categories.create');
        Route::post('/categories', [AdminCategoryController::class, 'store'])->name('categories.store');
        Route::get('/categories/{category}/edit', [AdminCategoryController::class, 'edit'])->name('categories.edit')->whereNumber('category');
        Route::put('/categories/{category}', [AdminCategoryController::class, 'update'])->name('categories.update')->whereNumber('category');
        Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy'])->name('categories.destroy')->whereNumber('category');
        Route::get('/categories/search', [AdminCategoryController::class, 'search'])->name('categories.search');

        // Inventory
        Route::get('/inventory/dashboard', [AdminInventoryController::class, 'dashboard'])->name('inventory.dashboard');
        Route::get('/inventory', [AdminInventoryController::class, 'index'])->name('inventory.index');
        Route::get('/inventory/movements', [AdminInventoryController::class, 'movements'])->name('inventory.movements');
        Route::get('/inventory/product/{product}', [AdminInventoryController::class, 'show'])->name('inventory.product.show')->whereNumber('product');

        // Warehouses
        Route::get('/warehouses', [AdminWarehouseController::class, 'index'])->name('warehouses.index');
        Route::get('/warehouses/search', [AdminWarehouseController::class, 'search'])->name('warehouses.search');
        Route::get('/warehouses/create', [AdminWarehouseController::class, 'create'])->name('warehouses.create');
        Route::post('/warehouses', [AdminWarehouseController::class, 'store'])->name('warehouses.store');
        Route::get('/warehouses/{warehouse}/edit', [AdminWarehouseController::class, 'edit'])->name('warehouses.edit')->whereNumber('warehouse');
        Route::put('/warehouses/{warehouse}', [AdminWarehouseController::class, 'update'])->name('warehouses.update')->whereNumber('warehouse');
        Route::delete('/warehouses/{warehouse}', [AdminWarehouseController::class, 'destroy'])->name('warehouses.destroy')->whereNumber('warehouse');

        // Units
        Route::get('/units', [AdminUnitController::class, 'index'])->name('units.index');
        Route::get('/units/create', [AdminUnitController::class, 'create'])->name('units.create');
        Route::post('/units', [AdminUnitController::class, 'store'])->name('units.store');
        Route::get('/units/{unit}/edit', [AdminUnitController::class, 'edit'])->name('units.edit')->whereNumber('unit');
        Route::put('/units/{unit}', [AdminUnitController::class, 'update'])->name('units.update')->whereNumber('unit');
        Route::delete('/units/{unit}', [AdminUnitController::class, 'destroy'])->name('units.destroy')->whereNumber('unit');
        Route::get('/units/search', [AdminUnitController::class, 'search'])->name('units.search');

        // Brands
        Route::get('/brands', [AdminBrandController::class, 'index'])->name('brands.index');
        Route::get('/brands/create', [AdminBrandController::class, 'create'])->name('brands.create');
        Route::post('/brands', [AdminBrandController::class, 'store'])->name('brands.store');
        Route::get('/brands/{brand}/edit', [AdminBrandController::class, 'edit'])->name('brands.edit')->whereNumber('brand');
        Route::put('/brands/{brand}', [AdminBrandController::class, 'update'])->name('brands.update')->whereNumber('brand');
        Route::delete('/brands/{brand}', [AdminBrandController::class, 'destroy'])->name('brands.destroy')->whereNumber('brand');
        Route::get('/brands/search', [AdminBrandController::class, 'search'])->name('brands.search');

        // Banners
        Route::get('/banners', [AdminPromotionBannerController::class, 'index'])->name('banners.index');
        Route::get('/banners/create', [AdminPromotionBannerController::class, 'create'])->name('banners.create');
        Route::post('/banners', [AdminPromotionBannerController::class, 'store'])->name('banners.store');
        Route::get('/banners/{promotion}/edit', [AdminPromotionBannerController::class, 'edit'])->name('banners.edit')->whereNumber('promotion');
        Route::put('/banners/{promotion}', [AdminPromotionBannerController::class, 'update'])->name('banners.update')->whereNumber('promotion');
        Route::delete('/banners/{promotion}', [AdminPromotionBannerController::class, 'destroy'])->name('banners.destroy')->whereNumber('promotion');
        Route::get('/banners/search', [AdminPromotionBannerController::class, 'search'])->name('banners.search');

        // Promotions
        Route::get('/promotions', [AdminPromotionController::class, 'index'])->name('promotions.index');
        Route::get('/promotions/create', [AdminPromotionController::class, 'create'])->name('promotions.create');
        Route::post('/promotions', [AdminPromotionController::class, 'store'])->name('promotions.store');
        Route::get('/promotions/{promotion}/edit', [AdminPromotionController::class, 'edit'])->name('promotions.edit')->whereNumber('promotion');
        Route::put('/promotions/{promotion}', [AdminPromotionController::class, 'update'])->name('promotions.update')->whereNumber('promotion');
        Route::delete('/promotions/{promotion}', [AdminPromotionController::class, 'destroy'])->name('promotions.destroy')->whereNumber('promotion');
        Route::post('/promotions/{promotion}/toggle', [AdminPromotionController::class, 'toggle'])->name('promotions.toggle')->whereNumber('promotion');
        Route::post('/promotions/{promotion}/duplicate', [AdminPromotionController::class, 'duplicate'])->name('promotions.duplicate')->whereNumber('promotion');
        Route::get('/promotions/search', [AdminPromotionController::class, 'search'])->name('promotions.search');

        // Promotions Reports
        Route::get('/promotions/reports', [AdminPromotionReportController::class, 'index'])->name('promotions.reports');
        Route::get('/promotions/reports/data', [AdminPromotionReportController::class, 'getData'])->name('promotions.reports.data');

        // Reports
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/sales', [AdminReportController::class, 'sales'])->name('sales');
            Route::post('/sales/clear-cache', [AdminReportController::class, 'clearCache'])->name('sales.clear-cache');
            Route::get('/sales/order/{order}', [AdminReportController::class, 'orderDetails'])->name('sales.order-details')->whereNumber('order');
            Route::get('/product-sales', [AdminReportController::class, 'productSales'])->name('product-sales');
            Route::get('/payments', [AdminReportController::class, 'payments'])->name('payments');
            Route::post('/payments/{order}/verify', [AdminReportController::class, 'verifyPayment'])->name('payments.verify')->whereNumber('order');
            Route::post('/payments/{order}/reject', [AdminReportController::class, 'rejectPayment'])->name('payments.reject')->whereNumber('order');
        });

        // Coupons
        Route::get('/coupons', [AdminCouponController::class, 'index'])->name('coupons.index');
        Route::get('/coupons/create', [AdminCouponController::class, 'create'])->name('coupons.create');
        Route::post('/coupons', [AdminCouponController::class, 'store'])->name('coupons.store');
        Route::get('/coupons/{coupon}/edit', [AdminCouponController::class, 'edit'])->name('coupons.edit')->whereNumber('coupon');
        Route::put('/coupons/{coupon}', [AdminCouponController::class, 'update'])->name('coupons.update')->whereNumber('coupon');
        Route::delete('/coupons/{coupon}', [AdminCouponController::class, 'destroy'])->name('coupons.destroy')->whereNumber('coupon');
        Route::get('/coupons/search', [AdminCouponController::class, 'search'])->name('coupons.search');

        // Payment Methods
        Route::get('/payment-methods', [AdminPaymentMethodController::class, 'index'])->name('payment-methods.index');
        Route::get('/payment-methods/create', [AdminPaymentMethodController::class, 'create'])->name('payment-methods.create');
        Route::post('/payment-methods', [AdminPaymentMethodController::class, 'store'])->name('payment-methods.store');
        Route::get('/payment-methods/{paymentMethod}/edit', [AdminPaymentMethodController::class, 'edit'])->name('payment-methods.edit')->whereNumber('paymentMethod');
        Route::put('/payment-methods/{paymentMethod}', [AdminPaymentMethodController::class, 'update'])->name('payment-methods.update')->whereNumber('paymentMethod');
        Route::delete('/payment-methods/{paymentMethod}', [AdminPaymentMethodController::class, 'destroy'])->name('payment-methods.destroy')->whereNumber('paymentMethod');
        Route::post('/payment-methods/{paymentMethod}/toggle', [AdminPaymentMethodController::class, 'toggle'])->name('payment-methods.toggle')->whereNumber('paymentMethod');

        // Cities
        Route::get('/cities', [AdminCityController::class, 'index'])->name('cities.index');
        Route::get('/cities/create', [AdminCityController::class, 'create'])->name('cities.create');
        Route::post('/cities', [AdminCityController::class, 'store'])->name('cities.store');
        Route::get('/cities/{city}/edit', [AdminCityController::class, 'edit'])->name('cities.edit')->whereNumber('city');
        Route::put('/cities/{city}', [AdminCityController::class, 'update'])->name('cities.update')->whereNumber('city');
        Route::delete('/cities/{city}', [AdminCityController::class, 'destroy'])->name('cities.destroy')->whereNumber('city');
        Route::post('/cities/{city}/toggle', [AdminCityController::class, 'toggle'])->name('cities.toggle')->whereNumber('city');
        Route::post('/locations/import-myanmar', [AdminCityController::class, 'importMyanmar'])->name('locations.import-myanmar');

        // Townships
        Route::get('/townships', [AdminTownshipController::class, 'index'])->name('townships.index');
        Route::get('/townships/create', [AdminTownshipController::class, 'create'])->name('townships.create');
        Route::post('/townships', [AdminTownshipController::class, 'store'])->name('townships.store');
        Route::get('/townships/{township}/edit', [AdminTownshipController::class, 'edit'])->name('townships.edit')->whereNumber('township');
        Route::put('/townships/{township}', [AdminTownshipController::class, 'update'])->name('townships.update')->whereNumber('township');
        Route::delete('/townships/{township}', [AdminTownshipController::class, 'destroy'])->name('townships.destroy')->whereNumber('township');
        Route::post('/townships/{township}/toggle', [AdminTownshipController::class, 'toggle'])->name('townships.toggle')->whereNumber('township');

        // Website Info
        Route::get('website-info/edit', [SettingsController::class, 'edit'])->name('website-info.edit');
        Route::put('website-info/edit', [SettingsController::class, 'update'])->name('website-info.update');

        // Notification Settings
        Route::get('/settings/notifications', [AdminNotificationSettingsController::class, 'edit'])->name('settings.notifications');
        Route::post('/settings/notifications', [AdminNotificationSettingsController::class, 'update'])->name('settings.notifications.update');

        // Telegram Integration Settings
        Route::get('/settings/telegram-integration', [TelegramIntegrationController::class, 'edit'])->name('settings.telegram-integration');

        // Users
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [AdminUserController::class, 'create'])->name('users.create');
        Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show')->whereNumber('user');
        Route::get('/users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit')->whereNumber('user');
        Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update')->whereNumber('user');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy')->whereNumber('user');
        Route::post('/users/{user}/suspend', [AdminUserController::class, 'suspend'])->name('users.suspend')->whereNumber('user');
        Route::post('/users/{user}/ban', [AdminUserController::class, 'ban'])->name('users.ban')->whereNumber('user');
        Route::post('/users/{user}/activate', [AdminUserController::class, 'activate'])->name('users.activate')->whereNumber('user');

        // Activity Logs
        Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
        Route::get('/activity-logs/{activityLog}', [ActivityLogController::class, 'show'])->name('activity-logs.show')->whereNumber('activityLog');

        // Roles
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::get('/roles/{role}', [RoleController::class, 'show'])->name('roles.show')->whereNumber('role');
        Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit')->whereNumber('role');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update')->whereNumber('role');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy')->whereNumber('role');

        // Permissions
        Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');
        Route::get('/permissions/create', [PermissionController::class, 'create'])->name('permissions.create');
        Route::post('/permissions', [PermissionController::class, 'store'])->name('permissions.store');
        Route::get('/permissions/{permission}/edit', [PermissionController::class, 'edit'])->name('permissions.edit');
        Route::put('/permissions/{permission}', [PermissionController::class, 'update'])->name('permissions.update');
        Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy'])->name('permissions.destroy');

        // Team Management
        Route::get('/team', [\App\Http\Controllers\Admin\TeamController::class, 'index'])->name('team.index');
        Route::get('/team/members', [\App\Http\Controllers\Admin\TeamController::class, 'members'])->name('team.members');
        Route::get('/team/invitations', [\App\Http\Controllers\Admin\TeamController::class, 'invitations'])->name('team.invitations');
        Route::get('/team/{member}/json', [\App\Http\Controllers\Admin\TeamController::class, 'showJson'])->name('team.member.json');
        Route::post('/team/invite', [\App\Http\Controllers\Admin\TeamController::class, 'invite'])->name('team.invite');
        Route::delete('/team/invitations/{invitation}', [\App\Http\Controllers\Admin\TeamController::class, 'revokeInvitation'])->name('team.invitations.revoke');
        Route::put('/team/{member}/role', [\App\Http\Controllers\Admin\TeamController::class, 'updateRole'])->name('team.member.role');
        Route::post('/team/{member}/suspend', [\App\Http\Controllers\Admin\TeamController::class, 'suspend'])->name('team.member.suspend');
        Route::post('/team/{member}/restore', [\App\Http\Controllers\Admin\TeamController::class, 'restore'])->name('team.member.restore');
        Route::delete('/team/{member}', [\App\Http\Controllers\Admin\TeamController::class, 'remove'])->name('team.member.remove');

    }); // ← ends tenant.active group
}); // ← ends storefront admin group
