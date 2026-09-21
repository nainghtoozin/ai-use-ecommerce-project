<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillNotificationTenants extends Command
{
    protected $signature = 'notifications:backfill-tenant {--dry-run : Show what would be backfilled without updating}';
    protected $description = 'Backfill tenant_id on database notifications where the tenant is unambiguous (one-shot)';

    public function handle(): int
    {
        $resolved = 0;
        $skipped = 0;
        $dryRun = $this->option('dry-run');

        DB::table('notifications')
            ->whereNull('tenant_id')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$resolved, &$skipped, $dryRun) {
                foreach ($rows as $row) {
                    $tenantId = $this->resolveTenant($row);

                    if ($tenantId === null) {
                        $skipped++;
                        continue;
                    }

                    $resolved++;

                    if (!$dryRun) {
                        DB::table('notifications')
                            ->where('id', $row->id)
                            ->whereNull('tenant_id')
                            ->update(['tenant_id' => $tenantId]);
                    }
                }
            });

        $this->info("Backfilled {$resolved} notification(s), left NULL {$skipped}" . ($dryRun ? ' (dry run).' : '.'));

        return self::SUCCESS;
    }

    private function resolveTenant(object $row): ?int
    {
        return $this->tenantIdFromData($row) ?? $this->tenantIdFromNotifiable($row);
    }

    private function tenantIdFromData(object $row): ?int
    {
        $data = json_decode((string) ($row->data ?? ''), true);

        if (!is_array($data)) {
            return null;
        }

        if (!empty($data['tenant_id']) && $this->tenantExists($data['tenant_id'])) {
            return (int) $data['tenant_id'];
        }

        foreach ([
            'subscription_id' => 'subscriptions',
            'order_id' => 'orders',
            'product_id' => 'products',
        ] as $key => $table) {
            if (empty($data[$key]) || !Schema::hasTable($table)) {
                continue;
            }

            $tenantId = DB::table($table)->where('id', $data[$key])->value('tenant_id');

            if ($tenantId && $this->tenantExists($tenantId)) {
                return (int) $tenantId;
            }
        }

        return null;
    }

    private function tenantIdFromNotifiable(object $row): ?int
    {
        $type = $row->notifiable_type ?? '';
        $id = $row->notifiable_id ?? null;

        if (!$id) {
            return null;
        }

        if ($type === User::class && Schema::hasTable('users')) {
            $tenantId = DB::table('users')->where('id', $id)->value('tenant_id');

            return ($tenantId && $this->tenantExists($tenantId)) ? (int) $tenantId : null;
        }

        if ($type === Account::class && Schema::hasTable('tenant_memberships')) {
            $tenantIds = DB::table('tenant_memberships')
                ->where('account_id', $id)
                ->where('status', 'active')
                ->distinct()
                ->pluck('tenant_id')
                ->all();

            if (count($tenantIds) === 1 && $this->tenantExists($tenantIds[0])) {
                return (int) $tenantIds[0];
            }
        }

        return null;
    }

    private function tenantExists(mixed $tenantId): bool
    {
        return DB::table('tenants')->where('id', $tenantId)->exists();
    }
}
