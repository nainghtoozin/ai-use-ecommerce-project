<?php

namespace App\Console\Commands;

use App\Services\TenantCleanupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ProcessTenantCleanup extends Command
{
    protected $signature = 'tenants:process-cleanup
                            {--dry-run : Preview cleanup actions without applying them}
                            {--limit= : Maximum number of tenant candidates to evaluate}
                            {--purge : Permanently purge cleaned-up tenants (disabled by default)}';

    protected $description = 'Staged, idempotent cleanup for tenants expired beyond the grace period';

    public function handle(TenantCleanupService $service): int
    {
        if ($this->option('purge')) {
            if (!config('billing.tenant_cleanup.purge_enabled', false)) {
                $this->warn('Permanent purge is disabled. Set TENANT_CLEANUP_PURGE_ENABLED=true only after verifying the full tenant-data inventory and retention policy.');

                return Command::SUCCESS;
            }

            $this->warn('Permanent purge runs via the dedicated tenants:purge command (double-gated by platform setting + server flag). No data was deleted by this command.');

            return Command::SUCCESS;
        }

        $lock = Cache::lock('tenants:process-cleanup', 600);

        if (!$lock->get()) {
            $this->warn('Another cleanup process is already running.');

            return Command::SUCCESS;
        }

        try {
            $dryRun = (bool) $this->option('dry-run');
            $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;

            $this->info($dryRun ? 'Tenant cleanup dry-run...' : 'Processing tenant cleanup...');

            $result = $service->process($dryRun, $limit);

            $this->line("  Candidates:       {$result['candidates']}");
            $this->line("  Reminded:         {$result['reminded']}");
            $this->line("  Retention nudged: {$result['retention_reminded']}");
            $this->line("  Restricted:       {$result['restricted']}");
            $this->line("  Pending deletion: {$result['pending_deletion']}");
            $this->line("  Flagged:          {$result['flagged']}");
            $this->line("  Cancelled:        {$result['cancelled']}");
            $this->line("  Skipped:          {$result['skipped']}");
            $this->line("  Errors:           {$result['errors']}");

            return Command::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
