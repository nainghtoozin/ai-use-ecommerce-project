<?php

namespace App\Console\Commands;

use App\Models\MerchantRecoveryClaim;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CleanupMerchantBackupUploads extends Command
{
    private const UPLOAD_PATTERN = '/^upload-[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.zip$/';

    protected $signature = 'backup:cleanup-uploads {--tenant= : Only process a single tenant ID} {--dry-run : Report expired uploads without deleting them}';

    protected $description = 'Delete expired merchant backup uploads from the private backup storage root, skipping uploads used by an active recovery';

    public function handle(): int
    {
        $root = $this->root();

        if ($root === null) {
            $this->warn('Backup storage root is not available.');
            return self::SUCCESS;
        }

        $tenantOption = $this->option('tenant');

        if ($tenantOption !== null && (!ctype_digit((string) $tenantOption) || (int) $tenantOption <= 0)) {
            $this->error('The --tenant option must be a positive tenant ID.');
            return self::FAILURE;
        }

        $lock = $this->acquireLock();

        if ($lock === false) {
            $this->warn('Another backup upload cleanup is already running. Skipping this run.');
            return self::SUCCESS;
        }

        try {
            return $this->process($root, $tenantOption === null ? null : (int) $tenantOption);
        } finally {
            if ($lock !== null) {
                try {
                    $lock->release();
                } catch (Throwable) {
                }
            }
        }
    }

    private function process(string $root, ?int $tenantId): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $ttl = max(1, (int) config('exports.backup.upload_ttl_hours', 1));
        $threshold = time() - ($ttl * 3600);

        if ($dryRun) {
            $this->info('Dry-run mode: nothing will be deleted.');
        }

        $tenantIds = $tenantId !== null
            ? [$tenantId]
            : $this->tenantIds($root);

        if ($tenantIds === []) {
            $this->info('No merchant backup upload directories found.');
            return self::SUCCESS;
        }

        $scanned = 0;
        $expired = 0;
        $deleted = 0;
        $protected = 0;
        $skipped = 0;

        foreach ($tenantIds as $id) {
            $dir = $root . DIRECTORY_SEPARATOR . $id . DIRECTORY_SEPARATOR . 'uploads';

            if (!is_dir($dir)) {
                continue;
            }

            $active = $this->activeUploadNames($id);

            foreach (glob($dir . DIRECTORY_SEPARATOR . 'upload-*.zip') ?: [] as $file) {
                $scanned++;

                if (!$this->isSafeUploadFile($root, $dir, $file)) {
                    $skipped++;
                    $this->line('  skipped unsafe path: ' . $file);
                    continue;
                }

                $name = basename($file);

                if (!preg_match(self::UPLOAD_PATTERN, $name)) {
                    $skipped++;
                    $this->line('  skipped unexpected file name: ' . $name);
                    continue;
                }

                if (in_array($name, $active, true)) {
                    $protected++;
                    $this->line("Tenant #{$id}: keeping {$name} (active recovery)");
                    continue;
                }

                $modified = filemtime($file);

                if ($modified === false || $modified >= $threshold) {
                    continue;
                }

                $expired++;
                $this->line("Tenant #{$id}: expired {$name}");

                if ($dryRun) {
                    continue;
                }

                if (@unlink($file)) {
                    $deleted++;
                }
            }
        }

        if ($expired === 0) {
            $this->info("No expired backup uploads found ({$scanned} scanned, {$protected} active, {$skipped} skipped).");
        } elseif ($dryRun) {
            $this->info("Found {$expired} expired backup upload(s) out of {$scanned} scanned. Re-run without --dry-run to delete them.");
        } else {
            $this->info("Deleted {$deleted} of {$expired} expired backup upload(s) ({$scanned} scanned, {$protected} active, {$skipped} skipped).");
        }

        return self::SUCCESS;
    }

    /**
     * @return list<int>
     */
    private function tenantIds(string $root): array
    {
        $ids = [];

        foreach (glob($root . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $dir) {
            $name = basename($dir);

            if (ctype_digit($name) && (int) $name > 0) {
                $ids[] = (int) $name;
            }
        }

        sort($ids);

        return $ids;
    }

    private function isSafeUploadFile(string $root, string $dir, string $file): bool
    {
        if (is_link($file) || !is_file($file)) {
            return false;
        }

        $realRoot = realpath($root);
        $realDir = realpath($dir);
        $realFile = realpath($file);

        if ($realRoot === false || $realDir === false || $realFile === false) {
            return false;
        }

        $tenantDir = dirname($realDir);

        return dirname($realFile) === $realDir
            && basename($realDir) === 'uploads'
            && dirname($tenantDir) === $realRoot
            && ctype_digit(basename($tenantDir));
    }

    /**
     * @return list<string>
     */
    private function activeUploadNames(int $tenantId): array
    {
        try {
            return MerchantRecoveryClaim::activeUploadNames($tenantId);
        } catch (Throwable) {
            return [];
        }
    }

    private function root(): ?string
    {
        try {
            $path = Storage::disk(config('exports.disk', 'local'))->path(trim((string) config('exports.path', 'exports'), '/'));
        } catch (Throwable) {
            return null;
        }

        return is_dir($path) ? $path : null;
    }

    private function acquireLock(): mixed
    {
        try {
            $lock = Cache::lock('merchant-backup-upload-cleanup', 300);
        } catch (Throwable) {
            $this->warn('Cache locks are unavailable; continuing without a distributed lock.');
            return null;
        }

        try {
            return $lock->get() ? $lock : false;
        } catch (Throwable) {
            return null;
        }
    }
}
