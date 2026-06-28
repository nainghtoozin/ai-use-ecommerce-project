<?php

namespace App\Providers;

use App\Contracts\PaymentProvider;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\User;
use App\Policies\CustomerAddressPolicy;
use App\Policies\CustomerOrderPolicy;
use App\Policies\UserPolicy;
use App\Services\NotificationPreferenceService;
use App\Services\OrderService;
use App\Services\Payment\PaymentGatewayResolver;
use App\Services\Payment\PaymentService;
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

        $this->app->singleton(PaymentGatewayResolver::class, function ($app) {
            $resolver = new PaymentGatewayResolver();

            foreach (config('payments.gateways', []) as $config) {
                $class = $config['provider'] ?? null;
                if ($class && class_exists($class)) {
                    $resolver->register($app->make($class));
                }
            }

            return $resolver;
        });

        $this->app->singleton(PaymentService::class, function ($app) {
            return new PaymentService(
                $app->make(PaymentGatewayResolver::class)
            );
        });

        $this->app->bind(PaymentProvider::class . '$stripe', \App\Services\Payment\Providers\StripeProvider::class);
        $this->app->bind(PaymentProvider::class . '$paypal', \App\Services\Payment\Providers\PayPalProvider::class);
        $this->app->bind(PaymentProvider::class . '$paddle', \App\Services\Payment\Providers\PaddleProvider::class);
        $this->app->bind(PaymentProvider::class . '$manual_transfer', \App\Services\Payment\Providers\ManualTransferProvider::class);
        $this->app->bind(PaymentProvider::class . '$kpay', \App\Services\Payment\Providers\KBZPayProvider::class);
        $this->app->bind(PaymentProvider::class . '$wavepay', \App\Services\Payment\Providers\WavePayProvider::class);
        $this->app->bind(PaymentProvider::class . '$ayapay', \App\Services\Payment\Providers\AYAPayProvider::class);
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
