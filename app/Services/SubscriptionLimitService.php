<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Product;
use App\Models\Tenant;

class SubscriptionLimitService
{
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

    /* ── Usage counts ── */

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

    /* ── Derived checks ── */

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

    /* ── Boolean checks ── */

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

    /* ── Assertions (throw on failure with upgrade message) ── */

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

    /* ── Upgrade prompts (usage data for frontend) ── */

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
