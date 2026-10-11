<?php

namespace App\Console\Commands;

use App\Services\TenantCleanupService;
use Illuminate\Console\Command;

class ProcessTenantCleanup extends Command
{
    protected $signature = 'tenants:process-cleanup
                            {--dry-run : Preview cleanup actions without applying them}
                            {--purge : Permanently purge cleaned-up tenants (disabled by default)}';

    protected $description = 'Staged, idempotent cleanup for tenants expired beyond the grace period';

    public function handle(TenantCleanupService $service): int
    {
        if ($this->option('purge')) {
            if (!config('billing.tenant_cleanup.purge_enabled', false)) {
                $this->warn('Permanent purge is disabled. Set TENANT_CLEANUP_PURGE_ENABLED=true only after verifying the full tenant-data inventory and retention policy.');

                return Command::SUCCESS;
            }

            $this->warn('Permanent purge is not implemented in this release (archival-first policy). No data was deleted.');

            return Command::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        $this->info($dryRun ? 'Tenant cleanup dry-run...' : 'Processing tenant cleanup...');

        $result = $service->process($dryRun);

        $this->line("  Candidates:  {$result['candidates']}");
        $this->line("  Reminded:    {$result['reminded']}");
        $this->line("  Restricted:  {$result['restricted']}");
        $this->line("  Flagged:     {$result['flagged']}");
        $this->line("  Cancelled:   {$result['cancelled']}");
        $this->line("  Skipped:     {$result['skipped']}");
        $this->line("  Errors:      {$result['errors']}");

        return Command::SUCCESS;
    }
}
