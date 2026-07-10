<?php
// Start fresh - no prior auth state in this process
$baseUrl = 'http://localhost:8003';

// Clean previous cookies
$cookieJar = tempnam(sys_get_temp_dir(), 'cookies');
$outputFile = tempnam(sys_get_temp_dir(), 'output');

// Step 1: GET login page to get session
$ch = curl_init($baseUrl . '/store/default/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
$response = curl_exec($ch);
curl_close($ch);

// Step 2: POST login with the session cookies
$postData = http_build_query([
    'email' => 'admin@shop.com',
    'password' => 'password',
    'remember' => 'false',
]);

$ch = curl_init($baseUrl . '/store/default/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-Requested-With: XMLHttpRequest',
    'Accept: application/json',
]);
$response = curl_exec($ch);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$responseHeaders = substr($response, 0, $headerSize);
$responseBody = substr($response, $headerSize);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "=== LOGIN RESPONSE ===" . PHP_EOL;
echo "Status: $httpCode" . PHP_EOL;
echo "Headers: $responseHeaders" . PHP_EOL;
echo "Body: " . substr($responseBody, 0, 500) . PHP_EOL;

if ($httpCode >= 300 && $httpCode < 400) {
    preg_match('/Location: (.+)/', $responseHeaders, $locMatch);
    echo "Redirect to: " . ($locMatch[1] ?? 'unknown') . PHP_EOL;
}

echo PHP_EOL . "=== NOTIFICATION PREFERENCES ===" . PHP_EOL;
$ch = curl_init($baseUrl . '/notifications/preferences');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-Requested-With: XMLHttpRequest',
    'Accept: application/json',
]);
$response = curl_exec($ch);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$responseHeaders = substr($response, 0, $headerSize);
$responseBody = substr($response, $headerSize);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status: $httpCode" . PHP_EOL;
if ($httpCode == 401) {
    echo "BODY: " . $responseBody . PHP_EOL;
    echo "=== AUTH FAILED ===" . PHP_EOL;
} else {
    echo "BODY: " . substr($responseBody, 0, 300) . PHP_EOL;
    echo "=== AUTH SUCCEEDED ===" . PHP_EOL;
}

echo PHP_EOL . "=== NOTIFICATION FETCH ===" . PHP_EOL;
$ch = curl_init($baseUrl . '/notifications/fetch?per_page=50');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-Requested-With: XMLHttpRequest',
    'Accept: application/json',
]);
$response = curl_exec($ch);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$responseHeaders = substr($response, 0, $headerSize);
$responseBody = substr($response, $headerSize);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status: $httpCode" . PHP_EOL;
if ($httpCode == 401) {
    echo "BODY: " . $responseBody . PHP_EOL;
    echo "=== AUTH FAILED ===" . PHP_EOL;
} else {
    echo "BODY: " . substr($responseBody, 0, 300) . PHP_EOL;
    echo "=== AUTH SUCCEEDED ===" . PHP_EOL;
}

// Cleanup
@unlink($cookieJar);
@unlink($outputFile);
