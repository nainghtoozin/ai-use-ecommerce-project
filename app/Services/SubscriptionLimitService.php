<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionBanner;
use App\Models\Tenant;

class SubscriptionLimitService
{
    /** Map of limit keys to human-readable labels */
    public const LIMIT_LABELS = [
        'product_limit' => 'Products',
        'staff_limit' => 'Staff Accounts',
        'storage_limit' => 'Storage',
        'orders_monthly_limit' => 'Monthly Orders',
        'coupon_limit' => 'Coupons',
        'promotion_limit' => 'Promotions',
        'flash_sale_limit' => 'Flash Sales',
        'api_request_limit' => 'API Requests',
        'image_limit' => 'Images per Product',
        'image_max_size_kb' => 'Max Image Size',
        'branch_limit' => 'Branches',
        'warehouse_limit' => 'Warehouses',
        'pos_device_limit' => 'POS Devices',
    ];

    public function __construct(
        private readonly ?Tenant $tenant = null,
        private readonly ?Plan $plan = null,
    ) {}

    public static function for(?Tenant $tenant = null, ?Plan $plan = null): static
    {
        return new static($tenant, $plan);
    }

    private function resolveTenant(): ?Tenant
    {
        if ($this->tenant) {
            return $this->tenant;
        }

        $user = auth()->user();
        return $user?->tenant;
    }

    private function resolvePlan(): ?Plan
    {
        if ($this->plan) {
            return $this->plan;
        }

        $tenant = $this->resolveTenant();
        return $tenant?->subscription?->plan;
    }

    /* ── Unified Limit API ── */

    /**
     * Get the plan's maximum value for a given limit key.
     * Returns null for unlimited, 0 for disabled.
     */
    public function maximum(string $key): ?int
    {
        $plan = $this->resolvePlan();
        if (!$plan) {
            return 0;
        }

        return match ($key) {
            'product_limit' => $plan->productLimit(),
            'staff_limit' => $plan->staffLimit(),
            'storage_limit' => $plan->storageLimitMb(),
            'orders_monthly_limit' => $plan->limitValue('orders_monthly_limit'),
            'coupon_limit' => $plan->limitValue('coupon_limit'),
            'promotion_limit' => $plan->limitValue('promotion_limit'),
            'flash_sale_limit' => $plan->limitValue('flash_sale_limit'),
            'api_request_limit' => $plan?->api_request_limit,
            'image_limit' => $plan?->image_limit,
            'image_max_size_kb' => $plan?->image_max_size_kb,
            'branch_limit' => $plan->limitValue('branch_limit'),
            'warehouse_limit' => $plan->limitValue('warehouse_limit'),
            'pos_device_limit' => $plan->limitValue('pos_device_limit'),
            default => null,
        };
    }

    /**
     * Get the current usage count for a given limit key.
     */
    public function currentUsage(string $key): int
    {
        $plan = $this->resolvePlan();
        return match ($key) {
            'product_limit' => $this->productCount(),
            'staff_limit' => $this->staffCount(),
            'storage_limit' => (int) ceil($this->storageUsedBytes() / (1024 * 1024)),
            'orders_monthly_limit' => $this->ordersMonthlyCount(),
            'coupon_limit' => $this->couponCount(),
            'promotion_limit' => $this->promotionCount(),
            'flash_sale_limit' => $this->flashSaleCount(),
            'api_request_limit' => 0,     // Not tracked yet
            'image_limit' => 0,           // Not tracked yet
            'image_max_size_kb' => $plan?->image_max_size_kb ?? 0,  // Max size cap, not usage
            'branch_limit' => $this->branchCount(),
            'warehouse_limit' => $this->warehouseCount(),
            'pos_device_limit' => $this->posDeviceCount(),
            default => 0,
        };
    }

    /**
     * Get remaining count for a given limit key.
     * Returns PHP_INT_MAX for unlimited.
     */
    public function remaining(string $key): int
    {
        $limit = $this->maximum($key);
        if ($limit === null) {
            return PHP_INT_MAX;
        }
        return max(0, $limit - $this->currentUsage($key));
    }

    /**
     * Check if the limit allows creating one more entity.
     */
    public function checkLimit(string $key): bool
    {
        $plan = $this->resolvePlan();
        if (!$plan) {
            return false;
        }

        $limit = $this->maximum($key);
        if ($limit === null) {
            return true;
        }

        return $this->currentUsage($key) < $limit;
    }

    /**
     * Assert that the limit allows creating one more entity.
     * Throws RuntimeException with upgrade message on failure.
     */
    public function assertCanCreate(string $key): void
    {
        if ($this->checkLimit($key)) {
            return;
        }

        $current = $this->currentUsage($key);
        $limit = $this->maximum($key) ?? 0;
        $label = self::LIMIT_LABELS[$key] ?? $key;

        throw new \RuntimeException(
            "{$label} limit reached. You have {$current} of {$limit}. " .
            "Please upgrade your plan to increase this limit."
        );
    }

    /**
     * Get full usage data for a given limit key (for frontend display).
     */
    public function getUsage(string $key): array
    {
        $current = $this->currentUsage($key);
        $limit = $this->maximum($key);
        $isUnlimited = $limit === null;

        return [
            'current' => $current,
            'limit' => $limit,
            'remaining' => $isUnlimited ? PHP_INT_MAX : max(0, $limit - $current),
            'is_unlimited' => $isUnlimited,
            'percent' => ($limit !== null && $limit > 0)
                ? round(($current / $limit) * 100)
                : 0,
        ];
    }

    /**
     * Get all limits and their usage data.
     */
    public function getAllLimits(): array
    {
        $keys = [
            'product_limit',
            'staff_limit',
            'storage_limit',
            'orders_monthly_limit',
            'coupon_limit',
            'promotion_limit',
            'flash_sale_limit',
            'branch_limit',
            'warehouse_limit',
            'pos_device_limit',
        ];

        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->getUsage($key);
        }
        return $result;
    }

    /* ── Legacy usage counts ── */

    public function productCount(): int
    {
        $tenant = $this->resolveTenant();
        if (!$tenant) {
            return 0;
        }
        return Product::withoutTenantScope()->where('tenant_id', $tenant->id)->count();
    }

    public function staffCount(): int
    {
        $tenant = $this->resolveTenant();
        if (!$tenant) {
            return 0;
        }
        return $tenant->users()
            ->whereHas('roles', fn($q) => $q->where('name', 'admin'))
            ->count();
    }

    public function storageUsedBytes(): int
    {
        $tenant = $this->resolveTenant();
        if (!$tenant) {
            return 0;
        }
        return (int) ($tenant->used_storage_bytes ?? 0);
    }

    public function ordersMonthlyCount(): int
    {
        $tenant = $this->resolveTenant();
        if (!$tenant) {
            return 0;
        }
        return Order::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
    }

    public function couponCount(): int
    {
        $tenant = $this->resolveTenant();
        if (!$tenant) {
            return 0;
        }
        return Coupon::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->count();
    }

    public function promotionCount(): int
    {
        $tenant = $this->resolveTenant();
        if (!$tenant) {
            return 0;
        }
        return Promotion::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->count();
    }

    public function flashSaleCount(): int
    {
        $tenant = $this->resolveTenant();
        if (!$tenant) {
            return 0;
        }
        return PromotionBanner::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->count();
    }

    /**
     * Branch model not yet implemented — returns 0.
     */
    public function branchCount(): int
    {
        return 0;
    }

    /**
     * Warehouse model not yet implemented — returns 0.
     */
    public function warehouseCount(): int
    {
        return 0;
    }

    /**
     * POS Device model not yet implemented — returns 0.
     */
    public function posDeviceCount(): int
    {
        return 0;
    }

    /* ── Legacy derived checks ── */

    public function productRemaining(): int
    {
        $plan = $this->resolvePlan();
        if (!$plan || $plan->hasUnlimitedProducts()) {
            return PHP_INT_MAX;
        }
        return max(0, $plan->productLimit() - $this->productCount());
    }

    public function staffRemaining(): int
    {
        $plan = $this->resolvePlan();
        if (!$plan || $plan->hasUnlimitedStaff()) {
            return PHP_INT_MAX;
        }
        return max(0, $plan->staffLimit() - $this->staffCount());
    }

    public function storageRemainingBytes(): int
    {
        $plan = $this->resolvePlan();
        if (!$plan || $plan->storageLimitMb() === null) {
            return PHP_INT_MAX;
        }
        $limitBytes = $plan->storageLimitMb() * 1024 * 1024;
        return max(0, $limitBytes - $this->storageUsedBytes());
    }

    /* ── Legacy boolean checks ── */

    public function canCreateProduct(): bool
    {
        $plan = $this->resolvePlan();
        if (!$plan || $plan->hasUnlimitedProducts()) {
            return true;
        }
        return $this->productCount() < $plan->productLimit();
    }

    public function canCreateStaff(): bool
    {
        $plan = $this->resolvePlan();
        if (!$plan || $plan->hasUnlimitedStaff()) {
            return true;
        }
        return $this->staffCount() < $plan->staffLimit();
    }

    public function canUpload(int $fileSizeBytes): bool
    {
        $plan = $this->resolvePlan();
        if (!$plan || $plan->storageLimitMb() === null) {
            return true;
        }
        return $this->storageRemainingBytes() >= $fileSizeBytes;
    }

    /* ── Legacy assertions ── */

    public function assertCanCreateProduct(): void
    {
        if ($this->canCreateProduct()) {
            return;
        }

        $plan = $this->resolvePlan();
        $current = $this->productCount();
        $limit = $plan?->productLimit() ?? 0;

        throw new \RuntimeException(
            "Product limit reached. You have {$current} of {$limit} products. " .
            "Please upgrade your plan to add more products."
        );
    }

    public function assertCanCreateStaff(): void
    {
        if ($this->canCreateStaff()) {
            return;
        }

        $plan = $this->resolvePlan();
        $current = $this->staffCount();
        $limit = $plan?->staffLimit() ?? 0;

        throw new \RuntimeException(
            "Staff limit reached. You have {$current} of {$limit} staff accounts. " .
            "Please upgrade your plan to add more staff."
        );
    }

    public function assertCanUpload(int $fileSizeBytes): void
    {
        if ($this->canUpload($fileSizeBytes)) {
            return;
        }

        $plan = $this->resolvePlan();
        $remaining = $this->storageRemainingBytes();
        $limitMb = $plan?->storageLimitMb() ?? 0;

        $remainingFormatted = $this->formatBytes($remaining);

        throw new \RuntimeException(
            "Storage limit reached. You have {$remainingFormatted} remaining of {$limitMb} MB. " .
            "Please upgrade your plan or free up space to upload more files."
        );
    }

    /* ── Legacy usage data for frontend ── */

    public function getProductUsage(): array
    {
        $plan = $this->resolvePlan();
        return [
            'current' => $this->productCount(),
            'limit' => $plan?->productLimit(),
            'remaining' => $this->productRemaining(),
            'is_unlimited' => $plan?->hasUnlimitedProducts() ?? false,
            'percent' => $plan && $plan->productLimit() > 0
                ? round(($this->productCount() / $plan->productLimit()) * 100)
                : 0,
        ];
    }

    public function getStaffUsage(): array
    {
        $plan = $this->resolvePlan();
        return [
            'current' => $this->staffCount(),
            'limit' => $plan?->staffLimit(),
            'remaining' => $this->staffRemaining(),
            'is_unlimited' => $plan?->hasUnlimitedStaff() ?? false,
            'percent' => $plan && $plan->staffLimit() > 0
                ? round(($this->staffCount() / $plan->staffLimit()) * 100)
                : 0,
        ];
    }

    public function getStorageUsage(): array
    {
        $plan = $this->resolvePlan();
        $usedBytes = $this->storageUsedBytes();
        $limitMb = $plan?->storageLimitMb();

        if ($limitMb === null) {
            return [
                'current' => $this->formatBytes($usedBytes),
                'current_bytes' => $usedBytes,
                'limit' => null,
                'limit_mb' => null,
                'remaining' => 'Unlimited',
                'is_unlimited' => true,
                'percent' => 0,
            ];
        }

        $limitBytes = $limitMb * 1024 * 1024;
        return [
            'current' => $this->formatBytes($usedBytes),
            'current_bytes' => $usedBytes,
            'limit' => $this->formatBytes($limitBytes),
            'limit_mb' => $limitMb,
            'remaining' => $this->formatBytes($this->storageRemainingBytes()),
            'is_unlimited' => false,
            'percent' => $limitBytes > 0
                ? round(($usedBytes / $limitBytes) * 100)
                : 0,
        ];
    }

    public function getAllUsage(): array
    {
        return [
            'products' => $this->getProductUsage(),
            'staff' => $this->getStaffUsage(),
            'storage' => $this->getStorageUsage(),
        ];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 1) . ' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }
}
