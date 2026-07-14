<?php

namespace App\Providers;

use App\Auth\AccountPasswordBrokerManager;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider that registers AccountPasswordBrokerManager
 * as the auth.password binding.
 *
 * Uses extend() to replace the resolved instance with our custom manager.
 */
class AccountPasswordBrokerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Use extend to replace the resolved instance
        $this->app->extend('auth.password', function ($manager, $app) {
            return new AccountPasswordBrokerManager($app);
        });
    }
}
