<?php

namespace App\Console\Commands;

use App\Services\TenantDataPurgeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class PurgeTenantData extends Command
{
    protected $signature = 'tenants:purge
                            {--dry-run : Preview purge actions without deleting anything}
                            {--limit= : Maximum number of tenants to purge}
                            {--tenant= : Purge a single tenant by ID}';

    protected $description = 'Permanently purge tenant data past the retention deadline (double-gated, tenant-isolated)';

    public function handle(TenantDataPurgeService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (!$dryRun && !$service->purgeAllowed()) {
            $this->warn('Automatic deletion is not enabled. Enable the SuperAdmin retention setting AND set TENANT_CLEANUP_PURGE_ENABLED=true.');

            return Command::SUCCESS;
        }

        $lock = Cache::lock('tenants:purge', 3600);

        if (!$lock->get()) {
            $this->warn('Another purge process is already running.');

            return Command::SUCCESS;
        }

        try {
            $tenantId = $this->option('tenant') !== null ? (int) $this->option('tenant') : null;
            $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;

            if ($tenantId !== null) {
                $this->info($dryRun ? "Purge dry-run for tenant {$tenantId}..." : "Purging tenant {$tenantId}...");

                $eligible = $service->purgeTenant($tenantId, $dryRun);
                $this->line($eligible ? '  Eligible: yes' : '  Eligible: no (skipped)');

                return Command::SUCCESS;
            }

            $this->info($dryRun ? 'Tenant purge dry-run...' : 'Purging eligible tenants...');

            $result = $service->process($dryRun, $limit);

            $this->line("  Candidates: {$result['candidates']}");
            $this->line("  Purged:     {$result['purged']}");
            $this->line("  Skipped:    {$result['skipped']}");
            $this->line("  Errors:     {$result['errors']}");

            return Command::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
