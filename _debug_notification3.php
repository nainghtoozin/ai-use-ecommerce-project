<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// Simulate what StartSession does - set user resolver on request
$request = Request::create('/notifications/fetch', 'GET');
$request->headers->set('X-Requested-With', 'XMLHttpRequest');

// Set user resolver the same way StartSession does
$request->setUserResolver(function ($guard = null) {
    return Auth::guard($guard)->user();
});

echo "=== PHASE 1: Log in via accounts guard ===\n";
Auth::guard('accounts')->attempt(['email' => 'admin@shop.com', 'password' => 'password'], false);

echo "Auth::guard('accounts')->check(): " . (Auth::guard('accounts')->check() ? 'true' : 'false') . "\n";

echo "\n=== PHASE 2: Simulate IdentifyTenant ===\n";
if (Auth::guard('web')->check()) {
    Auth::shouldUse('web');
} elseif (Auth::guard('accounts')->check()) {
    Auth::shouldUse('accounts');
}
echo "Default guard: " . Auth::getDefaultDriver() . "\n";
echo "Auth::check(): " . (Auth::check() ? 'true' : 'false') . "\n";

echo "\n=== PHASE 3: \$request->user() test ===\n";
echo "\$request->user() without guard: " . ($request->user() ? $request->user()->email . ' (' . get_class($request->user()) . ')' : 'NULL') . "\n";
echo "\$request->user('accounts'): " . ($request->user('accounts') ? $request->user('accounts')->email : 'NULL') . "\n";
echo "\$request->user('web'): " . ($request->user('web') ? $request->user('web')->email : 'NULL') . "\n";

echo "\n=== PHASE 4: Controller call ===\n";
$controller = app()->make(\App\Http\Controllers\NotificationController::class);
try {
    $response = $controller->preferences($request);
    echo "preferences() status: " . $response->getStatusCode() . "\n";
    echo "preferences() body: " . $response->getContent() . "\n";
} catch (\Exception $e) {
    echo "preferences() exception: " . get_class($e) . ": " . $e->getMessage() . "\n";
}

Auth::guard('accounts')->logout();
echo "\nDone.\n";
