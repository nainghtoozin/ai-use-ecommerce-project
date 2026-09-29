<?php
// Phase 21 Audit: Promotion/Price Breakdown

$pdo = new PDO("mysql:host=localhost;dbname=ecommerc_test", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== PHASE 21 AUDIT: Promotion & Price Breakdown ===\n\n";

// 1. Promotion types and applicability
$stmt = $pdo->query("SELECT id, name, type, applies_to FROM promotions WHERE is_active = 1 LIMIT 20");
$promos = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "--- Promotion Types ---\n";
foreach($promos as $p) echo "  id={$p['id']} name={$p['name']} type={$p['type']} applies_to={$p['applies_to']}\n";

// 2. Promotion-product mapping
$stmt2 = $pdo->query("SELECT COUNT(*) as cnt FROM promotion_product");
$r2 = $stmt2->fetch();
echo "\n--- Promotion-Product Mapping ---\n";
echo "promotion_product entries: " . $r2["cnt"] . "\n";

// Check if any promotions apply to specific products
$stmt3 = $pdo->query("SELECT promotion_id, COUNT(product_id) as product_count FROM promotion_product GROUP BY promotion_id HAVING product_count > 0 LIMIT 5");
$promo_products = $stmt3->fetchAll(PDO::FETCH_ASSOC);
echo "\n--- Promotions with Products ---\n";
foreach($promo_products as $p) echo "  promotion_id={$p['promotion_id']} has {$p['product_count']} products\n";

// 3. Order items: original_price vs price
$stmt4 = $pdo->query("SELECT id, product_id, price, original_price FROM order_items WHERE original_price IS NOT NULL LIMIT 10");
$items = $stmt4->fetchAll(PDO::FETCH_ASSOC);
echo "\n--- Order Items: original_price stored? ---\n";
echo "Items with original_price: " . count($items) . "\n";
foreach($items as $i) echo "  item_id={$i['id']} product={$i['product_id']} price={$i['price']} original_price={$i['original_price']}\n";

// Check difference
$stmt5 = $pdo->query("SELECT COUNT(*) as cnt_diff FROM order_items WHERE original_price != price AND original_price IS NOT NULL AND price IS NOT NULL");
$diff = $stmt5->fetch();
echo "Items where original_price != price: " . $diff["cnt_diff"] . "\n";

// 4. Orders with promotion_code
$stmt6 = $pdo->query("SELECT id, promotion_code, discount_amount FROM orders WHERE promotion_code IS NOT NULL LIMIT 5");
$orders = $stmt6->fetchAll(PDO::FETCH_ASSOC);
echo "\n--- Orders with promotion_code ---\n";
foreach($orders as $o) echo "  order_id={$o['id']} code={$o['promotion_code']} discount={$o['discount_amount']}\n";

// 5. Orders with promotion_id
$stmt7 = $pdo->query("SELECT id, promotion_id, discount_amount FROM orders WHERE promotion_id IS NOT NULL LIMIT 5");
$orders2 = $stmt7->fetchAll(PDO::FETCH_ASSOC);
echo "\n--- Orders with promotion_id ---\n";
foreach($orders2 as $o) echo "  order_id={$o['id']} promotion_id={$o['promotion_id']} discount={$o['discount_amount']}\n";

// 6. Coupon pivot data
$stmt7 = $pdo->query("SELECT order_id, code, discount_amount, type FROM order_coupon LIMIT 10");
$coupons = $stmt7->fetchAll(PDO::FETCH_ASSOC);
echo "\n--- Coupon Pivot Data (first 10) ---\n";
foreach($coupons as $c) echo "  order_id={$c['order_id']} code={$c['code']} discount={$c['discount_amount']} type={$c['type']}\n";

// 7. Delivery pricing with township
$stmt8 = $pdo->query("SELECT dp.township_id, ds.name as service_name, dp.min_days, dp.max_days FROM delivery_pricing dp JOIN delivery_service ds ON dp.delivery_service_id = ds.id LIMIT 10");
$dp = $stmt8->fetchAll(PDO::FETCH_ASSOC);
echo "\n--- Delivery Pricing (first 10) ---\n";
foreach($dp as $d) echo "  township_id={$d['township_id']} service={$d['service_name']} days={$d['min_days']}-{$d['max_days']}\n";

// 8. Packaging options
$stmt9 = $pdo->query("SELECT id, name, fee, is_active FROM packaging_options WHERE is_active = 1 LIMIT 10");
$pkg = $stmt9->fetchAll(PDO::FETCH_ASSOC);
echo "\n--- Packaging Options (first 10) ---\n";
foreach($pkg as $p) echo "  id={$p['id']} name={$p['name']} fee={$p['fee']} active={$p['is_active']}\n";

echo "\n=== AUDIT COMPLETE ===\n";