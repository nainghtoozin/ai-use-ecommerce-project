<?php

namespace App\Services;

use App\Exceptions\TenantFileCollectionException;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Models\TenantCleanup;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class TenantDataPurgeService
{
    private const LOCK_TTL_SECONDS = 600;

    private const PROTECTED_TABLES = [
        'tenants',
        'users',
        'accounts',
        'tenant_memberships',
        'subscriptions',
        'subscription_audit_logs',
        'subscription_cancellation_requests',
        'invoices',
        'receipts',
        'payment_intents',
        'payment_evidences',
        'payment_transactions',
        'payment_timeline_events',
        'subscription_payments',
        'activity_logs',
        'tenant_cleanups',
        'plans',
        'plan_features',
        'platform_settings',
        'store_categories',
        'roles',
        'permissions',
        'model_has_roles',
        'model_has_permissions',
        'role_has_permissions',
        'settings',
        'payment_methods',
        'telegram_integrations',
        'messages',
        'notifications',
    ];

    private const DELETABLE_TABLES = [
        'team_invitations',
        // Tenant-owned coupon/promotion pivot tables. Deleted explicitly (by
        // tenant_id) so correctness does not depend on FK cascade behavior,
        // which is not enforced in all environments (e.g. the test schema).
        'coupon_category',
        'coupon_product',
        'promotion_category',
        'promotion_product',
    ];

    private const DELETABLE_MODELS = [
        \App\Models\OrderItem::class,
        \App\Models\OrderCoupon::class,
        \App\Models\OrderOverrideLog::class,
        \App\Models\Order::class,
        \App\Models\PromotionUsage::class,
        \App\Models\Promotion::class,
        \App\Models\PromotionBanner::class,
        \App\Models\Coupon::class,
        \App\Models\FlashSale::class,
        \App\Models\StockMovement::class,
        \App\Models\Inventory::class,
        \App\Models\ProductVariant::class,
        \App\Models\ProductCombo::class,
        \App\Models\Product::class,
        \App\Models\Category::class,
        \App\Models\Brand::class,
        \App\Models\Unit::class,
        \App\Models\PackagingOption::class,
        \App\Models\CodRule::class,
        \App\Models\DeliveryService::class,
        \App\Models\CustomerAddress::class,
        \App\Models\Wishlist::class,
        \App\Models\StorefrontMedia::class,
        \App\Models\StorefrontRevision::class,
        \App\Models\StorefrontNavigationItem::class,
        \App\Models\StorefrontNavigation::class,
        \App\Models\StorefrontContent::class,
        \App\Models\StorefrontDesignToken::class,
        \App\Models\StorefrontHomepageSection::class,
        \App\Models\StorefrontProductDisplayConfig::class,
        \App\Models\StorefrontThemeConfig::class,
        \App\Models\StorefrontCheckoutConfig::class,
        \App\Models\Storefront::class,
        \App\Models\WebsiteFaq::class,
        \App\Models\WebsiteInfo::class,
        \App\Models\Warehouse::class,
        \App\Models\Township::class,
        \App\Models\City::class,
        \App\Models\ImportHistory::class,
    ];

    private const FILE_COLUMNS = [
        \App\Models\Order::class => ['payment_screenshot', 'payment_proof'],
        \App\Models\Product::class => ['photo1', 'photo2', 'seo_image'],
        \App\Models\ProductVariant::class => ['image'],
        \App\Models\Category::class => ['image'],
        \App\Models\Brand::class => ['logo', 'banner'],
        \App\Models\PromotionBanner::class => ['image'],
        \App\Models\StorefrontMedia::class => ['path'],
        \App\Models\WebsiteInfo::class => ['logo', 'favicon', 'footer_logo', 'og_image', 'hero_image', 'about_image'],
        \App\Models\ImportHistory::class => ['error_report_path'],
    ];

    private const ARRAY_FILE_COLUMNS = [
        \App\Models\Product::class => ['gallery_images'],
        \App\Models\WebsiteInfo::class => ['hero_images'],
    ];

    private const SHARED_PATH_PREFIXES = ['defaults', 'placeholder', 'placeholders', 'platform-settings', 'seed', 'seeds'];

    public function __construct(
        private readonly TenantCleanupService $cleanupService,
    ) {}

    public function purgeAllowed(): bool
    {
        if (!config('billing.tenant_cleanup.purge_enabled', false)) {
            return false;
        }

        try {
            return (bool) (PlatformSetting::current()->tenant_auto_delete_enabled ?? false);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return array{candidates:int,purged:int,skipped:int,errors:int,dry_run:bool}
     */
    public function process(bool $dryRun = false, ?int $limit = null): array
    {
        $result = ['candidates' => 0, 'purged' => 0, 'skipped' => 0, 'errors' => 0, 'dry_run' => $dryRun];

        if (!$dryRun && !$this->purgeAllowed()) {
            Log::warning('Tenant data purge skipped: automatic deletion is not enabled.');
            return $result;
        }

        $maxAttempts = $this->maxPurgeAttempts();

        $parked = TenantCleanup::where('status', TenantCleanup::STATUS_PENDING_DELETION)
            ->where('attempts', '>=', $maxAttempts)
            ->count();

        if ($parked > 0) {
            Log::warning('Tenant purge parked for manual review after repeated failures.', [
                'count' => $parked,
                'max_attempts' => $maxAttempts,
            ]);
        }

        TenantCleanup::where('status', TenantCleanup::STATUS_PENDING_DELETION)
            ->where('attempts', '<', $maxAttempts)
            ->orderBy('id')
            ->chunkById(50, function ($cleanups) use (&$result, $dryRun, $limit) {
                foreach ($cleanups as $cleanup) {
                    if ($limit !== null && $result['candidates'] >= $limit) {
                        return false;
                    }

                    $result['candidates']++;

                    try {
                        $purged = $this->purgeTenant((int) $cleanup->tenant_id, $dryRun);
                        $purged ? $result['purged']++ : $result['skipped']++;
                    } catch (\Throwable $e) {
                        $result['errors']++;
                        Log::error('Tenant data purge failed.', [
                            'tenant_id' => $cleanup->tenant_id,
                            'error' => $e->getMessage(),
                        ]);

                        if (!$dryRun) {
                            TenantCleanup::where('tenant_id', $cleanup->tenant_id)->update([
                                'deletion_status' => TenantCleanup::DELETION_FAILED,
                                'last_error' => $e->getMessage(),
                                'attempts' => DB::raw('attempts + 1'),
                            ]);
                        }
                    }
                }
            });

        return $result;
    }

    public function purgeTenant(int $tenantId, bool $dryRun = false): bool
    {
        $cleanup = TenantCleanup::where('tenant_id', $tenantId)->first();

        if (!$cleanup || $cleanup->isDeleted()) {
            return false;
        }

        if ($cleanup->status !== TenantCleanup::STATUS_PENDING_DELETION) {
            return false;
        }

        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return false;
        }

        if ($tenant->activeSubscription()->exists()) {
            if (!$dryRun) {
                $this->cancelStaleDeletion($cleanup, 'Active subscription present — purge cancelled.');
            }
            return false;
        }

        if ($this->cleanupService->retentionDays() > 0
            && $cleanup->retention_ends_at !== null
            && now()->lt($cleanup->retention_ends_at)
        ) {
            return false;
        }

        if ($dryRun) {
            return true;
        }

        if (!$this->purgeAllowed()) {
            return false;
        }

        $releaseLock = null;

        try {
            $lock = Cache::lock(self::purgeLockKey($tenantId), self::LOCK_TTL_SECONDS);

            if (!$lock->get()) {
                Log::info('Tenant purge skipped: another purge is already running.', ['tenant_id' => $tenantId]);

                return false;
            }

            $releaseLock = $lock;
        } catch (\Throwable $e) {
            Log::warning('Tenant purge lock unavailable; relying on row-level lock only.', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            return $this->performPurge($tenant, $tenantId);
        } finally {
            $releaseLock?->release();
        }
    }

    private function performPurge(Tenant $tenant, int $tenantId): bool
    {
        $deletedCounts = [];
        $filePaths = [];
        $locked = null;

        try {
            DB::transaction(function () use ($tenant, &$deletedCounts, &$filePaths, &$locked) {
                $locked = TenantCleanup::where('tenant_id', $tenant->id)->lockForUpdate()->first();

                if (!$locked || $locked->status !== TenantCleanup::STATUS_PENDING_DELETION || $locked->isDeleted()) {
                    $deletedCounts = [];
                    return;
                }

                $filePaths = array_merge($filePaths, $this->collectCloudinaryUrls((int) $tenant->id));

                $townshipsBlocked = $this->locationReferencedOutsideTenant('townships', (int) $tenant->id);
                $citiesBlocked = $townshipsBlocked
                    || $this->locationReferencedOutsideTenant('cities', (int) $tenant->id);

                foreach (self::DELETABLE_TABLES as $table) {
                    try {
                        if (in_array($table, self::PROTECTED_TABLES, true)) {
                            continue;
                        }

                        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'tenant_id')) {
                            continue;
                        }

                        $deletedCounts[$table] = DB::table($table)
                            ->where('tenant_id', $tenant->id)
                            ->delete();
                    } catch (\Throwable $e) {
                        $this->recordSkippedTable($locked, (int) $tenant->id, 'table:' . $table, $e);
                    }
                }

                foreach (self::DELETABLE_MODELS as $modelClass) {
                    try {
                        if (!class_exists($modelClass)) {
                            continue;
                        }

                        $instance = new $modelClass;
                        $table = $instance->getTable();

                        if (in_array($table, self::PROTECTED_TABLES, true)) {
                            continue;
                        }

                        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'tenant_id')) {
                            continue;
                        }

                        if (($table === 'townships' && $townshipsBlocked)
                            || ($table === 'cities' && $citiesBlocked)
                        ) {
                            $this->recordSkippedTable(
                                $locked,
                                (int) $tenant->id,
                                $modelClass,
                                new \RuntimeException('Location rows are referenced by another tenant (directly or via cascade); skipped to avoid cross-tenant damage.')
                            );
                            continue;
                        }

                        $filePaths = array_merge($filePaths, $this->collectFilePaths($modelClass, (int) $tenant->id));

                        $deletedCounts[$table] = $modelClass::withoutTenantScope()
                            ->where('tenant_id', $tenant->id)
                            ->delete();
                    } catch (TenantFileCollectionException $e) {
                        throw $e;
                    } catch (\Throwable $e) {
                        $this->recordSkippedTable($locked, (int) $tenant->id, $modelClass, $e);
                    }
                }

                $subscription = \App\Models\Subscription::withoutTenantScope()
                    ->where('tenant_id', $tenant->id)
                    ->latest('id')
                    ->first();

                $metadata = $locked->metadata ?? [];

                if (!isset($metadata['purged_at']) && $subscription) {
                    SubscriptionAuditService::log($subscription, 'tenant_data_purged', [
                        'reason' => 'Retention period ended and automatic deletion is enabled.',
                        'metadata' => ['purged_tables' => $deletedCounts],
                    ]);
                }

                $metadata['purged_at'] = $metadata['purged_at'] ?? now()->toDateTimeString();
                $metadata['purged_tables'] = $metadata['purged_tables'] ?? $deletedCounts;
                $metadata['pending_files'] = array_values(array_unique(array_merge(
                    (array) ($metadata['pending_files'] ?? []),
                    $filePaths,
                )));

                $locked->update([
                    'reason' => 'Tenant data purged; file verification pending. Audit records preserved.',
                    'metadata' => $metadata,
                ]);
            });
        } catch (TenantFileCollectionException $e) {
            $this->recordCollectionFailure($tenantId, $e);

            return false;
        }

        if ($deletedCounts === []) {
            return false;
        }

        $metadata = $locked?->metadata ?? [];
        $inventory = array_values(array_unique(array_merge(
            (array) ($metadata['pending_files'] ?? $filePaths),
            (array) ($metadata['failed_files'] ?? []),
        )));

        $failedFiles = $this->deleteTenantFiles($inventory, $tenantId);

        $fresh = TenantCleanup::where('tenant_id', $tenant->id)->first();

        if ($fresh === null) {
            return $failedFiles === [];
        }

        if ($failedFiles !== []) {
            $metadata = $fresh->metadata ?? [];
            $metadata['failed_files'] = array_values(array_unique($failedFiles));
            $metadata['pending_files'] = $inventory;
            $fresh->update([
                'status' => TenantCleanup::STATUS_PENDING_DELETION,
                'deletion_status' => TenantCleanup::DELETION_FAILED,
                'last_error' => 'Some tenant files could not be verified as deleted and will be retried.',
                'metadata' => $metadata,
            ]);

            $this->bumpAttempts($fresh);

            Log::warning('Tenant purge deleted database records but some files remain unverified.', [
                'tenant_id' => $tenantId,
                'failed_files' => $failedFiles,
            ]);

            return false;
        }

        $metadata = $fresh->metadata ?? [];
        unset(
            $metadata['failed_files'],
            $metadata['pending_files'],
            $metadata['cloudinary_skipped'],
            $metadata['cloudinary_unconfigured'],
        );

        DB::transaction(function () use ($fresh, $tenant, $metadata) {
            $fresh->update([
                'status' => TenantCleanup::STATUS_DELETED,
                'deletion_status' => TenantCleanup::DELETION_DELETED,
                'deleted_at' => now(),
                'reason' => 'Retention period ended and automatic deletion is enabled. Tenant data purged; audit records preserved.',
                'metadata' => $metadata,
            ]);

            $tenant->update(['status' => 'deleted']);
            $tenant->lock();
        });

        Log::info('Tenant data purged.', ['tenant_id' => $tenantId, 'tables' => $deletedCounts]);

        return true;
    }

    /** @return string[] */
    protected function collectFilePaths(string $modelClass, int $tenantId): array
    {
        $paths = [];
        $columns = array_merge(
            self::FILE_COLUMNS[$modelClass] ?? [],
            self::ARRAY_FILE_COLUMNS[$modelClass] ?? [],
        );

        if ($columns === []) {
            return [];
        }

        try {
            $instance = new $modelClass;
            $table = $instance->getTable();
        } catch (\Throwable $e) {
            return [];
        }

        $columns = array_values(array_filter($columns, fn ($c) => Schema::hasColumn($table, $c)));

        if ($columns === []) {
            return [];
        }

        try {
            $rows = $modelClass::withoutTenantScope()
                ->where('tenant_id', $tenantId)
                ->get($columns);
        } catch (\Throwable $e) {
            throw new TenantFileCollectionException(
                "Failed to collect file paths for {$modelClass} (tenant {$tenantId}): {$e->getMessage()}",
                0,
                $e
            );
        }

        foreach ($rows as $row) {
            foreach ($columns as $column) {
                $value = $row->getAttribute($column);

                if (is_string($value) && $value !== '') {
                    $paths[] = $value;
                } elseif (is_array($value)) {
                    foreach ($value as $item) {
                        if (is_string($item) && $item !== '') {
                            $paths[] = $item;
                        }
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($paths, fn ($p) => $this->isDeletablePath($p))));
    }

    /** @return string[] all known file paths (local + Cloudinary) for a tenant, without deleting */
    public function collectTenantFilePaths(int $tenantId): array
    {
        $paths = [];

        foreach (self::FILE_COLUMNS as $modelClass => $columns) {
            if (!class_exists($modelClass)) {
                continue;
            }

            try {
                $instance = new $modelClass;

                if (!Schema::hasTable($instance->getTable()) || !Schema::hasColumn($instance->getTable(), 'tenant_id')) {
                    continue;
                }

                $paths = array_merge($paths, $this->collectFilePaths($modelClass, $tenantId));
            } catch (\Throwable $e) {
                continue;
            }
        }

        try {
            $paths = array_merge($paths, $this->collectCloudinaryUrls($tenantId));
        } catch (\Throwable $e) {
            Log::warning('Tenant file inventory incomplete; Cloudinary URLs could not be collected.', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
        }

        return $paths;
    }

    /** @param string[] $paths @return string[] failed paths */
    public function deleteTenantFiles(array $paths, int $tenantId): array
    {
        $failed = [];
        $localPaths = [];

        foreach (array_unique($paths) as $path) {
            if ($this->pathReferencedByOtherTenant($path, $tenantId)) {
                continue;
            }

            try {
                if (str_starts_with($path, 'http')) {
                    if (!$this->deleteCloudinaryAsset($path, $tenantId)) {
                        $failed[] = $path;
                    }
                    continue;
                }

                if (!$this->isDeletablePath($path)) {
                    continue;
                }

                $localPaths[] = $path;
                $disk = Storage::disk(config('filesystems.default', 'public'));

                if ($disk->exists($path) && !$disk->delete($path)) {
                    $failed[] = $path;
                }
            } catch (\Throwable $e) {
                Log::warning('Tenant file deletion failed.', [
                    'tenant_id' => $tenantId,
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
                $failed[] = $path;
            }
        }

        $this->pruneEmptyDirectories($localPaths, $tenantId);

        $failed = array_merge($failed, $this->deleteImportErrorDirectory($tenantId));

        return array_values(array_unique($failed));
    }

    /** @return string[] Cloudinary URLs referenced only by the given tenant */
    protected function collectCloudinaryUrls(int $tenantId): array
    {
        $urls = [];

        foreach (self::FILE_COLUMNS as $modelClass => $columns) {
            if (!class_exists($modelClass)) {
                continue;
            }

            try {
                $instance = new $modelClass;
                $table = $instance->getTable();
            } catch (\Throwable $e) {
                continue;
            }

            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            $select = array_values(array_filter(
                $columns,
                fn ($c) => Schema::hasColumn($table, $c)
            ));

            if ($select === []) {
                continue;
            }

            try {
                $rows = $modelClass::withoutTenantScope()->where('tenant_id', $tenantId)->get($select);
            } catch (\Throwable $e) {
                throw new TenantFileCollectionException(
                    "Failed to collect Cloudinary URLs for {$modelClass} (tenant {$tenantId}): {$e->getMessage()}",
                    0,
                    $e
                );
            }

            foreach ($rows as $row) {
                foreach ($select as $column) {
                    $value = $row->getAttribute($column);

                    if (is_string($value) && str_starts_with($value, 'http')) {
                        $urls[] = $value;
                    }
                }
            }
        }

        return array_values(array_unique($urls));
    }

    private function deleteCloudinaryAsset(string $url, int $tenantId): bool
    {
        $publicId = ImageService::cloudinaryPublicId($url);

        if (!$publicId) {
            Log::warning('Tenant purge skipped a Cloudinary asset with an unparseable URL.', [
                'tenant_id' => $tenantId,
                'url' => $url,
            ]);

            return true;
        }

        foreach (self::SHARED_PATH_PREFIXES as $prefix) {
            if (str_starts_with(strtolower($publicId), strtolower($prefix))) {
                return true;
            }
        }

        if (!ImageService::cloudinaryConfigured()) {
            $cleanup = TenantCleanup::where('tenant_id', $tenantId)->first();

            if ($cleanup) {
                $metadata = $cleanup->metadata ?? [];
                $skipped = $metadata['cloudinary_skipped'] ?? [];
                $skipped[] = $url;
                $metadata['cloudinary_skipped'] = array_values(array_unique($skipped));
                $metadata['cloudinary_unconfigured'] = true;
                $cleanup->update(['metadata' => $metadata]);
            }

            Log::error('Tenant purge halted: a Cloudinary asset requires remote deletion but Cloudinary is not configured.', [
                'tenant_id' => $tenantId,
                'public_id' => $publicId,
            ]);

            return false;
        }

        $deleted = ImageService::deleteCloudinaryPublicId($publicId);

        if (!$deleted) {
            Log::warning('Tenant Cloudinary deletion failed verification; will be retried.', [
                'tenant_id' => $tenantId,
                'public_id' => $publicId,
            ]);

            return false;
        }

        return true;
    }

    /** @return string[] failed deletions (empty on success) */
    private function deleteImportErrorDirectory(int $tenantId): array
    {
        try {
            $disk = Storage::disk('local');
            $directory = "import-errors/{$tenantId}";

            if (!$disk->exists($directory)) {
                return [];
            }

            if (!$disk->deleteDirectory($directory) && $disk->exists($directory)) {
                return [$directory];
            }
        } catch (\Throwable $e) {
            Log::warning('Tenant import-error directory deletion failed.', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);

            return ["import-errors/{$tenantId}"];
        }

        return [];
    }

    /** @param string[] $localPaths */
    private function pruneEmptyDirectories(array $localPaths, int $tenantId): void
    {
        if ($localPaths === []) {
            return;
        }

        $disk = Storage::disk(config('filesystems.default', 'public'));
        $candidates = [];

        foreach (array_unique($localPaths) as $path) {
            $dir = str_replace('\\', '/', dirname(ltrim($path, '/')));

            while ($dir !== '' && $dir !== '.' && !$this->isSharedPath($dir)) {
                $candidates[$dir] = strlen($dir);
                $parent = str_replace('\\', '/', dirname($dir));

                if ($parent === $dir || $parent === '.') {
                    break;
                }

                $dir = $parent;
            }
        }

        arsort($candidates);

        foreach (array_keys($candidates) as $dir) {
            try {
                if ($disk->exists($dir) && $disk->allFiles($dir) === [] && $disk->allDirectories($dir) === []) {
                    $disk->deleteDirectory($dir);
                }
            } catch (\Throwable $e) {
                Log::warning('Tenant empty-directory pruning failed.', [
                    'tenant_id' => $tenantId,
                    'directory' => $dir,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function isSharedPath(string $path): bool
    {
        $normalized = strtolower(ltrim($path, '/'));

        foreach (self::SHARED_PATH_PREFIXES as $prefix) {
            if (str_starts_with($normalized, strtolower($prefix))) {
                return true;
            }
        }

        return false;
    }

    private function isDeletablePath(string $path): bool
    {
        if ($path === '' || str_starts_with($path, 'http')) {
            return false;
        }

        $normalized = ltrim($path, '/');

        foreach (self::SHARED_PATH_PREFIXES as $prefix) {
            if (str_starts_with(strtolower($normalized), strtolower($prefix))) {
                return false;
            }
        }

        return !str_contains($normalized, '..');
    }

    private function pathReferencedByOtherTenant(string $path, int $tenantId): bool
    {
        foreach (self::FILE_COLUMNS as $modelClass => $columns) {
            if (!class_exists($modelClass)) {
                continue;
            }

            try {
                $instance = new $modelClass;

                if (!Schema::hasColumn($instance->getTable(), 'tenant_id')) {
                    continue;
                }

                foreach ($columns as $column) {
                    if (!Schema::hasColumn($instance->getTable(), $column)) {
                        continue;
                    }

                    $exists = $modelClass::withoutTenantScope()
                        ->where('tenant_id', '!=', $tenantId)
                        ->where($column, $path)
                        ->exists();

                    if ($exists) {
                        return true;
                    }
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return false;
    }

    public static function purgeLockKey(int $tenantId): string
    {
        return 'tenant-data-purge:' . $tenantId;
    }

    private function maxPurgeAttempts(): int
    {
        return max(1, (int) config('billing.tenant_cleanup.max_purge_attempts', 5));
    }

    private function bumpAttempts(TenantCleanup $cleanup): void
    {
        TenantCleanup::where('id', $cleanup->getKey())->update([
            'attempts' => DB::raw('attempts + 1'),
        ]);
    }

    private function recordCollectionFailure(int $tenantId, \Throwable $e): void
    {
        $cleanup = TenantCleanup::where('tenant_id', $tenantId)->first();

        if (!$cleanup || $cleanup->isDeleted()) {
            return;
        }

        $metadata = $cleanup->metadata ?? [];
        $metadata['collection_error'] = $e->getMessage();
        $metadata['collection_failed_at'] = now()->toDateTimeString();

        $cleanup->update([
            'status' => TenantCleanup::STATUS_PENDING_DELETION,
            'deletion_status' => TenantCleanup::DELETION_FAILED,
            'last_error' => 'Tenant file inventory could not be collected; destructive purge aborted to preserve data.',
            'metadata' => $metadata,
        ]);

        $this->bumpAttempts($cleanup);

        Log::error('Tenant purge aborted before deletion: file inventory collection failed.', [
            'tenant_id' => $tenantId,
            'error' => $e->getMessage(),
        ]);
    }

    private function recordSkippedTable(TenantCleanup $locked, int $tenantId, string $label, \Throwable $e): void
    {
        Log::warning('Tenant purge skipped a table.', [
            'tenant_id' => $tenantId,
            'model' => $label,
            'error' => $e->getMessage(),
        ]);

        $metadata = $locked->metadata ?? [];
        $skipped = $metadata['skipped_tables'] ?? [];
        $skipped[] = $label . ': ' . $e->getMessage();
        $metadata['skipped_tables'] = $skipped;
        $locked->metadata = $metadata;
    }

    private function locationReferencedOutsideTenant(string $table, int $tenantId): bool
    {
        try {
            $ids = DB::table($table)->where('tenant_id', $tenantId)->pluck('id');

            if ($ids->isEmpty()) {
                return false;
            }

            $referrers = $table === 'cities'
                ? ['delivery_pricing' => 'city_id', 'orders' => 'city_id', 'customer_addresses' => 'city_id']
                : ['delivery_pricing' => 'township_id', 'orders' => 'township_id', 'customer_addresses' => 'township_id'];

            foreach ($referrers as $refTable => $column) {
                if (!Schema::hasTable($refTable) || !Schema::hasColumn($refTable, $column)) {
                    continue;
                }

                $query = DB::table($refTable)->whereIn($column, $ids);

                if (Schema::hasColumn($refTable, 'tenant_id')) {
                    $query->where('tenant_id', '!=', $tenantId);
                }

                if ($query->exists()) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Tenant purge location reference check failed; skipping location delete.', [
                'tenant_id' => $tenantId,
                'table' => $table,
                'error' => $e->getMessage(),
            ]);

            return true;
        }

        return false;
    }

    private function cancelStaleDeletion(TenantCleanup $cleanup, string $reason): void
    {
        $cleanup->update([
            'status' => TenantCleanup::STATUS_CANCELLED,
            'deletion_status' => TenantCleanup::DELETION_NONE,
            'reason' => $reason,
        ]);
    }
}
