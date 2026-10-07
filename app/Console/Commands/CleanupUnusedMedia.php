<?php

namespace App\Console\Commands;

use App\Models\StorefrontMedia;
use App\Models\Tenant;
use App\Services\MediaCleanupService;
use Illuminate\Console\Command;

class CleanupUnusedMedia extends Command
{
    protected $signature = 'media:cleanup {--tenant= : Only process a single tenant ID} {--delete : Actually delete orphaned media (default is dry-run)} {--limit=500 : Maximum media rows to scan per tenant}';

    protected $description = 'List (or delete with --delete) media rows that are confidently unused by any supported consumer';

    public function handle(MediaCleanupService $cleanup): int
    {
        $tenantOption = $this->option('tenant');
        $tenants = $tenantOption
            ? Tenant::whereKey($tenantOption)->get()
            : Tenant::orderBy('id')->get(['id', 'slug', 'name']);

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found.');
            return self::SUCCESS;
        }

        $delete = (bool) $this->option('delete');
        $limit = max(1, (int) $this->option('limit'));

        if (!$delete) {
            $this->info('Dry-run mode: nothing will be deleted. Pass --delete to remove confidently unused media.');
        }

        $totalOrphans = 0;
        $totalDeleted = 0;

        foreach ($tenants as $tenant) {
            $orphans = $cleanup->orphanCandidates((int) $tenant->id, $limit);
            if (empty($orphans)) {
                continue;
            }

            $totalOrphans += count($orphans);
            $this->line("Tenant #{$tenant->id} ({$tenant->slug}): " . count($orphans) . ' orphan candidate(s)');
            foreach ($orphans as $media) {
                $label = "  #{$media->id} {$media->path}";
                if ($delete) {
                    $row = StorefrontMedia::withoutTenantScope()->find($media->id);
                    $ok = $row ? $cleanup->deleteOrphan($row, (int) $tenant->id) : false;
                    $this->line($label . ($ok ? ' [deleted]' : ' [skipped: became referenced]'));
                    if ($ok) {
                        $totalDeleted++;
                    }
                } else {
                    $this->line($label);
                }
            }
        }

        if ($totalOrphans === 0) {
            $this->info('No orphaned media found.');
        } elseif ($delete) {
            $this->info("Deleted {$totalDeleted} of {$totalOrphans} orphan candidate(s).");
        } else {
            $this->info("Found {$totalOrphans} orphan candidate(s). Re-run with --delete to remove them.");
        }

        return self::SUCCESS;
    }
}
