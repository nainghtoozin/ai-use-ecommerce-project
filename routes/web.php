<?php

use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\Admin\AdminCityController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminPaymentMethodController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\AdminCouponController;
use App\Http\Controllers\Admin\AdminPromotionController;
use App\Http\Controllers\Admin\AdminPromotionBannerController;
use App\Http\Controllers\Admin\AdminTownshipController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\AdminNotificationSettingsController;
use App\Http\Controllers\Admin\AdminReportController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\Client\ClientController;
use App\Http\Controllers\Client\ClientOrderController;
use App\Http\Controllers\Client\StaticPagesController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TelegramIntegrationController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

// ============================================================
// DEFAULT ROUTE
// ============================================================
Route::get('/', [ClientController::class, 'index'])->name('home');
Route::get('/dashboard', [ClientController::class, 'index'])->name('client.dashboard');
Route::get('/products', [ClientController::class, 'products'])->name('products.page');

Route::get('/maintenance', function () {
    $settings = \App\Models\WebsiteInfo::getSettings();
    return \Inertia\Inertia::render('Client/Maintenance', [
        'message' => $settings->maintenance_message ?? 'We are currently performing maintenance. Please try again later.',
        'siteName' => $settings->site_name ?? 'My Store',
        'logoUrl' => $settings->logo_url,
        'contactEmail' => $settings->contact_email ?? $settings->support_email ?? '',
        'phone' => $settings->phone ?? '',
        'socialLinks' => [
            'facebook_url' => $settings->facebook_url ?? '',
            'twitter_url' => $settings->twitter_url ?? '',
            'instagram_url' => $settings->instagram_url ?? '',
            'linkedin_url' => $settings->linkedin_url ?? '',
            'youtube_url' => $settings->youtube_url ?? '',
        ],
    ]);
})->name('maintenance');

// ============================================================
// CART ROUTES (public — session based)
// ============================================================
// CART ROUTES (public — session based)
// ============================================================
Route::prefix('cart')->name('cart.')->group(function () {
    Route::get('/', [CartController::class, 'index'])->name('index');
    Route::post('/add', [CartController::class, 'store'])->name('store');
    Route::delete('/clear', [CartController::class, 'clear'])->name('clear');
    Route::patch('/{id}', [CartController::class, 'update'])->name('update');
    Route::delete('/{id}', [CartController::class, 'destroy'])->name('destroy');
});

// ============================================================
// PUBLIC CLIENT ROUTES
// ============================================================
Route::prefix('client')->name('client.')->group(function () {
    Route::get('/dashboard', [ClientController::class, 'index'])->name('products.index');
    Route::get('/product/{product}', [ClientController::class, 'show_product'])->name('product.show');
    Route::get('/search', [ClientController::class, 'search_product'])->name('search');
    Route::get('/products/category/{id}', [ClientController::class, 'getByCategory'])->name('products.byCategory');

    Route::get('/products', [ClientController::class, 'index'])->name('products.list');
    Route::get('/products/{product}', [ClientController::class, 'show_product'])->name('products.show');

    Route::get('/about', [StaticPagesController::class, 'about'])->name('pages.about');
    Route::get('/contact', [StaticPagesController::class, 'contact'])->name('pages.contact');
    Route::get('/faq', [StaticPagesController::class, 'faq'])->name('pages.faq');
    Route::get('/privacy', [StaticPagesController::class, 'privacy'])->name('pages.privacy');
    Route::get('/terms', [StaticPagesController::class, 'terms'])->name('pages.terms');
});

// ============================================================
// AUTHENTICATED ROUTES
// ============================================================
Route::middleware('auth')->group(function () {

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Chat
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::post('/chat/send', [ChatController::class, 'sendMessage'])->name('chat.send');
    Route::get('/chat/messages/{userId}/{beforeId?}', [ChatController::class, 'fetchMessages'])->name('chat.messages');
    Route::post('/chat/read/{userId}', [ChatController::class, 'markAsRead'])->name('chat.read');
    Route::get('/chat/unread-count', [ChatController::class, 'getUnreadCount'])->name('chat.unread-count');
    Route::post('/chat/typing', [ChatController::class, 'typing'])->name('chat.typing');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'page'])->name('notifications.index');
    Route::get('/notifications/fetch', [NotificationController::class, 'index'])->name('notifications.fetch');
    Route::get('/notifications/counts', [NotificationController::class, 'counts'])->name('notifications.counts');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::get('/notifications/preferences', [NotificationController::class, 'preferences'])->name('notifications.preferences');
    Route::put('/notifications/preferences', [NotificationController::class, 'updatePreferences'])->name('notifications.preferences.update');

    // Checkout
    Route::post('/checkout', [OrderController::class, 'store'])->name('checkout.store');

    // Orders
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');

    // Client order detail page (for React links from /orders listing)
    Route::get('/client/orders', [OrderController::class, 'index'])->name('client.orders.index');
    Route::get('/client/orders/{order}', [OrderController::class, 'show'])->name('client.orders.show');

    // Client order actions
    Route::post('/orders/{order}/upload-payment', [\App\Http\Controllers\Client\ClientOrderController::class, 'uploadPaymentProof'])->name('orders.upload-payment');
    Route::post('/orders/{order}/confirm-payment', [\App\Http\Controllers\Client\ClientOrderController::class, 'confirmPayment'])->name('orders.confirm-payment');
    Route::post('/orders/{order}/cancel', [\App\Http\Controllers\Client\ClientOrderController::class, 'cancelOrder'])->name('orders.cancel');

    // Telegram Integration
    Route::get('/telegram-integration', [TelegramIntegrationController::class, 'show'])->name('telegram-integration.show');
    Route::post('/telegram-integration', [TelegramIntegrationController::class, 'store'])->name('telegram-integration.store');
    Route::post('/telegram-integration/connect', [TelegramIntegrationController::class, 'connect'])->name('telegram-integration.connect');
    Route::get('/telegram-integration/status', [TelegramIntegrationController::class, 'status'])->name('telegram-integration.status');
    Route::patch('/telegram-integration/toggle', [TelegramIntegrationController::class, 'toggle'])->name('telegram-integration.toggle');
    Route::post('/telegram-integration/test', [TelegramIntegrationController::class, 'sendTestMessage'])->name('telegram-integration.test');

// ============================================================
// BROADCAST TEST ROUTE (production-restricted — SuperAdmin only)
// ============================================================
    Route::get('/broadcast/test', function (\Illuminate\Http\Request $request) {
        if ($request->user()->isSuperAdmin()) {
            $userId = $request->user()->id;
            $message = $request->query('message', 'This is a test broadcast from the server.');
            \App\Events\TestBroadcastEvent::dispatch($userId, $message);
            return response()->json([
                'success' => true,
                'message' => "Test broadcast dispatched to user #{$userId}",
            ]);
        }
        abort(403);
    })->name('broadcast.test');
});

// ============================================================
// ADMIN ROUTES
// ============================================================
//
// Middleware layering:
//   tenant.valid    — structural tenant check (tenant_id exists, record found)
//                     Applied to ALL admin routes.
//                     Does NOT check subscription status or tenant suspension.
//
//   tenant.active   — tenant health check (status + subscription expiry)
//                     Applied ONLY to operations routes (inner group).
//                     Suspended → suspension page (all blocked).
//                     Expired  → redirected to dashboard (account routes only).
//                     SuperAdmin → always bypass.
//
//   Account routes (dashboard, billing, suspended) sit in the outer group
//   so they remain accessible when the subscription is expired.
//   Suspended tenants are blocked entirely via CheckUserStatus (global)
//   or the tenant.active middleware (operations routes).
//
// ============================================================
Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin', 'tenant.valid'])->group(function () {
    // ── Account routes (accessible even when subscription expired) ──
    Route::get('/dashboard', [AdminController::class, 'index'])->name('dashboard');
    Route::get('/billing', [\App\Http\Controllers\Admin\AdminBillingController::class, 'index'])->name('billing');
    Route::post('/billing/renew', [\App\Http\Controllers\Admin\AdminBillingController::class, 'renew'])->name('billing.renew');
    Route::get('/suspended', fn () => \Inertia\Inertia::render('Admin/Suspended'))->name('suspended');

    // ── Operations routes (blocked when expired/suspended) ──
    Route::middleware('tenant.active')->group(function () {
    Route::get('/products', [AdminProductController::class, 'index'])->name('products.index');
    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('/categories', [AdminCategoryController::class, 'index'])->name('categories.index');
    Route::get('/payments', [AdminPaymentMethodController::class, 'index'])->name('payments.index');

    // Admin Chat
    Route::get('/chat/users', [ChatController::class, 'getAdminUsers'])->name('chat.users');
    Route::get('/chat/messages/{userId}/{beforeId?}', [ChatController::class, 'fetchMessages'])->name('chat.messages');
    Route::post('/chat/send', [ChatController::class, 'sendMessage'])->name('chat.send');
    Route::post('/chat/read/{userId}', [ChatController::class, 'markAsRead'])->name('chat.read');
    Route::post('/chat/typing', [ChatController::class, 'typing'])->name('chat.typing');

    // Search
    Route::get('/products/search', [AdminProductController::class, 'search'])->name('products.search');
    Route::get('/categories/search', [AdminCategoryController::class, 'search'])->name('categories.search');
    Route::get('/orders/search', [AdminOrderController::class, 'search'])->name('orders.search');
    Route::get('/promotions/search', [AdminPromotionController::class, 'search'])->name('promotions.search');
    Route::get('/banners/search', [AdminPromotionBannerController::class, 'search'])->name('banners.search');

    // Categories
    Route::get('/categories/create', [AdminCategoryController::class, 'create'])->name('categories.create');
    Route::post('/categories', [AdminCategoryController::class, 'store'])->name('categories.store');
    Route::get('/categories/{category}/edit', [AdminCategoryController::class, 'edit'])->name('categories.edit');
    Route::put('/categories/{category}', [AdminCategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy'])->name('categories.destroy');

    // Products
    Route::get('/products/type-select', [AdminProductController::class, 'typeSelect'])->name('products.type-select');
    Route::get('/products/create', [AdminProductController::class, 'create'])->name('products.create');
    Route::post('/products', [AdminProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}', [AdminProductController::class, 'show'])->name('products.show');
    Route::get('/products/{product}/edit', [AdminProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [AdminProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}', [AdminProductController::class, 'destroy'])->name('products.destroy');
    
    // Bulk Actions
    Route::post('/products/bulk-delete', [AdminProductController::class, 'bulkDestroy'])->name('products.bulk-delete');
    Route::post('/products/bulk-activate', [AdminProductController::class, 'bulkActivate'])->name('products.bulk-activate');
    Route::post('/products/bulk-deactivate', [AdminProductController::class, 'bulkDeactivate'])->name('products.bulk-deactivate');

    // Orders
    Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/confirm', [AdminOrderController::class, 'confirmOrder'])->name('orders.confirm');
    Route::post('/orders/{order}/process', [AdminOrderController::class, 'processOrder'])->name('orders.process');
    Route::post('/orders/{order}/ship', [AdminOrderController::class, 'shipOrder'])->name('orders.ship');
    Route::post('/orders/{order}/deliver', [AdminOrderController::class, 'deliverOrder'])->name('orders.deliver');
    Route::post('/orders/{order}/cancel', [AdminOrderController::class, 'cancelOrder'])->name('orders.cancel');
    Route::post('/orders/{order}/verify-payment', [AdminOrderController::class, 'verifyPayment'])->name('orders.verify-payment');
    Route::post('/orders/{order}/reject-payment', [AdminOrderController::class, 'rejectPayment'])->name('orders.reject-payment');
    Route::post('/orders/{order}/mark-as-paid', [AdminOrderController::class, 'markAsPaid'])->name('orders.mark-as-paid');
    Route::delete('/orders/{order}', [AdminOrderController::class, 'destroy'])->name('orders.destroy');
    Route::get('/notifications', [NotificationController::class, 'adminPage'])->name('notifications.admin');

    // Telegram Integration Settings
    Route::get('/settings/telegram-integration', [\App\Http\Controllers\TelegramIntegrationController::class, 'edit'])->name('settings.telegram-integration');

    // Banners (slider)
    Route::get('/banners', [AdminPromotionBannerController::class, 'index'])->name('banners.index');
    Route::get('/banners/create', [AdminPromotionBannerController::class, 'create'])->name('banners.create');
    Route::post('/banners', [AdminPromotionBannerController::class, 'store'])->name('banners.store');
    Route::get('/banners/{promotion}/edit', [AdminPromotionBannerController::class, 'edit'])->name('banners.edit');
    Route::put('/banners/{promotion}', [AdminPromotionBannerController::class, 'update'])->name('banners.update');
    Route::delete('/banners/{promotion}', [AdminPromotionBannerController::class, 'destroy'])->name('banners.destroy');

    // Promotions (discount engine)
    Route::get('/promotions', [AdminPromotionController::class, 'index'])->name('promotions.index');
    Route::get('/promotions/create', [AdminPromotionController::class, 'create'])->name('promotions.create');
    Route::post('/promotions', [AdminPromotionController::class, 'store'])->name('promotions.store');
    Route::get('/promotions/{promotion}/edit', [AdminPromotionController::class, 'edit'])->name('promotions.edit');
    Route::put('/promotions/{promotion}', [AdminPromotionController::class, 'update'])->name('promotions.update');
    Route::delete('/promotions/{promotion}', [AdminPromotionController::class, 'destroy'])->name('promotions.destroy');
    Route::post('/promotions/{promotion}/toggle', [AdminPromotionController::class, 'toggle'])->name('promotions.toggle');
    Route::post('/promotions/{promotion}/duplicate', [AdminPromotionController::class, 'duplicate'])->name('promotions.duplicate');

    // Promotions Reports
    Route::get('/promotions/reports', [\App\Http\Controllers\Admin\AdminPromotionReportController::class, 'index'])->name('promotions.reports');
    Route::get('/promotions/reports/data', [\App\Http\Controllers\Admin\AdminPromotionReportController::class, 'getData'])->name('promotions.reports.data');

    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/sales', [AdminReportController::class, 'sales'])->name('sales');
        Route::post('/sales/clear-cache', [AdminReportController::class, 'clearCache'])->name('sales.clear-cache');
        Route::get('/sales/order/{order}', [AdminReportController::class, 'orderDetails'])->name('sales.order-details');
        Route::get('/product-sales', [AdminReportController::class, 'productSales'])->name('product-sales');
        Route::get('/payments', [AdminReportController::class, 'payments'])->name('payments');
        Route::post('/payments/{order}/verify', [AdminReportController::class, 'verifyPayment'])->name('payments.verify');
        Route::post('/payments/{order}/reject', [AdminReportController::class, 'rejectPayment'])->name('payments.reject');
    });

    // Coupons (discount engine)
    Route::get('/coupons', [\App\Http\Controllers\Admin\AdminCouponController::class, 'index'])->name('coupons.index');
    Route::get('/coupons/create', [\App\Http\Controllers\Admin\AdminCouponController::class, 'create'])->name('coupons.create');
    Route::post('/coupons', [\App\Http\Controllers\Admin\AdminCouponController::class, 'store'])->name('coupons.store');
    Route::get('/coupons/{coupon}/edit', [\App\Http\Controllers\Admin\AdminCouponController::class, 'edit'])->name('coupons.edit');
    Route::put('/coupons/{coupon}', [\App\Http\Controllers\Admin\AdminCouponController::class, 'update'])->name('coupons.update');
    Route::delete('/coupons/{coupon}', [\App\Http\Controllers\Admin\AdminCouponController::class, 'destroy'])->name('coupons.destroy');
    Route::get('/coupons/search', [\App\Http\Controllers\Admin\AdminCouponController::class, 'search'])->name('coupons.search');

    // Website Info (now uses SettingsController)
    Route::get('website-info/edit', [SettingsController::class, 'edit'])->name('website-info.edit');
    Route::put('website-info/edit', [SettingsController::class, 'update'])->name('website-info.update');

    // Notification Settings
    Route::get('/settings/notifications', [AdminNotificationSettingsController::class, 'edit'])->name('settings.notifications');
    Route::post('/settings/notifications', [AdminNotificationSettingsController::class, 'update'])->name('settings.notifications.update');

    // Payment Methods
    Route::resource('payment-methods', AdminPaymentMethodController::class)->except(['show']);
    Route::post('payment-methods/{paymentMethod}/toggle', [AdminPaymentMethodController::class, 'toggle'])->name('payment-methods.toggle');

    // Cities
    Route::resource('cities', AdminCityController::class)->except(['show']);
    Route::post('cities/{city}/toggle', [AdminCityController::class, 'toggle'])->name('cities.toggle');

    // Myanmar locations import
    Route::post('locations/import-myanmar', [AdminCityController::class, 'importMyanmar'])->name('locations.import-myanmar');

    // Townships
    Route::resource('townships', AdminTownshipController::class)->except(['show']);
    Route::post('townships/{township}/toggle', [AdminTownshipController::class, 'toggle'])->name('townships.toggle');

    // ============================================================
    // USER MANAGEMENT ROUTES
    // ============================================================
    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [AdminUserController::class, 'create'])->name('users.create');
    Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
    Route::get('/users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
    Route::post('/users/{user}/suspend', [AdminUserController::class, 'suspend'])->name('users.suspend');
    Route::post('/users/{user}/ban', [AdminUserController::class, 'ban'])->name('users.ban');
    Route::post('/users/{user}/activate', [AdminUserController::class, 'activate'])->name('users.activate');

    // ============================================================
    // ACTIVITY LOG ROUTES
    // ============================================================
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    Route::get('/activity-logs/{activityLog}', [ActivityLogController::class, 'show'])->name('activity-logs.show');

    // ============================================================
    // ROLE MANAGEMENT ROUTES
    // ============================================================
    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create');
    Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
    Route::get('/roles/{role}', [RoleController::class, 'show'])->name('roles.show');
    Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

    // ============================================================
    // PERMISSION ROUTES (read-only)
    // ============================================================
    Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');
    }); // ← ends tenant.active group
}); // ← ends tenant.valid + admin group

// ============================================================
// IMPERSONATION LEAVE (before superadmin group to avoid route collision)
// ============================================================
Route::middleware('auth')->group(function () {
    Route::post('/superadmin/impersonate/leave', [\App\Http\Controllers\SuperAdmin\ImpersonationController::class, 'leave'])->name('superadmin.impersonate.leave');
});

// ============================================================
// SUPERADMIN ROUTES
// ============================================================
Route::prefix('superadmin')->name('superadmin.')->middleware(['auth', 'role:superadmin'])->group(function () {
    Route::get('/', [\App\Http\Controllers\SuperAdmin\DashboardController::class, 'index'])->name('dashboard');

    Route::get('/tenants', [\App\Http\Controllers\SuperAdmin\TenantController::class, 'index'])->name('tenants.index');
    Route::get('/tenants/create', [\App\Http\Controllers\SuperAdmin\TenantController::class, 'create'])->name('tenants.create');
    Route::post('/tenants', [\App\Http\Controllers\SuperAdmin\TenantController::class, 'store'])->name('tenants.store');
    Route::get('/tenants/{tenant}', [\App\Http\Controllers\SuperAdmin\TenantController::class, 'show'])->name('tenants.show');
    Route::get('/tenants/{tenant}/edit', [\App\Http\Controllers\SuperAdmin\TenantController::class, 'edit'])->name('tenants.edit');
    Route::put('/tenants/{tenant}', [\App\Http\Controllers\SuperAdmin\TenantController::class, 'update'])->name('tenants.update');
    Route::post('/tenants/{tenant}/toggle-status', [\App\Http\Controllers\SuperAdmin\TenantController::class, 'toggleStatus'])->name('tenants.toggle-status');
    Route::delete('/tenants/{tenant}', [\App\Http\Controllers\SuperAdmin\TenantController::class, 'destroy'])->name('tenants.destroy');

    Route::get('/plans', [\App\Http\Controllers\SuperAdmin\PlanController::class, 'index'])->name('plans.index');
    Route::get('/plans/create', [\App\Http\Controllers\SuperAdmin\PlanController::class, 'create'])->name('plans.create');
    Route::post('/plans', [\App\Http\Controllers\SuperAdmin\PlanController::class, 'store'])->name('plans.store');
    Route::get('/plans/{plan}/edit', [\App\Http\Controllers\SuperAdmin\PlanController::class, 'edit'])->name('plans.edit');
    Route::put('/plans/{plan}', [\App\Http\Controllers\SuperAdmin\PlanController::class, 'update'])->name('plans.update');
    Route::delete('/plans/{plan}', [\App\Http\Controllers\SuperAdmin\PlanController::class, 'destroy'])->name('plans.destroy');

    Route::get('/subscriptions', [\App\Http\Controllers\SuperAdmin\SubscriptionController::class, 'index'])->name('subscriptions.index');
    Route::post('/subscriptions', [\App\Http\Controllers\SuperAdmin\SubscriptionController::class, 'assign'])->name('subscriptions.assign');
    Route::get('/subscriptions/{subscription}', [\App\Http\Controllers\SuperAdmin\SubscriptionController::class, 'show'])->name('subscriptions.show');
    Route::put('/subscriptions/{subscription}/change-plan', [\App\Http\Controllers\SuperAdmin\SubscriptionController::class, 'changePlan'])->name('subscriptions.change-plan');
    Route::post('/subscriptions/{subscription}/renew', [\App\Http\Controllers\SuperAdmin\SubscriptionController::class, 'renew'])->name('subscriptions.renew');
    Route::post('/subscriptions/{subscription}/renew-from-interval', [\App\Http\Controllers\SuperAdmin\SubscriptionController::class, 'renewFromInterval'])->name('subscriptions.renew-from-interval');
    Route::post('/subscriptions/{subscription}/cancel', [\App\Http\Controllers\SuperAdmin\SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');
    Route::post('/subscriptions/{subscription}/suspend', [\App\Http\Controllers\SuperAdmin\SubscriptionController::class, 'suspend'])->name('subscriptions.suspend');
    Route::post('/subscriptions/{subscription}/activate', [\App\Http\Controllers\SuperAdmin\SubscriptionController::class, 'activate'])->name('subscriptions.activate');

    Route::post('/impersonate/{user}', [\App\Http\Controllers\SuperAdmin\ImpersonationController::class, 'start'])->name('impersonate.start');

    // ── Utility routes (SuperAdmin only) ──
    Route::post('/run-migrate', function () {
        Artisan::call('migrate', ['--force' => true]);
        return response()->json(['message' => 'Migration completed.']);
    })->name('run-migrate');
});

// Auth
require __DIR__ . '/auth.php';

// Coupon/Promotion apply/remove API (authenticated)
Route::middleware('auth')->group(function () {
    Route::post('/cart/apply-coupon', [CartController::class, 'applyCoupon'])->name('cart.apply-coupon');
    Route::post('/cart/remove-coupon', [CartController::class, 'removeCoupon'])->name('cart.remove-coupon');
    Route::post('/cart/apply-promotion', [CartController::class, 'applyPromotion'])->name('cart.apply-promotion');
    Route::post('/cart/remove-promotion', [CartController::class, 'removePromotion'])->name('cart.remove-promotion');
});

// Wishlist (literal routes before parameterized routes)
Route::prefix('wishlist')->name('wishlist.')->middleware('auth')->group(function () {
    Route::get('/', [WishlistController::class, 'index'])->name('index');
    Route::post('/move-all-to-cart', [WishlistController::class, 'moveAllToCart'])->name('move-all-to-cart');
    Route::post('/move-to-cart/{product}', [WishlistController::class, 'moveToCart'])->name('move-to-cart');
    Route::post('/{product}', [WishlistController::class, 'store'])->name('store');
    Route::delete('/clear', [WishlistController::class, 'clear'])->name('clear');
    Route::delete('/{product}', [WishlistController::class, 'destroy'])->name('destroy');
});

// API
// Checkout (public — guest checkout gate handled in controller)
Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');

Route::get('/api/locations', [App\Http\Controllers\Api\LocationController::class, 'getCities']);
Route::get('/api/townships/{cityId}', [App\Http\Controllers\Api\LocationController::class, 'getTownships']);


