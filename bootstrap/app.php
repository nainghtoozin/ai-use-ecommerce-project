<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\CheckUserStatus;
use App\Http\Middleware\CheckMaintenanceMode;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\TenantIsValid;
use App\Http\Middleware\SubscriptionIsActive;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Models\User;
use App\Policies\UserPolicy;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function () {
            $router = app('router');

            $router->post('/webhooks/telegram/{telegramIntegration}', \App\Http\Controllers\TelegramWebhookController::class)
                ->name('webhooks.telegram')
                ->middleware(\Illuminate\Routing\Middleware\SubstituteBindings::class);
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'check.status' => CheckUserStatus::class,
            'maintenance' => CheckMaintenanceMode::class,
            'tenant.active' => EnsureTenantIsActive::class,
            'tenant.valid' => TenantIsValid::class,
            'subscription.active' => SubscriptionIsActive::class,
        ]);

        $middleware->web(append: [
            IdentifyTenant::class,
            HandleInertiaRequests::class,
            CheckUserStatus::class,
            CheckMaintenanceMode::class,
        ]);

        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO);

        $middleware->validateCsrfTokens(except: [
            'webhooks/telegram/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
