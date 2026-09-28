<?php

namespace App\Services;

use App\Models\Account;
use App\Models\CodRule;
use App\Models\PaymentMethod;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebsiteInfo;
use Illuminate\Support\Collection;

class CodEligibilityService
{
    public const COD_MODE_ALL = 'all';
    public const COD_MODE_RULES = 'rules';
    public function isCodGloballyEnabled(?Tenant $tenant = null): bool
    {
        $tenant ??= Tenant::getCurrent();

        $value = WebsiteInfo::withoutTenantScope()
            ->where('tenant_id', $tenant?->id)
            ->value('cod_enabled');

        return $value === null ? true : (bool) $value;
    }

    public function isCodAvailable(
        PaymentMethod $paymentMethod,
        User|Account|null $user = null,
        ?int $cityId = null,
        ?float $orderAmount = null
    ): bool {
        if ($paymentMethod->type !== 'cod') {
            return false;
        }

        if (!$this->isCodGloballyEnabled()) {
            return false;
        }

        if ($this->getCodAvailabilityMode() === self::COD_MODE_ALL) {
            return true;
        }

        if ($orderAmount === null) {
            return true;
        }

        $activeRules = $this->getActiveRules();

        if ($activeRules->isEmpty()) {
            return false;
        }

        foreach ($activeRules as $rule) {
            if ($rule->isEligible($orderAmount, $cityId)) {
                return true;
            }
        }

        return false;
    }

    public function getEligibleRules(
        ?int $cityId = null,
        ?float $orderAmount = null
    ): Collection {
        $rules = $this->getActiveRules();

        return $rules->filter(function ($rule) use ($cityId, $orderAmount) {
            if ($orderAmount !== null && !$rule->isAmountEligible($orderAmount)) {
                return false;
            }

            if ($cityId !== null && !$rule->isCityEligible($cityId)) {
                return false;
            }

            return true;
        });
    }

    public function getCodAvailabilityMode(?Tenant $tenant = null): string
    {
        $tenant ??= Tenant::getCurrent();
        $mode = Setting::get('cod_availability_mode', self::COD_MODE_RULES, $tenant?->id);

        return $mode === self::COD_MODE_ALL ? self::COD_MODE_ALL : self::COD_MODE_RULES;
    }

    public function getIneligibilityReason(
        PaymentMethod $paymentMethod,
        User|Account|null $user = null,
        ?int $cityId = null,
        ?float $orderAmount = null
    ): ?string {
        if ($paymentMethod->type !== 'cod') {
            return 'Selected payment method is not COD.';
        }

        if (!$this->isCodGloballyEnabled()) {
            return 'Cash on Delivery is currently unavailable.';
        }

        if ($this->getCodAvailabilityMode() === self::COD_MODE_ALL) {
            return null;
        }

        if ($orderAmount === null) {
            return null;
        }

        $activeRules = $this->getActiveRules();

        if ($activeRules->isEmpty()) {
            return 'Cash on Delivery is currently unavailable.';
        }

        $amountFailed = false;
        $cityFailed = false;

        foreach ($activeRules as $rule) {
            if (!$rule->isAmountEligible($orderAmount)) {
                $amountFailed = true;
            }
            if (!$rule->isCityEligible($cityId)) {
                $cityFailed = true;
            }
        }

        $allFailed = $activeRules->every(function ($rule) use ($orderAmount, $cityId) {
            return !$rule->isEligible($orderAmount, $cityId);
        });

        if ($allFailed) {
            if ($amountFailed && $cityFailed) {
                return 'Cash on Delivery is not available for this order amount or delivery location.';
            }
            if ($amountFailed) {
                return 'Cash on Delivery is not available for this order amount.';
            }
            return 'Cash on Delivery is not available for this delivery location.';
        }

        return null;
    }

    public function getActiveRules(): Collection
    {
        return CodRule::active()->forCurrentTenant()->get();
    }

    public function getFirstEligibleRule(
        ?int $cityId = null,
        ?float $orderAmount = null
    ): ?CodRule {
        return $this->getEligibleRules($cityId, $orderAmount)->first();
    }

}
