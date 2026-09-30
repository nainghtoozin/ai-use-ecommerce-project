<?php
// check_order_data.php
$pdo = new PDO("mysql:host=localhost;dbname=ecommerc_test", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== Order Data Check ===\n\n";

// Check order snapshot fields
$stmt = $pdo->query("SELECT id, subtotal, total_amount, delivery_fee, discount_amount, packaging_id, packaging_fee FROM orders LIMIT 5");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "--- Order Snapshot Fields ---\n";
foreach($orders as $o) {
    echo "Order {$o["id"]}: subtotal={$o["subtotal"]} total={$o["total_amount"]} delivery={$o["delivery_fee"]} discount={$o["discount_amount"]} pkg_id={$o["packaging_id"]} pkg_fee={$o["packaging_fee"]}\n";
}

// Check for coupon/promotion data
$stmt2 = $pdo->query("SELECT id, promotion_code, discount_amount FROM orders WHERE promotion_code IS NOT NULL LIMIT 5");
$orders2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
echo "\n--- Orders with promotion_code ---\n";
foreach($orders2 as $o) {
    echo "Order {$o["id"]}: promotion_code={$o["promotion_code"]} discount={$o["discount_amount"]}\n";
}

// Check packaging data
$stmt3 = $pdo->query("SELECT id, packaging_id, packaging_fee FROM orders WHERE packaging_fee > 0 LIMIT 5");
$pkg = $stmt3->fetchAll(PDO::FETCH_ASSOC);
echo "\n--- Orders with packaging_fee > 0 ---\n";
foreach($pkg as $o) {
    echo "Order {$o["id"]}: packaging_id={$o["packaging_id"]} packaging_fee={$o["packaging_fee"]}\n";
}

echo "\n=== Done ===\n";