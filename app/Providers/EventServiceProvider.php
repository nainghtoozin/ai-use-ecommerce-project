<?php

namespace App\Providers;

use App\Events\OrderPlaced;
use App\Events\OrderStatusChanged;
use App\Events\PaymentVerified;
use App\Events\PaymentRejected;
use App\Services\DashboardCacheService;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        OrderPlaced::class => [
            [DashboardCacheService::class, 'clearOrderRelatedCache'],
        ],
        OrderStatusChanged::class => [
            [DashboardCacheService::class, 'clearOrderRelatedCache'],
        ],
        PaymentVerified::class => [
            [DashboardCacheService::class, 'clearOrderRelatedCache'],
        ],
        PaymentRejected::class => [
            [DashboardCacheService::class, 'clearOrderRelatedCache'],
        ],
    ];

    public function boot(): void
    {
        parent::boot();
    }
}