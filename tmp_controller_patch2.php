<?php
$p = 'app/Http/Controllers/Admin/MerchantBackupController.php';
$s = file_get_contents($p);
$lines = explode("\n", $s);
$idx = null;
foreach ($lines as $i => $line) {
    if (trim($line) === 'public function recovered(Request $request)') {
        $idx = $i;
        break;
    }
}
if ($idx === null) { echo "not found\n"; exit(1); }
$method = file_get_contents('php://stdin');
// Insert before the line at $idx, plus preceding blank line
array_splice($lines, $idx, 0, explode("\n", $method));
file_put_contents($p, implode("\n", $lines));
echo "done\n";
