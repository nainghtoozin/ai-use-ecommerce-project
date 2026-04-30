<?php

use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\Admin\AdminCityController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminPaymentMethodController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\AdminPromotionController;
use App\Http\Controllers\Admin\AdminTownshipController;
use App\Http\Controllers\Admin\AdminWebsiteInfoController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\Client\ClientController;
use App\Http\Controllers\Client\ClientOrderController;
use App\Http\Controllers\Client\StaticPagesController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// Default route → Client page (no auth required)
Route::get('/', [ClientController::class, 'index'])->name('client.home');
// Dashboard (only for logged in users)
Route::get('/dashboard', [ClientController::class, 'index'])->name('client.home');

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/orders/create', [OrderController::class, 'create'])->name('orders.create');
    Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');

    // Chat Routes
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::post('/chat/send', [ChatController::class, 'sendMessage'])->name('chat.send');
    Route::get('/chat/messages/{userId}', [ChatController::class, 'fetchMessages'])->name('chat.messages');
    Route::get('/chat/messages/{userId}/{beforeId?}', [ChatController::class, 'fetchMessages'])->name('chat.messages');
    Route::post('/chat/read/{userId}', [ChatController::class, 'markAsRead'])->name('chat.read');
    Route::get('/chat/unread-count', [ChatController::class, 'getUnreadCount'])->name('chat.unread-count');
    Route::post('/chat/typing', [ChatController::class, 'typing'])->name('chat.typing');
});

// Admin routes (only accessible by users with role 'admin')
Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/dashboard', [AdminController::class, 'index'])->name('dashboard');

    // Settings
    Route::get('/settings', [\App\Http\Controllers\Admin\SettingsController::class, 'edit'])->name('settings.edit');
    Route::post('/settings', [\App\Http\Controllers\Admin\SettingsController::class, 'update'])->name('settings.update');

    // Chat Routes
    Route::get('/chat/users', [ChatController::class, 'getAdminUsers'])->name('chat.users');
    Route::get('/chat/messages/{userId}', [ChatController::class, 'fetchMessages'])->name('chat.messages');
    Route::get('/chat/messages/{userId}/{beforeId?}', [ChatController::class, 'fetchMessages'])->name('chat.messages');
    Route::post('/chat/send', [ChatController::class, 'sendMessage'])->name('chat.send');
    Route::post('/chat/read/{userId}', [ChatController::class, 'markAsRead'])->name('chat.read');
    Route::post('/chat/typing', [ChatController::class, 'typing'])->name('chat.typing');

    // Search Routes
    Route::get('/products/search', [AdminProductController::class, 'search'])->name('products.search');
    Route::get('/categories/search', [AdminCategoryController::class, 'search'])->name('categories.search');
    Route::get('/orders/search', [AdminOrderController::class, 'search'])->name('orders.search');
    Route::get('/orders/filter', [AdminOrderController::class, 'index'])->name('orders.filter');
    Route::get('/promotions/search', [AdminPromotionController::class, 'search'])->name('promotions.search');

    // Categories CRUD Management
    Route::get('/categories', [AdminCategoryController::class, 'index'])->name('categories.index');
    Route::get('/categories/create', [AdminCategoryController::class, 'create'])->name('categories.create');
    Route::post('/categories', [AdminCategoryController::class, 'store'])->name('categories.store');
    Route::get('/categories/{category}/edit', [AdminCategoryController::class, 'edit'])->name('categories.edit');
    Route::put('/categories/{category}', [AdminCategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy'])->name('categories.destroy');

    // Products CRUD Management
    Route::get('/products', [AdminProductController::class, 'index'])->name('products.index');
    Route::get('/products/create', [AdminProductController::class, 'create'])->name('products.create');
    Route::post('/products', [AdminProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}/edit', [AdminProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [AdminProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}', [AdminProductController::class, 'destroy'])->name('products.destroy');

    // Orders CRUD Management
    Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('orders/search', [AdminOrderController::class, 'search'])->name('orders.search');
    Route::get('orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');

    // Order Status Actions
    Route::post('orders/{order}/confirm', [AdminOrderController::class, 'confirmOrder'])->name('orders.confirm');
    Route::post('orders/{order}/ship', [AdminOrderController::class, 'shipOrder'])->name('orders.ship');
    Route::post('orders/{order}/deliver', [AdminOrderController::class, 'deliverOrder'])->name('orders.deliver');
    Route::post('orders/{order}/cancel', [AdminOrderController::class, 'cancelOrder'])->name('orders.cancel');

    // Payment Verification
    Route::post('orders/{order}/verify-payment', [AdminOrderController::class, 'verifyPayment'])->name('orders.verify-payment');
    Route::post('orders/{order}/approve-payment', [AdminOrderController::class, 'verifyPayment'])->name('orders.approve-payment');
    Route::post('orders/{order}/reject-payment', [AdminOrderController::class, 'rejectPayment'])->name('orders.reject-payment');
    Route::post('orders/{order}/mark-as-paid', [AdminOrderController::class, 'markAsPaid'])->name('orders.mark-as-paid');

    // Legacy routes (kept for backward compatibility)
    Route::get('orders/{order}/edit', [AdminOrderController::class, 'edit'])->name('orders.edit');
    Route::put('orders/{order}', [AdminOrderController::class, 'update'])->name('orders.update');
    Route::delete('orders/{order}', [AdminOrderController::class, 'destroy'])->name('orders.destroy');

    // Promotion CRUD Management
    Route::get('promotions', [AdminPromotionController::class, 'index'])->name('promotions.index');
    Route::get('/promotions/create', [AdminPromotionController::class, 'create_promotion'])->name('promotions.create');
    Route::post('/promotions', [AdminPromotionController::class, 'store'])->name('promotions.store');
    Route::get('promotions/{promotion}', [AdminPromotionController::class, 'view_promotion'])->name('promotions.show');
    Route::get('promotions/{promotion}/edit', [AdminPromotionController::class, 'edit_promotion'])->name('promotions.edit');
    Route::put('promotions/{promotion}', [AdminPromotionController::class, 'update'])->name('promotions.update');
    Route::delete('promotions/{promotion}', [AdminPromotionController::class, 'destroy'])->name('promotions.destroy');

    // Website Information CRUDs
    Route::get('website-info/edit', [AdminWebsiteInfoController::class, 'edit'])->name('website-info.edit');
    Route::post('website-info/update', [AdminWebsiteInfoController::class, 'update'])->name('website-info.update');

    // Payment Methods CRUD Management
    Route::resource('payment-methods', AdminPaymentMethodController::class);
    Route::post('payment-methods/{paymentMethod}/toggle', [AdminPaymentMethodController::class, 'toggle'])->name('payment-methods.toggle');

    // Cities CRUD Management
    Route::resource('cities', AdminCityController::class);
    Route::post('cities/{city}/toggle', [AdminCityController::class, 'toggle'])->name('cities.toggle');

    // Townships CRUD Management
    Route::resource('townships', AdminTownshipController::class);
    Route::post('townships/{township}/toggle', [AdminTownshipController::class, 'toggle'])->name('townships.toggle');

});

// Client routes (private routes / need login)
Route::prefix('client')->name('client.')->middleware(['auth'])->group(function () {
    Route::get('/checkout', [ClientController::class, 'checkout'])->name('checkout');
    Route::get('/orders', [ClientOrderController::class, 'index'])->name('orders.index');
    Route::post('/orders', [ClientOrderController::class, 'store'])->name('orders.store');
    Route::get('/orders/{order}', [ClientOrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/upload-payment', [ClientOrderController::class, 'uploadPaymentProof'])->name('orders.upload-payment');
    Route::post('/orders/{order}/confirm-payment', [ClientOrderController::class, 'confirmPayment'])->name('orders.confirm-payment');
    Route::post('/orders/{order}/cancel', [ClientOrderController::class, 'cancelOrder'])->name('orders.cancel');
});

// Routes that are public (no login required) (accessible by users with role 'client')
Route::prefix('client')->name('client.')->group(function () {
    // Dashboard / Products list
    Route::get('/dashboard', [ClientController::class, 'index'])->name('dashboard');
    // Product show page
    Route::get('/product/{product}', [ClientController::class, 'show_product'])->name('product.show');
    // Cart page
    Route::get('/cart', [ClientController::class, 'cart'])->name('cart');
    Route::get('/search', [ClientController::class, 'search_product'])->name('search');
    Route::get('/products/category/{id}', [ClientController::class, 'getByCategory'])->name('products.byCategory');

    // ===== Static pages =====
    Route::get('/about', [StaticPagesController::class, 'about'])->name('pages.about');
    Route::get('/contact', [StaticPagesController::class, 'contact'])->name('pages.contact');
    Route::get('/faq', [StaticPagesController::class, 'faq'])->name('pages.faq');
    Route::get('/privacy', [StaticPagesController::class, 'privacy'])->name('pages.privacy');
    Route::get('/terms', [StaticPagesController::class, 'terms'])->name('pages.terms');

});

// Laravel auth routes
require __DIR__.'/auth.php';

// Public API routes for location data (no auth required)
Route::get('/api/locations', [App\Http\Controllers\Api\LocationController::class, 'getCities']);
Route::get('/api/townships/{cityId}', [App\Http\Controllers\Api\LocationController::class, 'getTownships']);
