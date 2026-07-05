<?php

namespace App\Providers;

use App\Contracts\PaymentProvider;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\PaymentIntent;
use App\Models\User;
use App\Policies\CustomerAddressPolicy;
use App\Policies\CustomerOrderPolicy;
use App\Policies\UserPolicy;
use App\Services\NotificationPreferenceService;
use App\Services\OrderService;
use App\Services\Payment\PaymentGatewayResolver;
use App\Services\Payment\PaymentService;
use App\Services\Payment\Platform\CheckoutService;
use App\Services\Payment\Platform\GatewayResolver;
use App\Services\Payment\Platform\IdempotencyService;
use App\Services\Payment\Platform\ManualPaymentService;
use App\Services\Payment\Platform\PaymentAuditService;
use App\Services\Payment\Platform\PaymentEvidenceService;
use App\Services\Payment\Platform\PaymentExecutionGuard;
use App\Services\Payment\Platform\PaymentIntentFactory;
use App\Services\Payment\Platform\PaymentIntentService;
use App\Services\Payment\Platform\PaymentIntentValidator;
use App\Services\Payment\Platform\PaymentReviewService;
use App\Services\Payment\Platform\PaymentTimelineService;
use App\Services\Payment\Platform\PaymentTransactionService;
use App\Services\Payment\Platform\PaymentCommentService;
use App\Services\Payment\Platform\LedgerService;
use App\Services\Payment\Platform\ReferenceNumberService;
use App\Services\Payment\Platform\SubscriptionPaymentService;
use App\Services\Payment\Platform\Gateways\ManualPaymentGateway;
use App\Services\Webhook\WebhookRouter;
use App\Services\Webhook\WebhookProcessor;
use App\Services\Webhook\Adapters\StripeWebhookAdapter;
use App\Services\Webhook\Adapters\KBZPayWebhookAdapter;
use App\Services\Webhook\Adapters\AyaPayWebhookAdapter;
use App\Services\Webhook\Adapters\WavePayWebhookAdapter;
use App\Services\Webhook\Adapters\PayPalWebhookAdapter;
use App\Services\Webhook\Adapters\LemonSqueezyWebhookAdapter;
use App\Services\Webhook\Adapters\PaddleWebhookAdapter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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

        $this->app->singleton(GatewayResolver::class, function ($app) {
            $resolver = new GatewayResolver();
            $resolver->register($app->make(ManualPaymentGateway::class));
            return $resolver;
        });

        $this->app->singleton(PaymentAuditService::class);

        $this->app->singleton(SubscriptionPaymentService::class, function ($app) {
            return new SubscriptionPaymentService(
                $app->make(GatewayResolver::class),
                $app->make(PaymentAuditService::class),
            );
        });

        $this->app->singleton(ReferenceNumberService::class);

        $this->app->singleton(IdempotencyService::class);

        $this->app->singleton(PaymentExecutionGuard::class);

        $this->app->singleton(PaymentIntentFactory::class);

        $this->app->singleton(PaymentIntentValidator::class);

        $this->app->singleton(PaymentIntentService::class, function ($app) {
            return new PaymentIntentService(
                $app->make(PaymentIntentFactory::class),
                $app->make(PaymentIntentValidator::class),
                $app->make(PaymentAuditService::class),
            );
        });

        $this->app->singleton(CheckoutService::class, function ($app) {
            return new CheckoutService(
                $app->make(SubscriptionPaymentService::class),
                $app->make(PaymentAuditService::class),
                $app->make(PaymentIntentService::class),
                $app->make(PaymentIntentValidator::class),
            );
        });

        $this->app->singleton(ManualPaymentService::class, function ($app) {
            return new ManualPaymentService(
                $app->make(CheckoutService::class),
                $app->make(PaymentIntentService::class),
                $app->make(PaymentExecutionGuard::class),
                $app->make(PaymentAuditService::class),
                $app->make(PaymentTimelineService::class),
            );
        });

        $this->app->singleton(PaymentEvidenceService::class);

        $this->app->singleton(PaymentReviewService::class, function ($app) {
            return new PaymentReviewService(
                $app->make(ManualPaymentService::class),
            );
        });

        $this->app->singleton(PaymentTimelineService::class);

        $this->app->singleton(PaymentTransactionService::class, function ($app) {
            return new PaymentTransactionService(
                $app->make(ReferenceNumberService::class),
            );
        });

        $this->app->singleton(PaymentCommentService::class, function ($app) {
            return new PaymentCommentService(
                $app->make(PaymentTimelineService::class),
            );
        });

        $this->app->singleton(BillingNotificationService::class);

        $this->app->singleton(LedgerService::class);

        $this->app->singleton(WebhookRouter::class, function ($app) {
            $router = new WebhookRouter();

            $router->register($app->make(StripeWebhookAdapter::class));
            $router->register($app->make(KBZPayWebhookAdapter::class));
            $router->register($app->make(AyaPayWebhookAdapter::class));
            $router->register($app->make(WavePayWebhookAdapter::class));
            $router->register($app->make(PayPalWebhookAdapter::class));
            $router->register($app->make(LemonSqueezyWebhookAdapter::class));
            $router->register($app->make(PaddleWebhookAdapter::class));

            return $router;
        });

        $this->app->singleton(WebhookProcessor::class, function ($app) {
            return new WebhookProcessor(
                $app->make(WebhookRouter::class),
                $app->make(PaymentIntentService::class),
                $app->make(PaymentTransactionService::class),
                $app->make(PaymentTimelineService::class),
                $app->make(LedgerService::class),
            );
        });
    }

    public function boot(): void
    {
        if (!app()->environment('local')) {
            URL::forceScheme('https');
        }

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Order::class, CustomerOrderPolicy::class);
        Gate::policy(CustomerAddress::class, CustomerAddressPolicy::class);

        PaymentIntent::observe(\App\Observers\PaymentIntentObserver::class);
    }
}
