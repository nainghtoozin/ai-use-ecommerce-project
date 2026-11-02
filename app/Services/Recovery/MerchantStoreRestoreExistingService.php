<?php

namespace App\Services\Recovery;

use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\CodRule;
use App\Models\Coupon;
use App\Models\CustomerAddress;
use App\Models\DeliveryPricing;
use App\Models\DeliveryService;
use App\Models\FlashSale;
use App\Models\FlashSaleProduct;
use App\Models\ImportHistory;
use App\Models\Inventory;
use App\Models\MerchantRecoveryClaim;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderCoupon;
use App\Models\OrderItem;
use App\Models\OrderOverrideLog;
use App\Models\PackagingOption;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductCombo;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\PromotionBanner;
use App\Models\PromotionCategory;
use App\Models\PromotionProduct;
use App\Models\PromotionUsage;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\Township;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WebsiteFaq;
use App\Models\Wishlist;
use App\Services\ActivityLogger;
use App\Services\Export\MerchantBackupSigner;
use App\Services\Export\MerchantBackupVerifier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Throwable;
use ZipArchive;

final class MerchantStoreRestoreExistingService
{
    public function __construct(
        private readonly MerchantBackupVerifier $verifier,
        private readonly MerchantRecoveryImporter $importer
    ) {}

    public function restoreExisting(
        Tenant $tenant,
        string $archivePath,
        string $archiveId,
        string $confirmation,
        User|Account $actor,
        bool $finalConfirmation = false,
        ?string $secondConfirmation = null,
        bool $final = false
    ): array {
        return $this->restore(
            $tenant,
            $archivePath,
            $archiveId,
            $confirmation,
            $actor,
            $finalConfirmation || $final || ($secondConfirmation !== null)
        );
    }

    public function restore(
        Tenant $tenant,
        string $archivePath,
        string $archiveId,
        string $confirmation,
        User|Account $actor,
        bool $finalConfirmation = false
    ): array {
        return $this->restoreToExistingStore(
            $tenant,
            $archivePath,
            $archiveId,
            $confirmation,
            $actor,
            $finalConfirmation
        );
    }

    public function restoreToExistingStore(
        Tenant $tenant,
        string $archivePath,
        string $archiveId,
        string $confirmation,
        User|Account $actor,
        bool $finalConfirmation = false
    ): array {
        $lockSeconds = max(30, (int) config('exports.backup.recovery_lock_seconds', 300));
        $lock = Cache::lock('merchant-restore-existing:' . $tenant->id, $lockSeconds);

        if (!$lock->get()) {
            $this->auditFailure($tenant, $actor, 'restore_in_progress', $archiveId);

            throw new MerchantRecoveryException('restore_in_progress', 'Another restore attempt is already running for this store.');
        }

        $correlationId = (string) Str::uuid();

        try {
            return $this->attempt($tenant, $archivePath, $archiveId, $confirmation, $actor, $finalConfirmation, $correlationId);
        } finally {
            if ($lock->isOwnedByCurrentProcess()) {
                $lock->release();
            }
        }
    }

    private function attempt(
        Tenant $tenant,
        string $archivePath,
        string $archiveId,
        string $confirmation,
        User|Account $actor,
        bool $finalConfirmation,
        string $correlationId
    ): array {
        $confirmation = trim($confirmation);

        if ($confirmation !== (string) $tenant->name && $confirmation !== (string) $tenant->slug) {
            $this->auditFailure($tenant, $actor, 'confirmation_mismatch', $archiveId);

            throw new MerchantRecoveryException('confirmation_mismatch', 'Type the exact store name or slug to confirm the restore.');
        }

        if (!$finalConfirmation) {
            $this->auditFailure($tenant, $actor, 'final_confirmation_required', $archiveId);

            throw new MerchantRecoveryException('final_confirmation_required', 'Final confirmation is required.');
        }

        if (!is_file($archivePath)) {
            $this->auditFailure($tenant, $actor, 'archive_missing', $archiveId);

            throw new MerchantRecoveryException('archive_missing', 'The retained upload is no longer available. Verify the archive again.');
        }

        $ttlSeconds = max(300, (int) config('exports.backup.upload_ttl_hours', 1) * 3600);
        $modified = filemtime($archivePath);

        if ($modified === false || (time() - $modified) > $ttlSeconds) {
            $this->auditFailure($tenant, $actor, 'archive_expired', $archiveId);

            throw new MerchantRecoveryException('archive_expired', 'The retained upload has expired. Verify the archive again.');
        }

        try {
            $verified = $this->verifier->verify($archivePath, $tenant, MerchantBackupSigner::fromConfig());
        } catch (Throwable $exception) {
            $this->auditFailure($tenant, $actor, 'reverification_failed', $archiveId);

            throw new MerchantRecoveryException('reverification_failed', $exception->getMessage(), $exception);
        }

        if ((string) ($verified['manifest']['archive_id'] ?? '') !== $archiveId) {
            $this->auditFailure($tenant, $actor, 'archive_mismatch', $archiveId);

            throw new MerchantRecoveryException('archive_mismatch', 'The retained upload does not match the verified archive. Verify the archive again.');
        }

        $manifest = $verified['manifest'];

        $sourceTenantId = $manifest['tenant']['id'] ?? null;
        $sourceTenantSlug = $manifest['tenant']['slug'] ?? null;
        $sourceStoreSlug = $manifest['store']['slug'] ?? null;

        if ((int) $sourceTenantId !== (int) $tenant->id) {
            $this->auditFailure($tenant, $actor, 'tenant_mismatch', $archiveId);

            throw new MerchantRecoveryException('tenant_mismatch', 'This backup belongs to a different store and cannot be restored here.');
        }

        if (!is_string($sourceTenantSlug) || $sourceTenantSlug !== (string) $tenant->slug) {
            $this->auditFailure($tenant, $actor, 'tenant_mismatch', $archiveId);

            throw new MerchantRecoveryException('tenant_mismatch', 'This backup belongs to a different store and cannot be restored here.');
        }

        if (!is_string($sourceStoreSlug) || $sourceStoreSlug !== (string) $tenant->slug) {
            $this->auditFailure($tenant, $actor, 'tenant_mismatch', $archiveId);

            throw new MerchantRecoveryException('tenant_mismatch', 'This backup belongs to a different store and cannot be restored here.');
        }

        if ($this->archiveAlreadyRestored($archiveId)) {
            $this->auditFailure($tenant, $actor, 'duplicate_restore', $archiveId);

            throw new MerchantRecoveryException('duplicate_restore', 'This archive has already been restored. Duplicate restore is blocked.');
        }

        $claim = $this->acquireClaim($tenant, $archiveId, $archivePath, $actor);

        $tenantSnapshot = $this->tenantSnapshot($tenant);

        $this->safeAudit(function () use ($tenant, $archiveId): void {
            ActivityLogger::log(
                'Merchant restore started',
                'merchant_restore_started',
                $tenant,
                [
                    'archive_id' => $archiveId,
                    'ip' => request()->ip(),
                ],
                'backup',
                ActivityLog::CATEGORY_SYSTEM
            );
        });

        try {
            $result = DB::transaction(function () use ($tenant, $archivePath, $archiveId, $verified, $tenantSnapshot): array {
                // Delete approved business datasets using the reset allowlist.
                $this->deleteBusinessData($tenant);

                // Settings policy: remove restorable allowlisted settings before import.
                $this->deleteRestorableSettings($tenant);

                // Replace storefront data (unique tenant_id on storefronts).
                $this->deleteStorefrontData($tenant);

                // Replace store profile row with restored data.
                DB::table('website_infos')->where('tenant_id', $tenant->id)->delete();

                $imported = $this->importer->import($archivePath, $tenant->id, $verified['manifest'], true);
                $checks = $this->importer->verify($verified['manifest']);

                if ($this->tenantSnapshot($tenant) !== $tenantSnapshot) {
                    throw new MerchantRecoveryException('identity_changed', 'Restore attempted to modify protected tenant identity. The attempt was rolled back.');
                }

                $settings = $tenant->settings ?? [];
                $settings['restore_existing'] = [
                    'archive_id' => $archiveId,
                    'completed_at' => now()->toIso8601String(),
                    'counts' => $checks['verified_counts'],
                ];
                $tenant->update(['settings' => $settings]);

                return [
                    'tenant' => $tenant->fresh(),
                    'checks' => $checks,
                    'imported' => $imported,
                ];
            }, 3);
        } catch (Throwable $exception) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $this->markClaimFailed($claim, $exception);

            $reason = $exception instanceof MerchantRecoveryException ? $exception->reason() : 'restore_failed';

            $this->auditFailure($tenant, $actor, $reason, $archiveId);

            $this->safeAudit(function () use ($tenant, $archiveId, $reason): void {
                ActivityLogger::log(
                    'Merchant restore rolled back',
                    'merchant_restore_rolled_back',
                    $tenant,
                    [
                        'archive_id' => $archiveId,
                        'reason' => $reason,
                    ],
                    'backup',
                    ActivityLog::CATEGORY_SYSTEM
                );
            });

            Log::error('Merchant restore failed', [
                'correlation_id' => $correlationId,
                'tenant_id' => $tenant->id,
                'archive_id' => $archiveId,
                'reason' => $reason,
                'exception_class' => get_class($exception),
                'exception_message' => $exception->getMessage(),
            ]);

            if ($exception instanceof MerchantRecoveryException) {
                throw $exception;
            }

            throw new MerchantRecoveryException('restore_failed', 'Restore failed and every change was rolled back.', $exception);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $restoredTenant = $result['tenant'];

        $this->completeClaim($claim, $restoredTenant);

        $this->verifier->forgetUpload($archivePath);

        $this->safeAudit(function () use ($tenant, $archiveId, $result): void {
            ActivityLogger::log(
                'Merchant restore completed',
                'merchant_restore_completed',
                $tenant,
                [
                    'archive_id' => $archiveId,
                    'counts' => $result['checks']['verified_counts'],
                ],
                'backup',
                ActivityLog::CATEGORY_SYSTEM
            );
        });

        return [
            'mode' => 'existing_store',
            'tenant_id' => $restoredTenant->id,
            'tenant_name' => $restoredTenant->name,
            'tenant_slug' => $restoredTenant->slug,
            'tenant_domain' => $restoredTenant->domain,
            'status' => $restoredTenant->status,
            'subscription_status' => $restoredTenant->subscription?->status,
            'expiry_date' => $restoredTenant->subscription?->expires_at?->toDateString(),
            'plan_name' => $restoredTenant->subscription?->plan?->name ?? 'No plan assigned',
            'archive_id' => $archiveId,
            'counts' => $result['checks']['verified_counts'],
            'guest_customers' => (int) ($result['checks']['guest_customers'] ?? 0),
            'warnings' => [],
        ];
    }

    private function deleteBusinessData(Tenant $tenant): void
    {
        $id = $tenant->id;

        $counts = [];
        $counts['order_override_logs'] = OrderOverrideLog::withoutTenantScope()->where('tenant_id', $id)->delete();
        $counts['order_coupons'] = OrderCoupon::withoutTenantScope()->where('tenant_id', $id)->delete();
        $counts['order_items'] = OrderItem::withoutTenantScope()->where('tenant_id', $id)->delete();
        $counts['orders'] = Order::withoutTenantScope()->where('tenant_id', $id)->delete();

        $flashSaleIds = FlashSale::withoutTenantScope()->where('tenant_id', $id)->pluck('id');
        FlashSaleProduct::whereIn('flash_sale_id', $flashSaleIds)->delete();
        $counts['flash_sales'] = FlashSale::withoutTenantScope()->where('tenant_id', $id)->delete();

        $counts['promotion_usages'] = PromotionUsage::withoutTenantScope()->where('tenant_id', $id)->delete();
        $counts['promotion_banners'] = PromotionBanner::withoutTenantScope()->where('tenant_id', $id)->delete();
        DB::table('promotion_product')->where('tenant_id', $id)->delete();
        DB::table('promotion_category')->where('tenant_id', $id)->delete();
        $counts['promotions'] = Promotion::withoutTenantScope()->where('tenant_id', $id)->delete();

        DB::table('coupon_product')->where('tenant_id', $id)->delete();
        DB::table('coupon_category')->where('tenant_id', $id)->delete();
        $counts['coupons'] = Coupon::withoutTenantScope()->where('tenant_id', $id)->delete();

        $counts['wishlists'] = Wishlist::withoutTenantScope()->where('tenant_id', $id)->delete();
        $counts['customer_addresses'] = CustomerAddress::withoutTenantScope()->where('tenant_id', $id)->delete();
        $counts['messages'] = Message::withoutTenantScope()->where('tenant_id', $id)->delete();

        $counts['product_combos'] = ProductCombo::withoutTenantScope()->where('tenant_id', $id)->delete();
        $counts['product_variants'] = ProductVariant::withoutTenantScope()->where('tenant_id', $id)->delete();
        $counts['stock_movements'] = StockMovement::withoutTenantScope()->where('tenant_id', $id)->delete();
        $counts['inventories'] = Inventory::withoutTenantScope()->where('tenant_id', $id)->delete();
        $counts['products'] = Product::withoutTenantScope()->where('tenant_id', $id)->delete();

        Category::withoutTenantScope()->where('tenant_id', $id)->update(['parent_id' => null]);
        $counts['categories'] = Category::withoutTenantScope()->where('tenant_id', $id)->delete();
        $counts['brands'] = Brand::withoutTenantScope()->where('tenant_id', $id)->delete();
        $counts['units'] = Unit::withoutTenantScope()->where('tenant_id', $id)->delete();

        $deliveryServiceIds = DeliveryService::withoutTenantScope()->where('tenant_id', $id)->pluck('id');
        DeliveryPricing::whereIn('delivery_service_id', $deliveryServiceIds)->delete();
        $counts['delivery_services'] = DeliveryService::withoutTenantScope()->where('tenant_id', $id)->delete();
        $counts['cod_rules'] = CodRule::withoutTenantScope()->where('tenant_id', $id)->delete();
        $counts['packaging_options'] = PackagingOption::withoutTenantScope()->where('tenant_id', $id)->delete();
        $counts['warehouses'] = Warehouse::withoutTenantScope()->where('tenant_id', $id)->delete();
        $counts['payment_methods'] = PaymentMethod::withoutTenantScope()->where('tenant_id', $id)->delete();
        $counts['townships'] = Township::withoutTenantScope()->where('tenant_id', $id)->delete();
        $counts['cities'] = City::withoutTenantScope()->where('tenant_id', $id)->delete();

        $counts['website_faqs'] = WebsiteFaq::withoutTenantScope()->where('tenant_id', $id)->delete();
        $counts['import_histories'] = ImportHistory::withoutTenantScope()->where('tenant_id', $id)->delete();
    }

    private function deleteRestorableSettings(Tenant $tenant): void
    {
        $allowlist = (array) config('exports.backup.setting_key_allowlist', []);

        $keys = DB::table('settings')->where('tenant_id', $tenant->id)->pluck('key');

        foreach ($keys as $key) {
            foreach ($allowlist as $allowed) {
                if ($key === $allowed || str_starts_with($key, rtrim($allowed, '.') . '.') || str_starts_with($key, rtrim($allowed, '_') . '_')) {
                    DB::table('settings')->where('tenant_id', $tenant->id)->where('key', $key)->delete();
                    break;
                }
            }
        }
    }

    private function deleteStorefrontData(Tenant $tenant): void
    {
        $id = $tenant->id;

        DB::table('storefront_navigation_items')->where('tenant_id', $id)->delete();
        DB::table('storefront_navigations')->where('tenant_id', $id)->delete();
        DB::table('storefront_contents')->where('tenant_id', $id)->delete();
        DB::table('storefront_design_tokens')->where('tenant_id', $id)->delete();
        DB::table('storefront_homepage_sections')->where('tenant_id', $id)->delete();
        DB::table('storefront_media')->where('tenant_id', $id)->delete();
        DB::table('storefront_revisions')->where('tenant_id', $id)->delete();
        DB::table('storefront_theme_configs')->where('tenant_id', $id)->delete();
        DB::table('storefront_checkout_configs')->where('tenant_id', $id)->delete();
        DB::table('storefront_product_display_configs')->where('tenant_id', $id)->delete();
        DB::table('storefronts')->where('tenant_id', $id)->delete();
    }

    private function tenantSnapshot(Tenant $tenant): array
    {
        $tenant->refresh();

        return [
            'id' => $tenant->id,
            'slug' => $tenant->slug,
            'name' => $tenant->name,
            'status' => $tenant->status,
            'subscription_plan_id' => $tenant->subscription_plan_id,
            'locked_at' => (string) $tenant->locked_at,
        ];
    }

    private function archiveAlreadyRestored(string $archiveId): bool
    {
        if (MerchantRecoveryClaim::query()
            ->where('archive_id', $archiveId)
            ->where('mode', 'existing_store')
            ->where('status', MerchantRecoveryClaim::STATUS_COMPLETED)
            ->exists()) {
            return true;
        }

        return Tenant::query()
            ->where('settings->restore_existing->archive_id', $archiveId)
            ->exists();
    }

    private function acquireClaim(Tenant $tenant, string $archiveId, string $archivePath, User|Account $actor): MerchantRecoveryClaim
    {
        $leaseMinutes = max(5, (int) config('exports.backup.recovery_claim_lease_minutes', 120));
        $expiresAt = now()->addMinutes($leaseMinutes);

        try {
            return MerchantRecoveryClaim::create([
                'archive_id' => $archiveId,
                'mode' => 'existing_store',
                'source_tenant_id' => $tenant->id,
                'target_slug' => $tenant->slug,
                'archive_name' => basename($archivePath),
                'actor_type' => get_class($actor),
                'actor_id' => $actor->getAuthIdentifier(),
                'status' => MerchantRecoveryClaim::STATUS_CLAIMED,
                'expires_at' => $expiresAt,
            ]);
        } catch (\Illuminate\Database\QueryException $exception) {
            if (!$this->isUniqueViolation($exception)) {
                throw $exception;
            }
        }

        $existing = MerchantRecoveryClaim::where('archive_id', $archiveId)
            ->where('mode', 'existing_store')
            ->first();

        if ($existing?->isCompleted()) {
            throw new MerchantRecoveryException('duplicate_restore', 'This archive has already been restored. Duplicate restore is blocked.');
        }

        if (!$existing) {
            throw new MerchantRecoveryException('restore_in_progress', 'Another restore attempt is already running for this archive.');
        }

        $reclaimable = $existing->status === MerchantRecoveryClaim::STATUS_FAILED
            || ($existing->status === MerchantRecoveryClaim::STATUS_CLAIMED && !$existing->isActive());

        if (!$reclaimable) {
            throw new MerchantRecoveryException('restore_in_progress', 'Another restore attempt is already running for this archive.');
        }

        $reclaimed = MerchantRecoveryClaim::query()
            ->whereKey($existing->id)
            ->whereIn('status', [MerchantRecoveryClaim::STATUS_CLAIMED, MerchantRecoveryClaim::STATUS_FAILED])
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '<=', now());
            })
            ->update([
                'target_slug' => $tenant->slug,
                'target_tenant_id' => $tenant->id,
                'archive_name' => basename($archivePath),
                'actor_type' => get_class($actor),
                'actor_id' => $actor->getAuthIdentifier(),
                'status' => MerchantRecoveryClaim::STATUS_CLAIMED,
                'expires_at' => $expiresAt,
                'completed_at' => null,
                'updated_at' => now(),
            ]);

        if ($reclaimed !== 1) {
            throw new MerchantRecoveryException('restore_in_progress', 'Another restore attempt is already running for this archive.');
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
            Log::error('Merchant restore claim completion could not be recorded', [
                'archive_id' => $claim->archive_id,
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
            Log::error('Merchant restore claim could not be marked as failed', [
                'archive_id' => $claim->archive_id,
                'error' => $failure->getMessage(),
            ]);
        }
    }

    private function isUniqueViolation(\Illuminate\Database\QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true)
            || str_contains(strtolower($exception->getMessage()), 'duplicate');
    }

    private function auditFailure(Tenant $tenant, User|Account $actor, string $reason, string $archiveId): void
    {
        $this->safeAudit(function () use ($tenant, $reason, $archiveId): void {
            ActivityLogger::log(
                'Merchant restore failed',
                'merchant_restore_failed',
                $tenant,
                [
                    'archive_id' => $archiveId,
                    'reason' => $reason,
                ],
                'backup',
                ActivityLog::CATEGORY_SYSTEM
            );
        });

        Log::warning('Merchant restore rejected', [
            'tenant_id' => $tenant->id,
            'actor_id' => $actor->getAuthIdentifier(),
            'archive_id' => $archiveId,
            'reason' => $reason,
        ]);
    }

    private function safeAudit(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            Log::error('Merchant restore audit write failed', [
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
