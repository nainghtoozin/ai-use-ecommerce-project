<?php

$db = new PDO('mysql:host=127.0.0.1;port=3306;dbname=ecommerc_db', 'root', '');
$st = $db->prepare('SELECT id, tenant_id, name, status, type, price, featured FROM products WHERE tenant_id=4 AND id IN (9,14,48)');
$st->execute();
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo json_encode($r) . PHP_EOL;
}
$st2 = $db->prepare('SELECT COUNT(*) AS c FROM products WHERE tenant_id=4');
$st2->execute();
echo 'tenant4 product count: ' . $st2->fetch(PDO::FETCH_ASSOC)['c'] . PHP_EOL;