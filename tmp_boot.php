<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

use App\Models\Tenant;
use App\Services\StorefrontConfigurationResolver;

$tenant = Tenant::withOutGlobalScope()->where('slug', 'may')->first();

if (!$tenant) {
    echo 'tenant may not found' . PHP_EOL;
    exit(1);
}

$resolver = $app->make(StorefrontConfigurationResolver::class);
$config = $resolver->resolve($tenant, 'published');

foreach (($config['homepage']['sections'] ?? []) as $section) {
    $type = $section['type'] ?? '?';
    if (!in_array($type, ['featured_products', 'product_showcase'], true)) {
        continue;
    }
    $products = $section['data']['products'] ?? [];
    echo $type . ' products=' . count($products) . PHP_EOL;
    if (empty($products)) {
        continue;
    }
    $p0 = $products[0];
    echo '  p0 is_object=' . (is_object($p0) ? 'yes' : 'no');
    echo ' class=' . (is_object($p0) ? $p0::class->getName() : 'array');
    echo ' keys=' . (is_object($p0) ? 'n/a' : json_encode(array_keys($p0))) . PHP_EOL;
}