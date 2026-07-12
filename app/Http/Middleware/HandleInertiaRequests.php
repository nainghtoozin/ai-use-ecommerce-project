<?php

namespace App\Http\Middleware;

use App\Auth\IdentityProjection;
use App\Contracts\HasSubscription;
use App\Models\Account;
use App\Models\Category;
use App\Models\PlatformSetting;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\FeatureGate;
use App\Services\SubscriptionLimitService;
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

        $authenticatable = $request->user();
        $isSuperAdmin = $authenticatable && $authenticatable->isSuperAdmin();

        $subscriptionExpired = false;
        $subscription = null;

        if (!$isSuperAdmin) {
            if ($authenticatable instanceof User) {
                $tenant = $authenticatable->tenant;
                $subscriptionExpired = $tenant ? $tenant->subscriptionExpired() : false;
                $subscription = $tenant && $tenant->subscription ? $tenant->subscription : null;
            } elseif ($authenticatable instanceof Account) {
                $tenant = Tenant::getCurrent();
                $subscriptionExpired = $tenant ? $tenant->subscriptionExpired() : false;
                $subscription = $tenant && $tenant->subscription ? $tenant->subscription : null;
            }
        }

        $isImpersonating = $authenticatable && session()->has('impersonator_id') && !$isSuperAdmin;
        $impersonatorName = $isImpersonating ? session('impersonator_name') : null;

        $projection = app(IdentityProjection::class)->forAuthenticatable($authenticatable);
        if ($projection) {
            $projection['subscription_expired'] = $subscriptionExpired;
            $projection['subscription_past_due'] = $subscription && $subscription->status === 'past_due';
            $projection['subscription'] = $subscription ? [
                'status' => $subscription->status,
                'plan_name' => $subscription->plan?->name,
                'expires_at' => $subscription->expires_at?->toDateString(),
            ] : null;
            $projection['is_impersonating'] = $isImpersonating;
            $projection['impersonator_name'] = $impersonatorName;
        }

        $superAdminRoute = $isSuperAdmin && $request->route() && str_starts_with($request->route()->getName(), 'superadmin.');

        if ($isSuperAdmin) {
            $tenant = null;
        } else {
            $tenant = Tenant::getCurrent();
        }

        $settingsModel = !$isSuperAdmin && $tenant ? \App\Models\WebsiteInfo::first() : null;
        $websiteSettings = $settingsModel ? $settingsModel->toArray() : [];

        $wishlistEnabled = !$isSuperAdmin && $settingsModel && ($settingsModel->enable_wishlist ?? true);
        if ($tenant && !$request->route('store_slug')) {
            $tenant = null;
        }

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $projection,
            ],
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'logo' => $tenant->logo,
                'settings' => $tenant->settings,
                'status' => $tenant->status,
                'subscription_expired' => $subscriptionExpired,
            ] : null,
            'cart' => $isSuperAdmin ? ['count' => 0, 'total' => 0, 'items' => []] : $cart,
            'wishlist_count' => !$isSuperAdmin && $wishlistEnabled && $authenticatable ? (int) $this->getWishlistCount($authenticatable) : 0,
            'wishlisted_ids' => !$isSuperAdmin && $wishlistEnabled && $authenticatable ? $this->getWishlistedIds($authenticatable) : [],
            'notifications' => [
                'unread_count' => $this->getUnreadCount($request),
            ],
            'flash' => [
                'success' => session('success'),
                'error'   => session('error'),
                'warning' => session('warning'),
                'feature_locked' => session('feature_locked'),
            ],
            'app' => [
                'name' => $websiteSettings['site_name'] ?? config('app.name', 'My E-Commerce Store'),
            ],
            'platform_setting' => PlatformSetting::current()->toArray(),
            'website_info' => $websiteSettings,
            'websiteSettings' => $websiteSettings,
            'categories' => $isSuperAdmin ? [] : cache()->remember('categories_' . ($tenant?->id ?? 'default'), 3600, function() {
                return Category::orderBy('name')->get(['id', 'name']);
            }),
            'featureStatus' => $isSuperAdmin ? [] : FeatureGate::forUser()->getAllFeaturesStatus(),
            'subscription_limits' => $isSuperAdmin ? [] : ($authenticatable ? SubscriptionLimitService::for()->getAllLimits() : []),
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

    private function getWishlistCount($authenticatable): int
    {
        if ($authenticatable instanceof User && method_exists($authenticatable, 'wishlistItems')) {
            return (int) $authenticatable->wishlistItems()->count();
        }
        return 0;
    }

    private function getWishlistedIds($authenticatable): array
    {
        if ($authenticatable instanceof User && method_exists($authenticatable, 'wishlistItems')) {
            return $authenticatable->wishlistItems()->pluck('product_id')->toArray();
        }
        return [];
    }
}