<?php
$p = 'app/Services/Recovery/MerchantRecoveryImporter.php';
$s = file_get_contents($p);
$old = "        \$this->resetImportState();\n\n        \$this->tenantId = \$tenantId;\n";
$new = "        \$this->resetImportState();\n\n        \\Illuminate\\Support\\Facades\\Log::info('IMPORT start restoreExisting=' . var_export(\$this->restoreExisting, true) . ' tenantId=' . \$tenantId);\n\n        \$this->tenantId = \$tenantId;\n";
// But resetImportState resets restoreExisting to false! setRestoreExisting called before import, but import calls resetImportState at start, wiping flag.
$s = str_replace($old, $new, $s);
file_put_contents($p, $s);
echo "done\n";
