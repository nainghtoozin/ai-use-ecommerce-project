<?php
$p = 'app/Services/Recovery/MerchantRecoveryImporter.php';
$s = file_get_contents($p);
// Add debug logging in legacy existing user not found branch? We'll log count of existing users.
// Insert a Log::info before creation fallback in legacy branch: replace the line "            } else {\n                    $existingUser = DB::table('users')" not easy.
// Instead, replace the throw? We'll just add error_log in the condition after existingUser check's closing.
echo "skip\n";
file_put_contents($p, $s);
