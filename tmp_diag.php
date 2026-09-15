<?php

$db = new PDO('mysql:host=127.0.0.1;port=3306;dbname=ecommerc_db', 'root', '');
$st = $db->prepare('SELECT id, status, configuration FROM storefront_revisions WHERE tenant_id=(SELECT id FROM tenants WHERE slug=?)');
$st->execute(['may']);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
echo 'REVISIONS: ' . count($rows) . PHP_EOL;
foreach ($rows as $r) {
    $cfg = json_decode($r['configuration'], true);
    $sections = $cfg['homepage']['sections'] ?? [];
    echo 'REV ' . $r['id'] . ' status=' . $r['status'] . ' sections=' . count($sections) . PHP_EOL;
    foreach ($sections as $s) {
        $type = $s['type'] ?? '?';
        if (in_array($type, ['featured_products', 'product_showcase'], true)) {
            $products = $s['data']['products'] ?? [];
            $enabled = $s['enabled'] ?? null;
            echo '  - ' . $type . ' enabled=' . ($enabled ? 'true' : ($enabled === null ? 'null' : 'false')) . ' products=' . count($products) . PHP_EOL;
            if (!empty($products)) {
                $p0 = $products[0];
                $isObj = is_object($p0);
                echo '    p0 is_object=' . ($isObj ? 'yes' : 'no');
                echo ' keys=' . ($isObj ? '' : json_encode(array_keys($p0))) . PHP_EOL;
            }
        }
    }
}