<?php

namespace App\Http\Middleware;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $cart = $this->getCartData($request);

        $user = $request->user();
        $subscriptionExpired = $user && $user->tenant ? $user->tenant->subscriptionExpired() : false;
        $subscription = $user && $user->tenant && $user->tenant->subscription ? $user->tenant->subscription : null;
        $isImpersonating = $user && session()->has('impersonator_id') && !$user->isSuperAdmin();
        $impersonatorName = $isImpersonating ? session('impersonator_name') : null;

        $userData = $user ? [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->getRoleNames()->first(),
            'status' => $user->status,
            'profile_image' => $user->profile_image,
            'email_verified_at' => $user->email_verified_at,
            'is_admin' => $user->isAdmin(),
            'is_superadmin' => $user->isSuperAdmin(),
            'tenant_id' => $user->tenant_id,
            'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
            'subscription_expired' => $subscriptionExpired,
            'subscription_past_due' => $subscription && $subscription->status === 'past_due',
            'subscription' => $subscription ? [
                'status' => $subscription->status,
                'plan_name' => $subscription->plan?->name,
                'expires_at' => $subscription->expires_at?->toDateString(),
            ] : null,
            'is_impersonating' => $isImpersonating,
            'impersonator_name' => $impersonatorName,
        ] : null;

        $settingsModel = \App\Models\WebsiteInfo::first();
        $websiteSettings = $settingsModel ? $settingsModel->toArray() : [];

        $wishlistEnabled = $settingsModel && ($settingsModel->enable_wishlist ?? true);

        $tenant = Tenant::getCurrent();
        // Only share tenant on pages with an explicit store_slug in the URL.
        // This prevents /store/default/... links on the root domain landing page.
        if ($tenant && !$request->route('store_slug')) {
            $tenant = null;
        }

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $userData,
            ],
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'logo' => $tenant->logo,
                'settings' => $tenant->settings,
                'status' => $tenant->status,
                'subscription_expired' => $user && $user->tenant ? $user->tenant->subscriptionExpired() : false,
            ] : null,
            'cart' => $cart,
            'wishlist_count' => $wishlistEnabled && $user ? (int) $user->wishlistItems()->count() : 0,
            'wishlisted_ids' => $wishlistEnabled && $user ? $user->wishlistItems()->pluck('product_id')->toArray() : [],
            'notifications' => [
                'unread_count' => $this->getUnreadCount($request),
            ],
            'flash' => [
                'success' => session('success'),
                'error'   => session('error'),
                'warning' => session('warning'),
            ],
            'app' => [
                'name' => $websiteSettings['site_name'] ?? config('app.name', 'My E-Commerce Store'),
            ],
            'website_info' => $websiteSettings,
            'websiteSettings' => $websiteSettings,
            'categories' => cache()->remember('categories_' . ($tenant?->id ?? 'default'), 3600, function() {
                return Category::orderBy('name')->get(['id', 'name']);
            }),
        ]);
    }

    private function getCartData(Request $request): array
    {
        $sessionCart = $request->session()->get('cart', []);

        if (empty($sessionCart)) {
            return [
                'count' => 0,
                'total' => 0,
                'items' => [],
            ];
        }

        $productIds = array_keys($sessionCart);
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $items = [];
        $total = 0;
        $count = 0;

        foreach ($sessionCart as $productId => $item) {
            $product = $products->get($productId);

            if ($product) {
                $quantity = $item['quantity'];
                $count += $quantity;
                $itemTotal = $product->price * $quantity;
                $total += $itemTotal;

                $items[] = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => (float) $product->price,
                    'photo1' => $product->photo1,
                    'quantity' => $quantity,
                ];
            }
        }

        return [
            'count' => $count,
            'total' => (float) $total,
            'items' => $items,
        ];
    }

    private function getUnreadCount(Request $request): int
    {
        if (!$request->user()) return 0;

        return cache()->remember('unread_notifications_' . $request->user()->id, 30, function() {
            return (int) request()->user()->unreadNotifications()->count();
        });
    }
}