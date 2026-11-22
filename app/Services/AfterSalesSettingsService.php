<?php

namespace App\Services;

use App\Models\CancellationSetting;
use App\Models\Order;
use App\Models\ReturnSetting;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class AfterSalesSettingsService
{
    private const CANCELLABLE_STATUSES = ['pending', 'confirmed', 'processing', 'shipped'];

    public function cancellationSettings(?int $tenantId = null): CancellationSetting
    {
        $tenantId = $tenantId ?? tenantId();

        if (!$tenantId) {
            throw new \RuntimeException('Cancellation settings require a tenant context.');
        }

        $settings = CancellationSetting::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->first();

        if ($settings) {
            return $settings;
        }

        return CancellationSetting::withoutTenantScope()->create(
            array_merge(CancellationSetting::defaults(), ['tenant_id' => $tenantId])
        );
    }

    public function returnSettings(?int $tenantId = null): ReturnSetting
    {
        $tenantId = $tenantId ?? tenantId();

        if (!$tenantId) {
            throw new \RuntimeException('Return settings require a tenant context.');
        }

        $settings = ReturnSetting::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->first();

        if ($settings) {
            return $settings;
        }

        return ReturnSetting::withoutTenantScope()->create(
            array_merge(ReturnSetting::defaults(), ['tenant_id' => $tenantId])
        );
    }

    public function updateCancellationSettings(Tenant $tenant, array $data): CancellationSetting
    {
        $settings = $this->cancellationSettings($tenant->id);

        $payload = [
            'customer_cancel_enabled' => (bool) ($data['customer_cancel_enabled'] ?? false),
            'merchant_cancel_enabled' => (bool) ($data['merchant_cancel_enabled'] ?? false),
            'instant_cancel_window_hours' => $this->nullableInt($data['instant_cancel_window_hours'] ?? null, 0, 8760),
            'max_cancellations' => $this->nullableInt($data['max_cancellations'] ?? null, 0, 1000),
            'limit_period_days' => max(1, min(365, (int) ($data['limit_period_days'] ?? 30))),
            'limit_exceeded_action' => in_array($data['limit_exceeded_action'] ?? '', ['block', 'require_approval'], true)
                ? $data['limit_exceeded_action']
                : 'require_approval',
            'allow_request_after_limit' => (bool) ($data['allow_request_after_limit'] ?? false),
            'require_reason' => (bool) ($data['require_reason'] ?? false),
            'require_note' => (bool) ($data['require_note'] ?? false),
            'refund_shipping_fee' => (bool) ($data['refund_shipping_fee'] ?? false),
            'status_rules' => $this->sanitizeStatusRules($data['status_rules'] ?? []),
            'reasons' => $this->sanitizeReasons($data['reasons'] ?? CancellationSetting::DEFAULT_REASONS),
        ];

        $settings->update($payload);

        return $settings->fresh();
    }

    public function updateReturnSettings(Tenant $tenant, array $data): ReturnSetting
    {
        $settings = $this->returnSettings($tenant->id);

        $payload = [
            'returns_enabled' => (bool) ($data['returns_enabled'] ?? false),
            'return_window_days' => max(0, min(365, (int) ($data['return_window_days'] ?? 7))),
            'allow_full_return' => (bool) ($data['allow_full_return'] ?? false),
            'allow_partial_return' => (bool) ($data['allow_partial_return'] ?? false),
            'require_reason' => (bool) ($data['require_reason'] ?? false),
            'require_note' => (bool) ($data['require_note'] ?? false),
            'require_evidence' => (bool) ($data['require_evidence'] ?? false),
            'allow_refund' => (bool) ($data['allow_refund'] ?? false),
            'allow_exchange' => (bool) ($data['allow_exchange'] ?? false),
            'allow_replacement' => (bool) ($data['allow_replacement'] ?? false),
            'return_shipping_responsibility' => in_array($data['return_shipping_responsibility'] ?? '', ['customer', 'merchant'], true)
                ? $data['return_shipping_responsibility']
                : 'customer',
            'shipping_responsibility_by_reason' => $this->sanitizeShippingMap($data['shipping_responsibility_by_reason'] ?? []),
            'refund_shipping_fee' => (bool) ($data['refund_shipping_fee'] ?? false),
            'reasons' => $this->sanitizeReasons($data['reasons'] ?? ReturnSetting::DEFAULT_REASONS),
            'instructions' => isset($data['instructions']) ? (string) $data['instructions'] : null,
            'policy_text' => isset($data['policy_text']) ? (string) $data['policy_text'] : null,
        ];

        $settings->update($payload);

        return $settings->fresh();
    }

    public function statusRules(CancellationSetting $settings): array
    {
        return array_merge(CancellationSetting::DEFAULT_RULES, $settings->status_rules ?? []);
    }

    public function modeFor(Order $order, CancellationSetting $settings): string
    {
        $rules = $this->statusRules($settings);

        return $rules[$order->order_status] ?? CancellationSetting::MODE_NOT_ALLOWED;
    }

    private function sanitizeStatusRules(array $rules): array
    {
        $sanitized = CancellationSetting::DEFAULT_RULES;
        $allowedModes = [
            CancellationSetting::MODE_INSTANT,
            CancellationSetting::MODE_APPROVAL,
            CancellationSetting::MODE_NOT_ALLOWED,
        ];

        foreach (self::CANCELLABLE_STATUSES as $status) {
            $mode = $rules[$status] ?? null;

            if (!in_array($mode, $allowedModes, true)) {
                continue;
            }

            if ($status === 'shipped' && $mode === CancellationSetting::MODE_INSTANT) {
                continue;
            }

            $sanitized[$status] = $mode;
        }

        $sanitized['delivered'] = CancellationSetting::MODE_RETURN_ONLY;

        return $sanitized;
    }

    private function sanitizeReasons(array $reasons): array
    {
        $clean = [];

        foreach ($reasons as $reason) {
            if (!is_array($reason)) {
                continue;
            }

            $label = trim((string) ($reason['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $code = trim((string) ($reason['code'] ?? ''));
            if ($code === '') {
                $code = \Illuminate\Support\Str::slug($label, '_');
            }

            $clean[] = ['code' => $code, 'label' => $label];
        }

        if (empty($clean)) {
            return CancellationSetting::DEFAULT_REASONS;
        }

        return array_slice($clean, 0, 25);
    }

    private function sanitizeShippingMap(array $map): array
    {
        $clean = [];

        foreach ($map as $code => $responsibility) {
            $code = trim((string) $code);
            if ($code === '' || !in_array($responsibility, ['customer', 'merchant'], true)) {
                continue;
            }
            $clean[$code] = $responsibility;
        }

        return $clean;
    }

    private function nullableInt(mixed $value, int $min, int $max): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max($min, min($max, (int) $value));
    }
}
