<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('messages:cleanup')->daily();

// ── Subscription lifecycle ──
// Process lifecycle transitions every 5 minutes during business hours,
// hourly otherwise (prevents subscriptions lingering past thresholds).
Schedule::command('subscriptions:process-expired')->everyFiveMinutes();
Schedule::command('subscriptions:send-expiry-warnings')->dailyAt('08:00');
