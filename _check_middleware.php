<?php
$router = app('router');
echo 'RouteMiddleware:' . PHP_EOL;
$middleware = $router->getMiddleware();
foreach ($middleware as $key => $value) {
    echo "  $key => " . (is_string($value) ? $value : get_class($value)) . PHP_EOL;
}
echo PHP_EOL;

// Check the route's middleware
$route = Illuminate\Support\Facades\Route::getRoutes()->getByName('notifications.fetch');
echo 'Route: notifications.fetch' . PHP_EOL;
echo 'Action: ' . $route->getActionName() . PHP_EOL;
echo 'Middleware: ' . PHP_EOL;
foreach ($route->gatherMiddleware() as $m) {
    echo "  - $m" . PHP_EOL;
}
echo PHP_EOL;

// Check authentication state
echo 'Auth check after login simulation:' . PHP_EOL;
Auth::guard('accounts')->attempt(['email' => 'admin@shop.com', 'password' => 'password'], false);
echo 'Auth::guard("accounts")->check(): ' . (Auth::guard('accounts')->check() ? 'true' : 'false') . PHP_EOL;
echo 'Auth::guard("web")->check(): ' . (Auth::guard('web')->check() ? 'true' : 'false') . PHP_EOL;
echo 'Default guard: ' . Auth::getDefaultDriver() . PHP_EOL;

// Now simulate IdentifyTenant
if (Auth::guard('web')->check()) {
    Auth::shouldUse('web');
} elseif (Auth::guard('accounts')->check()) {
    Auth::shouldUse('accounts');
}
echo 'After IdentifyTenant logic:' . PHP_EOL;
echo 'Default guard: ' . Auth::getDefaultDriver() . PHP_EOL;
echo 'Auth::check(): ' . (Auth::check() ? 'true' : 'false') . PHP_EOL;
if (Auth::check()) {
    echo 'Auth::user() class: ' . get_class(Auth::user()) . PHP_EOL;
    echo 'Auth::user()->email: ' . Auth::user()->email . PHP_EOL;
}

// Now simulate the auth middleware
echo PHP_EOL . 'Simulating auth middleware...' . PHP_EOL;
$guards = [null];
foreach ($guards as $guard) {
    $guardName = $guard ?: Auth::getDefaultDriver();
    $result = Auth::guard($guard)->check();
    echo "Auth::guard($guardName)->check(): " . ($result ? 'true' : 'false') . PHP_EOL;
}

Auth::guard('accounts')->logout();
