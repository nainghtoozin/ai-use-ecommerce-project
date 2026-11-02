<?php

namespace App\Services\Recovery;

use App\Exceptions\SubscriptionLimitException;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\MerchantRecoveryClaim;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Export\MerchantBackupSigner;
use App\Services\Export\MerchantBackupVerifier;
use App\Services\SubscriptionLimitService;
use App\Services\TenantBootstrapService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Throwable;
use ZipArchive;

final class MerchantRecoveryService
{
    public function __construct(
        private readonly MerchantBackupVerifier $verifier,
        private readonly TenantBootstrapService $bootstrap,
        private readonly MerchantRecoveryImporter $importer
    ) {}

    /**
     * Recover a verified archive into a brand new inactive store.
     *
     * @return array<string, mixed>
     */
    public function recover(
        Tenant $source,
        string $archivePath,
        string $archiveId,
        string $targetSlug,
        string $confirmation,
        User|Account $actor
    ): array {
        $lockSeconds = max(30, (int) config('exports.backup.recovery_lock_seconds', 300));
        $lock = Cache::lock('merchant-recovery:' . $source->id, $lockSeconds);

        if (!$lock->get()) {
            $this->auditFailure($source, $actor, 'recovery_in_progress', $archiveId);

            throw new MerchantRecoveryException('recovery_in_progress', 'Another recovery attempt is already running for this store.');
        }

        $correlationId = (string) Str::uuid();

        try {
            return $this->attempt($source, $archivePath, $archiveId, $targetSlug, $confirmation, $actor, $correlationId);
        } finally {
            if ($lock->isOwnedByCurrentProcess()) {
                $lock->release();
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function attempt(
        Tenant $source,
        string $archivePath,
        string $archiveId,
        string $targetSlug,
        string $confirmation,
        User|Account $actor,
        string $correlationId = ''
    ): array {
        if ($confirmation !== $source->slug) {
            $this->auditFailure($source, $actor, 'confirmation_mismatch', $archiveId);

            throw new MerchantRecoveryException('confirmation_mismatch', 'Type the current store slug exactly to confirm recovery.');
        }

        $targetSlug = trim($targetSlug);

        if (Str::slug($targetSlug) !== $targetSlug || mb_strlen($targetSlug) < 3 || mb_strlen($targetSlug) > 80) {
            $this->auditFailure($source, $actor, 'invalid_target_slug', $archiveId);

            throw new MerchantRecoveryException('invalid_target_slug', 'Choose a target store slug containing only lowercase letters, numbers and hyphens.');
        }

        if ($targetSlug === $source->slug) {
            $this->auditFailure($source, $actor, 'invalid_target_slug', $archiveId);

            throw new MerchantRecoveryException('invalid_target_slug', 'The recovered store must receive a different slug from the source store.');
        }

        if (Tenant::where('slug', $targetSlug)->exists()) {
            $this->auditFailure($source, $actor, 'target_slug_taken', $archiveId);

            throw new MerchantRecoveryException('target_slug_taken', 'That target store slug is already in use.');
        }

        if ($this->archiveAlreadyRecovered($archiveId, 'new_store')) {
            $this->auditFailure($source, $actor, 'duplicate_recovery', $archiveId);

            throw new MerchantRecoveryException('duplicate_recovery', 'This archive has already been recovered. Duplicate recovery is blocked.');
        }

        if (!is_file($archivePath)) {
            $this->auditFailure($source, $actor, 'archive_missing', $archiveId);

            throw new MerchantRecoveryException('archive_missing', 'The retained upload is no longer available. Verify the archive again.');
        }

        $ttlSeconds = max(300, (int) config('exports.backup.upload_ttl_hours', 1) * 3600);
        $modified = filemtime($archivePath);

        if ($modified === false || (time() - $modified) > $ttlSeconds) {
            $this->auditFailure($source, $actor, 'archive_expired', $archiveId);

            throw new MerchantRecoveryException('archive_expired', 'The retained upload has expired. Verify the archive again.');
        }

        try {
            $verified = $this->verifier->verify($archivePath, $source, MerchantBackupSigner::fromConfig());
        } catch (Throwable $exception) {
            $this->auditFailure($source, $actor, 'reverification_failed', $archiveId);

            throw new MerchantRecoveryException('reverification_failed', $exception->getMessage(), $exception);
        }

        if ((string) ($verified['manifest']['archive_id'] ?? '') !== $archiveId) {
            $this->auditFailure($source, $actor, 'archive_mismatch', $archiveId);

            throw new MerchantRecoveryException('archive_mismatch', 'The retained upload does not match the verified archive. Verify the archive again.');
        }

        $plan = $this->resolvePlan($source);

        if (!$plan) {
            $this->auditFailure($source, $actor, 'plan_unavailable', $archiveId);

            throw new MerchantRecoveryException('plan_unavailable', 'The source store has no subscription plan to carry into the recovered store.');
        }

        try {
            $this->assertArchiveFitsPlan($archivePath, $plan);
        } catch (SubscriptionLimitException $exception) {
            $this->auditFailure($source, $actor, 'subscription_limit', $archiveId);

            throw new MerchantRecoveryException('subscription_limit', $exception->getMessage(), $exception);
        }

        try {
            $claim = $this->acquireClaim($source, $archiveId, $targetSlug, $archivePath, $actor);
        } catch (MerchantRecoveryException $exception) {
            $this->auditFailure($source, $actor, $exception->reason(), $archiveId);

            throw $exception;
        }

        $snapshot = $this->sourceSnapshot($source->id);

        $this->safeAudit(function () use ($source, $archiveId, $targetSlug, $plan): void {
            ActivityLogger::log(
                'Merchant recovery started',
                'merchant_recovery_started',
                $source,
                [
                    'archive_id' => $archiveId,
                    'target_slug' => $targetSlug,
                    'plan_id' => $plan->id,
                    'ip' => request()->ip(),
                ],
                'backup',
                ActivityLog::CATEGORY_SYSTEM
            );
        });

        try {
            $result = DB::transaction(function () use ($source, $archivePath, $archiveId, $targetSlug, $plan, $actor, $snapshot, $verified): array {
                $tenant = Tenant::create([
                    'name' => Str::limit($source->name . ' (Recovered)', 200, ''),
                    'slug' => $targetSlug,
                    'domain' => null,
                    'store_url' => null,
                    'email' => null,
                    'logo' => null,
                    'status' => 'pending',
                    'store_category_id' => $source->store_category_id,
                    'subscription_plan_id' => $plan->id,
                    'settings' => [
                        'recovery' => [
                            'archive_id' => $archiveId,
                            'source_tenant_id' => $source->id,
                            'source_slug' => $source->slug,
                            'started_at' => now()->toIso8601String(),
                        ],
                    ],
                ]);

                $ownerResult = $this->bootstrap->bootstrapRecovery($tenant, $source, $actor, $plan);

                $imported = $this->importer->import($archivePath, $tenant->id, $verified['manifest']);
                $checks = $this->importer->verify($verified['manifest']);

                $settings = $tenant->settings ?? [];
                $settings['recovery'] = array_merge($settings['recovery'] ?? [], [
                    'completed_at' => now()->toIso8601String(),
                    'recovered_tenant_id' => $tenant->id,
                    'counts' => $checks['verified_counts'],
                ]);

                $tenant->update(['settings' => $settings]);
                $tenant->refresh();

                if ($tenant->status !== 'pending' || $tenant->activated_at !== null) {
                    throw new MerchantRecoveryException('inactive_verification_failed', 'The recovered store was not left inactive.');
                }

                if ($this->sourceSnapshot($source->id) !== $snapshot) {
                    throw new MerchantRecoveryException('source_modified', 'Recovery attempted to modify the source store. The attempt was rolled back.');
                }

                return [
                    'tenant' => $tenant,
                    'owner' => $ownerResult['owner'],
                    'owner_handoff_required' => (bool) $ownerResult['handoff_required'],
                    'checks' => $checks,
                    'imported' => $imported,
                ];
            }, 3);
        } catch (Throwable $exception) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $this->markClaimFailed($claim, $exception);

            $reason = $exception instanceof MerchantRecoveryException ? $exception->reason() : 'recovery_failed';

            $this->auditFailure($source, $actor, $reason, $archiveId);

            $this->safeAudit(function () use ($source, $archiveId, $reason): void {
                ActivityLogger::log(
                    'Merchant recovery rolled back',
                    'merchant_recovery_rolled_back',
                    $source,
                    [
                        'archive_id' => $archiveId,
                        'reason' => $reason,
                    ],
                    'backup',
                    ActivityLog::CATEGORY_SYSTEM
                );
            });

            Log::error('Merchant recovery failed', [
                'correlation_id' => $correlationId,
                'tenant_id' => $source->id,
                'archive_id' => $archiveId,
                'reason' => $reason,
                'exception_class' => get_class($exception),
                'exception_message' => $exception->getMessage(),
                'exception_file' => $exception->getFile(),
                'exception_line' => $exception->getLine(),
            ]);

            if ($exception instanceof MerchantRecoveryException) {
                throw $exception;
            }

            throw new MerchantRecoveryException('recovery_failed', 'Recovery failed and every change was rolled back.', $exception);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $tenant = $result['tenant'];
        $owner = $result['owner'];

        $this->completeClaim($claim, $tenant);

        $this->verifier->forgetUpload($archivePath);

        $this->safeAudit(function () use ($source, $archiveId, $tenant, $plan, $result): void {
            ActivityLogger::log(
                'Merchant recovery completed',
                'merchant_recovery_completed',
                $source,
                [
                    'archive_id' => $archiveId,
                    'recovered_tenant_id' => $tenant->id,
                    'recovered_slug' => $tenant->slug,
                    'plan_id' => $plan->id,
                    'counts' => $result['checks']['verified_counts'],
                ],
                'backup',
                ActivityLog::CATEGORY_SYSTEM
            );
        });

        return [
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'tenant_slug' => $tenant->slug,
            'status' => $tenant->status,
            'plan_name' => $plan->name,
            'archive_id' => $archiveId,
            'owner_email' => $owner?->email,
            'owner_mode' => $owner instanceof Account ? 'account' : ($owner ? 'user' : 'none'),
            'owner_handoff_required' => (bool) ($result['owner_handoff_required'] ?? false),
            'counts' => $result['checks']['verified_counts'],
            'guest_customers' => (int) ($result['checks']['guest_customers'] ?? 0),
            'warnings' => $this->resultWarnings($owner, (bool) ($result['owner_handoff_required'] ?? false)),
        ];
    }

    /**
     * Acquire the durable, database-backed claim for this archive.
     *
     * The unique index on archive_id is the authoritative guard, so a duplicate
     * is rejected even when the cache lock expired, the process restarted or a
     * retry replays the same archive. A claim owned by a live attempt is never
     * stolen; only failed or lease-expired claims may be taken over.
     */
    private function acquireClaim(
        Tenant $source,
        string $archiveId,
        string $targetSlug,
        string $archivePath,
        User|Account $actor
    ): MerchantRecoveryClaim {
        $leaseMinutes = max(5, (int) config('exports.backup.recovery_claim_lease_minutes', 120));
        $expiresAt = now()->addMinutes($leaseMinutes);

        try {
            return MerchantRecoveryClaim::create([
                'archive_id' => $archiveId,
                'mode' => 'new_store',
                'source_tenant_id' => $source->id,
                'target_slug' => $targetSlug,
                'archive_name' => basename($archivePath),
                'actor_type' => get_class($actor),
                'actor_id' => $actor->getAuthIdentifier(),
                'status' => MerchantRecoveryClaim::STATUS_CLAIMED,
                'expires_at' => $expiresAt,
            ]);
        } catch (QueryException $exception) {
            if (!$this->isUniqueViolation($exception)) {
                throw $exception;
            }
        }

        $existing = MerchantRecoveryClaim::where('archive_id', $archiveId)->first();

        if ($existing?->isCompleted()) {
            throw new MerchantRecoveryException('duplicate_recovery', 'This archive has already been recovered. Duplicate recovery is blocked.');
        }

        if (!$existing) {
            throw new MerchantRecoveryException('recovery_in_progress', 'Another recovery attempt is already running for this archive.');
        }

        $reclaimable = $existing->status === MerchantRecoveryClaim::STATUS_FAILED
            || ($existing->status === MerchantRecoveryClaim::STATUS_CLAIMED && !$existing->isActive());

        if (!$reclaimable) {
            throw new MerchantRecoveryException('recovery_in_progress', 'Another recovery attempt is already running for this archive.');
        }

        $reclaimed = MerchantRecoveryClaim::query()
            ->whereKey($existing->id)
            ->whereIn('status', [MerchantRecoveryClaim::STATUS_CLAIMED, MerchantRecoveryClaim::STATUS_FAILED])
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '<=', now());
            })
            ->update([
                'target_slug' => $targetSlug,
                'target_tenant_id' => null,
                'archive_name' => basename($archivePath),
                'actor_type' => get_class($actor),
                'actor_id' => $actor->getAuthIdentifier(),
                'status' => MerchantRecoveryClaim::STATUS_CLAIMED,
                'expires_at' => $expiresAt,
                'completed_at' => null,
                'updated_at' => now(),
            ]);

        if ($reclaimed !== 1) {
            throw new MerchantRecoveryException('recovery_in_progress', 'Another recovery attempt is already running for this archive.');
        }

        return $existing->refresh();
    }

    private function completeClaim(MerchantRecoveryClaim $claim, Tenant $tenant): void
    {
        try {
            $claim->update([
                'status' => MerchantRecoveryClaim::STATUS_COMPLETED,
                'target_tenant_id' => $tenant->id,
                'expires_at' => null,
                'completed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::error('Merchant recovery claim completion could not be recorded', [
                'archive_id' => $claim->archive_id,
                'recovered_tenant_id' => $tenant->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function markClaimFailed(?MerchantRecoveryClaim $claim, Throwable $exception): void
    {
        if (!$claim || $claim->isCompleted()) {
            return;
        }

        try {
            $claim->update([
                'status' => MerchantRecoveryClaim::STATUS_FAILED,
                'expires_at' => null,
            ]);
        } catch (Throwable $failure) {
            Log::error('Merchant recovery claim could not be marked as failed', [
                'archive_id' => $claim->archive_id,
                'error' => $failure->getMessage(),
            ]);
        }
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true)
            || str_contains(strtolower($exception->getMessage()), 'duplicate');
    }

    private function resolvePlan(Tenant $source): ?Plan
    {
        $active = $source->activeSubscription()->with('plan')->first();

        if ($active?->plan) {
            return $active->plan;
        }

        $latest = $source->subscription()->with('plan')->first();

        if ($latest?->plan) {
            return $latest->plan;
        }

        return $source->subscriptionPlan;
    }

    /**
     * @return array<string, int>
     */
    private function sourceSnapshot(int $tenantId): array
    {
        $tables = [
            'products',
            'product_variants',
            'product_combos',
            'categories',
            'brands',
            'units',
            'stock_movements',
            'orders',
            'order_items',
            'order_coupon',
            'order_override_logs',
            'customers',
            'customer_addresses',
            'wishlists',
            'coupons',
            'coupon_product',
            'coupon_category',
            'promotions',
            'promotion_product',
            'promotion_category',
            'promotion_usages',
            'promotion_banners',
            'flash_sales',
            'delivery_services',
            'delivery_pricing',
            'cod_rules',
            'packaging_options',
            'warehouses',
            'payment_methods',
            'cities',
            'townships',
            'storefronts',
            'storefront_contents',
            'storefront_design_tokens',
            'storefront_homepage_sections',
            'storefront_media',
            'storefront_navigations',
            'storefront_navigation_items',
            'storefront_revisions',
            'storefront_theme_configs',
            'storefront_checkout_configs',
            'storefront_product_display_configs',
            'website_faqs',
            'website_infos',
            'settings',
            'import_histories',
        ];

        $snapshot = [];

        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            if ($table === 'customers') {
                $snapshot[$table] = config('identity.use_accounts')
                    ? DB::table('tenant_memberships')->where('tenant_id', $tenantId)->count()
                    : DB::table('users')->where('tenant_id', $tenantId)->count();

                continue;
            }

            // Pivot and join tables (coupon_product, promotion_product,
            // promotion_usages, delivery_pricing, ...) carry no tenant_id of
            // their own, so counting them directly would abort the snapshot.
            if (!Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            $snapshot[$table] = DB::table($table)->where('tenant_id', $tenantId)->count();
        }

        return $snapshot;
    }

    /**
     * Durable duplicate detection: the claim table is authoritative, and the
     * tenant settings lookup covers archives recovered before claims existed.
     * The previous implementation hydrated every tenant's settings into PHP.
     */
    private function archiveAlreadyRecovered(string $archiveId, string $mode = 'new_store'): bool
    {
        if (MerchantRecoveryClaim::query()
            ->where('archive_id', $archiveId)
            ->where('mode', $mode)
            ->where('status', MerchantRecoveryClaim::STATUS_COMPLETED)
            ->exists()) {
            return true;
        }

        if ($mode === 'existing_store') {
            return Tenant::query()
                ->where('settings->restore_existing->archive_id', $archiveId)
                ->exists();
        }

        return Tenant::query()
            ->where('settings->recovery->archive_id', $archiveId)
            ->exists();
    }

    /**
     * Validate every plan limit that the archive will consume before any
     * business data is written. Usage is measured against a brand new tenant
     * (id 0) because recovery always targets a fresh store.
     */
    private function assertArchiveFitsPlan(string $archivePath, Plan $plan): void
    {
        $probe = new Tenant();
        $probe->id = 0;

        $limits = SubscriptionLimitService::for($probe, $plan);

        $required = [
            'product_limit' => $this->countArchiveRows($archivePath, 'products'),
            'coupon_limit' => $this->countArchiveRowsMatching(
                $archivePath,
                'promotions',
                fn (array $row): bool => !$this->csvBoolean($row['is_automatic'] ?? null)
                    && trim((string) ($row['code'] ?? '')) !== ''
            ),
            'promotion_limit' => $this->countArchiveRows($archivePath, 'promotions'),
            'flash_sale_limit' => $this->countArchiveRows($archivePath, 'flash_sales'),
        ];

        foreach ($required as $key => $count) {
            if ($count <= 0) {
                continue;
            }

            $limit = $limits->maximum($key);

            if ($limit === null) {
                continue;
            }

            $current = $limits->currentUsage($key);

            if ($current + $count <= $limit) {
                continue;
            }

            $label = SubscriptionLimitService::LIMIT_LABELS[$key] ?? $key;

            throw new SubscriptionLimitException(
                $key,
                $label,
                $current,
                $limit,
                $count,
                sprintf(
                    'This archive contains %d %s but the plan allows %d. Upgrade the plan before recovering.',
                    $count,
                    strtolower($label),
                    $limit
                )
            );
        }
    }

    private function countArchiveRowsMatching(string $archivePath, string $category, callable $matches): int
    {
        $zip = new ZipArchive();

        if ($zip->open($archivePath) !== true) {
            throw new MerchantRecoveryException('corrupt_archive', 'The archive could not be opened for recovery.');
        }

        try {
            $stream = $zip->getStream($category . '.csv');

            if ($stream === false) {
                return 0;
            }

            try {
                $header = fgetcsv($stream, 0, ',', '"', '\\');

                if (!is_array($header)) {
                    return 0;
                }

                $header[0] = ltrim((string) $header[0], "\xEF\xBB\xBF");

                $count = 0;

                while (($row = fgetcsv($stream, 0, ',', '"', '\\')) !== false) {
                    $data = array_combine($header, $row);

                    if ($data !== false && $matches($data)) {
                        $count++;
                    }
                }

                return $count;
            } finally {
                fclose($stream);
            }
        } finally {
            $zip->close();
        }
    }

    private function csvBoolean(?string $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    private function countArchiveRows(string $archivePath, string $category): int
    {
        $zip = new ZipArchive();

        if ($zip->open($archivePath) !== true) {
            throw new MerchantRecoveryException('corrupt_archive', 'The archive could not be opened for recovery.');
        }

        try {
            $stream = $zip->getStream($category . '.csv');

            if ($stream === false) {
                return 0;
            }

            $count = 0;

            try {
                if (fgetcsv($stream, 0, ',', '"', '\\') !== false) {
                    while (fgetcsv($stream, 0, ',', '"', '\\') !== false) {
                        $count++;
                    }
                }
            } finally {
                fclose($stream);
            }

            return $count;
        } finally {
            $zip->close();
        }
    }

    /**
     * @return list<string>
     */
    private function resultWarnings(User|Account|null $owner, bool $handoffRequired = false): array
    {
        $warnings = [
            'The recovered store is inactive (pending). Review it, reconnect media and activate it deliberately.',
            'Media binaries are not in the archive and were not recovered.',
            'Customer emails were rewritten with a recovery suffix to avoid colliding with the source store.',
            'Order records are restored with a placeholder customer link instead of the original customer.',
        ];

        if ($handoffRequired) {
            $warnings[] = 'The recovered owner cannot sign in yet. Ownership handoff is required: the platform operator must verify the owner identity before handing over access.';
        }

        return $warnings;
    }

    private function auditFailure(Tenant $source, User|Account $actor, string $reason, string $archiveId): void
    {
        $this->safeAudit(function () use ($source, $reason, $archiveId): void {
            ActivityLogger::log(
                'Merchant recovery failed',
                'merchant_recovery_failed',
                $source,
                [
                    'archive_id' => $archiveId,
                    'reason' => $reason,
                ],
                'backup',
                ActivityLog::CATEGORY_SYSTEM
            );
        });

        Log::warning('Merchant recovery rejected', [
            'tenant_id' => $source->id,
            'actor_id' => $actor->getAuthIdentifier(),
            'archive_id' => $archiveId,
            'reason' => $reason,
        ]);
    }

    /**
     * Audit rows are a secondary record: the authoritative outcome lives in the
     * claim table and the recovered tenant settings. A failed audit insert must
     * never mask the real recovery result, but it must be visible in the log.
     */
    private function safeAudit(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            Log::error('Merchant recovery audit write failed', [
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
