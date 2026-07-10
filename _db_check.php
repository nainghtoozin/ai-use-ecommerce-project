<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo '=== TENANTS ===' . PHP_EOL;
foreach (App\Models\Tenant::all() as $t) {
    echo "  id={$t->id} slug={$t->slug} name={$t->name}" . PHP_EOL;
}

echo PHP_EOL . '=== MEMBERSHIPS ===' . PHP_EOL;
foreach (App\Models\TenantMembership::all() as $m) {
    echo "  id={$m->id} account_id={$m->account_id} tenant_id={$m->tenant_id} role_id={$m->role_id} is_owner=" . ($m->is_owner ? 'true' : 'false') . PHP_EOL;
}

echo PHP_EOL . '=== ACCOUNTS ===' . PHP_EOL;
foreach (App\Models\Account::all() as $a) {
    echo "  id={$a->id} email={$a->email} status={$a->status} has_pw=" . (empty($a->password) ? 'NO' : 'YES') . PHP_EOL;
}

echo PHP_EOL . '=== USERS ===' . PHP_EOL;
foreach (App\Models\User::all() as $u) {
    echo "  id={$u->id} email={$u->email} status={$u->status} tenant_id=" . ($u->tenant_id ?? 'null') . PHP_EOL;
}
