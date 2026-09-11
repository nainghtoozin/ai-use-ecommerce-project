<?php

namespace App\Services;

use App\Models\CodRule;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;

class CodEligibilityService
{
    public function isCodAvailable(
        PaymentMethod $paymentMethod,
        ?User $user = null,
        ?int $cityId = null,
        ?float $orderAmount = null
    ): bool {
        if ($paymentMethod->type !== 'cod') {
            return false;
        }

        if ($user !== null && $this->isUserBlocked($user)) {
            return false;
        }

        if ($orderAmount === null) {
            return true;
        }

        $activeRules = $this->getActiveRules();

        if ($activeRules->isEmpty()) {
            return true;
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

    public function getCodFee(
        ?int $cityId = null,
        ?float $orderAmount = null
    ): float {
        $eligibleRules = $this->getEligibleRules($cityId, $orderAmount);

        if ($eligibleRules->isEmpty()) {
            return 0;
        }

        return (float) $eligibleRules->first()->cod_fee;
    }

    public function shouldApplyCodFeeToTotal(
        ?int $cityId = null,
        ?float $orderAmount = null
    ): bool {
        $eligibleRules = $this->getEligibleRules($cityId, $orderAmount);

        if ($eligibleRules->isEmpty()) {
            return false;
        }

        return (bool) $eligibleRules->first()->apply_cod_fee_to_total;
    }

    public function getIneligibilityReason(
        PaymentMethod $paymentMethod,
        ?User $user = null,
        ?int $cityId = null,
        ?float $orderAmount = null
    ): ?string {
        if ($paymentMethod->type !== 'cod') {
            return 'Selected payment method is not COD.';
        }

        if ($user !== null && $this->isUserBlocked($user)) {
            return 'Cash on Delivery is not available for your account.';
        }

        if ($orderAmount === null) {
            return null;
        }

        $activeRules = $this->getActiveRules();

        if ($activeRules->isEmpty()) {
            return null;
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

    private function isUserBlocked(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return !(bool) ($user->allow_cod ?? false);
    }
}
