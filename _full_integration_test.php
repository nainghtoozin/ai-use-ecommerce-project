<?php
/**
 * Full integration test that simulates:
 * 1. Login via accounts guard (as StorefrontLoginController does)
 * 2. Notification request via same session
 */

$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// ========================================================
// Step 1: GET login page to establish session
// ========================================================
echo "=== Step 1: GET /store/default/login ===" . PHP_EOL;

$getRequest = Illuminate\Http\Request::create(
    '/store/default/login', 'GET'
);

// Process through kernel (this runs all middleware including StartSession)
$kernel->handle($getRequest);
$session = $getRequest->session();
echo "Session started, ID: " . $session->getId() . PHP_EOL;

// Get CSRF token from session
$csrfToken = $session->token();
echo "CSRF token: " . $csrfToken . PHP_EOL;

$kernel->terminate($getRequest, null);

// ========================================================
// Step 2: POST login via kernel (fresh request, same session)
// ========================================================
echo PHP_EOL . "=== Step 2: POST /store/default/login ===" . PHP_EOL;

$postRequest = Illuminate\Http\Request::create(
    '/store/default/login', 'POST',
    [
        'email' => 'admin@shop.com',
        'password' => 'password',
        'remember' => false,
        '_token' => $csrfToken,
    ]
);
$postRequest->headers->set('X-Requested-With', 'XMLHttpRequest');
$postRequest->setLaravelSession($session);

// Need to set route params for middleware
$postRequest->route = new Illuminate\Routing\Route('POST', 'store/{store_slug}/login', [
    'controller' => 'App\Http\Controllers\StorefrontLoginController@store'
]);
$postRequest->route->setParameter('store_slug', 'default');

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($postRequest);

echo "Login response status: " . $response->getStatusCode() . PHP_EOL;
if ($response->isRedirection()) {
    echo "Redirect to: " . $response->headers->get('Location') . PHP_EOL;
}

echo "Auth::guard('accounts')->check(): " . (app('auth')->guard('accounts')->check() ? 'YES' : 'NO') . PHP_EOL;
echo "Auth::guard('web')->check(): " . (app('auth')->guard('web')->check() ? 'YES' : 'NO') . PHP_EOL;
echo "Default guard: " . app('auth')->getDefaultDriver() . PHP_EOL;

$kernel->terminate($postRequest, $response);

// ========================================================
// Step 3: GET /notifications/preferences (new request, same session)
// ========================================================
echo PHP_EOL . "=== Step 3: GET /notifications/preferences ===" . PHP_EOL;

$notifRequest = Illuminate\Http\Request::create(
    '/notifications/preferences', 'GET'
);
$notifRequest->headers->set('X-Requested-With', 'XMLHttpRequest');
$notifRequest->headers->set('Accept', 'application/json');

// Carry over the session from login
$notifRequest->setLaravelSession($session);

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($notifRequest);

echo "Notification preferences status: " . $response->getStatusCode() . PHP_EOL;
if ($response->getStatusCode() === 401) {
    echo "*** AUTH FAILED ***" . PHP_EOL;
    echo "Response: " . $response->getContent() . PHP_EOL;
    echo PHP_EOL . "=== DIAGNOSTICS ===" . PHP_EOL;
    echo "Default guard: " . app('auth')->getDefaultDriver() . PHP_EOL;
    echo "Auth::check(): " . (app('auth')->check() ? 'true' : 'false') . PHP_EOL;
    echo "Auth::guard('accounts')->check(): " . (app('auth')->guard('accounts')->check() ? 'true' : 'false') . PHP_EOL;
    echo "Auth::guard('web')->check(): " . (app('auth')->guard('web')->check() ? 'true' : 'false') . PHP_EOL;
    echo "Session has accounts key: " . ($session->has('login_accounts_9a7f5d9b3d8c4e6a2b1f0d8c7e5a3b9f1e4d6c8a') ? 'yes' : 'no') . PHP_EOL;
} else {
    echo "AUTH SUCCEEDED" . PHP_EOL;
    echo "Response: " . $response->getContent() . PHP_EOL;
}

$kernel->terminate($notifRequest, $response);

echo PHP_EOL . "Done." . PHP_EOL;
