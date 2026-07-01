<?php

namespace App\Providers;

use App\Events\Payments\PaymentIntentCompleted;
use App\Listeners\CreateTransactionFromCompletedIntent;
use App\Listeners\PaymentTimelineEventSubscriber;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        PaymentIntentCompleted::class => [
            CreateTransactionFromCompletedIntent::class,
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