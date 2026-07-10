<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// Create a simulated request
$request = Request::create('/notifications/fetch', 'GET');
$request->setRouteResolver(function () {
    return Illuminate\Support\Facades\Route::getRoutes()->getByName('notifications.fetch');
});
$request->headers->set('X-Requested-With', 'XMLHttpRequest');

echo "=== PHASE 1: Log in via accounts guard ===\n";
Auth::guard('accounts')->attempt(['email' => 'admin@shop.com', 'password' => 'password'], false);
echo "Logged in accounts: " . (Auth::guard('accounts')->check() ? 'yes' : 'no') . "\n";
echo "Logged in web: " . (Auth::guard('web')->check() ? 'yes' : 'no') . "\n";
echo "Default guard: " . Auth::getDefaultDriver() . "\n";

echo "\n=== PHASE 2: Simulate IdentifyTenant middleware ===\n";
if (Auth::guard('web')->check()) {
    Auth::shouldUse('web');
    echo "Identified web guard\n";
} elseif (Auth::guard('accounts')->check()) {
    Auth::shouldUse('accounts');
    echo "Identified accounts guard\n";
}
echo "Default guard after IdentifyTenant: " . Auth::getDefaultDriver() . "\n";
echo "Auth::check(): " . (Auth::check() ? 'true' : 'false') . "\n";
if (Auth::check()) {
    echo "Auth::user()->email: " . Auth::user()->email . "\n";
    echo "Auth::user() class: " . get_class(Auth::user()) . "\n";
}

echo "\n=== PHASE 3: Simulate \$request->user() in controller ===\n";
echo "Auth via shouldUse callback: " . (Auth::user() ? Auth::user()->email : 'NULL') . "\n";
echo "request()->user(): " . (request()->user() ? request()->user()->email : 'NULL') . "\n";
echo "\$request->user(): " . ($request->user() ? $request->user()->email : 'NULL') . "\n";

echo "\n=== PHASE 4: Session key analysis ===\n";
$session = $request->session();
$accountsGuard = Auth::guard('accounts');
$ref = new ReflectionClass($accountsGuard);
$nameMethod = $ref->getMethod('getName');
$nameMethod->setAccessible(true);
$accountsKey = $nameMethod->invoke($accountsGuard);
echo "Accounts guard session key: " . $accountsKey . "\n";
echo "Session has accounts key: " . ($session->has($accountsKey) ? 'yes' : 'no') . "\n";

$webGuard = Auth::guard('web');
$webKey = $nameMethod->invoke($webGuard);
echo "Web guard session key: " . $webKey . "\n";
echo "Session has web key: " . ($session->has($webKey) ? 'yes' : 'no') . "\n";

echo "\n=== PHASE 5: Controller methods ===\n";
$controller = app()->make(\App\Http\Controllers\NotificationController::class);
try {
    $response = $controller->preferences($request);
    echo "preferences() response: " . $response->getStatusCode() . "\n";
} catch (\Exception $e) {
    echo "preferences() exception: " . get_class($e) . ": " . $e->getMessage() . "\n";
}

try {
    $response = $controller->index($request);
    echo "index() response: " . $response->getStatusCode() . "\n";
} catch (\Exception $e) {
    echo "index() exception: " . get_class($e) . ": " . $e->getMessage() . "\n";
}

Auth::guard('accounts')->logout();
echo "\nDone.\n";
