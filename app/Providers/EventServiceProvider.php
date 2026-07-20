<?php

namespace App\Providers;

use App\Events\Payments\PaymentIntentCompleted;
use App\Listeners\ActivateSubscriptionOnPaymentCompleted;
use App\Listeners\ActivateTenantOnVerified;
use App\Listeners\CreateTransactionFromCompletedIntent;
use App\Listeners\GenerateInvoiceFromCompletedIntent;
use App\Listeners\PaymentTimelineEventSubscriber;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        PaymentIntentCompleted::class => [
            CreateTransactionFromCompletedIntent::class,
            ActivateSubscriptionOnPaymentCompleted::class,
            GenerateInvoiceFromCompletedIntent::class,
        ],
        Verified::class => [
            ActivateTenantOnVerified::class,
        ],
    ];

    protected $subscribe = [
        PaymentTimelineEventSubscriber::class,
    ];

    public function boot(): void
    {
        parent::boot();
    }
}