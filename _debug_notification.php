<?php
// Simulate: login via accounts guard, then check notification auth state

echo "=== PHASE 1: Log in via accounts guard ===\n";
Auth::guard('accounts')->attempt(['email' => 'admin@shop.com', 'password' => 'password'], false);
echo "Logged in: " . (Auth::guard('accounts')->check() ? 'yes' : 'no') . "\n";
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

echo "\n=== PHASE 3: Simulate $request->user() in controller ===\n";
// The controller uses $request->user() which relies on Auth
$request = request();
echo '$request->user(): ' . ($request->user() ? $request->user()->email : 'NULL') . "\n";
if (!$request->user()) {
    echo "  FAILED: \$request->user() returned null!\n";
} else {
    echo "  PASSED: \$request->user() returned: " . get_class($request->user()) . "\n";
}

echo "\n=== PHASE 4: Check session keys ===\n";
$session = $request->session();
$accountsGuard = Auth::guard('accounts');
$ref = new ReflectionClass($accountsGuard);
$nameMethod = $ref->getMethod('getName');
$nameMethod->setAccessible(true);
$sessionKey = $nameMethod->invoke($accountsGuard);
echo "Session key for accounts guard: " . $sessionKey . "\n";
echo "Session has key: " . ($session->has($sessionKey) ? 'true' : 'false') . "\n";
$webGuard = Auth::guard('web');
$webNameMethod = $ref->getMethod('getName');
$webNameMethod->setAccessible(true);
$webSessionKey = $webNameMethod->invoke($webGuard);
echo "Session key for web guard: " . $webSessionKey . "\n";
echo "Session has key: " . ($session->has($webSessionKey) ? 'true' : 'false') . "\n";

Auth::guard('accounts')->logout();
echo "\nDone.\n";
