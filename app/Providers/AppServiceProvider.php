<?php

namespace App\Providers;

use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\User;
use App\Policies\CustomerAddressPolicy;
use App\Policies\CustomerOrderPolicy;
use App\Policies\UserPolicy;
use App\Services\NotificationPreferenceService;
use App\Services\OrderService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OrderService::class, function ($app) {
            return new OrderService(
                $app->make(NotificationPreferenceService::class),
                $app->make(\App\Services\CouponService::class),
                $app->make(\App\Services\PromotionService::class)
            );
        });

        $this->app->singleton(\App\Services\OrderNotificationService::class, function ($app) {
            return new \App\Services\OrderNotificationService(
                $app->make(NotificationPreferenceService::class)
            );
        });
    }

    public function boot(): void
    {
        if (env('APP_ENV') !== 'local') {
            URL::forceScheme('https');
        }

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Order::class, CustomerOrderPolicy::class);
        Gate::policy(CustomerAddress::class, CustomerAddressPolicy::class);
    }
}
