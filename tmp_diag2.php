<?php

$db = new PDO('mysql:host=127.0.0.1;port=3306;dbname=ecommerc_db', 'root', '');
$st = $db->prepare('SELECT id, tenant_id, status, draft_revision_id, published_revision_id FROM storefronts WHERE tenant_id=(SELECT id FROM tenants WHERE slug=?)');
$st->execute(['may']);
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo json_encode($r) . PHP_EOL;
}

$st2 = $db->prepare("SELECT id, status, configuration FROM storefront_revisions WHERE tenant_id=(SELECT id FROM tenants WHERE slug=?) AND status='published'");
$st2->execute(['may']);
$rows = $st2->fetchAll(PDO::FETCH_ASSOC);
echo 'PUBLISHED REV ROWS: ' . count($rows) . PHP_EOL;
foreach ($rows as $r) {
    $cfg = json_decode($r['configuration'], true);
    foreach (($cfg['homepage']['sections'] ?? []) as $s) {
        if (in_array($s['type'] ?? '', ['featured_products', 'product_showcase'], true)) {
            echo json_encode([
                'rev' => $r['id'],
                'type' => $s['type'],
                'enabled' => $s['enabled'] ?? null,
                'configuration' => $s['configuration'] ?? null,
                'data_product_count' => count($s['data']['products'] ?? []),
            ]) . PHP_EOL;
        }
    }
}